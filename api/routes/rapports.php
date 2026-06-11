<?php
declare(strict_types=1);

require_auth(['admin', 'gestionnaire']);

if ($method !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$pdo = db();
$periode = (int) ($_GET['jours'] ?? 30);

$ca = (float) $pdo->query(
    "SELECT COALESCE(SUM(lv.quantite * lv.prix_vente), 0)
     FROM ligne_vente lv JOIN vente v ON v.id = lv.id_vente
     WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL {$periode} DAY)"
)->fetchColumn();

$topVentes = $pdo->query(
    "SELECT m.nom, SUM(lv.quantite) AS qte, SUM(lv.quantite * lv.prix_vente) AS ca
     FROM ligne_vente lv
     JOIN medicament m ON m.id = lv.id_medicament
     JOIN vente v ON v.id = lv.id_vente
     WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL {$periode} DAY)
     GROUP BY m.id, m.nom ORDER BY ca DESC LIMIT 10"
)->fetchAll();

$marges = $pdo->query(
    "SELECT c.nom AS categorie,
            SUM(lv.quantite * lv.prix_vente) AS ca,
            SUM(lv.quantite * m.prix_achat) AS cout
     FROM ligne_vente lv
     JOIN medicament m ON m.id = lv.id_medicament
     LEFT JOIN categorie c ON c.id = m.id_categorie
     JOIN vente v ON v.id = lv.id_vente
     WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL {$periode} DAY)
     GROUP BY c.nom ORDER BY ca DESC"
)->fetchAll();

$stockMort = $pdo->query(
    "SELECT m.nom, m.stock_actuel, m.prix_achat
     FROM medicament m
     WHERE m.actif = 1 AND m.stock_actuel > 0
     AND m.id NOT IN (
         SELECT DISTINCT lv.id_medicament FROM ligne_vente lv
         JOIN vente v ON v.id = lv.id_vente
         WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
     )
     ORDER BY m.stock_actuel DESC LIMIT 15"
)->fetchAll();

$export = $_GET['export'] ?? '';

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_' . $periode . 'j.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rapport PharmaRoyal', $periode . ' jours'], ';');
    fputcsv($out, ['CA période', $ca], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Top ventes', 'Qté', 'CA'], ';');
    foreach ($topVentes as $r) {
        fputcsv($out, [$r['nom'], $r['qte'], $r['ca']], ';');
    }
    fclose($out);
    exit;
}

json_response([
    'success' => true,
    'ca_periode' => $ca,
    'top_ventes' => $topVentes,
    'marges_categories' => $marges,
    'stock_mort' => $stockMort,
]);
