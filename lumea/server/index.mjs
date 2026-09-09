#!/usr/bin/env node
/**
 * LUMÉA application server.
 * Serves the generated static site from dist/ and the JSON API under /api.
 * No framework, no dependencies — Node's own http, sqlite and crypto only.
 */
import http from 'node:http';
import { createReadStream } from 'node:fs';
import { stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { one, all, run, id, now } from './db.mjs';
import {
  createUser, findUserByEmail, verifyPassword, createSession, sessionUser, destroySession,
  listSessions, publicUser, isThrottled, recordAttempt, issueToken, consumeToken, setPassword, hasSessionFromIp
} from './auth.mjs';
import { sendMail, link as mailLink } from './mail.mjs';
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { resolveGeo, clientIp, anonymiseIp } from './geo.mjs';
import { matchTherapists } from './matching.mjs';
import { seedTherapists } from './seed.mjs';
import { storeDocument, checklist, reviewDocument, pendingDocuments, documentFile, DOC_TYPES, REQUIRED } from './documents.mjs';
import { createReadStream as streamFile } from 'node:fs';
import { cityBySlug } from '../data/cities.mjs';
import { profileExtras } from '../data/therapists.mjs';
import { serviceBySlug } from '../data/services.mjs';
import { priveTiers } from '../data/prive.mjs';
import { site } from '../data/site.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DIST = path.resolve(__dirname, '../dist');
const PORT = Number(process.env.PORT || 4477);
const HOST = process.env.HOST || '0.0.0.0';
const COOKIE = 'lumea_session';
const LOCALES = ['de', 'en', 'es', 'fr', 'it'];
const SECURE = process.env.NODE_ENV === 'production';
const ADMIN_EMAILS = String(process.env.LUMEA_ADMIN_EMAIL || '').toLowerCase().split(',').map((e) => e.trim()).filter(Boolean);
const promoteAdmin = (user) => {
  if (user && ADMIN_EMAILS.includes(user.email) && user.role !== 'admin') {
    run(`UPDATE users SET role = 'admin', status = 'active' WHERE id = ?`, user.id);
    return { ...user, role: 'admin', status: 'active' };
  }
  return user;
};

const MIME = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml', '.xml': 'application/xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8', '.webmanifest': 'application/manifest+json',
  '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.ico': 'image/x-icon'
};

/* ------------------------------------------------------------------ utils */
const SECURITY_HEADERS = {
  'x-content-type-options': 'nosniff',
  'x-frame-options': 'DENY',
  'referrer-policy': 'strict-origin-when-cross-origin',
  'permissions-policy': 'camera=(), microphone=(), geolocation=(self), payment=(self)',
  'content-security-policy': "default-src 'self'; script-src 'self' 'unsafe-inline' https://plausible.io; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self' https://plausible.io; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
  ...(SECURE ? { 'strict-transport-security': 'max-age=31536000; includeSubDomains' } : {})
};

const json = (res, status, body, headers = {}) => {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(payload),
    'cache-control': 'no-store',
    ...SECURITY_HEADERS,
    ...headers
  });
  res.end(payload);
};

/** Sliding-window limiter for unauthenticated write endpoints: N hits / 10 min per IP. */
const hits = new Map();
function rateLimited(ip, key, max = 30, windowMs = 600e3) {
  if (process.env.LUMEA_NO_RATELIMIT) return false;
  const k = `${key}:${ip}`;
  const nowMs = Date.now();
  const list = (hits.get(k) || []).filter((t) => nowMs - t < windowMs);
  list.push(nowMs);
  hits.set(k, list);
  if (hits.size > 50000) hits.clear();
  return list.length > max;
}

async function readBody(req, limit = 1e6) {
  const chunks = [];
  let size = 0;
  for await (const c of req) {
    size += c.length;
    if (size > limit) throw Object.assign(new Error('payload too large'), { status: 413 });
    chunks.push(c);
  }
  if (!chunks.length) return {};
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); }
  catch { throw Object.assign(new Error('invalid JSON'), { status: 400 }); }
}

const cookies = (req) =>
  Object.fromEntries(
    String(req.headers.cookie || '')
      .split(';')
      .map((c) => c.trim().split('='))
      .filter((p) => p.length === 2)
      .map(([k, v]) => [k, decodeURIComponent(v)])
  );

const setCookie = (token, expires) =>
  `${COOKIE}=${encodeURIComponent(token)}; Path=/; HttpOnly; SameSite=Lax; Expires=${new Date(expires).toUTCString()}${SECURE ? '; Secure' : ''}`;
const clearCookie = () => `${COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0${SECURE ? '; Secure' : ''}`;

const currentUser = (req) => sessionUser(cookies(req)[COOKIE]);

const asStr = (v, max = 500) => (v == null ? null : String(v).slice(0, max).trim() || null);
const asArr = (v) => (Array.isArray(v) ? v.map((x) => String(x).slice(0, 80)) : v ? [String(v).slice(0, 80)] : []);
const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(String(v || ''));

/* ------------------------------------------------------------ notifications */
const cityName = (slug, locale) => cityBySlug[slug]?.name?.[locale] || cityBySlug[slug]?.name?.de || slug || '';
const svcName = (slug, locale) => serviceBySlug[slug]?.i18n?.[locale]?.name || slug;

function notifyBooking(bookingId, template) {
  const b = one('SELECT * FROM bookings WHERE id = ?', bookingId);
  if (!b || !b.email) return;
  const th = b.therapist_id ? one('SELECT name FROM therapists WHERE id = ?', b.therapist_id) : null;
  const loc = LOCALES.includes(b.locale) ? b.locale : 'de';
  sendMail(template, {
    to: b.email, locale: loc, name: b.name,
    vars: { service: svcName(b.service, loc), date: b.date, time: b.time, city: cityName(b.city, loc), minutes: site.trust.responseMinutes,
      therapist: th?.name || '—', ics: `${site.origin}${site.basePath}/api/bookings/ics?id=${b.id}`, link: mailLink(loc, 'account') }
  });
  if (template === 'bookingRequested' && process.env.LUMEA_ADMIN_EMAIL) {
    sendMail('bookingRequested', { to: process.env.LUMEA_ADMIN_EMAIL.split(',')[0].trim(), locale: 'en', name: 'Concierge',
      vars: { service: svcName(b.service, 'en'), date: b.date, time: b.time, city: cityName(b.city, 'en'), minutes: 0 } });
  }
}

