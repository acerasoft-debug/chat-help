#!/usr/bin/env node
/**
 * Fills data/images/ with real, commercially licensed photography — one per
 * treatment, city, journal article and the hero — from the first provider that
 * has credentials, falling back to Wikimedia Commons (no key needed):
 *   PEXELS_API_KEY      → Pexels (best aesthetics)
 *   UNSPLASH_ACCESS_KEY → Unsplash
 *   (none)              → Wikimedia Commons, landscape ≥ 1400 px, CC-BY / CC-BY-SA / CC0 / PD
 * Writes credits.json alongside so the imprint can render attributions.
 * Run on a machine with internet (or the "LUMÉA — fetch images" workflow).
 */
import { mkdirSync, writeFileSync, existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { services } from '../data/services.mjs';
import { cities } from '../data/cities.mjs';
import { articles } from '../data/journal.mjs';

const OUT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../data/images');
mkdirSync(OUT, { recursive: true });
const FORCE = process.argv.includes('--force');
const UA = 'LUMEA-image-fetch/1.0 (https://github.com/acerasoft-debug/chat-help; concierge@lumea.spa)';

/* Curated, subject-specific queries: [primary, fallback]. Calm, editorial, no faces where possible. */
const Q = {
  'hero-home': ['spa towels orchid candle', 'spa still life'],
  'signature-lumea': ['massage candles spa relaxation', 'spa candles'],
  'anti-cellulite': ['body massage legs spa', 'massage therapy legs'],
  'lymphatic-drainage': ['lymphatic drainage massage', 'back massage hands'],
  'body-sculpt-wrap': ['seaweed spa body wrap', 'algae spa'],
  'cupping-fascia': ['cupping therapy back', 'cupping massage'],
  'aromatherapy': ['essential oils lavender bottle', 'lavender aromatherapy'],
  'hot-stone': ['hot stone massage back', 'basalt massage stones'],
  'lomi-lomi': ['plumeria flowers spa', 'frangipani flower water'],
  'duo-couples': ['couple spa massage', 'rose petals spa'],
  'classic-swedish': ['massage therapy table', 'swedish massage'],
  'deep-tissue': ['deep tissue back massage', 'back massage'],
  'sports-recovery': ['sports massage athlete', 'physiotherapy massage'],
  'prenatal': ['pregnancy massage', 'pregnant woman relaxing'],
  'thai-yoga': ['thai massage stretching', 'thai massage'],
  'reflexology': ['foot reflexology massage', 'foot massage spa'],
  'head-neck-shoulder': ['head massage relaxation', 'scalp massage'],
  'signature-facial': ['facial treatment spa mask', 'facial spa'],
  'hydra-glow': ['facial skincare glowing skin', 'skin care treatment'],
  'lifting-facial': ['gua sha facial', 'face massage jade roller'],
  'enzyme-peel': ['papaya fruit fresh', 'enzyme peel skincare'],
  'mens-facial': ['men facial skincare', 'man face treatment'],
  'eye-decollete': ['eye mask spa cucumber', 'eye treatment spa'],
  'hifu-lifting': ['ultrasound skin treatment face', 'aesthetic skin device treatment'],
  'journal-cellulite-was-massage-wirklich-kann': ['body lotion legs skin', 'skin care body'],
  'journal-zuhause-vorbereiten-mobile-massage': ['living room calm candles', 'cozy living room minimal'],
  'journal-lymphdrainage-nach-dem-flug': ['airplane window clouds', 'travel airplane wing'],
  'journal-hautpflege-vor-dem-event-sieben-tage': ['skincare serum bottle', 'cosmetics minimal'],
  'city-berlin': ['Brandenburg Gate Berlin', 'Berlin skyline'], 'city-muenchen': ['Munich Marienplatz', 'Munich Frauenkirche'],
  'city-hamburg': ['Hamburg Elbphilharmonie', 'Hamburg Speicherstadt'], 'city-frankfurt': ['Frankfurt skyline', 'Frankfurt am Main'],
  'city-koeln': ['Cologne Cathedral Rhine', 'Cologne skyline'], 'city-duesseldorf': ['Düsseldorf Rheinturm', 'Düsseldorf Medienhafen'],
  'city-stuttgart': ['Stuttgart Schlossplatz', 'Stuttgart skyline'], 'city-wien': ['Vienna Hofburg', 'Vienna Schönbrunn'],
  'city-salzburg': ['Salzburg old town Hohensalzburg', 'Salzburg panorama'], 'city-innsbruck': ['Innsbruck Nordkette', 'Innsbruck old town'],
  'city-zuerich': ['Zurich lake Grossmünster', 'Zurich skyline'], 'city-genf': ['Geneva Jet d\'Eau lake', 'Geneva lake'],
  'city-basel': ['Basel Rhine Münster', 'Basel old town'], 'city-lugano': ['Lugano lake', 'Lago di Lugano'],
  'city-madrid': ['Madrid Gran Via', 'Madrid Retiro'], 'city-barcelona': ['Barcelona Sagrada Familia', 'Barcelona skyline'],
  'city-valencia': ['Valencia City of Arts and Sciences', 'Valencia'], 'city-marbella': ['Marbella beach', 'Marbella Puerto Banus'],
  'city-ibiza': ['Ibiza Dalt Vila', 'Ibiza beach'], 'city-palma': ['Palma Cathedral Mallorca', 'Palma de Mallorca']
};
for (const s of services) if (!Q[s.slug]) Q[s.slug] = [s.i18n.en.name, 'spa massage'];
for (const c of cities) if (!Q[`city-${c.slug}`]) Q[`city-${c.slug}`] = [c.name.en, `${c.name.en} skyline`];
for (const a of articles) if (!Q[`journal-${a.slug}`]) Q[`journal-${a.slug}`] = ['spa wellness', 'spa'];

const credits = existsSync(path.join(OUT, 'credits.json')) ? JSON.parse(readFileSync(path.join(OUT, 'credits.json'), 'utf8')) : {};
const get = async (url, headers = {}) => { const r = await fetch(url, { headers: { 'user-agent': UA, ...headers } }); if (!r.ok) throw new Error(`${r.status} ${url}`); return r; };
const download = async (url, file) => { const r = await get(url); writeFileSync(file, Buffer.from(await r.arrayBuffer())); };

/* ---- providers: each returns { url, credit } or null ------------------- */
async function pexels(q) {
  const j = await (await get(`https://api.pexels.com/v1/search?query=${encodeURIComponent(q)}&orientation=landscape&size=large&per_page=6`, { Authorization: process.env.PEXELS_API_KEY })).json();
  const p = j.photos?.[0]; if (!p) return null;
  return { url: `${p.src.original}?auto=compress&cs=tinysrgb&w=1600`, credit: { author: p.photographer, source: 'Pexels', license: 'Pexels License', page: p.url } };
}
async function unsplash(q) {
  const j = await (await get(`https://api.unsplash.com/search/photos?query=${encodeURIComponent(q)}&orientation=landscape&content_filter=high&per_page=6`, { Authorization: `Client-ID ${process.env.UNSPLASH_ACCESS_KEY}` })).json();
  const p = j.results?.[0]; if (!p) return null;
  return { url: `${p.urls.raw}&w=1600&q=80&fm=jpg&fit=max`, credit: { author: p.user.name, source: 'Unsplash', license: 'Unsplash License', page: p.links.html } };
}
const OK_LICENSE = /cc0|cc-by(-sa)?(-[0-9.]+)?$|public domain|pd/i;
async function commons(q) {
  const search = `${q} filetype:bitmap -filemime:svg fileres:>1400`;
  const s = await (await get(`https://commons.wikimedia.org/w/api.php?action=query&list=search&srnamespace=6&srlimit=12&format=json&srsearch=${encodeURIComponent(search)}`)).json();
  const titles = (s.query?.search || []).map((x) => x.title).filter((t) => /\.(jpe?g|png|webp)$/i.test(t));
  if (!titles.length) return null;
  const info = await (await get(`https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=url|size|extmetadata&iiurlwidth=1600&format=json&titles=${encodeURIComponent(titles.join('|'))}`)).json();
  const pages = Object.values(info.query?.pages || {}).map((p) => ({ title: p.title, ii: p.imageinfo?.[0] })).filter((p) => p.ii);
  pages.sort((a, b) => titles.indexOf(a.title) - titles.indexOf(b.title));
  for (const p of pages) {
    const m = p.ii.extmetadata || {};
    const lic = m.LicenseShortName?.value || '';
    const ratio = p.ii.width / p.ii.height;
    if (ratio < 1.15 || ratio > 2.2 || !OK_LICENSE.test(lic)) continue;
    return { url: p.ii.thumburl, credit: { author: (m.Artist?.value || '').replace(/<[^>]+>/g, '').trim() || 'Wikimedia Commons', source: 'Wikimedia Commons', license: lic, page: p.ii.descriptionurl } };
  }
  return null;
}
const provider = process.env.PEXELS_API_KEY ? pexels : process.env.UNSPLASH_ACCESS_KEY ? unsplash : commons;
console.log(`provider: ${provider.name}`);

let done = 0, skipped = 0, missing = [];
for (const [slug, queries] of Object.entries(Q)) {
  const file = path.join(OUT, `${slug}.jpg`);
  if (existsSync(file) && !FORCE) { skipped++; continue; }
  let hit = null;
  for (const q of queries) { try { hit = await provider(q); } catch (e) { console.log(`  ! ${slug} "${q}": ${e.message}`); } if (hit) break; await new Promise((r) => setTimeout(r, 250)); }
  if (!hit) { missing.push(slug); console.log(`  – ${slug}: nothing suitable`); continue; }
  try { await download(hit.url, file); credits[slug] = { ...hit.credit, fetchedAt: new Date().toISOString().slice(0, 10) }; done++; console.log(`  ✓ ${slug} ← ${hit.credit.source} · ${hit.credit.author}`); }
  catch (e) { missing.push(slug); console.log(`  ! ${slug} download: ${e.message}`); }
}
writeFileSync(path.join(OUT, 'credits.json'), JSON.stringify(credits, null, 2));
console.log(`\n${done} fetched, ${skipped} kept, ${missing.length} missing${missing.length ? ': ' + missing.join(', ') : ''}`);
