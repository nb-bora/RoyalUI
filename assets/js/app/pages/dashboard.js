(async () => {
  const user = await PharmaLayout.init();
  if (!user) return;

  const role = user.role;
  const headerDesc = document.querySelector('.pharma-page-desc');
  const headerActions = document.querySelector('.pharma-page-actions');
  const briefingEl = document.getElementById('briefing-panel');
  const recoEl = document.getElementById('recommandations-list');
  const adminOnly = document.querySelectorAll('.dash-admin-only');
  const gestionOnly = document.querySelectorAll('.dash-gestion-only');
  const vendeurOnly = document.querySelectorAll('.dash-vendeur-only');

  adminOnly.forEach((el) => { el.style.display = role === 'admin' ? '' : 'none'; });
  gestionOnly.forEach((el) => { el.style.display = ['admin', 'gestionnaire'].includes(role) ? '' : 'none'; });
  vendeurOnly.forEach((el) => { el.style.display = role === 'vendeur' ? '' : 'none'; });

  if (role === 'vendeur' && headerActions) {
    headerActions.innerHTML = `
      <a href="caisse.html" class="btn btn-pharma btn-pharma-primary"><i class="ti-shopping-cart-full"></i> Caisse</a>
      <a href="ventes.html" class="btn btn-pharma btn-pharma-outline"><i class="ti-time"></i> Mes ventes</a>`;
    if (headerDesc) headerDesc.textContent = 'Votre activité du jour et accès rapide à la caisse.';
  } else if (role === 'gestionnaire' && headerActions) {
    headerActions.innerHTML = `
      <a href="stock.html#etat" class="btn btn-pharma btn-pharma-outline"><i class="ti-archive"></i> Stock</a>
      <a href="achats.html#reception" class="btn btn-pharma btn-pharma-primary"><i class="ti-bag"></i> Réception</a>
      <a href="home.html#recommandations" class="btn btn-pharma btn-pharma-success"><i class="ti-light-bulb"></i> Recommandations</a>`;
  }

  try {
    const [dash, briefing, recos] = await Promise.all([
      PharmaAPI.get('dashboard'),
      PharmaAPI.get('briefing'),
      ['admin', 'gestionnaire'].includes(role)
        ? PharmaAPI.get('recommandations?statut=nouvelle').catch(() => ({ data: [] }))
        : Promise.resolve({ data: [] }),
    ]);

    const b = briefing.data;
    if (briefingEl && b) {
      let html = `<div class="pharma-briefing"><h5>${b.salutation}</h5>`;
      b.sections?.forEach((s) => {
        if (s.type === 'favoris' && s.produits?.length) {
          html += `<p class="mb-1"><strong>Favoris caisse :</strong> ${s.produits.map((p) => p.nom).join(', ')}</p>`;
        } else if (s.text) {
          html += `<p class="text-muted mb-2">${s.text}</p>`;
        }
      });
      if (b.actions?.length) {
        html += '<div class="d-flex flex-wrap gap-2 mt-2">';
        b.actions.forEach((a) => {
          html += `<a href="${a.href}" class="btn btn-pharma ${a.primary ? 'btn-pharma-primary' : 'btn-pharma-outline'} btn-sm">${a.label}</a>`;
        });
        html += '</div>';
      }
      html += '</div>';
      briefingEl.innerHTML = html;
    }

    const k = dash.kpis;
    document.getElementById('kpi-ca').textContent = formatMoney(k.ca_jour);
    document.getElementById('kpi-ventes').textContent = k.ventes_jour;
    document.getElementById('kpi-stock-bas').textContent = k.stock_bas + k.ruptures;
    document.getElementById('kpi-peremption').textContent = k.peremption_30j;

    if (recoEl && recos.data?.length) {
      recoEl.innerHTML = recos.data.map((r) => `
        <div class="pharma-alert-item pharma-alert-item--info">
          <i class="ti-light-bulb text-primary"></i>
          <div class="flex-grow-1"><strong>${r.titre}</strong><br><small>${r.message}</small></div>
          <button type="button" class="btn btn-pharma btn-pharma-primary btn-sm" data-reco="${r.id}">Appliquer</button>
          <button type="button" class="btn btn-pharma btn-pharma-outline btn-sm" data-ignore="${r.id}">Ignorer</button>
        </div>`).join('');
      recoEl.querySelectorAll('[data-reco]').forEach((btn) => btn.addEventListener('click', async () => {
        await PharmaAPI.put(`recommandations/${btn.dataset.reco}`, { statut: 'appliquee', creer_bc: true });
        PharmaSwal.toast('Bon de commande créé');
        location.reload();
      }));
      recoEl.querySelectorAll('[data-ignore]').forEach((btn) => btn.addEventListener('click', async () => {
        await PharmaAPI.put(`recommandations/${btn.dataset.ignore}`, { statut: 'ignoree' });
        btn.closest('.pharma-alert-item')?.remove();
      }));
    } else if (recoEl) {
      recoEl.innerHTML = pharmaEmpty('Aucune recommandation en attente', 'ti-check-box');
    }

    renderChart(dash.ventes_7j);
    renderTop(dash.top_produits);
  } catch (e) {
    PharmaSwal.error('Erreur', e.message);
  }

  function renderChart(rows) {
    const canvas = document.getElementById('salesChart');
    if (!canvas || typeof Chart === 'undefined' || role === 'vendeur') {
      canvas?.closest('.grid-margin')?.classList.add('d-none');
      return;
    }
    const labels = [];
    const values = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      const key = d.toISOString().slice(0, 10);
      labels.push(d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' }));
      const found = rows.find((r) => r.jour === key);
      values.push(found ? parseFloat(found.total) : 0);
    }
    new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'CA (FCFA)',
          data: values,
          borderColor: '#248afd',
          backgroundColor: 'rgba(36, 138, 253, 0.08)',
          fill: true,
          tension: 0.4,
        }],
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });
  }

  function renderTop(rows) {
    const el = document.getElementById('top-produits');
    if (!el) return;
    if (!rows.length) {
      el.innerHTML = pharmaEmpty('Pas encore de ventes', 'ti-bar-chart');
      return;
    }
    el.innerHTML = rows.map((r, i) => `
      <div class="d-flex justify-content-between align-items-center py-2 ${i < rows.length - 1 ? 'border-bottom' : ''}">
        <span><span class="badge badge-primary me-2">${i + 1}</span>${r.nom}</span>
        <strong class="text-primary">${r.qte} vendus</strong>
      </div>`).join('');
  }

  function updateDateTime() {
    const btn = document.getElementById('current-datetime');
    if (!btn) return;
    btn.textContent = new Date().toLocaleDateString('fr-FR', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }
  updateDateTime();
  setInterval(updateDateTime, 60000);
})();
