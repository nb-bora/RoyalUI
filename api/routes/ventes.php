<?php
declare(strict_types=1);

$user = require_auth();

// GET /ventes — historique
// GET /ventes/{id} — détail
if ($method === 'GET' && $id) {
    $vente = db()->prepare(
        'SELECT v.*, c.nom AS client_nom FROM vente v LEFT JOIN client c ON c.id = v.id_client WHERE v.id = ?'
    );
    $vente->execute([$id]);
    $row = $vente->fetch();
    if (!$row) {
        json_error('Vente introuvable', 404);
    }
    $lignes = db()->prepare(
        'SELECT lv.*, m.nom AS medicament_nom
         FROM ligne_vente lv JOIN medicament m ON m.id = lv.id_medicament
         WHERE lv.id_vente = ?'
    );
    $lignes->execute([$id]);
    $row['lignes'] = $lignes->fetchAll();
    $row['total'] = array_sum(array_map(fn($l) => $l['quantite'] * $l['prix_vente'], $row['lignes']));
    json_response(['success' => true, 'data' => $row]);
}

if ($method === 'GET') {
    $rows = db()->query(
        "SELECT v.id, v.date_vente, c.nom AS client_nom,
                COUNT(lv.id) AS nb_lignes,
                COALESCE(SUM(lv.quantite * lv.prix_vente), 0) AS total
         FROM vente v
         LEFT JOIN client c ON c.id = v.id_client
         LEFT JOIN ligne_vente lv ON lv.id_vente = v.id
         GROUP BY v.id, v.date_vente, c.nom
         ORDER BY v.date_vente DESC
         LIMIT 200"
    )->fetchAll();
    json_response(['success' => true, 'data' => $rows]);
}

// POST /ventes — créer vente (intelligence niveau 1)
if ($method === 'POST') {
    $data = read_json();
    $lignes = $data['lignes'] ?? [];
    $idClient = !empty($data['id_client']) ? (int) $data['id_client'] : null;

    if (!is_array($lignes) || count($lignes) === 0) {
        json_error('Au moins une ligne de vente est requise');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach ($lignes as $ligne) {
            $idMed = (int) ($ligne['id_medicament'] ?? 0);
            $qte = (int) ($ligne['quantite'] ?? 0);
            if ($idMed <= 0 || $qte <= 0) {
                throw new RuntimeException('Ligne de vente invalide');
            }

            $med = $pdo->prepare('SELECT id, nom, stock_actuel, prix_vente FROM medicament WHERE id = ? AND actif = 1 FOR UPDATE');
            $med->execute([$idMed]);
            $medicament = $med->fetch();
            if (!$medicament) {
                throw new RuntimeException('Médicament introuvable');
            }
            if ((int) $medicament['stock_actuel'] < $qte) {
                throw new RuntimeException(
                    "Stock insuffisant pour {$medicament['nom']} (disponible : {$medicament['stock_actuel']})"
                );
            }
        }

        $stmt = $pdo->prepare('INSERT INTO vente (id_client) VALUES (?)');
        $stmt->execute([$idClient]);
        $idVente = (int) $pdo->lastInsertId();

        $total = 0;
        foreach ($lignes as $ligne) {
            $idMed = (int) $ligne['id_medicament'];
            $qte = (int) $ligne['quantite'];
            $prixStmt = $pdo->prepare('SELECT prix_vente FROM medicament WHERE id = ?');
            $prixStmt->execute([$idMed]);
            $prix = isset($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $prixStmt->fetchColumn();

            $pdo->prepare('INSERT INTO ligne_vente (id_vente, id_medicament, quantite, prix_vente) VALUES (?, ?, ?, ?)')
                ->execute([$idVente, $idMed, $qte, $prix]);

            $pdo->prepare('UPDATE medicament SET stock_actuel = stock_actuel - ? WHERE id = ?')
                ->execute([$qte, $idMed]);

            $pdo->prepare(
                'INSERT INTO mouvement_stock (id_medicament, id_reference, type_mouvement, quantite) VALUES (?, ?, ?, ?)'
            )->execute([$idMed, $idVente, 'SORTIE', $qte]);

            $total += $qte * $prix;
        }

        $pdo->commit();
        audit((int) $user['id'], 'vente', 'vente', $idVente);
        json_response(['success' => true, 'id' => $idVente, 'total' => $total]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 422);
    }
}

json_error('Méthode non autorisée', 405);
