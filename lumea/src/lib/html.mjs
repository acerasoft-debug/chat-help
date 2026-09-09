import { site, localeMeta } from '../../data/site.mjs';
import { t, fmt } from '../../data/i18n.mjs';
import { art, motifForCategory } from './art.mjs';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import nodePath from 'node:path';

/* ------------------------------------------------------------------ utils */
export const esc = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export const attr = (s) => esc(s).replace(/\n/g, ' ');
export { fmt };

/** Localised URL segments. One entry per logical page type. */
const SEG = {
  de: { services: 'behandlungen', skincare: 'hautpflege', cities: 'staedte', how: 'ablauf', therapists: 'therapeut-werden', login: 'anmelden', register: 'registrieren', account: 'konto', book: 'buchen', contact: 'kontakt', imprint: 'impressum', privacy: 'datenschutz', terms: 'agb', gift: 'gutscheine', corporate: 'unternehmen', prive: 'prive', journal: 'journal', profiles: 'therapeuten', reset: 'passwort-zuruecksetzen' },
  en: { services: 'treatments', skincare: 'skincare', cities: 'cities', how: 'how-it-works', therapists: 'become-a-therapist', login: 'sign-in', register: 'register', account: 'account', book: 'book', contact: 'contact', imprint: 'imprint', privacy: 'privacy', terms: 'terms', gift: 'gift-vouchers', corporate: 'for-companies', prive: 'prive', journal: 'journal', profiles: 'therapists', reset: 'reset-password' },
  es: { services: 'tratamientos', skincare: 'estetica', cities: 'ciudades', how: 'como-funciona', therapists: 'trabaja-con-nosotros', login: 'entrar', register: 'registro', account: 'cuenta', book: 'reservar', contact: 'contacto', imprint: 'aviso-legal', privacy: 'privacidad', terms: 'condiciones', gift: 'tarjetas-regalo', corporate: 'empresas', prive: 'prive', journal: 'journal', profiles: 'terapeutas', reset: 'restablecer' },
  fr: { services: 'soins', skincare: 'soins-du-visage', cities: 'villes', how: 'comment-ca-marche', therapists: 'devenir-therapeute', login: 'connexion', register: 'inscription', account: 'compte', book: 'reserver', contact: 'contact', imprint: 'mentions-legales', privacy: 'confidentialite', terms: 'conditions', gift: 'cartes-cadeaux', corporate: 'entreprises', prive: 'prive', journal: 'journal', profiles: 'therapeutes', reset: 'reinitialiser' },
  it: { services: 'trattamenti', skincare: 'skincare', cities: 'citta', how: 'come-funziona', therapists: 'diventa-terapista', login: 'accedi', register: 'registrati', account: 'account', book: 'prenota', contact: 'contatti', imprint: 'note-legali', privacy: 'privacy', terms: 'condizioni', gift: 'buoni-regalo', corporate: 'aziende', prive: 'prive', journal: 'journal', profiles: 'terapisti', reset: 'reimposta-password' }
};

/**
 * Resolve a logical page key to its path in one locale. Because every page is
 * addressed by key rather than by string, hreflang alternates and the sitemap
 * fall out of the same function and can never drift apart.
 */
export function pathFor(locale, key) {
  const s = SEG[locale];
  const base = `/${locale}`;
  switch (key.t) {
    case 'home': return `${base}/`;
    case 'services': return `${base}/${s.services}/`;
    case 'service': return `${base}/${s.services}/${key.slug}/`;
    case 'skincare': return `${base}/${s.skincare}/`;
    case 'cities': return `${base}/${s.cities}/`;
    case 'city': return `${base}/${s.cities}/${key.city}/`;
    case 'cityService': return `${base}/${s.cities}/${key.city}/${key.service}/`;
    case 'article': return `${base}/${s.journal}/${key.slug}/`;
    case 'therapist': return `${base}/${s.profiles}/${key.id}/`;
    default: return `${base}/${s[key.t] || key.t}/`;
  }
}

export const withBase = (p) => `${site.basePath}${p}`;
export const absolute = (p) => `${site.origin}${site.basePath}${p}`;

