const PharmaAPI = (() => {
  const base = 'api/index.php';

  /** Construit une URL : r=rapports&jours=30 (jamais r=rapports?jours=30) */
  function buildUrl(route) {
    const clean = String(route).replace(/^\//, '');
    const qPos = clean.indexOf('?');
    const path = qPos === -1 ? clean : clean.slice(0, qPos);
    const extra = qPos === -1 ? '' : clean.slice(qPos + 1);
    let url = `${base}?r=${path}`;
    if (extra) {
      url += `&${extra}`;
    }
    return url;
  }

  async function request(route, options = {}) {
    const res = await fetch(buildUrl(route), {
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
