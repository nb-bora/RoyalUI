(async () => {
  const user = await PharmaLayout.init();
  if (!user || !['admin', 'gestionnaire'].includes(user.role)) return;

  initPageTabs();
  const form = document.getElementById('med-form');
  const tbody = document.querySelector('#med-table tbody');
  const catSelect = form.querySelector('[name="id_categorie"]');
  let editId = null;
  let lastData = [];
  let activeFilter = 'all';

  function renderKpis(data) {
    document.getElementById('kpi-med-total').textContent = data.length;
    document.getElementById('kpi-med-ok').textContent = data.filter((m) => m.statut_stock === 'ok').length;
    document.getElementById('kpi-med-bas').textContent = data.filter((m) => m.statut_stock === 'bas').length;
    document.getElementById('kpi-med-rupture').textContent = data.filter((m) => m.statut_stock === 'rupture').length;
  }

  async function loadCategories() {
    const res = await PharmaAPI.get('categories');
    catSelect.innerHTML = '<option value="">— Catégorie —</option>' +
      res.data.map((c) => `<option value="${c.id}">${c.nom}</option>`).join('');
  }

  async function loadTable() {
    const res = await PharmaAPI.get('medicaments');
    lastData = res.data;
    renderKpis(lastData);

    const data = activeFilter === 'all' ? lastData : lastData.filter((m) => m.statut_stock === activeFilter);
    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="5">${pharmaEmpty('Aucun médicament', 'ti-package')}</td></tr>`;
      if ($.fn.DataTable.isDataTable('#med-table')) $('#med-table').DataTable().destroy();
      return;
    }

    tbody.innerHTML = data.map((m) => `<tr>
      <td><div class="pharma-entity-cell">
        <span class="pharma-entity-cell__icon"><i class="ti-package"></i></span>
        <div><strong>${m.nom}</strong>${m.code_barre ? `<br><small class="text-muted">${m.code_barre}</small>` : ''}</div>
      </div></td>
      <td><span class="text-muted">${m.categorie_nom || '—'}</span></td>
      <td>${formatMoney(m.prix_vente)} ${margeBadge(m.marge_pct)}<br><small class="text-muted">Achat ${formatMoney(m.prix_achat)}</small></td>
      <td><div class="d-flex align-items-center gap-2">${stockBarHtml(m.stock_actuel, m.stock_min, m.statut_stock)}<strong>${m.stock_actuel}</strong> ${stockBadge(m.statut_stock)}</div></td>
      <td>${pharmaActions(`data-edit="${m.id}"`, `data-del="${m.id}" data-name="${m.nom.replace(/"/g, '&quot;')}"`)}</td>
    </tr>`).join('');

    tbody.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => editMed(+b.dataset.edit)));
    tbody.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => deleteMed(+b.dataset.del, b.dataset.name)));
    if ($.fn.DataTable.isDataTable('#med-table')) $('#med-table').DataTable().destroy();
    initDataTable('#med-table');
  }

  bindFilterChips('med-filters', (f) => { activeFilter = f; loadTable(); });

  function editMed(id) {
    const m = lastData.find((x) => x.id == id);
    if (!m) return;
    editId = id;
    form.nom.value = m.nom;
    form.prix_achat.value = m.prix_achat;
    form.prix_vente.value = m.prix_vente;
    form.stock_actuel.value = m.stock_actuel;
    form.stock_min.value = m.stock_min;
    form.id_categorie.value = m.id_categorie || '';
    form.code_barre.value = m.code_barre || '';
    setFormMode(form, true, 'un médicament');
    window.location.hash = 'form';
  }

  async function deleteMed(id, name) {
    if (!await PharmaSwal.confirmDelete('ce médicament', name)) return;
    try {
      await PharmaAPI.del(`medicaments/${id}`);
      PharmaSwal.toast('Médicament désactivé');
      loadTable();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = form.nom.value.trim();
    if (!await PharmaSwal.confirmSave(!!editId, name)) return;
    const body = {
      nom: name,
      prix_achat: form.prix_achat.value,
      prix_vente: form.prix_vente.value,
      stock_actuel: form.stock_actuel.value,
      stock_min: form.stock_min.value,
      id_categorie: form.id_categorie.value || null,
      code_barre: form.code_barre.value,
    };
    try {
      if (editId) await PharmaAPI.put(`medicaments/${editId}`, body);
      else await PharmaAPI.post('medicaments', body);
      PharmaSwal.toast(editId ? 'Modifications enregistrées' : 'Médicament ajouté');
      form.reset();
      editId = null;
      setFormMode(form, false, 'un médicament');
      loadTable();
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  document.getElementById('btn-cancel')?.addEventListener('click', () => {
    form.reset();
    editId = null;
    setFormMode(form, false, 'un médicament');
  });

  setFormMode(form, false, 'un médicament');
  await loadCategories();
  await loadTable();

  async function loadImportLogs() {
    const el = document.getElementById('import-logs');
    if (!el) return;
    try {
      const res = await PharmaAPI.get('import/logs');
      if (!res.data.length) { el.innerHTML = '<em>Aucun import récent</em>'; return; }
      el.innerHTML = '<strong>Derniers imports</strong><ul class="mt-2 ps-3">' +
        res.data.slice(0, 5).map((l) =>
          `<li>${formatDate(l.created_at)} — ${l.lignes_ok} OK, ${l.lignes_erreur} err.</li>`
        ).join('') + '</ul>';
    } catch { /* ignore */ }
  }

  document.getElementById('import-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fileInput = document.getElementById('import-file');
    if (!fileInput?.files?.length) return;
    const fd = new FormData();
    fd.append('fichier', fileInput.files[0]);
    const btn = document.getElementById('btn-import');
    btn.disabled = true;
    try {
      const res = await PharmaAPI.upload('import/medicaments', fd);
      const box = document.getElementById('import-result');
      box.style.display = 'block';
      let errHtml = '';
      if (res.errors?.length) {
        errHtml = '<ul class="text-danger small mt-2">' +
          res.errors.map((er) => `<li>Ligne ${er.line} : ${er.message}</li>`).join('') + '</ul>';
      }
      box.innerHTML = `<div class="alert alert-success"><strong>${res.lignes_ok}</strong> ligne(s) importée(s), <strong>${res.lignes_erreur}</strong> erreur(s).</div>${errHtml}`;
      PharmaSwal.toast('Import terminé');
      fileInput.value = '';
      await loadTable();
      await loadImportLogs();
    } catch (err) {
      PharmaSwal.error('Import échoué', err.message);
    } finally {
      btn.disabled = false;
    }
  });

  loadImportLogs();
})();
