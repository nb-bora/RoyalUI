<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/LotStockService.php';
require_once __DIR__ . '/../services/DecisionEngine.php';
require_once __DIR__ . '/../services/FactureService.php';

$user = require_auth();

if ($method === 'GET' && $id) {
    $vente = db()->prepare(
        'SELECT v.*, c.nom AS client_nom, u.nom AS vendeur_nom
         FROM vente v
         LEFT JOIN client c ON c.id = v.id_client
         LEFT JOIN utilisateur u ON u.id = v.id_utilisateur
         WHERE v.id = ?'
    );
    $vente->execute([$id]);
    $row = $vente->fetch();
    if (!$row) {
        json_error('Vente introuvable', 404);
    }
    $lignes = db()->prepare(
        'SELECT lv.*, m.nom AS medicament_nom FROM ligne_vente lv JOIN medicament m ON m.id = lv.id_medicament WHERE lv.id_vente = ?'
    );
    $lignes->execute([$id]);
    $row['lignes'] = $lignes->fetchAll();
    $row['total'] = (float) $row['montant_total'] ?: array_sum(array_map(fn($l) => $l['quantite'] * $l['prix_vente'], $row['lignes']));
    json_response(['success' => true, 'data' => $row]);
}

if ($method === 'GET' && $action === 'mes-ventes') {
    $stmt = db()->prepare(
        "SELECT v.id, v.date_vente, c.nom AS client_nom, v.montant_total AS total, v.statut
         FROM vente v LEFT JOIN client c ON c.id = v.id_client
         WHERE v.id_utilisateur = ? AND DATE(v.date_vente) = CURDATE()
         ORDER BY v.date_vente DESC"
    );
    $stmt->execute([(int) $user['id']]);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'GET') {
    $where = $user['role'] === 'vendeur' ? "WHERE v.statut = 'validee'" : '';
    $rows = db()->query(
        "SELECT v.id, v.date_vente, v.statut, c.nom AS client_nom, u.nom AS vendeur_nom,
                COUNT(lv.id) AS nb_lignes,
                COALESCE(v.montant_total, SUM(lv.quantite * lv.prix_vente), 0) AS total
         FROM vente v
         LEFT JOIN client c ON c.id = v.id_client
         LEFT JOIN utilisateur u ON u.id = v.id_utilisateur
         LEFT JOIN ligne_vente lv ON lv.id_vente = v.id
         $where
         GROUP BY v.id, v.date_vente, v.statut, c.nom, u.nom, v.montant_total
         ORDER BY v.date_vente DESC
         LIMIT 200"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

// POST ventes/{id}/annuler
if ($method === 'POST' && $id && $subAction === 'annuler') {
    require_auth(['admin', 'gestionnaire']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare("SELECT * FROM vente WHERE id = ? AND statut = 'validee' FOR UPDATE");
        $v->execute([$id]);
        $vente = $v->fetch();
        if (!$vente) {
            throw new RuntimeException('Vente introuvable ou déjà annulée');
        }

        $lignes = $pdo->prepare('SELECT * FROM ligne_vente WHERE id_vente = ?');
        $lignes->execute([$id]);
        foreach ($lignes->fetchAll() as $lv) {
            $pdo->prepare('UPDATE medicament SET stock_actuel = stock_actuel + ? WHERE id = ?')
                ->execute([$lv['quantite'], $lv['id_medicament']]);
            LotStockService::restoreFromVente($pdo, (int) $lv['id']);
            $pdo->prepare(
                'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, ?, ?, ?)'
            )->execute([$lv['id_medicament'], $id, 'ENTREE', $lv['quantite']]);
        }

        $pdo->prepare("UPDATE vente SET statut = 'annulee' WHERE id = ?")->execute([$id]);
        FactureService::annulerByVente($id);
        $pdo->commit();
        audit((int) $user['id'], 'annulation_vente', 'vente', $id);
        json_response(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

if ($method === 'POST') {
    $data = read_json();
    $lignes = $data['lignes'] ?? [];
    $idClient = !empty($data['id_client']) ? (int) $data['id_client'] : null;

    if (!is_array($lignes) || count($lignes) === 0) {
        json_error('Au moins une ligne de vente est requise');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach ($lignes as $ligne) {
            $idMed = (int) ($ligne['id_medicament'] ?? 0);
            $qte = (int) ($ligne['quantite'] ?? 0);
            if ($idMed <= 0 || $qte <= 0) {
                throw new RuntimeException('Ligne de vente invalide');
            }

            $med = $pdo->prepare('SELECT id, nom, stock_actuel, prix_vente FROM medicament WHERE id = ? AND actif = 1 FOR UPDATE');
            $med->execute([$idMed]);
            $medicament = $med->fetch();
            if (!$medicament) {
                throw new RuntimeException('Médicament introuvable');
            }
            if ((int) $medicament['stock_actuel'] < $qte) {
                throw new RuntimeException(
                    "Stock insuffisant pour {$medicament['nom']} (disponible : {$medicament['stock_actuel']})"
                );
            }
        }

        $stmt = $pdo->prepare('INSERT INTO vente (id_client, id_utilisateur, statut, montant_total) VALUES (?, ?, ?, 0)');
        $stmt->execute([$idClient, (int) $user['id'], 'validee']);
        $idVente = (int) $pdo->lastInsertId();

        $total = 0;
        foreach ($lignes as $ligne) {
            $idMed = (int) $ligne['id_medicament'];
            $qte = (int) $ligne['quantite'];
            $prixStmt = $pdo->prepare('SELECT prix_vente FROM medicament WHERE id = ?');
            $prixStmt->execute([$idMed]);
            $prix = isset($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $prixStmt->fetchColumn();

            $pdo->prepare('INSERT INTO ligne_vente (id_vente, id_medicament, quantite, prix_vente) VALUES (?, ?, ?, ?)')
                ->execute([$idVente, $idMed, $qte, $prix]);
            $idLigneVente = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE medicament SET stock_actuel = stock_actuel - ? WHERE id = ?')
                ->execute([$qte, $idMed]);

            LotStockService::consumeFefo($pdo, $idMed, $qte, $idLigneVente);

            $pdo->prepare(
                'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, ?, ?, ?)'
            )->execute([$idMed, $idVente, 'SORTIE', $qte]);

            $total += $qte * $prix;
        }

        $pdo->prepare('UPDATE vente SET montant_total = ? WHERE id = ?')->execute([$total, $idVente]);

        $modePaiement = strtoupper(trim($data['mode_paiement'] ?? 'ESPECES'));
        $idFacture = FactureService::createFromVente($pdo, $idVente, $total, $modePaiement);

        $pdo->commit();
        audit((int) $user['id'], 'vente', 'vente', $idVente);

        try {
            DecisionEngine::run((int) $user['id']);
        } catch (Throwable) {
        }

        json_response([
            'success' => true,
            'id' => $idVente,
            'total' => $total,
            'id_facture' => $idFacture,
            'ticket_url' => "api/index.php?r=tickets/$idVente",
            'facture_html_url' => "api/index.php?r=factures/$idFacture/html",
            'facture_pdf_url' => "api/index.php?r=factures/$idFacture/pdf",
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

json_error('Méthode non autorisée', 405);
