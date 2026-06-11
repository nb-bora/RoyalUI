<?php
declare(strict_types=1);

$user = require_auth(['admin']);

if ($method === 'GET') {
    $rows = db()->query('SELECT id, nom, email, role, created_at FROM utilisateur ORDER BY nom ASC')->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'vendeur';

    if ($nom === '' || $email === '' || $password === '') {
        json_error('Nom, email et mot de passe requis');
    }
    if (!in_array($role, ['admin', 'gestionnaire', 'vendeur'], true)) {
        json_error('Rôle invalide');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nom, $email, $hash, $role]);
    $newId = (int) db()->lastInsertId();
    audit((int) $user['id'], 'create', 'utilisateur', $newId);
    json_response(['success' => true, 'id' => $newId]);
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $fields = [];
    $params = [];

    if (!empty($data['nom'])) {
        $fields[] = 'nom = ?';
        $params[] = trim($data['nom']);
    }
    if (!empty($data['email'])) {
        $fields[] = 'email = ?';
        $params[] = trim($data['email']);
    }
    if (!empty($data['role']) && in_array($data['role'], ['admin', 'gestionnaire', 'vendeur'], true)) {
        $fields[] = 'role = ?';
        $params[] = $data['role'];
    }
    if (!empty($data['password'])) {
        $fields[] = 'mot_de_passe = ?';
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (!$fields) {
        json_error('Aucune donnée à mettre à jour');
    }

    $params[] = $id;
    db()->prepare('UPDATE utilisateur SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    audit((int) $user['id'], 'update', 'utilisateur', $id);
    json_response(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    if ($id === (int) $user['id']) {
        json_error('Impossible de supprimer votre propre compte');
    }
    $stmt = db()->prepare('DELETE FROM utilisateur WHERE id = ?');
    $stmt->execute([$id]);
    audit((int) $user['id'], 'delete', 'utilisateur', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
