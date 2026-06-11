const PharmaNotifications = (() => {
  let pollTimer = null;

  function prioriteClass(p) {
    return { critique: 'pharma-notif--critique', haute: 'pharma-notif--haute', info: 'pharma-notif--info' }[p] || '';
  }

  function renderDropdown(items, unread) {
    let panel = document.getElementById('notif-dropdown-panel');
    const nav = document.querySelector('.navbar-nav-right');
    if (!nav) return;

    let li = document.getElementById('notif-nav-item');
    if (!li) {
      li = document.createElement('li');
      li.className = 'nav-item dropdown';
      li.id = 'notif-nav-item';
      li.innerHTML = `<a class="nav-link pharma-nav-alert dropdown-toggle" href="#" data-bs-toggle="dropdown" id="notifDropdown">
        <i class="ti-bell"></i><span class="badge badge-danger" id="alert-count">0</span></a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown pharma-notif-panel" id="notif-dropdown-panel" style="width:360px;max-height:420px;overflow-y:auto"></div>`;
      nav.insertBefore(li, nav.firstChild);
      panel = document.getElementById('notif-dropdown-panel');
    }

    const badge = document.getElementById('alert-count');
    if (badge) {
      badge.textContent = unread;
      badge.style.display = unread > 0 ? '' : 'none';
    }

    if (!items.length) {
      panel.innerHTML = '<div class="dropdown-item-text text-muted p-3">Aucune notification</div>';
      return;
    }

    panel.innerHTML = `
      <div class="dropdown-item-text d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
        <strong>Notifications</strong>
        <button type="button" class="btn btn-link btn-sm p-0" id="notif-mark-all">Tout marquer lu</button>
      </div>
      ${items.map((n) => `
        <a href="${n.lien_action || '#'}" class="dropdown-item pharma-notif-item ${prioriteClass(n.priorite)}${n.lu == 1 ? '' : ' pharma-notif-item--unread'}" data-notif-id="${n.id}">
          <div class="pharma-notif-item__title">${n.titre}</div>
          <div class="pharma-notif-item__msg small text-muted">${n.message || ''}</div>
          <div class="pharma-notif-item__meta small">${new Date(n.created_at).toLocaleString('fr-FR')}</div>
        </a>`).join('')}`;

    panel.querySelector('#notif-mark-all')?.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      await PharmaAPI.post('notifications/lire-tout', {});
      refresh();
    });

    panel.querySelectorAll('[data-notif-id]').forEach((el) => {
      el.addEventListener('click', async () => {
        const id = el.dataset.notifId;
        try { await PharmaAPI.put(`notifications/${id}`, {}); } catch (_) {}
      });
    });
  }

  async function refresh() {
    try {
      const res = await PharmaAPI.get('notifications');
      renderDropdown(res.data || [], res.unread || 0);
    } catch (_) {}
  }

  function startPolling(intervalMs = 30000) {
    refresh();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refresh, intervalMs);
  }

  return { refresh, startPolling };
})();
