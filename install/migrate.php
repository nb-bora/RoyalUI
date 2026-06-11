<?php
declare(strict_types=1);

/**
 * Migration v2 — autonomie intelligente
 * php install/migrate.php
 * http://localhost/RoyalUI/install/migrate.php
 */
require_once dirname(__DIR__) . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $sql = file_get_contents(__DIR__ . '/migrate_v2.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || stripos($stmt, 'USE ') === 0) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')
                || str_contains($e->getMessage(), 'already exists')
                || str_contains($e->getMessage(), 'Duplicate key')) {
                continue;
            }
            throw $e;
        }
    }
    echo "Migration v2 terminée avec succès.\n";
    echo "Tables : notification, recommandation, lot_stock, inventaire, bon_commande, etc.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur migration : ' . $e->getMessage() . "\n";
}
