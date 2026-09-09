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
import { mkdirSync, writeFileSync, existsSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { services } from '../data/services.mjs';
import { cities } from '../data/cities.mjs';
import { articles } from '../data/journal.mjs';

const OUT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../data/images');
mkdirSync(OUT, { recursive: true });
const FORCE = process.argv.includes('--force');
const PLAN = existsSync(path.join(OUT, 'plan.json')) ? JSON.parse(readFileSync(path.join(OUT, 'plan.json'), 'utf8')) : { mode: 'auto' };
const MODE = process.argv.includes('--candidates') ? 'candidates' : process.argv.includes('--choose') ? 'choose' : PLAN.mode || 'auto';
const CAND_DIR = path.join(OUT, 'candidates');
const UA = 'LUMEA-image-fetch/1.0 (https://github.com/acerasoft-debug/chat-help; concierge@lumea.spa)';

/* Curated queries per slug — still life and nature first (they photograph reliably), people last. */
const Q = {
  'hero-home': ['deepcat:"Massage" spa relaxation woman', 'deepcat:"Spas" massage', 'massage therapy spa'],
  'signature-lumea': ['deepcat:"Massage" back oil', 'deepcat:"Massage" relaxation', 'massage spa candles'],
  'anti-cellulite': ['deepcat:"Massage" legs', 'deepcat:"Massage" thigh', 'anti-cellulite massage'],
  'lymphatic-drainage': ['deepcat:"Massage" lymphatic', 'lymphatic drainage', 'deepcat:"Massage" legs gentle'],
  'body-sculpt-wrap': ['deepcat:"Spas" body wrap', 'body wrap spa', 'deepcat:"Spas" treatment'],
  'cupping-fascia': ['deepcat:"Cupping therapy"', 'cupping therapy back', 'cupping massage'],
  'aromatherapy': ['deepcat:"Massage" aromatherapy', 'deepcat:"Massage" oil', 'aromatherapy massage'],
  'hot-stone': ['deepcat:"Hot stone massage"', 'hot stone massage back', 'deepcat:"Massage" stones'],
  'lomi-lomi': ['deepcat:"Massage" lomi lomi', 'deepcat:"Massage" hawaiian', 'deepcat:"Massage" forearm'],
  'duo-couples': ['deepcat:"Massage" couple', 'couple massage spa', 'deepcat:"Spas" couple'],
  'classic-swedish': ['deepcat:"Swedish massage"', 'deepcat:"Massage" back', 'deepcat:"Massage" table'],
  'deep-tissue': ['deepcat:"Massage" deep tissue', 'deepcat:"Massage" back shoulders', 'deep tissue massage'],
  'sports-recovery': ['deepcat:"Sports massage"', 'sports massage athlete', 'deepcat:"Massage" physiotherapy'],
  'prenatal': ['deepcat:"Massage" pregnancy', 'pregnancy massage', 'deepcat:"Massage" prenatal'],
  'thai-yoga': ['deepcat:"Thai massage"', 'thai massage stretch', 'deepcat:"Massage" thai'],
  'reflexology': ['deepcat:"Foot massage"', 'deepcat:"Reflexology"', 'foot massage spa'],
  'head-neck-shoulder': ['deepcat:"Head massage"', 'deepcat:"Massage" head', 'deepcat:"Massage" shoulders neck'],
  'signature-facial': ['deepcat:"Facials"', 'facial treatment spa', 'deepcat:"Facial massage"'],
  'hydra-glow': ['deepcat:"Facials" mask', 'facial skin care treatment', 'deepcat:"Skin care"'],
  'lifting-facial': ['deepcat:"Facial massage"', 'facial massage spa', 'deepcat:"Facials" massage'],
  'enzyme-peel': ['deepcat:"Facials" mask', 'facial mask spa', 'deepcat:"Skin care" mask'],
  'mens-facial': ['deepcat:"Facials" man', 'deepcat:"Barbershops" shave', 'barber hot towel'],
  'eye-decollete': ['deepcat:"Facials" eyes', 'facial treatment eye', 'deepcat:"Spas" facial'],
  'hifu-lifting': ['deepcat:"Facials" device', 'ultrasound face treatment', 'aesthetic treatment face'],
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
const SUBJECT = /massage|spa|wellness|beauty|facial|skin|physiother|yoga|cupping|reflexolog|salon|treatment|barber|therap/i;
const REJECT = /\b(1[0-8]\d\d|19\d\d|200[0-4])\b|black[ -]and[ -]white|monochrome|grayscale|painting|engraving|drawing|lithograph|map|stereo|postcard|war|military|soldier|medical|disease|hospital|edema|patient|sign|poster|logo|diagram|screenshot|scan|document|book|coin|stamp|statue|nude|naked|erotic|sex|brothel|prostitut|cartoon|illustration|clip ?art|anime|drawing|manga|comic|advertis|screenshot|meme/i;
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
  if ((!titles.length && currentSlug.startsWith('city-')) || MODE === 'candidates' || /deepcat:/.test(q)) titles = titles.concat(await commonsSearch(q, false));
  titles = [...new Set(titles)];
  if (!titles.length) return null;
  const info = await (await get(`https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=url|size|extmetadata&iiurlwidth=1600&format=json&titles=${encodeURIComponent(titles.join('|'))}`)).json();
  const pages = Object.values(info.query?.pages || {}).map((p) => ({ title: p.title, ii: p.imageinfo?.[0] })).filter((p) => p.ii);
  pages.sort((a, b) => titles.indexOf(a.title) - titles.indexOf(b.title));
  const hits = [];
  for (const p of pages) {
    const m = p.ii.extmetadata || {};
    const lic = (m.LicenseShortName?.value || '').trim();
    const cats = m.Categories?.value || '';
    const year = Number((m.DateTimeOriginal?.value || '').match(/\b(1[0-9]{3}|20[0-9]{2})\b/)?.[1] || 2020);
    const ratio = p.ii.width / p.ii.height;
    const subjectOk = currentSlug.startsWith('city-') || currentSlug.startsWith('journal-') || SUBJECT.test(cats + ' ' + p.title);
    if (ratio < 1.15 || ratio > 2.2 || p.ii.width < 1400 || !OK_LICENSE.test(lic) || REJECT.test(cats + ' ' + p.title) || year < 2006 || !subjectOk || REJECTED.has(p.ii.descriptionurl)) continue;
    hits.push({ url: p.ii.thumburl, small: p.ii.thumburl.replace(/\/1600px-/, '/640px-'), credit: { author: (m.Artist?.value || '').replace(/<[^>]+>/g, '').trim() || 'Wikimedia Commons', source: 'Wikimedia Commons', license: lic, page: p.ii.descriptionurl }, title: p.title });
    if (hits.length >= 8) break;
  }
  if (!hits.length) return null;
  return MODE === 'candidates' ? hits : hits[0];
}
const provider = process.env.PEXELS_API_KEY ? pexels : process.env.UNSPLASH_ACCESS_KEY ? unsplash : commons;
console.log(`provider: ${provider.name}`);

let done = 0, skipped = 0, missing = [];
const WANTED = PLAN.slugs && PLAN.slugs.length ? PLAN.slugs : Object.keys(Q);

/* --- candidates mode: small thumbs of up to 6 options per slug for a human pick --- */
if (MODE === 'candidates') {
  mkdirSync(CAND_DIR, { recursive: true });
  const manifest = {};
  for (const slug of WANTED) {
    currentSlug = slug;
    const seen = new Set(); const list = [];
    for (const q of Q[slug] || []) {
      let hits = null;
      try { hits = await provider(q); } catch (e) { console.log(`  ! ${slug} "${q}": ${e.message}`); }
      for (const h of hits || []) { if (!seen.has(h.credit.page) && list.length < 6) { seen.add(h.credit.page); list.push(h); } }
      if (list.length >= 6) break;
      await new Promise((r) => setTimeout(r, 200));
    }
    manifest[slug] = [];
    for (let i = 0; i < list.length; i++) {
      const h = list[i];
      const f = path.join(CAND_DIR, `${slug}--${i + 1}.jpg`);
      try { await download(h.small, f); manifest[slug].push({ n: i + 1, url: h.url, credit: h.credit, title: h.title }); } catch (e) { console.log(`  ! ${slug} cand ${i + 1}: ${e.message}`); }
    }
    console.log(`  ${slug}: ${manifest[slug].length} candidates`);
  }
  writeFileSync(path.join(OUT, 'candidates.json'), JSON.stringify(manifest, null, 2));
  process.exit(0);
}

/* --- choose mode: download the human-picked candidate at full size --- */
if (MODE === 'choose') {
  const manifest = JSON.parse(readFileSync(path.join(OUT, 'candidates.json'), 'utf8'));
  for (const [slug, ref] of Object.entries(PLAN.choices || {})) {
    // A choice is "n" from the slug's own list or "other-slug#n" to borrow from another list.
    const [src, n] = String(ref).includes('#') ? String(ref).split('#') : [slug, ref];
    const pick = (manifest[src] || []).find((c) => c.n === Number(n));
    if (!pick) { console.log(`  – ${slug}: choice ${n} not found`); continue; }
    try { await download(pick.url, path.join(OUT, `${slug}.jpg`)); credits[slug] = { ...pick.credit, fetchedAt: new Date().toISOString().slice(0, 10) }; done++; console.log(`  ✓ ${slug} ← #${n} ${pick.title}`); }
    catch (e) { console.log(`  ! ${slug}: ${e.message}`); }
  }
  writeFileSync(path.join(OUT, 'credits.json'), JSON.stringify(credits, null, 2));
  try { rmSync(CAND_DIR, { recursive: true, force: true }); rmSync(path.join(OUT, 'candidates.json'), { force: true }); } catch { /* fine */ }
  writeFileSync(path.join(OUT, 'plan.json'), JSON.stringify({ mode: 'auto' }, null, 2));
  console.log(`${done} chosen photos fetched`);
  process.exit(0);
}

for (const [slug, queries] of Object.entries(Q)) {
  if (!WANTED.includes(slug)) continue;
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
