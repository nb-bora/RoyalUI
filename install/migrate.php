<?php
declare(strict_types=1);

/**
 * Migrations PharmaRoyal (v2 + v3)
 * php install/migrate.php
 * http://localhost/RoyalUI/install/migrate.php
 */
require_once dirname(__DIR__) . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');

function runMigrationFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || stripos($stmt, 'USE ') === 0) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')
                || str_contains($e->getMessage(), 'already exists')
                || str_contains($e->getMessage(), 'Duplicate key')
                || str_contains($e->getMessage(), 'Duplicate entry')) {
                continue;
            }
            throw $e;
        }
    }
}

try {
    $pdo = db();
    runMigrationFile($pdo, __DIR__ . '/migrate_v2.sql');
    echo "Migration v2 OK.\n";
    runMigrationFile($pdo, __DIR__ . '/migrate_v3.sql');
    echo "Migration v3 OK (factures, import, paramètres légaux).\n";
    echo "Pour PDF/Excel : cd api && composer install\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur migration : ' . $e->getMessage() . "\n";
}
