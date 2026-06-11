<?php
declare(strict_types=1);

if ($action === 'login' && $method === 'POST') {
    $data = read_json();
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if ($email === '' || $password === '') {
        json_error('Email et mot de passe requis');
    }

    $stmt = db()->prepare('SELECT id, nom, email, mot_de_passe, role FROM utilisateur WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        json_error('Identifiants incorrects', 401);
    }

    unset($user['mot_de_passe']);
    $_SESSION['user'] = $user;
    audit((int) $user['id'], 'connexion', 'utilisateur', (int) $user['id']);

    $briefing = null;
    $redirect = 'home.html';
    try {
        require_once __DIR__ . '/../services/ActiviteService.php';
        require_once __DIR__ . '/../services/DecisionEngine.php';
        ActiviteService::log((int) $user['id'], 'connexion', 'index.html');
        DecisionEngine::run((int) $user['id']);
        $briefing = DecisionEngine::briefing($user);
        $pref = db()->prepare('SELECT page_accueil FROM utilisateur_preferences WHERE id_utilisateur = ?');
        $pref->execute([(int) $user['id']]);
        $page = $pref->fetchColumn();
        if ($page) {
            $redirect = $page;
        } elseif ($user['role'] === 'vendeur') {
            $redirect = 'caisse.html';
        }
    } catch (Throwable) {
    }

    json_response(['success' => true, 'user' => $user, 'redirect' => $redirect, 'briefing' => $briefing]);
}

if ($action === 'logout' && $method === 'POST') {
    if (!empty($_SESSION['user'])) {
        audit((int) $_SESSION['user']['id'], 'deconnexion', 'utilisateur', (int) $_SESSION['user']['id']);
    }
    $_SESSION = [];
    session_destroy();
    json_response(['success' => true]);
}

if ($action === 'me' && $method === 'GET') {
    if (empty($_SESSION['user'])) {
        json_error('Non authentifié', 401);
    }
    json_response(['success' => true, 'user' => $_SESSION['user']]);
}

json_error('Action auth invalide', 404);
