const PharmaSwal = (() => {
  const base = {
    customClass: {
      popup: 'pharma-swal-popup',
      title: 'pharma-swal-title',
      htmlContainer: 'pharma-swal-html',
      confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm',
      cancelButton: 'pharma-swal-btn pharma-swal-btn--cancel',
      icon: 'pharma-swal-icon',
    },
    buttonsStyling: false,
    reverseButtons: true,
    focusCancel: true,
    showClass: { popup: 'swal2-show' },
    hideClass: { popup: 'swal2-hide' },
  };

  async function confirm(opts) {
    if (typeof Swal === 'undefined') return window.confirm(opts.fallback || opts.text || 'Continuer ?');

    const isDanger = opts.type === 'delete';
    const isSuccess = opts.type === 'save';

    const result = await Swal.fire({
      ...base,
      title: opts.title || 'Confirmer l\'action',
      html: opts.html || opts.text || '',
      icon: opts.icon || (isDanger ? 'warning' : isSuccess ? 'question' : 'question'),
      showCancelButton: true,
      confirmButtonText: opts.confirmText || (isDanger ? '<i class="ti-trash"></i> Oui, supprimer' : '<i class="ti-check"></i> Oui, continuer'),
      cancelButtonText: opts.cancelText || '<i class="ti-close"></i> Annuler',
      customClass: {
        ...base.customClass,
        confirmButton: `pharma-swal-btn pharma-swal-btn--confirm${isDanger ? ' pharma-swal-btn--danger' : ''}${isSuccess ? ' pharma-swal-btn--success' : ''}`,
      },
    });
    return result.isConfirmed;
  }

  async function confirmDelete(entity, name) {
    return confirm({
      type: 'delete',
      icon: 'warning',
      title: 'Supprimer cet élément ?',
      html: `Vous êtes sur le point de supprimer <strong>${name || entity}</strong>.<br><small class="text-muted">Cette action est irréversible.</small>`,
      confirmText: '<i class="ti-trash"></i> Oui, supprimer',
      fallback: `Supprimer ${name || entity} ?`,
    });
  }

  async function confirmSave(isEdit, entity) {
    const action = isEdit ? 'modifier' : 'ajouter';
    return confirm({
      type: 'save',
      icon: 'question',
      title: isEdit ? 'Enregistrer les modifications ?' : `Confirmer l'ajout ?`,
      html: `Voulez-vous vraiment ${action} ${entity ? `<strong>${entity}</strong>` : 'cet élément'} ?`,
      confirmText: isEdit ? '<i class="ti-save"></i> Oui, enregistrer' : '<i class="ti-plus"></i> Oui, ajouter',
      fallback: `Confirmer ${action} ?`,
    });
  }

  async function confirmLogout() {
    return confirm({
      icon: 'question',
      title: 'Se déconnecter ?',
      html: 'Votre session sera fermée. Vous devrez vous reconnecter pour accéder à l\'application.',
      confirmText: '<i class="ti-power-off"></i> Oui, me déconnecter',
      cancelText: '<i class="ti-close"></i> Rester connecté',
      fallback: 'Se déconnecter ?',
    });
  }

  async function confirmSale(total, itemCount) {
    return confirm({
      type: 'save',
      icon: 'question',
      title: 'Valider cette vente ?',
      html: `<div style="text-align:center">
        <p style="margin:0 0 .5rem">${itemCount} article(s) dans le panier</p>
        <p style="font-size:1.4rem;font-weight:700;color:#248afd;margin:0">${total}</p>
        <small class="text-muted">Le stock sera mis à jour automatiquement.</small>
      </div>`,
      confirmText: '<i class="ti-check-box"></i> Oui, valider la vente',
      fallback: 'Valider la vente ?',
    });
  }

  async function confirmPurchase(lineCount) {
    return confirm({
      type: 'save',
      icon: 'question',
      title: 'Valider cet achat ?',
      html: `Confirmer la réception de <strong>${lineCount}</strong> ligne(s) ?<br><small class="text-muted">Le stock sera incrémenté automatiquement.</small>`,
      confirmText: '<i class="ti-check-box"></i> Oui, valider l\'achat',
      fallback: 'Valider l\'achat ?',
    });
  }

  async function confirmStockMovement(type, medName, qty) {
    const label = type === 'ENTREE' ? 'entrée' : 'sortie';
    return confirm({
      icon: 'question',
      title: `Confirmer le mouvement de stock ?`,
      html: `${label === 'entrée' ? 'Ajouter' : 'Retirer'} <strong>${qty}</strong> unité(s) de <strong>${medName}</strong> ?`,
      confirmText: '<i class="ti-check"></i> Oui, confirmer',
      fallback: 'Confirmer le mouvement ?',
    });
  }

  function toast(message, type = 'success') {
    if (typeof Swal === 'undefined') {
      showToastLegacy(message, type);
      return;
    }
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      customClass: { popup: 'pharma-swal-popup' },
      didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
      },
    });
    Toast.fire({ icon: type, title: message });
  }

  function success(title, text) {
    if (typeof Swal === 'undefined') { showToastLegacy(title, 'success'); return; }
    Swal.fire({ ...base, icon: 'success', title, html: text, confirmButtonText: 'OK', showCancelButton: false });
  }

  function error(title, text) {
    if (typeof Swal === 'undefined') { showToastLegacy(title, 'error'); return; }
    Swal.fire({
      ...base,
      icon: 'error',
      title: title || 'Erreur',
      html: text,
      confirmButtonText: 'Compris',
      showCancelButton: false,
      customClass: { ...base.customClass, confirmButton: 'pharma-swal-btn pharma-swal-btn--confirm pharma-swal-btn--danger' },
    });
  }

  function showToastLegacy(message, type) {
    if (typeof showToast === 'function') showToast(message, type);
  }

  return {
    confirm, confirmDelete, confirmSave, confirmLogout,
    confirmSale, confirmPurchase, confirmStockMovement,
    toast, success, error,
  };
})();
