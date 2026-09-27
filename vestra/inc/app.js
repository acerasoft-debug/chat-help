/* VESTRA app client: service worker, install prompt, push opt-in, app-icon badge.
   ONE copy for every page. foot.php and index.php each used to carry their own
   opt-in function, and the two had already drifted apart.

   Server-rendered boot data (vestra_app_boot() in inc/push.php):
     window.VESTRA_BOOT = { signedIn, stamped, vapid, unread, s: { …strings… } }

   Push states, as the panel card and the homepage box show them:
     on | off | denied | unsupported | ios-install | signin | error
   'ios-install' is its own state because iPhone Safari in a normal tab has no
   push at all. It only exists once VESTRA is added to the Home Screen, and
   telling that user "blocked in your settings" (as v2 did) sent them hunting
   for a switch that does not exist. */
(function () {
  'use strict';
  var B = window.VESTRA_BOOT || {};
  var S = B.s || {};
  var hasSW = 'serviceWorker' in navigator;

  var swReady = hasSW
    ? navigator.serviceWorker.register('/sw.js').then(function () { return navigator.serviceWorker.ready; }).catch(function () { return null; })
    : Promise.resolve(null);

  function isIOS() {
    return /iP(hone|ad|od)/.test(navigator.userAgent || '') || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }
  function standalone() {
    return (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  }
  function capable() { return hasSW && 'PushManager' in window && 'Notification' in window; }
  function b64u(buf) {
    if (!buf) return '';
    var s = '', b = new Uint8Array(buf);
    for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]);
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function bytes(s) {
    var raw = atob((s + '='.repeat((4 - s.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function post(action, body) {
    return fetch('/push?a=' + action, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {})
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.status === 401) j.error = 'signin';
        if (!r.ok && !j.error) j.error = 'http_' + r.status;
        return j;
      });
    }).catch(function () { return { error: 'network' }; });
  }
  function currentSub() {
    return swReady.then(function (reg) { return reg ? reg.pushManager.getSubscription() : null; }).catch(function () { return null; });
  }

  /* What is true for THIS browser right now. Asks nothing of the user. */
  function state() {
    if (!capable()) return Promise.resolve(isIOS() && !standalone() ? 'ios-install' : 'unsupported');
    if (!B.signedIn) return Promise.resolve('signin');
    if (Notification.permission === 'denied') return Promise.resolve('denied');
    return currentSub().then(function (sub) {
      if (!sub || Notification.permission !== 'granted') return 'off';
      return post('sync', sub.toJSON()).then(function (r) {
        return (r.state === 'on' || r.state === 'relinked') ? 'on' : 'off';
      });
    });
  }

  /* Turn on. The permission prompt is requested BEFORE anything is awaited:
     Safari only accepts it as a direct result of the tap, and an await in
     front of it can cost that. A signed-out visitor is not asked at all.
     v2 prompted first, then said "sign in", leaving a granted permission
     and a subscription that belonged to nobody. */
  function enable() {
    if (!capable()) return Promise.resolve(isIOS() && !standalone() ? 'ios-install' : 'unsupported');
    if (!B.signedIn) return Promise.resolve('signin');
    if (Notification.permission === 'denied') return Promise.resolve('denied');
    var perm = Notification.permission === 'granted' ? Promise.resolve('granted') : Notification.requestPermission();
    return Promise.resolve(perm).then(function (p) {
      if (p !== 'granted') return p === 'denied' ? 'denied' : 'off';
      return swReady.then(function (reg) {
        if (!reg) return 'unsupported';
        return reg.pushManager.getSubscription().then(function (sub) {
          var key = B.vapid || '';
          /* A subscription made with an older server key cannot be reused. */
          if (sub && key && sub.options && sub.options.applicationServerKey && b64u(sub.options.applicationServerKey) !== key) {
            return sub.unsubscribe().then(function () { return null; }, function () { return null; });
          }
          return sub;
        }).then(function (sub) {
          if (sub) return sub;
          var k = B.vapid ? Promise.resolve(B.vapid)
            : fetch('/push?a=vapid').then(function (r) { return r.json(); }).then(function (j) { return j.publicKey || ''; });
          return k.then(function (key) {
            if (!key) throw new Error('no key');
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes(key) });
          });
        }).then(function (sub) {
          return post('subscribe', sub.toJSON()).then(function (r) {
            if (r.error === 'signin') return 'signin';
            return r.ok ? 'on' : 'error';
          });
        });
      });
    }).catch(function () { return 'error'; });
  }

  function disable() {
    return currentSub().then(function (sub) {
      if (!sub) return 'off';
      return post('unsubscribe', { endpoint: sub.endpoint }).then(function () {
        return sub.unsubscribe().then(function () { return 'off'; }, function () { return 'off'; });
      });
    }).catch(function () { return 'error'; });
  }

  function test() {
    return currentSub().then(function (sub) {
      if (!sub) return 'off';
      return post('test', { endpoint: sub.endpoint }).then(function (r) {
        return r.ok ? 'sent' : (r.error === 'wait' ? 'wait' : 'error');
      });
    });
  }

  /* ── Install ───────────────────────────────────────────────────────────
     Chrome's own install bar is kept on pages that have no install button of
     their own. The event is only held back where we offer the button. */
  var deferred = null;
  var ownInstallUI = !!document.querySelector('[data-vapp="install"], #btnAndroid');
  window.addEventListener('beforeinstallprompt', function (e) {
    if (ownInstallUI) e.preventDefault();
    deferred = e;
    paintAll();
  });
  window.addEventListener('appinstalled', function () { deferred = null; paintAll(); });
  function install() {
    if (!deferred) return Promise.resolve('unavailable');
    var ev = deferred; deferred = null;
    ev.prompt();
    return (ev.userChoice || Promise.resolve({})).then(function (c) { paintAll(); return (c && c.outcome) || 'dismissed'; });
  }

  /* ── The panel card ([data-vpush-card]) ─────────────────────────────── */
  var cards = [].slice.call(document.querySelectorAll('[data-vpush-card]'));
  var last = null;
  function paint(card, st, note) {
    var label = card.querySelector('[data-vpush-status]');
    var hint = card.querySelector('[data-vpush-note]');
    var txt = { on: S.on, off: S.off, denied: S.denied, unsupported: S.unsupported, 'ios-install': S.ios_title, error: S.error }[st] || '';
    if (label) { label.textContent = txt; label.setAttribute('data-state', st); }
    var show = function (sel, yes) { [].forEach.call(card.querySelectorAll(sel), function (b) { b.hidden = !yes; b.disabled = false; }); };
    show('[data-vpush="on"]', st === 'off' || st === 'error');
    show('[data-vpush="off"]', st === 'on');
    show('[data-vpush="test"]', st === 'on');
    show('[data-vapp="install"]', !!deferred && !standalone());
    var n = note || ({ denied: S.denied_hint, 'ios-install': S.ios, unsupported: '' }[st] || '');
    if (hint) { hint.textContent = n; hint.hidden = !n; }
  }
  function paintAll(note) { if (last) cards.forEach(function (c) { paint(c, last, note); }); }
  function refresh() {
    if (!cards.length) return;
    state().then(function (st) { last = st; paintAll(); });
  }
  cards.forEach(function (card) {
    card.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-vpush],[data-vapp]');
      if (!btn || !card.contains(btn)) return;
      e.preventDefault();
      var act = btn.getAttribute('data-vpush') || btn.getAttribute('data-vapp');
      btn.disabled = true;
      var run = act === 'on' ? enable() : act === 'off' ? disable() : act === 'test' ? test() : install();
      run.then(function (r) {
        if (act === 'test') { paintAll(r === 'sent' ? S.sent : r === 'wait' ? S.wait : S.error); return; }
        if (act === 'install') { paintAll(); return; }
        last = (r === 'signin') ? 'off' : r;
        paintAll(r === 'on' ? S.on_hint : (r === 'error' ? S.error : ''));
      });
    });
  });
  refresh();

  /* ── Overview nudge ([data-vpush-nudge]) ───────────────────────────── */
  var nudges = [].slice.call(document.querySelectorAll('[data-vpush-nudge]'));
  var nudgeGone = function () { try { return localStorage.getItem('vpush_nudge_x') === '1'; } catch (e) { return false; } };
  if (nudges.length && capable() && B.signedIn && Notification.permission === 'default' && !nudgeGone()) {
    state().then(function (st) {
      if (st !== 'off') return;
      nudges.forEach(function (nd) {
        nd.hidden = false;
        nd.addEventListener('click', function (e) {
          if (e.target.closest('[data-vpush-x]')) {
            try { localStorage.setItem('vpush_nudge_x', '1'); } catch (x) {}
            nd.hidden = true; return;
          }
          var btn = e.target.closest('[data-vpush="on"]');
          if (!btn) return;
          btn.disabled = true;
          enable().then(function (r) {
            var tx = nd.querySelector('.vpush-nudge-tx');
            if (r === 'on') { if (tx) tx.textContent = S.on_hint; btn.hidden = true; setTimeout(function () { nd.hidden = true; }, 4000); }
            else { btn.disabled = false; if (tx) tx.textContent = { denied: S.denied_hint, 'ios-install': S.ios }[r] || S.error; if (r === 'denied') btn.hidden = true; }
          });
        });
      });
    });
  }

  /* ── Homepage app box (index.php) ──────────────────────────────────── */
  var hintEl = document.getElementById('appHint');
  function say(m) { if (hintEl && m) { hintEl.textContent = m; hintEl.style.display = 'inline-block'; } }
  var bA = document.getElementById('btnAndroid'), bI = document.getElementById('btnIos'), bN = document.getElementById('btnNoti');
  if (standalone()) { if (bA) bA.style.display = 'none'; if (bI) bI.style.display = 'none'; }
  if (bA) bA.addEventListener('click', function () {
    install().then(function (r) { if (r === 'unavailable') say(S.and_hint || ''); });
  });
  if (bI) bI.addEventListener('click', function () { say(S.ios_hint || S.ios || ''); });
  if (bN) bN.addEventListener('click', function () {
    bN.disabled = true;
    enable().then(function (r) {
      bN.disabled = false;
      if (r === 'on') { bN.textContent = S.on_short || ('✓ ' + S.on); say(S.on_hint || S.on); }
      else say({ signin: S.signin, denied: S.denied_hint, 'ios-install': S.ios, unsupported: S.unsupported }[r] || S.error);
    });
  });

  /* ── Quiet upkeep on every page ─────────────────────────────────────── */
  /* The session remembers which device it linked, so that signing out can
     unlink it. Sessions come and go (restored from the remember-me cookie),
     so a signed-in page with an unstamped session re-announces the device. */
  if (B.signedIn && !B.stamped && capable() && Notification.permission === 'granted' && !cards.length) {
    currentSub().then(function (sub) { if (sub) post('sync', sub.toJSON()); });
  }
  /* Unread conversations on the installed app's icon, kept in step with the
     header badge the page itself prints. */
  if (B.signedIn && 'setAppBadge' in navigator) {
    try { (B.unread > 0 ? navigator.setAppBadge(B.unread) : navigator.clearAppBadge()).catch(function () {}); } catch (e) {}
  }

  /* Legacy entry point (older inline buttons). */
  window.vestraEnablePush = function () { return enable().then(function (r) { return r === 'on' ? 'ok' : r; }); };
  window.VestraApp = { state: state, enable: enable, disable: disable, test: test, install: install };
})();
