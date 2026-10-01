/* Runs the REAL vestra/sw.js in a Node VM with a fake worker global and prints one
   "ok|FAIL <claim>" line per assertion (tests/push_app_test.php reads them).
   Reading sw.js as text proves nothing about what it does when a push arrives;
   this dispatches the events and looks at what the worker actually shows/opens. */
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', 'vestra', 'sw.js'), 'utf8');

function world(opts = {}) {
  const handlers = {};
  const shown = [], opened = [], fetched = [];
  let badge = null;
  const sub = opts.sub === undefined ? {
    endpoint: 'https://fcm.googleapis.com/fcm/send/dev1',
    getKey: (k) => (k === 'auth' ? new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]).buffer : null),
  } : opts.sub;
  const self = {
    location: { origin: 'https://vestrasales.com' },
    navigator: { language: opts.lang || 'de-DE', setAppBadge: async (n) => { badge = n; }, clearAppBadge: async () => { badge = 0; } },
    addEventListener: (t, f) => { handlers[t] = f; },
    skipWaiting: () => {},
    clients: { claim: async () => {} },
    registration: {
      showNotification: async (title, o) => { shown.push({ title, ...o }); },
      pushManager: { getSubscription: async () => sub },
      navigationPreload: { enable: async () => {} },
    },
  };
  const ctx = {
    self, console, URL, Promise, JSON, Date, Uint8Array, Array, String, Object,
    btoa: (s) => Buffer.from(s, 'binary').toString('base64'),
    atob: (s) => Buffer.from(s, 'base64').toString('binary'),
    location: { origin: 'https://vestrasales.com' },
    caches: { open: async () => ({ addAll: async () => {}, put: async () => {} }), keys: async () => [], match: async () => null, delete: async () => true },
    clients: {
      matchAll: async () => opts.wins || [],
      openWindow: async (u) => { opened.push(u); return null; },
    },
    fetch: async (u, init) => { fetched.push({ u, init }); return opts.fetchResp ? opts.fetchResp(u, init) : { ok: false, json: async () => ({}) }; },
    Response: { error: () => 'ERR' },
  };
  ctx.addEventListener = self.addEventListener;
  vm.createContext(ctx);
  vm.runInContext(src, ctx);
  return { handlers, shown, opened, fetched, badge: () => badge };
}
async function fire(w, type, ev) {
  let p = null;
  ev.waitUntil = (x) => { p = x; };
  w.handlers[type](ev);
  if (p) await p;
}
const out = (ok, claim) => console.log((ok ? 'ok   ' : 'FAIL ') + claim);

