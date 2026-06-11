<?php
declare(strict_types=1);

require_auth(['admin']);

if ($method !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$limit = min(500, max(50, (int) ($_GET['limit'] ?? 100)));
$rows = db()->query(
    "SELECT a.*, u.nom AS utilisateur_nom
     FROM audit_log a
     LEFT JOIN utilisateur u ON u.id = a.id_utilisateur
     ORDER BY a.created_at DESC
     LIMIT $limit"
)->fetchAll();

$stats = db()->query(
    "SELECT action, COUNT(*) AS nb FROM audit_log
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY action ORDER BY nb DESC LIMIT 10"
)->fetchAll();

json_response(['success' => true, 'data' => $rows, 'stats' => $stats]);
