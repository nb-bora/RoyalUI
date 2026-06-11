<?php
declare(strict_types=1);

/**
 * Exécuter une fois : php install/seed.php
 * Ou via navigateur : http://localhost/RoyalUI/install/seed.php
 */
require_once dirname(__DIR__) . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents(__DIR__ . '/setup.sql');
    $pdo->exec($sql);

    $pdo->exec('USE ' . DB_NAME);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM utilisateur')->fetchColumn();
    if ($count > 0) {
        echo "Base déjà initialisée.\n";
        exit;
    }

    $hash = password_hash('admin123', PASSWORD_DEFAULT);

    $pdo->prepare('INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)')
        ->execute(['Administrateur', 'admin@pharma.local', $hash, 'admin']);
    $pdo->prepare('INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)')
        ->execute(['Gestionnaire', 'gestion@pharma.local', $hash, 'gestionnaire']);
    $pdo->prepare('INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)')
        ->execute(['Vendeur', 'vendeur@pharma.local', $hash, 'vendeur']);

    $categories = ['Antalgique', 'Antibiotique', 'Anti-inflammatoire', 'Vitamines', 'Dermatologie'];
    foreach ($categories as $cat) {
        $pdo->prepare('INSERT INTO categorie (nom) VALUES (?)')->execute([$cat]);
    }

    $pdo->prepare('INSERT INTO fournisseur (nom, telephone, email) VALUES (?, ?, ?)')
        ->execute(['MediSupply SARL', '+237 600 00 00 01', 'contact@medisupply.cm']);
    $pdo->prepare('INSERT INTO fournisseur (nom, telephone, email) VALUES (?, ?, ?)')
        ->execute(['PharmaDistrib', '+237 600 00 00 02', 'info@pharmadistrib.cm']);

    $pdo->prepare('INSERT INTO client (nom, telephone) VALUES (?, ?)')->execute(['Jean Dupont', '+237 677 00 00 01']);
    $pdo->prepare('INSERT INTO client (nom, telephone) VALUES (?, ?)')->execute(['Marie Court', '+237 678 00 00 02']);
    $pdo->prepare('INSERT INTO client (nom, telephone) VALUES (?, ?)')->execute(['Client passage', null]);

    $meds = [
        ['Paracétamol 500mg', 120, 250, 120, 20, 1, '340001'],
        ['Amoxicilline 500mg', 240, 490, 58, 15, 2, '340002'],
        ['Ibuprofène 400mg', 180, 320, 8, 25, 3, '340003'],
        ['Vitamine C 1000mg', 90, 180, 45, 10, 4, '340004'],
        ['Crème hydratante', 350, 650, 0, 5, 5, '340005'],
    ];
    foreach ($meds as $m) {
        $pdo->prepare(
            'INSERT INTO medicament (nom, prix_achat, prix_vente, stock_actuel, stock_min, id_categorie, code_barre) VALUES (?,?,?,?,?,?,?)'
        )->execute($m);
    }

    $pdo->exec("INSERT INTO parametres (nom_pharmacie, devise) VALUES ('PharmaRoyal', 'FCFA')");

    // Achat démo avec péremption proche pour alertes
    $pdo->exec('INSERT INTO achat (id_fournisseur) VALUES (1)');
    $pdo->prepare(
        'INSERT INTO ligne_achat (id_achat, id_medicament, quantite, prix_achat, date_peremption) VALUES (1, 3, 50, 180, DATE_ADD(CURDATE(), INTERVAL 20 DAY))'
    )->execute();

    require __DIR__ . '/migrate.php';

    echo "Installation réussie !\n\n";
    echo "Comptes de démo (mot de passe : admin123)\n";
    echo "  admin@pharma.local      (admin)\n";
    echo "  gestion@pharma.local    (gestionnaire)\n";
    echo "  vendeur@pharma.local    (vendeur)\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur : ' . $e->getMessage() . "\n";
}
