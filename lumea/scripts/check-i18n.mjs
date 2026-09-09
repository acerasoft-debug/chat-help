#!/usr/bin/env node
/** Fails the build when any locale is missing a key that another locale has. */
import { t } from '../data/i18n.mjs';
import { services } from '../data/services.mjs';
import { prive } from '../data/prive.mjs';
import { testimonials } from '../data/testimonials.mjs';
import { articles, journalMeta } from '../data/journal.mjs';
import { site } from '../data/site.mjs';

const shape = (o, prefix = '') => Object.entries(o).flatMap(([k, v]) => (v && typeof v === 'object' && !Array.isArray(v) ? shape(v, `${prefix}${k}.`) : [`${prefix}${k}`]));
let problems = 0;
const compare = (label, get) => {
  const ref = new Set(shape(get(site.defaultLocale)));
  for (const l of site.locales) {
    const keys = new Set(shape(get(l)));
    for (const k of ref) if (!keys.has(k)) { problems++; console.log(`✗ ${label} ${l} missing ${k}`); }
    for (const k of keys) if (!ref.has(k)) { problems++; console.log(`✗ ${label} ${l} extra ${k}`); }
  }
};
compare('ui', (l) => t[l]);
compare('prive', (l) => prive[l]);
compare('journalMeta', (l) => journalMeta[l]);
for (const s of services) compare(`service:${s.slug}`, (l) => s.i18n[l]);
for (const a of articles) compare(`article:${a.slug}`, (l) => a[l]);
for (const l of site.locales) if (!testimonials[l]?.length) { problems++; console.log(`✗ testimonials ${l} empty`); }
console.log(problems ? `${problems} i18n problems` : `✓ i18n complete across ${site.locales.join(', ')}`);
process.exit(problems ? 1 : 0);
