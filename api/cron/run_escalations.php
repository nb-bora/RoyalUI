<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

$pdo = db();
$ruptures = (int) $pdo->query("SELECT COUNT(*) FROM medicament WHERE actif = 1 AND stock_actuel <= 0")->fetchColumn();

if ($ruptures > 0) {
    $old = $pdo->query(
        "SELECT COUNT(*) FROM notification WHERE type = 'ESCALADE_RUPTURE' AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)"
    )->fetchColumn();
    if ((int) $old === 0) {
        NotificationService::notifyRole('admin', 'ESCALADE_RUPTURE',
            "$ruptures rupture(s) non résolues depuis 48h",
            'Intervention admin requise immédiatement.',
            'critique', 'stock.html#etat');
        echo "Escalade envoyée pour $ruptures ruptures.\n";
    } else {
        echo "Escalade déjà envoyée récemment.\n";
    }
} else {
    echo "Aucune rupture.\n";
}
