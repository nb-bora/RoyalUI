(async () => {
  await PharmaLayout.init();
  const tbody = document.querySelector('#ventes-table tbody');
  const today = new Date().toISOString().slice(0, 10);

  async function load() {
    const res = await PharmaAPI.get('ventes');
    const rows = res.data;
    const caTotal = rows.reduce((s, v) => s + parseFloat(v.total), 0);
    const todayCount = rows.filter((v) => (v.date_vente || '').slice(0, 10) === today).length;

    document.getElementById('kpi-ventes-total').textContent = rows.length;
    document.getElementById('kpi-ventes-ca').textContent = formatMoney(caTotal);
    document.getElementById('kpi-ventes-jour').textContent = todayCount;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6">${pharmaEmpty('Aucune vente', 'ti-shopping-cart')}</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map((v) => `<tr>
      <td><span class="badge badge-light">#${v.id}</span></td>
      <td>${formatDate(v.date_vente)}</td>
      <td><div class="pharma-entity-cell">
        <span class="pharma-entity-cell__icon"><i class="ti-user"></i></span>
        ${v.client_nom || '<span class="text-muted">Passage</span>'}
      </div></td>
      <td><span class="badge badge-light">${v.nb_lignes} art.</span></td>
      <td><strong class="text-primary">${formatMoney(v.total)}</strong></td>
      <td>${pharmaActions(null, null, `data-detail="${v.id}"`)}</td>
    </tr>`).join('');

    tbody.querySelectorAll('[data-detail]').forEach((b) => b.addEventListener('click', async () => {
      try {
        const d = await PharmaAPI.get(`ventes/${b.dataset.detail}`);
        const lines = d.data.lignes.map((l) =>
          `<tr><td>${l.medicament_nom}</td><td>${l.quantite}</td><td>${formatMoney(l.prix_vente)}</td><td><strong>${formatMoney(l.quantite * l.prix_vente)}</strong></td></tr>`
        ).join('');
        Swal.fire({
          customClass: { popup: 'pharma-swal-popup', title: 'pharma-swal-title', confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm' },
          buttonsStyling: false,
          title: `Vente #${d.data.id}`,
          html: `<p class="text-muted small mb-2">${formatDate(d.data.date_vente)} · ${d.data.client_nom || 'Client passage'}</p>
                 <table class="table table-sm pharma-table-compact mt-2"><thead><tr><th>Produit</th><th>Qté</th><th>P.U.</th><th>Total</th></tr></thead><tbody>${lines}</tbody></table>
                 <p class="text-end mt-3 mb-0 pharma-cart-summary__total"><span>Total</span><span>${formatMoney(d.data.total)}</span></p>`,
          confirmButtonText: 'Fermer',
          width: 520,
        });
      } catch (err) { PharmaSwal.error('Erreur', err.message); }
    }));

    if ($.fn.DataTable.isDataTable('#ventes-table')) $('#ventes-table').DataTable().destroy();
    initDataTable('#ventes-table');
  }

  load();
})();
