/* ChatHelp — App-Brücke für Studio, Fahrer-Seiten und die native App (Capacitor)
   ============================================================================
   window.ChatHelpApp = {
     init({api, getKey, scope, token, module, label}),   // Studio: init({api, getKey:()=>KEY}); Fahrer: init({scope:'driver', token:'<24-hex>', module:'ride'|'order'})
     isNative, isStandalone, isIOS, isAndroid, platform,  // Eigenschaften (boolean bzw. 'web'|'android'|'ios')
     pushState()   → Promise<'unsupported'|'denied'|'default'|'granted'|'subscribed'>
     enablePush()  → Promise<{ok, reason, message}>      // IM KLICK aufrufen (Berechtigungsdialog braucht die Nutzergeste)
     disablePush() → Promise<{ok, reason, message}>
     installHint() → deutscher Hinweis (iPhone: Teilen → Zum Home-Bildschirm; Android: App installieren) oder ''
     deniedHint()  → deutscher Hinweis, wie man blockierte Benachrichtigungen wieder erlaubt
     onNotificationTap(cb)  // cb(data{url,event,tag,id}); gibt cb true zurück, entfällt die Standard-Navigation
     deviceHash()  → Promise<string> (16 Hex, passt zu devices[].h aus /v1/push/config)
     setUnread(n), startUnreadWatch(sec), install(), canInstall
   }
   Ereignisse auf window: 'chathelp:push' (detail = Nutzlast, auch im Vordergrund), 'chathelp:unread' (detail = {count}).
   Wirft nie – fehlende Plugins/APIs ergeben {ok:false, reason, message}. */
