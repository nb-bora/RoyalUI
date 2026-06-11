(async () => {
  await PharmaLayout.init();
  initPageTabs();
  const form = document.getElementById('client-form');
  const tbody = document.querySelector('#client-table tbody');
  let editId = null;

  async function load() {
    const res = await PharmaAPI.get('clients');
    const el = document.getElementById('kpi-clients-total');
    if (el) el.textContent = res.data.length;

    if (!res.data.length) {
      tbody.innerHTML = `<tr><td colspan="3">${pharmaEmpty('Aucun client', 'ti-user')}</td></tr>`;
      return;
    }
    tbody.innerHTML = res.data.map((c) => `<tr>
      <td><div class="pharma-entity-cell"><span class="pharma-entity-cell__icon"><i class="ti-user"></i></span><strong>${c.nom}</strong></div></td>
      <td>${c.telephone ? `<i class="ti-mobile text-muted"></i> ${c.telephone}` : '<span class="text-muted">—</span>'}</td>
      <td>${pharmaActions(`data-edit="${c.id}" data-nom="${c.nom.replace(/"/g, '&quot;')}" data-tel="${(c.telephone || '').replace(/"/g, '&quot;')}"`, `data-del="${c.id}" data-name="${c.nom.replace(/"/g, '&quot;')}"`)}</td>
    </tr>`).join('');

    tbody.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
      editId = +b.dataset.edit;
      form.nom.value = b.dataset.nom;
      form.telephone.value = b.dataset.tel;
      setFormMode(form, true, 'un client');
      window.location.hash = 'form';
    }));
    tbody.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirmDelete('ce client', b.dataset.name)) return;
      try {
        await PharmaAPI.del(`clients/${b.dataset.del}`);
        PharmaSwal.toast('Client supprimé');
        load();
      } catch (err) { PharmaSwal.error('Erreur', err.message); }
    }));
    if ($.fn.DataTable.isDataTable('#client-table')) $('#client-table').DataTable().destroy();
    initDataTable('#client-table');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = form.nom.value.trim();
    if (!await PharmaSwal.confirmSave(!!editId, name)) return;
    const body = { nom: name, telephone: form.telephone.value };
    try {
      if (editId) await PharmaAPI.put(`clients/${editId}`, body);
      else await PharmaAPI.post('clients', body);
      PharmaSwal.toast(editId ? 'Client modifié' : 'Client ajouté');
      form.reset();
      editId = null;
      setFormMode(form, false, 'un client');
      load();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  setFormMode(form, false, 'un client');
  load();
})();