function sendVerification(user) {
  const token = issueToken(user.id, 'verify', 72);
  sendMail('verifyEmail', { to: user.email, locale: user.locale, name: user.name, vars: { link: `${site.origin}${site.basePath}/api/auth/verify?token=${token}&locale=${user.locale}` } });
}

/* ------------------------------------------------------------------ routes */
const routes = [];
const route = (method, pattern, handler) => routes.push({ method, pattern, handler });

route('GET', '/api/health', async () => ({ status: 200, body: { ok: true, time: now() } }));

route('GET', '/api/geo', async (req) => {
  const geo = await resolveGeo(req);
  const city = cityBySlug[geo.city];
  const count = one(`SELECT COUNT(*) c FROM therapists WHERE city = ? AND status = 'active'`, geo.city)?.c || 0;
  return { status: 200, body: { ...geo, cityName: city?.name || null, therapists: count } };
});

route('GET', '/api/therapists', async (req, _b, url) => {
  const q = url.searchParams;
  // An absent coordinate must stay absent: Number(null) is 0, which would place
  // every request off the coast of Africa and filter out the whole directory.
  const num = (v) => { const n = Number(v); return v == null || v === '' || !Number.isFinite(n) ? undefined : n; };
  const matches = matchTherapists({
    city: q.get('city') || null,
    service: q.get('service') || null,
    lat: num(q.get('lat')),
    lng: num(q.get('lng')),
    when: q.get('when'),
    languages: q.getAll('lang'),
    limit: Math.min(Number(q.get('limit')) || 12, 50)
  });
  return { status: 200, body: { count: matches.length, matches } };
});

route('POST', '/api/auth/register', async (req, body) => {
  if (rateLimited(anonymiseIp(clientIp(req)), 'register', 20)) return { status: 429, body: { error: 'too many requests' } };
  const email = asStr(body.email, 190);
  const password = String(body.password || '');
  if (!isEmail(email)) return { status: 400, body: { error: 'invalid email' } };
  if (password.length < 10) return { status: 400, body: { error: 'password too short' } };
  if (findUserByEmail(email)) return { status: 409, body: { error: 'account exists' } };

  const role = body.role === 'therapist' ? 'therapist' : 'client';
  const ip = clientIp(req);
  const user = createUser({
    email,
    password,
    name: asStr(body.name, 120),
    phone: asStr(body.phone, 40),
    role,
    locale: LOCALES.includes(body.locale) ? body.locale : 'de',
    ip: anonymiseIp(ip)
  });
  const promoted = promoteAdmin(user);
  const geo = await resolveGeo(req);
  const s = createSession(user.id, { ip: anonymiseIp(ip), userAgent: req.headers['user-agent'], country: geo.country, city: geo.city });
  sendVerification(user);
  return { status: 201, body: { user: publicUser(promoted) }, headers: { 'set-cookie': setCookie(s.token, s.expires) } };
});

route('POST', '/api/auth/login', async (req, body) => {
  const email = asStr(body.email, 190);
  const ip = clientIp(req);
  const ua = req.headers['user-agent'];
  if (!isEmail(email)) return { status: 400, body: { error: 'invalid email' } };
  if (isThrottled(email, anonymiseIp(ip))) return { status: 429, body: { error: 'too many attempts' } };

  const user = findUserByEmail(email);
  const ok = user && user.status !== 'blocked' && !user.deleted_at && verifyPassword(String(body.password || ''), user.password_hash);
  recordAttempt(email, anonymiseIp(ip), !!ok, ua);
  if (!ok) return { status: 401, body: { error: 'invalid credentials' } };

  const knownDevice = hasSessionFromIp(user.id, anonymiseIp(ip));
  run('UPDATE users SET last_login_at = ? WHERE id = ?', now(), user.id);
  const promoted = promoteAdmin(user);
  const geo = await resolveGeo(req);
  const s = createSession(user.id, { ip: anonymiseIp(ip), userAgent: ua, country: geo.country, city: geo.city });
  if (!knownDevice && user.last_login_at) {
    sendMail('newLogin', { to: user.email, locale: user.locale, name: user.name, vars: { ip: anonymiseIp(ip), city: cityName(geo.city, user.locale), link: mailLink(user.locale, 'reset') } });
  }
  return { status: 200, body: { user: publicUser(promoted) }, headers: { 'set-cookie': setCookie(s.token, s.expires) } };
});

route('POST', '/api/auth/logout', async (req) => {
  destroySession(cookies(req)[COOKIE]);
  return { status: 200, body: { ok: true }, headers: { 'set-cookie': clearCookie() } };
});

route('GET', '/api/auth/me', async (req) => {
  const u = currentUser(req);
  return u ? { status: 200, body: { user: publicUser(u) } } : { status: 401, body: { error: 'not signed in' } };
});

route('GET', '/api/auth/sessions', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  return { status: 200, body: { sessions: listSessions(u.id) } };
});

/* ------------------------------------------- password reset & email verify */
route('POST', '/api/auth/forgot', async (req, body) => {
  const ip = anonymiseIp(clientIp(req));
  if (rateLimited(ip, 'forgot', 10)) return { status: 429, body: { error: 'too many requests' } };
  const user = isEmail(body.email) ? findUserByEmail(body.email) : null;
  // Always 200: never reveal whether an address is registered.
  if (user && !user.deleted_at) {
    const token = issueToken(user.id, 'reset', 1);
    const loc = LOCALES.includes(body.locale) ? body.locale : user.locale;
    sendMail('resetPassword', { to: user.email, locale: loc, name: user.name, vars: { link: mailLink(loc, 'reset', `?token=${token}`) } });
  }
  return { status: 200, body: { ok: true } };
});

