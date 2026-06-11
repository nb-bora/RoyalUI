(async () => {
  await PharmaLayout.init();
  initPageTabs();
  const form = document.getElementById('four-form');
  const tbody = document.querySelector('#four-table tbody');
  let editId = null;
  let lastData = [];

  function renderStats(stats) {
    const s = stats || { total: 0, nb_achats: 0, montant_total: 0 };
    document.getElementById('kpi-four-total').textContent = s.total ?? 0;
    document.getElementById('kpi-four-achats').textContent = s.nb_achats ?? 0;
    document.getElementById('kpi-four-montant').textContent = formatMoney(s.montant_total ?? 0);
  }

  async function load() {
    try {
      const res = await PharmaAPI.get('fournisseurs');
      lastData = Array.isArray(res.data) ? res.data : [];
      renderStats(res.stats);

      if ($.fn.DataTable.isDataTable('#four-table')) {
        $('#four-table').DataTable().destroy();
      }

      if (!lastData.length) {
        tbody.innerHTML = `<tr><td colspan="5">${pharmaEmpty('Aucun fournisseur — ajoutez-en un pour les réceptions', 'ti-truck')}</td></tr>`;
        return;
      }

      tbody.innerHTML = lastData.map((f) => `<tr>
        <td><div class="pharma-entity-cell"><span class="pharma-entity-cell__icon"><i class="ti-truck"></i></span><strong>${f.nom}</strong></div></td>
        <td class="text-muted">${f.telephone || '—'}${f.email ? `<br><small>${f.email}</small>` : ''}</td>
        <td><span class="badge badge-light">${f.nb_achats || 0}</span></td>
        <td><strong class="text-primary">${formatMoney(f.montant_achats || 0)}</strong></td>
        <td>${pharmaActions(`data-edit="${f.id}"`, `data-del="${f.id}" data-name="${f.nom.replace(/"/g, '&quot;')}"`)}</td>
      </tr>`).join('');

      tbody.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
        const f = lastData.find((x) => x.id == +b.dataset.edit);
        if (!f) return;
        editId = f.id;
        form.nom.value = f.nom;
        form.telephone.value = f.telephone || '';
        form.email.value = f.email || '';
        setFormMode(form, true, 'un fournisseur');
        window.location.hash = 'form';
      }));
      tbody.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', async () => {
        if (!await PharmaSwal.confirmDelete('ce fournisseur', b.dataset.name)) return;
        try {
          await PharmaAPI.del(`fournisseurs/${b.dataset.del}`);
          PharmaSwal.toast('Fournisseur supprimé');
          load();
        } catch (err) { PharmaSwal.error('Erreur', err.message); }
      }));

      initDataTable('#four-table');
    } catch (err) {
      renderStats({ total: 0, nb_achats: 0, montant_total: 0 });
      PharmaSwal.error('Impossible de charger les fournisseurs', err.message);
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = form.nom.value.trim();
    if (!await PharmaSwal.confirmSave(!!editId, name)) return;
    const body = { nom: name, telephone: form.telephone.value, email: form.email.value };
    try {
      if (editId) await PharmaAPI.put(`fournisseurs/${editId}`, body);
      else await PharmaAPI.post('fournisseurs', body);
      PharmaSwal.toast(editId ? 'Fournisseur modifié' : 'Fournisseur ajouté');
      form.reset();
      editId = null;
      setFormMode(form, false, 'un fournisseur');
      load();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  setFormMode(form, false, 'un fournisseur');
  load();
})();
