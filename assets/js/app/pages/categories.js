(async () => {
  await PharmaLayout.init();
  initPageTabs();
  const form = document.getElementById('cat-form');
  const tbody = document.querySelector('#cat-table tbody');
  let editId = null;

  async function load() {
    const res = await PharmaAPI.get('categories');
    const el = document.getElementById('kpi-cat-total');
    if (el) el.textContent = res.data.length;

    if (!res.data.length) {
      tbody.innerHTML = `<tr><td colspan="3">${pharmaEmpty('Aucune catégorie', 'ti-tag')}</td></tr>`;
      return;
    }
    tbody.innerHTML = res.data.map((c) => `<tr>
      <td><div class="pharma-entity-cell"><span class="pharma-entity-cell__icon"><i class="ti-tag"></i></span><strong>${c.nom}</strong></div></td>
      <td class="text-muted">${formatDate(c.created_at)}</td>
      <td>${pharmaActions(`data-edit="${c.id}" data-nom="${c.nom.replace(/"/g, '&quot;')}"`, `data-del="${c.id}" data-name="${c.nom.replace(/"/g, '&quot;')}"`)}</td>
    </tr>`).join('');

    tbody.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
      editId = +b.dataset.edit;
      form.nom.value = b.dataset.nom;
      setFormMode(form, true, 'une catégorie');
      window.location.hash = 'form';
    }));
    tbody.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirmDelete('cette catégorie', b.dataset.name)) return;
      try {
        await PharmaAPI.del(`categories/${b.dataset.del}`);
        PharmaSwal.toast('Catégorie supprimée');
        load();
      } catch (err) { PharmaSwal.error('Erreur', err.message); }
    }));
    if ($.fn.DataTable.isDataTable('#cat-table')) $('#cat-table').DataTable().destroy();
    initDataTable('#cat-table');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = form.nom.value.trim();
    if (!await PharmaSwal.confirmSave(!!editId, name)) return;
    try {
      if (editId) await PharmaAPI.put(`categories/${editId}`, { nom: name });
      else await PharmaAPI.post('categories', { nom: name });
      PharmaSwal.toast(editId ? 'Catégorie modifiée' : 'Catégorie ajoutée');
      form.reset();
      editId = null;
      setFormMode(form, false, 'une catégorie');
      load();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  setFormMode(form, false, 'une catégorie');
  load();
})();
