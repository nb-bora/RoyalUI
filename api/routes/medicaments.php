<?php
declare(strict_types=1);

$user = require_auth();

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT m.*, c.nom AS categorie_nom
            FROM medicament m
            LEFT JOIN categorie c ON c.id = m.id_categorie
            WHERE m.actif = 1";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (m.nom LIKE ? OR m.code_barre LIKE ?)";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }
    $sql .= " ORDER BY m.nom ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['statut_stock'] = stock_badge((int) $row['stock_actuel'], (int) $row['stock_min']);
        $pa = (float) $row['prix_achat'];
        $pv = (float) $row['prix_vente'];
        $row['marge_pct'] = $pa > 0 ? round((($pv - $pa) / $pa) * 100, 1) : 0;
    }
    unset($row);
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    require_auth(['admin', 'gestionnaire']);
    $data = read_json();
    $nom = trim($data['nom'] ?? '');
    if ($nom === '') {
        json_error('Le nom est requis');
    }
    $stmt = db()->prepare(
        'INSERT INTO medicament (nom, prix_achat, prix_vente, stock_actuel, stock_min, id_categorie, code_barre)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $nom,
        (float) ($data['prix_achat'] ?? 0),
        (float) ($data['prix_vente'] ?? 0),
        (int) ($data['stock_actuel'] ?? 0),
        (int) ($data['stock_min'] ?? 5),
        !empty($data['id_categorie']) ? (int) $data['id_categorie'] : null,
        trim($data['code_barre'] ?? '') ?: null,
    ]);
    $newId = (int) db()->lastInsertId();
    audit((int) $user['id'], 'create', 'medicament', $newId);
    json_response(['success' => true, 'id' => $newId]);
}

if ($method === 'PUT' && $id) {
    require_auth(['admin', 'gestionnaire']);
    $data = read_json();
    $stmt = db()->prepare(
        'UPDATE medicament SET nom=?, prix_achat=?, prix_vente=?, stock_actuel=?, stock_min=?, id_categorie=?, code_barre=?
         WHERE id=?'
    );
    $stmt->execute([
        trim($data['nom'] ?? ''),
        (float) ($data['prix_achat'] ?? 0),
        (float) ($data['prix_vente'] ?? 0),
        (int) ($data['stock_actuel'] ?? 0),
        (int) ($data['stock_min'] ?? 5),
        !empty($data['id_categorie']) ? (int) $data['id_categorie'] : null,
        trim($data['code_barre'] ?? '') ?: null,
        $id,
    ]);
    audit((int) $user['id'], 'update', 'medicament', $id);
    json_response(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    require_auth(['admin', 'gestionnaire']);
    $stmt = db()->prepare('UPDATE medicament SET actif = 0 WHERE id = ?');
    $stmt->execute([$id]);
    audit((int) $user['id'], 'delete', 'medicament', $id);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
