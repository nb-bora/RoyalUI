(async () => {
  await PharmaLayout.init();
  initPageTabs();
  const form = document.getElementById('user-form');
  const tbody = document.querySelector('#user-table tbody');
  let editId = null;
  let lastData = [];

  async function load() {
    const res = await PharmaAPI.get('utilisateurs');
    lastData = res.data;
    if (!res.data.length) {
      tbody.innerHTML = `<tr><td colspan="4">${pharmaEmpty('Aucun utilisateur', 'ti-id-badge')}</td></tr>`;
      return;
    }
    const roleBadge = (r) => ({ admin: 'badge-primary', gestionnaire: 'badge-info', vendeur: 'badge-secondary' }[r] || 'badge-light');
    tbody.innerHTML = res.data.map((u) => `<tr>
      <td><strong>${u.nom}</strong></td>
      <td>${u.email}</td>
      <td><span class="badge ${roleBadge(u.role)}">${u.role}</span></td>
      <td>${pharmaActions(`data-edit="${u.id}"`, `data-del="${u.id}" data-name="${u.nom.replace(/"/g, '&quot;')}"`)}</td>
    </tr>`).join('');

    tbody.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
      const u = lastData.find((x) => x.id == +b.dataset.edit);
      if (!u) return;
      editId = u.id;
      form.nom.value = u.nom;
      form.email.value = u.email;
      form.role.value = u.role;
      form.password.value = '';
      form.password.required = false;
      setFormMode(form, true, 'un utilisateur');
      window.location.hash = 'form';
    }));
    tbody.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirmDelete('cet utilisateur', b.dataset.name)) return;
      try {
        await PharmaAPI.del(`utilisateurs/${b.dataset.del}`);
        PharmaSwal.toast('Utilisateur supprimé');
        load();
      } catch (err) { PharmaSwal.error('Erreur', err.message); }
    }));
    if ($.fn.DataTable.isDataTable('#user-table')) $('#user-table').DataTable().destroy();
    initDataTable('#user-table');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = form.nom.value.trim();
    if (!await PharmaSwal.confirmSave(!!editId, name)) return;
    const body = { nom: name, email: form.email.value, role: form.role.value };
    if (form.password.value) body.password = form.password.value;
    try {
      if (editId) {
        await PharmaAPI.put(`utilisateurs/${editId}`, body);
      } else {
        if (!form.password.value) { PharmaSwal.error('Mot de passe requis', 'Veuillez saisir un mot de passe.'); return; }
        body.password = form.password.value;
        await PharmaAPI.post('utilisateurs', body);
      }
      PharmaSwal.toast(editId ? 'Utilisateur modifié' : 'Utilisateur créé');
      form.reset();
      form.password.required = true;
      editId = null;
      setFormMode(form, false, 'un utilisateur');
      load();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  setFormMode(form, false, 'un utilisateur');
  load();
})();
