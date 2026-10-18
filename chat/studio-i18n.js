/* ChatHelp Studio — Laufzeit der Übersetzung (13 Sprachen, Deutsch = Quelle im HTML).
   window.CH_I18N kommt aus /chat/studio-i18n.php (zusammengeführtes Wörterbuch der aktiven Sprache, Fallback Englisch → Deutsch).
   Globale Hilfen (bewusst kurze, eindeutige Namen – „t“ ist in vielen Funktionen eine lokale Variable):
     t(key, vars) = _t(key, vars)   Text zum Schlüssel, {name}-Platzhalter aus vars
     _tx(text)                      deutschen Servertext übersetzen (src.* exakt, srx.* Muster), sonst unverändert
     _te(err)                       Fehlertext einer API-Antwort: Code (err.<code>) → übersetzte Nachricht → Nachricht
     I18N.apply(root)               data-i18n | data-i18n-html | data-i18n-t (direkte Textknoten, „k1|k2“) | data-i18n-attr („placeholder:k;title:k2“)
     I18N.num/eur/date/dt/time/wd   Zahlen, Euro, Datum, Wochentage über Intl in der Oberflächensprache
     I18N.switcher()/I18N.set(l)    Sprachauswahl im Kopf (Cookie ch_lang + localStorage, danach Neuladen) */
