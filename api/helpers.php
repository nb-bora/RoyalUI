<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function cors(): void
{
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400): void
{
    json_response(['success' => false, 'error' => $message], $code);
}

function read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_auth(array $roles = []): array
{
    start_session();
    if (empty($_SESSION['user'])) {
        json_error('Non authentifié', 401);
    }
    $user = $_SESSION['user'];
    if ($roles && !in_array($user['role'], $roles, true)) {
        json_error('Accès refusé', 403);
    }
    return $user;
}

function audit(int $userId, string $action, string $table, ?int $targetId = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_log (id_utilisateur, action, table_cible, id_cible) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $table, $targetId]);
    } catch (Throwable) {
        // Table optionnelle
    }
}

function stock_badge(int $stock, int $min): string
{
    if ($stock <= 0) {
        return 'rupture';
    }
    if ($stock <= $min) {
        return 'bas';
    }
    return 'ok';
}
