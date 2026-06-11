<?php
declare(strict_types=1);

$user = require_auth();

if ($method === 'GET') {
    $stmt = db()->prepare('SELECT * FROM utilisateur_preferences WHERE id_utilisateur = ?');
    $stmt->execute([(int) $user['id']]);
    $prefs = $stmt->fetch();
    if (!$prefs) {
        $defaultPage = $user['role'] === 'vendeur' ? 'caisse.html' : 'home.html';
        $prefs = ['id_utilisateur' => $user['id'], 'page_accueil' => $defaultPage, 'theme' => 'light', 'notifications_email' => 0];
    }
    json_response(['success' => true, 'data' => $prefs]);
}

if ($method === 'PUT') {
    $data = read_json();
    db()->prepare(
        'INSERT INTO utilisateur_preferences (id_utilisateur, page_accueil, theme, notifications_email, seuils_personnels)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE page_accueil = VALUES(page_accueil), theme = VALUES(theme),
         notifications_email = VALUES(notifications_email), seuils_personnels = VALUES(seuils_personnels)'
    )->execute([
        (int) $user['id'],
        trim($data['page_accueil'] ?? 'home.html'),
        trim($data['theme'] ?? 'light'),
        !empty($data['notifications_email']) ? 1 : 0,
        isset($data['seuils_personnels']) ? json_encode($data['seuils_personnels']) : null,
    ]);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
