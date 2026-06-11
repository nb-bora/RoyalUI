function showToast(message, type = 'success') {
  const icon = type === 'error' ? 'error' : type === 'info' ? 'info' : 'success';
  if (typeof PharmaSwal !== 'undefined') {
    PharmaSwal.toast(message, icon);
    return;
  }
  if (typeof Swal !== 'undefined') {
    Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      customClass: { popup: 'pharma-swal-popup' },
    }).fire({ icon, title: message });
    return;
  }
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;';
    document.body.appendChild(container);
  }
  const el = document.createElement('div');
  const bg = type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8';
  el.style.cssText = `background:${bg};color:#fff;padding:12px 16px;margin-bottom:8px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);`;
  el.textContent = message;
  container.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
