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

/* Curated queries per slug — still life and nature first (they photograph reliably), people last. */
const Q = {
  'hero-home': ['orchid white flower', 'lotus flower', 'water lily'],
  'signature-lumea': ['lotus flower', 'orchid', 'candle flame'],
  'anti-cellulite': ['salt crystals', 'coffee beans', 'sea salt'],
  'lymphatic-drainage': ['calm lake reflection', 'water ripples', 'still water'],
  'body-sculpt-wrap': ['sea foam waves', 'kelp underwater', 'ocean wave'],
  'cupping-fascia': ['bamboo forest', 'glass sphere', 'bamboo'],
  'aromatherapy': ['lavender field', 'lavender', 'essential oil bottle'],
  'hot-stone': ['stacked stones', 'pebbles beach', 'basalt columns'],
  'lomi-lomi': ['plumeria', 'frangipani flower', 'hibiscus'],
  'duo-couples': ['rose petals', 'pink roses close up', 'peony'],
  'classic-swedish': ['eucalyptus leaves', 'fern leaf', 'green leaves'],
  'deep-tissue': ['zen stones water', 'massage stones', 'black pebbles'],
  'sports-recovery': ['running track', 'athletics track', 'runner sunrise'],
  'prenatal': ['pregnant silhouette', 'pregnancy belly', 'maternity'],
  'thai-yoga': ['Wat Arun', 'thai temple', 'frangipani'],
  'reflexology': ['sand ripples', 'pebbles beach', 'beach sand'],
  'head-neck-shoulder': ['head massage', 'scalp massage', 'massage relaxation'],
  'signature-facial': ['white rose', 'camellia', 'peony white'],
  'hydra-glow': ['water drops', 'dewdrops leaf', 'water droplets macro'],
  'lifting-facial': ['rose quartz', 'quartz crystal', 'jade stone'],
  'enzyme-peel': ['papaya', 'pineapple', 'lemon slices'],
  'mens-facial': ['shaving brush', 'barber shop', 'razor shaving'],
  'eye-decollete': ['chamomile flowers', 'aloe vera', 'cucumber'],
  'hifu-lifting': ['white marble texture', 'silk fabric', 'abstract light'],
  'journal-cellulite-was-massage-wirklich-kann': ['olive branch', 'almond blossom', 'coconut'],
  'journal-zuhause-vorbereiten-mobile-massage': ['tea cup wooden table', 'cozy blanket', 'reading nook'],
  'journal-lymphdrainage-nach-dem-flug': ['airplane wing clouds', 'airplane window', 'clouds from above'],
  'journal-hautpflege-vor-dem-event-sieben-tage': ['rose water', 'glass bottle', 'perfume bottle'],
  'city-berlin': ['Brandenburg Gate', 'Berlin skyline', 'Berlin Museumsinsel'], 'city-muenchen': ['Munich Marienplatz', 'Munich Frauenkirche', 'Munich skyline'],
  'city-hamburg': ['Elbphilharmonie', 'Hamburg Speicherstadt', 'Hamburg harbour'], 'city-frankfurt': ['Frankfurt skyline', 'Frankfurt Main skyline night', 'Frankfurt am Main'],
  'city-koeln': ['Cologne Cathedral', 'Cologne Rhine bridge', 'Köln skyline'], 'city-duesseldorf': ['Düsseldorf Rheinturm', 'Düsseldorf Medienhafen', 'Düsseldorf skyline'],
  'city-stuttgart': ['Stuttgart Schlossplatz', 'Stuttgart Neues Schloss', 'Stuttgart'], 'city-wien': ['Schönbrunn Palace', 'Vienna Rathaus', 'Vienna Hofburg'],
  'city-salzburg': ['Salzburg Hohensalzburg', 'Salzburg old town', 'Salzburg panorama'], 'city-innsbruck': ['Innsbruck Nordkette', 'Innsbruck Goldenes Dachl', 'Innsbruck Inn river'],
  'city-zuerich': ['Zürich Grossmünster', 'Zurich lake', 'Zürich skyline'], 'city-genf': ['Geneva Jet d\'Eau', 'Lake Geneva', 'Geneva lake'],
  'city-basel': ['Basel Münster Rhine', 'Basel Rhine', 'Basel old town'], 'city-lugano': ['Lugano lake', 'Lago di Lugano', 'Lugano'],
  'city-madrid': ['Metropolis Building Madrid', 'Palacio de Cibeles', 'Royal Palace of Madrid'], 'city-barcelona': ['Sagrada Família', 'Barcelona skyline', 'Barcelona Park Güell'],
  'city-valencia': ['Valencia City of Arts and Sciences', 'Ciutat de les Arts i les Ciències', 'Valencia'], 'city-marbella': ['Marbella beach', 'Marbella', 'Puerto Banús'],
  'city-ibiza': ['Ibiza Dalt Vila', 'Ibiza sunset', 'Ibiza'], 'city-palma': ['Cathedral of Palma', 'La Seu Palma', 'Palma de Mallorca']
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
const OK_LICENSE = /^(cc0|cc[ -]by([ -]sa)?([ -][0-9.]+)?|public domain|pd[ -]?[a-z0-9-]*)$/i;
const REJECT = /\b(1[0-8]\d\d|19\d\d|200[0-4])\b|black[ -]and[ -]white|monochrome|grayscale|painting|engraving|drawing|lithograph|map|stereo|postcard|war|military|soldier|medical|disease|hospital|edema|patient|sign|poster|logo|diagram|screenshot|scan|document|book|coin|stamp|statue|portrait|nude|naked/i;
const SIGNATURE_SPAM = /\.(gif|tiff?)$/i;
async function commonsSearch(q, qualityOnly) {
  const search = `${q} filetype:bitmap filemime:image/jpeg fileres:>1600${qualityOnly ? ' hastemplate:QualityImage' : ''}`;
  const s = await (await get(`https://commons.wikimedia.org/w/api.php?action=query&list=search&srnamespace=6&srlimit=20&format=json&srsearch=${encodeURIComponent(search)}`)).json();
  return (s.query?.search || []).map((x) => x.title).filter((t) => /\.jpe?g$/i.test(t) && !REJECT.test(t) && !SIGNATURE_SPAM.test(t));
}
const REJECTED = new Set(existsSync(path.join(OUT, 'rejected.json')) ? JSON.parse(readFileSync(path.join(OUT, 'rejected.json'), 'utf8')) : []);
let currentSlug = '';
async function commons(q) {
  // Treatments: reviewed "Quality images" only — a generated composition beats a mediocre photo.
  // Cities: the open pool is allowed as a fallback (landmark photos are abundant and safe).
  let titles = await commonsSearch(q, true);
  if (!titles.length && currentSlug.startsWith('city-')) titles = await commonsSearch(q, false);
  if (!titles.length) return null;
  const info = await (await get(`https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=url|size|extmetadata&iiurlwidth=1600&format=json&titles=${encodeURIComponent(titles.join('|'))}`)).json();
  const pages = Object.values(info.query?.pages || {}).map((p) => ({ title: p.title, ii: p.imageinfo?.[0] })).filter((p) => p.ii);
  pages.sort((a, b) => titles.indexOf(a.title) - titles.indexOf(b.title));
  for (const p of pages) {
    const m = p.ii.extmetadata || {};
    const lic = (m.LicenseShortName?.value || '').trim();
    const cats = m.Categories?.value || '';
    const year = Number((m.DateTimeOriginal?.value || '').match(/\b(1[0-9]{3}|20[0-9]{2})\b/)?.[1] || 2020);
    const ratio = p.ii.width / p.ii.height;
    if (ratio < 1.2 || ratio > 2.1 || p.ii.width < 1600 || !OK_LICENSE.test(lic) || REJECT.test(cats) || year < 2006 || REJECTED.has(p.ii.descriptionurl)) continue;
    return { url: p.ii.thumburl, credit: { author: (m.Artist?.value || '').replace(/<[^>]+>/g, '').trim() || 'Wikimedia Commons', source: 'Wikimedia Commons', license: lic, page: p.ii.descriptionurl } };
  }
  return null;
}
const provider = process.env.PEXELS_API_KEY ? pexels : process.env.UNSPLASH_ACCESS_KEY ? unsplash : commons;
console.log(`provider: ${provider.name}`);

let done = 0, skipped = 0, missing = [];
for (const [slug, queries] of Object.entries(Q)) {
  const file = path.join(OUT, `${slug}.jpg`);
  currentSlug = slug;
  if (existsSync(file) && !FORCE) { skipped++; continue; }
  let hit = null;
  for (const q of queries) { try { hit = await provider(q); } catch (e) { console.log(`  ! ${slug} "${q}": ${e.message}`); } if (hit) break; await new Promise((r) => setTimeout(r, 250)); }
  if (!hit) { missing.push(slug); console.log(`  – ${slug}: nothing suitable`); continue; }
  try { await download(hit.url, file); credits[slug] = { ...hit.credit, fetchedAt: new Date().toISOString().slice(0, 10) }; done++; console.log(`  ✓ ${slug} ← ${hit.credit.source} · ${hit.credit.author}`); }
  catch (e) { missing.push(slug); console.log(`  ! ${slug} download: ${e.message}`); }
}
writeFileSync(path.join(OUT, 'credits.json'), JSON.stringify(credits, null, 2));
console.log(`\n${done} fetched, ${skipped} kept, ${missing.length} missing${missing.length ? ': ' + missing.join(', ') : ''}`);
