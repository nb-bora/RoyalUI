(async () => {
  const user = await PharmaLayout.init();
  const tbody = document.querySelector('#ventes-table tbody');
  const today = new Date().toISOString().slice(0, 10);
  const canCancel = user && ['admin', 'gestionnaire'].includes(user.role);

  async function load() {
    const res = await PharmaAPI.get('ventes');
    const rows = res.data.filter((v) => v.statut !== 'annulee' || canCancel);
    const valides = rows.filter((v) => v.statut !== 'annulee');
    const caTotal = valides.reduce((s, v) => s + parseFloat(v.total), 0);
    const todayCount = valides.filter((v) => (v.date_vente || '').slice(0, 10) === today).length;

    document.getElementById('kpi-ventes-total').textContent = valides.length;
    document.getElementById('kpi-ventes-ca').textContent = formatMoney(caTotal);
    document.getElementById('kpi-ventes-jour').textContent = todayCount;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="8">${pharmaEmpty('Aucune vente', 'ti-shopping-cart')}</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map((v) => {
      const annulee = v.statut === 'annulee';
      return `<tr class="${annulee ? 'text-muted' : ''}">
      <td><span class="badge badge-light">#${v.id}</span></td>
      <td>${formatDate(v.date_vente)}</td>
      <td>${v.vendeur_nom || '—'}</td>
      <td><div class="pharma-entity-cell">
        <span class="pharma-entity-cell__icon"><i class="ti-user"></i></span>
        ${v.client_nom || '<span class="text-muted">Passage</span>'}
      </div></td>
      <td><span class="badge badge-light">${v.nb_lignes} art.</span></td>
      <td><strong class="text-primary">${formatMoney(v.total)}</strong></td>
      <td>${annulee ? '<span class="badge badge-secondary">Annulée</span>' : '<span class="badge badge-success">Validée</span>'}</td>
      <td><div class="pharma-actions">
        <button class="btn-action btn-action--view" title="Détail" data-detail="${v.id}"><i class="ti-eye"></i></button>
        ${!annulee ? `<button class="btn-action btn-action--view" title="Facture" data-facture="${v.id}"><i class="ti-receipt"></i></button>
        <button class="btn-action btn-action--view" title="Ticket" data-ticket="${v.id}"><i class="ti-printer"></i></button>` : ''}
        ${canCancel && !annulee ? `<button class="btn-action btn-action--delete" title="Annuler" data-cancel="${v.id}"><i class="ti-undo"></i></button>` : ''}
      </div></td>
    </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-detail]').forEach((b) => b.addEventListener('click', () => showDetail(b.dataset.detail)));
    tbody.querySelectorAll('[data-ticket]').forEach((b) => b.addEventListener('click', () => {
      window.open(`api/index.php?r=tickets/${b.dataset.ticket}`, '_blank', 'width=400,height=600');
    }));
    tbody.querySelectorAll('[data-facture]').forEach((b) => b.addEventListener('click', async () => {
      try {
        const f = await PharmaAPI.get(`factures/vente/${b.dataset.facture}`);
        window.open(`api/index.php?r=factures/${f.data.id}/html`, '_blank');
      } catch (e) {
        PharmaSwal.error('Facture', e.message);
      }
    }));
    tbody.querySelectorAll('[data-cancel]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirm({ icon: 'warning', title: 'Annuler cette vente ?', html: 'Le stock sera remis à jour (FEFO inversé).', confirmText: 'Oui, annuler' })) return;
      try {
        await PharmaAPI.post(`ventes/${b.dataset.cancel}/annuler`, {});
        PharmaSwal.toast('Vente annulée');
        load();
      } catch (e) { PharmaSwal.error('Erreur', e.message); }
    }));

    if ($.fn.DataTable.isDataTable('#ventes-table')) $('#ventes-table').DataTable().destroy();
    initDataTable('#ventes-table');
  }

  async function showDetail(id) {
    try {
      const d = await PharmaAPI.get(`ventes/${id}`);
      const lines = d.data.lignes.map((l) =>
        `<tr><td>${l.medicament_nom}</td><td>${l.quantite}</td><td>${formatMoney(l.prix_vente)}</td><td><strong>${formatMoney(l.quantite * l.prix_vente)}</strong></td></tr>`
      ).join('');
      Swal.fire({
        customClass: { popup: 'pharma-swal-popup', title: 'pharma-swal-title', confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm' },
        buttonsStyling: false,
        title: `Vente #${d.data.id}`,
        html: `<p class="text-muted small mb-2">${formatDate(d.data.date_vente)} · ${d.data.client_nom || 'Client passage'} · ${d.data.vendeur_nom || ''}</p>
               <table class="table table-sm pharma-table-compact mt-2"><thead><tr><th>Produit</th><th>Qté</th><th>P.U.</th><th>Total</th></tr></thead><tbody>${lines}</tbody></table>
               <p class="text-end mt-3 mb-0 pharma-cart-summary__total"><span>Total</span><span>${formatMoney(d.data.total)}</span></p>`,
        confirmButtonText: 'Fermer',
        width: 520,
      });
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  }

  load();
})();
