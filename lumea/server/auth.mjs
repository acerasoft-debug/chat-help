import { randomBytes, scryptSync, timingSafeEqual, createHash } from 'node:crypto';
import { one, all, run, id, now } from './db.mjs';

const SESSION_DAYS = 30;
const SCRYPT = { N: 16384, r: 8, p: 1, keylen: 64 };

export function hashPassword(password) {
  const salt = randomBytes(16).toString('hex');
  const key = scryptSync(password, salt, SCRYPT.keylen, SCRYPT).toString('hex');
  return `scrypt$${SCRYPT.N}$${SCRYPT.r}$${SCRYPT.p}$${salt}$${key}`;
}

export function verifyPassword(password, stored) {
  try {
    const [scheme, N, r, p, salt, key] = String(stored).split('$');
    if (scheme !== 'scrypt') return false;
    const derived = scryptSync(password, salt, key.length / 2, { N: +N, r: +r, p: +p });
    const known = Buffer.from(key, 'hex');
    return derived.length === known.length && timingSafeEqual(derived, known);
  } catch {
    return false;
  }
}

/** Sessions are opaque random tokens; only their SHA-256 digest is stored. */
export const newToken = () => randomBytes(32).toString('base64url');
const digest = (token) => createHash('sha256').update(token).digest('hex');

export function createSession(userId, { ip, userAgent, country, city }) {
  const token = newToken();
  const expires = new Date(Date.now() + SESSION_DAYS * 864e5).toISOString();
  run(
    'INSERT INTO sessions (token,user_id,created_at,expires_at,ip,user_agent,country,city) VALUES (?,?,?,?,?,?,?,?)',
    digest(token), userId, now(), expires, ip || null, (userAgent || '').slice(0, 200), country || null, city || null
  );
  return { token, expires };
}

export function sessionUser(token) {
  if (!token) return null;
  const row = one(
    `SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
     WHERE s.token = ? AND s.expires_at > ?`,
    digest(token), now()
  );
  return row || null;
}

export const destroySession = (token) => token && run('DELETE FROM sessions WHERE token = ?', digest(token));

export const listSessions = (userId) =>
  all('SELECT created_at, expires_at, ip, user_agent, country, city FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', userId)
    .map((s) => ({ createdAt: s.created_at, expiresAt: s.expires_at, ip: s.ip, userAgent: s.user_agent, country: s.country, city: s.city }));

/**
 * Throttle credential stuffing: 8 failures per email and 25 per IP in 15 minutes.
 * Successful logins reset the email counter.
 */
export function isThrottled(email, ip) {
  const since = new Date(Date.now() - 15 * 60e3).toISOString();
  const byEmail = one('SELECT COUNT(*) c FROM login_attempts WHERE email = ? AND ok = 0 AND at > ?', String(email).toLowerCase(), since);
  const byIp = one('SELECT COUNT(*) c FROM login_attempts WHERE ip = ? AND ok = 0 AND at > ?', ip || '', since);
  return (byEmail?.c || 0) >= 8 || (byIp?.c || 0) >= 25;
}

export function recordAttempt(email, ip, ok, userAgent) {
  run('INSERT INTO login_attempts (email,ip,ok,at,user_agent) VALUES (?,?,?,?,?)',
    String(email).toLowerCase(), ip || null, ok ? 1 : 0, now(), (userAgent || '').slice(0, 200));
  if (ok) run('DELETE FROM login_attempts WHERE email = ? AND ok = 0', String(email).toLowerCase());
}

export function createUser({ email, password, name, phone, role = 'client', locale = 'de', ip, status = 'active' }) {
  const uid = id('usr');
  run(
    'INSERT INTO users (id,email,password_hash,name,phone,role,locale,status,created_at,created_ip) VALUES (?,?,?,?,?,?,?,?,?,?)',
    uid, String(email).toLowerCase().trim(), hashPassword(password), name || null, phone || null,
    role, locale, status, now(), ip || null
  );
  return one('SELECT * FROM users WHERE id = ?', uid);
}

export const findUserByEmail = (email) => one('SELECT * FROM users WHERE email = ?', String(email).toLowerCase().trim());

/** Never let password hashes leave the server. */
export const publicUser = (u) => u && ({ id: u.id, email: u.email, name: u.name, phone: u.phone, role: u.role, locale: u.locale, status: u.status, createdAt: u.created_at,
  emailVerified: !!u.email_verified, priveTier: u.prive_tier || null, priveSince: u.prive_since || null });

/* ------------------------------------------ one-time tokens (reset / verify) */
export function issueToken(userId, kind, hours) {
  const token = newToken();
  run('DELETE FROM tokens WHERE user_id = ? AND kind = ?', userId, kind);
  run('INSERT INTO tokens (token,user_id,kind,expires_at,created_at) VALUES (?,?,?,?,?)',
    digest(token), userId, kind, new Date(Date.now() + hours * 3600e3).toISOString(), now());
  return token;
}

/** Returns the user row and burns the token; null when unknown, expired or already used. */
export function consumeToken(token, kind) {
  if (!token) return null;
  const row = one('SELECT * FROM tokens WHERE token = ? AND kind = ? AND used_at IS NULL AND expires_at > ?', digest(String(token)), kind, now());
  if (!row) return null;
  run('UPDATE tokens SET used_at = ? WHERE token = ?', now(), row.token);
  return one('SELECT * FROM users WHERE id = ?', row.user_id);
}

export function setPassword(userId, password) {
  run('UPDATE users SET password_hash = ? WHERE id = ?', hashPassword(password), userId);
  run('DELETE FROM sessions WHERE user_id = ?', userId); // every device signs in again
}

export const hasSessionFromIp = (userId, ip) => !!one('SELECT 1 FROM sessions WHERE user_id = ? AND ip = ? LIMIT 1', userId, ip || '');