route('POST', '/api/auth/reset', async (req, body) => {
  const password = String(body.password || '');
  if (password.length < 10) return { status: 400, body: { error: 'password too short' } };
  const user = consumeToken(asStr(body.token, 120), 'reset');
  if (!user) return { status: 410, body: { error: 'invalid or expired token' } };
  setPassword(user.id, password);
  return { status: 200, body: { ok: true } };
});

route('POST', '/api/auth/password', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  if (!verifyPassword(String(body.current || ''), u.password_hash)) return { status: 403, body: { error: 'current password incorrect' } };
  const password = String(body.password || '');
  if (password.length < 10) return { status: 400, body: { error: 'password too short' } };
  setPassword(u.id, password);
  const geo = await resolveGeo(req);
  const s = createSession(u.id, { ip: anonymiseIp(clientIp(req)), userAgent: req.headers['user-agent'], country: geo.country, city: geo.city });
  return { status: 200, body: { ok: true }, headers: { 'set-cookie': setCookie(s.token, s.expires) } };
});

route('POST', '/api/auth/verify/resend', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  if (u.email_verified) return { status: 200, body: { ok: true, already: true } };
  if (rateLimited(u.id, 'verify', 5)) return { status: 429, body: { error: 'too many requests' } };
  sendVerification(u);
  return { status: 200, body: { ok: true } };
});

route('GET', '/api/auth/verify', async (req, _b, url) => {
  const user = consumeToken(url.searchParams.get('token'), 'verify');
  const loc = LOCALES.includes(url.searchParams.get('locale')) ? url.searchParams.get('locale') : 'de';
  if (user) run('UPDATE users SET email_verified = 1 WHERE id = ?', user.id);
  return { status: 302, body: '', raw: true, headers: { location: mailLink(loc, 'account', user ? '?verified=1' : '?verified=0') } };
});

/* ------------------------------------------------- GDPR: export & erasure */
route('GET', '/api/auth/export', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const data = {
    exportedAt: now(),
    user: publicUser(u),
    sessions: listSessions(u.id),
    bookings: all('SELECT id, service, duration, persons, addons, city, place, address, date, time, notes, total, status, payment_status, voucher_code, discount, created_at FROM bookings WHERE user_id = ?', u.id),
    favorites: all('SELECT therapist_id, created_at FROM favorites WHERE user_id = ?', u.id),
    reviews: all('SELECT booking_id, therapist_id, rating, text, created_at FROM reviews WHERE user_id = ?', u.id),
    therapistProfile: one('SELECT id, name, full_name, title, city, country, radius_km, services, languages, years, status, about, website, created_at FROM therapists WHERE user_id = ?', u.id) || null
  };
  return { status: 200, body: JSON.stringify(data, null, 2), raw: true, headers: { 'content-type': 'application/json; charset=utf-8', 'content-disposition': `attachment; filename="lumea-export-${u.id}.json"` } };
});

route('POST', '/api/auth/delete', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  if (!verifyPassword(String(body.password || ''), u.password_hash)) return { status: 403, body: { error: 'password incorrect' } };
  const stamp = now();
  run(`UPDATE bookings SET status = 'cancelled', payment_status = CASE WHEN payment_status = 'authorised' THEN 'refunded' ELSE payment_status END WHERE user_id = ? AND status IN ('requested','confirmed')`, u.id);
  run('DELETE FROM favorites WHERE user_id = ?', u.id);
  run(`UPDATE reviews SET author = '—', user_id = NULL WHERE user_id = ?`, u.id);
  run(`UPDATE therapists SET status = 'rejected', about = NULL, website = NULL, photo_path = NULL, user_id = NULL WHERE user_id = ?`, u.id);
  run('DELETE FROM sessions WHERE user_id = ?', u.id);
  run('DELETE FROM tokens WHERE user_id = ?', u.id);
  // Keep the row (invoices reference it) but strip every personal field.
  run(`UPDATE users SET email = ?, name = NULL, phone = NULL, password_hash = 'deleted', status = 'blocked', deleted_at = ?, prive_tier = NULL WHERE id = ?`,
    `deleted-${u.id}@invalid.lumea`, stamp, u.id);
  return { status: 200, body: { ok: true }, headers: { 'set-cookie': clearCookie() } };
});

/* --------------------------------------------------------------- favorites */
route('GET', '/api/favorites', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const rows = all(`SELECT t.id, t.name, t.title, t.city, t.rating, t.reviews, t.initials, t.hue, t.photo_path FROM favorites f JOIN therapists t ON t.id = f.therapist_id WHERE f.user_id = ? ORDER BY f.created_at DESC`, u.id);
  return { status: 200, body: { favorites: rows.map((t) => ({ id: t.id, name: t.name, title: t.title, city: t.city, rating: t.rating, reviews: t.reviews, initials: t.initials, hue: t.hue, photo: !!t.photo_path })) } };
});

route('POST', '/api/favorites', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const tid = asStr(body.therapistId, 40);
  if (!one('SELECT 1 FROM therapists WHERE id = ?', tid)) return { status: 404, body: { error: 'not found' } };
  if (body.remove) run('DELETE FROM favorites WHERE user_id = ? AND therapist_id = ?', u.id, tid);
  else run('INSERT OR IGNORE INTO favorites (user_id, therapist_id, created_at) VALUES (?,?,?)', u.id, tid, now());
  return { status: 200, body: { ok: true, saved: !body.remove } };
});

/* ----------------------------------------------------------------- reviews */
function recalcRating(therapistId) {
  const r = one('SELECT AVG(rating) avg, COUNT(*) n FROM reviews WHERE therapist_id = ?', therapistId);
  const t = one('SELECT rating, reviews, about FROM therapists WHERE id = ?', therapistId);
  if (!t || !r.n) return;
  // Seeded profiles keep their historic count; real reviews are blended on top of it.
  const seeded = Math.max(t.reviews - (r.n - 1), 0);
  const rating = seeded > 0 ? ((t.rating * seeded) + r.avg * r.n) / (seeded + r.n) : r.avg;
  run('UPDATE therapists SET rating = ?, reviews = ? WHERE id = ?', Number(rating.toFixed(2)), seeded + r.n, therapistId);
}

