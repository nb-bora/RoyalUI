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
    { href: 'utilisateurs.html', icon: 'ti-id-badge', label: 'Utilisateurs', roles: ['admin'] },
    { href: 'rapports.html', icon: 'ti-bar-chart', label: 'Rapports', roles: ['admin', 'gestionnaire'] },
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
      nameEl.textContent = user.nom;
      const roleLabel = { admin: 'Administrateur', gestionnaire: 'Gestionnaire', vendeur: 'Vendeur' }[user.role] || user.role;
      nameEl.innerHTML = `${user.nom}<br><small class="text-muted font-weight-normal">${roleLabel}</small>`;
    }
  }

  async function loadAlertBadge() {
    try {
      const res = await PharmaAPI.get('alertes');
      const count = res.total || 0;
      let badge = document.getElementById('alert-count');
      if (!badge) {
        const nav = document.querySelector('.navbar-nav-right');
        if (!nav) return;
        const li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = `<a class="nav-link pharma-nav-alert" href="home.html#alertes" title="Alertes stock et péremption"><i class="ti-bell"></i><span class="badge badge-danger" id="alert-count">${count}</span></a>`;
        nav.insertBefore(li, nav.firstChild);
      } else {
        badge.textContent = count;
      }
    } catch (_) {}
  }

  async function init() {
    const user = await PharmaAuth.requireAuth();
    if (!user) return null;
    normalizeNavbar(user);
    PharmaAuth.bindLogout();
    renderSidebar(user.role);
    loadAlertBadge();
    const year = document.getElementById('current-year');
    if (year) year.textContent = new Date().getFullYear();
    return user;
  }

  return { init, renderSidebar };
})();
