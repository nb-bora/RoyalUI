const PharmaLayout = (() => {
  const menu = [
    { href: 'home.html', icon: 'ti-home', label: 'Tableau de bord', roles: ['admin', 'gestionnaire', 'vendeur'] },
    { href: 'caisse.html', icon: 'ti-shopping-cart-full', label: 'Caisse', roles: ['admin', 'gestionnaire', 'vendeur'] },
    { href: 'medicaments.html', icon: 'ti-package', label: 'Médicaments', roles: ['admin', 'gestionnaire'] },
    { href: 'categories.html', icon: 'ti-tag', label: 'Catégories', roles: ['admin', 'gestionnaire'] },
    { href: 'stock.html', icon: 'ti-archive', label: 'Stock', roles: ['admin', 'gestionnaire'] },
    { href: 'ventes.html', icon: 'ti-shopping-cart', label: 'Ventes', roles: ['admin', 'gestionnaire', 'vendeur'] },
    { href: 'achats.html', icon: 'ti-bag', label: 'Achats', roles: ['admin', 'gestionnaire'] },
    { href: 'fournisseurs.html', icon: 'ti-truck', label: 'Fournisseurs', roles: ['admin', 'gestionnaire'] },
    { href: 'clients.html', icon: 'ti-user', label: 'Clients', roles: ['admin', 'gestionnaire', 'vendeur'] },
    { href: 'rapports.html', icon: 'ti-bar-chart', label: 'Rapports', roles: ['admin', 'gestionnaire'] },
    { href: 'parametres.html', icon: 'ti-settings', label: 'Paramètres', roles: ['admin'] },
    { href: 'audit.html', icon: 'ti-list', label: 'Audit', roles: ['admin'] },
    { href: 'utilisateurs.html', icon: 'ti-id-badge', label: 'Utilisateurs', roles: ['admin'] },
  ];

  function renderSidebar(role) {
    const sidebar = document.querySelector('#sidebar .nav');
    if (!sidebar) return;
    const page = location.pathname.split('/').pop() || 'home.html';
    sidebar.innerHTML = menu
      .filter((m) => m.roles.includes(role))
      .map((m) => {
        const active = page === m.href ? ' active' : '';
        return `<li class="nav-item"><a class="nav-link${active}" href="${m.href}"><i class="${m.icon} menu-icon"></i><span class="menu-title">${m.label}</span></a></li>`;
      })
      .join('');
  }

  function normalizeNavbar(user) {
    document.getElementById('notificationDropdown')?.closest('.nav-item')?.remove();

    document.querySelectorAll('a[href="assets/#"]').forEach((a) => {
      a.setAttribute('href', '#');
    });

    const dropdown = document.querySelector('.navbar-nav-right .dropdown-menu');
    if (!dropdown) return;

    dropdown.querySelectorAll('.dropdown-item').forEach((item) => {
      if (/paramètres/i.test(item.textContent) && !item.hasAttribute('data-logout')) item.remove();
    });

    let nameEl = dropdown.querySelector('#user-name');
    if (!nameEl && user) {
      nameEl = document.createElement('span');
      nameEl.className = 'dropdown-item-text font-weight-bold text-primary px-4 py-2 border-bottom';
      nameEl.id = 'user-name';
      dropdown.insertBefore(nameEl, dropdown.firstChild);
    }
    if (nameEl && user) {
      const roleLabel = { admin: 'Administrateur', gestionnaire: 'Gestionnaire', vendeur: 'Vendeur' }[user.role] || user.role;
      nameEl.innerHTML = `${user.nom}<br><small class="text-muted font-weight-normal">${roleLabel}</small>`;
    }
  }

  async function init() {
    const user = await PharmaAuth.requireAuth();
    if (!user) return null;
    normalizeNavbar(user);
    PharmaAuth.bindLogout();
    renderSidebar(user.role);
    if (typeof PharmaNotifications !== 'undefined') {
      PharmaNotifications.startPolling();
    }
    const year = document.getElementById('current-year');
    if (year) year.textContent = new Date().getFullYear();
    return user;
  }

  return { init, renderSidebar };
})();
