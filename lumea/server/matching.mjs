import { all } from './db.mjs';
import { haversineKm } from '../data/therapists.mjs';
import { cityBySlug } from '../data/cities.mjs';

/**
 * Ranks active therapists for one request. Distance dominates, then quality,
 * then how well the profile matches the requested treatment and time window.
 * Anyone outside their own declared radius (plus a 5 km courtesy margin) is
 * dropped entirely rather than merely down-ranked.
 */
export function matchTherapists({ city, service, lat, lng, when, languages, limit = 12 }) {
  const rows = all(
    `SELECT * FROM therapists WHERE status = 'active' AND (? IS NULL OR city = ?)`,
    city || null, city || null
  );

  const origin = Number.isFinite(lat) && Number.isFinite(lng)
    ? { lat: Number(lat), lng: Number(lng) }
    : cityBySlug[city]
      ? { lat: cityBySlug[city].lat, lng: cityBySlug[city].lng }
      : null;

  const wantLangs = (languages || []).map((l) => l.toLowerCase());

  return rows
    .map((r) => {
      const services = JSON.parse(r.services || '[]');
      const langs = JSON.parse(r.languages || '[]');
      if (service && !services.includes(service)) return null;

      const distanceKm = origin ? haversineKm(origin.lat, origin.lng, r.lat, r.lng) : null;
      if (distanceKm != null && distanceKm > r.radius_km + 5) return null;

      let score = 100;
      if (distanceKm != null) score -= Math.min(distanceKm, 40) * 1.6;
      score += (r.rating - 4.5) * 22;
      score += Math.min(r.reviews, 200) / 25;
      score -= r.response_minutes / 4;
      if (service) score += 18;
      if (when === 'today' && r.accepts_short_notice) score += 12;
      if (wantLangs.length && langs.some((l) => wantLangs.includes(l.toLowerCase()))) score += 10;
      if (r.verified) score += 6;

      return {
        id: r.id,
        name: r.name,
        title: r.title,
        city: r.city,
        lat: r.lat,
        lng: r.lng,
        radiusKm: r.radius_km,
        services,
        languages: langs,
        rating: r.rating,
        reviews: r.reviews,
        responseMinutes: r.response_minutes,
        verified: !!r.verified,
        verification: {
          identity: !!r.identity_verified,
          qualification: !!r.qualification_verified,
          insurance: !!r.insurance_verified,
          background: !!r.background_verified
        },
        topRated: r.rating >= 4.8 && r.reviews > 60,
        acceptsShortNotice: !!r.accepts_short_notice,
        initials: r.initials,
        hue: r.hue,
        distanceKm: distanceKm == null ? null : Number(distanceKm.toFixed(2)),
        score: Number(score.toFixed(2))
      };
    })
    .filter(Boolean)
    .sort((a, b) => b.score - a.score)
    .slice(0, limit);
}
