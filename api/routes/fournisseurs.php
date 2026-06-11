<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT f.id, f.nom, f.telephone, f.email, f.created_at,
                COUNT(DISTINCT a.id) AS nb_achats,
                COALESCE(SUM(la.quantite * la.prix_achat), 0) AS montant_achats
         FROM fournisseur f
         LEFT JOIN achat a ON a.id_fournisseur = f.id
         LEFT JOIN ligne_achat la ON la.id_achat = a.id
         GROUP BY f.id, f.nom, f.telephone, f.email, f.created_at
         ORDER BY f.nom ASC"
    )->fetchAll();

    $stats = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM fournisseur) AS total,
            (SELECT COUNT(*) FROM achat) AS nb_achats,
            (SELECT COALESCE(SUM(la.quantite * la.prix_achat), 0) FROM ligne_achat la) AS montant_total"
    )->fetch();

    json_response([
        'success' => true,
        'data' => $rows,
        'stats' => [
            'total' => (int) $stats['total'],
            'nb_achats' => (int) $stats['nb_achats'],
            'montant_total' => (float) $stats['montant_total'],
        ],
    ]);
}

if ($method === 'POST') {
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    if ($nom === '') {
        json_error('Le nom est requis');
    }
    $stmt = db()->prepare('INSERT INTO fournisseur (nom, telephone, email) VALUES (?, ?, ?)');
    $stmt->execute([
        $nom,
        trim($data['telephone'] ?? '') ?: null,
        trim($data['email'] ?? '') ?: null,
    ]);
    $newId = (int) db()->lastInsertId();
    audit((int) $user['id'], 'create', 'fournisseur', $newId);
    json_response(['success' => true, 'id' => $newId]);
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $stmt = db()->prepare('UPDATE fournisseur SET nom=?, telephone=?, email=? WHERE id=?');
    $stmt->execute([
        trim($data['nom'] ?? ''),
        trim($data['telephone'] ?? '') ?: null,
        trim($data['email'] ?? '') ?: null,
        $id,
    ]);
    audit((int) $user['id'], 'update', 'fournisseur', $id);
    json_response(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    $stmt = db()->prepare('DELETE FROM fournisseur WHERE id = ?');
    $stmt->execute([$id]);
    audit((int) $user['id'], 'delete', 'fournisseur', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
