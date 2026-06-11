<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

cors();
start_session();

$method = $_SERVER['REQUEST_METHOD'];
$route = trim($_GET['r'] ?? '', '/');
// Ancien format JS : r=rapports?jours=30 → extraire le chemin et fusionner les params
if (str_contains($route, '?')) {
    [$pathOnly, $queryTail] = explode('?', $route, 2);
    $route = $pathOnly;
    parse_str($queryTail, $embedded);
    if (is_array($embedded)) {
        $_GET = array_merge($embedded, $_GET);
    }
}
$parts = $route !== '' ? explode('/', $route) : [];
$resource = $parts[0] ?? '';
$id = null;
$action = null;
$subAction = null;
if (isset($parts[1]) && $parts[1] !== '') {
    if (ctype_digit($parts[1])) {
        $id = (int) $parts[1];
        $subAction = $parts[2] ?? null;
    } else {
        $action = $parts[1];
        if (isset($parts[2]) && $parts[2] !== '') {
            $subAction = $parts[2];
        }
    }
}

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
        'notifications' => require __DIR__ . '/routes/notifications.php',
        'parametres' => require __DIR__ . '/routes/parametres.php',
        'recommandations' => require __DIR__ . '/routes/recommandations.php',
        'briefing' => require __DIR__ . '/routes/briefing.php',
        'inventaire' => require __DIR__ . '/routes/inventaire.php',
        'caisse' => require __DIR__ . '/routes/caisse.php',
        'tickets' => require __DIR__ . '/routes/tickets.php',
        'audit' => require __DIR__ . '/routes/audit.php',
        'bons-commande' => require __DIR__ . '/routes/bons_commande.php',
        'preferences' => require __DIR__ . '/routes/preferences.php',
        default => json_error('Route introuvable', 404),
    };
} catch (PDOException $e) {
    json_error('Erreur base de données : ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}
