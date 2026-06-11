(async () => {
  const user = await PharmaLayout.init();
  if (!user) return;

  const kpiEls = {
    ca_jour: document.getElementById('kpi-ca'),
    ventes_jour: document.getElementById('kpi-ventes'),
    stock_bas: document.getElementById('kpi-stock-bas'),
    peremption_30j: document.getElementById('kpi-peremption'),
  };

  try {
    const [dash, alertes] = await Promise.all([
      PharmaAPI.get('dashboard'),
      PharmaAPI.get('alertes'),
    ]);

    const k = dash.kpis;
    if (kpiEls.ca_jour) kpiEls.ca_jour.textContent = formatMoney(k.ca_jour);
    if (kpiEls.ventes_jour) kpiEls.ventes_jour.textContent = k.ventes_jour;
    if (kpiEls.stock_bas) kpiEls.stock_bas.textContent = k.stock_bas + k.ruptures;
    if (kpiEls.peremption_30j) kpiEls.peremption_30j.textContent = k.peremption_30j;

    renderAlerts(alertes);
    renderChart(dash.ventes_7j);
    renderTop(dash.top_produits);
  } catch (e) {
    PharmaSwal.error('Erreur', e.message);
  }

  function renderAlerts(data) {
    const el = document.getElementById('alertes-list');
    if (!el) return;
    const items = [];

    data.stock_bas.forEach((m) => {
      items.push(`<div class="pharma-alert-item pharma-alert-item--warning">
        <i class="ti-alert text-warning"></i>
        <div class="flex-grow-1"><strong>${m.nom}</strong> — stock : ${m.stock_actuel} (min : ${m.stock_min})</div>
        <a href="medicaments.html#liste" class="btn btn-pharma btn-pharma-outline btn-sm">Gérer</a>
      </div>`);
    });
    data.peremption.forEach((p) => {
      items.push(`<div class="pharma-alert-item pharma-alert-item--danger">
        <i class="ti-timer text-danger"></i>
        <div class="flex-grow-1"><strong>${p.nom}</strong> — péremption dans <strong>${p.jours_restants} j</strong> (${formatDate(p.date_peremption)})</div>
        <a href="stock.html#etat" class="btn btn-pharma btn-pharma-outline btn-sm">Stock</a>
      </div>`);
    });
    data.reappro.forEach((r) => {
      items.push(`<div class="pharma-alert-item pharma-alert-item--info">
        <i class="ti-shopping-cart text-primary"></i>
        <div class="flex-grow-1"><strong>${r.nom}</strong> — ${r.message} <small class="text-muted">(${r.ventes_30j} ventes/30j)</small></div>
        <a href="achats.html#reception" class="btn btn-pharma btn-pharma-primary btn-sm">Commander</a>
      </div>`);
    });

    el.innerHTML = items.length ? items.join('') : pharmaEmpty('Aucune alerte — tout est en ordre', 'ti-check-box');
  }

  function renderChart(rows) {
    const canvas = document.getElementById('salesChart');
    if (!canvas || typeof Chart === 'undefined') return;

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
          pointBackgroundColor: '#248afd',
          pointRadius: 5,
          pointHoverRadius: 7,
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
          x: { grid: { display: false } },
        },
      },
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
