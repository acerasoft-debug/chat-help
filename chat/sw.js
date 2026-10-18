/* ChatHelp Business — Service Worker (Scope /chat/)
   Push-Benachrichtigungen (Web Push, verschlüsselt nach RFC 8291), Klick → passendes Fenster öffnen,
   Abo-Erneuerung (pushsubscriptionchange) und Offline-Seite für fehlgeschlagene Navigationen.
   Business-App (/chat/app.php): die HTML-Hülle (ohne Kundendaten) + app-bridge.js werden nach jedem erfolgreichen Laden
   zwischengespeichert, damit die App auch offline startet; Tipp auf eine Meldung öffnet – wenn die App auf diesem Gerät
   genutzt wird – direkt den passenden Bereich der App statt des Studios.
   API-Antworten werden NIE zwischengespeichert – nur offline.html, die App-Hülle und die App-Symbole. */
'use strict';
const SW_VERSION = 'chathelp-sw-2026-10-18.1';
const SHELL_CACHE = 'chathelp-shell-' + SW_VERSION;
const META_CACHE = 'chathelp-push-meta';                 // gespeicherter VAPID-Schlüssel + aktuelles Abo (für pushsubscriptionchange)
const META_KEY = '/chat/__push-meta.json';
const OFFLINE_URL = '/chat/offline.html?v=' + encodeURIComponent(SW_VERSION);
const APP_CACHE = 'chathelp-app-' + SW_VERSION;          // Hülle der Business-App (HTML ohne Daten) + app-bridge.js
const APP_PATH = '/chat/app.php';
const APP_SHELL_KEY = '/chat/__app-shell.html';
const APP_BRIDGE_KEY = '/chat/__app-bridge.js';
/* Studio-Bereich → Bereich der Business-App (für Tipps auf Meldungen, wenn die App auf diesem Gerät genutzt wird) */
const APP_ROUTES = { home: 'heute', heute: 'heute', phone: 'heute', bestellungen: 'bestellungen', termine: 'termine', taxi: 'taxi', hotel: 'hotel',
  rueckruf: 'rueckruf', kunden: 'kunden', benachrichtigungen: 'benachrichtigungen', whatsapp: 'kunden' };
const ICON = '/chat/icons/icon-192.png';
const BADGE = '/chat/icons/badge-96.png';
const DEFAULT_URL = '/chat/studio.php#benachrichtigungen';
/* Dringende Ereignisse: erneut klingeln (renotify) und stehen lassen, bis jemand reagiert */
const URGENT = ['order_new', 'ride_new', 'booking_new', 'hotel_booking'];
const STICKY = ['order_new', 'ride_new'];
const LABELS = {
  order_new: 'Neue Bestellung', order_cancel: 'Bestellung storniert', booking_new: 'Neuer Termin', booking_cancel: 'Termin abgesagt',
  ride_new: 'Neue Taxifahrt', ride_cancel: 'Fahrt storniert', hotel_booking: 'Neue Zimmerbuchung', hotel_request: 'Gästewunsch',
  callback_new: 'Rückrufwunsch', lead_new: 'Neuer Lead', call_done: 'Anruf beendet', tokens_low: 'Guthaben niedrig', system: 'ChatHelp'
};

/* ---------- Lebenszyklus ---------- */
self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    try {
      const c = await caches.open(SHELL_CACHE);
      await c.addAll([new Request(OFFLINE_URL, { cache: 'reload' }), ICON, BADGE]);
    } catch (e) { /* offline beim Installieren: Push funktioniert trotzdem */ }
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => (k.startsWith('chathelp-shell-') && k !== SHELL_CACHE) || (k.startsWith('chathelp-app-') && k !== APP_CACHE)).map((k) => caches.delete(k)));
    try { if (self.registration.navigationPreload) await self.registration.navigationPreload.enable(); } catch (e) {}
    await self.clients.claim();
  })());
});

