<?php
declare(strict_types=1);

class ActiviteService
{
    public static function log(int $userId, string $action, string $page, ?array $meta = null): void
    {
        try {
            db()->prepare(
                'INSERT INTO activite_utilisateur (id_utilisateur, action, page, meta) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $action, $page, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
        } catch (Throwable) {
        }
    }

    public static function getTopProduitsVendeur(int $userId, int $limit = 10): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT m.id, m.nom, SUM(lv.quantite) AS qte
                 FROM ligne_vente lv
                 JOIN vente v ON v.id = lv.id_vente
                 JOIN medicament m ON m.id = lv.id_medicament
                 WHERE v.id_utilisateur = ? AND v.statut = 'validee'
                   AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY m.id, m.nom ORDER BY qte DESC LIMIT ?"
            );
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function statsVendeurJour(int $userId): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT COUNT(DISTINCT v.id) AS nb_ventes,
                        COALESCE(SUM(lv.quantite * lv.prix_vente), 0) AS ca
                 FROM vente v
                 LEFT JOIN ligne_vente lv ON lv.id_vente = v.id
                 WHERE v.id_utilisateur = ? AND v.statut = 'validee' AND DATE(v.date_vente) = CURDATE()"
            );
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: ['nb_ventes' => 0, 'ca' => 0];
        } catch (Throwable) {
            $stmt = db()->prepare(
                "SELECT COUNT(DISTINCT v.id) AS nb_ventes,
                        COALESCE(SUM(lv.quantite * lv.prix_vente), 0) AS ca
                 FROM vente v
                 LEFT JOIN ligne_vente lv ON lv.id_vente = v.id
                 WHERE DATE(v.date_vente) = CURDATE()"
            );
            $stmt->execute();
            return $stmt->fetch() ?: ['nb_ventes' => 0, 'ca' => 0];
        }
    }
}
