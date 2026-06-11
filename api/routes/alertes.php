<?php
declare(strict_types=1);

require_auth();

if ($method !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$pdo = db();

$stockBas = $pdo->query(
    "SELECT id, nom, stock_actuel, stock_min
     FROM medicament
     WHERE stock_actuel <= stock_min
     ORDER BY stock_actuel ASC
     LIMIT 20"
)->fetchAll();

$peremption = $pdo->query(
    "SELECT m.id, m.nom, la.date_peremption, la.quantite,
            DATEDIFF(la.date_peremption, CURDATE()) AS jours_restants
     FROM ligne_achat la
     JOIN medicament m ON m.id = la.id_medicament
     WHERE la.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY la.date_peremption ASC
     LIMIT 20"
)->fetchAll();

$reappro = $pdo->query(
    "SELECT m.id, m.nom, m.stock_actuel, m.stock_min,
            COALESCE(SUM(lv.quantite), 0) AS ventes_30j
     FROM medicament m
     LEFT JOIN ligne_vente lv ON lv.id_medicament = m.id
     LEFT JOIN vente v ON v.id = lv.id_vente AND v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     WHERE m.stock_actuel <= m.stock_min
     GROUP BY m.id, m.nom, m.stock_actuel, m.stock_min
     HAVING ventes_30j > 0
     ORDER BY ventes_30j DESC
     LIMIT 10"
)->fetchAll();

foreach ($reappro as &$item) {
    $besoin = max($item['stock_min'] * 2 - $item['stock_actuel'], $item['stock_min']);
    $item['quantite_suggeree'] = (int) $besoin;
    $item['message'] = "Commander environ {$besoin} unités";
}
unset($item);

json_response([
    'success' => true,
    'stock_bas' => $stockBas,
    'peremption' => $peremption,
    'reappro' => $reappro,
    'total' => count($stockBas) + count($peremption),
]);
