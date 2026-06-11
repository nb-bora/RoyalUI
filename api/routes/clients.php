<?php
declare(strict_types=1);

$user = require_auth();

if ($method === 'GET') {
    $rows = db()->query('SELECT id, nom, telephone, created_at FROM client ORDER BY nom ASC')->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    if ($nom === '') {
        json_error('Le nom est requis');
    }
    $stmt = db()->prepare('INSERT INTO client (nom, telephone) VALUES (?, ?)');
    $stmt->execute([$nom, trim($data['telephone'] ?? '') ?: null]);
    $newId = (int) db()->lastInsertId();
    audit((int) $user['id'], 'create', 'client', $newId);
    json_response(['success' => true, 'id' => $newId]);
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $stmt = db()->prepare('UPDATE client SET nom = ?, telephone = ? WHERE id = ?');
    $stmt->execute([trim($data['nom'] ?? ''), trim($data['telephone'] ?? '') ?: null, $id]);
    audit((int) $user['id'], 'update', 'client', $id);
    json_response(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $stmt = db()->prepare('DELETE FROM client WHERE id = ?');
    $stmt->execute([$id]);
    audit((int) $user['id'], 'delete', 'client', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
