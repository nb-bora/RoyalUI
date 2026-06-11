<?php
declare(strict_types=1);

class InvoiceTemplate
{
    public static function render(array $data, bool $forPrint = false, ?string $pdfUrl = null): string
    {
        $p = $data['parametres'] ?? [];
        $devise = htmlspecialchars($p['devise'] ?? 'FCFA');
        $nomPharma = htmlspecialchars($p['nom_pharmacie'] ?? 'PharmaRoyal');
        $adresse = nl2br(htmlspecialchars($p['adresse'] ?? ''));
        $tel = htmlspecialchars($p['telephone'] ?? '');
        $nif = htmlspecialchars($p['nif'] ?? '');
        $rccm = htmlspecialchars($p['rccm'] ?? '');
        $mention = nl2br(htmlspecialchars($p['mention_legale_facture'] ?? 'Merci de votre confiance. Les médicaments ne sont ni repris ni échangés.'));

        $numero = htmlspecialchars($data['numero_facture'] ?? '');
        $dateFacture = date('d/m/Y', strtotime($data['date_facture'] ?? 'now'));
        $heure = date('H:i', strtotime($data['date_vente'] ?? $data['date_facture'] ?? 'now'));
        $statut = $data['statut'] ?? 'emise';
        $annulee = $statut === 'annulee';

        $clientNom = htmlspecialchars($data['client_nom'] ?? 'Client passage');
        $clientTel = htmlspecialchars($data['client_telephone'] ?? '');
        $clientAdr = htmlspecialchars($data['client_adresse'] ?? '');
        $vendeur = htmlspecialchars($data['vendeur_nom'] ?? '—');
        $modePaiement = htmlspecialchars(self::labelPaiement($data['mode_paiement'] ?? 'ESPECES'));
        $idVente = (int) ($data['id_vente'] ?? 0);

        $montantHt = (float) ($data['montant_ht'] ?? 0);
        $montantTva = (float) ($data['montant_tva'] ?? 0);
        $montantTtc = (float) ($data['montant_ttc'] ?? 0);
        $tauxTva = (float) ($data['taux_tva'] ?? 0);

        $lignesHtml = '';
        $i = 0;
        foreach ($data['lignes'] ?? [] as $l) {
            $i++;
            $lotsInfo = '';
            if (!empty($l['lots'])) {
                $parts = [];
                foreach ($l['lots'] as $lot) {
                    $exp = $lot['date_peremption'] ? date('m/Y', strtotime($lot['date_peremption'])) : '—';
                    $lotRef = !empty($lot['lot_id']) ? '#' . (int) $lot['lot_id'] : '—';
                    $parts[] = 'Lot ' . $lotRef . ' (exp. ' . $exp . ') ×' . (int) $lot['quantite'];
                }
                $lotsInfo = '<br><span style="font-size:9px;color:#64748b;">' . implode(' · ', $parts) . '</span>';
            }
            $codeBarre = $l['code_barre'] ? '<br><span style="font-size:9px;color:#94a3b8;">EAN: ' . htmlspecialchars($l['code_barre']) . '</span>' : '';
            $cat = $l['categorie_nom'] ? '<span style="font-size:9px;background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;margin-left:4px;">' . htmlspecialchars($l['categorie_nom']) . '</span>' : '';

            $lignesHtml .= '<tr>
                <td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:center;color:#64748b;">' . $i . '</td>
                <td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;">
                    <strong style="color:#0f172a;">' . htmlspecialchars($l['medicament_nom']) . '</strong>' . $cat . $codeBarre . $lotsInfo . '
                </td>
                <td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:center;">' . (int) $l['quantite'] . '</td>
                <td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:right;">' . self::fmt($l['prix_vente']) . '</td>
                <td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0d9488;">' . self::fmt($l['total_ligne']) . '</td>
            </tr>';
        }

        $watermark = $annulee ? '<div style="position:fixed;top:40%;left:10%;transform:rotate(-25deg);font-size:72px;color:rgba(220,38,38,0.15);font-weight:900;z-index:1000;pointer-events:none;">ANNULÉE</div>' : '';
        $printBar = $forPrint ? '' : '<div class="no-print" style="text-align:center;margin:24px 0;padding:16px;background:#f1f5f9;border-radius:8px;">
            <button onclick="window.print()" style="background:#0d9488;color:#fff;border:none;padding:12px 28px;border-radius:6px;font-size:14px;cursor:pointer;margin:0 8px;"><span style="margin-right:6px;">🖨</span> Imprimer</button>
            <a href="' . htmlspecialchars($pdfUrl ?? '#') . '" style="background:#1e40af;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;display:inline-block;margin:0 8px;">⬇ Télécharger PDF</a>
        </div>';

        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Facture ' . $numero . '</title>
<style>
  @page { size: A4; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Segoe UI, Arial, sans-serif; font-size: 11px; color: #334155; margin: 0; padding: 0; background: #f8fafc; }
  .invoice { max-width: 210mm; margin: 0 auto; background: #fff; }
  .header { background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%); color: #fff; padding: 28px 32px 24px; }
  .header-grid { display: table; width: 100%; }
  .header-left { display: table-cell; vertical-align: top; width: 55%; }
  .header-right { display: table-cell; vertical-align: top; text-align: right; }
  .pharma-name { font-size: 22px; font-weight: 700; margin: 0 0 6px; letter-spacing: -0.5px; }
  .pharma-meta { font-size: 10px; opacity: 0.92; line-height: 1.6; }
  .invoice-badge { display: inline-block; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); padding: 8px 16px; border-radius: 8px; margin-bottom: 8px; }
  .invoice-num { font-size: 18px; font-weight: 700; }
  .body { padding: 24px 32px 32px; }
  .info-grid { display: table; width: 100%; margin-bottom: 24px; }
  .info-box { display: table-cell; width: 48%; vertical-align: top; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
  .info-box + .info-box { padding-left: 16px; background: transparent; border: none; }
  .info-box-inner { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
  .info-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #0d9488; font-weight: 700; margin-bottom: 8px; }
  .info-line { margin: 3px 0; font-size: 11px; }
  table.items { width: 100%; border-collapse: collapse; margin: 8px 0 20px; }
  table.items thead th { background: #0f766e; color: #fff; padding: 10px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
  table.items thead th:first-child { border-radius: 6px 0 0 0; }
  table.items thead th:last-child { border-radius: 0 6px 0 0; }
  .totals-wrap { display: table; width: 100%; }
  .totals-spacer { display: table-cell; width: 55%; }
  .totals-box { display: table-cell; width: 45%; }
  .totals-table { width: 100%; border-collapse: collapse; }
  .totals-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
  .totals-table tr:last-child td { border-bottom: none; background: #ecfdf5; font-size: 14px; font-weight: 700; color: #0f766e; }
  .totals-table .label { color: #64748b; }
  .totals-table .val { text-align: right; font-weight: 600; }
  .footer { margin-top: 28px; padding-top: 16px; border-top: 2px solid #e2e8f0; font-size: 9px; color: #64748b; line-height: 1.7; }
  .footer-grid { display: table; width: 100%; }
  .footer-left { display: table-cell; width: 70%; vertical-align: top; }
  .footer-right { display: table-cell; text-align: right; vertical-align: bottom; }
  .ref-box { margin-top: 16px; padding: 10px; background: #f1f5f9; border-radius: 6px; font-size: 9px; color: #475569; }
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .invoice { box-shadow: none; }
  }
</style>
</head>
<body>
' . $watermark . '
<div class="invoice">
  <div class="header">
    <div class="header-grid">
      <div class="header-left">
        <div class="pharma-name">' . $nomPharma . '</div>
        <div class="pharma-meta">' .
            ($adresse ? $adresse . '<br>' : '') .
            ($tel ? 'Tél : ' . $tel . '<br>' : '') .
            ($nif ? 'NIF : ' . $nif . '<br>' : '') .
            ($rccm ? 'RCCM : ' . $rccm : '') .
        '</div>
      </div>
      <div class="header-right">
        <div class="invoice-badge">FACTURE</div>
        <div class="invoice-num">' . $numero . '</div>
        <div style="margin-top:8px;font-size:11px;opacity:0.9;">Date : ' . $dateFacture . ' à ' . $heure . '</div>
        <div style="font-size:10px;opacity:0.85;">Réf. vente #' . $idVente . '</div>
      </div>
    </div>
  </div>
  <div class="body">
    ' . $printBar . '
    <div class="info-grid">
      <div class="info-box">
        <div class="info-box-inner">
          <div class="info-title">Facturé à</div>
          <div class="info-line"><strong>' . $clientNom . '</strong></div>
          ' . ($clientTel ? '<div class="info-line">Tél : ' . $clientTel . '</div>' : '') . '
          ' . ($clientAdr ? '<div class="info-line">' . $clientAdr . '</div>' : '') . '
        </div>
      </div>
      <div class="info-box">
        <div class="info-box-inner">
          <div class="info-title">Détails transaction</div>
          <div class="info-line">Vendeur : <strong>' . $vendeur . '</strong></div>
          <div class="info-line">Mode de paiement : <strong>' . $modePaiement . '</strong></div>
          <div class="info-line">Statut : <strong>' . ($annulee ? 'Annulée' : 'Payée') . '</strong></div>
        </div>
      </div>
    </div>
    <table class="items">
      <thead>
        <tr>
          <th style="width:4%;">#</th>
          <th style="width:46%;text-align:left;">Désignation</th>
          <th style="width:10%;">Qté</th>
          <th style="width:20%;text-align:right;">P.U. (' . $devise . ')</th>
          <th style="width:20%;text-align:right;">Total (' . $devise . ')</th>
        </tr>
      </thead>
      <tbody>' . $lignesHtml . '</tbody>
    </table>
    <div class="totals-wrap">
      <div class="totals-spacer"></div>
      <div class="totals-box">
        <table class="totals-table">
          <tr><td class="label">Total HT</td><td class="val">' . self::fmt($montantHt) . ' ' . $devise . '</td></tr>' .
            ($tauxTva > 0 ? '<tr><td class="label">TVA (' . number_format($tauxTva, 1, ',', ' ') . ' %)</td><td class="val">' . self::fmt($montantTva) . ' ' . $devise . '</td></tr>' : '') .
          '<tr><td class="label">Total TTC</td><td class="val">' . self::fmt($montantTtc) . ' ' . $devise . '</td></tr>
        </table>
      </div>
    </div>
    <div class="ref-box">
      Document généré automatiquement par PharmaRoyal · ' . date('d/m/Y H:i') . ' · ' . $numero . '
    </div>
    <div class="footer">
      <div class="footer-grid">
        <div class="footer-left">' . $mention . '</div>
        <div class="footer-right">
          <div style="font-size:11px;font-weight:700;color:#0f766e;">' . $nomPharma . '</div>
          <div>Signature & cachet</div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>';
    }

    private static function fmt(float|string|int $n): string
    {
        return number_format((float) $n, 0, ',', ' ');
    }

    private static function labelPaiement(string $mode): string
    {
        return match (strtoupper($mode)) {
            'CARTE' => 'Carte bancaire',
            'MOBILE' => 'Mobile Money',
            'CHEQUE' => 'Chèque',
            'VIREMENT' => 'Virement',
            default => 'Espèces',
        };
    }
}
