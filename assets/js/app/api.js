const PharmaAPI = (() => {
  const base = 'api/index.php?r=';

  async function request(route, options = {}) {
    const res = await fetch(base + route, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.error || 'Erreur serveur');
    }
    return data;
  }

  return {
    get: (route) => request(route),
    post: (route, body) => request(route, { method: 'POST', body: JSON.stringify(body) }),
    put: (route, body) => request(route, { method: 'PUT', body: JSON.stringify(body) }),
    del: (route) => request(route, { method: 'DELETE' }),
  };
})();

function formatMoney(amount) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount) + ' FCFA';
}

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleDateString('fr-FR');
}

// stockBadge défini dans ui.js
