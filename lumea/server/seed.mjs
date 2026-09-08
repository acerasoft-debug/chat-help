import { one, run, now } from './db.mjs';
import { therapists } from '../data/therapists.mjs';

/** Idempotent: seeds the generated directory once, then leaves the table alone. */
export function seedTherapists() {
  const existing = one('SELECT COUNT(*) c FROM therapists');
  if (existing.c > 0) return existing.c;

  const insert = `INSERT INTO therapists
    (id,name,full_name,title,city,country,lat,lng,radius_km,services,languages,equipment,availability,
     years,rating,reviews,response_minutes,verified,accepts_short_notice,status,initials,hue,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`;

  for (const t of therapists) {
    run(insert, t.id, t.name, t.fullName, t.title, t.city, t.country, t.lat, t.lng, t.radiusKm,
      JSON.stringify(t.services), JSON.stringify(t.languages), '[]', '[]',
      t.years, t.rating, t.reviews, t.responseMinutes, 1, t.acceptsShortNotice ? 1 : 0,
      'active', t.initials, t.hue, now());
  }
  // The seeded roster is the curated launch team: fully verified on every axis.
  run(`UPDATE therapists SET identity_verified = 1, qualification_verified = 1, insurance_verified = 1, background_verified = 1`);
  return therapists.length;
}
