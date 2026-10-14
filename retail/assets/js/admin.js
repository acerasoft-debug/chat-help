/* Verwaltung — küçük yardımcılar: menü, onay, arama kısayolu, canlı sayaç, saat. */
(function () {
  'use strict';
  var body = document.body;
  var burger = document.querySelector('[data-adm-menu]');
  if (burger) {
    burger.addEventListener('click', function () { body.classList.toggle('is-menu'); });
    document.addEventListener('click', function (e) {
      if (!body.classList.contains('is-menu')) return;
      if (e.target.closest('#adm-side') || e.target.closest('[data-adm-menu]')) return;
      body.classList.remove('is-menu');
    });
  }
  // Onaylı formlar
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.hasAttribute('data-confirm') && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });
  // "/" arama kutusuna odaklar
  var search = document.querySelector('.adm__search input');
  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && search && !/INPUT|TEXTAREA|SELECT/.test((e.target.tagName || '')) ) { e.preventDefault(); search.focus(); }
  });
  // Saat ve canlı ziyaretçi sayısı (dakikada bir)
  var clock = document.querySelector('[data-adm-clock]');
  var live = document.querySelector('[data-adm-live]');
  function tick() {
    if (clock) { var d = new Date(); clock.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  }
  tick(); setInterval(tick, 15000);
  if (live) {
    setInterval(function () {
      fetch(live.closest('a').getAttribute('href').split('?')[0] + '?f=live&json=1', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (j && typeof j.n === 'number') live.textContent = j.n; })
        .catch(function () {});
    }, 60000);
  }
  // Otomatik yenilenen sayfalar (canlı görünüm)
  var auto = document.querySelector('[data-adm-refresh]');
  if (auto) setTimeout(function () { window.location.reload(); }, parseInt(auto.getAttribute('data-adm-refresh'), 10) * 1000 || 60000);
  // Tablo filtresi (istemci tarafı, küçük listeler)
  document.querySelectorAll('[data-adm-filter]').forEach(function (inp) {
    var tbl = document.querySelector(inp.getAttribute('data-adm-filter'));
    if (!tbl) return;
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      tbl.querySelectorAll('tbody tr').forEach(function (tr) { tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1; });
    });
  });
})();