(function () {
  'use strict';
  if (window.ChatHelpApp && window.ChatHelpApp.version) return;

  var VERSION = '2026-10-17.1';
  var SW_URL = '/chat/sw.js', SW_SCOPE = '/chat/', SUB_URL = '/chat/push-sub.php', API_BASE = '/chat/tokens-api.php/v1';
  var cfg = { api: null, getKey: null, scope: 'owner', token: '', module: '', label: '' };
  var inited = false, regPromise = null, infoPromise = null, nativeReady = false, nativeToken = '', deferredInstall = null;
  var tapCbs = [], unreadTimer = null, lastUnread = null;
  var ua = (navigator && navigator.userAgent) || '';
  var noop = function () {};

  /* ---------- kleine Helfer ---------- */
  function safe(fn) { try { return fn(); } catch (e) { return undefined; } }
  function store(k, v) {
    try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) {}
    return null;
  }
  function res(ok, reason, message, extra) {
    var r = { ok: !!ok, reason: reason || (ok ? 'ok' : 'error'), message: message || '' };
    if (extra) for (var k in extra) if (Object.prototype.hasOwnProperty.call(extra, k)) r[k] = extra[k];
    return r;
  }
  function mkErr(reason, message) { var e = new Error(message || reason); e.reason = reason; e.userMessage = message; return e; }
  function emit(name, detail) {
    safe(function () {
      var ev;
      if (typeof window.CustomEvent === 'function') ev = new CustomEvent(name, { detail: detail });
      else { ev = document.createEvent('CustomEvent'); ev.initCustomEvent(name, false, false, detail); }
      window.dispatchEvent(ev);
    });
  }
  function b64uToBytes(s) {
    s = String(s || ''); var pad = '='.repeat((4 - (s.length % 4)) % 4);
    var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/')); var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function bytesEqual(a, b) {
    if (!a || !b) return false; a = new Uint8Array(a); b = new Uint8Array(b);
    if (a.length !== b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
    return true;
  }
  function sha256hex(text) {
    try {
      if (!window.crypto || !crypto.subtle || !window.TextEncoder) return Promise.resolve('');
      return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buf) {
        var a = new Uint8Array(buf), s = ''; for (var i = 0; i < a.length; i++) s += ('0' + a[i].toString(16)).slice(-2); return s;
      }).catch(function () { return ''; });
    } catch (e) { return Promise.resolve(''); }
  }

  /* ---------- Plattform ---------- */
  function plugin(name) { return safe(function () { var C = window.Capacitor; return (C && C.Plugins && C.Plugins[name]) || null; }) || null; }
  function isNative() { return !!safe(function () { return window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform(); }); }
  function platform() {
    if (!isNative()) return 'web';
    var p = safe(function () { return window.Capacitor.getPlatform(); });
    return p === 'ios' || p === 'android' ? p : 'android';
  }
  function isIOS() { return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) || platform() === 'ios'; }
  function isAndroid() { return /Android/i.test(ua) || platform() === 'android'; }
  function isStandalone() {
    return !!safe(function () {
      if (navigator.standalone === true) return true;
      if (!window.matchMedia) return false;
      return matchMedia('(display-mode: standalone)').matches || matchMedia('(display-mode: minimal-ui)').matches || matchMedia('(display-mode: fullscreen)').matches;
    });
  }
  function iosVersion() { var m = /OS (\d+)[_.](\d+)/.exec(ua); return m ? [parseInt(m[1], 10), parseInt(m[2], 10)] : null; }
  function iosTooOld() { var v = iosVersion(); return !!(v && (v[0] < 16 || (v[0] === 16 && v[1] < 4))); }
  function webSupported() {
    return !!safe(function () { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext !== false; });
  }

  /* ---------- Texte (deutsch) ---------- */
  function installHint() {
    if (isNative()) return '';
    if (isIOS()) {
      if (iosTooOld()) return 'Benachrichtigungen gibt es auf iPhone und iPad ab iOS 16.4. Bitte zuerst aktualisieren: Einstellungen → Allgemein → Softwareupdate.';
      if (isStandalone()) return '';
      return 'Auf iPhone und iPad funktionieren Benachrichtigungen nur in der installierten Web-App (ab iOS 16.4): Tippe in Safari unten auf „Teilen“ (Quadrat mit Pfeil nach oben) → „Zum Home-Bildschirm“ → „Hinzufügen“. Öffne ChatHelp danach über das neue Symbol auf dem Home-Bildschirm und tippe dort auf „Benachrichtigungen aktivieren“.';
    }
    if (isAndroid()) return isStandalone() ? '' : 'Tipp: Installiere ChatHelp als App – im Chrome-Menü ⋮ → „App installieren“ bzw. „Zum Startbildschirm hinzufügen“. Benachrichtigungen funktionieren aber auch direkt hier im Browser.';
    return 'Benachrichtigungen funktionieren in Chrome, Edge, Firefox und Safari (ab macOS 13), solange der Browser geöffnet ist oder im Hintergrund läuft. Tipp: Über das Installieren-Symbol in der Adressleiste wird ChatHelp zur eigenen App.';
  }
  function deniedHint() {
    if (isNative()) {
      return platform() === 'ios'
        ? 'Mitteilungen sind für die App ausgeschaltet: iPhone-Einstellungen → Mitteilungen → ChatHelp Business → „Mitteilungen erlauben“ einschalten.'
        : 'Benachrichtigungen sind für die App ausgeschaltet: Einstellungen → Apps → ChatHelp Business → Benachrichtigungen → einschalten (auch „Aufträge & Hinweise“).';
    }
    if (isIOS()) return 'Mitteilungen sind blockiert: iPhone-Einstellungen → Mitteilungen → ChatHelp → „Mitteilungen erlauben“ einschalten und die App neu öffnen.';
    if (isAndroid()) return 'Benachrichtigungen sind blockiert: Tippe links neben der Adresse auf das Schloss bzw. ⓘ → Berechtigungen → Benachrichtigungen → „Zulassen“. In der installierten App: lange auf das ChatHelp-Symbol tippen → App-Info → Benachrichtigungen.';
    return 'Benachrichtigungen sind für chat-help.com blockiert: Klicke links neben der Adresse auf das Schloss- bzw. Regler-Symbol → Benachrichtigungen → „Zulassen“ und lade die Seite neu. Auf dem Mac zusätzlich: Systemeinstellungen → Mitteilungen → (dein Browser) → Mitteilungen erlauben.';
  }
  function unsupportedText() {
    var h = installHint();
    if (isIOS() && !isStandalone() && !iosTooOld()) return h;
    return 'Dieser Browser unterstützt keine Push-Benachrichtigungen. Nutze Chrome, Edge, Firefox oder Safari – oder die ChatHelp-App. Wichtige Ereignisse bekommst du bis dahin per E-Mail.' + (h ? ' ' + h : '');
  }

  /* ---------- Server ---------- */
  function info() {
    if (infoPromise) return infoPromise;
    infoPromise = fetch(SUB_URL + '?vapid=1&_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.vapid_public) throw mkErr('server', j.message || 'Der Server liefert keinen Push-Schlüssel.'); return j; }); })
      .catch(function (e) { infoPromise = null; throw e.reason ? e : mkErr('offline', 'Keine Verbindung zum Server – bitte Internet prüfen und erneut versuchen.'); });
    return infoPromise;
  }
  function apiPost(path, body) {
    if (cfg.scope !== 'owner') {
      var b = { scope: cfg.scope, t: cfg.token, module: cfg.module };
      for (var k in body) if (Object.prototype.hasOwnProperty.call(body, k)) b[k] = body[k];
      if (path === '/push/unsubscribe') b.action = 'unsubscribe';
      return fetch(SUB_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, cache: 'no-store', body: JSON.stringify(b) })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw mkErr(j.error || 'server', j.message || ('Fehler ' + r.status)); return j; }); });
    }
    if (typeof cfg.api === 'function') {
      return Promise.resolve().then(function () { return cfg.api(path, { method: 'POST', body: JSON.stringify(body) }); })
        .catch(function (e) { var d = (e && e.data) || {}; throw mkErr(d.error || 'server', d.message || (e && e.message) || 'Serverfehler'); });
    }
    var key = safe(function () { return cfg.getKey ? cfg.getKey() : ''; }) || '';
    if (!key) return Promise.reject(mkErr('login', 'Bitte zuerst im Studio anmelden.'));
    return fetch(API_BASE + path + '?_=' + Date.now(), { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + key }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw mkErr(j.error || 'server', j.message || ('Fehler ' + r.status)); return j; }); });
  }
  function apiGet(path) {
    if (typeof cfg.api === 'function') return Promise.resolve().then(function () { return cfg.api(path); });
    var key = safe(function () { return cfg.getKey ? cfg.getKey() : ''; }) || '';
    if (!key) return Promise.reject(mkErr('login', ''));
    return fetch(API_BASE + path + (path.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), { cache: 'no-store', headers: { 'Authorization': 'Bearer ' + key } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw mkErr(j.error || 'server', j.message || ''); return j; }); });
  }

  /* ---------- Service Worker ---------- */
  function ensureSW() {
    if (regPromise) return regPromise;
    if (!safe(function () { return 'serviceWorker' in navigator; })) return Promise.resolve(null);
    regPromise = info().then(function (j) { return j.sw ? SW_URL + '?v=' + encodeURIComponent(j.sw) : SW_URL; }, function () { return null; })
      .then(function (url) {
        if (!url) return navigator.serviceWorker.getRegistration(SW_SCOPE).then(function (r) { return r || navigator.serviceWorker.register(SW_URL, { scope: SW_SCOPE }); });
        return navigator.serviceWorker.register(url, { scope: SW_SCOPE, updateViaCache: 'none' });
      })
      .catch(function () { regPromise = null; return safe(function () { return navigator.serviceWorker.getRegistration(SW_SCOPE); }) || null; })
      .then(function (r) { return r || null; }, function () { return null; });
    return regPromise;
  }
  function getReg() {
    if (!safe(function () { return 'serviceWorker' in navigator; })) return Promise.resolve(null);
    return Promise.resolve(safe(function () { return navigator.serviceWorker.getRegistration(SW_SCOPE); })).then(function (r) { return r || null; }, function () { return null; });
  }
  function waitActive(reg) {
    if (reg.active) return Promise.resolve(reg);
    return new Promise(function (resolve, reject) {
      var w = reg.installing || reg.waiting, t = setTimeout(function () { reject(mkErr('sw_timeout', 'Der Hintergrund-Dienst startet nicht. Bitte Seite neu laden und erneut versuchen.')); }, 15000);
      var done = function () { clearTimeout(t); resolve(reg); };
      if (!w) { navigator.serviceWorker.ready.then(done); return; }
      w.addEventListener('statechange', function () { if (w.state === 'activated') done(); else if (w.state === 'redundant') { clearTimeout(t); reject(mkErr('sw_failed', 'Der Hintergrund-Dienst konnte nicht installiert werden.')); } });
    });
  }
  function tellSW(reg, msg) { safe(function () { var w = (reg && (reg.active || reg.waiting || reg.installing)) || (navigator.serviceWorker && navigator.serviceWorker.controller); if (w) w.postMessage(msg); }); }

  function requestPermission() {
    return new Promise(function (resolve) {
      try {
        var p = Notification.requestPermission(function (x) { resolve(x); });   // ältere Safari-Versionen: Callback
        if (p && typeof p.then === 'function') p.then(resolve, function () { resolve(Notification.permission); });
      } catch (e) { resolve(safe(function () { return Notification.permission; }) || 'default'); }
    });
  }
  function subscribeWith(reg, vapid) {
    var key = b64uToBytes(vapid);
    return reg.pushManager.getSubscription().then(function (s) {
      var old = s && s.options && s.options.applicationServerKey;
      if (s && (!old || bytesEqual(old, key))) return s;
      return (s ? s.unsubscribe().catch(noop) : Promise.resolve()).then(function () {
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
      });
    }).catch(function (e) {
      var n = (e && e.name) || '', m = (e && e.message) || '';
      if (n === 'NotAllowedError') throw mkErr('denied', deniedHint());
      if (/brave/i.test(ua) || (navigator.brave && true)) throw mkErr('push_service', 'Der Push-Dienst ist im Browser ausgeschaltet. In Brave: Einstellungen → Datenschutz und Sicherheit → „Google-Dienste für Push-Nachrichten verwenden“ einschalten und erneut versuchen.');
      throw mkErr('push_service', 'Der Push-Dienst des Browsers ist gerade nicht erreichbar' + (m ? ' (' + m + ')' : '') + '. Bitte Internetverbindung prüfen und erneut versuchen.');
    });
  }

  /* ---------- Öffentliche Funktionen: Status / Ein / Aus ---------- */
  function pushState() {
    return new Promise(function (resolve) {
      try {
        if (isNative()) {
          var PN = plugin('PushNotifications'); if (!PN || !PN.checkPermissions) return resolve('unsupported');
          Promise.resolve(PN.checkPermissions()).then(function (p) {
            var r = p && p.receive;
            if (r === 'granted') resolve(store('ch_push_native') ? 'subscribed' : 'granted');
            else if (r === 'denied') resolve('denied'); else resolve('default');
          }, function () { resolve('unsupported'); });
          return;
        }
        if (!webSupported()) return resolve('unsupported');
        var perm = Notification.permission;
        if (perm === 'denied') return resolve('denied');
        if (perm !== 'granted') return resolve('default');
        getReg().then(function (reg) {
          if (!reg || !reg.pushManager) return resolve('granted');
          return reg.pushManager.getSubscription().then(function (s) { resolve(s ? 'subscribed' : 'granted'); });
        }).catch(function () { resolve('granted'); });
      } catch (e) { resolve('unsupported'); }
    });
  }

  function enablePush(opts) {
    opts = opts || {};
    try {
      if (isNative()) return nativeEnable(opts);
      if (!webSupported()) return Promise.resolve(res(false, isIOS() && !isStandalone() && !iosTooOld() ? 'ios_install' : 'unsupported', unsupportedText()));
      if (Notification.permission === 'denied') return Promise.resolve(res(false, 'denied', deniedHint()));
      /* Berechtigung SOFORT (noch in der Klick-Geste) anfragen – erst danach asynchron weiter */
      var permP = Notification.permission === 'granted' ? Promise.resolve('granted') : requestPermission();
      var regP = ensureSW(), infoP = info();
      infoP.catch(noop);   // Fehler wird unten ausgewertet – hier nur „unhandled rejection“ vermeiden
      return permP.then(function (p) {
        if (p !== 'granted') return res(false, p === 'denied' ? 'denied' : 'dismissed', p === 'denied' ? deniedHint() : 'Die Anfrage wurde geschlossen. Tippe erneut auf „Benachrichtigungen aktivieren“ und wähle „Zulassen“.');
        return Promise.all([regP, infoP]).then(function (a) {
          var reg = a[0], inf = a[1];
          if (!reg) throw mkErr('sw_failed', 'Der Hintergrund-Dienst (Service Worker) konnte nicht starten. Bitte Seite neu laden – im privaten Modus sind Benachrichtigungen nicht möglich.');
          return waitActive(reg).then(function () { return subscribeWith(reg, inf.vapid_public); }).then(function (sub) {
            var json = sub.toJSON ? sub.toJSON() : sub;
            return apiPost('/push/subscribe', { type: 'webpush', subscription: json, label: opts.label || cfg.label || '' }).then(function (j) {
              store('ch_push_ep', json.endpoint);
              tellSW(reg, { type: 'chathelp-push-config', vapid: inf.vapid_public, endpoint: json.endpoint, auth: (json.keys && json.keys.auth) || '' });
              return res(true, 'subscribed', j.message || 'Benachrichtigungen sind auf diesem Gerät aktiv.', { id: j.id || 0, endpoint: json.endpoint });
            });
          });
        });
      }).catch(function (e) {
        return res(false, (e && e.reason) || 'error', (e && e.userMessage) || ('Aktivieren hat nicht geklappt: ' + ((e && e.message) || e)));
      });
    } catch (e) {
      return Promise.resolve(res(false, 'error', 'Aktivieren hat nicht geklappt: ' + ((e && e.message) || e)));
    }
  }

  function disablePush() {
    try {
      if (isNative()) {
        var PN = plugin('PushNotifications'), tok = nativeToken || store('ch_push_native') || '';
        return (tok ? apiPost('/push/unsubscribe', { token: tok }).catch(noop) : Promise.resolve())
          .then(function () { store('ch_push_native', null); nativeToken = ''; return PN && PN.unregister ? Promise.resolve(PN.unregister()).catch(noop) : null; })
          .then(function () { return res(true, 'disabled', 'Benachrichtigungen sind in der App ausgeschaltet.'); })
          .catch(function (e) { return res(false, 'error', (e && e.message) || 'Ausschalten fehlgeschlagen.'); });
      }
      if (!webSupported()) return Promise.resolve(res(false, 'unsupported', unsupportedText()));
      return getReg().then(function (reg) { return reg && reg.pushManager ? reg.pushManager.getSubscription() : null; }).then(function (s) {
        if (!s) { store('ch_push_ep', null); return res(true, 'disabled', 'Auf diesem Gerät waren keine Benachrichtigungen aktiv.'); }
        var ep = s.endpoint;
        return apiPost('/push/unsubscribe', { endpoint: ep }).catch(noop).then(function () { return s.unsubscribe().catch(noop); }).then(function () {
          store('ch_push_ep', null);
          return res(true, 'disabled', 'Benachrichtigungen sind auf diesem Gerät ausgeschaltet.');
        });
      }).catch(function (e) { return res(false, 'error', (e && e.message) || 'Ausschalten fehlgeschlagen.'); });
    } catch (e) { return Promise.resolve(res(false, 'error', 'Ausschalten fehlgeschlagen.')); }
  }

  /** Kennung dieses Geräts wie auf dem Server (sha256 des Endpoints bzw. „fcm:“+Token, erste 16 Hex-Zeichen). */
  function deviceHash() {
    try {
      if (isNative()) { var t = nativeToken || store('ch_push_native') || ''; return t ? sha256hex('fcm:' + t).then(function (h) { return h.slice(0, 16); }) : Promise.resolve(''); }
      if (!webSupported()) return Promise.resolve('');
      return getReg().then(function (reg) { return reg && reg.pushManager ? reg.pushManager.getSubscription() : null; })
        .then(function (s) { return s ? sha256hex(s.endpoint).then(function (h) { return h.slice(0, 16); }) : ''; })
        .catch(function () { return ''; });
    } catch (e) { return Promise.resolve(''); }
  }

  /* ---------- Native App (Capacitor) ---------- */
  function nativeSetup() {
    if (nativeReady) return; nativeReady = true;
    var PN = plugin('PushNotifications'), LN = plugin('LocalNotifications');
    if (PN && PN.addListener) {
      safe(function () { PN.addListener('pushNotificationReceived', function (n) { safe(function () { onForeground(n || {}); }); }); });
      safe(function () { PN.addListener('pushNotificationActionPerformed', function (a) { safe(function () { var n = (a && a.notification) || {}; handleTap(n.data || {}); }); }); });
      safe(function () { PN.addListener('registration', function (t) { if (t && t.value && store('ch_push_native') && t.value !== store('ch_push_native')) syncNativeToken(t.value); }); });
    }
    if (LN && LN.addListener) safe(function () { LN.addListener('localNotificationActionPerformed', function (a) { safe(function () { handleTap((a && a.notification && a.notification.extra) || {}); }); }); });
  }
  function syncNativeToken(tok) {
    nativeToken = tok;
    return apiPost('/push/subscribe', { type: 'fcm', token: tok, platform: platform() === 'ios' ? 'ios' : 'android', label: cfg.label || '' })
      .then(function (j) { store('ch_push_native', tok); return j; }).catch(function () { return null; });
  }
  function nativeEnable(opts) {
    var PN = plugin('PushNotifications');
    if (!PN || !PN.register) return Promise.resolve(res(false, 'plugin_missing', 'Diese App-Version kann noch keine Benachrichtigungen empfangen – bitte die ChatHelp-App aktualisieren.'));
    nativeSetup();
    var chan = platform() === 'android' && PN.createChannel
      ? Promise.resolve(safe(function () {
          return PN.createChannel({ id: 'chathelp_alerts', name: 'Aufträge & Hinweise', description: 'Neue Bestellungen, Fahrten, Termine, Rückrufe und wichtige Hinweise',
            importance: 5, sound: 'default', vibration: true, visibility: 1, lights: true, lightColor: '#B8862B' });
        })).catch(noop)
      : Promise.resolve();
    return chan.then(function () { return PN.checkPermissions(); }).then(function (p) {
      if (p && p.receive === 'granted') return p;
      return PN.requestPermissions();
    }).then(function (p) {
      if (!p || p.receive !== 'granted') throw mkErr('denied', deniedHint());
      return new Promise(function (resolve, reject) {
        var done = false, handles = [];
        var finish = function () { done = true; clearTimeout(timer); handles.forEach(function (h) { safe(function () { h.remove(); }); }); };
        var keep = function (h) { Promise.resolve(h).then(function (x) { if (!x) return; if (done) safe(function () { x.remove(); }); else handles.push(x); }, noop); };
        var timer = setTimeout(function () { if (done) return; finish(); reject(mkErr('timeout', 'Keine Antwort vom Push-Dienst. Bitte Internetverbindung prüfen und erneut versuchen.')); }, 20000);
        keep(safe(function () { return PN.addListener('registration', function (t) { if (done) return; finish(); resolve(t && t.value); }); }));
        keep(safe(function () { return PN.addListener('registrationError', function (err) { if (done) return; finish(); reject(mkErr('registration_error', 'Anmeldung beim Push-Dienst fehlgeschlagen' + (err && err.error ? ' (' + err.error + ')' : '') + '.')); }); }));
        Promise.resolve(safe(function () { return PN.register(); })).catch(function (e) { if (done) return; finish(); reject(mkErr('registration_error', 'Anmeldung beim Push-Dienst fehlgeschlagen' + (e && e.message ? ' (' + e.message + ')' : '') + '.')); });
      });
    }).then(function (tok) {
      if (!tok) throw mkErr('registration_error', 'Die App hat kein Geräte-Token erhalten.');
      nativeToken = tok;
      return apiPost('/push/subscribe', { type: 'fcm', token: tok, platform: platform() === 'ios' ? 'ios' : 'android', label: opts.label || cfg.label || '' }).then(function (j) {
        store('ch_push_native', tok);
        return res(true, 'subscribed', j.message || 'Benachrichtigungen sind in der App aktiv.', { id: j.id || 0 });
      });
    }).catch(function (e) {
      return res(false, (e && e.reason) || 'error', (e && e.userMessage) || ('Aktivieren hat nicht geklappt: ' + ((e && e.message) || e)));
    });
  }
  function onForeground(n) {
    var d = n.data || {};
    var p = { title: n.title || d.title || 'ChatHelp', body: n.body || d.body || '', url: d.url || '', event: d.event || 'system', tag: d.tag || '', id: +d.id || 0, badge: d.badge != null && d.badge !== '' ? +d.badge : null };
    emit('chathelp:push', p);
    if (p.badge !== null && !isNaN(p.badge)) setUnread(p.badge);
    toast(p);
    if (platform() === 'android') {
      var LN = plugin('LocalNotifications');
      if (LN && LN.schedule) Promise.resolve(safe(function () {
        return LN.schedule({ notifications: [{ id: (Date.now() % 2000000000) | 0, title: p.title, body: p.body, channelId: 'chathelp_alerts', smallIcon: 'ic_stat_chathelp', iconColor: '#B8862B', extra: { url: p.url, event: p.event, tag: p.tag, id: p.id } }] });
      })).catch(noop);
    }
  }

  /* ---------- Antippen einer Benachrichtigung ---------- */
  function navigateTo(url) {
    try {
      if (!url) return false;
      var u = new URL(url, location.href);
      if (u.origin !== location.origin) return false;
      if (u.pathname === location.pathname) {
        var h = (u.hash || '').slice(1);
        if (h && typeof window.go === 'function') { try { window.go(h); return true; } catch (e) {} }
        if (u.href === location.href) return true;
        if (h && u.search === location.search) { location.hash = h; return true; }
      }
      location.assign(u.href); return true;
    } catch (e) { return false; }
  }
  function fireTap(data) {
    var handled = false;
    tapCbs.forEach(function (cb) { try { if (cb(data) === true) handled = true; } catch (e) {} });
    return handled;
  }
  function handleTap(data) { data = data || {}; if (!fireTap(data) && data.url) navigateTo(data.url); }

  /* ---------- In-App-Hinweis (Vordergrund, native App) ---------- */
  function toast(p) {
    safe(function () {
      var old = document.getElementById('chAppToast'); if (old) old.remove();
      var el = document.createElement('div'); el.id = 'chAppToast'; el.setAttribute('role', 'status');
      el.style.cssText = 'position:fixed;left:50%;top:calc(12px + env(safe-area-inset-top));transform:translate(-50%,-140%);z-index:2147483000;width:min(440px,calc(100vw - 32px));' +
        'background:#fff;color:#0f1222;border:1px solid #e4e6ee;border-left:4px solid #b8862b;border-radius:16px;padding:12px 14px;box-shadow:0 2px 6px rgba(15,18,34,.06),0 24px 60px rgba(15,18,34,.18);' +
        'font:14px/1.45 Inter,system-ui,sans-serif;cursor:pointer;transition:transform .35s cubic-bezier(.2,.9,.3,1.2)';
      var t = document.createElement('div'); t.style.cssText = 'font-weight:700;margin-bottom:2px'; t.textContent = p.title;
      var b = document.createElement('div'); b.style.cssText = 'color:#3b4055;font-size:13.5px'; b.textContent = p.body;
      el.appendChild(t); if (p.body) el.appendChild(b);
      el.onclick = function () { el.remove(); handleTap({ url: p.url, event: p.event, tag: p.tag, id: p.id }); };
      document.body.appendChild(el);
      requestAnimationFrame(function () { el.style.transform = 'translate(-50%,0)'; });
      setTimeout(function () { el.style.transform = 'translate(-50%,-140%)'; setTimeout(function () { el.remove(); }, 400); }, 7000);
    });
  }

  /* ---------- Ungelesen-Zähler (App-Symbol + [data-ch-unread]) ---------- */
  function setUnread(n) {
    n = Math.max(0, parseInt(n, 10) || 0);
    safe(function () {
      var els = document.querySelectorAll('[data-ch-unread]');
      for (var i = 0; i < els.length; i++) { els[i].textContent = n > 99 ? '99+' : String(n); els[i].style.display = n > 0 ? '' : 'none'; els[i].setAttribute('aria-label', n + ' ungelesene Benachrichtigungen'); }
    });
    if (n !== lastUnread) {
      lastUnread = n;
      safe(function () { if (n > 0 && navigator.setAppBadge) navigator.setAppBadge(n).catch(noop); else if (n === 0 && navigator.clearAppBadge) navigator.clearAppBadge().catch(noop); });
      emit('chathelp:unread', { count: n });
    }
    return n;
  }
  function startUnreadWatch(sec) {
    if (unreadTimer) clearInterval(unreadTimer);
    var tick = function () {
      if (document.visibilityState === 'hidden') return;
      apiGet('/push/inbox?limit=1').then(function (j) { setUnread(j.unread); }, noop);
    };
    tick(); unreadTimer = setInterval(tick, Math.max(20, sec || 60) * 1000);
    safe(function () { document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') tick(); }); });
  }

  /* ---------- Installation (Android/Desktop: beforeinstallprompt) ---------- */
  safe(function () {
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferredInstall = e; emit('chathelp:installable', {}); });
    window.addEventListener('appinstalled', function () { deferredInstall = null; emit('chathelp:installed', {}); });
  });
  function install() {
    if (!deferredInstall) return Promise.resolve(res(false, 'not_available', installHint()));
    var ev = deferredInstall; deferredInstall = null;
    return Promise.resolve(safe(function () { ev.prompt(); return ev.userChoice; })).then(function (c) {
      return c && c.outcome === 'accepted' ? res(true, 'installed', 'ChatHelp wird als App installiert.') : res(false, 'dismissed', 'Installation abgebrochen.');
    }, function () { return res(false, 'error', 'Installation nicht möglich.'); });
  }

  /* ---------- Start ---------- */
  function init(o) {
    o = o || {};
    if (typeof o.api === 'function') cfg.api = o.api;
    if (typeof o.getKey === 'function') cfg.getKey = o.getKey;
    if (o.scope === 'driver' || o.scope === 'board') { cfg.scope = o.scope; cfg.token = String(o.token || ''); cfg.module = String(o.module || ''); }
    if (o.label) cfg.label = String(o.label).slice(0, 80);
    if (inited) return api;
    inited = true;
    if (isNative()) {
      nativeSetup();
      /* Token kann sich ändern (App-Update, Neuinstallation): bei erteilter Berechtigung still neu anmelden */
      var PN = plugin('PushNotifications');
      if (PN && PN.checkPermissions && store('ch_push_native')) Promise.resolve(safe(function () { return PN.checkPermissions(); })).then(function (p) { if (p && p.receive === 'granted') safe(function () { return PN.register(); }); }, noop);
      return api;
    }
    if (!safe(function () { return 'serviceWorker' in navigator; })) return api;
    safe(function () {
      navigator.serviceWorker.addEventListener('message', function (e) {
        var d = e.data || {};
        if (d.type === 'chathelp-push') { var p = d.payload || {}; emit('chathelp:push', p); if (typeof p.badge === 'number') setUnread(p.badge); }
        else if (d.type === 'chathelp-notification-click') {
          var data = d.data || {}, ok = fireTap(data) || (d.navigate ? navigateTo(data.url) : false);
          if (e.ports && e.ports[0]) safe(function () { e.ports[0].postMessage({ ok: !!ok }); });
        }
      });
    });
    /* Service Worker registrieren (versionierte URL – an Cloudflare vorbei immer die aktuelle sw.js) und Abo-Daten für pushsubscriptionchange hinterlegen */
    ensureSW().then(function (reg) {
      if (!reg || !webSupported() || Notification.permission !== 'granted') return;
      return reg.pushManager.getSubscription().then(function (s) {
        if (!s) return;
        var json = s.toJSON ? s.toJSON() : s;
        info().then(function (inf) { tellSW(reg, { type: 'chathelp-push-config', vapid: inf.vapid_public, endpoint: json.endpoint, auth: (json.keys && json.keys.auth) || '' }); }, noop);
        var known = store('ch_push_ep');
        if (known && known !== json.endpoint && (cfg.scope !== 'owner' || cfg.api || cfg.getKey)) {   // Abo wurde im Hintergrund erneuert → Server aktualisieren
          apiPost('/push/subscribe', { type: 'webpush', subscription: json, label: cfg.label || '' }).then(function () { store('ch_push_ep', json.endpoint); }, noop);
        }
      });
    }).catch(noop);
    return api;
  }

  var api = {
    version: VERSION,
    init: init,
    pushState: pushState,
    enablePush: enablePush,
    disablePush: disablePush,
    installHint: installHint,
    deniedHint: deniedHint,
    onNotificationTap: function (cb) { if (typeof cb === 'function') tapCbs.push(cb); return api; },
    deviceHash: deviceHash,
    setUnread: setUnread,
    startUnreadWatch: startUnreadWatch,
    install: install,
    navigate: navigateTo
  };
  Object.defineProperty(api, 'isNative', { get: isNative, enumerable: true });
  Object.defineProperty(api, 'isStandalone', { get: isStandalone, enumerable: true });
  Object.defineProperty(api, 'isIOS', { get: isIOS, enumerable: true });
  Object.defineProperty(api, 'isAndroid', { get: isAndroid, enumerable: true });
  Object.defineProperty(api, 'platform', { get: platform, enumerable: true });
  Object.defineProperty(api, 'canInstall', { get: function () { return !!deferredInstall; }, enumerable: true });
  Object.defineProperty(api, 'webPushSupported', { get: webSupported, enumerable: true });
  window.ChatHelpApp = api;
})();