export const CURRENCY_SYMBOL = { EUR: '€', CHF: 'CHF' };
export function money(amount, currency = 'EUR') {
  return currency === 'CHF' ? `CHF ${amount}` : `${amount} €`;
}

/* ---------------------------------------------------------------- imagery */
const IMG_DIR = nodePath.resolve(nodePath.dirname(fileURLToPath(import.meta.url)), '../../data/images');
/** Real photography wins when data/images/<slug>.jpg|webp exists; otherwise a generated composition. */
export function media({ slug, motif, accent, label = '', alt = '', className = '', w = 800, h = 600 }) {
  for (const ext of ['jpg', 'webp', 'png']) {
    if (existsSync(nodePath.join(IMG_DIR, `${slug}.${ext}`))) {
      return `<div class="media ${className}"><img src="${withBase(`/assets/img/${slug}.${ext}`)}" alt="${attr(alt)}" loading="lazy" width="${w}" height="${h}"></div>`;
    }
  }
  return `<div class="media ${className}">${art({ motif, accent, seed: slug, w, h, label })}</div>`;
}
export { motifForCategory };

/* ------------------------------------------------------------- structured */
export function jsonLd(obj) {
  return `<script type="application/ld+json">${JSON.stringify(obj).replace(/</g, '\\u003c')}</script>`;
}

export function orgLd() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    '@id': `${site.origin}/#organization`,
    name: site.brand,
    legalName: site.legalName,
    url: site.origin,
    logo: `${site.origin}${site.basePath}/assets/logo.svg`,
    email: site.email,
    telephone: site.phone,
    foundingDate: String(site.founded),
    areaServed: ['DE', 'AT', 'CH', 'ES'],
    sameAs: Object.values(site.social),
    aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: site.trust.rating,
      reviewCount: site.trust.reviewCount,
      bestRating: 5
    }
  };
}

export function faqLd(items) {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: items.map((f) => ({
      '@type': 'Question',
      name: f.q,
      acceptedAnswer: { '@type': 'Answer', text: f.a }
    }))
  };
}

export function breadcrumbLd(trail) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: trail.map((c, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: c.label,
      item: absolute(c.href)
    }))
  };
}

/* ------------------------------------------------------------------ chrome */
function header(locale, key) {
  const L = t[locale];
  const link = (pk, label) => {
    const href = withBase(pathFor(locale, pk));
    const on = key.t === pk.t ? ' class="is-active"' : '';
    return `<a href="${href}"${on}>${esc(label)}</a>`;
  };
  const langs = site.locales
    .map((l) => {
      const href = withBase(pathFor(l, key));
      const on = l === locale ? ' class="is-active"' : '';
      return `<a href="${href}" hreflang="${l}" lang="${l}"${on}>${localeMeta[l].flag}</a>`;
    })
    .join('');

  return `<header class="header" id="siteHeader">
  <div class="wrap header__in">
    <a class="logo" href="${withBase(pathFor(locale, { t: 'home' }))}" aria-label="${attr(site.brand)}">
      ${esc(site.brand)}<small>${esc(L.tagline)}</small>
    </a>
    <button class="burger" type="button" aria-label="Menu" aria-expanded="false" data-nav-toggle><span></span><span></span><span></span></button>
    <nav class="nav" id="mainNav">
      ${link({ t: 'services' }, L.nav.services)}
      ${link({ t: 'skincare' }, L.nav.skincare)}
      ${link({ t: 'cities' }, L.nav.cities)}
      ${link({ t: 'prive' }, 'Privé')}
      ${link({ t: 'therapists' }, L.nav.therapists)}
      <div class="nav__actions">
        <div class="langs">${langs}</div>
        <a class="btn btn--ghost btn--sm" href="${withBase(pathFor(locale, { t: 'login' }))}" data-auth-anon>${esc(L.nav.login)}</a>
        <a class="btn btn--ghost btn--sm hidden" href="${withBase(pathFor(locale, { t: 'account' }))}" data-auth-user>${esc(L.nav.account)}</a>
        <a class="btn btn--gold btn--sm" href="${withBase(pathFor(locale, { t: 'book' }))}">${esc(L.nav.book)}</a>
      </div>
    </nav>
  </div>
</header>`;
}

