import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const DATA_DIR = process.env.LUMEA_DATA_DIR || path.resolve(process.cwd(), '.data');
mkdirSync(DATA_DIR, { recursive: true });

export const db = new DatabaseSync(path.join(DATA_DIR, 'lumea.db'));

db.exec('PRAGMA journal_mode = WAL');
db.exec('PRAGMA foreign_keys = ON');

db.exec(`
CREATE TABLE IF NOT EXISTS users (
  id            TEXT PRIMARY KEY,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  name          TEXT,
  phone         TEXT,
  role          TEXT NOT NULL DEFAULT 'client',   -- client | therapist | admin
  locale        TEXT NOT NULL DEFAULT 'de',
  status        TEXT NOT NULL DEFAULT 'active',   -- active | pending | blocked
  created_at    TEXT NOT NULL,
  created_ip    TEXT,
  last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS sessions (
  token      TEXT PRIMARY KEY,
  user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  ip         TEXT,
  user_agent TEXT,
  country    TEXT,
  city       TEXT
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

CREATE TABLE IF NOT EXISTS login_attempts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  email      TEXT NOT NULL,
  ip         TEXT,
  ok         INTEGER NOT NULL,
  at         TEXT NOT NULL,
  user_agent TEXT
);
CREATE INDEX IF NOT EXISTS idx_attempts_email_at ON login_attempts(email, at);
CREATE INDEX IF NOT EXISTS idx_attempts_ip_at ON login_attempts(ip, at);

CREATE TABLE IF NOT EXISTS therapists (
  id                  TEXT PRIMARY KEY,
  user_id             TEXT REFERENCES users(id) ON DELETE SET NULL,
  name                TEXT NOT NULL,
  full_name           TEXT,
  title               TEXT,
  city                TEXT NOT NULL,
  country             TEXT NOT NULL,
  postal              TEXT,
  lat                 REAL NOT NULL,
  lng                 REAL NOT NULL,
  radius_km           REAL NOT NULL DEFAULT 15,
  services            TEXT NOT NULL DEFAULT '[]',
  languages           TEXT NOT NULL DEFAULT '[]',
  equipment           TEXT NOT NULL DEFAULT '[]',
  availability        TEXT NOT NULL DEFAULT '[]',
  years               INTEGER DEFAULT 0,
  rating              REAL DEFAULT 5,
  reviews             INTEGER DEFAULT 0,
  response_minutes    INTEGER DEFAULT 15,
  verified            INTEGER DEFAULT 0,
  accepts_short_notice INTEGER DEFAULT 0,
  status              TEXT NOT NULL DEFAULT 'pending',  -- pending | active | rejected
  about               TEXT,
  website             TEXT,
  initials            TEXT,
  hue                 INTEGER DEFAULT 30,
  created_at          TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_therapists_city ON therapists(city, status);

CREATE TABLE IF NOT EXISTS bookings (
  id           TEXT PRIMARY KEY,
  user_id      TEXT REFERENCES users(id) ON DELETE SET NULL,
  therapist_id TEXT REFERENCES therapists(id) ON DELETE SET NULL,
  service      TEXT NOT NULL,
  duration     INTEGER NOT NULL DEFAULT 60,
  persons      INTEGER NOT NULL DEFAULT 1,
  addons       TEXT NOT NULL DEFAULT '[]',
  city         TEXT,
  place        TEXT,
  address      TEXT,
  date         TEXT,
  time         TEXT,
  notes        TEXT,
  name         TEXT,
  email        TEXT,
  phone        TEXT,
  total        TEXT,
  locale       TEXT DEFAULT 'de',
  status       TEXT NOT NULL DEFAULT 'requested',  -- requested | confirmed | done | cancelled
  created_at   TEXT NOT NULL,
  created_ip   TEXT
);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id, created_at);

CREATE TABLE IF NOT EXISTS documents (
  id           TEXT PRIMARY KEY,
  therapist_id TEXT NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
  user_id      TEXT REFERENCES users(id) ON DELETE SET NULL,
  type         TEXT NOT NULL,            -- identity | qualification | insurance | background | business
  filename     TEXT, mime TEXT, size INTEGER, path TEXT,
  status       TEXT NOT NULL DEFAULT 'pending',   -- pending | approved | rejected
  note         TEXT,
  reviewed_by  TEXT, reviewed_at TEXT,
  created_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_documents_therapist ON documents(therapist_id, type);
CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status, created_at);

CREATE TABLE IF NOT EXISTS messages (
  id         TEXT PRIMARY KEY,
  kind       TEXT NOT NULL,          -- contact | application-note
  name       TEXT, email TEXT, city TEXT, body TEXT,
  locale     TEXT, ip TEXT,
  created_at TEXT NOT NULL
);
`);