(async () => {
  // 1) payload push → shown exactly as sent, badge icon + tag + renotify + lang/dir
  let w = world();
  const n = { title: 'Neue Bestellung VES-1', body: 'Boutique · 24 Stk. · 1.234,50 €', url: '/seller?tab=orders&view=VES-1',
              tag: 'order-VES-1', lang: 'de', dir: 'ltr', ts: 1790000000000, unread: 3 };
  await fire(w, 'push', { data: { json: () => n } });
  const s = w.shown[0] || {};
  out(w.shown.length === 1 && s.title === n.title && s.body === n.body, 'payload: title/body shown as sent');
  out(s.badge === '/icon-badge-96.png', 'payload: monochrome badge icon');
  out(s.tag === 'order-VES-1' && s.renotify === true, 'payload: tag + renotify');
  out(s.lang === 'de' && s.dir === 'ltr' && s.timestamp === n.ts, 'payload: lang/dir/timestamp');
  out(s.data && s.data.url === '/seller?tab=orders&view=VES-1', 'payload: deep link kept');
  out(w.fetched.length === 0, 'payload: NO server round-trip when the push carries the text');
  out(w.badge() === 3, 'payload: app-icon badge set to unread count');

  // 2) Arabic direction
  w = world();
  await fire(w, 'push', { data: { json: () => ({ title: 'تم شحن الطلبية', body: 'x', dir: 'rtl', lang: 'ar' }) } });
  out((w.shown[0] || {}).dir === 'rtl', 'rtl direction passed to the notification');

  // 3) no payload → device queue asked with endpoint + auth (POST), then shown
  w = world({ fetchResp: (u, init) => ({ ok: true, json: async () => ({ notifs: [{ title: 'Q', body: 'queued', url: '/buyer' }] }) }) });
  await fire(w, 'push', { data: null });
  const f = w.fetched[0] || {};
  let body = {}; try { body = JSON.parse((f.init || {}).body || '{}'); } catch (e) {}
  out(f.u === '/push?a=pending' && (f.init || {}).method === 'POST', 'no payload: POST /push?a=pending');
  out(body.endpoint === 'https://fcm.googleapis.com/fcm/send/dev1' && typeof body.auth === 'string' && body.auth.length === 22, 'no payload: sends its endpoint + auth secret');
  out((w.shown[0] || {}).body === 'queued', 'no payload: queued notification shown');

  // 4) nothing anywhere → localized generic line (device language), never empty
  w = world({ lang: 'fr-FR' });
  await fire(w, 'push', { data: null });
  out((w.shown[0] || {}).body === 'Du nouveau sur VESTRA.', 'fallback: device language (fr), not English');
  w = world({ lang: 'xx' });
  await fire(w, 'push', { data: null });
  out((w.shown[0] || {}).body === 'You have news on VESTRA.', 'fallback: English for an unknown language');

  // 5) malicious / broken URLs never leave the site
  for (const bad of ['https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', 42]) {
    w = world();
    await fire(w, 'push', { data: { json: () => ({ title: 't', body: 'b', url: bad }) } });
    out(((w.shown[0] || {}).data || {}).url === '/', 'url sanitized: ' + String(bad));
  }

  // 6) click: existing controlled window navigated+focused; uncontrolled → openWindow
  let navigated = null, focused = 0;
  const ctrl = { url: 'https://vestrasales.com/shop', focus: async () => { focused++; return ctrl; },
                 navigate: async (u) => { navigated = u; return ctrl; } };
  w = world({ wins: [ctrl] });
  await fire(w, 'notificationclick', { notification: { close() {}, data: { url: '/buyer?tab=orders&view=VES-1' } } });
  out(navigated === 'https://vestrasales.com/buyer?tab=orders&view=VES-1' && focused === 1, 'click: navigates the open window to the deep link');
  const unctrl = { url: 'https://vestrasales.com/shop', focus: async () => unctrl, navigate: async () => { throw new TypeError('not controlled'); } };
  w = world({ wins: [unctrl] });
  await fire(w, 'notificationclick', { notification: { close() {}, data: { url: '/buyer' } } });
  out(w.opened[0] === 'https://vestrasales.com/buyer', 'click: uncontrolled window → opens a window instead of doing nothing');
  w = world();
  await fire(w, 'notificationclick', { notification: { close() {}, data: { url: '//evil.example' } } });
  out(w.opened[0] === 'https://vestrasales.com/', 'click: foreign URL opens the home page');

  // 7) subscription rotated → renew with old endpoint + old auth
  const newSub = { toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/dev2', keys: { p256dh: 'P', auth: 'A' } }) };
  const oldSub = { endpoint: 'https://fcm.googleapis.com/fcm/send/dev1', getKey: () => new Uint8Array(16).buffer };
  w = world({ fetchResp: () => ({ ok: true, json: async () => ({ ok: true }) }) });
  await fire(w, 'pushsubscriptionchange', { newSubscription: newSub, oldSubscription: oldSub });
  const r = w.fetched.find((x) => x.u === '/push?a=renew') || {};
  let rb = {}; try { rb = JSON.parse((r.init || {}).body || '{}'); } catch (e) {}
  out(rb.sub && rb.sub.endpoint.endsWith('/dev2') && rb.old && rb.old.endpoint.endsWith('/dev1') && rb.old.auth === 'AAAAAAAAAAAAAAAAAAAAAA', 'renew: new sub + old endpoint/auth posted');
})().catch((e) => { out(false, 'sw_check crashed: ' + e.message); });
