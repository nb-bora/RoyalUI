(async () => {
  const user = await PharmaLayout.init();
  if (!user || user.role !== 'admin') {
    window.location.href = 'home.html';
    return;
  }

  const tbody = document.querySelector('#audit-table tbody');
  const statsEl = document.getElementById('audit-stats');

  try {
    const res = await PharmaAPI.get('audit');
    if (statsEl && res.stats?.length) {
      statsEl.innerHTML = res.stats.map((s) => `
        <div class="col-md-3 grid-margin stretch-card">
          <div class="card pharma-kpi pharma-kpi--compact"><div class="card-body">
            <p class="pharma-kpi__label">${s.action}</p>
            <h3 class="pharma-kpi__value">${s.nb}</h3>
          </div></div>
        </div>`).join('');
    }
    tbody.innerHTML = res.data.map((a) => `<tr>
      <td>${new Date(a.created_at).toLocaleString('fr-FR')}</td>
      <td>${a.utilisateur_nom || '—'}</td>
      <td><span class="badge badge-light">${a.action}</span></td>
      <td>${a.table_cible || '—'}</td>
      <td>${a.id_cible ?? '—'}</td>
    </tr>`).join('');
    initDataTable('#audit-table');
  } catch (e) {
    PharmaSwal.error('Erreur', e.message);
  }
})();
