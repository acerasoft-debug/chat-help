#!/usr/bin/env node
/**
 * End-to-end test: boots the real server on a throw-away data dir and walks
 * every user journey through the public API. Zero dependencies. `npm test`.
 */
import { spawn } from 'node:child_process';
import { mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const PORT = 4600 + Math.floor(Math.random() * 300);
const B = `http://127.0.0.1:${PORT}`;
const DATA = mkdtempSync(path.join(tmpdir(), 'lumea-e2e-'));
const ADMIN = 'admin@lumea.test';
const root = new URL('..', import.meta.url).pathname;

const server = spawn(process.execPath, ['server/index.mjs'], {
  cwd: root, stdio: ['ignore', 'pipe', 'pipe'],
  env: { ...process.env, PORT: String(PORT), LUMEA_DATA_DIR: DATA, LUMEA_ADMIN_EMAIL: ADMIN, LUMEA_NO_RATELIMIT: '1', NO_PROXY: '*', no_proxy: '*' }
});
let log = '';
server.stdout.on('data', (d) => (log += d)); server.stderr.on('data', (d) => (log += d));

let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { if (cond) { pass++; console.log('  ✓', name); } else { fail++; console.log('  ✗', name, extra); } };
const jar = {};
async function call(as, method, p, body, raw = false) {
  const res = await fetch(B + '/api' + p, { method, headers: { 'content-type': 'application/json', cookie: jar[as] || '' }, body: body ? JSON.stringify(body) : undefined, redirect: 'manual' });
  const sc = res.headers.get('set-cookie'); if (sc) jar[as] = sc.split(';')[0];
  const text = await res.text();
  let json = null; try { json = JSON.parse(text); } catch { /* raw */ }
  return { status: res.status, json, text, headers: res.headers };
}
const outbox = () => readdirSync(path.join(DATA, 'outbox')).map((f) => ({ f, body: readFileSync(path.join(DATA, 'outbox', f), 'utf8') }));
const tokenFrom = (name) => { const m = outbox().filter((e) => e.f.includes(name)).pop(); return m?.body.match(/token=([A-Za-z0-9_-]+)/)?.[1]; };

for (let i = 0; i < 60; i++) { try { if ((await fetch(B + '/api/health')).ok) break; } catch { /* boot */ } await new Promise((r) => setTimeout(r, 250)); }

try {
  console.log('▶ public');
  let r = await call('anon', 'GET', '/health'); ok('health', r.json?.ok);
  r = await call('anon', 'GET', '/geo'); ok('geo resolves a city', !!r.json?.city);
  ok('security headers', r.headers.get('content-security-policy')?.includes("default-src 'self'") && r.headers.get('x-frame-options') === 'DENY');
  r = await call('anon', 'GET', '/therapists?city=berlin&service=deep-tissue'); ok('matching returns profiles', r.json?.count > 0);
  const seeded = r.json.matches[0];
  r = await fetch(B + '/de/gibtsnicht/'); ok('localised 404 page', r.status === 404 && (await r.text()).includes('lang="de"'));
  r = await fetch(B + '/fr/journal/feed.xml'); ok('journal RSS', r.status === 200 && (await r.text()).includes('<rss'));
  r = await fetch(B + '/.well-known/security.txt'); ok('security.txt', r.status === 200);

  console.log('▶ guest account');
  r = await call('guest', 'POST', '/auth/register', { email: 'clara@lumea.test', password: 'einSicheresPW1', name: 'Clara Vogel', role: 'client', locale: 'de' });
  ok('register', r.status === 201 && r.json.user.emailVerified === false);
  ok('verification mail in outbox', !!tokenFrom('verifyEmail'));
  r = await call('anon', 'GET', `/auth/verify?token=${tokenFrom('verifyEmail')}&locale=de`); ok('verify redirects to account', r.status === 302 && r.headers.get('location').includes('verified=1'));
  r = await call('guest', 'GET', '/auth/me'); ok('email now verified', r.json.user.emailVerified === true);
  r = await call('anon', 'POST', '/auth/forgot', { email: 'clara@lumea.test', locale: 'de' }); ok('forgot always 200', r.status === 200);
  const reset = tokenFrom('resetPassword'); ok('reset mail in outbox', !!reset);
  r = await call('anon', 'POST', '/auth/reset', { token: reset, password: 'neuesPasswort99' }); ok('reset password', r.status === 200);
  r = await call('anon', 'POST', '/auth/reset', { token: reset, password: 'neuesPasswort99' }); ok('reset token single-use', r.status === 410);
  r = await call('guest', 'GET', '/auth/me'); ok('old sessions revoked after reset', r.status === 401);
  r = await call('guest', 'POST', '/auth/login', { email: 'clara@lumea.test', password: 'neuesPasswort99' }); ok('login with new password', r.status === 200);
  r = await call('guest', 'POST', '/auth/password', { current: 'wrong', password: 'abcdefghijk' }); ok('change password rejects wrong current', r.status === 403);
  r = await call('guest', 'POST', '/auth/password', { current: 'neuesPasswort99', password: 'einSicheresPW1' }); ok('change password', r.status === 200);
  r = await call('guest', 'POST', '/favorites', { therapistId: seeded.id }); ok('add favourite', r.json?.saved === true);
  r = await call('guest', 'GET', '/favorites'); ok('list favourites', r.json.favorites.length === 1);

  console.log('▶ vouchers');
  r = await call('anon', 'POST', '/vouchers', { amount: 150, buyerEmail: 'clara@lumea.test', recipientName: 'Mira', message: 'Alles Gute', locale: 'de' });
  ok('buy voucher', r.status === 201 && /^LUMEA-/.test(r.json.code)); const code = r.json.code;
  ok('voucher mail sent', outbox().some((e) => e.f.includes('voucher')));
  r = await call('anon', 'GET', `/vouchers/check?code=${code}`); ok('voucher check', r.json?.balance === 150);
  r = await call('anon', 'GET', '/vouchers/check?code=LUMEA-NOPE-NOPE'); ok('invalid voucher 404', r.status === 404);

  console.log('▶ therapist onboarding');
  r = await call('th', 'POST', '/therapists/apply', { firstName: 'Nuria', lastName: 'Ferrer', email: 'nuria@lumea.test', phone: '+34611', password: 'terapeuta2026x', city: 'berlin', radiusKm: 20, languages: 'Deutsch, Englisch', years: 9, qualification: 'Physiotherapeutin', services: ['deep-tissue', 'lymphatic-drainage'], equipment: ['Mobile Massageliege'], availability: ['Kurzfristig (unter 3 Std.)'], about: 'Neun Jahre Sportklinik.', locale: 'de' });
  ok('application accepted', r.status === 201 && r.json.status === 'pending'); const tid = r.json.applicationId;
  ok('application mail sent', outbox().some((e) => e.f.includes('applicationReceived')));
  r = await call('th', 'POST', '/auth/login', { email: 'nuria@lumea.test', password: 'terapeuta2026x' }); ok('therapist login (pending)', r.status === 200);
  const png = 'data:image/png;base64,' + Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0, 0, 0, 0]).toString('base64');
  for (const type of ['identity', 'qualification', 'insurance']) { r = await call('th', 'POST', '/therapist/documents', { type, filename: `${type}.png`, mime: 'image/png', data: png }); }
  ok('documents uploaded', r.status === 201);
  r = await call('th', 'POST', '/therapist/photo', { mime: 'image/png', data: png }); ok('profile photo upload', r.status === 201);
  r = await call('anon', 'GET', `/therapists/profile?id=${tid}`); ok('pending profile is not public', r.status === 404);

  console.log('▶ booking (pre-approval)');
  r = await call('guest', 'POST', '/bookings', { service: 'deep-tissue', duration: 90, city: 'berlin', address: 'Kurfürstendamm 1', date: '2026-10-01', time: '18:00', name: 'Clara', email: 'clara@lumea.test', phone: '+49', total: '209 €', locale: 'de', therapistId: tid, voucher: code });
  ok('booking with voucher', r.status === 201 && r.json.discount === 150);
  ok('unverified therapist not assignable', r.json.therapistId === null);
  ok('booking mail sent', outbox().some((e) => e.f.includes('bookingRequested')));
  const bid = r.json.bookingId;
  r = await call('anon', 'GET', `/vouchers/check?code=${code}`); ok('voucher balance consumed', r.status === 404);
  r = await call('th', 'POST', '/therapist/accept', { id: bid }); ok('pending therapist cannot accept', r.status === 403);

  console.log('▶ admin verification');
  r = await call('admin', 'POST', '/auth/register', { email: ADMIN, password: 'adminPassw0rd!', name: 'Review Team', locale: 'en' }); ok('admin bootstrap', r.status === 201);
  r = await call('admin', 'GET', '/admin/overview'); ok('admin overview', r.status === 200 && r.json.pendingDocuments.length === 3 && 'escrow' in r.json);
  for (const d of r.json.pendingDocuments) await call('admin', 'POST', '/admin/documents/review', { id: d.id, status: 'approved' });
  ok('document mails sent', outbox().filter((e) => e.f.includes('documentReviewed')).length === 3);
  r = await call('anon', 'GET', `/therapists/profile?id=${tid}`); ok('profile live after approval', r.status === 200 && r.json.profile.verified && r.json.profile.photo === true);
  r = await fetch(`${B}/api/therapists/photo?id=${tid}`); ok('photo served', r.status === 200 && r.headers.get('content-type') === 'image/png');

  console.log('▶ appointment lifecycle');
  r = await call('th', 'POST', '/therapist/accept', { id: bid }); ok('accept booking', r.status === 200);
  ok('confirmation mail sent', outbox().some((e) => e.f.includes('bookingConfirmed')));
  r = await call('guest', 'GET', `/bookings/ics?id=${bid}`); ok('ics export', r.text.includes('BEGIN:VCALENDAR'));
  r = await call('th', 'POST', '/therapist/complete', { id: bid }); ok('complete + payout', r.json?.payout === 'released');
  r = await call('guest', 'POST', '/bookings/review', { id: bid, rating: 5, text: 'Wunderbar, sehr präzise.' }); ok('guest review', r.status === 201);
  r = await call('guest', 'POST', '/bookings/review', { id: bid, rating: 5 }); ok('review once only', r.status === 409);
  r = await call('anon', 'GET', `/therapists/profile?id=${tid}`); ok('review visible on profile', r.json.profile.sampleReviews.some((x) => x.verified && x.text.includes('präzise')) && r.json.profile.reviews === 1);
  r = await call('guest', 'GET', '/bookings'); ok('booking marked reviewed', r.json.bookings[0].reviewed === true && r.json.bookings[0].discount === 150);

  console.log('▶ privé + GDPR');
  r = await call('guest', 'POST', '/prive/subscribe', { tier: 'signature' }); ok('privé subscribe', r.json?.tier === 'signature');
  r = await call('guest', 'GET', '/auth/me'); ok('membership on user', r.json.user.priveTier === 'signature');
  r = await call('guest', 'POST', '/prive/cancel'); ok('privé cancel', r.status === 200);
  r = await call('guest', 'GET', '/auth/export'); ok('data export', r.status === 200 && r.json.bookings.length === 1 && r.json.reviews.length === 1);
  r = await call('guest', 'POST', '/auth/delete', { password: 'wrong' }); ok('delete needs password', r.status === 403);
  r = await call('guest', 'POST', '/auth/delete', { password: 'einSicheresPW1' }); ok('account erased', r.status === 200);
  r = await call('anon', 'POST', '/auth/login', { email: 'clara@lumea.test', password: 'einSicheresPW1' }); ok('erased account cannot sign in', r.status === 401);
  r = await call('anon', 'GET', `/therapists/reviews?id=${tid}`); ok('review anonymised, kept', r.json.reviews[0].name === '—');
} catch (err) {
  fail++; console.log('  ✗ crashed:', err.message);
} finally {
  server.kill();
  rmSync(DATA, { recursive: true, force: true });
}
console.log(`\n${pass} passed, ${fail} failed`);
if (fail) { console.log(log.slice(-2000)); process.exit(1); }