function footer(locale, services, cities) {
  const L = t[locale];
  const li = (pk, label) => `<li><a href="${withBase(pathFor(locale, pk))}">${esc(label)}</a></li>`;
  const topServices = services.filter((s) => s.popular).slice(0, 7);
  const topCities = cities.filter((c) => c.flagship).concat(cities.filter((c) => !c.flagship)).slice(0, 9);

  return `<footer class="footer">
  <div class="wrap">
    <div class="footer__grid">
      <div>
        <div class="logo">${esc(site.brand)}<small>${esc(L.tagline)}</small></div>
        <p style="font-size:.87rem;max-width:34ch">${esc(L.footer.claim)}</p>
        <p style="font-size:.87rem"><a href="mailto:${attr(site.email)}">${esc(site.email)}</a><br><a href="tel:${attr(site.phoneHref)}">${esc(site.phone)}</a></p>
      </div>
      <div>
        <h4>${esc(L.footer.services)}</h4>
        <ul>${topServices.map((s) => li({ t: 'service', slug: s.slug }, s.i18n[locale].name)).join('')}
        ${li({ t: 'services' }, L.common.all)}</ul>
      </div>
      <div>
        <h4>${esc(L.footer.cities)}</h4>
        <ul>${topCities.map((c) => li({ t: 'city', city: c.slug }, c.name[locale])).join('')}</ul>
      </div>
      <div>
        <h4>${esc(L.footer.company)}</h4>
        <ul>
          ${li({ t: 'how' }, L.nav.how)}
          ${li({ t: 'prive' }, 'Luméa Privé')}
          ${li({ t: 'journal' }, L.nav.journal)}
          ${li({ t: 'therapists' }, L.footer.therapists)}
          ${li({ t: 'corporate' }, L.footer.corporate)}
          ${li({ t: 'gift' }, L.footer.gift)}
          ${li({ t: 'contact' }, L.footer.contact)}
        </ul>
      </div>
      <div>
        <h4>${esc(L.footer.legal)}</h4>
        <ul>
          ${li({ t: 'imprint' }, L.footer.imprint)}
          ${li({ t: 'privacy' }, L.footer.privacy)}
          ${li({ t: 'terms' }, L.footer.terms)}
          ${li({ t: 'login' }, L.nav.login)}
          ${li({ t: 'register' }, t[locale].auth.register)}
        </ul>
      </div>
    </div>
    <div class="footer__bar">
      <span>© ${new Date().getFullYear()} ${esc(site.legalName)}. ${esc(L.footer.rights)}</span>
      <span>DE · AT · CH · ES</span>
    </div>
  </div>
</footer>`;
}

