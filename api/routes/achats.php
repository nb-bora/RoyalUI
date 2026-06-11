<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/LotStockService.php';
require_once __DIR__ . '/../services/DecisionEngine.php';

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET' && $id) {
    $achat = db()->prepare(
        'SELECT a.*, f.nom AS fournisseur_nom FROM achat a JOIN fournisseur f ON f.id = a.id_fournisseur WHERE a.id = ?'
    );
    $achat->execute([$id]);
    $row = $achat->fetch();
    if (!$row) {
        json_error('Achat introuvable', 404);
    }
    $lignes = db()->prepare(
        'SELECT la.*, m.nom AS medicament_nom FROM ligne_achat la JOIN medicament m ON m.id = la.id_medicament WHERE la.id_achat = ?'
    );
    $lignes->execute([$id]);
    $row['lignes'] = $lignes->fetchAll();
    json_response(['success' => true, 'data' => $row]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT a.id, a.date_achat, f.nom AS fournisseur_nom,
                COUNT(la.id) AS nb_lignes,
                COALESCE(SUM(la.quantite * la.prix_achat), 0) AS total
         FROM achat a
         JOIN fournisseur f ON f.id = a.id_fournisseur
         LEFT JOIN ligne_achat la ON la.id_achat = a.id
         GROUP BY a.id, a.date_achat, f.nom
         ORDER BY a.date_achat DESC
         LIMIT 200"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $data = read_json();
    $idFournisseur = (int) ($data['id_fournisseur'] ?? 0);
    $lignes = $data['lignes'] ?? [];

    if ($idFournisseur <= 0 || !is_array($lignes) || count($lignes) === 0) {
        json_error('Fournisseur et lignes requis');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('INSERT INTO achat (id_fournisseur) VALUES (?)')->execute([$idFournisseur]);
        $idAchat = (int) $pdo->lastInsertId();

        foreach ($lignes as $ligne) {
            $idMed = (int) ($ligne['id_medicament'] ?? 0);
            $qte = (int) ($ligne['quantite'] ?? 0);
            $prix = (float) ($ligne['prix_achat'] ?? 0);
            $peremption = $ligne['date_peremption'] ?? date('Y-m-d', strtotime('+1 year'));

            if ($idMed <= 0 || $qte <= 0 || $prix < 0) {
                throw new RuntimeException('Ligne achat invalide');
            }

            $pdo->prepare(
                'INSERT INTO ligne_achat (id_achat, id_medicament, quantite, prix_achat, date_peremption) VALUES (?, ?, ?, ?, ?)'
            )->execute([$idAchat, $idMed, $qte, $prix, $peremption]);
            $idLigneAchat = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE medicament SET stock_actuel = stock_actuel + ?, prix_achat = ? WHERE id = ?')
                ->execute([$qte, $prix, $idMed]);

            LotStockService::createFromAchat($idMed, $idLigneAchat, $qte, $peremption, $prix);

            $pdo->prepare(
                'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, ?, ?, ?)'
            )->execute([$idMed, $idAchat, 'ENTREE', $qte]);
        }

        $pdo->commit();
        audit((int) $user['id'], 'achat', 'achat', $idAchat);

        try {
            DecisionEngine::run((int) $user['id']);
        } catch (Throwable) {
        }

        json_response(['success' => true, 'id' => $idAchat]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

json_error('Méthode non autorisée', 405);
