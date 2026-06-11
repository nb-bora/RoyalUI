<?php
declare(strict_types=1);

$user = require_auth(['admin', 'gestionnaire']);

if ($method === 'GET') {
    $statut = $_GET['statut'] ?? 'nouvelle';
    try {
        $stmt = db()->prepare(
            "SELECT r.*, m.nom AS medicament_nom, f.nom AS fournisseur_nom
             FROM recommandation r
             LEFT JOIN medicament m ON m.id = r.id_medicament
             LEFT JOIN fournisseur f ON f.id = r.id_fournisseur
             WHERE r.statut = ?
             ORDER BY r.priorite DESC, r.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$statut]);
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Throwable) {
        json_response(['success' => true, 'data' => []]);
    }
}

if ($method === 'PUT' && $id) {
    $data = read_json();
    $statut = $data['statut'] ?? 'vue';
    if (!in_array($statut, ['vue', 'appliquee', 'ignoree'], true)) {
        json_error('Statut invalide');
    }
    db()->prepare('UPDATE recommandation SET statut = ? WHERE id = ?')->execute([$statut, $id]);

    if ($statut === 'appliquee' && !empty($data['creer_bc'])) {
        $stmt = db()->prepare('SELECT * FROM recommandation WHERE id = ?');
        $stmt->execute([$id]);
        $reco = $stmt->fetch();
        if ($reco && $reco['id_medicament']) {
            $payload = json_decode($reco['payload'] ?? '{}', true) ?: [];
            $idFour = (int) ($reco['id_fournisseur'] ?? 1);
            $pdo = db();
            $pdo->prepare('INSERT INTO bon_commande (id_fournisseur, id_utilisateur, statut) VALUES (?, ?, ?)')
                ->execute([$idFour, (int) $user['id'], 'brouillon']);
            $idBc = (int) $pdo->lastInsertId();
            $qte = (int) ($payload['quantite'] ?? 10);
            $med = db()->prepare('SELECT prix_achat FROM medicament WHERE id = ?');
            $med->execute([$reco['id_medicament']]);
            $pa = (float) $med->fetchColumn();
            $pdo->prepare('INSERT INTO ligne_bon_commande (id_bon_commande, id_medicament, quantite, prix_estime) VALUES (?, ?, ?, ?)')
                ->execute([$idBc, $reco['id_medicament'], $qte, $pa]);
            json_response(['success' => true, 'bon_commande_id' => $idBc]);
        }
    }
    json_response(['success' => true]);
}

if ($method === 'POST' && $action === 'executer') {
    require_once __DIR__ . '/../services/DecisionEngine.php';
    $result = DecisionEngine::run((int) $user['id']);
    json_response(['success' => true, 'result' => $result]);
}

json_error('Méthode non autorisée', 405);
