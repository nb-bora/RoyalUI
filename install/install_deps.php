<?php
declare(strict_types=1);

/**
 * Installe les dépendances Composer (PDF + Excel)
 * http://localhost/RoyalUI/install/install_deps.php
 */
header('Content-Type: text/plain; charset=utf-8');

$apiDir = dirname(__DIR__) . '/api';
chdir($apiDir);

if (!file_exists($apiDir . '/composer.json')) {
    http_response_code(500);
    echo "composer.json introuvable.\n";
    exit(1);
}

$cmd = 'composer install --no-interaction --ignore-platform-req=ext-gd 2>&1';
$output = shell_exec($cmd);
echo $output ?: "Exécutez manuellement : cd api && composer install\n";
echo "\nAssurez-vous que l'extension PHP gd est activée dans php.ini (XAMPP).\n";
