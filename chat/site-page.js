/* ChatHelp — Websites aus dem Baukasten: kleine Helfer ohne Abhängigkeiten (CSP: nur 'self').
   Chat-Knöpfe (data-chat) öffnen den eingebetteten ChatHelp-Agenten, sonst gilt der normale Link (#contact).
   Aufklappmenüs (Sprache, Menü) schließen bei Klick außerhalb, Esc oder Auswahl. Formular: kein Doppel-Absenden. */
(function () {
  'use strict';
  function widgetReady() { return !!(window.ChatHelpWidget && typeof window.ChatHelpWidget.open === 'function' && document.querySelector('[data-chathelp-widget]')); }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null; if (!t) return;
    var c = t.closest('[data-chat]');
    if (c && widgetReady()) { e.preventDefault(); try { window.ChatHelpWidget.open(); } catch (err) { location.hash = 'contact'; } }
    var item = t.closest('.dd .ddm a'); if (item) { var d = item.closest('details'); if (d) d.removeAttribute('open'); }
    Array.prototype.forEach.call(document.querySelectorAll('details.dd[open]'), function (d) { if (!d.contains(t)) d.removeAttribute('open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    Array.prototype.forEach.call(document.querySelectorAll('details.dd[open]'), function (d) { d.removeAttribute('open'); var s = d.querySelector('summary'); if (s) s.focus(); });
  });
  var f = document.getElementById('cform');
  if (f) f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); if (b) setTimeout(function () { b.disabled = true; }, 0); });
})();
