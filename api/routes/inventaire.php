<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET' && $action === 'produits') {
    $rows = db()->query(
        "SELECT m.id, m.nom, m.stock_actuel AS stock_theorique, c.nom AS categorie_nom
         FROM medicament m LEFT JOIN categorie c ON c.id = m.id_categorie
         WHERE m.actif = 1 ORDER BY m.nom"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT i.*, u.nom AS utilisateur_nom FROM inventaire i
         JOIN utilisateur u ON u.id = i.id_utilisateur ORDER BY i.date_inventaire DESC LIMIT 20"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $lignes = $data['lignes'] ?? [];
    if (!is_array($lignes) || !count($lignes)) {
        json_error('Lignes inventaire requises');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO inventaire (id_utilisateur, notes, statut) VALUES (?, ?, ?)')
            ->execute([(int) $user['id'], trim($data['notes'] ?? '') ?: null, 'valide']);
        $idInv = (int) $pdo->lastInsertId();

        foreach ($lignes as $l) {
            $idMed = (int) ($l['id_medicament'] ?? 0);
            $theo = (int) ($l['stock_theorique'] ?? 0);
            $reel = (int) ($l['stock_reel'] ?? 0);
            $ecart = $reel - $theo;
            if ($idMed <= 0) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO ligne_inventaire (id_inventaire, id_medicament, stock_theorique, stock_reel, ecart) VALUES (?, ?, ?, ?, ?)'
            )->execute([$idInv, $idMed, $theo, $reel, $ecart]);

            if ($ecart !== 0) {
                $pdo->prepare('UPDATE medicament SET stock_actuel = ? WHERE id = ?')->execute([$reel, $idMed]);
                $type = $ecart > 0 ? 'ENTREE' : 'SORTIE';
                $pdo->prepare(
                    'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, ?, ?, ?)'
                )->execute([$idMed, $idInv, $type, abs($ecart)]);
            }
        }
        $pdo->commit();
        audit((int) $user['id'], 'inventaire', 'inventaire', $idInv);
        json_response(['success' => true, 'id' => $idInv]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

json_error('Méthode non autorisée', 405);
