/* VESTRA service worker — makes the site installable (PWA) and handles Web Push.
   Deliberately minimal: no HTML caching (the marketplace is dynamic/session-based),
   just a tiny static-asset cache + the push → fetch-pending → notify pipeline. */
const CACHE = 'vestra-v1';
const STATIC = ['/inc/style.css', '/favicon.svg', '/icon-192.png', '/icon-512.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

/* Cache-first for the few static assets above; everything else straight to network. */
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method === 'GET' && url.origin === location.origin && STATIC.includes(url.pathname)) {
    e.respondWith(
      caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy)).catch(() => {});
        return res;
      }))
    );
  }
});

/* Payloadless push: wake up, ask the server what's pending for THIS signed-in
   user (session cookie travels with the same-origin fetch), show it. Always
   shows something — required so the browser keeps trusting our pushes. */
self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let notifs = [];
    try {
      const r = await fetch('/push?a=pending', { cache: 'no-store' });
      if (r.ok) notifs = (await r.json()).notifs || [];
    } catch (err) {}
    if (!notifs.length) notifs = [{ title: 'VESTRA', body: 'You have news on VESTRA.', url: '/' }];
    for (const n of notifs.slice(-3)) {
      await self.registration.showNotification(n.title || 'VESTRA', {
        body: n.body || '',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        data: { url: n.url || '/' },
        tag: 'vestra-' + (n.url || 'news'),
      });
    }
  })());
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil((async () => {
    const wins = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) {
      if (new URL(w.url).origin === location.origin) { w.focus(); w.navigate(target); return; }
    }
    await clients.openWindow(target);
  })());
});
