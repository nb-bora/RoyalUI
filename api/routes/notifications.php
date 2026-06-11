<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/NotificationService.php';

$user = require_auth();

if ($method === 'GET') {
    $unread = NotificationService::countUnread($user);
    json_response([
        'success' => true,
        'data' => NotificationService::listForUser($user),
        'unread' => $unread,
    ]);
}

if ($method === 'POST' && $action === 'lire-tout') {
    NotificationService::markAllRead($user);
    json_response(['success' => true]);
}

if ($method === 'PUT' && $id) {
    NotificationService::markRead($id, (int) $user['id']);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
