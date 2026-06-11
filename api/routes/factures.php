<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/FactureService.php';
require_once __DIR__ . '/../services/InvoiceTemplate.php';
require_once __DIR__ . '/../services/InvoicePdfService.php';

$user = require_auth();

if ($method === 'GET' && $id && $subAction === 'pdf') {
    $data = FactureService::getFullData($id);
    if (!$data) {
        json_error('Facture introuvable', 404);
    }
    $html = InvoiceTemplate::render($data, true);
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['numero_facture']) . '.pdf';

    try {
        $pdf = InvoicePdfService::render($html, $filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    } catch (Throwable $e) {
        header('Content-Type: text/html; charset=utf-8');
        echo $html . '<script>alert("PDF non disponible — utilisez Imprimer (Ctrl+P) pour enregistrer en PDF.\\n' . addslashes($e->getMessage()) . '");</script>';
        exit;
    }
}

if ($method === 'GET' && $id && $subAction === 'html') {
    $data = FactureService::getFullData($id);
    if (!$data) {
        json_error('Facture introuvable', 404);
    }
    $pdfUrl = 'api/index.php?r=factures/' . $id . '/pdf';
    $html = InvoiceTemplate::render($data, false, $pdfUrl);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

if ($method === 'GET' && $id) {
    $data = FactureService::getFullData($id);
    if (!$data) {
        json_error('Facture introuvable', 404);
    }
    json_response(['success' => true, 'data' => $data]);
}

if ($method === 'GET' && $action === 'vente' && $subAction && ctype_digit($subAction)) {
    $facture = FactureService::getByVenteId((int) $subAction);
    if (!$facture) {
        json_error('Aucune facture pour cette vente', 404);
    }
    json_response(['success' => true, 'data' => $facture]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT f.id, f.numero_facture, f.date_facture, f.montant_ttc, f.mode_paiement, f.statut,
                v.id AS id_vente, c.nom AS client_nom, u.nom AS vendeur_nom
         FROM facture f
         JOIN vente v ON v.id = f.id_vente
         LEFT JOIN client c ON c.id = v.id_client
         LEFT JOIN utilisateur u ON u.id = v.id_utilisateur
         ORDER BY f.date_facture DESC
         LIMIT 300"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

json_error('Méthode non autorisée', 405);
