<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ImportService.php';

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET' && $action === 'template' && $subAction === 'medicaments') {
    $csv = ImportService::medicamentsTemplateCsv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modele_import_medicaments.csv"');
    echo $csv;
    exit;
}

if ($method === 'POST' && $action === 'medicaments') {
    if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        json_error('Fichier requis (.xlsx, .xls ou .csv)', 400);
    }
    $tmp = $_FILES['fichier']['tmp_name'];
    $name = $_FILES['fichier']['name'] ?? 'import.csv';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], true)) {
        json_error('Extension non supportée', 400);
    }

    try {
        $result = ImportService::importMedicaments($tmp, $ext, (int) $user['id']);
        json_response([
            'success' => true,
            'lignes_ok' => $result['ok'],
            'lignes_erreur' => count($result['errors']),
            'errors' => array_slice($result['errors'], 0, 50),
        ]);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 422);
    }
}

if ($method === 'GET' && $action === 'logs') {
    $rows = db()->query(
        'SELECT il.*, u.nom AS utilisateur_nom FROM import_log il JOIN utilisateur u ON u.id = il.id_utilisateur ORDER BY il.created_at DESC LIMIT 50'
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

json_error('Route import introuvable', 404);
