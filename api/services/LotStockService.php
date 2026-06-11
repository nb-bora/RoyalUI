<?php
declare(strict_types=1);

class LotStockService
{
    /** Crée un lot à la réception achat */
    public static function createFromAchat(int $idMed, int $idLigneAchat, int $qte, string $peremption, float $prix): int
    {
        $stmt = db()->prepare(
            'INSERT INTO lot_stock (id_medicament, id_ligne_achat, quantite_initiale, quantite_restante, date_peremption, prix_achat)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$idMed, $idLigneAchat, $qte, $qte, $peremption, $prix]);
        return (int) db()->lastInsertId();
    }

    /** FEFO : consomme les lots les plus proches de péremption */
    public static function consumeFefo(PDO $pdo, int $idMed, int $qte, int $idLigneVente): void
    {
        $lots = $pdo->prepare(
            'SELECT id, quantite_restante FROM lot_stock
             WHERE id_medicament = ? AND quantite_restante > 0
             ORDER BY date_peremption ASC, id ASC
             FOR UPDATE'
        );
        $lots->execute([$idMed]);
        $rows = $lots->fetchAll();

        $remaining = $qte;
        foreach ($rows as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $lot['quantite_restante']);
            $pdo->prepare('UPDATE lot_stock SET quantite_restante = quantite_restante - ? WHERE id = ?')
                ->execute([$take, $lot['id']]);
            $pdo->prepare('INSERT INTO ligne_vente_lot (id_ligne_vente, id_lot, quantite) VALUES (?, ?, ?)')
                ->execute([$idLigneVente, $lot['id'], $take]);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException("Lots FEFO insuffisants (manque $remaining unités)");
        }
    }

    /** Remet en stock lors d'annulation vente */
    public static function restoreFromVente(PDO $pdo, int $idLigneVente): void
    {
        $lots = $pdo->prepare(
            'SELECT id_lot, quantite FROM ligne_vente_lot WHERE id_ligne_vente = ?'
        );
        $lots->execute([$idLigneVente]);
        foreach ($lots->fetchAll() as $row) {
            $pdo->prepare('UPDATE lot_stock SET quantite_restante = quantite_restante + ? WHERE id = ?')
                ->execute([$row['quantite'], $row['id_lot']]);
        }
    }

    public static function listByMedicament(int $idMed): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM lot_stock WHERE id_medicament = ? AND quantite_restante > 0 ORDER BY date_peremption ASC'
        );
        $stmt->execute([$idMed]);
        return $stmt->fetchAll();
    }
}