/* ------------------------------------------------------------------ layout */
export function layout({ locale, key, title, description, body, extraLd = [], services, cities, noindex = false, bodyClass = '' }) {
  const meta = localeMeta[locale];
  const canonical = absolute(pathFor(locale, key));
  const alternates = site.locales
    .map((l) => `<link rel="alternate" hreflang="${localeMeta[l].hreflang}" href="${absolute(pathFor(l, key))}">`)
    .join('\n  ');

  const ld = [orgLd(), ...extraLd].map(jsonLd).join('\n');

  return `<!doctype html>
<html lang="${meta.htmlLang}" dir="${meta.dir}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${esc(title)}</title>
<meta name="description" content="${attr(description)}">
${noindex ? '<meta name="robots" content="noindex,follow">' : '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">'}
<link rel="canonical" href="${canonical}">
${alternates}
<link rel="alternate" hreflang="x-default" href="${absolute(pathFor(site.defaultLocale, key))}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="${attr(site.brand)}">
<meta property="og:locale" content="${{ de: 'de_DE', en: 'en_GB', es: 'es_ES', fr: 'fr_FR', it: 'it_IT' }[locale]}">
<meta property="og:title" content="${attr(title)}">
<meta property="og:description" content="${attr(description)}">
<meta property="og:url" content="${canonical}">
<meta property="og:image" content="${absolute('/assets/og.png')}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${attr(title)}">
<meta name="twitter:description" content="${attr(description)}">
<meta name="twitter:image" content="${absolute('/assets/og.png')}">
<meta name="theme-color" content="#16130f" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#f7f3ec" media="(prefers-color-scheme: light)">
${site.verification.google ? `<meta name="google-site-verification" content="${attr(site.verification.google)}">` : ''}
${site.verification.bing ? `<meta name="msvalidate.01" content="${attr(site.verification.bing)}">` : ''}
<link rel="icon" href="${withBase('/assets/favicon.svg')}" type="image/svg+xml">
<link rel="apple-touch-icon" href="${withBase('/assets/favicon.svg')}">
<link rel="manifest" href="${withBase('/manifest.webmanifest')}">
<link rel="sitemap" type="application/xml" href="${withBase('/sitemap.xml')}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="${withBase('/assets/styles.css')}">
${site.analytics.plausibleDomain ? `<script>window.__plausible=${JSON.stringify(site.analytics.plausibleDomain)};</script>` : ''}
${ld}
</head>
<body${bodyClass ? ` class="${bodyClass}"` : ''} data-locale="${locale}" data-base="${withBase('')}">
<a class="skip" href="#main">Skip to content</a>
${header(locale, key)}
<main id="main">
${body}
</main>
${footer(locale, services, cities)}
<div class="cookie" id="cookieBar" hidden role="dialog" aria-live="polite">
  <p>${esc(t[locale].x.cookie.text)} <a href="${withBase(pathFor(locale, { t: 'privacy' }))}">${esc(t[locale].x.cookie.more)}</a></p>
  <div class="cookie__actions"><button class="btn btn--ghost btn--sm" data-cookie="necessary">${esc(t[locale].x.cookie.decline)}</button><button class="btn btn--gold btn--sm" data-cookie="all">${esc(t[locale].x.cookie.accept)}</button></div>
</div>
<div class="sticky-cta" id="stickyCta">
  <a class="btn btn--ghost btn--sm" href="https://wa.me/${attr(site.whatsapp.replace(/\D/g, ''))}" rel="noopener" target="_blank">${esc(t[locale].conv.stickyWa)}</a>
  <a class="btn btn--gold" href="${withBase(pathFor(locale, { t: 'book' }))}">${esc(t[locale].conv.stickyBook)}</a>
</div>
<script>window.__x=${JSON.stringify(t[locale].x)};window.__geoTpl=${JSON.stringify(t[locale].hero.locatedIn)};window.__applySuccess=${JSON.stringify(t[locale].apply.success)};window.__bookSuccess=${JSON.stringify(fmt(t[locale].booking.success, { minutes: site.trust.responseMinutes }))};</script>
<script src="${withBase('/assets/app.js')}" defer></script>
</body>
</html>`;
}

/* -------------------------------------------------------------- components */
export function crumbs(locale, trail) {
  const items = trail
    .map((c, i) =>
      i === trail.length - 1
        ? `<span aria-current="page">${esc(c.label)}</span>`
        : `<a href="${withBase(c.href)}">${esc(c.label)}</a><span aria-hidden="true">/</span>`
    )
    .join(' ');
  return `<div class="wrap"><nav class="crumbs" aria-label="Breadcrumb">${items}</nav></div>`;
}

export function serviceCard(locale, s, currency = 'EUR') {
  const c = s.i18n[locale];
  const L = t[locale];
  return `<a class="card" href="${withBase(pathFor(locale, { t: 'service', slug: s.slug }))}">
    ${media({ slug: s.slug, motif: motifForCategory[s.category], accent: s.accent, alt: c.name, className: 'card__media' })}
    <div class="card__top">
      <div>
        <h3>${esc(c.name)}</h3>
        <span class="card__tag">${esc(c.tagline)}</span>
      </div>
      <div class="card__price"><small>${esc(L.common.from)}</small>${esc(money(s.price[currency], currency))}</div>
    </div>
    <p>${esc(c.short)}</p>
    <div class="card__foot">
      <span>${s.durations.join(' / ')} ${esc(L.common.minutes)}</span>
      <span class="btn btn--gold btn--sm">${esc(L.conv.cardBook)} &rarr;</span>
    </div>
  </a>`;
}

