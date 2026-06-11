const PharmaAuth = (() => {
  let currentUser = null;

  async function login(email, password) {
    const res = await PharmaAPI.post('auth/login', { email, password });
    currentUser = res.user;
    return res;
  }

  async function logout() {
    const ok = typeof PharmaSwal !== 'undefined'
      ? await PharmaSwal.confirmLogout()
      : window.confirm('Se déconnecter ?');
    if (!ok) return;
    try {
      await PharmaAPI.post('auth/logout', {});
    } catch (_) {}
    currentUser = null;
    window.location.href = 'index.html';
  }

  async function me() {
    const res = await PharmaAPI.get('auth/me');
    currentUser = res.user;
    return res.user;
  }

  async function requireAuth(allowedRoles = null) {
    try {
      const user = await me();
      if (allowedRoles && !allowedRoles.includes(user.role)) {
        showToast('Accès non autorisé', 'error');
        window.location.href = 'home.html';
        return null;
      }
      return user;
    } catch {
      window.location.href = 'index.html';
      return null;
    }
  }

  function bindLogout() {
    document.querySelectorAll('[data-logout]').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        logout();
      });
    });
  }

  function setUserName(selector = '#user-name') {
    const el = document.querySelector(selector);
    if (el && currentUser) el.textContent = currentUser.nom;
  }

  return { login, logout, me, requireAuth, bindLogout, setUserName, get user() { return currentUser; } };
})();
