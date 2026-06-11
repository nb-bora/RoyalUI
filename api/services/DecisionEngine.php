<?php
declare(strict_types=1);

require_once __DIR__ . '/ParametresService.php';
require_once __DIR__ . '/NotificationService.php';

class DecisionEngine
{
    public static function run(?int $triggerUserId = null): array
    {
        $pdo = db();
        $params = ParametresService::get();
        $seuilMarge = (float) ($params['seuil_marge_min'] ?? 15);
        $seuilPerempUrgent = ParametresService::regleInt('seuil_peremption_urgent', 7);
        $seuilSurstock = ParametresService::regleInt('seuil_surstock_ratio', 3);
        $joursDormant = ParametresService::regleInt('stock_dormant_jours', 90);
        $delaiFournisseur = (int) ($params['delai_fournisseur_jours'] ?? 3);

        $created = ['notifications' => 0, 'recommandations' => 0];

        // ——— Stock critique / rupture imminente (vélocité) ———
        $meds = $pdo->query(
            "SELECT m.id, m.nom, m.stock_actuel, m.stock_min,
                    COALESCE(SUM(lv.quantite), 0) / 30 AS velocite_jour
             FROM medicament m
             LEFT JOIN ligne_vente lv ON lv.id_medicament = m.id
             LEFT JOIN vente v ON v.id = lv.id_vente AND v.statut = 'validee'
               AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             WHERE m.actif = 1
             GROUP BY m.id, m.nom, m.stock_actuel, m.stock_min"
        )->fetchAll();

        $defaultFournisseur = $pdo->query('SELECT id, nom FROM fournisseur ORDER BY id ASC LIMIT 1')->fetch();

        foreach ($meds as $m) {
            $stock = (int) $m['stock_actuel'];
            $min = (int) $m['stock_min'];
            $vel = max((float) $m['velocite_jour'], 0.1);
            $joursRestants = $stock > 0 ? $stock / $vel : 0;
            $besoin = max($min * 2 - $stock, $min);

            if ($stock <= 0) {
                self::addReco($pdo, 'COMMANDE_SUGGEREE', $m, $defaultFournisseur, 95,
                    "Rupture : {$m['nom']}",
                    "Commander $besoin unités en urgence",
                    ['quantite' => $besoin, 'jours_restants' => 0], $created);
                NotificationService::notifyRole('gestionnaire', 'STOCK_CRITIQUE',
                    "Rupture : {$m['nom']}",
                    "Stock à zéro. Commander environ $besoin unités.",
                    'critique', 'achats.html#reception', (int) $m['id']);
                $created['notifications']++;
            } elseif ($joursRestants <= $delaiFournisseur + 1) {
                $fourNom = $defaultFournisseur['nom'] ?? 'fournisseur';
                self::addReco($pdo, 'COMMANDE_SUGGEREE', $m, $defaultFournisseur, 80,
                    "Rupture imminente : {$m['nom']}",
                    "Commander $besoin unités chez $fourNom avant " . date('d/m', strtotime("+$delaiFournisseur days")),
                    ['quantite' => $besoin, 'jours_restants' => round($joursRestants, 1)], $created);
                NotificationService::notifyRole('gestionnaire', 'STOCK_CRITIQUE',
                    "Rupture imminente : {$m['nom']}",
                    "Stock épuisé dans ~" . round($joursRestants, 1) . " j (vélocité). Commander $besoin unités.",
                    'haute', 'achats.html#reception', (int) $m['id']);
                $created['notifications']++;
            } elseif ($stock <= $min) {
                self::addReco($pdo, 'REAPPRO', $m, $defaultFournisseur, 60,
                    "Stock bas : {$m['nom']}",
                    "Commander environ $besoin unités",
                    ['quantite' => $besoin], $created);
            }

            // Surstock
            if ($stock > $min * $seuilSurstock && $min > 0) {
                NotificationService::notifyRole('gestionnaire', 'SURSTOCK',
                    "Surstock : {$m['nom']}",
                    "Stock ($stock) > {$seuilSurstock}× minimum ($min). Réduire les commandes.",
                    'info', 'stock.html#etat', (int) $m['id']);
                $created['notifications']++;
            }

            // Marge incohérente
            $medFull = $pdo->prepare('SELECT prix_achat, prix_vente FROM medicament WHERE id = ?');
            $medFull->execute([$m['id']]);
            $prices = $medFull->fetch();
            if ($prices) {
                $pa = (float) $prices['prix_achat'];
                $pv = (float) $prices['prix_vente'];
                if ($pa > 0) {
                    $marge = (($pv - $pa) / $pa) * 100;
                    if ($pv < $pa || $marge < $seuilMarge) {
                        NotificationService::notifyRole('admin', 'ANOMALIE_PRIX',
                            "Prix incohérent : {$m['nom']}",
                            $pv < $pa ? 'Prix vente < prix achat !' : "Marge {$marge}% < seuil {$seuilMarge}%",
                            'haute', 'medicaments.html#liste', (int) $m['id']);
                        $created['notifications']++;
                    }
                }
            }
        }

        // ——— Péremption urgente ———
        $peremp = $pdo->prepare(
            "SELECT m.id, m.nom, ls.date_peremption, ls.quantite_restante,
                    DATEDIFF(ls.date_peremption, CURDATE()) AS jours
             FROM lot_stock ls
             JOIN medicament m ON m.id = ls.id_medicament
             WHERE ls.quantite_restante > 0 AND ls.date_peremption <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY ls.date_peremption ASC"
        );
        $peremp->execute([$seuilPerempUrgent]);
        foreach ($peremp->fetchAll() as $p) {
            $priorite = (int) $p['jours'] <= 7 ? 'critique' : 'haute';
            NotificationService::notifyRole('gestionnaire', 'PEREMPTION_URGENTE',
                "Péremption J-{$p['jours']} : {$p['nom']}",
                "{$p['quantite_restante']} unités expirent le " . date('d/m/Y', strtotime($p['date_peremption'])) . ". Promo ou retour fournisseur.",
                $priorite, 'stock.html#etat', (int) $p['id']);
            self::addReco($pdo, 'PROMO_PEREMPTION', $p, null, 70,
                "Promo péremption : {$p['nom']}",
                "Proposer -20% en caisse sur ce lot (J-{$p['jours']})",
                ['remise_pct' => 20, 'jours' => $p['jours']], $created);
            $created['notifications']++;
        }

        // ——— Stock dormant ———
        $dormant = $pdo->query(
            "SELECT m.id, m.nom, m.stock_actuel, m.prix_achat
             FROM medicament m
             WHERE m.actif = 1 AND m.stock_actuel > 0
             AND m.id NOT IN (
                 SELECT DISTINCT lv.id_medicament FROM ligne_vente lv
                 JOIN vente v ON v.id = lv.id_vente
                 WHERE v.statut = 'validee' AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL $joursDormant DAY)
             )"
        )->fetchAll();
        foreach ($dormant as $d) {
            NotificationService::notifyRole('gestionnaire', 'STOCK_DORMANT',
                "Stock dormant : {$d['nom']}",
                "Aucune vente depuis {$joursDormant}j. " . $d['stock_actuel'] . " unités immobilisées.",
                'info', 'rapports.html', (int) $d['id']);
            $created['notifications']++;
        }

