<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

cors();
start_session();

$method = $_SERVER['REQUEST_METHOD'];
$route = trim($_GET['r'] ?? '', '/');
$parts = $route !== '' ? explode('/', $route) : [];
$resource = $parts[0] ?? '';
$id = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : null;
$action = $parts[1] ?? null;

try {
    match ($resource) {
        'auth' => require __DIR__ . '/routes/auth.php',
        'dashboard' => require __DIR__ . '/routes/dashboard.php',
        'alertes' => require __DIR__ . '/routes/alertes.php',
        'categories' => require __DIR__ . '/routes/categories.php',
        'medicaments' => require __DIR__ . '/routes/medicaments.php',
        'clients' => require __DIR__ . '/routes/clients.php',
        'fournisseurs' => require __DIR__ . '/routes/fournisseurs.php',
        'utilisateurs' => require __DIR__ . '/routes/utilisateurs.php',
        'ventes' => require __DIR__ . '/routes/ventes.php',
        'achats' => require __DIR__ . '/routes/achats.php',
        'stock' => require __DIR__ . '/routes/stock.php',
        'rapports' => require __DIR__ . '/routes/rapports.php',
        default => json_error('Route introuvable', 404),
    };
} catch (PDOException $e) {
    json_error('Erreur base de données : ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}
