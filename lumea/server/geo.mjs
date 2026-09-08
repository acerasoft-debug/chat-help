import { cities } from '../data/cities.mjs';
import { haversineKm } from '../data/therapists.mjs';

/**
 * IP → city resolution, in descending order of trust:
 *   1. Edge headers already added by the CDN (Cloudflare, Vercel, Fastly).
 *   2. An optional geo-IP HTTP provider (LUMEA_GEOIP_URL, {ip} placeholder).
 *   3. Accept-Language, which at least narrows the country.
 * A private or missing IP short-circuits to the default market so local
 * development still behaves like production.
 */
const PRIVATE = /^(10\.|127\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|::1|fc|fd|169\.254\.)/i;

export function clientIp(req) {
  const fwd = req.headers['x-forwarded-for'];
  const raw =
    (typeof fwd === 'string' ? fwd.split(',')[0] : Array.isArray(fwd) ? fwd[0] : null) ||
    req.headers['cf-connecting-ip'] ||
    req.headers['x-real-ip'] ||
    req.socket?.remoteAddress ||
    '';
  return String(raw).trim().replace(/^::ffff:/, '');
}

/** Truncated form used in logs, so we never persist a full address. */
export function anonymiseIp(ip) {
  if (!ip) return null;
  if (ip.includes(':')) return ip.split(':').slice(0, 3).join(':') + '::';
  const p = ip.split('.');
  return p.length === 4 ? `${p[0]}.${p[1]}.${p[2]}.0` : ip;
}

const nearestCity = (lat, lng) =>
  cities.reduce((best, c) => {
    const d = haversineKm(lat, lng, c.lat, c.lng);
    return !best || d < best.d ? { city: c, d } : best;
  }, null);

const firstCityOfCountry = (cc) => cities.find((c) => c.country === cc);

function fromHeaders(req) {
  const h = req.headers;
  const lat = Number(h['cf-iplatitude'] ?? h['x-vercel-ip-latitude']);
  const lng = Number(h['cf-iplongitude'] ?? h['x-vercel-ip-longitude']);
  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    const near = nearestCity(lat, lng);
    return { city: near.city.slug, country: near.city.country, lat, lng, source: 'edge-header', distanceKm: Number(near.d.toFixed(1)) };
  }
  const cc = String(h['cf-ipcountry'] || h['x-vercel-ip-country'] || '').toUpperCase();
  const c = cc && firstCityOfCountry(cc);
  if (c) return { city: c.slug, country: c.country, lat: c.lat, lng: c.lng, source: 'edge-country' };
  return null;
}

function fromAcceptLanguage(req) {
  const al = String(req.headers['accept-language'] || '').toLowerCase();
  const m = al.match(/[a-z]{2}-(de|at|ch|es)\b/);
  const cc = m && m[1].toUpperCase();
  const c = cc && firstCityOfCountry(cc);
  if (c) return { city: c.slug, country: c.country, lat: c.lat, lng: c.lng, source: 'accept-language' };
  if (/\bes\b/.test(al)) { const es = firstCityOfCountry('ES'); return { city: es.slug, country: 'ES', lat: es.lat, lng: es.lng, source: 'accept-language' }; }
  return null;
}

const cache = new Map(); // ip → { value, at }
const TTL = 6 * 3600e3;

async function fromProvider(ip) {
  const tpl = process.env.LUMEA_GEOIP_URL;
  if (!tpl || !ip || PRIVATE.test(ip)) return null;
  const hit = cache.get(ip);
  if (hit && Date.now() - hit.at < TTL) return hit.value;
  try {
    const res = await fetch(tpl.replace('{ip}', encodeURIComponent(ip)), { signal: AbortSignal.timeout(1500) });
    if (!res.ok) return null;
    const j = await res.json();
    const lat = Number(j.latitude ?? j.lat);
    const lng = Number(j.longitude ?? j.lon ?? j.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    const near = nearestCity(lat, lng);
    const value = { city: near.city.slug, country: near.city.country, lat, lng, source: 'geoip', distanceKm: Number(near.d.toFixed(1)) };
    cache.set(ip, { value, at: Date.now() });
    return value;
  } catch {
    return null;
  }
}

export async function resolveGeo(req) {
  const ip = clientIp(req);
  const header = fromHeaders(req);
  if (header) return { ...header, ip: anonymiseIp(ip) };

  const provider = await fromProvider(ip);
  if (provider) return { ...provider, ip: anonymiseIp(ip) };

  const lang = fromAcceptLanguage(req);
  if (lang) return { ...lang, ip: anonymiseIp(ip) };

  const fallback = cities[0];
  return { city: fallback.slug, country: fallback.country, lat: fallback.lat, lng: fallback.lng, source: 'default', ip: anonymiseIp(ip) };
}

export { nearestCity };
