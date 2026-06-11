<?php
declare(strict_types=1);

require_auth();

if ($method !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$pdo = db();

$caJour = (float) $pdo->query(
    "SELECT COALESCE(SUM(lv.quantite * lv.prix_vente), 0)
     FROM ligne_vente lv
     JOIN vente v ON v.id = lv.id_vente
     WHERE DATE(v.date_vente) = CURDATE()"
)->fetchColumn();

$ventesJour = (int) $pdo->query(
    "SELECT COUNT(*) FROM vente WHERE DATE(date_vente) = CURDATE()"
)->fetchColumn();

$stockBas = (int) $pdo->query(
    "SELECT COUNT(*) FROM medicament WHERE stock_actuel <= stock_min AND stock_actuel > 0"
)->fetchColumn();

$ruptures = (int) $pdo->query(
    "SELECT COUNT(*) FROM medicament WHERE stock_actuel <= 0"
)->fetchColumn();

$peremption = (int) $pdo->query(
    "SELECT COUNT(DISTINCT la.id_medicament)
     FROM ligne_achat la
     WHERE la.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
)->fetchColumn();

$ventes7j = $pdo->query(
    "SELECT DATE(v.date_vente) AS jour, COALESCE(SUM(lv.quantite * lv.prix_vente), 0) AS total
     FROM vente v
     LEFT JOIN ligne_vente lv ON lv.id_vente = v.id
     WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(v.date_vente)
     ORDER BY jour ASC"
)->fetchAll();

$topProduits = $pdo->query(
    "SELECT m.nom, SUM(lv.quantite) AS qte
     FROM ligne_vente lv
     JOIN medicament m ON m.id = lv.id_medicament
     JOIN vente v ON v.id = lv.id_vente
     WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY m.id, m.nom
     ORDER BY qte DESC
     LIMIT 5"
)->fetchAll();

json_response([
    'success' => true,
    'kpis' => [
        'ca_jour' => $caJour,
        'ventes_jour' => $ventesJour,
        'stock_bas' => $stockBas,
        'ruptures' => $ruptures,
        'peremption_30j' => $peremption,
    ],
    'ventes_7j' => $ventes7j,
    'top_produits' => $topProduits,
]);