/* ---------- Netzwerk: nur Navigationen (+ app-bridge.js der App) anfassen, sonst nichts (keine API-Antworten im Cache) ---------- */
async function putApp(key, res) {
  try { if (res && res.ok && res.status === 200 && res.type === 'basic') { const c = await caches.open(APP_CACHE); await c.put(key, res); } } catch (e) {}
}
async function matchApp(key) { try { const c = await caches.open(APP_CACHE); return (await c.match(key)) || null; } catch (e) { return null; } }
function offlineResponse() {
  return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="font-family:system-ui,sans-serif;padding:40px;color:#0f1222;background:#f6f7fb"><h1 style="font-family:Georgia,serif">Keine Verbindung</h1><p>Bitte Internetverbindung prüfen und erneut versuchen.</p><button onclick="location.reload()">Erneut versuchen</button>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } });
}
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith('/chat/')) return;
  /* app-bridge.js für die Business-App: Netz zuerst, offline aus dem App-Cache (andere Seiten laden sie wie bisher direkt) */
  if (req.mode !== 'navigate') {
    let ref = ''; try { ref = req.referrer ? new URL(req.referrer).pathname : ''; } catch (e) {}
    if (url.pathname !== '/chat/app-bridge.js' || ref !== APP_PATH) return;
    event.respondWith((async () => {
      try { const res = await fetch(req); event.waitUntil(putApp(APP_BRIDGE_KEY, res.clone())); return res; }
      catch (e) { const hit = await matchApp(APP_BRIDGE_KEY); if (hit) return hit; throw e; }
    })());
    return;
  }
  const isApp = url.pathname === APP_PATH;
  event.respondWith((async () => {
    try {
      const pre = await event.preloadResponse;
      const res = pre || await fetch(req);
      if (isApp) event.waitUntil(putApp(APP_SHELL_KEY, res.clone()));   // Hülle ohne Daten – Inhalte lädt die App selbst über die API
      return res;
    } catch (e) {
      if (isApp) { const shell = await matchApp(APP_SHELL_KEY); if (shell) return shell; }
      const c = await caches.open(SHELL_CACHE);
      const off = await c.match(OFFLINE_URL);
      return off || offlineResponse();
    }
  })());
});

/* ---------- Push ---------- */
function parsePush(event) {
  let d = {};
  if (event.data) {
    try { d = event.data.json() || {}; } catch (e) { try { d = { body: event.data.text() }; } catch (e2) { d = {}; } }
  }
  if (typeof d !== 'object' || d === null) d = { body: String(d) };
  const ev = typeof d.event === 'string' ? d.event : 'system';
  return {
    title: String(d.title || LABELS[ev] || 'ChatHelp').slice(0, 120),
    body: String(d.body || '').slice(0, 600),
    url: typeof d.url === 'string' && d.url ? d.url : DEFAULT_URL,
    tag: typeof d.tag === 'string' ? d.tag : '',
    event: ev,
    id: Number(d.id) || 0,
    badge: Number.isFinite(Number(d.badge)) ? Number(d.badge) : null,
    icon: typeof d.icon === 'string' && d.icon.startsWith('/') ? d.icon : ICON,
    urgency: d.urgency || '',
    silent: !!d.silent,
    ts: Number(d.ts) || Date.now()
  };
}

function safeUrl(u) {
  try {
    const x = new URL(u || DEFAULT_URL, self.location.origin);
    return x.origin === self.location.origin ? x.href : new URL(DEFAULT_URL, self.location.origin).href;
  } catch (e) { return new URL(DEFAULT_URL, self.location.origin).href; }
}

async function setBadge(n) {
  try {
    const nav = self.navigator;
    if (n === null || n === undefined) return;
    if (n > 0 && nav.setAppBadge) await nav.setAppBadge(n);
    else if (n === 0 && nav.clearAppBadge) await nav.clearAppBadge();
  } catch (e) {}
}

async function tellClients(msg) {
  try {
    const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    list.forEach((c) => { try { c.postMessage(msg); } catch (e) {} });
  } catch (e) {}
}

