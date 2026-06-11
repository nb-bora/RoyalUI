(async () => {
  await PharmaLayout.init();
  const tbody = document.querySelector('#factures-table tbody');
  const today = new Date().toISOString().slice(0, 10);

  function labelPaiement(mode) {
    return { ESPECES: 'Espèces', CARTE: 'Carte', MOBILE: 'Mobile Money', CHEQUE: 'Chèque', VIREMENT: 'Virement' }[mode] || mode;
  }

  async function load() {
    const res = await PharmaAPI.get('factures');
    const rows = res.data;
    const emises = rows.filter((f) => f.statut === 'emise');
    const caTotal = emises.reduce((s, f) => s + parseFloat(f.montant_ttc), 0);
    const todayCount = emises.filter((f) => (f.date_facture || '').slice(0, 10) === today).length;

    document.getElementById('kpi-factures-total').textContent = emises.length;
    document.getElementById('kpi-factures-ca').textContent = formatMoney(caTotal);
    document.getElementById('kpi-factures-jour').textContent = todayCount;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="8">${pharmaEmpty('Aucune facture', 'ti-receipt')}</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map((f) => {
      const annulee = f.statut === 'annulee';
      return `<tr class="${annulee ? 'text-muted' : ''}">
        <td><strong class="text-primary">${f.numero_facture}</strong><br><small class="text-muted">Vente #${f.id_vente}</small></td>
        <td>${formatDate(f.date_facture)}</td>
        <td>${f.client_nom || '<span class="text-muted">Passage</span>'}</td>
        <td>${f.vendeur_nom || '—'}</td>
        <td><span class="badge badge-light">${labelPaiement(f.mode_paiement)}</span></td>
        <td><strong>${formatMoney(f.montant_ttc)}</strong></td>
        <td>${annulee ? '<span class="badge badge-secondary">Annulée</span>' : '<span class="badge badge-success">Émise</span>'}</td>
        <td><div class="pharma-actions">
          <button class="btn-action btn-action--view" title="Voir / Imprimer" data-html="${f.id}"><i class="ti-eye"></i></button>
          <button class="btn-action btn-action--view" title="PDF" data-pdf="${f.id}"><i class="ti-download"></i></button>
        </div></td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-html]').forEach((b) => b.addEventListener('click', () => {
      window.open(`api/index.php?r=factures/${b.dataset.html}/html`, '_blank');
    }));
    tbody.querySelectorAll('[data-pdf]').forEach((b) => b.addEventListener('click', () => {
      window.open(`api/index.php?r=factures/${b.dataset.pdf}/pdf`, '_blank');
    }));

    if ($.fn.DataTable.isDataTable('#factures-table')) $('#factures-table').DataTable().destroy();
    initDataTable('#factures-table');
  }

  load();
})();
