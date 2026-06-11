function initPageTabs() {
  const tabsRoot = document.getElementById('page-tabs');
  if (!tabsRoot) return;

  function activatePageTab(tabId) {
    document.querySelectorAll('.tab-link').forEach((link) => {
      link.classList.toggle('active', link.getAttribute('data-tab') === tabId);
    });
    document.querySelectorAll('.tab-pane').forEach((pane) => {
      const active = pane.id === tabId;
      pane.classList.toggle('active', active);
      pane.classList.toggle('show', active);
      if (!active && !pane.classList.contains('fade')) pane.classList.add('fade');
      if (active) pane.classList.remove('fade');
    });
  }

  function restoreTabFromHash() {
    const defaultTab = tabsRoot.dataset.defaultTab || 'form';
    const hash = window.location.hash.replace('#', '');
    const validTabs = [...document.querySelectorAll('.tab-link')].map((l) => l.getAttribute('data-tab'));
    const tabId = validTabs.includes(hash) ? hash : defaultTab;
    activatePageTab(tabId);
  }

  window.addEventListener('hashchange', restoreTabFromHash);
  document.querySelectorAll('.tab-link').forEach((link) => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.hash = link.getAttribute('data-tab');
    });
  });
  restoreTabFromHash();
}

function initDataTable(selector = 'table.data-table') {
  if (typeof jQuery !== 'undefined' && $.fn.DataTable) {
    $(selector).each(function () {
      if (!$.fn.dataTable.isDataTable(this)) {
        $(this).addClass('pharma-table');
        $(this).DataTable({
          responsive: true,
          language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' },
          dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
        });
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#page-tabs').forEach((el) => el.classList.add('pharma-tabs'));
});
