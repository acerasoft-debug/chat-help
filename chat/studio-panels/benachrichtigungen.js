/* ChatHelp Studio — Panel „Benachrichtigungen“ (push-lib + app-bridge.js):
   Status dieses Geräts (aktivieren / blockiert / iPhone-Anleitung), angemeldete Geräte mit Entfernen und Test-Ergebnis je Gerät,
   Ereignisse nach Gruppen (Aufträge, Kommunikation, Konto), Ruhezeiten, E-Mail-Ersatz und das Postfach (neueste zuerst, ungelesen fett,
   alle als gelesen, Klick öffnet das Ziel). Postfach-Abfrage alle 30 s, nur solange das Panel sichtbar ist.
   Nutzt die Studio-Globals api, msg, esc, go, KEY. */
(function () {
  'use strict';
  var PANEL = 'panel-benachrichtigungen';
  var BRIDGE = (window.CH_BRIDGE_SRC || '/chat/app-bridge.js?v=2026-10-17.1');   // studio.php setzt den versionierten Pfad (Cache)
  var CFG = null, ITEMS = [], MAXID = 0, MORE = false, FILTER = 'all', UNREAD = 0, HASH = '', STATE = '', TEST = {};
  var timer = null, saveT = null, bridgeP = null, bound = false, polling = false;
  var g = function (id) { return document.getElementById(id); };
  var h = function (v) { return esc(v == null ? '' : String(v)); };
  var visible = function () { var el = g(PANEL); return !!el && !el.closest('.hidden'); };
  var B = function () { return window.ChatHelpApp || null; };
  var key = function () { try { return KEY; } catch (e) { return ''; } };

  /* ---------- Symbole ---------- */
  var SV = function (p, s) { return '<svg width="' + (s || 18) + '" height="' + (s || 18) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var IC = {
    order: '<path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
    ride: '<path d="M5 15.5v-4l1.9-4.3A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.8 1.2L19 11.5v4"/><rect x="3.5" y="11.5" width="17" height="5.5" rx="1.6"/><path d="M7 17v2M17 17v2M7.5 14.2h.01M16.5 14.2h.01"/>',
    booking: '<rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    hotel: '<path d="M3 18.5V8M3 14.5h18v4M21 14.5V12a2.5 2.5 0 0 0-2.5-2.5H11v5"/><circle cx="7" cy="11.5" r="1.7"/>',
    request: '<path d="M4 17.5h16M6 17.5a6 6 0 0 1 12 0M12 9.5V7.5M10.3 7.5h3.4"/>',
    phone: '<path d="M6.6 4h2.8l1.5 4-2 1.3a10.5 10.5 0 0 0 5.8 5.8l1.3-2 4 1.5v2.8a1.6 1.6 0 0 1-1.7 1.6A15.8 15.8 0 0 1 5 5.7 1.6 1.6 0 0 1 6.6 4z"/>',
    lead: '<circle cx="10" cy="8" r="3.2"/><path d="M4 19.5a6 6 0 0 1 12 0M18.5 8v6M15.5 11h6"/>',
    coin: '<circle cx="12" cy="12" r="8.2"/><path d="M14.6 9.3a3 3 0 0 0-2.6-1.3c-1.5 0-2.7.9-2.7 2s1 1.7 2.7 2 2.7.9 2.7 2-1.2 2-2.7 2a3 3 0 0 1-2.6-1.3M12 6.5V8M12 16v1.5"/>',
    info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.2M12 7.8h.01"/>',
    bell: '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.6 2H4.4z"/><path d="M10 21a2.2 2.2 0 0 0 4 0"/>',
    check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
    block: '<circle cx="12" cy="12" r="8.5"/><path d="M6 6l12 12"/>',
    share: '<path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M6 11H5.5A1.5 1.5 0 0 0 4 12.5v6A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5v-6a1.5 1.5 0 0 0-1.5-1.5H18"/>',
    mobile: '<rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/><path d="M11 18h2"/>',
    desktop: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16v4"/>',
    app: '<rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/><path d="M14.3 9.2a2.7 2.7 0 1 0 .2 3.2"/><circle cx="12" cy="10.8" r=".9"/>'
  };
  var EV_IC = { order_new: 'order', order_cancel: 'order', booking_new: 'booking', booking_cancel: 'booking', ride_new: 'ride', ride_cancel: 'ride',
    hotel_booking: 'hotel', hotel_request: 'request', callback_new: 'phone', lead_new: 'lead', call_done: 'phone', tokens_low: 'coin', system: 'info' };
  var GROUP_COL = { auftraege: ['var(--goldl)', 'var(--gold)', 'gold'], kommunikation: ['var(--indigol)', 'var(--indigo)', 'indigo'], konto: ['var(--teall)', 'var(--teal)', 'teal'] };
  function colors(ev, group) { return /_cancel$/.test(ev || '') ? ['var(--rosel)', 'var(--rose)', 'rose'] : (GROUP_COL[group] || ['var(--sur2)', 'var(--ink2)', '']); }
  function iconBox(ev, group, size) {
    var c = colors(ev, group), s = size || 38;
    return '<div style="width:' + s + 'px;height:' + s + 'px;border-radius:' + Math.round(s / 3) + 'px;display:grid;place-items:center;flex:0 0 auto;background:' + c[0] + ';color:' + c[1] + '">' + SV(IC[EV_IC[ev] || 'info']) + '</div>';
  }

  /* ---------- Zeit ---------- */
  function ago(iso) {
    if (!iso) return ''; var t = new Date(iso).getTime(); if (!isFinite(t)) return '';
    var s = Math.round((Date.now() - t) / 1000);
    if (s < 45) return 'gerade eben';
    if (s < 3600) return 'vor ' + Math.max(1, Math.round(s / 60)) + ' Min.';
    if (s < 6 * 3600) return 'vor ' + Math.round(s / 3600) + ' Std.';
    var d = new Date(t), now = new Date(), hm = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === now.toDateString()) return 'heute ' + hm;
    var y = new Date(now.getTime() - 86400000); if (d.toDateString() === y.toDateString()) return 'gestern ' + hm;
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) + '. ' + hm;
  }
  function dfull(iso) { try { return new Date(iso).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return ''; } }
  function iosOld() { var m = /OS (\d+)[_.](\d+)/.exec(navigator.userAgent || ''); return !!(m && (+m[1] < 16 || (+m[1] === 16 && +m[2] < 4))); }
  function uaLabel() {
    var ua = navigator.userAgent || '', b = 'Browser', o = '';
    if (/Edg\//.test(ua)) b = 'Edge'; else if (/SamsungBrowser/.test(ua)) b = 'Samsung Internet'; else if (/OPR\//.test(ua)) b = 'Opera';
    else if (/Firefox\/|FxiOS/.test(ua)) b = 'Firefox'; else if (/CriOS|Chrome\//.test(ua)) b = 'Chrome'; else if (/Safari\//.test(ua)) b = 'Safari';
    if (/iPhone/.test(ua)) o = 'iPhone'; else if (/iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) o = 'iPad';
    else if (/Android/.test(ua)) o = 'Android'; else if (/Windows/.test(ua)) o = 'Windows'; else if (/Mac OS X|Macintosh/.test(ua)) o = 'Mac'; else if (/CrOS/.test(ua)) o = 'ChromeOS'; else if (/Linux/.test(ua)) o = 'Linux';
    var A = B(); if (A && A.isNative) return 'ChatHelp-App · ' + (A.platform === 'ios' ? 'iPhone' : 'Android');
    return o ? b + ' · ' + o : b;
  }

  /* ---------- App-Brücke laden (falls Studio sie nicht schon eingebunden hat) ---------- */
  function ensureBridge() {
    if (B()) return Promise.resolve(B());
    if (bridgeP) return bridgeP;
    bridgeP = new Promise(function (resolve) {
      var s = document.createElement('script'); s.src = BRIDGE; s.async = true;
      s.onload = function () { resolve(B()); }; s.onerror = function () { bridgeP = null; resolve(null); };
      document.head.appendChild(s);
    });
    return bridgeP;
  }
  function initBridge() {
    var A = B(); if (!A) return;
    try { A.init({ api: api, getKey: key }); } catch (e) {}
    window.addEventListener('chathelp:push', function () { pollInbox(); });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible' && visible()) { refreshStatus(); pollInbox(); } });
  }

  /* ---------- Ungelesen ---------- */
  function ensureTabBadge() {
    if (document.querySelector('[data-ch-unread]')) return;
    var t = document.querySelector('.tab[data-v="benachrichtigungen"]'); if (!t) return;
    var s = document.createElement('span'); s.setAttribute('data-ch-unread', '');
    s.style.cssText = 'display:none;margin-left:6px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--gold);color:#fff;font:700 11px/18px var(--sans);text-align:center;vertical-align:1px';
    t.appendChild(s);
  }
  function setUnread(n) {
    UNREAD = Math.max(0, parseInt(n, 10) || 0);
    var t = g('bnUnreadTag');
    if (t) { t.textContent = UNREAD > 0 ? UNREAD + ' ungelesen' : 'Alles gelesen'; t.className = UNREAD > 0 ? 'tag gold' : 'tag ok'; }
    var ra = g('bnReadAll'); if (ra) ra.disabled = UNREAD === 0;
    ensureTabBadge();
    var A = B();
    if (A && A.setUnread) A.setUnread(UNREAD);
    else document.querySelectorAll('[data-ch-unread]').forEach(function (el) { el.textContent = UNREAD > 99 ? '99+' : String(UNREAD); el.style.display = UNREAD > 0 ? '' : 'none'; });
  }

  /* ---------- Status dieses Geräts ---------- */
  function show(id, on) { var el = g(id); if (el) el.classList.toggle('hidden', !on); }
  function paintStatus(o) {
    var ic = g('bnStIcon'), c = { ok: ['var(--okl)', 'var(--ok)'], gold: ['var(--goldl)', 'var(--gold)'], rose: ['var(--rosel)', 'var(--rose)'], mute: ['var(--sur2)', 'var(--mute)'] }[o.tone || 'mute'];
    ic.style.background = c[0]; ic.style.color = c[1]; ic.innerHTML = SV(IC[o.icon || 'bell'], 28);
    var tag = g('bnStTag'); tag.className = 'tag' + (o.tag ? ' ' + o.tag : ''); tag.textContent = o.tagText || '';
    g('bnStDev').textContent = o.dev || '';
    g('bnStTitle').textContent = o.title || '';
    g('bnStText').innerHTML = o.text || '';
    var st = g('bnStSteps'); st.innerHTML = (o.steps || []).map(function (s) { return '<li>' + s + '</li>'; }).join(''); st.classList.toggle('hidden', !(o.steps && o.steps.length));
    var nt = g('bnStNote'); nt.textContent = o.note || ''; nt.classList.toggle('hidden', !o.note);
    var en = g('bnEnable'); show('bnEnable', !!o.enable); if (o.enable) en.textContent = o.enable;
    show('bnTest', !!o.test); show('bnDisable', !!o.disable); show('bnRecheck', !!o.recheck);
    var A = B(); show('bnInstall', !!(A && A.canInstall));
    g('bnStatus').style.background = o.tone === 'ok' ? 'linear-gradient(135deg,#ffffff 0%,#f3fbf6 100%)' : (o.tone === 'gold' ? 'linear-gradient(135deg,#ffffff 0%,#fdf8ec 100%)' : '');
  }
  function refreshStatus() {
    var A = B();
    if (!A) {
      paintStatus({ tone: 'rose', icon: 'block', tag: 'rose', tagText: 'Nicht geladen', title: 'Benachrichtigungen konnten nicht geladen werden', text: 'Bitte die Seite neu laden. Wichtige Ereignisse bekommst du weiterhin per E-Mail.', recheck: true });
      return Promise.resolve();
    }
    return Promise.all([A.pushState(), A.deviceHash()]).then(function (r) {
      STATE = r[0]; HASH = r[1] || '';
      var mine = CFG && HASH ? CFG.devices.filter(function (d) { return d.h === HASH; })[0] : null;
      var dev = mine ? mine.label : uaLabel(), mailHint = CFG && CFG.email_fallback ? ' Bis dahin bekommst du wichtige Ereignisse per E-Mail.' : '';
      if (STATE === 'subscribed' && mine) {
        paintStatus({ tone: 'ok', icon: 'check', tag: 'ok', tagText: 'Aktiv', dev: dev, title: 'Benachrichtigungen sind auf diesem Gerät aktiv',
          text: 'Neue Aufträge erreichen dich hier sofort – auch wenn das Studio geschlossen ist.' + (mine.last_ok ? ' Zuletzt zugestellt ' + h(ago(mine.last_ok)) + '.' : ' Mit „Test senden“ prüfst du in Sekunden alle Geräte.'),
          test: true, disable: true });
      } else if (STATE === 'subscribed' || STATE === 'granted') {
        paintStatus({ tone: 'gold', icon: 'bell', tag: 'gold', tagText: 'Fast fertig', dev: dev, title: 'Noch dieses Gerät verbinden',
          text: 'Die Erlaubnis ist erteilt, aber dieses Gerät ist (nicht mehr) in deiner Geräteliste – z. B. weil es entfernt wurde oder abgelaufen ist. Ein Tipp verbindet es wieder.', enable: 'Gerät verbinden' });
      } else if (STATE === 'default') {
        var tip = A.isNative ? '' : A.installHint();
        paintStatus({ tone: 'gold', icon: 'bell', tag: '', tagText: 'Aus', dev: dev, title: 'Benachrichtigungen aktivieren',
          text: 'Tippe auf den Knopf und bestätige mit „Zulassen“ – dann meldet sich ChatHelp bei jeder neuen Bestellung, Fahrt oder jedem Termin sofort, mit Ton.' + (tip ? '<br><span class="mute small">' + h(tip) + '</span>' : ''),
          enable: 'Benachrichtigungen aktivieren' });
      } else if (STATE === 'denied') {
        paintStatus({ tone: 'rose', icon: 'block', tag: 'rose', tagText: 'Blockiert', dev: dev, title: 'Benachrichtigungen sind blockiert',
          text: h(A.deniedHint()) + mailHint, recheck: true });
      } else if (A.isIOS && !A.isStandalone && !A.isNative && !iosOld()) {
        paintStatus({ tone: 'gold', icon: 'share', tag: 'gold', tagText: 'iPhone & iPad', dev: dev, title: 'Zuerst zum Home-Bildschirm hinzufügen',
          text: 'Apple erlaubt Benachrichtigungen nur für installierte Web-Apps (ab iOS 16.4). So geht’s – dauert 20 Sekunden:',
          steps: ['In Safari unten auf <b>Teilen</b> tippen (Quadrat mit Pfeil nach oben).', '<b>„Zum Home-Bildschirm“</b> wählen und <b>„Hinzufügen“</b> tippen.',
            'ChatHelp über das <b>neue Symbol</b> öffnen, hier „Benachrichtigungen“ wählen und <b>„Benachrichtigungen aktivieren“</b> tippen.'], note: mailHint.trim() });
      } else {
        paintStatus({ tone: 'mute', icon: 'block', tag: '', tagText: 'Nicht unterstützt', dev: dev, title: 'Dieser Browser kann keine Push-Benachrichtigungen',
          text: h(A.installHint() && A.isIOS ? A.installHint() : 'Nutze Chrome, Edge, Firefox oder Safari – oder die ChatHelp-App.') + mailHint });
      }
      renderDevices();
    }).catch(function () {});
  }

  /* ---------- Geräte ---------- */
  function devIcon(d) {
    var k = d.type === 'fcm' ? 'app' : (/(iPhone|iPad|Android)/.test(d.label || '') ? 'mobile' : 'desktop');
    var mine = HASH && d.h === HASH;
    return '<div style="width:38px;height:38px;border-radius:13px;display:grid;place-items:center;flex:0 0 auto;background:' + (mine ? 'var(--okl)' : 'var(--sur2)') + ';color:' + (mine ? 'var(--ok)' : 'var(--ink2)') + '">' + SV(IC[k]) + '</div>';
  }
  function renderDevices() {
    var box = g('bnDevices'); if (!box || !CFG) return;
    var list = CFG.devices || [];
    g('bnDevCount').textContent = list.length ? list.length + (list.length === 1 ? ' Gerät' : ' Geräte') : '';
    if (!list.length) {
      box.innerHTML = '<div class="call" style="text-align:center;padding:20px 14px"><div class="mute small">Noch kein Gerät angemeldet. Aktiviere oben die Benachrichtigungen auf diesem Gerät – und am besten auch auf deinem Handy.</div></div>';
    } else {
      box.innerHTML = list.map(function (d) {
        var mine = HASH && d.h === HASH, t = TEST[d.id], bits = [];
        bits.push(d.type === 'fcm' ? 'ChatHelp-App' : 'Browser-Push');
        if (d.created) bits.push('seit ' + new Date(d.created).toLocaleDateString('de-DE'));
        bits.push(d.last_ok ? 'zuletzt zugestellt ' + ago(d.last_ok) : 'noch nichts zugestellt');
        var res = '';
        if (t) res = t.ok ? '<div class="small" style="color:var(--ok);margin-top:4px">✓ Test zugestellt' + (t.status ? ' (' + t.status + ')' : '') + '</div>'
          : '<div class="small" style="color:var(--rose);margin-top:4px">✗ ' + h(t.removed ? 'Nicht mehr erreichbar – Gerät wurde entfernt' : (t.error || 'nicht zugestellt')) + '</div>';
        else if (d.last_error) res = '<div class="small" style="color:var(--rose);margin-top:4px">Letzter Fehler: ' + h(d.last_error) + (d.fails ? ' · ' + d.fails + '× in Folge' : '') + '</div>';
        return '<div class="call" style="display:flex;gap:12px;align-items:center' + (mine ? ';border-color:#bfe8cf' : '') + '">' + devIcon(d) +
          '<div style="flex:1;min-width:0"><div class="row" style="gap:6px"><b style="font-size:14.5px">' + h(d.label) + '</b>' + (mine ? '<span class="tag ok" style="font-size:10px;padding:3px 8px">Dieses Gerät</span>' : '') + '</div>' +
          '<div class="mute small">' + h(bits.join(' · ')) + '</div>' + res + '</div>' +
          '<button class="btn s ghost" type="button" data-rm="' + d.id + '" title="Gerät entfernen" style="color:var(--rose)">Entfernen</button></div>';
      }).join('');
      box.querySelectorAll('[data-rm]').forEach(function (b) { b.onclick = function () { removeDevice(+b.getAttribute('data-rm')); }; });
    }
    var extra = [];
    if (CFG.drivers) extra.push(CFG.drivers + (CFG.drivers === 1 ? ' Fahrer-Handy' : ' Fahrer-Handys'));
    if (CFG.boards) extra.push(CFG.boards + (CFG.boards === 1 ? ' Board' : ' Boards'));
    var notes = [];
    if (extra.length) notes.push('Außerdem angemeldet: ' + h(extra.join(' · ')) + ' – diese bekommen nur ihre eigenen Aufträge.');
    if (!CFG.fcm_ready && list.some(function (d) { return d.type === 'fcm'; })) notes.push('Die ChatHelp-App wird gerade für Benachrichtigungen freigeschaltet – bis dahin kommen Meldungen über den Browser und per E-Mail.');
    msg(g('bnDevMsg'), notes.join('<br>'), 'info');
  }
  function removeDevice(id) {
    var d = (CFG.devices || []).filter(function (x) { return x.id === id; })[0]; if (!d) return;
    var mine = HASH && d.h === HASH;
    if (!confirm('„' + d.label + '“ entfernen? Dieses Gerät bekommt dann keine Benachrichtigungen mehr.')) return;
    var A = B();
    var p = mine && A ? A.disablePush().then(function () { return api('/push/unsubscribe', { method: 'POST', body: JSON.stringify({ id: id }) }); })
      : api('/push/unsubscribe', { method: 'POST', body: JSON.stringify({ id: id }) });
    p.then(function (j) { if (j && j.devices) CFG.devices = j.devices; delete TEST[id]; msg(g('bnDevMsg'), 'Gerät entfernt.', 'ok'); return loadCfg(); })
      .then(refreshStatus).catch(function (e) { msg(g('bnDevMsg'), h(e.message), 'err'); });
  }
  function sendTest(btn) {
    if (btn) btn.disabled = true;
    msg(g('bnStMsg'), 'Test wird gesendet …', 'info');
    api('/push/test', { method: 'POST', body: '{}' }).then(function (j) {
      TEST = {}; (j.results || []).forEach(function (r) { TEST[r.id] = r; });
      if (j.devices) CFG.devices = j.devices;
      msg(g('bnStMsg'), h(j.message || '') + (j.total ? ' Die Meldung liegt auch im Postfach.' : ''), j.total ? (j.ok ? 'ok' : 'err') : 'info');
      renderDevices(); pollInbox(); if (typeof j.unread === 'number') setUnread(j.unread);
    }).catch(function (e) { msg(g('bnStMsg'), h(e.message), 'err'); }).then(function () { if (btn) btn.disabled = false; });
  }

  /* ---------- Ereignisse ---------- */
  function renderEvents() {
    var box = g('bnEvents'); if (!box || !CFG) return;
    var groups = CFG.groups || {}, html = '';
    Object.keys(groups).forEach(function (gk, i) {
      var evs = (CFG.events || []).filter(function (e) { return e.group === gk; }); if (!evs.length) return;
      html += '<div class="sec"' + (i === 0 ? ' style="margin-top:12px"' : '') + '><h3 style="font-size:15px">' + h(groups[gk]) + '</h3>';
      evs.forEach(function (e) {
        var on = CFG.prefs ? CFG.prefs[e.key] !== false : e.on;
        html += '<div class="sw" data-row="' + h(e.key) + '" style="cursor:pointer;align-items:flex-start"><input type="checkbox" data-ev="' + h(e.key) + '"' + (on ? ' checked' : '') + ' aria-label="' + h(e.label) + '" style="margin-top:2px">' +
          iconBox(e.key, e.group, 30) +
          '<div style="flex:1;min-width:0"><div class="row" style="gap:6px"><b>' + h(e.label) + '</b>' +
          (e.urgent ? '<span class="tag gold" style="font-size:10px;padding:3px 7px">Dringend</span>' : '') +
          (e.critical ? '<span class="tag" style="font-size:10px;padding:3px 7px" title="Bei aktivem E-Mail-Ersatz zusätzlich per E-Mail">+ E-Mail</span>' : '') +
          '</div><div class="mute small">' + h(e.hint || '') + '</div></div></div>';
      });
      html += '</div>';
    });
    box.innerHTML = html;
    box.querySelectorAll('[data-row]').forEach(function (row) {
      row.addEventListener('click', function (ev) { if (ev.target.tagName === 'INPUT') return; var cb = row.querySelector('input'); cb.checked = !cb.checked; scheduleSave(); });
    });
    box.querySelectorAll('input[data-ev]').forEach(function (cb) { cb.addEventListener('change', scheduleSave); });
  }

  /* ---------- Ruhezeiten + E-Mail ---------- */
  function fillTimes(sel) {
    if (sel.options.length) return;
    var o = '<option value="">Aus</option>';
    for (var m = 0; m < 1440; m += 30) { var t = ('0' + Math.floor(m / 60)).slice(-2) + ':' + ('0' + (m % 60)).slice(-2); o += '<option value="' + t + '">' + t + ' Uhr</option>'; }
    sel.innerHTML = o;
  }
  function setSel(sel, v) {
    if (v && !Array.prototype.some.call(sel.options, function (o) { return o.value === v; })) { var op = document.createElement('option'); op.value = v; op.textContent = v + ' Uhr'; sel.appendChild(op); }
    sel.value = v || '';
  }
  function renderQuiet() {
    if (!CFG) return;
    var q = CFG.quiet || {}, f = g('bnQFrom'), t = g('bnQTo'), tz = g('bnQTz');
    fillTimes(f); fillTimes(t); setSel(f, q.from || ''); setSel(t, q.to || '');
    if (q.tz && !Array.prototype.some.call(tz.options, function (o) { return o.value === q.tz; })) { var op = document.createElement('option'); op.value = q.tz; op.textContent = q.tz; tz.appendChild(op); }
    tz.value = q.tz || 'Europe/Berlin';
    g('bnQUrgent').checked = q.urgent !== false;
    var active = !!(q.from && q.to);
    g('bnQUrgentRow').style.opacity = active ? '1' : '.55';
    var tzName = tz.options[tz.selectedIndex] ? tz.options[tz.selectedIndex].textContent : q.tz;
    msg(g('bnQNow'), active ? (CFG.in_quiet ? '🌙 Gerade ist Ruhezeit (' + h(q.from) + '–' + h(q.to) + ' Uhr, ' + h(tzName) + ') – ' + (q.urgent !== false ? 'nur dringende Aufträge klingeln.' : 'alles wartet im Postfach.')
      : 'Ruhezeit täglich ' + h(q.from) + '–' + h(q.to) + ' Uhr (' + h(tzName) + ').') : '', 'info');
    g('bnMail').checked = !!CFG.email_fallback;
    g('bnMailTo').textContent = CFG.email || 'deine Konto-E-Mail';
  }
  function scheduleSave() {
    clearTimeout(saveT); g('bnSaved').textContent = 'Speichert …'; g('bnSaved').style.color = '';
    saveT = setTimeout(save, 450);
  }
  function save() {
    var prefs = {};
    document.querySelectorAll('#bnEvents input[data-ev]').forEach(function (i) { prefs[i.getAttribute('data-ev')] = i.checked; });
    var body = { prefs: prefs, email_fallback: g('bnMail').checked, quiet: { from: g('bnQFrom').value, to: g('bnQTo').value, tz: g('bnQTz').value, urgent: g('bnQUrgent').checked } };
    api('/push/prefs', { method: 'POST', body: JSON.stringify(body) }).then(function (j) {
      CFG.prefs = j.prefs; CFG.email_fallback = j.email_fallback; CFG.quiet = j.quiet; CFG.in_quiet = j.in_quiet;
      renderQuiet();
      var s = g('bnSaved'); s.textContent = 'Gespeichert ✓'; s.style.color = 'var(--ok)';
      setTimeout(function () { if (s.textContent === 'Gespeichert ✓') s.textContent = ''; }, 2500);
    }).catch(function (e) { var s = g('bnSaved'); s.textContent = 'Nicht gespeichert: ' + e.message; s.style.color = 'var(--rose)'; });
  }

  /* ---------- Postfach ---------- */
  function passes(it) {
    if (FILTER === 'all') return true;
    if (FILTER === 'unread') return !it.read;
    return it.group === FILTER;
  }
  function panelTarget(url) { var m = /^\/chat\/studio\.php(?:\?[^#]*)?#([a-z0-9_-]+)$/i.exec(url || ''); return m ? m[1] : null; }
  function itemHtml(it) {
    var c = colors(it.event, it.group), tgt = panelTarget(it.url);
    return '<div class="call" data-id="' + it.id + '" role="button" tabindex="0" style="display:flex;gap:12px;align-items:flex-start;cursor:pointer;transition:.15s' + (it.read ? '' : ';background:#fffcf4;border-color:#eadbb0') + '">' +
      iconBox(it.event, it.group) +
      '<div style="flex:1;min-width:0">' +
      '<div style="font-weight:' + (it.read ? '500' : '700') + ';color:' + (it.read ? 'var(--ink2)' : 'var(--ink)') + ';overflow-wrap:break-word;line-height:1.4">' + h(it.title) + '</div>' +
      (it.body ? '<div class="small" style="color:var(--ink2);margin-top:3px;white-space:pre-line;overflow-wrap:break-word">' + h(it.body) + '</div>' : '') +
      '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap"><div class="row" style="gap:8px"><span class="tag ' + c[2] + '" style="font-size:10px;padding:3px 8px">' + h(it.label) + '</span>' +
      (it.url && tgt !== 'benachrichtigungen' ? '<span class="small" style="color:var(--gold);font-weight:600">Öffnen →</span>' : '') + '</div>' +
      '<span class="mute small" style="white-space:nowrap" title="' + h(dfull(it.created)) + '">' + h(ago(it.created)) + '</span></div></div>' +
      (it.read ? '' : '<span title="ungelesen" style="width:9px;height:9px;border-radius:50%;background:var(--gold);flex:0 0 auto;margin-top:7px;box-shadow:0 0 0 3px var(--goldl)"></span>') +
      '</div>';
  }
  function renderInbox() {
    var box = g('bnInbox'); if (!box) return;
    var list = ITEMS.filter(passes);
    if (!list.length) {
      var empty = ITEMS.length ? 'Keine Meldungen in dieser Ansicht.' : 'Noch keine Meldungen. Sobald dein Agent eine Bestellung, Fahrt, einen Termin oder Rückrufwunsch annimmt, steht es hier – und auf deinen Geräten.';
      box.innerHTML = '<div style="text-align:center;padding:30px 12px"><div style="width:52px;height:52px;margin:0 auto 10px;border-radius:18px;display:grid;place-items:center;background:var(--sur2);color:var(--mute)">' + SV(IC.bell, 24) + '</div><div class="mute small" style="max-width:40ch;margin:0 auto">' + h(empty) + '</div></div>';
    } else box.innerHTML = list.map(itemHtml).join('');
    show('bnMore', MORE && FILTER !== 'unread');
    box.querySelectorAll('[data-id]').forEach(function (el) {
      var open = function () { openItem(+el.getAttribute('data-id')); };
      el.addEventListener('click', open);
      el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
      el.addEventListener('mouseenter', function () { el.style.boxShadow = 'var(--sh1)'; });
      el.addEventListener('mouseleave', function () { el.style.boxShadow = ''; });
    });
  }
  function openItem(id) {
    var it = ITEMS.filter(function (x) { return x.id === id; })[0]; if (!it) return;
    if (!it.read) {
      it.read = true; setUnread(UNREAD - 1); renderInbox();
      api('/push/read', { method: 'POST', body: JSON.stringify({ ids: [id] }) }).then(function (j) { if (typeof j.unread === 'number') setUnread(j.unread); }).catch(function () {});
    }
    openUrl(it.url);
  }
  function openUrl(url) {
    if (!url || url.charAt(0) !== '/') return;
    var tab = panelTarget(url);
    if (tab) { if (tab === 'benachrichtigungen') return; go(tab); return; }
    var A = B();
    if (A && (A.isStandalone || A.isNative)) location.href = url; else window.open(url, '_blank', 'noopener');
  }
  function merge(items, append) {
    var seen = {}; ITEMS.forEach(function (x) { seen[x.id] = 1; });
    var fresh = items.filter(function (x) { return !seen[x.id]; });
    ITEMS = append ? ITEMS.concat(fresh) : fresh.concat(ITEMS);
    ITEMS.sort(function (a, b) { return b.id - a.id; });
    ITEMS.forEach(function (x) { if (x.id > MAXID) MAXID = x.id; });
    return fresh.length;
  }
  function loadInbox() {
    return api('/push/inbox?limit=30').then(function (j) {
      ITEMS = []; MAXID = 0; merge(j.items || [], true); MORE = !!j.more; setUnread(j.unread); renderInbox();
    }).catch(function (e) { msg(g('bnInboxMsg'), h(e.message), 'err'); });
  }
  function pollInbox() {
    if (polling || !CFG) return Promise.resolve(); polling = true;
    return api('/push/inbox?since=' + MAXID + '&limit=50').then(function (j) {
      var n = merge(j.items || [], false); setUnread(j.unread); if (n) renderInbox(); msg(g('bnInboxMsg'), '', '');
    }).catch(function () {}).then(function () { polling = false; });
  }
  function loadMore() {
    var min = ITEMS.length ? ITEMS[ITEMS.length - 1].id : 0; if (!min) return;
    var b = g('bnMore'); b.disabled = true;
    api('/push/inbox?before=' + min + '&limit=30').then(function (j) { merge(j.items || [], true); MORE = !!j.more; renderInbox(); })
      .catch(function (e) { msg(g('bnInboxMsg'), h(e.message), 'err'); }).then(function () { b.disabled = false; });
  }
  function readAll() {
    var b = g('bnReadAll'); b.disabled = true;
    api('/push/read', { method: 'POST', body: JSON.stringify({ ids: 'all' }) }).then(function (j) {
      ITEMS.forEach(function (x) { x.read = true; }); setUnread(j.unread || 0); renderInbox();
    }).catch(function (e) { msg(g('bnInboxMsg'), h(e.message), 'err'); b.disabled = false; });
  }

  /* ---------- Laden / Binden ---------- */
  function loadCfg() {
    return api('/push/config').then(function (j) { CFG = j; renderEvents(); renderQuiet(); renderDevices(); setUnread(j.unread); });
  }
  function load() {
    return Promise.all([loadCfg(), loadInbox()]).then(refreshStatus).catch(function (e) {
      msg(g('bnStMsg'), 'Benachrichtigungen konnten nicht geladen werden: ' + h(e.message), 'err');
    });
  }
  function bind() {
    if (bound) return; bound = true;
    g('bnEnable').onclick = function () {
      var A = B(), btn = g('bnEnable'); if (!A) { refreshStatus(); return; }
      btn.disabled = true; msg(g('bnStMsg'), 'Einen Moment – bitte im Dialog „Zulassen“ wählen …', 'info');
      A.enablePush({ label: '' }).then(function (r) {   // direkt im Klick: der Browser zeigt den Erlaubnis-Dialog nur mit Nutzergeste
        btn.disabled = false;
        msg(g('bnStMsg'), h(r.message), r.ok ? 'ok' : (r.reason === 'dismissed' ? 'info' : 'err'));
        return loadCfg().then(refreshStatus);
      }).catch(function () { btn.disabled = false; });
    };
    g('bnTest').onclick = function () { sendTest(g('bnTest')); };
    g('bnTestTop').onclick = function () { sendTest(g('bnTestTop')); };
    g('bnDisable').onclick = function () {
      var A = B(); if (!A) return; var btn = g('bnDisable'); btn.disabled = true;
      A.disablePush().then(function (r) { btn.disabled = false; msg(g('bnStMsg'), h(r.message), r.ok ? 'ok' : 'err'); return loadCfg().then(refreshStatus); });
    };
    g('bnRecheck').onclick = function () { msg(g('bnStMsg'), '', ''); loadCfg().then(refreshStatus); };
    g('bnInstall').onclick = function () { var A = B(); if (A) A.install().then(function (r) { msg(g('bnStMsg'), h(r.message), r.ok ? 'ok' : 'info'); refreshStatus(); }); };
    window.addEventListener('chathelp:installable', function () { if (visible()) refreshStatus(); });
    g('bnReadAll').onclick = readAll;
    g('bnMore').onclick = loadMore;
    document.querySelectorAll('#bnFilter [data-f]').forEach(function (b) {
      b.onclick = function () {
        FILTER = b.getAttribute('data-f');
        document.querySelectorAll('#bnFilter [data-f]').forEach(function (x) { x.classList.toggle('p', x === b); });
        renderInbox();
      };
    });
    ['bnQFrom', 'bnQTo'].forEach(function (id) {
      g(id).addEventListener('change', function () {
        var f = g('bnQFrom'), t = g('bnQTo');
        if (id === 'bnQFrom' && f.value && !t.value) t.value = f.value === '07:00' ? '08:00' : '07:00';   // sinnvolle Ergänzung statt halber Ruhezeit
        if (id === 'bnQTo' && t.value && !f.value) f.value = t.value === '22:00' ? '21:00' : '22:00';
        if (id === 'bnQFrom' && !f.value) t.value = ''; if (id === 'bnQTo' && !t.value) f.value = '';
        scheduleSave();
      });
    });
    g('bnQTz').addEventListener('change', scheduleSave);
    [['bnQUrgentRow', 'bnQUrgent'], ['bnMailRow', 'bnMail']].forEach(function (p) {
      g(p[1]).addEventListener('change', scheduleSave);
      g(p[0]).style.cursor = 'pointer';
      g(p[0]).addEventListener('click', function (e) { if (e.target.tagName === 'INPUT') return; var cb = g(p[1]); cb.checked = !cb.checked; scheduleSave(); });
    });
  }
  function startTimer() {
    if (timer) return;
    timer = setInterval(function () { if (!visible()) return; if (!CFG) load(); else pollInbox(); }, 30000);
  }

  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  STUDIO_PANELS['benachrichtigungen'] = {
    init: function () {
      bind(); startTimer();
      paintStatus({ tone: 'mute', icon: 'bell', tagText: 'Wird geprüft …', title: 'Dieses Gerät', text: '' });
      ensureBridge().then(function () { initBridge(); return load(); });
    },
    show: function () { startTimer(); load(); }
  };
})();
