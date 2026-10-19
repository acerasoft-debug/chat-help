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
  // Canlı akış (Gerade online): 10 sn'de bir tablo + harita + sayaç, sayfa
  // yenilenmeden. Sekme arka plandayken sorulmaz; geri gelince hemen sorulur.
  var feed = document.querySelector('[data-adm-livefeed]');
  if (feed) {
    var every = (parseInt(feed.getAttribute('data-adm-livefeed'), 10) || 10) * 1000;
    var rows = document.querySelector('[data-live-rows]');
    var map = document.querySelector('[data-live-map]');
    var side = document.querySelector('[data-live-side]');
    var at = document.querySelector('[data-live-at]');
    var busy = false;
    var mk = feed.getAttribute('data-mk') || '';
    // Yönetici bölgede bir şeye odaklanmış ya da metin seçmişse (IP kopyalamak
    // gibi) o bölge bu turda yazılmaz; odak ve seçim kaybolmasın.
    var hold = function (el) {
      var a = document.activeElement;
      var s = window.getSelection ? window.getSelection() : null;
      return !!(el && ((a && a !== document.body && el.contains(a)) || (s && !s.isCollapsed && s.anchorNode && el.contains(s.anchorNode))));
    };
    var pull = function () {
      if (busy || document.hidden) return;
      busy = true;
      var src = feed.getAttribute('data-src');
      fetch(src + (src.indexOf('?') < 0 ? '?' : '&') + 'mk=' + encodeURIComponent(mk), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) {
          if (!j) return;
          if (rows && typeof j.rows === 'string' && !hold(rows)) rows.innerHTML = j.rows;
          // Harita yalnız ülke sayıları değiştiyse gelir (j.mk yeni anahtar).
          if (map && typeof j.map === 'string' && !hold(map)) { map.innerHTML = j.map; mk = j.mk || ''; }
          if (side && typeof j.side === 'string' && !hold(side)) side.innerHTML = j.side;
          if (typeof j.n === 'number' && live) live.textContent = j.n;
          if (at && j.at) at.textContent = j.at;
        })
        .catch(function () {})
        .then(function () { busy = false; });
    };
    setInterval(pull, every);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) pull(); });
  } else if (live) {
    // Diğer sayfalar: yalnız üst çubuktaki sayaç, 30 sn'de bir.
    setInterval(function () {
      if (document.hidden) return;
      fetch(live.closest('a').getAttribute('href').split('?')[0] + '?f=live&json=1', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (j && typeof j.n === 'number') live.textContent = j.n; })
        .catch(function () {});
    }, 30000);
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
