(async () => {
  await PharmaLayout.init();
  const periode = document.getElementById('periode');
  const caEl = document.getElementById('rapport-ca');
  const topEl = document.getElementById('rapport-top');
  const margeEl = document.getElementById('rapport-marges');
  const mortEl = document.getElementById('rapport-stock-mort');

  async function load() {
    try {
      const res = await PharmaAPI.get(`rapports?jours=${periode.value}`);
      caEl.textContent = formatMoney(res.ca_periode);
      topEl.innerHTML = res.top_ventes.map((r) => `<tr><td>${r.nom}</td><td>${r.qte}</td><td>${formatMoney(r.ca)}</td></tr>`).join('') || '<tr><td colspan="3">Aucune donnée</td></tr>';
      margeEl.innerHTML = res.marges_categories.map((r) => {
        const marge = r.ca - r.cout;
        return `<tr><td>${r.categorie || 'Sans catégorie'}</td><td>${formatMoney(r.ca)}</td><td>${formatMoney(marge)}</td></tr>`;
      }).join('') || '<tr><td colspan="3">Aucune donnée</td></tr>';
      mortEl.innerHTML = res.stock_mort.map((r) => `<tr><td>${r.nom}</td><td>${r.stock_actuel}</td><td>${formatMoney(r.prix_achat * r.stock_actuel)}</td></tr>`).join('') || '<tr><td colspan="3">Aucun stock dormant</td></tr>';
    } catch (e) { PharmaSwal.error('Erreur', e.message); }
  }

  periode.addEventListener('change', load);
  document.getElementById('btn-export-csv')?.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.href = `api/index.php?r=rapports&jours=${periode.value}&export=csv`;
  });
  load();
})();