// Verification flags live on the therapist row so listing queries stay cheap.
for (const col of ['identity_verified', 'qualification_verified', 'insurance_verified', 'background_verified']) {
  try { db.exec(`ALTER TABLE therapists ADD COLUMN ${col} INTEGER DEFAULT 0`); } catch { /* column exists */ }
}
// Prepaid escrow: unpaid → authorised (guest paid) → released (paid out to therapist) | refunded.
for (const [col, def] of [['payment_status', "TEXT DEFAULT 'unpaid'"], ['payout_at', 'TEXT'], ['payout_amount', 'TEXT']]) {
  try { db.exec(`ALTER TABLE bookings ADD COLUMN ${col} ${def}`); } catch { /* column exists */ }
}

db.exec(`
CREATE TABLE IF NOT EXISTS tokens (
  token      TEXT PRIMARY KEY,           -- sha256 digest
  user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind       TEXT NOT NULL,              -- reset | verify
  expires_at TEXT NOT NULL,
  used_at    TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_tokens_user ON tokens(user_id, kind);

CREATE TABLE IF NOT EXISTS favorites (
  user_id      TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  therapist_id TEXT NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
  created_at   TEXT NOT NULL,
  PRIMARY KEY (user_id, therapist_id)
);

CREATE TABLE IF NOT EXISTS reviews (
  id           TEXT PRIMARY KEY,
  booking_id   TEXT NOT NULL UNIQUE REFERENCES bookings(id) ON DELETE CASCADE,
  therapist_id TEXT NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
  user_id      TEXT REFERENCES users(id) ON DELETE SET NULL,
  rating       INTEGER NOT NULL,
  text         TEXT,
  author       TEXT,
  service      TEXT,
  locale       TEXT,
  created_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_reviews_therapist ON reviews(therapist_id, created_at);

CREATE TABLE IF NOT EXISTS vouchers (
  code           TEXT PRIMARY KEY,
  amount         INTEGER NOT NULL,        -- EUR, initial value
  balance        INTEGER NOT NULL,
  currency       TEXT NOT NULL DEFAULT 'EUR',
  buyer_email    TEXT, recipient_email TEXT, recipient_name TEXT, message TEXT,
  locale         TEXT,
  payment_status TEXT NOT NULL DEFAULT 'authorised',
  expires_at     TEXT NOT NULL,
  created_at     TEXT NOT NULL,
  created_ip     TEXT
);
`);
for (const [col, def] of [['email_verified', 'INTEGER DEFAULT 0'], ['prive_tier', 'TEXT'], ['prive_since', 'TEXT'], ['deleted_at', 'TEXT']]) {
  try { db.exec(`ALTER TABLE users ADD COLUMN ${col} ${def}`); } catch { /* column exists */ }
}
for (const [col, def] of [['photo_path', 'TEXT'], ['photo_mime', 'TEXT']]) {
  try { db.exec(`ALTER TABLE therapists ADD COLUMN ${col} ${def}`); } catch { /* column exists */ }
}
for (const [col, def] of [['voucher_code', 'TEXT'], ['discount', 'INTEGER DEFAULT 0'], ['reviewed', 'INTEGER DEFAULT 0']]) {
  try { db.exec(`ALTER TABLE bookings ADD COLUMN ${col} ${def}`); } catch { /* column exists */ }
}

export const now = () => new Date().toISOString();
export const id = (prefix) => `${prefix}_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;

/** Small helpers so route code never touches raw statement objects. */
export const one = (sql, ...args) => db.prepare(sql).get(...args);
export const all = (sql, ...args) => db.prepare(sql).all(...args);
export const run = (sql, ...args) => db.prepare(sql).run(...args);
