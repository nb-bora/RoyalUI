<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET') {
    $rows = db()->query('SELECT id, nom, created_at FROM categorie ORDER BY nom ASC')->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    if ($nom === '') {
        json_error('Le nom est requis');
    }
    $stmt = db()->prepare('INSERT INTO categorie (nom) VALUES (?)');
    $stmt->execute([$nom]);
    audit((int) $user['id'], 'create', 'categorie', (int) db()->lastInsertId());
    json_response(['success' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    if ($nom === '') {
        json_error('Le nom est requis');
    }
    $stmt = db()->prepare('UPDATE categorie SET nom = ? WHERE id = ?');
    $stmt->execute([$nom, $id]);
    audit((int) $user['id'], 'update', 'categorie', $id);
    json_response(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $stmt = db()->prepare('DELETE FROM categorie WHERE id = ?');
    $stmt->execute([$id]);
    audit((int) $user['id'], 'delete', 'categorie', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