        // ——— Objectif CA ———
        $objectif = (float) ($params['objectif_ca_jour'] ?? 0);
        if ($objectif > 0) {
            $caJour = (float) $pdo->query(
                "SELECT COALESCE(SUM(lv.quantite * lv.prix_vente), 0)
                 FROM ligne_vente lv JOIN vente v ON v.id = lv.id_vente
                 WHERE v.statut = 'validee' AND DATE(v.date_vente) = CURDATE()"
            )->fetchColumn();
            $hour = (int) date('H');
            if ($hour >= 18 && $caJour < $objectif) {
                NotificationService::notifyRole('admin', 'OBJECTIF_CA',
                    'Objectif CA non atteint',
                    'CA jour : ' . number_format($caJour, 0, ',', ' ') . ' / ' . number_format($objectif, 0, ',', ' ') . ' FCFA',
                    'haute', 'rapports.html');
                $created['notifications']++;
            } elseif ($caJour >= $objectif) {
                NotificationService::notifyRole('admin', 'OBJECTIF_CA',
                    'Objectif CA atteint !',
                    'CA jour : ' . number_format($caJour, 0, ',', ' ') . ' FCFA',
                    'info', 'home.html');
                $created['notifications']++;
            }
        }

        // Escalade : ruptures non traitées depuis 48h
        $escalade = $pdo->query(
            "SELECT COUNT(*) FROM medicament WHERE actif = 1 AND stock_actuel <= 0"
        )->fetchColumn();
        if ((int) $escalade > 0) {
            NotificationService::notifyRole('admin', 'ESCALADE_RUPTURE',
                "$escalade rupture(s) active(s)",
                'Ruptures non résolues — action gestionnaire requise.',
                'critique', 'stock.html#etat');
            $created['notifications']++;
        }

