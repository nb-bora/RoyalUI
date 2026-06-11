<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET' && $action === 'mouvements') {
    $rows = db()->query(
        "SELECT ms.*, m.nom AS medicament_nom
         FROM mouvement_stock ms
         JOIN medicament m ON m.id = ms.id_medicament
         ORDER BY ms.date_mouvement DESC
         LIMIT 300"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT m.id, m.nom, m.stock_actuel, m.stock_min, c.nom AS categorie_nom
         FROM medicament m
         LEFT JOIN categorie c ON c.id = m.id_categorie
         WHERE m.actif = 1
         ORDER BY m.stock_actuel ASC"
    )->fetchAll();
    foreach ($rows as &$row) {
        $row['statut_stock'] = stock_badge((int) $row['stock_actuel'], (int) $row['stock_min']);
    }
    unset($row);
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $idMed = (int) ($data['id_medicament'] ?? 0);
    $type = strtoupper(trim($data['type_mouvement'] ?? ''));
    $qte = (int) ($data['quantite'] ?? 0);
    $motif = trim($data['motif'] ?? 'Ajustement manuel');

    if ($idMed <= 0 || $qte <= 0 || !in_array($type, ['ENTREE', 'SORTIE'], true)) {
        json_error('Données invalides');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $med = $pdo->prepare('SELECT stock_actuel, nom FROM medicament WHERE id = ? FOR UPDATE');
        $med->execute([$idMed]);
        $medicament = $med->fetch();
        if (!$medicament) {
            throw new RuntimeException('Médicament introuvable');
        }
        if ($type === 'SORTIE' && (int) $medicament['stock_actuel'] < $qte) {
            throw new RuntimeException("Stock insuffisant pour {$medicament['nom']}");
        }

        $delta = $type === 'ENTREE' ? $qte : -$qte;
        $pdo->prepare('UPDATE medicament SET stock_actuel = stock_actuel + ? WHERE id = ?')->execute([$delta, $idMed]);

        $pdo->prepare(
            'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, 0, ?, ?)'
        )->execute([$idMed, $type, $qte]);

        $pdo->commit();
        audit((int) $user['id'], $motif, 'mouvement_stock', $idMed);
        json_response(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

json_error('Méthode non autorisée', 405);
