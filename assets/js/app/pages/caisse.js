(async () => {
  const user = await PharmaLayout.init();
  if (!user) return;

  const cart = [];
  let medicaments = [];
  let favoriteIds = new Set();
  let activeFilter = 'all';

  const searchInput = document.getElementById('search-med');
  const searchClear = document.getElementById('search-clear');
  const resultsEl = document.getElementById('search-results');
  const cartEl = document.getElementById('cart-items');
  const totalEl = document.getElementById('cart-total');
  const clientSelect = document.getElementById('id_client');
  const cartBadge = document.getElementById('cart-badge');
  const btnClear = document.getElementById('btn-clear');
  const resultCount = document.getElementById('result-count');

  function updateDateTime() {
    const btn = document.getElementById('pos-datetime');
    if (!btn) return;
    btn.textContent = new Date().toLocaleDateString('fr-FR', {
      weekday: 'short', day: 'numeric', month: 'short',
      hour: '2-digit', minute: '2-digit',
    });
  }
  updateDateTime();
  setInterval(updateDateTime, 60000);

  try {
    const [medRes, cliRes, briefing] = await Promise.all([
      PharmaAPI.get('medicaments'),
      PharmaAPI.get('clients'),
      PharmaAPI.get('briefing').catch(() => ({ data: {} })),
    ]);
    medicaments = medRes.data;
    const favSection = briefing.data?.sections?.find((s) => s.type === 'favoris');
    if (favSection?.produits) {
      favoriteIds = new Set(favSection.produits.map((p) => p.id));
    }
    await ensureCaisseSession();
    clientSelect.innerHTML = '<option value="">— Client passage —</option>' +
      cliRes.data.map((c) => `<option value="${c.id}">${c.nom}</option>`).join('');
    updateKpisDispo();
  } catch (e) {
    PharmaSwal.error('Erreur', e.message);
  }

  function stockBarMini(m) {
    const max = Math.max(m.stock_min * 2, m.stock_actuel, 1);
    const pct = Math.min(100, Math.round((m.stock_actuel / max) * 100));
    const cls = m.stock_actuel <= 0 ? 'rupture' : m.statut_stock === 'bas' ? 'bas' : 'ok';
    return `<div class="pharma-stock-bar" style="margin-top:0.35rem"><div class="pharma-stock-bar__fill pharma-stock-bar__fill--${cls}" style="width:${pct}%"></div></div>`;
  }

  function filteredMeds() {
    let list = [...medicaments];
    if (activeFilter === 'dispo') list = list.filter((m) => m.stock_actuel > 0);
    else if (activeFilter === 'bas') list = list.filter((m) => m.statut_stock === 'bas' && m.stock_actuel > 0);

    const q = searchInput.value.trim().toLowerCase();
    if (q) {
      list = list.filter((m) =>
        m.nom.toLowerCase().includes(q) || (m.code_barre || '').toLowerCase().includes(q)
      );
    }
    if (!q && favoriteIds.size) {
      list.sort((a, b) => (favoriteIds.has(b.id) ? 1 : 0) - (favoriteIds.has(a.id) ? 1 : 0));
    }
    return list;
  }

  function updateKpisDispo() {
    const dispo = medicaments.filter((m) => m.stock_actuel > 0).length;
    document.getElementById('pos-kpi-dispo').textContent = dispo;
  }

  function updateKpisCart() {
    const units = cart.reduce((s, c) => s + c.quantite, 0);
    const total = cart.reduce((s, c) => s + c.prix_vente * c.quantite, 0);
    document.getElementById('pos-kpi-items').textContent = units;
    document.getElementById('pos-kpi-total').textContent = formatMoney(total);
    document.getElementById('summary-lines').textContent = cart.length;
    document.getElementById('summary-units').textContent = units;
    totalEl.textContent = formatMoney(total);
    cartBadge.textContent = units;
    cartBadge.dataset.count = units;
    btnClear.disabled = cart.length === 0;
    document.getElementById('btn-valider').disabled = cart.length === 0;
  }

  let debounce;
  searchInput.addEventListener('input', () => {
    searchClear.classList.toggle('d-none', !searchInput.value);
    clearTimeout(debounce);
    debounce = setTimeout(renderSearch, 200);
  });

  searchClear.addEventListener('click', () => {
    searchInput.value = '';
    searchClear.classList.add('d-none');
    searchInput.focus();
    renderSearch();
  });

  document.querySelectorAll('#pos-filters .pharma-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('#pos-filters .pharma-chip').forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      activeFilter = chip.dataset.filter;
      renderSearch();
    });
  });

  async function ensureCaisseSession() {
    const res = await PharmaAPI.get('caisse/session');
    if (!res.data) {
      await PharmaAPI.post('caisse/ouvrir', { fond_caisse: 0 });
    }
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
    if (e.key === 'F10') { e.preventDefault(); document.getElementById('btn-valider')?.click(); }
    if (e.key === 'Escape' && document.activeElement === searchInput) {
      searchInput.value = '';
      searchClear.classList.add('d-none');
      renderSearch();
    }
  });

  function renderSearch() {
    const list = filteredMeds().slice(0, 48);
    resultCount.textContent = `${list.length} produit${list.length !== 1 ? 's' : ''}`;

    if (!list.length) {
      resultsEl.innerHTML = `<div class="pharma-pos-grid-empty">${pharmaEmpty('Aucun produit trouvé', 'ti-search')}</div>`;
      return;
    }

    resultsEl.innerHTML = list.map((m) => {
      const rupture = m.stock_actuel <= 0;
      const inCart = cart.find((c) => c.id_medicament === m.id);
      const cartHint = inCart ? ` · ${inCart.quantite} au panier` : '';
      return `<button type="button" class="pharma-product-card${rupture ? ' pharma-product-card--rupture' : ''}" data-id="${m.id}" ${rupture ? 'disabled' : ''}>
        <div class="pharma-product-card__icon"><i class="ti-package"></i></div>
        <div class="pharma-product-card__main">
          <div class="pharma-product-card__line1">
            <span class="pharma-product-card__name">${m.nom}</span>
            ${rupture ? '<span class="badge-stock-rupture">Rupture</span>' : stockBadge(m.statut_stock)}
          </div>
          <div class="pharma-product-card__line2">
            ${m.categorie_nom ? `<span class="pharma-product-card__cat">${m.categorie_nom}</span>` : ''}
            <div class="pharma-product-card__bar">${stockBarMini(m)}</div>
          </div>
        </div>
        <div class="pharma-product-card__side">
          <span class="pharma-product-card__price">${formatMoney(m.prix_vente)}</span>
          <span class="pharma-product-card__stock">Qté ${m.stock_actuel}${cartHint}</span>
        </div>
        <div class="pharma-product-card__add"><i class="ti-plus"></i></div>
      </button>`;
    }).join('');

    resultsEl.querySelectorAll('[data-id]').forEach((btn) => {
      btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id, 10)));
    });
  }

  function addToCart(id) {
    const med = medicaments.find((m) => m.id == id);
    if (!med || med.stock_actuel <= 0) return;

    const inCart = cart.find((c) => c.id_medicament === id);
    const qte = (inCart ? inCart.quantite : 0) + 1;
    if (qte > med.stock_actuel) {
      PharmaSwal.error('Stock insuffisant', `Il ne reste que ${med.stock_actuel} unité(s) de ${med.nom}.`);
      return;
    }

    if (inCart) inCart.quantite = qte;
    else cart.push({
      id_medicament: id,
      nom: med.nom,
      prix_vente: parseFloat(med.prix_vente),
      quantite: 1,
      stock: med.stock_actuel,
    });

    renderCart();
    renderSearch();
    PharmaSwal.toast(`${med.nom} ajouté`, 'info');
  }

  function renderCart() {
    if (!cart.length) {
      cartEl.innerHTML = `<div class="pharma-cart-empty">${pharmaEmpty('Panier vide', 'ti-shopping-cart')}<p class="small text-muted mt-2">Recherchez un produit à gauche</p></div>`;
      updateKpisCart();
      return;
    }

    cartEl.innerHTML = cart.map((item, i) => {
      const sub = item.prix_vente * item.quantite;
      return `<div class="pharma-cart-item">
        <div class="pharma-cart-item__info">
          <div class="pharma-cart-item__name">${item.nom}</div>
          <div class="pharma-cart-item__meta">${formatMoney(item.prix_vente)} / unité</div>
        </div>
        <div class="pharma-cart-item__qty">
          <button type="button" data-minus="${i}" aria-label="Diminuer">−</button>
          <span>${item.quantite}</span>
          <button type="button" data-plus="${i}" aria-label="Augmenter">+</button>
        </div>
        <div class="pharma-cart-item__subtotal">${formatMoney(sub)}</div>
        <button type="button" class="pharma-cart-item__remove" data-remove="${i}" aria-label="Supprimer"><i class="ti-trash"></i></button>
      </div>`;
    }).join('');

    cartEl.querySelectorAll('[data-minus]').forEach((b) => b.addEventListener('click', () => changeQty(+b.dataset.minus, -1)));
    cartEl.querySelectorAll('[data-plus]').forEach((b) => b.addEventListener('click', () => changeQty(+b.dataset.plus, 1)));
    cartEl.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', async () => {
      if (!await PharmaSwal.confirmDelete('cet article', cart[+b.dataset.remove].nom)) return;
      cart.splice(+b.dataset.remove, 1);
      renderCart();
      renderSearch();
    }));

    updateKpisCart();
  }

  function changeQty(index, delta) {
    const item = cart[index];
    const med = medicaments.find((m) => m.id == item.id_medicament);
    const newQte = item.quantite + delta;
    if (newQte < 1) { cart.splice(index, 1); }
    else if (newQte > med.stock_actuel) {
      PharmaSwal.error('Stock insuffisant', `Maximum : ${med.stock_actuel}`);
      return;
    } else { item.quantite = newQte; }
    renderCart();
    renderSearch();
  }

  btnClear.addEventListener('click', async () => {
    if (!cart.length) return;
    if (!await PharmaSwal.confirmDelete('le panier entier', `${cart.length} article(s)`)) return;
    cart.length = 0;
    renderCart();
    renderSearch();
    PharmaSwal.toast('Panier vidé');
  });

  document.getElementById('btn-valider').addEventListener('click', async () => {
    if (!cart.length) { PharmaSwal.error('Panier vide', 'Ajoutez au moins un produit.'); return; }
    const total = cart.reduce((s, c) => s + c.prix_vente * c.quantite, 0);
    if (!await PharmaSwal.confirmSale(formatMoney(total), cart.length)) return;

    try {
      const res = await PharmaAPI.post('ventes', {
        id_client: clientSelect.value || null,
        lignes: cart.map((c) => ({ id_medicament: c.id_medicament, quantite: c.quantite, prix_vente: c.prix_vente })),
      });
      const print = await Swal.fire({
        customClass: { popup: 'pharma-swal-popup', confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm', cancelButton: 'pharma-swal-btn pharma-swal-btn--cancel' },
        buttonsStyling: false,
        icon: 'success',
        title: 'Vente enregistrée',
        html: `Ticket #${res.id} — Total : <strong>${formatMoney(res.total)}</strong>`,
        showCancelButton: true,
        confirmButtonText: '<i class="ti-printer"></i> Imprimer ticket',
        cancelButtonText: 'Continuer',
      });
      if (print.isConfirmed) {
        window.open(`api/index.php?r=tickets/${res.id}`, '_blank', 'width=400,height=600');
      }
      cart.length = 0;
      clientSelect.value = '';
      const medRes = await PharmaAPI.get('medicaments');
      medicaments = medRes.data;
      updateKpisDispo();
      renderCart();
      renderSearch();
      searchInput.focus();
    } catch (e) {
      PharmaSwal.error('Vente refusée', e.message);
    }
  });

  document.getElementById('btn-cloture-caisse')?.addEventListener('click', async () => {
    const { value: caReel } = await Swal.fire({
      customClass: { popup: 'pharma-swal-popup', confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm', cancelButton: 'pharma-swal-btn pharma-swal-btn--cancel' },
      buttonsStyling: false,
      title: 'Clôture de caisse',
      input: 'number',
      inputLabel: 'Montant réel en caisse (FCFA)',
      inputAttributes: { min: 0, step: 1 },
      showCancelButton: true,
      confirmButtonText: 'Clôturer',
    });
    if (caReel === undefined) return;
    try {
      const res = await PharmaAPI.post('caisse/cloturer', { ca_reel: parseFloat(caReel) || 0 });
      Swal.fire({
        customClass: { popup: 'pharma-swal-popup', confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm' },
        buttonsStyling: false,
        icon: 'info',
        title: 'Caisse clôturée',
        html: `CA théorique : <strong>${formatMoney(res.ca_theorique)}</strong><br>Écart : <strong>${formatMoney(res.ecart)}</strong>`,
      });
      await PharmaAPI.post('caisse/ouvrir', { fond_caisse: 0 });
    } catch (e) {
      PharmaSwal.error('Erreur', e.message);
    }
  });

  renderSearch();
  renderCart();
})();
