#!/usr/bin/env node
/**
 * LUMÉA static build.
 * Zero dependencies: renders every locale × page into dist/, plus sitemap,
 * robots, manifest, brand SVGs and the JSON directory the front-end matches on.
 */
import { mkdir, writeFile, rm, cp } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { site } from '../data/site.mjs';
import { t } from '../data/i18n.mjs';
import { services, moneyServices } from '../data/services.mjs';
import { cities } from '../data/cities.mjs';
import { therapists } from '../data/therapists.mjs';
import { pathFor, absolute } from '../src/lib/html.mjs';
import * as P from '../src/lib/pages.mjs';
import { articles } from '../data/journal.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'dist');

const pages = [];
const add = (urlPath, html, meta = {}) => pages.push({ urlPath, html, ...meta });

/* ------------------------------------------------------------- render all */
for (const locale of site.locales) {
  const L = t[locale];
  add(pathFor(locale, { t: 'home' }), P.homePage(locale), { priority: '1.0', changefreq: 'weekly' });
  add(pathFor(locale, { t: 'services' }), P.servicesPage(locale), { priority: '0.9' });
  add(pathFor(locale, { t: 'skincare' }), P.servicesPage(locale, 'skincare'), { priority: '0.9' });
  add(pathFor(locale, { t: 'cities' }), P.citiesPage(locale), { priority: '0.8' });
  add(pathFor(locale, { t: 'how' }), P.howPage(locale), { priority: '0.7' });
  add(pathFor(locale, { t: 'therapists' }), P.therapistsPage(locale), { priority: '0.9' });
  add(pathFor(locale, { t: 'contact' }), P.contactPage(locale), { priority: '0.5' });
  add(pathFor(locale, { t: 'gift' }), P.giftPage(locale), { priority: '0.5' });
  add(pathFor(locale, { t: 'corporate' }), P.corporatePage(locale), { priority: '0.6' });
  add(pathFor(locale, { t: 'prive' }), P.privePage(locale), { priority: '0.9', changefreq: 'weekly' });
  add(pathFor(locale, { t: 'journal' }), P.journalPage(locale), { priority: '0.7', changefreq: 'weekly' });
  for (const a of articles) add(pathFor(locale, { t: 'article', slug: a.slug }), P.articlePage(locale, a), { priority: '0.6' });
  for (const th of therapists) add(pathFor(locale, { t: 'therapist', id: th.id }), P.therapistPage(locale, th), { priority: '0.6' });
  add(pathFor(locale, { t: 'login' }), P.authPage(locale, 'login'), { noindex: true });
  add(pathFor(locale, { t: 'register' }), P.authPage(locale, 'register'), { noindex: true });
  add(pathFor(locale, { t: 'reset' }), P.resetPage(locale), { noindex: true });
  add(pathFor(locale, { t: 'account' }), P.accountPage(locale), { noindex: true });
  add(pathFor(locale, { t: 'book' }), P.bookPage(locale), { noindex: true });
  for (const which of ['imprint', 'privacy', 'terms']) {
    add(pathFor(locale, { t: which }), P.legalPage(locale, which), { priority: '0.3' });
  }
  for (const s of services) {
    add(pathFor(locale, { t: 'service', slug: s.slug }), P.servicePage(locale, s), { priority: '0.9' });
  }
  for (const c of cities) {
    add(pathFor(locale, { t: 'city', city: c.slug }), P.cityPage(locale, c), { priority: '0.8' });
    for (const slug of moneyServices) {
      add(
        pathFor(locale, { t: 'cityService', city: c.slug, service: slug }),
        P.cityServicePage(locale, c, services.find((s) => s.slug === slug)),
        { priority: '0.7' }
      );
    }
  }
  void L;
}

/* ------------------------------------------------------------------ write */
await rm(OUT, { recursive: true, force: true });
await mkdir(OUT, { recursive: true });

for (const p of pages) {
  const file = path.join(OUT, p.urlPath, 'index.html');
  await mkdir(path.dirname(file), { recursive: true });
  await writeFile(file, p.html);
}

// 404 for GitHub Pages / Netlify / Cloudflare Pages — root + one per locale (the server picks by path prefix).
await writeFile(path.join(OUT, '404.html'), P.notFoundPage(site.defaultLocale));
for (const locale of site.locales) await writeFile(path.join(OUT, locale, '404.html'), P.notFoundPage(locale));