route('POST', '/api/bookings/review', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const b = one(`SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status = 'done'`, asStr(body.id, 40), u.id);
  if (!b || !b.therapist_id) return { status: 404, body: { error: 'no completed booking' } };
  if (b.reviewed) return { status: 409, body: { error: 'already reviewed' } };
  const rating = Math.min(Math.max(Math.round(Number(body.rating)) || 5, 1), 5);
  const author = (u.name || 'Guest').split(' ').map((w, i) => (i === 0 ? w : w[0] + '.')).join(' ');
  run('INSERT INTO reviews (id,booking_id,therapist_id,user_id,rating,text,author,service,locale,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
    id('rev'), b.id, b.therapist_id, u.id, rating, asStr(body.text, 1200), author, b.service, b.locale, now());
  run('UPDATE bookings SET reviewed = 1 WHERE id = ?', b.id);
  recalcRating(b.therapist_id);
  return { status: 201, body: { ok: true } };
});

route('GET', '/api/therapists/reviews', async (req, _b, url) => {
  const rows = all('SELECT rating, text, author, service, locale, created_at FROM reviews WHERE therapist_id = ? ORDER BY created_at DESC LIMIT 50', url.searchParams.get('id') || '');
  return { status: 200, body: { reviews: rows.map((r) => ({ rating: r.rating, text: r.text, name: r.author, service: r.service, date: r.created_at.slice(0, 10), verified: true })) } };
});

/* ---------------------------------------------------------------- vouchers */
const voucherCode = () => 'LUMEA-' + randomBytes(4).toString('hex').toUpperCase().match(/.{4}/g).join('-');

route('POST', '/api/vouchers', async (req, body) => {
  const ip = anonymiseIp(clientIp(req));
  if (rateLimited(ip, 'voucher', 10)) return { status: 429, body: { error: 'too many requests' } };
  const amount = Math.round(Number(body.amount));
  if (!Number.isFinite(amount) || amount < 50 || amount > 5000) return { status: 400, body: { error: 'amount must be 50–5000' } };
  if (!isEmail(body.buyerEmail)) return { status: 400, body: { error: 'invalid email' } };
  const recipient = isEmail(body.recipientEmail) ? asStr(body.recipientEmail, 190) : null;
  const loc = LOCALES.includes(body.locale) ? body.locale : 'de';
  const code = voucherCode();
  const expires = new Date(Date.now() + 3 * 365 * 864e5).toISOString();
  run('INSERT INTO vouchers (code,amount,balance,currency,buyer_email,recipient_email,recipient_name,message,locale,payment_status,expires_at,created_at,created_ip) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    code, amount, amount, 'EUR', asStr(body.buyerEmail, 190), recipient, asStr(body.recipientName, 120), asStr(body.message, 600), loc, 'authorised', expires, now(), ip);
  const to = recipient || asStr(body.buyerEmail, 190);
  sendMail('voucher', { to, locale: loc, name: asStr(body.recipientName, 120), vars: { code, message: asStr(body.message, 600), expires: expires.slice(0, 10), link: mailLink(loc, 'book') } });
  return { status: 201, body: { ok: true, code, amount, expiresAt: expires, sentTo: to } };
});

route('GET', '/api/vouchers/check', async (req, _b, url) => {
  const v = one(`SELECT code, balance, currency, expires_at FROM vouchers WHERE code = ? AND payment_status = 'authorised' AND balance > 0 AND expires_at > ?`, String(url.searchParams.get('code') || '').trim().toUpperCase(), now());
  return v ? { status: 200, body: { ok: true, balance: v.balance, currency: v.currency, expiresAt: v.expires_at } } : { status: 404, body: { error: 'voucher invalid' } };
});

/* ------------------------------------------------------------------- privé */
route('POST', '/api/prive/subscribe', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const tier = priveTiers.find((x) => x.slug === body.tier);
  if (!tier) return { status: 400, body: { error: 'unknown tier' } };
  run('UPDATE users SET prive_tier = ?, prive_since = COALESCE(prive_since, ?) WHERE id = ?', tier.slug, now(), u.id);
  sendMail('priveWelcome', { to: u.email, locale: u.locale, name: u.name, vars: { tier: tier.slug.charAt(0).toUpperCase() + tier.slug.slice(1) } });
  return { status: 200, body: { ok: true, tier: tier.slug, price: tier.price } };
});

route('POST', '/api/prive/cancel', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  run('UPDATE users SET prive_tier = NULL, prive_since = NULL WHERE id = ?', u.id);
  return { status: 200, body: { ok: true } };
});