export function cityCard(locale, city, countries) {
  const L = t[locale];
  return `<a class="card" href="${withBase(pathFor(locale, { t: 'city', city: city.slug }))}">
    ${media({ slug: `city-${city.slug}`, motif: 'skyline', accent: { DE: '#a78d5e', AT: '#8f9b8a', CH: '#8fa0ad', ES: '#c49a6c' }[city.country], label: city.name[locale].charAt(0), alt: city.name[locale], className: 'card__media card__media--wide', w: 800, h: 450 })}
    <div class="card__top">
      <div>
        <span class="card__tag">${esc(countries[city.country][locale])}</span>
        <h3 style="margin-top:.3rem">${esc(city.name[locale])}</h3>
      </div>
    </div>
    <p>${esc(city.districts.slice(0, 4).join(' · '))}</p>
    <div class="card__foot">
      <span>${fmt(L.city.therapistCount, { count: city.therapists })}</span>
      <span class="card__arrow">&rarr;</span>
    </div>
  </a>`;
}

export function therapistCard(locale, th, services, distanceKm = null) {
  const L = t[locale];
  const href = withBase(pathFor(locale, { t: 'therapist', id: th.id }));
  const names = th.services
    .slice(0, 3)
    .map((sl) => services.find((s) => s.slug === sl)?.i18n[locale].name)
    .filter(Boolean);
  return `<article class="card card--hover t-card">
    <div class="avatar" style="background:hsl(${th.hue} 32% 42%)">${esc(th.initials)}</div>
    <div class="t-card__body">
      <div class="t-card__name">
        <strong><a href="${href}">${esc(th.name)}</a></strong>
        ${th.verified ? `<span class="badge badge--forest">${esc(L.match.verified)}</span>` : ''}
        ${th.topRated ? `<span class="badge">${esc(L.match.topRated)}</span>` : ''}
      </div>
      <div class="small muted">${esc(th.title)}</div>
      <div class="t-meta">
        <span class="stars">★ ${th.rating}</span>
        <span>${fmt(L.match.rating, { rating: th.rating, count: th.reviews })}</span>
        ${distanceKm != null ? `<span>${fmt(L.match.distance, { km: distanceKm.toFixed(1) })}</span>` : ''}
        <span>${fmt(L.match.responds, { min: th.responseMinutes })}</span>
        <span>${esc(L.match.speaks)}: ${esc(th.languages.join(', '))}</span>
      </div>
      <div class="t-tags">${names.map((n) => `<span class="t-tag">${esc(n)}</span>`).join('')}</div>
      <div class="t-tags t-tags--verify" aria-label="Verification">
        <span class="t-tag t-tag--ok">✓ ${esc(L.match.idOk)}</span>
        <span class="t-tag t-tag--ok">✓ ${esc(L.match.certOk)}</span>
        <span class="t-tag t-tag--ok">✓ ${esc(L.match.insOk)}</span>
        <span class="t-tag t-tag--ok">✓ ${esc(L.match.bgOk)}</span>
      </div>
      <p style="margin:.8rem 0 0"><a class="link-btn" href="${href}">${esc(L.match.profile)} &rarr;</a></p>
    </div>
  </article>`;
}

export function sectionHead(eyebrow, title, sub, center = false) {
  return `<div class="section-head${center ? ' center' : ''}">
    ${eyebrow ? `<p class="eyebrow">${esc(eyebrow)}</p>` : ''}
    <h2>${esc(title)}</h2>
    ${sub ? `<p class="lede">${esc(sub)}</p>` : ''}
  </div>`;
}

export function accordion(items) {
  return `<div class="acc">${items
    .map((f) => `<details><summary>${esc(f.q)}</summary><div>${esc(f.a)}</div></details>`)
    .join('')}</div>`;
}
