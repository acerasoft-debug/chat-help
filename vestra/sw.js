/* VESTRA service worker — makes the site installable (PWA) and handles Web Push.
   Deliberately small: no HTML caching (the marketplace is dynamic and session-
   based), a tiny static cache for the offline page and icons, and the push
   pipeline.

   v3 (27 Sep 2026): the notification now arrives INSIDE the push, encrypted to
   this device (see inc/push.php). v2 woke up and asked the server "what's
   pending?" — two devices raced for one queue, and a signed-out or offline-for-
   an-hour device showed a vague English line instead of the order.

   Bump CACHE whenever the STATIC list or offline.html changes — activate deletes
   every other cache, so the bump is what actually retires the old one. */
const CACHE = 'vestra-v3';
const OFFLINE = '/offline.html';
const BADGE = '/icon-badge-96.png';
const STATIC = ['/favicon.svg', '/icon-192.png', '/icon-512.png', BADGE, OFFLINE];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
    /* Navigation preload: the page request starts while this worker is still
       booting, instead of after — the worker otherwise adds its own start-up
       time to every page view of the installed app. */
    if (self.registration.navigationPreload) {
      try { await self.registration.navigationPreload.enable(); } catch (err) {}
    }
    await self.clients.claim();
  })());
});

/* Page loads stay network-first and are never cached — prices, stock and session
   state must not be served from memory. The only thing kept for offline is the
   fallback page, so a dead connection shows VESTRA rather than the browser's
   error screen. Installed to the home screen, that error screen is what a user
   reads as "the app is broken". */
self.addEventListener('fetch', (e) => {
  if (e.request.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        const pre = await e.preloadResponse;
        if (pre) return pre;
        return await fetch(e.request);
      } catch (err) {
        return (await caches.match(OFFLINE)) || Response.error();
      }
    })());
    return;
  }

  const url = new URL(e.request.url);
  if (e.request.method === 'GET' && url.origin === location.origin && url.search === '' && STATIC.includes(url.pathname)) {
    e.respondWith(
      caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(e.request, copy)).catch(() => {});
        }
        return res;
      }))
    );
  }
});

/* ── Push ──────────────────────────────────────────────────────────────── */

/* Only ever open a page of THIS site — a notification is a link the user trusts. */
function safeUrl(u) {
  try {
    if (typeof u !== 'string' || u[0] !== '/' || u.startsWith('//') || u.startsWith('/\\')) return '/';
    const full = new URL(u, self.location.origin);
    return full.origin === self.location.origin ? full.pathname + full.search + full.hash : '/';
  } catch (err) { return '/'; }
}

/* Last resort when a push carried nothing readable and the device queue was
   empty too. The browser only keeps trusting a site that shows something for
   every push, so something is shown — in the device's language, not English. */
const NEWS = {
  en: 'You have news on VESTRA.', de: 'Es gibt Neuigkeiten auf VESTRA.', fr: 'Du nouveau sur VESTRA.',
  it: 'Ci sono novità su VESTRA.', es: 'Tienes novedades en VESTRA.', pt: 'Tem novidades na VESTRA.',
  ru: 'На VESTRA есть новости.', ar: 'لديك مستجدات على VESTRA.', ja: 'VESTRAにお知らせがあります。',
};
function genericNotice() {
  const l = String((self.navigator && self.navigator.language) || 'en').slice(0, 2).toLowerCase();
  return { title: 'VESTRA', body: NEWS[l] || NEWS.en, url: '/', lang: NEWS[l] ? l : 'en', dir: l === 'ar' ? 'rtl' : 'ltr' };
}

function b64u(buf) {
  if (!buf) return '';
  let s = ''; const b = new Uint8Array(buf);
  for (let i = 0; i < b.length; i++) s += String.fromCharCode(b[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
function b64uToBytes(s) {
  const pad = '='.repeat((4 - (s.length % 4)) % 4);
  const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

/* Only reached when a push came without a payload: the server queued the
   notification for this device (identified by its endpoint + its own secret). */
async function fetchQueued() {
  try {
    const sub = await self.registration.pushManager.getSubscription();
    if (!sub) return [];
    const r = await fetch('/push?a=pending', {
      method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ endpoint: sub.endpoint, auth: b64u(sub.getKey && sub.getKey('auth')) }),
    });
    if (!r.ok) return [];
    return (await r.json()).notifs || [];
  } catch (err) { return []; }
}

async function show(n) {
  const opts = {
    body: n.body || '',
    icon: '/icon-192.png',
    badge: BADGE,
    timestamp: n.ts || Date.now(),
    dir: n.dir === 'rtl' ? 'rtl' : (n.dir === 'ltr' ? 'ltr' : 'auto'),
    data: { url: safeUrl(n.url), kind: n.kind || '' },
  };
  if (n.lang) opts.lang = n.lang;
  /* One notification per conversation / order: a new message in the same thread
     replaces the last one (and still rings — renotify), instead of stacking. */
  if (n.tag) { opts.tag = n.tag; opts.renotify = true; }
  await self.registration.showNotification(n.title || 'VESTRA', opts);
  /* App-icon badge = unread conversations (installed app; ignored elsewhere). */
  if (typeof n.unread === 'number' && self.navigator && 'setAppBadge' in self.navigator) {
    try { n.unread > 0 ? await self.navigator.setAppBadge(n.unread) : await self.navigator.clearAppBadge(); } catch (err) {}
  }
}

self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let list = [];
    if (e.data) {
      try { const j = e.data.json(); list = Array.isArray(j) ? j : [j]; } catch (err) { list = []; }
      list = list.filter((n) => n && typeof n === 'object' && (n.title || n.body));
    }
    if (!list.length) list = await fetchQueued();
    if (!list.length) list = [genericNotice()];
    for (const n of list.slice(-3)) await show(n);
  })());
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = new URL(safeUrl(e.notification.data && e.notification.data.url), self.location.origin).href;
  e.waitUntil((async () => {
    const wins = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) if (w.url === target && 'focus' in w) return w.focus();
    for (const w of wins) {
      if (new URL(w.url).origin !== self.location.origin) continue;
      /* navigate() only works on pages this worker controls; on any other it
         throws — v2 then did nothing at all and the tap looked dead. */
      try { const nw = await w.navigate(target); if (nw) return nw.focus(); } catch (err) {}
      break;
    }
    return clients.openWindow(target);
  })());
});

/* The browser may replace a subscription (key rotation, expiry). Without this the
   server kept posting to the dead endpoint and the device went silent for good.
   The old subscription's own secret proves which account it belonged to. */
self.addEventListener('pushsubscriptionchange', (e) => {
  e.waitUntil((async () => {
    try {
      let sub = e.newSubscription;
      if (!sub) {
        const k = await (await fetch('/push?a=vapid', { cache: 'no-store' })).json();
        if (!k.publicKey) return;
        sub = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(k.publicKey) });
      }
      const old = e.oldSubscription;
      await fetch('/push?a=renew', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sub: sub.toJSON(),
          old: old ? { endpoint: old.endpoint, auth: b64u(old.getKey && old.getKey('auth')) } : null,
        }),
      });
    } catch (err) {}
  })());
});