(function () {
  'use strict';
  var C = window.CH_I18N_CFG || {}, D = window.CH_I18N || { l: 'de', tag: 'de-DE', d: {}, src: {}, srx: [] };
  var LANG = D.l || C.lang || 'de', TAG = D.tag || 'de-DE', DICT = D.d || {}, SRC = D.src || {}, SRX = [];
  (D.srx || []).forEach(function (p) {
    try { var names = []; var re = '^' + String(p[0]).replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\\\{(\w+)\\\}/g, function (m, n) { names.push(n); return '([\\s\\S]+?)'; }) + '$';
      SRX.push({ re: new RegExp(re), names: names, to: p[1] }); } catch (e) { /* Muster ungültig */ }
  });
  function fill(s, v) { return v ? String(s).replace(/\{(\w+)\}/g, function (m, n) { return v[n] != null ? v[n] : m; }) : String(s); }
  function t(k, v) { var s = DICT[k]; if (s == null) s = k; return fill(s, v); }
  function tx(s) {
    if (s == null || LANG === 'de') return s;
    var x = String(s); if (Object.prototype.hasOwnProperty.call(SRC, x)) return SRC[x];
    var y = x.trim(); if (y !== x && Object.prototype.hasOwnProperty.call(SRC, y)) return SRC[y];
    for (var i = 0; i < SRX.length; i++) { var m = SRX[i].re.exec(x); if (m) { var v = {}; SRX[i].names.forEach(function (n, j) { v[n] = tx(m[j + 1]); }); return fill(SRX[i].to, v); } }
    return s;
  }
  /** API-Fehler (Objekt mit error/message bzw. Error mit .data) → Text in der Oberflächensprache */
  function te(e, fallback) {
    var j = e && e.data ? e.data : (e || {}), code = j && j.error, m = (j && j.message) || (e && e.message) || '';
    if (LANG === 'de') return m || fallback || (code ? String(code) : '');
    if (code && DICT['err.' + code] != null) return t('err.' + code, j);
    var x = tx(m); if (x && x !== m) return x;
    if (m && !/[äöüßÄÖÜ„“]|\b(der|die|das|und|nicht|bitte|ist|ein|eine|für|mit|kein|keine|noch|wird|oder)\b/.test(m)) return m;
    return fallback || t('err.generic') + (code ? ' (' + code + ')' : '');
  }
  function apply(root) {
    if (LANG === 'de' || !root || !root.querySelectorAll) return;
    var q = function (sel) { var a = Array.prototype.slice.call(root.querySelectorAll(sel)); if (root.matches && root.matches(sel)) a.unshift(root); return a; };
    q('[data-i18n]').forEach(function (el) { var k = el.getAttribute('data-i18n'); if (DICT[k] != null) el.textContent = DICT[k]; });
    q('[data-i18n-html]').forEach(function (el) { var k = el.getAttribute('data-i18n-html'); if (DICT[k] != null) el.innerHTML = DICT[k]; });
    q('[data-i18n-t]').forEach(function (el) {
      var ks = el.getAttribute('data-i18n-t').split('|'), i = 0;
      Array.prototype.forEach.call(el.childNodes, function (n) {
        if (n.nodeType !== 3 || !n.nodeValue.trim()) return; var k = ks[i++]; if (!k || DICT[k] == null) return;
        var lead = (n.nodeValue.match(/^\s*/) || [''])[0], tail = (n.nodeValue.match(/\s*$/) || [''])[0]; n.nodeValue = lead + DICT[k] + tail;
      });
    });
    q('[data-i18n-attr]').forEach(function (el) {
      el.getAttribute('data-i18n-attr').split(';').forEach(function (p) { var i = p.indexOf(':'); if (i < 1) return; var a = p.slice(0, i), k = p.slice(i + 1); if (DICT[k] != null) el.setAttribute(a, DICT[k]); });
    });
  }
  /* ---- Formate ---- */
  var nf = {};
  function num(n, o) { var k = JSON.stringify(o || {}); try { if (!nf[k]) nf[k] = new Intl.NumberFormat(TAG, o || {}); return nf[k].format(Number(n || 0)); } catch (e) { return String(n); } }
  function eur(v, d) { return num(v, { style: 'currency', currency: 'EUR', minimumFractionDigits: d == null ? 2 : d, maximumFractionDigits: d == null ? 2 : d }); }
  function toD(x) { return x instanceof Date ? x : new Date(x); }
  function date(x, o) { try { return toD(x).toLocaleDateString(TAG, o); } catch (e) { return String(x); } }
  function dt(x, o) { try { return toD(x).toLocaleString(TAG, o); } catch (e) { return String(x); } }
  function time(x) { try { return toD(x).toLocaleTimeString(TAG, { hour: '2-digit', minute: '2-digit' }); } catch (e) { return String(x); } }
  /** Wochentag 0 = Sonntag … 6 = Samstag; form 'long' | 'short' */
  function wd(i, form) { try { return new Date(Date.UTC(2023, 0, 1 + (+i || 0), 12)).toLocaleDateString(TAG, { weekday: form || 'long', timeZone: 'UTC' }); } catch (e) { return String(i); } }
  /** Vom Server deutsch formatierte Beträge („1.234,50 €“) und Daten („24.10.2026“) in die Oberflächensprache umformatieren */
  function fx(s) {
    if (s == null || LANG === 'de') return s;
    return String(s).replace(/(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?\s?€/g, function (m, a, b) { var n = parseFloat(a.replace(/\./g, '') + '.' + (b || '0')); return eur(n, b ? 2 : 0); })
      .replace(/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\b/g, function (m, d, mo, y) { return date(new Date(+y, +mo - 1, +d)); });
  }
  var DEC = (function () { try { return (1.5).toLocaleString(TAG).replace(/\d/g, '').charAt(0) || ','; } catch (e) { return ','; } })();
  /* ---- Sprachwahl ---- */
  var LANGS = C.langs || [['de', 'Deutsch', 'de-DE']];
  function cookie(k, v) {
    if (v === undefined) { try { var m = document.cookie.match(new RegExp('(?:^|; )' + k + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; } catch (e) { return ''; } }
    try { document.cookie = k + '=' + encodeURIComponent(v) + '; Path=/; Max-Age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); } catch (e) { /* egal */ }
  }
  function known(l) { return LANGS.some(function (x) { return x[0] === l; }); }
  function set(l, noReload) {
    if (!known(l)) return; cookie('ch_lang', l); try { localStorage.setItem('ch_lang', l); } catch (e) { /* privat */ }
    if (noReload || l === LANG) return;
    try { var u = new URL(location.href); u.searchParams.delete('lang'); u.searchParams.delete('v'); location.replace(u.pathname + (u.search || '') + u.hash); } catch (e) { location.reload(); }
  }
  function switcher() {
    var o = LANGS.map(function (x) { return '<option value="' + x[0] + '" lang="' + x[2] + '"' + (x[0] === LANG ? ' selected' : '') + '>' + x[0].toUpperCase() + ' · ' + x[1] + '</option>'; }).join('');
    return '<label class="lsx" title="' + t('i18n.lang') + '"><b>' + LANG.toUpperCase() + '</b><i aria-hidden="true">▾</i><select id="stLang" aria-label="' + t('i18n.lang') + '">' + o + '</select></label>';
  }
  function bindSwitcher(el) { var s = el && el.querySelector('select'); if (s) s.onchange = function () { set(s.value); }; }
  /** Nach dem Laden: localStorage-Wahl ohne Cookie wiederherstellen; Profilsprache übernehmen, wenn nichts ausdrücklich gewählt ist. */
  function autoFromProfile(langs) {
    if (C.source === 'query' || C.source === 'cookie') return false;
    var ls = ''; try { ls = localStorage.getItem('ch_lang') || ''; } catch (e) { /* privat */ }
    var want = known(ls) ? ls : (Array.isArray(langs) && known(langs[0]) ? langs[0] : '');
    if (!want) return false;
    try { if (sessionStorage.getItem('ch_lang_auto') === want) return false; sessionStorage.setItem('ch_lang_auto', want); } catch (e) { return false; }
    if (want === LANG) { cookie('ch_lang', want); return false; }
    set(want); return true;
  }
  var html = document.documentElement; html.lang = LANG === 'no' ? 'nb' : LANG; html.dir = 'ltr';
  window.I18N = { lang: LANG, tag: TAG, dec: DEC, t: t, tx: tx, te: te, apply: apply, num: num, eur: eur, fx: fx, date: date, dt: dt, time: time, wd: wd,
    set: set, switcher: switcher, bindSwitcher: bindSwitcher, autoFromProfile: autoFromProfile, langs: LANGS, has: function (k) { return DICT[k] != null; } };
  window._t = t; window._tx = tx; window._te = te; if (typeof window.t === 'undefined') window.t = t;
})();
