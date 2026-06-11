<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/DecisionEngine.php';
require_once __DIR__ . '/../services/ActiviteService.php';

$user = require_auth();

if ($method !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

ActiviteService::log((int) $user['id'], 'briefing', 'briefing');
json_response(['success' => true, 'data' => DecisionEngine::briefing($user)]);
