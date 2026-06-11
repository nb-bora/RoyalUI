<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET' && $id) {
    $bc = db()->prepare('SELECT bc.*, f.nom AS fournisseur_nom FROM bon_commande bc JOIN fournisseur f ON f.id = bc.id_fournisseur WHERE bc.id = ?');
    $bc->execute([$id]);
    $row = $bc->fetch();
    if (!$row) {
        json_error('Bon introuvable', 404);
    }
    $lignes = db()->prepare(
        'SELECT lbc.*, m.nom AS medicament_nom FROM ligne_bon_commande lbc JOIN medicament m ON m.id = lbc.id_medicament WHERE lbc.id_bon_commande = ?'
    );
    $lignes->execute([$id]);
    $row['lignes'] = $lignes->fetchAll();
    json_response(['success' => true, 'data' => $row]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT bc.*, f.nom AS fournisseur_nom, u.nom AS utilisateur_nom,
                (SELECT COUNT(*) FROM ligne_bon_commande WHERE id_bon_commande = bc.id) AS nb_lignes
         FROM bon_commande bc
         JOIN fournisseur f ON f.id = bc.id_fournisseur
         LEFT JOIN utilisateur u ON u.id = bc.id_utilisateur
         ORDER BY bc.date_creation DESC LIMIT 50"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $statut = $data['statut'] ?? 'envoye';
    if (!in_array($statut, ['brouillon', 'envoye', 'recu', 'annule'], true)) {
        json_error('Statut invalide');
    }
    db()->prepare('UPDATE bon_commande SET statut = ? WHERE id = ?')->execute([$statut, $id]);
    audit((int) $user['id'], 'bon_commande_' . $statut, 'bon_commande', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
