/*
 * SARVESTO — servis çalışanı (uygulama kabuğu)
 *
 * Amaç hız ve "uygulama gibi" davranış, verinin kopyası DEĞİL:
 *  - Sayfalar her zaman ağdan gelir (fiyat, stok, Vault fiyatı canlı kalsın).
 *    Ağ yoksa markalı çevrimdışı sayfa gösterilir.
 *  - Sürüm parametreli CSS/JS ve uygulama ikonları önbellekten gelir.
 *  - Sepet, kasa, hesap, satıcı, yönetim ve API isteklerine HİÇ dokunulmaz.
 */
'use strict';

const VERSION = 'sv-2026-10-10';
const SHELL   = 'shell-' + VERSION;
const SCOPE   = new URL(self.registration.scope).pathname;          // "/" ya da "/shop/"
const OFFLINE = SCOPE + 'offline.html';
const PRECACHE = [OFFLINE, SCOPE + 'assets/app/icon-192.png', SCOPE + 'assets/app/icon-512.png'];

const PRIVATE = /\/(cart|checkout|order|wishlist|account|seller|admin|api|newsletter)(\.php|\/|$)/;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(SHELL).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== SHELL).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (PRIVATE.test(url.pathname)) return;

  // Sayfa gezinmesi: önce ağ; ağ yoksa çevrimdışı sayfa.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  // Sürümlü stil/betik ve uygulama ikonları: önce önbellek.
  if (/\/assets\/(css|js|app)\//.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) { const copy = res.clone(); caches.open(SHELL).then((c) => c.put(req, copy)); }
        return res;
      }))
    );
  }
});