async function showPush(p) {
  const urgent = URGENT.indexOf(p.event) >= 0 || p.urgency === 'high';
  const tag = p.tag || (urgent ? '' : 'ch-' + p.event);
  const opts = {
    body: p.body,
    icon: p.icon,
    badge: BADGE,
    lang: 'de',
    dir: 'ltr',
    timestamp: p.ts,
    silent: p.silent,
    requireInteraction: STICKY.indexOf(p.event) >= 0,
    data: { url: safeUrl(p.url), event: p.event, id: p.id, tag: p.tag },
    actions: [{ action: 'open', title: 'Öffnen' }]
  };
  if (tag) { opts.tag = tag; opts.renotify = true; }   // mit tag immer erneut melden – sonst ersetzt die zweite Meldung die erste lautlos
  if (!p.silent) opts.vibrate = urgent ? [260, 120, 260, 120, 520] : [180, 90, 180];
  try {
    await self.registration.showNotification(p.title, opts);
  } catch (e) {
    /* ältere Browser: ohne actions/vibrate erneut versuchen – eine sichtbare Meldung ist Pflicht (userVisibleOnly) */
    delete opts.actions; delete opts.vibrate;
    await self.registration.showNotification(p.title, opts);
  }
}

self.addEventListener('push', (event) => {
  const p = parsePush(event);
  event.waitUntil(Promise.all([
    showPush(p),
    setBadge(p.badge),
    tellClients({ type: 'chathelp-push', payload: p })
  ]));
});

/* ---------- Klick: vorhandenes Fenster fokussieren und dorthin navigieren, sonst neues Fenster ---------- */
/** Nutzt dieses Gerät die Business-App (app.php meldet sich per „chathelp-shell“), führen Studio-Links in den passenden App-Bereich. */
function appTarget(target, data, meta) {
  try {
    if (!meta || meta.shell !== 'app') return target;
    const u = new URL(target);
    if (u.origin !== self.location.origin || u.pathname !== '/chat/studio.php') return target;
    const r = APP_ROUTES[(u.hash || '').slice(1)] || (u.hash ? '' : 'heute');
    if (!r) return target;   // z. B. Tokens, Agenten → bleibt im Studio
    const q = new URLSearchParams({ src: 'push' });
    if (data && data.tag) q.set('tag', String(data.tag).slice(0, 64));
    if (data && Number(data.id) > 0) q.set('nid', String(Number(data.id)));
    return new URL(APP_PATH + '?' + q.toString() + '#' + r, self.location.origin).href;
  } catch (e) { return target; }
}
async function openFromNotification(data) {
  const target = appTarget(safeUrl(data && data.url), data, await readMeta());
  const t = new URL(target);
  const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  const sameOrigin = list.filter((c) => { try { return new URL(c.url).origin === t.origin; } catch (e) { return false; } });
  /* bevorzugt: ein Fenster mit derselben Seite, dann ein beliebiges ChatHelp-Fenster (/chat/), dann irgendeins der Domain */
  const pick = sameOrigin.find((c) => new URL(c.url).pathname === t.pathname)
    || sameOrigin.find((c) => new URL(c.url).pathname.startsWith('/chat/'))
    || sameOrigin[0];
  if (pick) {
    let c = pick;
    try { c = (await pick.focus()) || pick; } catch (e) { /* Fokus nur mit Nutzer-Klick erlaubt */ }
    /* 1. die Seite selbst wechseln lassen (app-bridge.js: gleicher Tab → nur Bereich wechseln, ohne Neuladen) */
    if (await askClient(c, { type: 'chathelp-notification-click', navigate: true, data: Object.assign({}, data, { url: target }) }, 900)) return c;
    /* 2. ohne app-bridge: das gesteuerte Fenster navigieren */
    try {
      if (c.url === target) return c;
      if ('navigate' in c) { const n = await c.navigate(target); if (n) return n; }
    } catch (e) { /* nicht von diesem Worker gesteuert → neues Fenster */ }
  }
  /* 3. neues Fenster bzw. die installierte App öffnen */
  if (self.clients.openWindow) return self.clients.openWindow(target);
  return null;
}
/** Nachricht an ein Fenster mit Antwortkanal; true = die Seite hat die Navigation übernommen. */
function askClient(client, msg, ms) {
  return new Promise((resolve) => {
    let done = false;
    const finish = (v) => { if (!done) { done = true; resolve(v); } };
    const timer = setTimeout(() => finish(false), ms);
    try {
      const ch = new MessageChannel();
      ch.port1.onmessage = (e) => { clearTimeout(timer); finish(!!(e.data && e.data.ok)); };
      client.postMessage(msg, [ch.port2]);
    } catch (e) { clearTimeout(timer); finish(false); }
  });
}
self.chOpenFromNotification = openFromNotification;   // auch für Selbsttests im Worker-Kontext nutzbar

