import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import { one, all, run, id, now } from './db.mjs';

const DATA_DIR = process.env.LUMEA_DATA_DIR || path.resolve(process.cwd(), '.data');
const UPLOADS = path.join(DATA_DIR, 'uploads');
mkdirSync(UPLOADS, { recursive: true });

export const DOC_TYPES = ['identity', 'qualification', 'insurance', 'background', 'business'];
/** A profile goes live only once these three are approved. */
export const REQUIRED = ['identity', 'qualification', 'insurance'];
const ALLOWED_MIME = { 'application/pdf': 'pdf', 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/heic': 'heic' };
const MAX_BYTES = 8 * 1024 * 1024;

/** Accepts a base64 payload from the browser; returns the stored row. */
export function storeDocument({ therapistId, userId, type, filename, mime, dataBase64 }) {
  if (!DOC_TYPES.includes(type)) throw Object.assign(new Error('unknown document type'), { status: 400 });
  const ext = ALLOWED_MIME[mime];
  if (!ext) throw Object.assign(new Error('only PDF, JPEG, PNG, WEBP or HEIC'), { status: 415 });
  const buf = Buffer.from(String(dataBase64 || '').replace(/^data:[^;]+;base64,/, ''), 'base64');
  if (!buf.length) throw Object.assign(new Error('empty file'), { status: 400 });
  if (buf.length > MAX_BYTES) throw Object.assign(new Error('file larger than 8 MB'), { status: 413 });

  const docId = id('doc');
  const dir = path.join(UPLOADS, therapistId);
  mkdirSync(dir, { recursive: true });
  const file = path.join(dir, `${docId}.${ext}`);
  writeFileSync(file, buf, { mode: 0o600 });

  // A re-upload of the same type supersedes the previous pending/rejected one.
  run(`DELETE FROM documents WHERE therapist_id = ? AND type = ? AND status <> 'approved'`, therapistId, type);
  run(
    `INSERT INTO documents (id,therapist_id,user_id,type,filename,mime,size,path,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)`,
    docId, therapistId, userId, type, String(filename || '').slice(0, 160), mime, buf.length, file, 'pending', now()
  );
  return one('SELECT * FROM documents WHERE id = ?', docId);
}

export const documentsFor = (therapistId) =>
  all('SELECT id,type,filename,mime,size,status,note,created_at,reviewed_at FROM documents WHERE therapist_id = ? ORDER BY created_at DESC', therapistId);

/** Checklist the UI renders: one row per required/optional type with the latest status. */
export function checklist(therapistId) {
  const docs = documentsFor(therapistId);
  return DOC_TYPES.map((type) => {
    const latest = docs.find((d) => d.type === type) || null;
    return { type, required: REQUIRED.includes(type), status: latest?.status || 'missing', id: latest?.id || null, filename: latest?.filename || null, note: latest?.note || null, uploadedAt: latest?.created_at || null };
  });
}

/** Review outcome → flags on the therapist → automatic activation when complete. */
export function reviewDocument({ docId, status, note, reviewerId }) {
  if (!['approved', 'rejected'].includes(status)) throw Object.assign(new Error('status must be approved or rejected'), { status: 400 });
  const doc = one('SELECT * FROM documents WHERE id = ?', docId);
  if (!doc) throw Object.assign(new Error('document not found'), { status: 404 });
  run('UPDATE documents SET status = ?, note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?', status, note || null, reviewerId, now(), docId);

  const col = `${doc.type}_verified`;
  if (['identity', 'qualification', 'insurance', 'background'].includes(doc.type)) {
    run(`UPDATE therapists SET ${col} = ? WHERE id = ?`, status === 'approved' ? 1 : 0, doc.therapist_id);
  }
  const t = one('SELECT * FROM therapists WHERE id = ?', doc.therapist_id);
  const complete = t.identity_verified && t.qualification_verified && t.insurance_verified;
  run('UPDATE therapists SET verified = ?, status = CASE WHEN ? THEN \'active\' WHEN status = \'active\' THEN \'pending\' ELSE status END WHERE id = ?',
    complete ? 1 : 0, complete ? 1 : 0, doc.therapist_id);
  if (complete && t.user_id) run(`UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'`, t.user_id);
  return { doc: one('SELECT id,type,status,note,reviewed_at FROM documents WHERE id = ?', docId), therapistStatus: one('SELECT status, verified FROM therapists WHERE id = ?', doc.therapist_id) };
}

export const pendingDocuments = () =>
  all(`SELECT d.id, d.type, d.filename, d.mime, d.size, d.created_at, t.id AS therapist_id, t.full_name, t.city, t.country, t.status AS therapist_status
       FROM documents d JOIN therapists t ON t.id = d.therapist_id WHERE d.status = 'pending' ORDER BY d.created_at ASC LIMIT 200`);

export const documentFile = (docId) => {
  const d = one('SELECT * FROM documents WHERE id = ?', docId);
  return d && existsSync(d.path) ? d : null;
};