route('POST', '/api/therapists/apply', async (req, body) => {
  if (rateLimited(anonymiseIp(clientIp(req)), 'apply', 10)) return { status: 429, body: { error: 'too many requests' } };
  const email = asStr(body.email, 190);
  if (!isEmail(email)) return { status: 400, body: { error: 'invalid email' } };
  const city = cityBySlug[body.city];
  if (!city) return { status: 400, body: { error: 'unknown city' } };

  const services = asArr(body.services).filter((s) => serviceBySlug[s]);
  if (!services.length) return { status: 400, body: { error: 'select at least one treatment' } };

  const ip = anonymiseIp(clientIp(req));
  let user = findUserByEmail(email);
  if (!user) {
    const password = String(body.password || '');
    if (password.length < 10) return { status: 400, body: { error: 'password too short' } };
    user = createUser({
      email, password,
      name: [asStr(body.firstName, 60), asStr(body.lastName, 60)].filter(Boolean).join(' '),
      phone: asStr(body.phone, 40), role: 'therapist',
      locale: LOCALES.includes(body.locale) ? body.locale : 'de',
      ip, status: 'pending'
    });
  }

  // One profile per account: a second application updates the pending record instead of duplicating it.
  const existing = one(`SELECT id, status FROM therapists WHERE user_id = ? ORDER BY created_at DESC LIMIT 1`, user.id);
  if (existing && existing.status === 'active') return { status: 409, body: { error: 'profile already active — edit it from your account' } };
  if (existing) run('DELETE FROM therapists WHERE id = ?', existing.id);

  const first = asStr(body.firstName, 60) || '';
  const last = asStr(body.lastName, 60) || '';
  const tid = id('thr');
  run(
    `INSERT INTO therapists
      (id,user_id,name,full_name,title,city,country,postal,lat,lng,radius_km,services,languages,equipment,availability,
       years,rating,reviews,response_minutes,verified,accepts_short_notice,status,about,website,initials,hue,created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    tid, user.id, `${first} ${last.charAt(0)}.`.trim(), `${first} ${last}`.trim(),
    asStr(body.qualification, 120), city.slug, city.country, asStr(body.postal, 16),
    city.lat, city.lng, Math.min(Math.max(Number(body.radiusKm) || 15, 3), 80),
    JSON.stringify(services), JSON.stringify(asArr(String(body.languages || '').split(/[,;]/).map((s) => s.trim()).filter(Boolean))),
    JSON.stringify(asArr(body.equipment)), JSON.stringify(asArr(body.availability)),
    Math.min(Number(body.years) || 0, 60), 5, 0, 15, 0,
    asArr(body.availability).some((a) => /kurzfristig|short notice|aviso corto/i.test(a)) ? 1 : 0,
    'pending', asStr(body.about, 2000), asStr(body.website, 200),
    ((first[0] || 'L') + (last[0] || 'X')).toUpperCase(), Math.floor(Math.random() * 360), now()
  );

  sendMail('applicationReceived', { to: user.email, locale: user.locale, name: user.name, vars: { link: mailLink(user.locale, 'account') } });
  return { status: 201, body: { ok: true, applicationId: tid, status: 'pending' } };
});

route('POST', '/api/bookings', async (req, body) => {
  if (rateLimited(anonymiseIp(clientIp(req)), 'booking', 30)) return { status: 429, body: { error: 'too many requests' } };
  const service = serviceBySlug[body.service];
  if (!service) return { status: 400, body: { error: 'unknown treatment' } };
  if (!isEmail(body.email)) return { status: 400, body: { error: 'invalid email' } };

  // A requested therapist must exist, be active and offer the treatment — otherwise fall back to matching.
  let therapistId = asStr(body.therapistId, 40);
  if (therapistId) {
    const th = one(`SELECT id, services FROM therapists WHERE id = ? AND status = 'active'`, therapistId);
    therapistId = th && JSON.parse(th.services || '[]').includes(service.slug) ? th.id : null;
  }
  body.therapistId = therapistId;

  // Voucher: consume as much balance as the booking total allows.
  let discount = 0, voucher = null;
  const code = String(body.voucher || '').trim().toUpperCase();
  if (code) {
    voucher = one(`SELECT * FROM vouchers WHERE code = ? AND payment_status = 'authorised' AND balance > 0 AND expires_at > ?`, code, now());
    if (!voucher) return { status: 400, body: { error: 'voucher invalid' } };
    const totalNum = Number(String(body.total || '').replace(/[^0-9.]/g, '')) || 0;
    discount = Math.min(voucher.balance, totalNum || voucher.balance);
    run('UPDATE vouchers SET balance = balance - ? WHERE code = ?', discount, code);
  }

  const user = currentUser(req);
  const bid = id('bkg');
  run(
    `INSERT INTO bookings
      (id,user_id,therapist_id,service,duration,persons,addons,city,place,address,date,time,notes,name,email,phone,total,locale,status,created_at,created_ip)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    bid, user?.id || null, asStr(body.therapistId, 40), service.slug,
    Math.min(Number(body.duration) || 60, 240), Math.min(Number(body.persons) || 1, 6),
    JSON.stringify(asArr(body.addons)), asStr(body.city, 40), asStr(body.place, 60),
    asStr(body.address, 300), asStr(body.date, 20), asStr(body.time, 10),
    asStr(body.notes, 2000), asStr(body.name, 120), asStr(body.email, 190), asStr(body.phone, 40),
    asStr(body.total, 40), LOCALES.includes(body.locale) ? body.locale : 'de',
    'requested', now(), anonymiseIp(clientIp(req))
  );
  // Payment model: the guest pays upfront (payment_status = authorised); the amount is held
  // and released to the therapist once the appointment is marked done. The PSP webhook that
  // flips these states plugs in here (see README → Payments).
  run(`UPDATE bookings SET payment_status = 'authorised', voucher_code = ?, discount = ? WHERE id = ?`, voucher?.code || null, discount, bid);
  notifyBooking(bid, 'bookingRequested');

  const matches = matchTherapists({
    city: asStr(body.city, 40),
    service: service.slug,
    lat: cityBySlug[body.city]?.lat,
    lng: cityBySlug[body.city]?.lng,
    limit: 3
  });
  return { status: 201, body: { ok: true, bookingId: bid, status: 'requested', therapistId, discount, suggested: matches } };
});

route('GET', '/api/bookings', async (req) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const rows = all(`SELECT b.*, t.name AS therapist_name, t.title AS therapist_title
    FROM bookings b LEFT JOIN therapists t ON t.id = b.therapist_id
    WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 50`, u.id);
  return {
    status: 200,
    body: {
      bookings: rows.map((b) => ({
        id: b.id, service: b.service, serviceName: serviceBySlug[b.service]?.i18n[u.locale || 'de']?.name || b.service,
        date: b.date, time: b.time, city: b.city, address: b.address,
        duration: b.duration, total: b.total, status: b.status, paymentStatus: b.payment_status, createdAt: b.created_at,
        reviewed: !!b.reviewed, discount: b.discount || 0, voucher: b.voucher_code || null,
        therapist: b.therapist_name ? { id: b.therapist_id, name: b.therapist_name, title: b.therapist_title } : null
      }))
    }
  };
});

route('POST', '/api/contact', async (req, body) => {
  if (rateLimited(anonymiseIp(clientIp(req)), 'contact', 10)) return { status: 429, body: { error: 'too many requests' } };
  if (!isEmail(body.email)) return { status: 400, body: { error: 'invalid email' } };
  run('INSERT INTO messages (id,kind,name,email,city,body,locale,ip,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
    id('msg'), 'contact', asStr(body.name, 120), asStr(body.email, 190), asStr(body.city, 40),
    asStr(body.message, 4000), asStr(body.locale, 5), anonymiseIp(clientIp(req)), now());
  return { status: 201, body: { ok: true } };
});