self.addEventListener('notificationclick', (event) => {
  const n = event.notification;
  const data = Object.assign({}, n.data || {});
  n.close();
  event.waitUntil(openFromNotification(data));
});

self.addEventListener('notificationclose', (event) => {
  /* nichts zu tun – das Postfach im Studio bleibt die verlässliche Liste */
});

/* ---------- Abo erneuert (Browser hat die Push-Adresse gewechselt) ---------- */
async function readMeta() {
  try { const c = await caches.open(META_CACHE); const r = await c.match(META_KEY); return r ? await r.json() : {}; } catch (e) { return {}; }
}
async function writeMeta(m) {
  try {
    const c = await caches.open(META_CACHE);
    await c.put(META_KEY, new Response(JSON.stringify(m), { headers: { 'Content-Type': 'application/json' } }));
  } catch (e) {}
}
function b64uToBytes(s) {
  const pad = '='.repeat((4 - (s.length % 4)) % 4);
  const b = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
  const out = new Uint8Array(b.length);
  for (let i = 0; i < b.length; i++) out[i] = b.charCodeAt(i);
  return out;
}
function bytesToB64u(buf) {
  const a = new Uint8Array(buf); let s = '';
  for (let i = 0; i < a.length; i++) s += String.fromCharCode(a[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
async function vapidKey(meta) {
  if (meta && meta.vapid) return meta.vapid;
  try {
    const r = await fetch('/chat/push-sub.php?vapid=1&_=' + Date.now(), { cache: 'no-store' });
    const j = await r.json(); return j.vapid_public || '';
  } catch (e) { return ''; }
}

self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    const meta = await readMeta();
    const old = event.oldSubscription || null;
    const oldEndpoint = (old && old.endpoint) || meta.endpoint || '';
    let oldAuth = meta.auth || '';
    try { if (old && old.getKey && old.getKey('auth')) oldAuth = bytesToB64u(old.getKey('auth')); } catch (e) {}
    let sub = event.newSubscription || null;
    if (!sub) {
      const key = await vapidKey(meta);
      if (!key) return;
      try { sub = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(key) }); } catch (e) { return; }
    }
    const json = sub.toJSON ? sub.toJSON() : sub;
    if (oldEndpoint && oldAuth && oldEndpoint !== json.endpoint) {   // ohne bekanntes Auth-Secret kein Umzug – die App meldet das Gerät dann neu an
      try {
        await fetch('/chat/push-sub.php?resub=1', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, cache: 'no-store',
          body: JSON.stringify({ old_endpoint: oldEndpoint, old_auth: oldAuth, subscription: json })
        });
      } catch (e) {}
    }
    await writeMeta(Object.assign({}, meta, { endpoint: json.endpoint, auth: (json.keys && json.keys.auth) || '', updated: Date.now() }));
  })());
});

/* ---------- Nachrichten der Seite (app-bridge.js) ---------- */
self.addEventListener('message', (event) => {
  const m = event.data || {};
  if (m.type === 'chathelp-push-config') {
    event.waitUntil(readMeta().then((old) => writeMeta(Object.assign({}, old, {
      vapid: String(m.vapid || old.vapid || ''), endpoint: String(m.endpoint || ''), auth: String(m.auth || ''), updated: Date.now()
    }))));
  } else if (m.type === 'chathelp-shell') {
    /* app.php: „dieses Gerät nutzt die Business-App“ (shell:'app') bzw. zurück zum Studio (shell:'') */
    event.waitUntil(readMeta().then((old) => writeMeta(Object.assign({}, old, { shell: m.shell === 'app' ? 'app' : '', shellAt: Date.now() }))));
  } else if (m.type === 'chathelp-badge') {
    event.waitUntil(setBadge(Math.max(0, Number(m.count) || 0)));
  } else if (m.type === 'chathelp-skip-waiting') {
    self.skipWaiting();
  } else if (m.type === 'chathelp-version' && event.source) {
    try { event.source.postMessage({ type: 'chathelp-version', version: SW_VERSION }); } catch (e) {}
  }
});
