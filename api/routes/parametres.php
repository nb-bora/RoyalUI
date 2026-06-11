<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ParametresService.php';

$user = require_auth();

if ($method === 'GET') {
    $params = ParametresService::get();
    $regles = db()->query('SELECT cle, valeur, description FROM regle_metier ORDER BY cle')->fetchAll();
    json_response(['success' => true, 'data' => $params, 'regles' => $regles]);
}

require_auth(['admin']);

if ($method === 'PUT') {
    $data = read_json();
    $pdo = db();
    $row = $pdo->query('SELECT id FROM parametres LIMIT 1')->fetch();
    if (!$row) {
        $pdo->exec("INSERT INTO parametres (nom_pharmacie, devise) VALUES ('PharmaRoyal', 'FCFA')");
        $row = $pdo->query('SELECT id FROM parametres LIMIT 1')->fetch();
    }
    $pdo->prepare(
        'UPDATE parametres SET nom_pharmacie=?, adresse=?, telephone=?, devise=?,
         seuil_marge_min=?, seuil_peremption_jours=?, objectif_ca_jour=?, delai_fournisseur_jours=?, email_alerte=?,
         nif=?, rccm=?, mention_legale_facture=?, taux_tva=?, prefixe_facture=?
         WHERE id=?'
    )->execute([
        trim($data['nom_pharmacie'] ?? 'PharmaRoyal'),
        trim($data['adresse'] ?? '') ?: null,
        trim($data['telephone'] ?? '') ?: null,
        trim($data['devise'] ?? 'FCFA'),
        (float) ($data['seuil_marge_min'] ?? 15),
        (int) ($data['seuil_peremption_jours'] ?? 30),
        (float) ($data['objectif_ca_jour'] ?? 0),
        (int) ($data['delai_fournisseur_jours'] ?? 3),
        trim($data['email_alerte'] ?? '') ?: null,
        trim($data['nif'] ?? '') ?: null,
        trim($data['rccm'] ?? '') ?: null,
        trim($data['mention_legale_facture'] ?? '') ?: null,
        (float) ($data['taux_tva'] ?? 0),
        trim($data['prefixe_facture'] ?? 'FA') ?: 'FA',
        $row['id'],
    ]);

    if (!empty($data['regles']) && is_array($data['regles'])) {
        foreach ($data['regles'] as $cle => $valeur) {
            $pdo->prepare('INSERT INTO regle_metier (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
                ->execute([$cle, (string) $valeur]);
        }
    }

    audit((int) $user['id'], 'update', 'parametres', (int) $row['id']);
    json_response(['success' => true]);
}

json_error('Méthode non autorisée', 405);