/* -------------------------------------------------- appointment workflow */
const therapistProfile = (userId) => one(`SELECT * FROM therapists WHERE user_id = ? ORDER BY created_at DESC LIMIT 1`, userId);

route('GET', '/api/therapist/me', async (req) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  return { status: 200, body: { profile: shapeProfile(p, u.locale) } };
});

/** Open requests in the therapist's city for treatments they actually offer. */
route('GET', '/api/therapist/requests', async (req) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  const services = JSON.parse(p.services || '[]');
  const open = all(`SELECT * FROM bookings WHERE status = 'requested' AND city = ? ORDER BY date, time LIMIT 100`, p.city)
    .filter((b) => services.includes(b.service));
  const mine = all(`SELECT * FROM bookings WHERE therapist_id = ? AND status IN ('confirmed','done') ORDER BY date DESC LIMIT 100`, p.id);
  const shape = (b) => ({ id: b.id, service: b.service, date: b.date, time: b.time, duration: b.duration, persons: b.persons, city: b.city, place: b.place, notes: b.notes, total: b.total, status: b.status, createdAt: b.created_at,
    // Address and contact details are released only once the therapist has accepted.
    address: b.therapist_id === p.id ? b.address : null, name: b.therapist_id === p.id ? b.name : null, phone: b.therapist_id === p.id ? b.phone : null });
  return { status: 200, body: { profileStatus: p.status, open: open.map(shape), mine: mine.map(shape) } };
});

route('POST', '/api/therapist/accept', async (req, body) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  if (p.status !== 'active') return { status: 403, body: { error: 'profile not yet verified' } };
  const b = one('SELECT * FROM bookings WHERE id = ?', asStr(body.id, 40));
  if (!b) return { status: 404, body: { error: 'booking not found' } };
  if (b.status !== 'requested') return { status: 409, body: { error: `booking already ${b.status}` } };
  if (!JSON.parse(p.services).includes(b.service) || b.city !== p.city) return { status: 403, body: { error: 'outside your profile' } };
  const r = run(`UPDATE bookings SET therapist_id = ?, status = 'confirmed' WHERE id = ? AND status = 'requested'`, p.id, b.id);
  if (!r.changes) return { status: 409, body: { error: 'booking was taken' } };
  notifyBooking(b.id, 'bookingConfirmed');
  return { status: 200, body: { ok: true, bookingId: b.id, status: 'confirmed' } };
});

route('POST', '/api/therapist/complete', async (req, body) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  const r = run(`UPDATE bookings SET status = 'done', payment_status = CASE WHEN payment_status = 'authorised' THEN 'released' ELSE payment_status END, payout_at = ?
    WHERE id = ? AND therapist_id = ? AND status = 'confirmed'`, now(), asStr(body.id, 40), p?.id || '');
  if (r.changes) notifyBooking(asStr(body.id, 40), 'bookingDone');
  return r.changes ? { status: 200, body: { ok: true, payout: 'released' } } : { status: 404, body: { error: 'nothing to complete' } };
});

route('POST', '/api/bookings/cancel', async (req, body) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const r = run(`UPDATE bookings SET status = 'cancelled', payment_status = CASE WHEN payment_status = 'authorised' THEN 'refunded' ELSE payment_status END
    WHERE id = ? AND user_id = ? AND status IN ('requested','confirmed')`, asStr(body.id, 40), u.id);
  if (r.changes) notifyBooking(asStr(body.id, 40), 'bookingCancelled');
  return r.changes ? { status: 200, body: { ok: true } } : { status: 404, body: { error: 'nothing to cancel' } };
});

/** iCalendar export so a confirmed appointment lands in Apple/Google/Outlook calendars. */
route('GET', '/api/bookings/ics', async (req, _b, url) => {
  const u = currentUser(req);
  if (!u) return { status: 401, body: { error: 'not signed in' } };
  const b = one('SELECT * FROM bookings WHERE id = ?', url.searchParams.get('id') || '');
  const p = b?.therapist_id ? one('SELECT * FROM therapists WHERE id = ?', b.therapist_id) : null;
  if (!b || (b.user_id !== u.id && p?.user_id !== u.id)) return { status: 404, body: { error: 'not found' } };
  const start = new Date(`${b.date}T${b.time || '12:00'}:00`);
  const end = new Date(start.getTime() + (b.duration || 60) * 60e3);
  const fmtIcs = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
  const svcName = serviceBySlug[b.service]?.i18n[b.locale || 'de']?.name || b.service;
  const ics = [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LUMEA//Booking//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
    'BEGIN:VEVENT', `UID:${b.id}@lumea`, `DTSTAMP:${fmtIcs(new Date())}`, `DTSTART:${fmtIcs(start)}`, `DTEND:${fmtIcs(end)}`,
    `SUMMARY:LUMÉA · ${svcName}`, `LOCATION:${(b.address || '').replace(/[,;]/g, '\\$&')}`,
    `DESCRIPTION:${b.duration} min · ${b.total || ''} · ${b.status}`, 'END:VEVENT', 'END:VCALENDAR'
  ].join('\r\n');
  return { status: 200, body: ics, raw: true, headers: { 'content-type': 'text/calendar; charset=utf-8', 'content-disposition': `attachment; filename="lumea-${b.id}.ics"` } };
});



/* -------------------------------------------------------- therapist profile */
const shapeProfile = (r, locale = 'de') => {
  const rec = {
    id: r.id, name: r.name, fullName: r.full_name, title: r.title, city: r.city, country: r.country, lat: r.lat, lng: r.lng,
    radiusKm: r.radius_km, services: JSON.parse(r.services || '[]'), languages: JSON.parse(r.languages || '[]'),
    equipment: JSON.parse(r.equipment || '[]'), availability: JSON.parse(r.availability || '[]'),
    years: r.years, rating: r.rating, reviews: r.reviews, responseMinutes: r.response_minutes, since: new Date(r.created_at).getFullYear(),
    verified: !!r.verified, topRated: r.rating >= 4.8 && r.reviews > 60, acceptsShortNotice: !!r.accepts_short_notice,
    initials: r.initials, hue: r.hue, about: r.about, website: r.website, status: r.status,
    verification: { identity: !!r.identity_verified, qualification: !!r.qualification_verified, insurance: !!r.insurance_verified, background: !!r.background_verified }
  };
  // Seeded roster carries generated bios/reviews; applicants use what they wrote themselves.
  const x = profileExtras({ ...rec, lateNight: false }, locale);
  const real = all('SELECT rating, text, author, service, created_at FROM reviews WHERE therapist_id = ? ORDER BY created_at DESC LIMIT 20', r.id)
    .map((rv) => ({ rating: rv.rating, text: rv.text, name: rv.author, service: rv.service, date: rv.created_at.slice(0, 10), verified: true }));
  return { ...rec, bio: rec.about || x.bio, certifications: x.certifications, districts: x.districts,
    availability: rec.availability.length ? rec.availability : x.availability, equipment: rec.equipment.length ? rec.equipment : x.equipment,
    photo: !!r.photo_path, sampleReviews: real.concat(rec.about ? [] : x.reviews) };
};