        if ($triggerUserId) {
            ActiviteService::log($triggerUserId, 'decision_engine', 'cron', $created);
        }

        return $created;
    }

    private static function addReco(PDO $pdo, string $type, array $m, ?array $four, int $priorite, string $titre, string $msg, array $payload, array &$created): void
    {
        $idMed = (int) ($m['id'] ?? 0);
        $exists = $pdo->prepare(
            "SELECT id FROM recommandation WHERE type = ? AND id_medicament = ? AND statut IN ('nouvelle','vue') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $exists->execute([$type, $idMed]);
        if ($exists->fetch()) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO recommandation (type, id_medicament, id_fournisseur, priorite, score, titre, message, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type, $idMed, $four ? (int) $four['id'] : null,
            $priorite, $priorite, $titre, $msg,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $created['recommandations']++;
    }

    /** Briefing personnalisé au login */
    public static function briefing(array $user): array
    {
        $role = $user['role'];
        $uid = (int) $user['id'];
        $hour = (int) date('H');
        $salutation = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');

        $data = ['salutation' => "$salutation {$user['nom']}", 'role' => $role, 'sections' => []];

        if ($role === 'vendeur') {
            $stats = ActiviteService::statsVendeurJour($uid);
            $data['sections'][] = [
                'type' => 'stats',
                'text' => "{$stats['nb_ventes']} vente(s) aujourd'hui — " . number_format((float) $stats['ca'], 0, ',', ' ') . ' FCFA',
            ];
            $favs = ActiviteService::getTopProduitsVendeur($uid, 5);
            if ($favs) {
                $data['sections'][] = ['type' => 'favoris', 'produits' => $favs];
            }
            $data['actions'] = [
                ['label' => 'Caisse', 'href' => 'caisse.html', 'primary' => true],
                ['label' => 'Mes ventes', 'href' => 'ventes.html'],
            ];
        } elseif ($role === 'gestionnaire') {
            $alertes = NotificationService::countUnread($user);
            try {
                $recos = (int) db()->query("SELECT COUNT(*) FROM recommandation WHERE statut = 'nouvelle'")->fetchColumn();
            } catch (Throwable) {
                $recos = 0;
            }
            $data['sections'][] = [
                'type' => 'alertes',
                'text' => "$alertes notification(s) | $recos recommandation(s) en attente",
            ];
            $data['actions'] = [
                ['label' => 'Commandes suggérées', 'href' => 'home.html#recommandations', 'primary' => true],
                ['label' => 'Stock', 'href' => 'stock.html#etat'],
                ['label' => 'Achats', 'href' => 'achats.html#reception'],
            ];
        } else {
            $dash = db()->query(
                "SELECT
                    (SELECT COALESCE(SUM(lv.quantite * lv.prix_vente), 0) FROM ligne_vente lv JOIN vente v ON v.id = lv.id_vente WHERE v.statut = 'validee' AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS ca_semaine,
                    (SELECT COUNT(*) FROM utilisateur WHERE role = 'vendeur') AS nb_vendeurs"
            )->fetch();
            $data['sections'][] = [
                'type' => 'pilotage',
                'text' => 'CA semaine : ' . number_format((float) $dash['ca_semaine'], 0, ',', ' ') . ' FCFA',
            ];
            $data['actions'] = [
                ['label' => 'Rapports', 'href' => 'rapports.html', 'primary' => true],
                ['label' => 'Utilisateurs', 'href' => 'utilisateurs.html'],
                ['label' => 'Paramètres', 'href' => 'parametres.html'],
            ];
        }

        $data['notifications_non_lues'] = NotificationService::countUnread($user);
        return $data;
    }
}

require_once __DIR__ . '/ActiviteService.php';
