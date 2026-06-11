<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ParametresService.php';

$user = require_auth();

if ($method !== 'GET' || !$id) {
    json_error('ID vente requis', 400);
}

$vente = db()->prepare(
    'SELECT v.*, c.nom AS client_nom, u.nom AS vendeur_nom
     FROM vente v
     LEFT JOIN client c ON c.id = v.id_client
     LEFT JOIN utilisateur u ON u.id = v.id_utilisateur
     WHERE v.id = ? AND v.statut = ?'
);
$vente->execute([$id, 'validee']);
$row = $vente->fetch();
if (!$row) {
    json_error('Vente introuvable', 404);
}

$lignes = db()->prepare(
    'SELECT lv.*, m.nom AS medicament_nom FROM ligne_vente lv JOIN medicament m ON m.id = lv.id_medicament WHERE lv.id_vente = ?'
);
$lignes->execute([$id]);
$lignes = $lignes->fetchAll();
$params = ParametresService::get();
$total = array_sum(array_map(fn($l) => $l['quantite'] * $l['prix_vente'], $lignes));
$devise = $params['devise'] ?? 'FCFA';
$nomPharma = $params['nom_pharmacie'] ?? 'PharmaRoyal';

$format = $_GET['format'] ?? 'html';

if ($format === 'json') {
    json_response(['success' => true, 'vente' => $row, 'lignes' => $lignes, 'total' => $total, 'pharmacie' => $params]);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Ticket #<?= (int) $id ?></title>
<style>
  body { font-family: 'Courier New', monospace; max-width: 320px; margin: 20px auto; font-size: 13px; }
  h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
  .meta { text-align: center; font-size: 11px; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 3px 0; vertical-align: top; }
  .right { text-align: right; }
  hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
  .total { font-weight: bold; font-size: 15px; }
  @media print { body { margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
  <h1><?= htmlspecialchars($nomPharma) ?></h1>
  <div class="meta">
    Ticket #<?= (int) $id ?><br>
    <?= date('d/m/Y H:i', strtotime($row['date_vente'])) ?><br>
    Vendeur : <?= htmlspecialchars($row['vendeur_nom'] ?? '—') ?><br>
    Client : <?= htmlspecialchars($row['client_nom'] ?? 'Passage') ?>
  </div>
  <hr>
  <table>
    <?php foreach ($lignes as $l): ?>
    <tr>
      <td><?= htmlspecialchars($l['medicament_nom']) ?><br><small><?= $l['quantite'] ?> × <?= number_format((float)$l['prix_vente'], 0, ',', ' ') ?></small></td>
      <td class="right"><?= number_format($l['quantite'] * $l['prix_vente'], 0, ',', ' ') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr class="total"><td>TOTAL</td><td class="right"><?= number_format($total, 0, ',', ' ') ?> <?= htmlspecialchars($devise) ?></td></tr>
  </table>
  <p class="meta">Merci de votre visite</p>
  <p class="no-print" style="text-align:center"><button onclick="window.print()">Imprimer</button></p>
</body>
</html>
<?php
exit;