route('GET', '/api/therapists/profile', async (req, _b, url) => {
  const r = one(`SELECT * FROM therapists WHERE id = ? AND status = 'active'`, url.searchParams.get('id') || '');
  if (!r) return { status: 404, body: { error: 'not found' } };
  const locale = LOCALES.includes(url.searchParams.get('locale')) ? url.searchParams.get('locale') : 'de';
  return { status: 200, body: { profile: shapeProfile(r, locale) } };
});

/** Therapists edit their own public profile; identity-relevant fields (name, city) stay locked to the verified documents. */
route('POST', '/api/therapist/profile', async (req, body) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  const services = asArr(body.services).filter((x) => serviceBySlug[x]);
  if (body.services != null && !services.length) return { status: 400, body: { error: 'select at least one treatment' } };
  run(`UPDATE therapists SET title = COALESCE(?, title), about = COALESCE(?, about), website = COALESCE(?, website),
        radius_km = COALESCE(?, radius_km), services = COALESCE(?, services), languages = COALESCE(?, languages),
        equipment = COALESCE(?, equipment), availability = COALESCE(?, availability), accepts_short_notice = COALESCE(?, accepts_short_notice)
       WHERE id = ?`,
    asStr(body.title, 120), asStr(body.about, 2000), asStr(body.website, 200),
    body.radiusKm != null ? Math.min(Math.max(Number(body.radiusKm) || 15, 3), 80) : null,
    body.services != null ? JSON.stringify(services) : null,
    body.languages != null ? JSON.stringify(asArr(String(body.languages).split(/[,;]/).map((x) => x.trim()).filter(Boolean))) : null,
    body.equipment != null ? JSON.stringify(asArr(body.equipment)) : null,
    body.availability != null ? JSON.stringify(asArr(body.availability)) : null,
    body.availability != null ? (asArr(body.availability).some((a) => /kurzfristig|short notice|aviso corto/i.test(a)) ? 1 : 0) : null,
    p.id);
  return { status: 200, body: { ok: true, profile: shapeProfile(therapistProfile(u.id), u.locale) } };
});

/* ------------------------------------------- identity & document checks */
route('GET', '/api/therapist/documents', async (req) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  return { status: 200, body: { types: DOC_TYPES, required: REQUIRED, checklist: checklist(p.id), profileStatus: p.status, verified: !!p.verified } };
});

/** Browser sends { type, filename, mime, data (base64) } — max 8 MB after decoding. */
route('POST', '/api/therapist/documents', async (req, body) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  const doc = storeDocument({ therapistId: p.id, userId: u.id, type: asStr(body.type, 30), filename: body.filename, mime: asStr(body.mime, 60), dataBase64: body.data });
  return { status: 201, body: { ok: true, document: { id: doc.id, type: doc.type, status: doc.status }, checklist: checklist(p.id) } };
});

/* ------------------------------------------------------------ profile photo */
const PHOTO_DIR = path.join(process.env.LUMEA_DATA_DIR || path.resolve(process.cwd(), '.data'), 'photos');
const PHOTO_MIME = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

route('POST', '/api/therapist/photo', async (req, body) => {
  const u = currentUser(req);
  if (!u || u.role !== 'therapist') return { status: 401, body: { error: 'therapist sign-in required' } };
  const p = therapistProfile(u.id);
  if (!p) return { status: 404, body: { error: 'no profile' } };
  const ext = PHOTO_MIME[asStr(body.mime, 40)];
  if (!ext) return { status: 415, body: { error: 'JPEG, PNG or WEBP only' } };
  const buf = Buffer.from(String(body.data || '').replace(/^data:[^;]+;base64,/, ''), 'base64');
  if (!buf.length || buf.length > 2 * 1024 * 1024) return { status: 413, body: { error: 'photo must be under 2 MB' } };
  mkdirSync(PHOTO_DIR, { recursive: true });
  const file = path.join(PHOTO_DIR, `${p.id}.${ext}`);
  writeFileSync(file, buf, { mode: 0o600 });
  run('UPDATE therapists SET photo_path = ?, photo_mime = ? WHERE id = ?', file, asStr(body.mime, 40), p.id);
  return { status: 201, body: { ok: true } };
});

route('GET', '/api/therapists/photo', async (req, _b, url) => {
  const r = one('SELECT photo_path, photo_mime FROM therapists WHERE id = ?', url.searchParams.get('id') || '');
  if (!r?.photo_path || !existsSync(r.photo_path)) return { status: 404, body: { error: 'no photo' } };
  return { status: 200, stream: r.photo_path, headers: { 'content-type': r.photo_mime, 'cache-control': 'public, max-age=3600' } };
});

const requireAdmin = (req) =>
 { const u = currentUser(req); return u && u.role === 'admin' ? u : null; };

