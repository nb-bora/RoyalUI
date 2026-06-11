<?php
declare(strict_types=1);

require_once __DIR__ . '/ParametresService.php';

class FactureService
{
    public static function generateNumero(PDO $pdo): string
    {
        $params = ParametresService::get();
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($params['prefixe_facture'] ?? 'FA')) ?: 'FA';
        $year = date('Y');
        $stmt = $pdo->prepare(
            "SELECT numero_facture FROM facture WHERE numero_facture LIKE ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(["$prefix-$year-%"]);
        $last = $stmt->fetchColumn();
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('%s-%s-%05d', $prefix, $year, $seq);
    }

    public static function createFromVente(PDO $pdo, int $idVente, float $total, string $modePaiement = 'ESPECES'): int
    {
        $existing = $pdo->prepare('SELECT id FROM facture WHERE id_vente = ?');
        $existing->execute([$idVente]);
        $found = $existing->fetchColumn();
        if ($found) {
            return (int) $found;
        }

        $params = ParametresService::get();
        $tauxTva = (float) ($params['taux_tva'] ?? 0);
        $montantTtc = round($total, 2);
        if ($tauxTva > 0) {
            $montantHt = round($montantTtc / (1 + $tauxTva / 100), 2);
            $montantTva = round($montantTtc - $montantHt, 2);
        } else {
            $montantHt = $montantTtc;
            $montantTva = 0;
        }

        $numero = self::generateNumero($pdo);
        $pdo->prepare(
            'INSERT INTO facture (id_vente, numero_facture, montant_ht, montant_tva, montant_ttc, taux_tva, mode_paiement, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $idVente,
            $numero,
            $montantHt,
            $montantTva,
            $montantTtc,
            $tauxTva,
            strtoupper($modePaiement),
            'emise',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function annulerByVente(int $idVente): void
    {
        db()->prepare("UPDATE facture SET statut = 'annulee' WHERE id_vente = ? AND statut = 'emise'")
            ->execute([$idVente]);
    }

    public static function getFullData(int $idFacture): ?array
    {
        $stmt = db()->prepare(
            'SELECT f.*, v.date_vente, v.id AS id_vente,
                    c.nom AS client_nom, c.telephone AS client_telephone,
                    u.nom AS vendeur_nom
             FROM facture f
             JOIN vente v ON v.id = f.id_vente
             LEFT JOIN client c ON c.id = v.id_client
             LEFT JOIN utilisateur u ON u.id = v.id_utilisateur
             WHERE f.id = ?'
        );
        $stmt->execute([$idFacture]);
        $facture = $stmt->fetch();
        if (!$facture) {
            return null;
        }

        $lignesStmt = db()->prepare(
            'SELECT lv.id, lv.quantite, lv.prix_vente, m.nom AS medicament_nom, m.code_barre,
                    cat.nom AS categorie_nom
             FROM ligne_vente lv
             JOIN medicament m ON m.id = lv.id_medicament
             LEFT JOIN categorie cat ON cat.id = m.id_categorie
             WHERE lv.id_vente = ?
             ORDER BY lv.id'
        );
        $lignesStmt->execute([(int) $facture['id_vente']]);
        $lignes = $lignesStmt->fetchAll();

        foreach ($lignes as &$ligne) {
            $lots = db()->prepare(
                'SELECT lvl.quantite, ls.id AS lot_id, ls.date_peremption
                 FROM ligne_vente_lot lvl
                 JOIN lot_stock ls ON ls.id = lvl.id_lot
                 WHERE lvl.id_ligne_vente = ?'
            );
            $lots->execute([(int) $ligne['id']]);
            $ligne['lots'] = $lots->fetchAll();
            $ligne['total_ligne'] = (float) $ligne['quantite'] * (float) $ligne['prix_vente'];
        }
        unset($ligne);

        $facture['lignes'] = $lignes;
        $facture['parametres'] = ParametresService::get();

        return $facture;
    }

    public static function getByVenteId(int $idVente): ?array
    {
        $stmt = db()->prepare('SELECT * FROM facture WHERE id_vente = ? LIMIT 1');
        $stmt->execute([$idVente]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
