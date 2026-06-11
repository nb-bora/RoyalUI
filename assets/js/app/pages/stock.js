(async () => {
  await PharmaLayout.init();
  initPageTabs();

  const form = document.getElementById('stock-form');
  const medSelect = form.querySelector('[name="id_medicament"]');
  const typeInput = form.querySelector('[name="type_mouvement"]');
  const qtyInput = form.querySelector('[name="quantite"]');
  const stockTbody = document.querySelector('#stock-table tbody');
  const movTbody = document.querySelector('#mouv-table tbody');
  const movFeed = document.getElementById('mov-feed');
  const filterCountEl = document.getElementById('stock-filter-count');

  let medList = [];
  let stockList = [];
  let activeFilter = 'all';

  const FILTER_LABELS = {
    all: 'Tous',
    dispo: 'Disponibles',
    ok: 'Stock OK',
    bas: 'Stock bas',
    rupture: 'Rupture',
  };

  /** Statut calculé côté client (cohérent avec les filtres) */
  function getStatut(m) {
    const s = Number(m.stock_actuel);
    const min = Number(m.stock_min);
    if (s <= 0) return 'rupture';
    if (s <= min) return 'bas';
    return 'ok';
  }

  function movLabel(type) {
    const map = {
      ENTREE: { label: 'Entrée', cls: 'entree', icon: 'ti-arrow-down' },
      SORTIE: { label: 'Sortie', cls: 'sortie', icon: 'ti-arrow-up' },
    };
    return map[type] || { label: type, cls: 'sortie', icon: 'ti-exchange-vertical' };
  }

  function stockBar(m) {
    const st = getStatut(m);
    const max = Math.max(m.stock_min * 3, m.stock_actuel, 1);
    const pct = Math.min(100, Math.round((m.stock_actuel / max) * 100));
    return `<div class="pharma-stock-bar" title="${m.stock_actuel} unités (min. ${m.stock_min})">
      <div class="pharma-stock-bar__fill pharma-stock-bar__fill--${st}" style="width:${pct}%"></div>
    </div>`;
  }

  function matchesFilter(m) {
    const st = getStatut(m);
    const qte = Number(m.stock_actuel);
    switch (activeFilter) {
      case 'dispo': return qte > 0;
      case 'ok': return st === 'ok';
      case 'bas': return st === 'bas';
      case 'rupture': return st === 'rupture';
      default: return true;
    }
  }

  function filteredStock() {
    return stockList.filter(matchesFilter);
  }

  function renderKpis(data) {
    const withStatut = data.map((m) => ({ ...m, statut_stock: getStatut(m) }));
    document.getElementById('kpi-total').textContent = data.length;
    document.getElementById('kpi-ok').textContent = withStatut.filter((m) => m.statut_stock === 'ok').length;
    document.getElementById('kpi-bas').textContent = withStatut.filter((m) => m.statut_stock === 'bas').length;
    document.getElementById('kpi-rupture').textContent = withStatut.filter((m) => m.statut_stock === 'rupture').length;
  }

  function setActiveFilter(filter) {
    activeFilter = filter;
    document.querySelectorAll('#stock-filters .pharma-chip').forEach((c) => {
      c.classList.toggle('active', c.dataset.filter === filter);
    });
    document.querySelectorAll('.pharma-kpi--clickable').forEach((k) => {
      k.classList.toggle('pharma-kpi--active', k.dataset.filter === filter);
    });
    renderStockTable();
  }

  function renderStockTable() {
    const data = filteredStock();
    const total = stockList.length;

    if (filterCountEl) {
      const label = FILTER_LABELS[activeFilter] || activeFilter;
      filterCountEl.textContent = activeFilter === 'all'
        ? `${total} produit${total !== 1 ? 's' : ''}`
        : `${data.length} / ${total} · ${label}`;
    }

    if (!data.length) {
      stockTbody.innerHTML = `<tr><td colspan="5">${pharmaEmpty(
        activeFilter === 'all' ? 'Aucun produit en stock' : `Aucun produit : ${FILTER_LABELS[activeFilter]}`,
        'ti-archive'
      )}</td></tr>`;
      return;
    }

    stockTbody.innerHTML = data.map((m) => {
      const st = getStatut(m);
      return `<tr data-id="${m.id}" data-statut="${st}">
        <td><strong>${m.nom}</strong></td>
        <td><span class="text-muted">${m.categorie_nom || '—'}</span></td>
        <td>${stockBar(m)}</td>
        <td><strong class="text-${st === 'rupture' ? 'danger' : st === 'bas' ? 'warning' : 'dark'}">${m.stock_actuel}</strong>
          <small class="text-muted"> / min ${m.stock_min}</small></td>
        <td>${stockBadge(st)}</td>
      </tr>`;
    }).join('');

    stockTbody.querySelectorAll('tr[data-id]').forEach((row) => {
      row.addEventListener('click', () => {
        stockTbody.querySelectorAll('tr').forEach((r) => r.classList.remove('pharma-row--highlight'));
        row.classList.add('pharma-row--highlight');
        medSelect.value = row.dataset.id;
        medSelect.dispatchEvent(new Event('change'));
        window.location.hash = 'mouvement';
      });
    });
  }

  function renderMovFeed(rows) {
    const recent = rows.slice(0, 12);
    if (!recent.length) {
      movFeed.innerHTML = pharmaEmpty('Aucun mouvement récent', 'ti-exchange-vertical');
      return;
    }
    movFeed.innerHTML = recent.map((m) => {
      const t = movLabel(m.type_mouvement);
      const isEntree = m.type_mouvement === 'ENTREE';
      return `<div class="pharma-mov-item">
        <div class="pharma-mov-item__icon pharma-mov-item__icon--${t.cls}"><i class="${t.icon}"></i></div>
        <div class="pharma-mov-item__body">
          <div class="pharma-mov-item__title">${m.medicament_nom}</div>
          <div class="pharma-mov-item__meta">${t.label} · ${formatDate(m.date_mouvement)}</div>
        </div>
        <div class="pharma-mov-item__qty pharma-mov-item__qty--${isEntree ? 'plus' : 'minus'}">${isEntree ? '+' : '−'}${m.quantite}</div>
      </div>`;
    }).join('');
  }

  function renderMovTable(rows) {
    if (!rows.length) {
      movTbody.innerHTML = `<tr><td colspan="4">${pharmaEmpty('Aucun mouvement', 'ti-exchange-vertical')}</td></tr>`;
      return;
    }
    movTbody.innerHTML = rows.map((m) => {
      const t = movLabel(m.type_mouvement);
      return `<tr>
        <td>${formatDate(m.date_mouvement)}</td>
        <td>${m.medicament_nom}</td>
        <td><span class="pharma-mov-badge pharma-mov-badge--${t.cls}"><i class="${t.icon}"></i> ${t.label}</span></td>
        <td><strong>${m.quantite}</strong></td>
      </tr>`;
    }).join('');
    if ($.fn.DataTable.isDataTable('#mouv-table')) $('#mouv-table').DataTable().destroy();
    initDataTable('#mouv-table');
  }

  function updatePreview() {
    const empty = document.getElementById('preview-empty');
    const content = document.getElementById('preview-content');
    const id = +medSelect.value;
    const med = stockList.find((m) => m.id == id) || medList.find((m) => m.id == id);

    if (!med) {
      empty.classList.remove('d-none');
      content.classList.add('d-none');
      return;
    }

    const st = getStatut(med);
    empty.classList.add('d-none');
    content.classList.remove('d-none');

    const type = typeInput.value;
    const qte = Math.max(1, +qtyInput.value || 1);
    const after = type === 'ENTREE' ? med.stock_actuel + qte : med.stock_actuel - qte;
    const max = Math.max(med.stock_min * 3, med.stock_actuel, after, 1);
    const pct = Math.min(100, Math.round((med.stock_actuel / max) * 100));

    document.getElementById('preview-cat').textContent = med.categorie_nom || 'Sans catégorie';
    document.getElementById('preview-name').textContent = med.nom;
    document.getElementById('preview-badge').innerHTML = stockBadge(st);
    document.getElementById('preview-min').textContent = med.stock_min;
    document.getElementById('preview-max-label').textContent = max;
    document.getElementById('preview-qty').textContent = med.stock_actuel;
    document.getElementById('preview-after').textContent = after;
    document.getElementById('preview-delta').textContent = (type === 'ENTREE' ? '+' : '−') + qte;
    document.getElementById('preview-delta').className = `pharma-stat-pill__value ${type === 'ENTREE' ? 'text-success' : 'text-danger'}`;

    const gauge = document.getElementById('preview-gauge');
    gauge.style.width = pct + '%';
    gauge.className = 'pharma-stock-gauge__fill' +
      (st === 'rupture' ? ' pharma-stock-gauge__fill--rupture' : st === 'bas' ? ' pharma-stock-gauge__fill--bas' : '');

    const tip = document.getElementById('preview-tip');
    if (type === 'SORTIE' && after < 0) {
      tip.className = 'pharma-stock-preview__tip pharma-stock-preview__tip--danger';
      tip.innerHTML = '<i class="ti-alert"></i> Stock insuffisant — cette sortie sera refusée.';
    } else if (after <= med.stock_min) {
      tip.className = 'pharma-stock-preview__tip pharma-stock-preview__tip--warn';
      tip.innerHTML = '<i class="ti-info-alt"></i> Après ce mouvement, le stock passera sous le seuil minimum.';
    } else if (type === 'ENTREE' && st !== 'ok') {
      tip.className = 'pharma-stock-preview__tip pharma-stock-preview__tip--ok';
      tip.innerHTML = '<i class="ti-check"></i> Cette entrée améliorera le niveau de stock.';
    } else {
      tip.className = 'pharma-stock-preview__tip pharma-stock-preview__tip--ok';
      tip.innerHTML = '<i class="ti-check-box"></i> Mouvement valide — le stock sera mis à jour.';
    }
  }

  async function loadMeds() {
    const res = await PharmaAPI.get('medicaments');
    medList = res.data;
    medSelect.innerHTML = '<option value="">— Choisir un médicament —</option>' +
      res.data.map((m) => `<option value="${m.id}">${m.nom} (stock: ${m.stock_actuel})</option>`).join('');
  }

  async function loadStock() {
    const res = await PharmaAPI.get('stock');
    stockList = res.data;
    renderKpis(stockList);
    setActiveFilter(activeFilter);
  }

  async function loadMouvements() {
    const res = await PharmaAPI.get('stock/mouvements');
    renderMovFeed(res.data);
    renderMovTable(res.data);
  }

  document.querySelectorAll('#stock-filters .pharma-chip').forEach((chip) => {
    chip.addEventListener('click', () => setActiveFilter(chip.dataset.filter));
  });

  document.querySelectorAll('.pharma-kpi--clickable').forEach((kpi) => {
    kpi.addEventListener('click', () => setActiveFilter(kpi.dataset.filter));
  });

  document.querySelectorAll('.pharma-type-toggle__btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pharma-type-toggle__btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      typeInput.value = btn.dataset.type;
      updatePreview();
    });
  });

  document.querySelectorAll('.pharma-qty-stepper__btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const step = +btn.dataset.step;
      qtyInput.value = Math.max(1, (+qtyInput.value || 1) + step);
      updatePreview();
    });
  });

  medSelect.addEventListener('change', updatePreview);
  qtyInput.addEventListener('input', updatePreview);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const idMed = +form.id_medicament.value;
    const type = form.type_mouvement.value;
    const qte = +form.quantite.value;
    const med = medList.find((m) => m.id == idMed);
    if (!med) {
      PharmaSwal.error('Sélection requise', 'Choisissez un médicament.');
      return;
    }

    if (!await PharmaSwal.confirmStockMovement(type, med.nom, qte)) return;

    try {
      await PharmaAPI.post('stock', { id_medicament: idMed, type_mouvement: type, quantite: qte });
      PharmaSwal.toast('Mouvement enregistré');
      form.quantite.value = 1;
      await loadMeds();
      await loadStock();
      await loadMouvements();
      medSelect.value = idMed;
      updatePreview();
      window.location.hash = 'etat';
    } catch (err) { PharmaSwal.error('Erreur', err.message); }
  });

  await loadMeds();
  await loadStock();
  await loadMouvements();
})();