// Journal RSS per locale.
import { journalMeta } from '../data/journal.mjs';
for (const locale of site.locales) {
  const items = articles.map((a) => `    <item>
      <title>${a[locale].title.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</title>
      <link>${absolute(pathFor(locale, { t: 'article', slug: a.slug }))}</link>
      <guid>${absolute(pathFor(locale, { t: 'article', slug: a.slug }))}</guid>
      <pubDate>${new Date(a.date).toUTCString()}</pubDate>
      <description>${a[locale].excerpt.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</description>
    </item>`).join('\n');
  await writeFile(path.join(OUT, locale, 'journal', 'feed.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
    <title>${site.brand} — ${journalMeta[locale].title}</title>
    <link>${absolute(pathFor(locale, { t: 'journal' }))}</link>
    <description>${journalMeta[locale].sub.replace(/&/g, '&amp;')}</description>
    <language>${locale}</language>
${items}
</channel></rss>`);
}

// RFC 9116 security contact + humans.txt
await mkdir(path.join(OUT, '.well-known'), { recursive: true });
await writeFile(path.join(OUT, '.well-known', 'security.txt'), `Contact: mailto:security@lumea.spa\nPreferred-Languages: de, en, es, fr, it\nExpires: ${new Date(Date.now() + 365 * 864e5).toISOString()}\nCanonical: ${site.origin}${site.basePath}/.well-known/security.txt\n`);
await writeFile(path.join(OUT, 'humans.txt'), `/* TEAM */\n${site.brand} — ${site.legalName}\nConcierge: ${site.email}\n\n/* SITE */\nLanguages: ${site.locales.join(', ')}\nStandards: HTML5, CSS3, ES2022, JSON-LD, hreflang\nBuilt with: Node.js, zero dependencies\n`);

// Root: language negotiation with a hard fallback that still links out.
const rootLinks = site.locales
  .map((l) => `<a href="${site.basePath}${pathFor(l, { t: 'home' })}" hreflang="${l}">${l.toUpperCase()}</a>`)
  .join(' · ');
await writeFile(
  path.join(OUT, 'index.html'),
  `<!doctype html><html lang="${site.defaultLocale}"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${site.brand} — ${t[site.defaultLocale].tagline}</title>
<meta name="description" content="${t[site.defaultLocale].metaHomeDesc}">
<link rel="canonical" href="${absolute(pathFor(site.defaultLocale, { t: 'home' }))}">
${site.locales.map((l) => `<link rel="alternate" hreflang="${l}" href="${absolute(pathFor(l, { t: 'home' }))}">`).join('\n')}
<link rel="alternate" hreflang="x-default" href="${absolute(pathFor(site.defaultLocale, { t: 'home' }))}">
<meta http-equiv="refresh" content="0;url=${site.basePath}${pathFor(site.defaultLocale, { t: 'home' })}">
<style>body{font:16px/1.6 system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:#f7f3ec;color:#16130f}a{color:#a8823f;padding:0 .4rem}</style>
</head><body><div style="text-align:center"><h1 style="font-weight:500;letter-spacing:.2em">${site.brand}</h1><p>${rootLinks}</p></div>
<script>
(function(){
  var supported = ${JSON.stringify(site.locales)};
  var map = ${JSON.stringify(Object.fromEntries(site.locales.map((l) => [l, `${site.basePath}${pathFor(l, { t: 'home' })}`])))};
  var langs = (navigator.languages || [navigator.language || '${site.defaultLocale}']);
  for (var i = 0; i < langs.length; i++) {
    var code = String(langs[i]).slice(0, 2).toLowerCase();
    if (supported.indexOf(code) > -1) { location.replace(map[code]); return; }
  }
  location.replace(map['${site.defaultLocale}']);
})();
</script></body></html>`
);

/* ------------------------------------------------------------- sitemap.xml */
const today = new Date().toISOString().slice(0, 10);
const indexable = pages.filter((p) => !p.noindex);
const urlset = indexable
  .map((p) => {
    // Every indexable page exists in all locales, so alternates come from the path shape.
    const rest = p.urlPath.replace(/^\/[a-z]{2}\//, '/');
    const alts = site.locales
      .map((l) => `    <xhtml:link rel="alternate" hreflang="${l}" href="${site.origin}${site.basePath}/${l}${rest}"/>`)
      .join('\n');
    return `  <url>
    <loc>${absolute(p.urlPath)}</loc>
    <lastmod>${today}</lastmod>
    <changefreq>${p.changefreq || 'monthly'}</changefreq>
    <priority>${p.priority || '0.6'}</priority>
${alts}
  </url>`;
  })
  .join('\n');

await writeFile(
  path.join(OUT, 'sitemap.xml'),
  `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
${urlset}
</urlset>`
);

await writeFile(
  path.join(OUT, 'robots.txt'),
  `User-agent: *
Allow: /
Disallow: /*/anmelden/
Disallow: /*/registrieren/
Disallow: /*/konto/
Disallow: /*/buchen/
Disallow: /*/sign-in/
Disallow: /*/register/
Disallow: /*/account/
Disallow: /*/book/
Disallow: /*/entrar/
Disallow: /*/registro/
Disallow: /*/cuenta/
Disallow: /*/reservar/
Disallow: /*/connexion/
Disallow: /*/inscription/
Disallow: /*/compte/
Disallow: /*/reserver/
Disallow: /*/accedi/
Disallow: /*/registrati/
Disallow: /*/account/
Disallow: /*/prenota/
Disallow: /api/

Sitemap: ${site.origin}${site.basePath}/sitemap.xml
`
);

await writeFile(
  path.join(OUT, 'manifest.webmanifest'),
  JSON.stringify(
    {
      name: `${site.brand} — ${t[site.defaultLocale].tagline}`,
      short_name: site.brandAscii,
      start_url: `${site.basePath}/`,
      display: 'standalone',
      background_color: '#f7f3ec',
      theme_color: '#16130f',
      lang: site.defaultLocale,
      icons: [{ src: `${site.basePath}/assets/favicon.svg`, sizes: 'any', type: 'image/svg+xml', purpose: 'any' }]
    },
    null,
    2
  )
);

/* ---------------------------------------------------------------- assets */
const assetsOut = path.join(OUT, 'assets');
await mkdir(assetsOut, { recursive: true });
await cp(path.join(ROOT, 'src/assets'), assetsOut, { recursive: true });
if (existsSync(path.join(ROOT, 'data/images'))) await cp(path.join(ROOT, 'data/images'), path.join(assetsOut, 'img'), { recursive: true });

const favicon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
<rect width="64" height="64" rx="14" fill="#16130f"/>
<text x="32" y="44" text-anchor="middle" font-family="Georgia,serif" font-size="34" fill="#c9a961">L</text>
</svg>`;
await writeFile(path.join(assetsOut, 'favicon.svg'), favicon);
await writeFile(
  path.join(assetsOut, 'logo.svg'),
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80"><rect width="320" height="80" fill="none"/>
<text x="10" y="52" font-family="Georgia,serif" font-size="40" letter-spacing="8" fill="#16130f">LUMÉA</text></svg>`
);
await writeFile(
  path.join(assetsOut, 'og.svg'),
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="#16130f"/><stop offset="1" stop-color="#2a2318"/></linearGradient></defs>
<rect width="1200" height="630" fill="url(#g)"/>
<circle cx="1010" cy="130" r="230" fill="#c9a961" opacity=".14"/>
<text x="90" y="300" font-family="Georgia,serif" font-size="96" letter-spacing="18" fill="#f4efe6">LUMÉA</text>
<text x="94" y="360" font-family="Helvetica,Arial,sans-serif" font-size="30" letter-spacing="6" fill="#c9a961">${t[site.defaultLocale].tagline.toUpperCase()}</text>
<text x="94" y="430" font-family="Helvetica,Arial,sans-serif" font-size="26" fill="#cdc5b7">DE · AT · CH · ES — ${site.trust.therapists}+ ${t.en.footer.therapists}</text>
</svg>`
);

/* ------------------------------- directory the front-end matches against */
await writeFile(
  path.join(assetsOut, 'data.json'),
  JSON.stringify({
    generatedAt: new Date().toISOString(),
    cities: cities.map((c) => ({ slug: c.slug, name: c.name, country: c.country, lat: c.lat, lng: c.lng, tz: c.tz, therapists: c.therapists })),
    therapists: therapists.map((th) => ({
      id: th.id, name: th.name, fullName: th.fullName, title: th.title, city: th.city, lat: th.lat, lng: th.lng,
      radiusKm: th.radiusKm, services: th.services, languages: th.languages, rating: th.rating,
      reviews: th.reviews, responseMinutes: th.responseMinutes, topRated: th.topRated,
      acceptsShortNotice: th.acceptsShortNotice, initials: th.initials, hue: th.hue
    })),
    services: Object.fromEntries(
      site.locales.map((l) => [l, Object.fromEntries(services.map((s) => [s.slug, s.i18n[l].name]))])
    )
  })
);

// GitHub Pages must not run Jekyll over the output.
await writeFile(path.join(OUT, '.nojekyll'), '');
if (existsSync(path.join(ROOT, 'CNAME'))) await cp(path.join(ROOT, 'CNAME'), path.join(OUT, 'CNAME'));

const bytes = pages.reduce((n, p) => n + Buffer.byteLength(p.html), 0);
console.log(`✓ ${pages.length} pages  ·  ${indexable.length} indexable  ·  ${(bytes / 1048576).toFixed(2)} MB`);
console.log(`  locales: ${site.locales.join(', ')}  ·  services: ${services.length}  ·  cities: ${cities.length}`);
console.log(`  origin:  ${site.origin}${site.basePath}`);
