function stockBadge(statut) {
  const map = {
    ok: '<span class="badge-stock-ok">En stock</span>',
    bas: '<span class="badge-stock-bas">Stock bas</span>',
    rupture: '<span class="badge-stock-rupture">Rupture</span>',
  };
  return map[statut] || statut;
}

function pharmaActions(editAttrs, delAttrs, viewAttrs) {
  let html = '<div class="pharma-actions">';
  if (viewAttrs) html += `<button class="btn-action btn-action--view" title="Détail" ${viewAttrs}><i class="ti-eye"></i></button>`;
  if (editAttrs) html += `<button class="btn-action btn-action--edit" title="Modifier" ${editAttrs}><i class="ti-pencil"></i></button>`;
  if (delAttrs) html += `<button class="btn-action btn-action--delete" title="Supprimer" ${delAttrs}><i class="ti-trash"></i></button>`;
  return html + '</div>';
}

function pharmaEmpty(message, icon = 'ti-inbox') {
  return `<div class="pharma-empty"><i class="${icon}"></i><p>${message}</p></div>`;
}

function stockBarHtml(stockActuel, stockMin, statut) {
  const max = Math.max(stockMin * 2, stockActuel, 1);
  const pct = Math.min(100, Math.round((stockActuel / max) * 100));
  const cls = statut || (stockActuel <= 0 ? 'rupture' : stockActuel <= stockMin ? 'bas' : 'ok');
  return `<div class="pharma-stock-bar pharma-stock-bar--sm"><div class="pharma-stock-bar__fill pharma-stock-bar__fill--${cls}" style="width:${pct}%"></div></div>`;
}

function margeBadge(pct) {
  const n = parseFloat(pct) || 0;
  const cls = n < 15 ? 'badge-marge badge-marge--low' : 'badge-marge';
  return `<span class="${cls}">${n}% marge</span>`;
}

function bindFilterChips(containerId, onFilter) {
  document.querySelectorAll(`#${containerId} .pharma-chip`).forEach((chip) => {
    chip.addEventListener('click', () => {
      document.querySelectorAll(`#${containerId} .pharma-chip`).forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      onFilter(chip.dataset.filter);
    });
  });
}

function setFormMode(form, isEdit, entityLabel) {
  const title = form.closest('.pharma-card')?.querySelector('.pharma-card-header__title');
  const sub = form.closest('.pharma-card')?.querySelector('.pharma-card-header__sub');
  const submitBtn = form.querySelector('[type="submit"]');
  if (title) title.textContent = isEdit ? `Modifier ${entityLabel}` : `Ajouter ${entityLabel}`;
  if (sub) sub.textContent = isEdit ? 'Modifiez les champs puis confirmez' : 'Remplissez le formulaire puis confirmez';
  if (submitBtn) {
    submitBtn.innerHTML = isEdit
      ? '<i class="ti-save"></i> Enregistrer les modifications'
      : '<i class="ti-plus"></i> Ajouter';
  }
}
