<?php
declare(strict_types=1);

$user = require_auth();

if ($method === 'GET' && $action === 'session') {
    $stmt = db()->prepare(
        "SELECT * FROM session_caisse WHERE id_utilisateur = ? AND statut = 'ouverte' ORDER BY ouverture DESC LIMIT 1"
    );
    $stmt->execute([(int) $user['id']]);
    $session = $stmt->fetch();
    json_response(['success' => true, 'data' => $session ?: null]);
}

if ($method === 'POST' && $action === 'ouvrir') {
    $data = read_json();
    $fond = (float) ($data['fond_caisse'] ?? 0);
    $existing = db()->prepare(
        "SELECT id FROM session_caisse WHERE id_utilisateur = ? AND statut = 'ouverte'"
    );
    $existing->execute([(int) $user['id']]);
    if ($existing->fetch()) {
        json_error('Session déjà ouverte');
    }
    db()->prepare('INSERT INTO session_caisse (id_utilisateur, fond_caisse) VALUES (?, ?)')
        ->execute([(int) $user['id'], $fond]);
    json_response(['success' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'POST' && $action === 'cloturer') {
    $data = read_json();
    $caReel = (float) ($data['ca_reel'] ?? 0);
    $stmt = db()->prepare(
        "SELECT * FROM session_caisse WHERE id_utilisateur = ? AND statut = 'ouverte' ORDER BY ouverture DESC LIMIT 1"
    );
    $stmt->execute([(int) $user['id']]);
    $session = $stmt->fetch();
    if (!$session) {
        json_error('Aucune session ouverte');
    }

    $caStmt = db()->prepare(
        "SELECT COALESCE(SUM(lv.quantite * lv.prix_vente), 0)
         FROM vente v JOIN ligne_vente lv ON lv.id_vente = v.id
         WHERE v.id_utilisateur = ? AND v.statut = 'validee' AND v.date_vente >= ?"
    );
    $caStmt->execute([(int) $user['id'], $session['ouverture']]);
    $caTheo = (float) $caStmt->fetchColumn();

    $ecart = $caReel - ($session['fond_caisse'] + $caTheo);

    db()->prepare(
        'UPDATE session_caisse SET cloture = NOW(), ca_theorique = ?, ca_reel = ?, ecart = ?, statut = ? WHERE id = ?'
    )->execute([$caTheo, $caReel, $ecart, 'cloturee', $session['id']]);

    require_once __DIR__ . '/../services/NotificationService.php';
    if (abs($ecart) > 100) {
        NotificationService::notifyRole('admin', 'ECART_CAISSE',
            'Écart de caisse à la clôture',
            "Écart : " . number_format($ecart, 0, ',', ' ') . " FCFA pour {$user['nom']}",
            'haute', 'rapports.html');
    }

    audit((int) $user['id'], 'cloture_caisse', 'session_caisse', (int) $session['id']);
    json_response(['success' => true, 'ca_theorique' => $caTheo, 'ecart' => $ecart]);
}

if ($method === 'GET' && $action === 'historique') {
    require_auth(['admin', 'gestionnaire']);
    $rows = db()->query(
        "SELECT s.*, u.nom AS utilisateur_nom FROM session_caisse s
         JOIN utilisateur u ON u.id = s.id_utilisateur
         WHERE s.statut = 'cloturee' ORDER BY s.cloture DESC LIMIT 50"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

json_error('Méthode non autorisée', 405);