route('GET', '/api/admin/overview', async (req) => {
  if (!requireAdmin(req)) return { status: 403, body: { error: 'admin only' } };
  return { status: 200, body: {
    pendingDocuments: pendingDocuments(),
    pendingTherapists: all(`SELECT id, full_name, city, country, status, created_at FROM therapists WHERE status = 'pending' ORDER BY created_at DESC LIMIT 100`),
    openBookings: one(`SELECT COUNT(*) c FROM bookings WHERE status = 'requested'`).c,
    users: one('SELECT COUNT(*) c FROM users WHERE deleted_at IS NULL').c,
    members: one('SELECT COUNT(*) c FROM users WHERE prive_tier IS NOT NULL').c,
    vouchersOpen: one('SELECT COALESCE(SUM(balance),0) c FROM vouchers WHERE balance > 0').c,
    reviews: one('SELECT COUNT(*) c FROM reviews').c,
    escrow: one(`SELECT COUNT(*) c FROM bookings WHERE payment_status = 'authorised'`).c
  } };
});

route('POST', '/api/admin/documents/review', async (req, body) => {
  const admin = requireAdmin(req);
  if (!admin) return { status: 403, body: { error: 'admin only' } };
  const out = reviewDocument({ docId: asStr(body.id, 40), status: asStr(body.status, 12), note: asStr(body.note, 500), reviewerId: admin.id });
  const d = one('SELECT d.type, d.note, t.user_id FROM documents d JOIN therapists t ON t.id = d.therapist_id WHERE d.id = ?', asStr(body.id, 40));
  const owner = d?.user_id ? one('SELECT * FROM users WHERE id = ?', d.user_id) : null;
  if (owner) sendMail('documentReviewed', { to: owner.email, locale: owner.locale, name: owner.name, vars: { type: d.type, status: out.doc.status, note: d.note, profile: out.therapistStatus.status } });
  return { status: 200, body: out };
});

route('GET', '/api/admin/documents/file', async (req, _b, url) => {
  if (!requireAdmin(req)) return { status: 403, body: { error: 'admin only' } };
  const d = documentFile(url.searchParams.get('id') || '');
  if (!d) return { status: 404, body: { error: 'not found' } };
  return { status: 200, stream: d.path, headers: { 'content-type': d.mime, 'content-disposition': `inline; filename="${d.filename || d.id}"`, 'x-content-type-options': 'nosniff' } };
});

route('POST', '/api/admin/therapists/status', async (req, body) => {
  if (!requireAdmin(req)) return { status: 403, body: { error: 'admin only' } };
  const status = asStr(body.status, 12);
  if (!['active', 'pending', 'rejected'].includes(status)) return { status: 400, body: { error: 'bad status' } };
  const t = one('SELECT * FROM therapists WHERE id = ?', asStr(body.id, 40));
  if (!t) return { status: 404, body: { error: 'not found' } };
  // Policy: nobody works without approved identity, qualification and insurance documents.
  if (status === 'active' && !(t.identity_verified && t.qualification_verified && t.insurance_verified)) {
    return { status: 409, body: { error: 'identity, qualification and insurance must be approved first' } };
  }
  run('UPDATE therapists SET status = ? WHERE id = ?', status, t.id);
  return { status: 200, body: { ok: true, status } };
});

/* ------------------------------------------------------------ static files */
async function serveStatic(req, res, pathname) {
  const clean = path.normalize(decodeURIComponent(pathname)).replace(/^(\.\.[/\\])+/, '');
  let file = path.join(DIST, clean);
  if (!file.startsWith(DIST)) { res.writeHead(403).end('Forbidden'); return; }

  try {
    let s = await stat(file).catch(() => null);
    if (s?.isDirectory()) { file = path.join(file, 'index.html'); s = await stat(file).catch(() => null); }
    if (!s) {
      // Directory-style URLs without a trailing slash.
      const alt = path.join(DIST, clean, 'index.html');
      s = await stat(alt).catch(() => null);
      if (s) file = alt;
    }
    if (!s) {
      const loc = (clean.match(/^[/\\]?(de|en|es|fr|it)[/\\]/) || [])[1];
      const candidates = [loc && path.join(DIST, loc, '404.html'), path.join(DIST, '404.html')].filter(Boolean);
      res.writeHead(404, { 'content-type': 'text/html; charset=utf-8', ...SECURITY_HEADERS });
      for (const nf of candidates) if (await stat(nf).catch(() => null)) return createReadStream(nf).pipe(res);
      return res.end('Not found');
    }

    const ext = path.extname(file);
    const immutable = /\/assets\//.test(file) && ext !== '.json';
    res.writeHead(200, {
      'content-type': MIME[ext] || 'application/octet-stream',
      'content-length': s.size,
      'cache-control': ext === '.html' ? 'public, max-age=300' : immutable ? 'public, max-age=604800' : 'public, max-age=3600',
      ...SECURITY_HEADERS
    });
    if (req.method === 'HEAD') return res.end();
    createReadStream(file).pipe(res);
  } catch {
    res.writeHead(500).end('Server error');
  }
}

/* --------------------------------------------------------------- the server */
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const { pathname } = url;

  if (pathname.startsWith('/api/')) {
    const match = routes.find((r) => r.pattern === pathname && r.method === req.method);
    if (!match) return json(res, 404, { error: 'not found' });
    try {
      const limit = pathname === '/api/therapist/documents' ? 12e6 : 1e6;
      const body = req.method === 'POST' || req.method === 'PUT' ? await readBody(req, limit) : {};
      const out = await match.handler(req, body, url);
      if (out.raw) { res.writeHead(out.status, { 'cache-control': 'no-store', ...SECURITY_HEADERS, ...out.headers }); return res.end(out.body); }
      if (out.stream) { res.writeHead(out.status, { 'cache-control': 'private, no-store', ...SECURITY_HEADERS, ...out.headers }); return streamFile(out.stream).pipe(res); }
      return json(res, out.status, out.body, out.headers || {});
    } catch (err) {
      const status = err.status || 500;
      if (status >= 500) console.error('[api]', pathname, err);
      return json(res, status, { error: err.message || 'server error' });
    }
  }

  if (req.method !== 'GET' && req.method !== 'HEAD') return json(res, 405, { error: 'method not allowed' });
  return serveStatic(req, res, pathname);
});

const seeded = seedTherapists();
server.listen(PORT, HOST, () => {
  console.log(`LUMÉA server → http://localhost:${PORT}`);
  console.log(`  static: ${DIST}`);
  console.log(`  therapists in directory: ${seeded || one('SELECT COUNT(*) c FROM therapists').c}`);
});
