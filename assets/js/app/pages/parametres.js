(async () => {
  const user = await PharmaLayout.init();
  if (!user || user.role !== 'admin') {
    window.location.href = 'home.html';
    return;
  }
  initPageTabs();

  const form = document.getElementById('param-form');
  const regleInputs = document.querySelectorAll('.pharma-regle-input');
  const regleMap = {
    seuil_peremption_urgent: 'regle-peremption-urgent',
    seuil_surstock_ratio: 'regle-surstock',
    stock_dormant_jours: 'regle-dormant',
  };
  let dirty = false;

  function markDirty() {
    dirty = true;
    const st = document.getElementById('param-status');
    if (st) st.innerHTML = '<i class="ti-alert text-warning"></i> Modifications non enregistrées';
  }

  function markSaved() {
    dirty = false;
    const st = document.getElementById('param-status');
    if (st) st.innerHTML = '<i class="ti-check text-success"></i> Configuration à jour';
  }

  function renderKpis(d) {
    const nom = d.nom_pharmacie || '—';
    document.getElementById('kpi-nom').textContent = nom.length > 18 ? nom.slice(0, 16) + '…' : nom;
    document.getElementById('kpi-objectif').textContent = formatMoney(+d.objectif_ca_jour || 0);
    document.getElementById('kpi-marge').textContent = (d.seuil_marge_min ?? '—') + (d.seuil_marge_min != null ? ' %' : '');
    document.getElementById('kpi-peremp').textContent = (d.seuil_peremption_jours ?? '—') + (d.seuil_peremption_jours != null ? ' j' : '');
    document.getElementById('side-objectif').textContent = formatMoney(+d.objectif_ca_jour || 0);

    const days = Math.min(100, Math.max(5, +d.seuil_peremption_jours || 30));
    const fill = document.querySelector('#gauge-peremp .pharma-settings-gauge__fill');
    const label = document.getElementById('gauge-peremp-label');
    if (fill) fill.style.width = `${(days / 90) * 100}%`;
    if (label) label.textContent = days;
  }

  function renderPreview() {
    const body = Object.fromEntries(new FormData(form));
    const regles = {};
    regleInputs.forEach((inp) => {
      if (inp.dataset.regle) regles[inp.dataset.regle] = inp.value;
    });

    const rows = [
      ['Établissement', body.nom_pharmacie || '—'],
      ['Devise', body.devise || 'FCFA'],
      ['Adresse', body.adresse || '—'],
      ['Téléphone', body.telephone || '—'],
      ['Email alertes', body.email_alerte || '—'],
      ['Marge minimum', `${body.seuil_marge_min || '—'} %`],
      ['Objectif CA / jour', formatMoney(+body.objectif_ca_jour || 0)],
      ['Délai fournisseur', `${body.delai_fournisseur_jours || '—'} j`],
      ['Alerte péremption', `${body.seuil_peremption_jours || '—'} j`],
      ['Péremption urgente', `${regles.seuil_peremption_urgent || '—'} j`],
      ['Ratio surstock', regles.seuil_surstock_ratio || '—'],
      ['Stock dormant', `${regles.stock_dormant_jours || '—'} j`],
    ];

    document.getElementById('settings-preview').innerHTML = rows.map(([k, v]) => `
      <div class="pharma-settings-preview__row">
        <span class="pharma-settings-preview__key">${k}</span>
        <span class="pharma-settings-preview__val">${v}</span>
      </div>`).join('');
  }

  function applyData(res) {
    const d = res.data;
    Object.keys(d).forEach((k) => {
      const el = form.elements[k];
      if (el) el.value = d[k] ?? '';
    });
    (res.regles || []).forEach((r) => {
      const id = regleMap[r.cle];
      if (id) document.getElementById(id).value = r.valeur;
    });
    renderKpis(d);
    renderPreview();
    markSaved();
  }

  async function load() {
    try {
      const res = await PharmaAPI.get('parametres');
      applyData(res);
    } catch (e) {
      PharmaSwal.error('Erreur', e.message);
    }
  }

  async function runEngine() {
    try {
      await PharmaAPI.post('recommandations/executer', {});
      PharmaSwal.toast('Moteur de décision exécuté');
    } catch (e) {
      PharmaSwal.error('Erreur', e.message);
    }
  }

  form.addEventListener('input', () => {
    markDirty();
    renderKpis(Object.fromEntries(new FormData(form)));
    renderPreview();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!await PharmaSwal.confirmSave(true, 'les paramètres pharmacie')) return;

    const body = Object.fromEntries(new FormData(form));
    body.regles = {};
    Object.entries(regleMap).forEach(([cle, id]) => {
      body.regles[cle] = document.getElementById(id).value;
    });

    try {
      await PharmaAPI.put('parametres', body);
      PharmaSwal.toast('Paramètres enregistrés');
      markSaved();
      await load();
    } catch (err) {
      PharmaSwal.error('Erreur', err.message);
    }
  });

  document.getElementById('btn-run-engine')?.addEventListener('click', runEngine);
  document.getElementById('btn-run-engine-side')?.addEventListener('click', runEngine);
  document.getElementById('btn-reset-preview')?.addEventListener('click', () => {
    if (dirty && !window.confirm('Recharger et perdre les modifications ?')) return;
    load();
  });

  window.addEventListener('hashchange', () => {
    if (window.location.hash === '#apercu') renderPreview();
  });

  await load();
})();
