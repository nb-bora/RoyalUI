(async () => {
  await PharmaLayout.init();
  initPageTabs();

  const form = document.getElementById('achat-form');
  const fourSelect = form.querySelector('[name="id_fournisseur"]');
  const medSelect = form.querySelector('[name="id_medicament"]');
  const lignesContainer = document.getElementById('achat-lignes');
  const tbody = document.querySelector('#achats-table tbody');
  const lignes = [];
  let medList = [];

  async function loadRefs() {
    const [f, m] = await Promise.all([PharmaAPI.get('fournisseurs'), PharmaAPI.get('medicaments')]);
    medList = m.data;
    fourSelect.innerHTML = '<option value="">— Choisir un fournisseur —</option>' +
      f.data.map((x) => `<option value="${x.id}">${x.nom}</option>`).join('');
    if (!f.data.length) {
      fourSelect.innerHTML += '<option disabled>Aucun fournisseur — créez-en un d\'abord</option>';
    }
    medSelect.innerHTML = '<option value="">— Choisir un médicament —</option>' +
      m.data.map((x) => `<option value="${x.id}" data-prix="${x.prix_achat}">${x.nom}</option>`).join('');
  }

  medSelect.addEventListener('change', () => {
    const opt = medSelect.selectedOptions[0];
    if (!opt || !opt.dataset.prix) return;
    form.prix_achat.value = opt.dataset.prix;
    if (!form.date_peremption.value) {
      const d = new Date();
      d.setFullYear(d.getFullYear() + 1);
      form.date_peremption.value = d.toISOString().slice(0, 10);
    }
  });

  function updateLignesKpi() {
    const el = document.getElementById('kpi-achats-lignes');
    if (el) el.textContent = lignes.length;
  }

  function renderLignes() {
    updateLignesKpi();
    lignesContainer.innerHTML = lignes.length
      ? '<div class="list-group">' + lignes.map((l, i) => `
          <div class="list-group-item d-flex justify-content-between align-items-center pharma-cart-line">
            <div><strong>${l.nom}</strong><br><small class="text-muted">${l.quantite} × ${formatMoney(l.prix_achat)} — pérem. ${formatDate(l.date_peremption)}</small></div>
            <button type="button" class="btn-action btn-action--delete" data-rm="${i}"><i class="ti-trash"></i></button>
          </div>`).join('') + '</div>'
      : pharmaEmpty('Ajoutez des lignes à la réception', 'ti-list');
    lignesContainer.querySelectorAll('[data-rm]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirmDelete('cette ligne', lignes[+b.dataset.rm].nom)) return;
      lignes.splice(+b.dataset.rm, 1);
      renderLignes();
    }));
  }

  document.getElementById('btn-add-ligne').addEventListener('click', async () => {
    const opt = medSelect.selectedOptions[0];
    if (!medSelect.value) { PharmaSwal.error('Sélection requise', 'Choisissez un médicament.'); return; }
    if (!form.quantite.value || !form.prix_achat.value) { PharmaSwal.error('Champs requis', 'Quantité et prix sont obligatoires.'); return; }
    lignes.push({
      id_medicament: +medSelect.value,
      nom: opt.text,
      quantite: +form.quantite.value,
      prix_achat: +form.prix_achat.value,
      date_peremption: form.date_peremption.value || new Date(Date.now() + 365 * 86400000).toISOString().slice(0, 10),
    });
    renderLignes();
    PharmaSwal.toast('Ligne ajoutée', 'info');
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!fourSelect.value) {
      PharmaSwal.error('Fournisseur requis', 'Sélectionnez un fournisseur ou créez-en un dans Fournisseurs.');
      return;
    }
    if (!lignes.length) { PharmaSwal.error('Panier vide', 'Ajoutez au moins une ligne.'); return; }
    if (!await PharmaSwal.confirmPurchase(lignes.length)) return;
    try {
      await PharmaAPI.post('achats', { id_fournisseur: fourSelect.value, lignes });
      PharmaSwal.success('Réception validée', 'Le stock a été mis à jour automatiquement.');
      lignes.length = 0;
      renderLignes();
      form.reset();
      await loadHistory();
      window.location.hash = 'historique';
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  async function loadHistory() {
    const res = await PharmaAPI.get('achats');
    const total = res.data.reduce((s, a) => s + parseFloat(a.total), 0);
    document.getElementById('kpi-achats-nb').textContent = res.data.length;
    document.getElementById('kpi-achats-total').textContent = formatMoney(total);

    if (!res.data.length) {
      tbody.innerHTML = `<tr><td colspan="4">${pharmaEmpty('Aucune réception enregistrée', 'ti-bag')}</td></tr>`;
      return;
    }
    tbody.innerHTML = res.data.map((a) => `<tr>
      <td>${formatDate(a.date_achat)}</td>
      <td><div class="pharma-entity-cell"><span class="pharma-entity-cell__icon"><i class="ti-truck"></i></span><strong>${a.fournisseur_nom}</strong></div></td>
      <td><span class="badge badge-light">${a.nb_lignes} lig.</span></td>
      <td><strong class="text-primary">${formatMoney(a.total)}</strong></td>
    </tr>`).join('');
    if ($.fn.DataTable.isDataTable('#achats-table')) $('#achats-table').DataTable().destroy();
    initDataTable('#achats-table');
  }

  await loadRefs();
  renderLignes();
  loadHistory();
})();
