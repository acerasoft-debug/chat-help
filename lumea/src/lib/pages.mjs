import { site } from '../../data/site.mjs';
import { t, fmt } from '../../data/i18n.mjs';
import { services, categories, addons, moneyServices, serviceBySlug } from '../../data/services.mjs';
import { cities, countries, cityBySlug } from '../../data/cities.mjs';
import { therapists, therapistsFor, profileExtras } from '../../data/therapists.mjs';
import { testimonials } from '../../data/testimonials.mjs';
import { prive, priveTiers } from '../../data/prive.mjs';
import { articles, journalMeta } from '../../data/journal.mjs';
import {
  esc, attr, layout, pathFor, withBase, absolute, money, sectionHead, accordion,
  serviceCard, cityCard, therapistCard, crumbs, faqLd, breadcrumbLd
} from './html.mjs';

const currencyFor = (countryCode) => site.currencyByCountry[countryCode] || 'EUR';
const base = (locale, key, extra = {}) => ({ locale, key, services, cities, ...extra });

/* --------------------------------------------------------------- shared UI */
function quickBook(locale, opts = {}) {
  const L = t[locale];
  const grouped = categories
    .map((cat) => {
      const inCat = services.filter((s) => s.category === cat.slug);
      return `<optgroup label="${attr(cat[locale])}">${inCat
        .map((s) => `<option value="${s.slug}"${opts.service === s.slug ? ' selected' : ''}>${esc(s.i18n[locale].name)}</option>`)
        .join('')}</optgroup>`;
    })
    .join('');
  const cityOpts = cities
    .map((c) => `<option value="${c.slug}"${opts.city === c.slug ? ' selected' : ''}>${esc(c.name[locale])} · ${esc(countries[c.country][locale])}</option>`)
    .join('');

  return `<form class="qb" id="quickBook" novalidate>
    <h3>${esc(L.quickBook.title)}</h3>
    <label class="field">
      <span>${esc(L.quickBook.service)}</span>
      <select name="service">${grouped}</select>
    </label>
    <label class="field">
      <span>${esc(L.quickBook.city)}</span>
      <select name="city" id="qbCity">${cityOpts}</select>
    </label>
    <div class="field--row">
      <label class="field">
        <span>${esc(L.quickBook.when)}</span>
        <select name="when">
          <option value="today">${esc(L.quickBook.today)}</option>
          <option value="tomorrow">${esc(L.quickBook.tomorrow)}</option>
          <option value="week" selected>${esc(L.quickBook.thisWeek)}</option>
          <option value="flex">${esc(L.quickBook.flexible)}</option>
        </select>
      </label>
      <label class="field">
        <span>${esc(L.quickBook.duration)}</span>
        <select name="duration">
          <option value="60">60 ${esc(L.common.minutes)}</option>
          <option value="90" selected>90 ${esc(L.common.minutes)}</option>
          <option value="120">120 ${esc(L.common.minutes)}</option>
        </select>
      </label>
    </div>
    <button class="btn btn--gold btn--block" type="submit">${esc(L.quickBook.search)}</button>
    <p class="center" style="margin:.9rem 0 0">
      <button type="button" class="link-btn" data-use-location>${esc(L.quickBook.useLocation)}</button>
    </p>
    <div class="results" id="qbResults" aria-live="polite"></div>
  </form>`;
}

function ctaBand(locale) {
  const L = t[locale];
  return `<section class="band section">
    <div class="wrap" style="display:grid;grid-template-columns:1.2fr .8fr;gap:clamp(24px,4vw,60px);align-items:center">
      <div>
        <p class="eyebrow">${esc(L.sections.therapistCta)}</p>
        <h2>${esc(fmt(L.apply.title, { brand: site.brand }))}</h2>
        <p class="lede">${esc(L.apply.sub)}</p>
      </div>
      <div style="display:flex;flex-direction:column;gap:.7rem">
        <a class="btn btn--light" href="${withBase(pathFor(locale, { t: 'therapists' }))}">${esc(L.apply.submit)}</a>
        <a class="btn btn--ghost" style="border-color:rgba(244,239,230,.28);color:#f4efe6" href="${withBase(pathFor(locale, { t: 'how' }))}">${esc(L.nav.how)}</a>
      </div>
    </div>
  </section>`;
}

function statsBand(locale) {
  const L = t[locale];
  const items = [
    [`${site.trust.therapists}+`, L.footer.therapists],
    [String(site.trust.cities), L.nav.cities],
    [String(site.trust.countries), locale === 'de' ? 'Länder' : locale === 'es' ? 'Países' : 'Countries'],
    [`${site.trust.rating}/5`, locale === 'de' ? 'Bewertung' : locale === 'es' ? 'Valoración' : 'Rating']
  ];
  return `<section class="band section--tight section">
    <div class="wrap grid g4">
      ${items.map(([b, s]) => `<div class="stat"><b>${esc(b)}</b><span>${esc(s)}</span></div>`).join('')}
    </div>
  </section>`;
}

/* -------------------------------------------------------------------- home */
export function homePage(locale) {
  const L = t[locale];
  const popular = services.filter((s) => s.popular);
  const quotes = testimonials[locale];

  const body = `
<section class="hero">
  <div class="wrap hero__grid">
    <div>
      <div class="geo-pill is-loading" id="geoPill">
        <span class="geo-pill__dot"></span>
        <span data-geo-text>${esc(L.hero.locating)}</span>
      </div>
      <p class="eyebrow">${esc(L.hero.eyebrow)}</p>
      <h1>${esc(L.hero.title)}<em>${esc(L.hero.titleAccent)}</em></h1>
      <p class="hero__sub">${esc(L.hero.sub)}</p>
      <div class="hero__cta">
        <a class="btn btn--gold" href="#quickBook">${esc(L.hero.ctaPrimary)}</a>
        <a class="btn btn--ghost" href="${withBase(pathFor(locale, { t: 'services' }))}">${esc(L.hero.ctaSecondary)}</a>
      </div>
      <p class="hero__trust"><span class="stars">★★★★★</span> ${esc(
        fmt(L.hero.trustLine, {
          therapists: site.trust.therapists,
          rating: site.trust.rating,
          reviews: site.trust.reviewCount,
          minutes: site.trust.responseMinutes
        })
      )}</p>
    </div>
    <div>${quickBook(locale)}</div>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(L.sections.popular, L.sections.popular, L.sections.popularSub)}
    <div class="grid g3">${popular.map((s) => serviceCard(locale, s)).join('')}</div>
    <p style="margin-top:2rem"><a class="btn btn--ghost" href="${withBase(pathFor(locale, { t: 'services' }))}">${esc(L.sections.catalogue)} &rarr;</a></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    ${sectionHead(null, L.sections.how, L.sections.howSub)}
    <div class="grid g4">
      ${L.how
        .map((s, i) => `<article class="card step-card"><span class="step-card__n">${String(i + 1).padStart(2, '0')}</span>
        <h4>${esc(s.t)}</h4><p style="font-size:.89rem;color:var(--ink-2)">${esc(fmt(s.d, { minutes: site.trust.responseMinutes }))}</p></article>`)
        .join('')}
    </div>
  </div>
</section>

${statsBand(locale)}

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, fmt(L.sections.why, { brand: site.brand }), L.sections.whySub)}
    <div class="grid g3">
      ${L.why.map((w) => `<div class="feature"><span class="feature__ico">◆</span><div><h4>${esc(w.t)}</h4><p>${esc(w.d)}</p></div></div>`).join('')}
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    ${sectionHead(null, L.sections.cities, L.sections.citiesSub)}
    <div class="grid g4">${cities.filter((c) => c.flagship).map((c) => cityCard(locale, c, countries)).join('')}</div>
    <div class="tag-row" style="margin-top:1.6rem">
      ${cities.filter((c) => !c.flagship).map((c) => `<a href="${withBase(pathFor(locale, { t: 'city', city: c.slug }))}">${esc(c.name[locale])}</a>`).join('')}
    </div>
  </div>
</section>

<section class="section section--sand2">
  <div class="wrap">
    ${sectionHead(null, L.sections.testimonials, null)}
    <div class="grid g3">
      ${quotes
        .map((q) => `<figure class="quote" style="margin:0"><p>&ldquo;${esc(q.text)}&rdquo;</p>
        <footer><b>${esc(q.name)}</b> · ${esc(cityBySlug[q.city].name[locale])} · ${esc(serviceBySlug[q.service].i18n[locale].name)}</footer></figure>`)
        .join('')}
    </div>
  </div>
</section>

<section class="band section">
  <div class="wrap" style="display:grid;grid-template-columns:1.1fr .9fr;gap:clamp(24px,4vw,60px);align-items:center">
    <div>
      <p class="eyebrow">${esc(prive[locale].eyebrow)}</p>
      <h2>${esc(prive[locale].title)}</h2>
      <p class="lede">${esc(prive[locale].sub)}</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:.7rem">
      <a class="btn btn--light" href="${withBase(pathFor(locale, { t: 'prive' }))}">${esc(prive[locale].cta)}</a>
      <p class="small center" style="color:#9c9385;margin:0">${esc(prive[locale].ctaSub)}</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    ${sectionHead(null, journalMeta[locale].title, journalMeta[locale].sub)}
    <div class="grid g2">${articles.slice(0, 2).map((a) => articleCard(locale, a)).join('')}</div>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    ${sectionHead(null, L.sections.faq, null)}
    ${accordion(L.faq)}
  </div>
</section>

${ctaBand(locale)}`;

  return layout(
    base(locale, { t: 'home' }, {
      title: fmt(L.metaHomeTitle, { brand: site.brand }),
      description: L.metaHomeDesc,
      body,
      extraLd: [
        faqLd(L.faq),
        {
          '@context': 'https://schema.org',
          '@type': 'WebSite',
          name: site.brand,
          url: absolute(pathFor(locale, { t: 'home' })),
          inLanguage: locale,
          potentialAction: {
            '@type': 'SearchAction',
            target: `${absolute(pathFor(locale, { t: 'services' }))}?q={search_term_string}`,
            'query-input': 'required name=search_term_string'
          }
        }
      ]
    })
  );
}

/* ---------------------------------------------------------- services index */
export function servicesPage(locale, onlyCategory = null) {
  const L = t[locale];
  const cats = onlyCategory ? categories.filter((c) => c.slug === onlyCategory) : categories;
  const key = onlyCategory === 'skincare' ? { t: 'skincare' } : { t: 'services' };
  const title = onlyCategory === 'skincare'
    ? `${L.nav.skincare} — ${L.sections.catalogue} | ${site.brand}`
    : `${L.sections.catalogue} — ${L.nav.services} | ${site.brand}`;

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, key), label: onlyCategory === 'skincare' ? L.nav.skincare : L.nav.services }
])}
<section class="section section--tight">
  <div class="wrap">
    ${sectionHead(L.hero.eyebrow, onlyCategory === 'skincare' ? L.nav.skincare : L.sections.catalogue, L.sections.catalogueSub)}
    ${cats
      .map((cat) => {
        const list = services.filter((s) => s.category === cat.slug);
        return `<div class="menu-group">
        <div class="menu-group__head"><h3>${esc(cat[locale])}</h3><hr class="rule"></div>
        ${list
          .map((s) => {
            const c = s.i18n[locale];
            return `<a class="menu-row" href="${withBase(pathFor(locale, { t: 'service', slug: s.slug }))}">
            <strong>${esc(c.name)}</strong>
            <span class="menu-row__price">${esc(L.common.from)} ${esc(money(s.price.EUR))} <small>· ${s.durations.join('/')} ${esc(L.common.minutes)}</small></span>
            <p>${esc(c.short)}</p>
          </a>`;
          })
          .join('')}
      </div>`;
      })
      .join('')}
  </div>
</section>

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, L.sections.addons, null)}
    <div class="grid g4">
      ${addons
        .map((a) => `<div class="card"><h4>${esc(a[locale])}</h4><p class="card__price" style="font-size:1.15rem;margin-top:.6rem">${a.price.EUR ? esc(money(a.price.EUR)) : esc(L.service.included)}</p></div>`)
        .join('')}
    </div>
  </div>
</section>
${ctaBand(locale)}`;

  return layout(
    base(locale, key, {
      title,
      description: L.sections.catalogueSub,
      body,
      extraLd: [
        breadcrumbLd([
          { href: pathFor(locale, { t: 'home' }), label: L.dir },
          { href: pathFor(locale, key), label: onlyCategory === 'skincare' ? L.nav.skincare : L.nav.services }
        ]),
        {
          '@context': 'https://schema.org',
          '@type': 'ItemList',
          name: onlyCategory === 'skincare' ? L.nav.skincare : L.sections.catalogue,
          itemListElement: services
            .filter((s) => !onlyCategory || s.category === onlyCategory)
            .map((s, i) => ({
              '@type': 'ListItem',
              position: i + 1,
              url: absolute(pathFor(locale, { t: 'service', slug: s.slug })),
              name: s.i18n[locale].name
            }))
        }
      ]
    })
  );
}

/* --------------------------------------------------------- service details */
export function servicePage(locale, s) {
  const L = t[locale];
  const c = s.i18n[locale];
  const key = { t: 'service', slug: s.slug };
  const related = services.filter((x) => x.category === s.category && x.slug !== s.slug).slice(0, 3);
  const pressureBar = '●'.repeat(s.pressure) + '○'.repeat(5 - s.pressure);
  const cityLinks = moneyServices.includes(s.slug)
    ? cities.map((city) => `<a href="${withBase(pathFor(locale, { t: 'cityService', city: city.slug, service: s.slug }))}">${esc(fmt(L.city.title, { service: c.name, city: city.name[locale] }))}</a>`).join('')
    : cities.map((city) => `<a href="${withBase(pathFor(locale, { t: 'city', city: city.slug }))}">${esc(city.name[locale])}</a>`).join('');

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'services' }), label: L.nav.services },
  { href: pathFor(locale, key), label: c.name }
])}
<section class="section section--tight">
  <div class="wrap" style="display:grid;grid-template-columns:1.35fr .65fr;gap:clamp(28px,4vw,64px);align-items:start">
    <div>
      <div class="swatch" style="background:${s.accent};max-width:120px"></div>
      <p class="eyebrow">${esc(categories.find((x) => x.slug === s.category)[locale])}</p>
      <h1 style="font-size:clamp(2.2rem,4.6vw,3.6rem)">${esc(c.name)}</h1>
      <p class="lede" style="margin-top:.8rem">${esc(c.tagline)}</p>
      <div class="stack" style="margin-top:2rem">${c.long.map((p) => `<p>${esc(p)}</p>`).join('')}</div>

      <h3 style="margin-top:2.6rem">${esc(L.service.benefits)}</h3>
      <ul class="ticks">${c.benefits.map((b) => `<li>${esc(b)}</li>`).join('')}</ul>

      <h3 style="margin-top:2.2rem">${esc(L.service.ritual)}</h3>
      <ol class="steps-ol">${c.ritual.map((r) => `<li>${esc(r)}</li>`).join('')}</ol>

      <div class="panel" style="margin-top:2rem">
        <h4>${esc(L.service.forWhom)}</h4>
        <p style="margin:0;color:var(--ink-2)">${esc(c.forWhom)}</p>
      </div>

      <h3 style="margin-top:2.6rem">${esc(L.service.faqTitle)}</h3>
      ${accordion(c.faq)}
    </div>

    <aside class="panel" style="position:sticky;top:96px">
      <div class="card__price" style="font-size:2.4rem">${esc(money(s.price.EUR))}<small style="margin-top:.4rem">${esc(L.common.from)} · ${esc(fmt(L.service.per, { min: s.durations[0] }))}</small></div>
      <p class="small muted" style="margin-top:.4rem">${esc(money(s.price.CHF, 'CHF'))} ${esc(countries.CH[locale])}</p>
      <hr class="rule" style="margin:1.3rem 0">
      <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;font-size:.88rem">
        <dt class="muted">${esc(L.service.duration)}</dt><dd style="margin:0">${s.durations.join(' / ')} ${esc(L.common.minutes)}</dd>
        <dt class="muted">${esc(L.service.pressure)}</dt><dd style="margin:0;color:var(--gold);letter-spacing:.14em">${pressureBar}</dd>
        <dt class="muted">${esc(L.nav.cities)}</dt><dd style="margin:0">${site.trust.cities}</dd>
      </dl>
      <a class="btn btn--gold btn--block" style="margin-top:1.4rem" href="${withBase(pathFor(locale, { t: 'book' }))}?service=${s.slug}">${esc(L.service.book)}</a>
      <p class="small muted center" style="margin-top:.8rem">${esc(L.booking.payLater)}</p>
    </aside>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, L.sections.relatedServices, null)}
    <div class="grid g3">${related.map((r) => serviceCard(locale, r)).join('')}</div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    ${sectionHead(null, L.service.allCities, null)}
    <div class="tag-row">${cityLinks}</div>
  </div>
</section>
${ctaBand(locale)}`;

  return layout(
    base(locale, key, {
      title: `${c.name} — ${c.tagline} | ${site.brand}`,
      description: c.short,
      body,
      extraLd: [
        breadcrumbLd([
          { href: pathFor(locale, { t: 'home' }), label: L.dir },
          { href: pathFor(locale, { t: 'services' }), label: L.nav.services },
          { href: pathFor(locale, key), label: c.name }
        ]),
        faqLd(c.faq),
        {
          '@context': 'https://schema.org',
          '@type': 'Service',
          name: c.name,
          serviceType: c.name,
          description: c.short,
          provider: { '@id': `${site.origin}/#organization` },
          areaServed: cities.map((city) => ({ '@type': 'City', name: city.name[locale] })),
          offers: {
            '@type': 'Offer',
            price: s.price.EUR,
            priceCurrency: 'EUR',
            availability: 'https://schema.org/InStock',
            url: absolute(pathFor(locale, key))
          }
        }
      ]
    })
  );
}

/* ----------------------------------------------------------- cities index */
export function citiesPage(locale) {
  const L = t[locale];
  const byCountry = Object.keys(countries).map((cc) => ({
    cc,
    label: countries[cc][locale],
    list: cities.filter((c) => c.country === cc)
  }));

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities }
])}
<section class="section section--tight">
  <div class="wrap">
    ${sectionHead(L.hero.eyebrow, L.sections.cities, L.sections.citiesSub)}
    ${byCountry
      .map(
        (g) => `<div class="menu-group">
      <div class="menu-group__head"><h3>${esc(g.label)}</h3><hr class="rule"></div>
      <div class="grid g4">${g.list.map((c) => cityCard(locale, c, countries)).join('')}</div>
    </div>`
      )
      .join('')}
  </div>
</section>
${statsBand(locale)}
${ctaBand(locale)}`;

  return layout(
    base(locale, { t: 'cities' }, {
      title: `${L.sections.cities} — ${L.nav.cities} | ${site.brand}`,
      description: L.sections.citiesSub,
      body,
      extraLd: [
        breadcrumbLd([
          { href: pathFor(locale, { t: 'home' }), label: L.dir },
          { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities }
        ])
      ]
    })
  );
}

/* ------------------------------------------------------------- city detail */
export function cityPage(locale, city) {
  const L = t[locale];
  const key = { t: 'city', city: city.slug };
  const cur = currencyFor(city.country);
  const roster = therapistsFor(city.slug).slice(0, 6);
  const cityName = city.name[locale];

  const localBusiness = {
    '@context': 'https://schema.org',
    '@type': 'HealthAndBeautyBusiness',
    '@id': `${absolute(pathFor(locale, key))}#business`,
    name: `${site.brand} ${cityName}`,
    description: fmt(L.city.intro, { count: city.therapists, city: cityName }),
    url: absolute(pathFor(locale, key)),
    telephone: site.phone,
    email: site.email,
    priceRange: '€€€',
    currenciesAccepted: cur,
    paymentAccepted: 'Card, Apple Pay, Google Pay, SEPA' + (city.country === 'CH' ? ', TWINT' : ''),
    areaServed: city.districts.map((d) => ({ '@type': 'Place', name: `${d}, ${cityName}` })),
    address: { '@type': 'PostalAddress', addressLocality: cityName, addressCountry: city.country },
    geo: { '@type': 'GeoCoordinates', latitude: city.lat, longitude: city.lng },
    openingHoursSpecification: [{
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
      opens: '08:00', closes: '23:00'
    }],
    aggregateRating: { '@type': 'AggregateRating', ratingValue: site.trust.rating, reviewCount: Math.round(site.trust.reviewCount / 20), bestRating: 5 },
    parentOrganization: { '@id': `${site.origin}/#organization` }
  };

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities },
  { href: pathFor(locale, key), label: cityName }
])}
<section class="hero" style="padding-block:clamp(32px,5vw,64px)">
  <div class="wrap hero__grid">
    <div>
      <p class="eyebrow">${esc(countries[city.country][locale])}</p>
      <h1>${esc(fmt(L.city.heroTitle, { city: cityName }))}</h1>
      <p class="hero__sub">${esc(fmt(L.city.intro, { count: city.therapists, city: cityName }))}</p>
      <div class="hero__cta">
        <a class="btn btn--gold" href="#quickBook">${esc(fmt(L.city.bookNow, { city: cityName }))}</a>
        <a class="btn btn--ghost" href="${withBase(pathFor(locale, { t: 'services' }))}">${esc(L.nav.services)}</a>
      </div>
    </div>
    <div>${quickBook(locale, { city: city.slug })}</div>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, fmt(L.city.servicesIn, { city: cityName }), L.sections.popularSub)}
    <div class="grid g3">${services.filter((s) => s.popular).map((s) => serviceCard(locale, s, cur)).join('')}</div>
    <div class="tag-row" style="margin-top:1.6rem">
      ${services.filter((s) => !s.popular).map((s) => `<a href="${withBase(pathFor(locale, { t: 'service', slug: s.slug }))}">${esc(s.i18n[locale].name)}</a>`).join('')}
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    ${sectionHead(null, L.sections.nearby, fmt(L.city.therapistCount, { count: city.therapists }))}
    <div class="grid g2">${roster.map((th) => therapistCard(locale, th, services)).join('')}</div>
  </div>
</section>

<section class="section section--sand2">
  <div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(24px,4vw,56px)">
    <div>
      <h3>${esc(L.sections.districts)}</h3>
      <div class="tag-row">${city.districts.map((d) => `<a href="#quickBook">${esc(d)}</a>`).join('')}</div>
    </div>
    <div>
      <h3>${esc(L.sections.hotels)}</h3>
      <ul class="ticks">${city.hotels.map((h) => `<li>${esc(h)}</li>`).join('')}</ul>
    </div>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    ${sectionHead(null, L.sections.faq, null)}
    ${accordion(L.faq.slice(0, 5))}
  </div>
</section>
${ctaBand(locale)}`;

  return layout(
    base(locale, key, {
      title: `${fmt(L.city.heroTitle, { city: cityName })} | ${site.brand}`,
      description: fmt(L.city.intro, { count: city.therapists, city: cityName }),
      body,
      extraLd: [
        localBusiness,
        faqLd(L.faq.slice(0, 5)),
        breadcrumbLd([
          { href: pathFor(locale, { t: 'home' }), label: L.dir },
          { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities },
          { href: pathFor(locale, key), label: cityName }
        ])
      ]
    })
  );
}

/* --------------------------------------------- city × service landing page */
export function cityServicePage(locale, city, s) {
  const L = t[locale];
  const c = s.i18n[locale];
  const key = { t: 'cityService', city: city.slug, service: s.slug };
  const cityName = city.name[locale];
  const cur = currencyFor(city.country);
  const title = fmt(L.city.title, { service: c.name, city: cityName });
  const roster = therapistsFor(city.slug, s.slug).slice(0, 4);
  const others = cities.filter((x) => x.slug !== city.slug).slice(0, 12);

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities },
  { href: pathFor(locale, { t: 'city', city: city.slug }), label: cityName },
  { href: pathFor(locale, key), label: c.name }
])}
<section class="hero" style="padding-block:clamp(28px,4vw,56px)">
  <div class="wrap hero__grid">
    <div>
      <p class="eyebrow">${esc(countries[city.country][locale])} · ${esc(cityName)}</p>
      <h1 style="font-size:clamp(2.1rem,4.4vw,3.4rem)">${esc(title)}</h1>
      <p class="hero__sub">${esc(c.short)}</p>
      <p class="hero__trust"><span class="stars">★★★★★</span> ${esc(fmt(L.city.therapistCount, { count: therapistsFor(city.slug, s.slug).length || 3 }))} · ${esc(L.common.from)} ${esc(money(s.price[cur], cur))}</p>
      <div class="hero__cta">
        <a class="btn btn--gold" href="${withBase(pathFor(locale, { t: 'book' }))}?service=${s.slug}&amp;city=${city.slug}">${esc(L.service.book)}</a>
        <a class="btn btn--ghost" href="${withBase(pathFor(locale, { t: 'service', slug: s.slug }))}">${esc(L.common.readMore)}</a>
      </div>
    </div>
    <div>${quickBook(locale, { city: city.slug, service: s.slug })}</div>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap" style="display:grid;grid-template-columns:1.3fr .7fr;gap:clamp(26px,4vw,56px);align-items:start">
    <div>
      <div class="stack">${c.long.map((p) => `<p>${esc(p)}</p>`).join('')}</div>
      <h3 style="margin-top:2.2rem">${esc(L.service.benefits)}</h3>
      <ul class="ticks">${c.benefits.map((b) => `<li>${esc(b)}</li>`).join('')}</ul>
      <h3 style="margin-top:2.2rem">${esc(L.sections.districts)}</h3>
      <p class="muted">${esc(cityName)}: ${esc(city.districts.join(' · '))}</p>
      <h3 style="margin-top:2.2rem">${esc(L.service.faqTitle)}</h3>
      ${accordion(c.faq.concat(L.faq.slice(0, 3)))}
    </div>
    <aside class="panel" style="position:sticky;top:96px">
      <div class="card__price" style="font-size:2.2rem">${esc(money(s.price[cur], cur))}<small style="margin-top:.4rem">${esc(L.common.from)} · ${esc(fmt(L.service.per, { min: s.durations[0] }))}</small></div>
      <a class="btn btn--gold btn--block" style="margin-top:1.2rem" href="${withBase(pathFor(locale, { t: 'book' }))}?service=${s.slug}&amp;city=${city.slug}">${esc(fmt(L.service.bookIn, { service: c.name, city: cityName }))}</a>
      <p class="small muted center" style="margin-top:.7rem">${esc(L.booking.payLater)}</p>
    </aside>
  </div>
</section>

${roster.length ? `<section class="section">
  <div class="wrap">
    ${sectionHead(null, L.sections.nearby, null)}
    <div class="grid g2">${roster.map((th) => therapistCard(locale, th, services)).join('')}</div>
  </div>
</section>` : ''}

<section class="section section--sand2">
  <div class="wrap">
    ${sectionHead(null, L.sections.relatedCities, null)}
    <div class="tag-row">
      ${others.map((o) => `<a href="${withBase(pathFor(locale, moneyServices.includes(s.slug) ? { t: 'cityService', city: o.slug, service: s.slug } : { t: 'city', city: o.slug }))}">${esc(fmt(L.city.title, { service: c.name, city: o.name[locale] }))}</a>`).join('')}
    </div>
  </div>
</section>`;

  return layout(
    base(locale, key, {
      title: `${title} — ${esc(L.common.from)} ${money(s.price[cur], cur)} | ${site.brand}`,
      description: `${c.short} ${fmt(L.city.intro, { count: city.therapists, city: cityName })}`,
      body,
      extraLd: [
        breadcrumbLd([
          { href: pathFor(locale, { t: 'home' }), label: L.dir },
          { href: pathFor(locale, { t: 'cities' }), label: L.nav.cities },
          { href: pathFor(locale, { t: 'city', city: city.slug }), label: cityName },
          { href: pathFor(locale, key), label: c.name }
        ]),
        faqLd(c.faq),
        {
          '@context': 'https://schema.org',
          '@type': 'Service',
          name: title,
          description: c.short,
          serviceType: c.name,
          provider: { '@id': `${site.origin}/#organization` },
          areaServed: { '@type': 'City', name: cityName, address: { '@type': 'PostalAddress', addressLocality: cityName, addressCountry: city.country } },
          offers: { '@type': 'Offer', price: s.price[cur], priceCurrency: cur, availability: 'https://schema.org/InStock', url: absolute(pathFor(locale, key)) }
        }
      ]
    })
  );
}

/* ------------------------------------------------------------ how it works */
export function howPage(locale) {
  const L = t[locale];
  const key = { t: 'how' };
  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: L.nav.how }])}
<section class="section section--tight">
  <div class="wrap">
    ${sectionHead(L.hero.eyebrow, L.sections.how, L.sections.howSub)}
    <div class="grid g2">
      ${L.how.map((s, i) => `<article class="card step-card"><span class="step-card__n">${String(i + 1).padStart(2, '0')}</span>
      <h3>${esc(s.t)}</h3><p>${esc(fmt(s.d, { minutes: site.trust.responseMinutes }))}</p></article>`).join('')}
    </div>
  </div>
</section>
${statsBand(locale)}
<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, fmt(L.sections.why, { brand: site.brand }), L.sections.whySub)}
    <div class="grid g3">${L.why.map((w) => `<div class="feature"><span class="feature__ico">◆</span><div><h4>${esc(w.t)}</h4><p>${esc(w.d)}</p></div></div>`).join('')}</div>
  </div>
</section>
<section class="section">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    ${sectionHead(null, L.sections.faq, null)}
    ${accordion(L.faq)}
  </div>
</section>
${ctaBand(locale)}`;

  return layout(base(locale, key, {
    title: `${L.sections.how} — ${site.brand}`,
    description: L.sections.howSub,
    body,
    extraLd: [faqLd(L.faq), breadcrumbLd([{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: L.nav.how }])]
  }));
}

/* ------------------------------------------- therapist recruiting + apply */
export function therapistsPage(locale) {
  const L = t[locale];
  const A = L.apply;
  const key = { t: 'therapists' };
  const F = A.fields;

  const serviceChecks = categories
    .map((cat) => `<fieldset style="border:0;padding:0;margin:0 0 1.2rem">
      <legend class="eyebrow" style="margin-bottom:.7rem">${esc(cat[locale])}</legend>
      <div class="checks">${services.filter((s) => s.category === cat.slug)
        .map((s) => `<label class="check"><input type="checkbox" name="services" value="${s.slug}"><span>${esc(s.i18n[locale].name)}</span></label>`).join('')}</div>
    </fieldset>`)
    .join('');

  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: L.nav.therapists }])}
<section class="section section--tight">
  <div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:start">
    <div>
      <p class="eyebrow">${esc(L.sections.therapistCta)}</p>
      <h1>${esc(fmt(A.title, { brand: site.brand }))}</h1>
      <p class="lede" style="margin-top:.9rem">${esc(A.sub)}</p>
      <h3 style="margin-top:2.4rem">${esc(A.benefitsTitle)}</h3>
      <div class="grid" style="gap:1.2rem;margin-top:1rem">
        ${A.benefits.map((b) => `<div class="feature"><span class="feature__ico">◆</span><div><h4>${esc(b.t)}</h4><p>${esc(b.d)}</p></div></div>`).join('')}
      </div>
      <h3 style="margin-top:2.4rem">${esc(A.requirementsTitle)}</h3>
      <ul class="ticks">${A.requirements.map((r) => `<li>${esc(r)}</li>`).join('')}</ul>
    </div>

    <div class="panel" id="applyPanel">
      <ol class="stepper" id="applySteps">${A.steps.map((s, i) => `<li${i === 0 ? ' class="is-on"' : ''}>${esc(s)}</li>`).join('')}</ol>
      <div id="applyNotice"></div>
      <form id="applyForm" novalidate>
        <section data-step="0">
          <div class="form-grid">
            <label class="field"><span>${esc(F.firstName)}</span><input name="firstName" autocomplete="given-name" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(F.lastName)}</span><input name="lastName" autocomplete="family-name" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(F.email)}</span><input type="email" name="email" autocomplete="email" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(F.phone)}</span><input name="phone" autocomplete="tel" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field field--full"><span>${esc(F.password)}</span><input type="password" name="password" minlength="10" autocomplete="new-password" required><em class="field-hint">${esc(L.auth.passwordHint)}</em></label>
          </div>
        </section>

        <section data-step="1" hidden>
          <div class="form-grid">
            <label class="field"><span>${esc(F.years)}</span><input type="number" name="years" min="0" max="50" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(F.languages)}</span><input name="languages" placeholder="DE, EN, ES" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field field--full"><span>${esc(F.qualification)}</span><input name="qualification" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field field--full"><span>${esc(F.certificates)}</span><input name="certificates" placeholder="Lymphdrainage, Prenatal, Hot Stone …"></label>
            <label class="field field--full"><span>${esc(F.insurance)}</span>
              <select name="insurance" required><option value="yes">${esc(L.common.yes)}</option><option value="no">${esc(L.common.no)}</option></select>
            </label>
          </div>
        </section>

        <section data-step="2" hidden>
          ${serviceChecks}
          <label class="field"><span>${esc(F.equipment)}</span></label>
          <div class="checks">${A.equipmentOptions.map((e) => `<label class="check"><input type="checkbox" name="equipment" value="${attr(e)}"><span>${esc(e)}</span></label>`).join('')}</div>
        </section>

        <section data-step="3" hidden>
          <div class="form-grid">
            <label class="field"><span>${esc(F.country)}</span>
              <select name="country" required>${Object.values(countries).map((c) => `<option value="${c.code}">${esc(c[locale])}</option>`).join('')}</select>
            </label>
            <label class="field"><span>${esc(F.city)}</span>
              <select name="city" required>${cities.map((c) => `<option value="${c.slug}">${esc(c.name[locale])}</option>`).join('')}</select>
            </label>
            <label class="field"><span>${esc(F.postal)}</span><input name="postal" autocomplete="postal-code"></label>
            <label class="field"><span>${esc(F.radius)}</span><input type="number" name="radiusKm" value="15" min="3" max="80" required></label>
          </div>
          <label class="field"><span>${esc(F.availability)}</span></label>
          <div class="checks">${A.availabilityOptions.map((a) => `<label class="check"><input type="checkbox" name="availability" value="${attr(a)}"><span>${esc(a)}</span></label>`).join('')}</div>
        </section>

        <section data-step="4" hidden>
          <div class="form-grid">
            <label class="field field--full"><span>${esc(F.website)}</span><input name="website" placeholder="https://"></label>
            <label class="field field--full"><span>${esc(F.about)}</span><textarea name="about" rows="5" required></textarea><em class="field-error">${esc(L.common.required)}</em></label>
          </div>
          <div id="applySummary" class="notice notice--info"></div>
          <label class="check"><input type="checkbox" name="consent" required><span>${esc(L.auth.terms)}</span></label>
        </section>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" data-apply-back hidden>${esc(A.back)}</button>
          <button type="button" class="btn btn--gold" data-apply-next>${esc(A.next)}</button>
          <button type="submit" class="btn btn--gold" data-apply-submit hidden>${esc(A.submit)}</button>
        </div>
      </form>
    </div>
  </div>
</section>
${statsBand(locale)}`;

  return layout(base(locale, key, {
    title: `${fmt(A.title, { brand: site.brand })} — ${L.footer.therapists} | ${site.brand}`,
    description: A.sub,
    body,
    extraLd: [
      breadcrumbLd([{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: L.nav.therapists }]),
      {
        '@context': 'https://schema.org',
        '@type': 'JobPosting',
        title: L.footer.therapists,
        description: A.sub,
        employmentType: 'CONTRACTOR',
        hiringOrganization: { '@id': `${site.origin}/#organization` },
        jobLocationType: 'TELECOMMUTE',
        applicantLocationRequirements: Object.keys(countries).map((cc) => ({ '@type': 'Country', name: countries[cc][locale] })),
        datePosted: new Date().toISOString().slice(0, 10),
        directApply: true
      }
    ]
  }));
}

/* --------------------------------------------------------------- auth pages */
export function authPage(locale, mode) {
  const L = t[locale];
  const A = L.auth;
  const key = { t: mode === 'login' ? 'login' : 'register' };
  const isLogin = mode === 'login';

  const body = `
<section class="section">
  <div class="wrap" style="max-width:520px">
    <div class="panel">
      <h1 style="font-size:clamp(1.9rem,3.4vw,2.6rem)">${esc(isLogin ? A.loginTitle : A.registerTitle)}</h1>
      <p class="muted" style="margin-bottom:1.8rem">${esc(isLogin ? A.loginSub : A.registerSub)}</p>
      <div id="authNotice"></div>
      <form id="authForm" data-mode="${mode}" novalidate>
        ${isLogin ? '' : `<div class="checks" style="margin-bottom:1.1rem;grid-template-columns:1fr 1fr">
          <label class="check"><input type="radio" name="role" value="client" checked><span>${esc(A.asClient)}</span></label>
          <label class="check"><input type="radio" name="role" value="therapist"><span>${esc(A.asTherapist)}</span></label>
        </div>`}
        ${isLogin ? '' : `<label class="field"><span>${esc(A.name)}</span><input name="name" autocomplete="name" required><em class="field-error">${esc(L.common.required)}</em></label>`}
        <label class="field"><span>${esc(A.email)}</span><input type="email" name="email" autocomplete="email" required><em class="field-error">${esc(L.common.required)}</em></label>
        ${isLogin ? '' : `<label class="field"><span>${esc(A.phone)}</span><input name="phone" autocomplete="tel"></label>`}
        <label class="field"><span>${esc(A.password)}</span><input type="password" name="password" minlength="${isLogin ? 1 : 10}" autocomplete="${isLogin ? 'current-password' : 'new-password'}" required><em class="field-hint">${esc(isLogin ? '' : A.passwordHint)}</em><em class="field-error">${esc(L.common.required)}</em></label>
        ${isLogin ? `<label class="check" style="margin-bottom:1.2rem"><input type="checkbox" name="remember" checked><span>${esc(A.remember)}</span></label>` : `<label class="check" style="margin-bottom:1.2rem"><input type="checkbox" name="consent" required><span>${esc(A.terms)}</span></label>`}
        <button class="btn btn--gold btn--block" type="submit">${esc(isLogin ? A.login : A.register)}</button>
      </form>
      <p class="small muted" style="margin-top:1.3rem">${esc(A.loginIpNote)}</p>
      <hr class="rule" style="margin:1.6rem 0">
      <p class="small center" style="margin:0">
        ${esc(isLogin ? A.noAccount : A.haveAccount)}
        <a style="color:var(--gold)" href="${withBase(pathFor(locale, { t: isLogin ? 'register' : 'login' }))}">${esc(isLogin ? A.register : A.login)}</a>
      </p>
    </div>
  </div>
</section>`;

  return layout(base(locale, key, {
    title: `${isLogin ? A.loginTitle : A.registerTitle} | ${site.brand}`,
    description: isLogin ? A.loginSub : A.registerSub,
    body,
    noindex: true
  }));
}

export function accountPage(locale) {
  const L = t[locale];
  const key = { t: 'account' };
  const body = `
<section class="section">
  <div class="wrap">
    <div id="accountNotice"></div>
    <div id="accountRoot" data-account>
      <div class="panel center"><span class="spinner" style="margin:0 auto"></span></div>
    </div>
  </div>
</section>`;
  return layout(base(locale, key, {
    title: `${L.nav.account} | ${site.brand}`,
    description: L.auth.loginSub,
    body,
    noindex: true
  }));
}

/* ----------------------------------------------------------- booking wizard */
export function bookPage(locale) {
  const L = t[locale];
  const B = L.booking;
  const key = { t: 'book' };
  const serviceOptions = categories
    .map((cat) => `<optgroup label="${attr(cat[locale])}">${services.filter((s) => s.category === cat.slug)
      .map((s) => `<option value="${s.slug}" data-eur="${s.price.EUR}" data-chf="${s.price.CHF}">${esc(s.i18n[locale].name)} — ${esc(L.common.from)} ${esc(money(s.price.EUR))}</option>`).join('')}</optgroup>`)
    .join('');

  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: B.title }])}
<section class="section section--tight">
  <div class="wrap" style="display:grid;grid-template-columns:1.25fr .75fr;gap:clamp(26px,4vw,56px);align-items:start">
    <div class="panel">
      <h1 style="font-size:clamp(1.9rem,3.4vw,2.6rem)">${esc(B.title)}</h1>
      <ol class="stepper" id="bookSteps" style="margin-top:1.4rem">
        ${[B.step1, B.step2, B.step3, B.step4].map((s, i) => `<li${i === 0 ? ' class="is-on"' : ''}>${esc(s)}</li>`).join('')}
      </ol>
      <div id="bookNotice"></div>
      <form id="bookForm" novalidate>
        <section data-step="0">
          <label class="field"><span>${esc(L.quickBook.service)}</span><select name="service" id="bookService">${serviceOptions}</select></label>
          <div class="field--row">
            <label class="field"><span>${esc(L.quickBook.duration)}</span>
              <select name="duration"><option value="60">60 ${esc(L.common.minutes)}</option><option value="90" selected>90 ${esc(L.common.minutes)}</option><option value="120">120 ${esc(L.common.minutes)}</option></select>
            </label>
            <label class="field"><span>${esc(B.persons)}</span>
              <select name="persons"><option value="1" selected>1</option><option value="2">2</option></select>
            </label>
          </div>
          <label class="field"><span>${esc(L.sections.addons)}</span></label>
          <div class="checks">${addons.map((a) => `<label class="check"><input type="checkbox" name="addons" value="${a.slug}" data-eur="${a.price.EUR}"><span>${esc(a[locale])}${a.price.EUR ? ` · ${money(a.price.EUR)}` : ''}</span></label>`).join('')}</div>
        </section>

        <section data-step="1" hidden>
          <div class="field--row">
            <label class="field"><span>${esc(L.quickBook.city)}</span><select name="city" id="bookCity">${cities.map((c) => `<option value="${c.slug}">${esc(c.name[locale])}</option>`).join('')}</select></label>
            <label class="field"><span>${esc(B.place)}</span><select name="place">${B.placeOptions.map((p) => `<option>${esc(p)}</option>`).join('')}</select></label>
          </div>
          <label class="field"><span>${esc(B.address)}</span><input name="address" placeholder="${attr(B.addressHint)}" required><em class="field-error">${esc(L.common.required)}</em></label>
          <div class="field--row">
            <label class="field"><span>${esc(B.date)}</span><input type="date" name="date" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(B.time)}</span><input type="time" name="time" value="18:00" required></label>
          </div>
          <label class="field"><span>${esc(B.notes)}</span><textarea name="notes" rows="4" placeholder="${attr(B.notesHint)}"></textarea></label>
        </section>

        <section data-step="2" hidden>
          <div class="form-grid">
            <label class="field"><span>${esc(L.auth.name)}</span><input name="name" autocomplete="name" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field"><span>${esc(L.auth.phone)}</span><input name="phone" autocomplete="tel" required><em class="field-error">${esc(L.common.required)}</em></label>
            <label class="field field--full"><span>${esc(L.auth.email)}</span><input type="email" name="email" autocomplete="email" required><em class="field-error">${esc(L.common.required)}</em></label>
          </div>
          <label class="check"><input type="checkbox" name="consent" required><span>${esc(L.auth.terms)}</span></label>
        </section>

        <section data-step="3" hidden>
          <div id="bookSummary" class="notice notice--info"></div>
          <p class="small muted">${esc(B.payLater)}</p>
        </section>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" data-book-back hidden>${esc(L.apply.back)}</button>
          <button type="button" class="btn btn--gold" data-book-next>${esc(L.apply.next)}</button>
          <button type="submit" class="btn btn--gold" data-book-submit hidden>${esc(B.submit)}</button>
        </div>
      </form>
    </div>

    <aside class="panel" style="position:sticky;top:96px">
      <h3 style="font-family:var(--sans);font-size:1rem">${esc(B.summary)}</h3>
      <div id="bookAside" class="small muted"></div>
      <hr class="rule" style="margin:1.2rem 0">
      <div style="display:flex;justify-content:space-between;align-items:baseline">
        <span class="small muted">${esc(B.total)}</span>
        <strong class="card__price" id="bookTotal" style="font-size:1.7rem">—</strong>
      </div>
      <p class="small muted" style="margin-top:1rem">${esc(fmt(B.success, { minutes: site.trust.responseMinutes }))}</p>
    </aside>
  </div>
</section>`;

  return layout(base(locale, key, {
    title: `${B.title} | ${site.brand}`,
    description: L.metaHomeDesc,
    body,
    noindex: true
  }));
}

/* ------------------------------------------------------- editorial / legal */
function simplePage(locale, key, title, description, blocks, opts = {}) {
  const L = t[locale];
  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: title }])}
<section class="section section--tight">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    <h1>${esc(title)}</h1>
    <p class="lede" style="margin-bottom:2.2rem">${esc(description)}</p>
    ${blocks.map((b) => (b.h ? `<h3 style="margin-top:2rem">${esc(b.h)}</h3>` : '') + (b.p ? b.p.map((x) => `<p>${esc(x)}</p>`).join('') : '') + (b.list ? `<ul class="ticks">${b.list.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>` : '')).join('')}
  </div>
</section>
${opts.cta === false ? '' : ctaBand(locale)}`;

  return layout(base(locale, key, { title: `${title} | ${site.brand}`, description, body, noindex: !!opts.noindex }));
}

const LEGAL = {
  de: {
    imprint: { title: 'Impressum', desc: 'Angaben gemäß § 5 TMG.', blocks: [
      { h: 'Anbieter', p: [`${site.legalName}, Kurfürstendamm 194, 10707 Berlin, Deutschland`, `E-Mail: ${site.email} · Telefon: ${site.phone}`] },
      { h: 'Vertretungsberechtigt', p: ['Die Geschäftsführung. Registereintrag und Umsatzsteuer-Identifikationsnummer werden vor dem Marktstart ergänzt.'] },
      { h: 'Verantwortlich für den Inhalt', p: ['Die Geschäftsführung, Anschrift wie oben.'] },
      { h: 'Streitbeilegung', p: ['Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit. Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.'] },
      { h: 'Hinweis', p: ['Dies ist eine Demonstrations- und Entwicklungsfassung. Alle Angaben zu Anschrift, Register und Steuernummern sind vor der Veröffentlichung durch die tatsächlichen Daten zu ersetzen.'] }
    ] },
    privacy: { title: 'Datenschutzerklärung', desc: 'Wie wir personenbezogene Daten erheben, verarbeiten und schützen — nach DSGVO.', blocks: [
      { h: 'Verantwortliche Stelle', p: [`${site.legalName}, Anschrift siehe Impressum. Datenschutzanfragen: ${site.email}`] },
      { h: 'Welche Daten wir verarbeiten', list: ['Kontodaten: Name, E-Mail, Telefonnummer, Passwort-Hash', 'Buchungsdaten: Adresse, Termin, gewählte Behandlung, Anmerkungen', 'Standortdaten: Ihre IP-Adresse zur Bestimmung der Stadt (Art. 6 Abs. 1 lit. f DSGVO) sowie — nur nach ausdrücklicher Freigabe — der Gerätestandort', 'Sicherheitsdaten: IP-Adresse, Zeitpunkt und Gerätekennung jeder Anmeldung', 'Bewerbungsdaten von Therapeut:innen: Qualifikationen, Versicherung, Einsatzgebiet'] },
      { h: 'Rechtsgrundlagen', p: ['Vertragserfüllung (Art. 6 Abs. 1 lit. b), berechtigtes Interesse an Sicherheit und Betrugsprävention (lit. f) sowie Einwilligung für den Gerätestandort und Marketing (lit. a).'] },
      { h: 'Standortermittlung über die IP-Adresse', p: ['Zur Vorauswahl Ihrer Stadt werten wir die IP-Adresse serverseitig aus. Die vollständige IP wird dabei nicht dauerhaft gespeichert; für Sicherheitsprotokolle wird sie gekürzt beziehungsweise nur für die gesetzlich zulässige Dauer vorgehalten. Sie können die Stadt jederzeit manuell überschreiben.'] },
      { h: 'Speicherdauer', p: ['Kontodaten bis zur Löschung des Kontos, Buchungsdaten entsprechend den handels- und steuerrechtlichen Aufbewahrungsfristen, Sicherheitsprotokolle maximal 90 Tage.'] },
      { h: 'Ihre Rechte', list: ['Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung', 'Datenübertragbarkeit', 'Widerspruch gegen Verarbeitung auf Grundlage berechtigter Interessen', 'Widerruf erteilter Einwilligungen mit Wirkung für die Zukunft', 'Beschwerde bei einer Aufsichtsbehörde'] },
      { h: 'Hinweis', p: ['Diese Fassung ist eine technische Vorlage und ersetzt keine anwaltliche Prüfung vor dem Marktstart.'] }
    ] },
    terms: { title: 'Allgemeine Geschäftsbedingungen', desc: 'Die Bedingungen für die Vermittlung und Erbringung von Behandlungen.', blocks: [
      { h: 'Gegenstand', p: [`${site.legalName} vermittelt ausschließlich professionelle Wellness-, Massage- und Kosmetikbehandlungen zwischen geprüften, selbstständigen Therapeut:innen und Gästen.`] },
      { h: 'Ausschließlich therapeutischer Rahmen', p: ['Sämtliche vermittelten Leistungen sind ausschließlich therapeutischer und kosmetischer Natur. Anfragen oder Verhaltensweisen sexueller Art führen zum sofortigen Abbruch der Behandlung, zur vollständigen Berechnung des Termins und zur dauerhaften Sperrung des Kontos. Therapeut:innen sind ausdrücklich berechtigt, eine Behandlung jederzeit ohne Angabe von Gründen zu beenden.'] },
      { h: 'Buchung und Vertragsschluss', p: ['Eine Anfrage ist unverbindlich. Der Vertrag kommt mit der Bestätigung durch die Therapeut:in zustande. Die Leistung wird durch die selbstständige Therapeut:in erbracht.'] },
      { h: 'Preise und Zahlung', p: ['Es gelten die zum Zeitpunkt der Buchung angezeigten Preise inklusive Anfahrt und Ausstattung. Die Zahlung erfolgt bei der Buchung im Voraus. Der Betrag wird von der Plattform treuhänderisch gehalten und erst nach Abschluss der Behandlung an die Therapeut:in ausgezahlt. Bei Absage durch die Therapeut:in oder Nichterscheinen wird der volle Betrag erstattet. Trinkgeld ist freiwillig und verbleibt vollständig bei der Therapeut:in.'] },
      { h: 'Identitäts- und Dokumentenprüfung', p: ['Therapeut:innen werden erst nach Prüfung und Freigabe von Ausweis, Ausbildungsnachweis, Berufshaftpflichtversicherung und Führungszeugnis durch die Plattform freigeschaltet. Ohne vollständige Prüfung ist keine Leistungserbringung über die Plattform möglich. Gäste bestätigen bei der Registrierung ihre Identität per E-Mail und Telefonnummer.'] },
      { h: 'Stornierung', p: ['Bis 12 Stunden vor dem Termin kostenfrei. Danach werden 50 % des Behandlungspreises berechnet. Bei Nichtantreffen vor Ort wird der volle Preis berechnet.'] },
      { h: 'Gesundheit und Kontraindikationen', p: ['Gäste sind verpflichtet, relevante Erkrankungen, Schwangerschaft, Operationen und Medikamente vor Behandlungsbeginn anzugeben. Bei bestimmten Indikationen ist eine ärztliche Freigabe erforderlich.'] },
      { h: 'Haftung', p: ['Alle Therapeut:innen verfügen über eine eigene Berufshaftpflichtversicherung. Die Haftung der Plattform richtet sich nach den gesetzlichen Bestimmungen.'] }
    ] }
  },
  en: {
    imprint: { title: 'Imprint', desc: 'Provider identification.', blocks: [
      { h: 'Provider', p: [`${site.legalName}, Kurfürstendamm 194, 10707 Berlin, Germany`, `Email: ${site.email} · Phone: ${site.phone}`] },
      { h: 'Represented by', p: ['The management. Commercial register entry and VAT identification number will be added before launch.'] },
      { h: 'Responsible for content', p: ['The management, address as above.'] },
      { h: 'Dispute resolution', p: ['The European Commission provides a platform for online dispute resolution. We are neither obliged nor willing to participate in dispute resolution proceedings before a consumer arbitration board.'] },
      { h: 'Note', p: ['This is a demonstration and development build. All address, register and tax details must be replaced with the actual data before publication.'] }
    ] },
    privacy: { title: 'Privacy Policy', desc: 'How we collect, process and protect personal data under the GDPR.', blocks: [
      { h: 'Controller', p: [`${site.legalName}, address as in the imprint. Privacy requests: ${site.email}`] },
      { h: 'What we process', list: ['Account data: name, email, phone number, password hash', 'Booking data: address, appointment, chosen treatment, notes', 'Location data: your IP address to determine the city (Art. 6(1)(f) GDPR) and — only with explicit permission — device location', 'Security data: IP address, timestamp and device identifier of every sign-in', 'Therapist application data: qualifications, insurance, coverage area'] },
      { h: 'Legal bases', p: ['Contract performance (Art. 6(1)(b)), legitimate interest in security and fraud prevention (f), and consent for device location and marketing (a).'] },
      { h: 'IP-based location', p: ['To preselect your city we evaluate the IP address server-side. The full IP is not stored permanently; for security logs it is truncated or retained only for the period permitted by law. You can override the city manually at any time.'] },
      { h: 'Retention', p: ['Account data until account deletion, booking data according to commercial and tax retention periods, security logs for a maximum of 90 days.'] },
      { h: 'Your rights', list: ['Access, rectification, erasure and restriction of processing', 'Data portability', 'Objection to processing based on legitimate interests', 'Withdrawal of consent with future effect', 'Complaint to a supervisory authority'] },
      { h: 'Note', p: ['This version is a technical template and does not replace legal review before launch.'] }
    ] },
    terms: { title: 'Terms and Conditions', desc: 'The terms governing the arrangement and delivery of treatments.', blocks: [
      { h: 'Subject', p: [`${site.legalName} exclusively arranges professional wellness, massage and skincare treatments between vetted, self-employed therapists and guests.`] },
      { h: 'Strictly therapeutic scope', p: ['All arranged services are strictly therapeutic and cosmetic in nature. Requests or conduct of a sexual nature result in immediate termination of the treatment, full charging of the appointment and permanent account closure. Therapists are expressly entitled to end a treatment at any time without giving reasons.'] },
      { h: 'Booking and contract', p: ['A request is non-binding. The contract is formed upon confirmation by the therapist. The service is delivered by the self-employed therapist.'] },
      { h: 'Prices and payment', p: ['The prices displayed at the time of booking apply and include travel and equipment. Payment is made upfront at booking. The amount is held in escrow by the platform and released to the therapist only after the treatment is completed. If the therapist cancels or does not appear, the full amount is refunded. Tips are voluntary and remain entirely with the therapist.'] },
      { h: 'Identity and document verification', p: ['Therapists are activated only after the platform has reviewed and approved their ID, qualification certificate, professional liability insurance and criminal record certificate. Without complete verification no service can be delivered through the platform. Guests confirm their identity at registration by email and phone number.'] },
      { h: 'Cancellation', p: ['Free of charge up to 12 hours before the appointment. After that 50 % of the treatment price is charged. If no one is present on site, the full price is charged.'] },
      { h: 'Health and contraindications', p: ['Guests must disclose relevant conditions, pregnancy, surgery and medication before the treatment begins. Certain indications require medical clearance.'] },
      { h: 'Liability', p: ['All therapists hold their own professional liability insurance. Platform liability is governed by statutory provisions.'] }
    ] }
  },
  es: {
    imprint: { title: 'Aviso legal', desc: 'Identificación del prestador.', blocks: [
      { h: 'Prestador', p: [`${site.legalName}, Kurfürstendamm 194, 10707 Berlín, Alemania`, `Correo: ${site.email} · Teléfono: ${site.phone}`] },
      { h: 'Representación', p: ['La dirección. La inscripción registral y el número de identificación fiscal se añadirán antes del lanzamiento.'] },
      { h: 'Responsable de contenidos', p: ['La dirección, dirección postal indicada arriba.'] },
      { h: 'Resolución de litigios', p: ['La Comisión Europea facilita una plataforma de resolución de litigios en línea. No estamos obligados ni dispuestos a participar en procedimientos de arbitraje de consumo.'] },
      { h: 'Nota', p: ['Esta es una versión de demostración y desarrollo. Todos los datos de dirección, registro e identificación fiscal deben sustituirse por los reales antes de su publicación.'] }
    ] },
    privacy: { title: 'Política de privacidad', desc: 'Cómo recogemos, tratamos y protegemos los datos personales conforme al RGPD.', blocks: [
      { h: 'Responsable', p: [`${site.legalName}, dirección en el aviso legal. Solicitudes de privacidad: ${site.email}`] },
      { h: 'Qué datos tratamos', list: ['Datos de cuenta: nombre, correo, teléfono, hash de la contraseña', 'Datos de reserva: dirección, cita, tratamiento elegido, notas', 'Datos de ubicación: tu dirección IP para determinar la ciudad (art. 6.1.f RGPD) y, solo con permiso expreso, la ubicación del dispositivo', 'Datos de seguridad: dirección IP, hora e identificador de dispositivo de cada inicio de sesión', 'Datos de candidatura de terapeutas: titulación, seguro, zona de cobertura'] },
      { h: 'Bases jurídicas', p: ['Ejecución del contrato (art. 6.1.b), interés legítimo en la seguridad y la prevención del fraude (f) y consentimiento para la ubicación del dispositivo y el marketing (a).'] },
      { h: 'Geolocalización por IP', p: ['Para preseleccionar tu ciudad evaluamos la dirección IP en el servidor. La IP completa no se almacena de forma permanente; en los registros de seguridad se trunca o se conserva solo durante el plazo legalmente admisible. Puedes cambiar la ciudad manualmente en cualquier momento.'] },
      { h: 'Plazos de conservación', p: ['Datos de cuenta hasta la eliminación de la cuenta, datos de reserva según los plazos mercantiles y fiscales, y registros de seguridad un máximo de 90 días.'] },
      { h: 'Tus derechos', list: ['Acceso, rectificación, supresión y limitación del tratamiento', 'Portabilidad de los datos', 'Oposición al tratamiento basado en intereses legítimos', 'Retirada del consentimiento con efectos futuros', 'Reclamación ante una autoridad de control'] },
      { h: 'Nota', p: ['Esta versión es una plantilla técnica y no sustituye la revisión jurídica previa al lanzamiento.'] }
    ] },
    terms: { title: 'Condiciones generales', desc: 'Las condiciones que rigen la intermediación y prestación de los tratamientos.', blocks: [
      { h: 'Objeto', p: [`${site.legalName} intermedia exclusivamente tratamientos profesionales de bienestar, masaje y estética entre terapeutas autónomos verificados y clientes.`] },
      { h: 'Marco estrictamente terapéutico', p: ['Todos los servicios intermediados son de naturaleza estrictamente terapéutica y estética. Las solicitudes o conductas de carácter sexual conllevan la interrupción inmediata del tratamiento, el cobro íntegro de la cita y el cierre permanente de la cuenta. Las terapeutas están expresamente facultadas para finalizar un tratamiento en cualquier momento sin indicar motivos.'] },
      { h: 'Reserva y contrato', p: ['La solicitud no es vinculante. El contrato se perfecciona con la confirmación de la terapeuta. El servicio lo presta la terapeuta autónoma.'] },
      { h: 'Precios y pago', p: ['Se aplican los precios mostrados en el momento de la reserva, con desplazamiento y equipamiento incluidos. El pago se realiza por adelantado al reservar. La plataforma retiene el importe en depósito y lo transfiere a la terapeuta únicamente una vez completado el tratamiento. Si la terapeuta cancela o no acude, se reembolsa el importe íntegro. La propina es voluntaria y queda íntegramente para la terapeuta.'] },
      { h: 'Verificación de identidad y documentos', p: ['Las terapeutas se activan solo después de que la plataforma haya revisado y aprobado su documento de identidad, titulación, seguro de responsabilidad civil y certificado de antecedentes penales. Sin verificación completa no es posible prestar servicios a través de la plataforma. Los clientes confirman su identidad al registrarse mediante correo electrónico y teléfono.'] },
      { h: 'Cancelación', p: ['Gratuita hasta 12 horas antes de la cita. A partir de ese momento se cobra el 50 % del precio. Si no hay nadie en el domicilio, se cobra el precio íntegro.'] },
      { h: 'Salud y contraindicaciones', p: ['Los clientes deben comunicar enfermedades relevantes, embarazo, cirugías y medicación antes de iniciar el tratamiento. Determinadas indicaciones requieren autorización médica.'] },
      { h: 'Responsabilidad', p: ['Todas las terapeutas disponen de su propio seguro de responsabilidad civil profesional. La responsabilidad de la plataforma se rige por la normativa aplicable.'] }
    ] }
  }
};

export function legalPage(locale, which) {
  const d = LEGAL[locale][which];
  return simplePage(locale, { t: which }, d.title, d.desc, d.blocks, { cta: false });
}

export function contactPage(locale) {
  const L = t[locale];
  const key = { t: 'contact' };
  const copy = {
    de: { h: 'Kontakt', d: 'Concierge, Hotel- und Firmenanfragen, Presse — wir antworten in der Regel innerhalb eines Werktages.', hotel: 'Hotels, Villen & Yachten', hotelP: 'Wir richten feste Ansprechpartner:innen, Rahmenpreise und Verfügbarkeitsfenster für Ihre Häuser ein.', corp: 'Unternehmen', corpP: 'Wiederkehrende Bürotage mit fester Therapeutin und Buchungsliste für Ihr Team.' },
    en: { h: 'Contact', d: 'Concierge, hotel and corporate enquiries, press — we usually reply within one working day.', hotel: 'Hotels, villas & yachts', hotelP: 'We set up dedicated contacts, framework rates and availability windows for your properties.', corp: 'Companies', corpP: 'Recurring office days with a dedicated therapist and a booking list for your team.' },
    es: { h: 'Contacto', d: 'Conserjería, solicitudes de hoteles y empresas, prensa: respondemos normalmente en un día laborable.', hotel: 'Hoteles, villas y yates', hotelP: 'Configuramos contactos dedicados, tarifas marco y ventanas de disponibilidad para tus propiedades.', corp: 'Empresas', corpP: 'Jornadas periódicas en oficina con terapeuta fija y lista de reservas para tu equipo.' }
  }[locale];

  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: copy.h }])}
<section class="section section--tight">
  <div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(26px,4vw,56px);align-items:start">
    <div>
      <h1>${esc(copy.h)}</h1>
      <p class="lede">${esc(copy.d)}</p>
      <p style="margin-top:1.6rem"><a class="btn btn--ghost" href="mailto:${attr(site.email)}">${esc(site.email)}</a>
      <a class="btn btn--ghost" href="tel:${attr(site.phoneHref)}">${esc(site.phone)}</a></p>
      <h3 style="margin-top:2.4rem">${esc(copy.hotel)}</h3><p>${esc(copy.hotelP)}</p>
      <h3 style="margin-top:1.6rem">${esc(copy.corp)}</h3><p>${esc(copy.corpP)}</p>
    </div>
    <div class="panel">
      <div id="contactNotice"></div>
      <form id="contactForm" novalidate>
        <label class="field"><span>${esc(L.auth.name)}</span><input name="name" required><em class="field-error">${esc(L.common.required)}</em></label>
        <label class="field"><span>${esc(L.auth.email)}</span><input type="email" name="email" required><em class="field-error">${esc(L.common.required)}</em></label>
        <label class="field"><span>${esc(L.quickBook.city)}</span><select name="city">${cities.map((c) => `<option value="${c.slug}">${esc(c.name[locale])}</option>`).join('')}</select></label>
        <label class="field"><span>${esc(L.booking.notes)}</span><textarea name="message" rows="6" required></textarea><em class="field-error">${esc(L.common.required)}</em></label>
        <button class="btn btn--gold btn--block" type="submit">${esc(L.booking.submit)}</button>
      </form>
    </div>
  </div>
</section>`;

  return layout(base(locale, key, { title: `${copy.h} | ${site.brand}`, description: copy.d, body }));
}

export function giftPage(locale) {
  const L = t[locale];
  const copy = {
    de: { h: 'Gutscheine', d: 'Ein Gutschein ohne Preisangabe, den die beschenkte Person selbst terminiert — gültig in allen zwanzig Städten und drei Jahre lang einlösbar.', list: ['Digital in Minuten, gedruckt auf Wunsch', 'Ohne sichtbaren Betrag', 'Gültig für alle Behandlungen und alle Städte', 'Drei Jahre gültig, Restbetrag bleibt erhalten'] },
    en: { h: 'Gift vouchers', d: 'A voucher without a visible price that the recipient schedules themselves — valid in all twenty cities and redeemable for three years.', list: ['Digital in minutes, printed on request', 'No amount shown on the voucher', 'Valid for every treatment and every city', 'Three years validity, remaining balance kept'] },
    es: { h: 'Tarjetas regalo', d: 'Una tarjeta sin precio visible que la persona obsequiada agenda por sí misma: válida en las veinte ciudades y canjeable durante tres años.', list: ['Digital en minutos, impresa si lo deseas', 'Sin importe visible en la tarjeta', 'Válida para todos los tratamientos y ciudades', 'Tres años de validez, el saldo restante se conserva'] }
  }[locale];
  return simplePage(locale, { t: 'gift' }, copy.h, copy.d, [{ list: copy.list }, { p: [L.booking.payLater] }]);
}

export function corporatePage(locale) {
  const copy = {
    de: { h: 'Für Unternehmen & Hotels', d: 'Wiederkehrende Bürotage, Event-Lounges, Hotelsuiten und Retreats — mit festen Therapeut:innen, Rahmenvertrag und monatlicher Sammelrechnung.', list: ['Feste Ansprechpartner:innen und garantierte Zeitfenster', 'Buchungsliste für Ihr Team, kein Verwaltungsaufwand', 'Massagestuhl-Format ab 20 Minuten pro Person', 'Rahmenpreise ab 15 Terminen im Monat', 'Monatliche Sammelrechnung, DSGVO-konform'] },
    en: { h: 'For companies & hotels', d: 'Recurring office days, event lounges, hotel suites and retreats — with dedicated therapists, a framework agreement and one monthly invoice.', list: ['Dedicated contacts and guaranteed time slots', 'A booking list for your team, no admin overhead', 'Chair-massage format from 20 minutes per person', 'Framework rates from 15 appointments per month', 'One monthly invoice, GDPR compliant'] },
    es: { h: 'Para empresas y hoteles', d: 'Jornadas periódicas en oficina, salas de eventos, suites de hotel y retiros, con terapeutas fijas, contrato marco y una única factura mensual.', list: ['Contactos dedicados y franjas garantizadas', 'Lista de reservas para tu equipo, sin carga administrativa', 'Formato silla desde 20 minutos por persona', 'Tarifas marco a partir de 15 citas al mes', 'Factura mensual única y conforme al RGPD'] }
  }[locale];
  return simplePage(locale, { t: 'corporate' }, copy.h, copy.d, [{ list: copy.list }]);
}

export function notFoundPage(locale) {
  const L = t[locale];
  const copy = { de: 'Diese Seite gibt es nicht (mehr).', en: 'This page does not exist (any more).', es: 'Esta página no existe (ya).' }[locale];
  const body = `<section class="section center"><div class="wrap">
    <p class="eyebrow">404</p><h1>${esc(copy)}</h1>
    <p class="lede center" style="margin:1rem auto 2rem">${esc(L.sections.catalogueSub)}</p>
    <a class="btn btn--gold" href="${withBase(pathFor(locale, { t: 'home' }))}">${esc(L.common.backHome)}</a>
    <a class="btn btn--ghost" href="${withBase(pathFor(locale, { t: 'services' }))}">${esc(L.nav.services)}</a>
  </div></section>`;
  return layout(base(locale, { t: 'home' }, { title: `404 | ${site.brand}`, description: copy, body, noindex: true }));
}


/* ------------------------------------------------------------- Privé page */
export function privePage(locale) {
  const L = t[locale];
  const P = prive[locale];
  const key = { t: 'prive' };
  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: 'Luméa Privé' }])}
<section class="hero" style="padding-block:clamp(36px,5vw,72px)">
  <div class="wrap" style="max-width:820px">
    <p class="eyebrow">${esc(P.eyebrow)}</p>
    <h1>${esc(P.title)}</h1>
    <p class="hero__sub">${esc(P.sub)}</p>
  </div>
</section>
<section class="section section--tight">
  <div class="wrap grid g3">
    ${priveTiers.map((tier) => {
      const c = P.tiers[tier.slug];
      return `<article class="card${tier.featured ? ' card--hover' : ''}" style="${tier.featured ? 'border-color:var(--gold);box-shadow:var(--shadow-md)' : ''}">
        <div class="swatch" style="background:${tier.accent}"></div>
        <span class="card__tag">${esc(c.tag)}</span>
        <h3 style="margin-top:.3rem">${esc(c.name)}</h3>
        <div class="card__price" style="font-size:2rem;margin:.4rem 0 .8rem">${esc(money(tier.price))}<small style="margin-top:.35rem">${esc(P.perMonth)} · CHF ${Math.round(tier.price * 1.2)}</small></div>
        <p>${esc(c.desc)}</p>
        <ul class="ticks" style="margin-top:.6rem">${c.includes.map((i) => `<li>${esc(i)}</li>`).join('')}</ul>
        <div class="card__foot" style="display:block">
          <a class="btn ${tier.featured ? 'btn--gold' : 'btn--ghost'} btn--block" href="${withBase(pathFor(locale, { t: 'register' }))}?plan=${tier.slug}">${esc(P.cta)}</a>
          <p class="small muted center" style="margin:.6rem 0 0">${esc(P.ctaSub)}</p>
        </div>
      </article>`;
    }).join('')}
  </div>
</section>
${statsBand(locale)}
<section class="section section--paper">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    ${sectionHead(null, L.sections.faq, null)}
    ${accordion(P.faq)}
  </div>
</section>`;

  return layout(base(locale, key, {
    title: `Luméa Privé — ${P.title} | ${site.brand}`,
    description: P.sub,
    body,
    extraLd: [
      faqLd(P.faq),
      breadcrumbLd([{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: 'Luméa Privé' }]),
      ...priveTiers.map((tier) => ({
        '@context': 'https://schema.org',
        '@type': 'Offer',
        name: `Luméa Privé ${P.tiers[tier.slug].name}`,
        description: P.tiers[tier.slug].desc,
        price: tier.price,
        priceCurrency: 'EUR',
        url: absolute(pathFor(locale, key)),
        offeredBy: { '@id': `${site.origin}/#organization` }
      }))
    ]
  }));
}

/* ----------------------------------------------------------------- Journal */
function articleCard(locale, a) {
  const c = a[locale];
  const J = journalMeta[locale];
  return `<a class="card" href="${withBase(pathFor(locale, { t: 'article', slug: a.slug }))}">
    <span class="card__tag">${esc(serviceBySlug[a.service].i18n[locale].name)}</span>
    <h3 style="margin-top:.4rem">${esc(c.title)}</h3>
    <p>${esc(c.excerpt)}</p>
    <div class="card__foot"><span>${esc(a.date)} · ${a.minutes} ${esc(J.minutes)}</span><span class="card__arrow">&rarr;</span></div>
  </a>`;
}

export function journalPage(locale) {
  const L = t[locale];
  const J = journalMeta[locale];
  const key = { t: 'journal' };
  const body = `
${crumbs(locale, [{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: J.title }])}
<section class="section section--tight">
  <div class="wrap">
    ${sectionHead(site.brand, J.title, J.sub)}
    <div class="grid g2">${articles.map((a) => articleCard(locale, a)).join('')}</div>
  </div>
</section>
${ctaBand(locale)}`;
  return layout(base(locale, key, {
    title: `${J.title} | ${site.brand}`,
    description: J.sub,
    body,
    extraLd: [breadcrumbLd([{ href: pathFor(locale, { t: 'home' }), label: L.dir }, { href: pathFor(locale, key), label: J.title }])]
  }));
}

export function articlePage(locale, a) {
  const L = t[locale];
  const J = journalMeta[locale];
  const c = a[locale];
  const key = { t: 'article', slug: a.slug };
  const svc = serviceBySlug[a.service];
  const others = articles.filter((x) => x.slug !== a.slug).slice(0, 3);
  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'journal' }), label: J.title },
  { href: pathFor(locale, key), label: c.title }
])}
<article class="section section--tight">
  <div class="wrap wrap-narrow" style="padding-inline:0">
    <p class="eyebrow">${esc(J.title)} · ${esc(a.date)} · ${a.minutes} ${esc(J.minutes)}</p>
    <h1 style="font-size:clamp(2.1rem,4.4vw,3.4rem)">${esc(c.title)}</h1>
    <p class="lede">${esc(c.excerpt)}</p>
    <p class="small muted">${esc(J.by)} ${esc(a.author)}</p>
    <div class="stack" style="margin-top:2rem;font-size:1.05rem">${c.body.map((p) => `<p>${esc(p)}</p>`).join('')}</div>
    <div class="panel" style="margin-top:2.6rem;display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap">
      <div><span class="card__tag">${esc(L.sections.relatedServices)}</span><h3 style="margin:.3rem 0 0">${esc(svc.i18n[locale].name)}</h3></div>
      <a class="btn btn--gold" href="${withBase(pathFor(locale, { t: 'service', slug: svc.slug }))}">${esc(L.common.readMore)}</a>
    </div>
  </div>
</article>
<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, J.title, null)}
    <div class="grid g3">${others.map((o) => articleCard(locale, o)).join('')}</div>
  </div>
</section>`;

  return layout(base(locale, key, {
    title: `${c.title} | ${site.brand} ${J.title}`,
    description: c.excerpt,
    body,
    extraLd: [
      breadcrumbLd([
        { href: pathFor(locale, { t: 'home' }), label: L.dir },
        { href: pathFor(locale, { t: 'journal' }), label: J.title },
        { href: pathFor(locale, key), label: c.title }
      ]),
      {
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: c.title,
        description: c.excerpt,
        datePublished: a.date,
        dateModified: a.date,
        inLanguage: locale,
        author: { '@type': 'Person', name: a.author },
        publisher: { '@id': `${site.origin}/#organization` },
        mainEntityOfPage: absolute(pathFor(locale, key)),
        articleBody: c.body.join('\n\n')
      }
    ]
  }));
}


/* --------------------------------------------------------- therapist profile */
const PROFILE_UI = {
  de: { about: 'Über', certs: 'Ausbildung & Zertifikate', verify: 'Identitäts- & Dokumentenprüfung', coverage: 'Einsatzgebiet', avail: 'Verfügbarkeit', equip: 'Ausstattung', menu: 'Behandlungen & Preise', reviews: 'Verifizierte Bewertungen', book: 'Mit {name} buchen', radius: 'Radius {km} km um {city}', langs: 'Sprachen', exp: '{years} Jahre Erfahrung', since: 'Bei Luméa seit {year}', response: 'Antwortet in ⌀ {min} Min.', verifiedOn: 'Alle Pflichtdokumente durch das Luméa-Prüfteam freigegeben.', metaDesc: '{name}, {title} in {city}: {count} Behandlungen, {rating}/5 aus {reviews} Bewertungen, identitäts- und dokumentengeprüft. Mobil buchbar im Umkreis von {km} km.', otherIn: 'Weitere Therapeut:innen in {city}' },
  en: { about: 'About', certs: 'Training & certificates', verify: 'Identity & document verification', coverage: 'Coverage', avail: 'Availability', equip: 'Equipment', menu: 'Treatments & prices', reviews: 'Verified reviews', book: 'Book with {name}', radius: '{km} km radius around {city}', langs: 'Languages', exp: '{years} years of experience', since: 'With Luméa since {year}', response: 'Replies in ~{min} min', verifiedOn: 'All mandatory documents approved by the Luméa review team.', metaDesc: '{name}, {title} in {city}: {count} treatments, {rating}/5 from {reviews} reviews, identity- and document-verified. Mobile bookings within {km} km.', otherIn: 'More therapists in {city}' },
  es: { about: 'Sobre', certs: 'Formación y certificados', verify: 'Verificación de identidad y documentos', coverage: 'Zona de cobertura', avail: 'Disponibilidad', equip: 'Equipamiento', menu: 'Tratamientos y precios', reviews: 'Reseñas verificadas', book: 'Reservar con {name}', radius: 'Radio de {km} km alrededor de {city}', langs: 'Idiomas', exp: '{years} años de experiencia', since: 'En Luméa desde {year}', response: 'Responde en ~{min} min', verifiedOn: 'Todos los documentos obligatorios aprobados por el equipo de verificación de Luméa.', metaDesc: '{name}, {title} en {city}: {count} tratamientos, {rating}/5 de {reviews} reseñas, identidad y documentos verificados. Reservas a domicilio en un radio de {km} km.', otherIn: 'Más terapeutas en {city}' }
};

export function therapistPage(locale, th) {
  const L = t[locale];
  const U = PROFILE_UI[locale];
  const key = { t: 'therapist', id: th.id };
  const city = cityBySlug[th.city];
  const cityName = city.name[locale];
  const cur = currencyFor(city.country);
  const x = profileExtras(th, locale);
  const offered = th.services.map((sl) => serviceBySlug[sl]).filter(Boolean);
  const others = therapistsFor(th.city).filter((o) => o.id !== th.id).slice(0, 3);
  const seals = [['identity', L.match.idOk], ['qualification', L.match.certOk], ['insurance', L.match.insOk], ['background', L.match.bgOk]];
  const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);
  const bookHref = `${withBase(pathFor(locale, { t: 'book' }))}?city=${th.city}&therapist=${th.id}&service=${offered[0]?.slug || ''}`;

  const body = `
${crumbs(locale, [
  { href: pathFor(locale, { t: 'home' }), label: L.dir },
  { href: pathFor(locale, { t: 'city', city: th.city }), label: cityName },
  { href: pathFor(locale, key), label: th.name }
])}
<section class="section section--tight">
  <div class="wrap" style="display:grid;grid-template-columns:1.35fr .65fr;gap:clamp(28px,4vw,64px);align-items:start">
    <div>
      <div class="t-card" style="align-items:center;gap:1.4rem">
        <div class="avatar" style="width:96px;height:96px;font-size:2rem;background:hsl(${th.hue} 32% 42%)">${esc(th.initials)}</div>
        <div>
          <p class="eyebrow" style="margin-bottom:.3rem">${esc(th.title)} · ${esc(cityName)}</p>
          <h1 style="font-size:clamp(2rem,4.2vw,3.2rem);margin-bottom:.4rem">${esc(th.fullName)}</h1>
          <div class="t-meta" style="font-size:.86rem">
            <span class="stars">★ ${th.rating}</span><span>${fmt(L.match.rating, { rating: th.rating, count: th.reviews })}</span>
            <span>${esc(fmt(U.exp, { years: th.years }))}</span><span>${esc(fmt(U.since, { year: th.since }))}</span><span>${esc(fmt(U.response, { min: th.responseMinutes }))}</span>
          </div>
          <div class="t-tags" style="margin-top:.6rem">
            ${th.verified ? `<span class="badge badge--forest">${esc(L.match.verified)}</span>` : ''}${th.topRated ? `<span class="badge">${esc(L.match.topRated)}</span>` : ''}
            <span class="t-tag">${esc(U.langs)}: ${esc(th.languages.join(', '))}</span>
          </div>
        </div>
      </div>

      <h3 style="margin-top:2.4rem">${esc(U.about)} ${esc(th.fullName.split(' ')[0])}</h3>
      <p class="lede" style="font-size:1.05rem">${esc(x.bio)}</p>

      <div class="panel" style="margin-top:1.8rem;border-color:color-mix(in srgb,var(--forest) 35%,transparent)">
        <h4 style="margin-bottom:.4rem">${esc(U.verify)}</h4>
        <p class="small muted" style="margin-bottom:.8rem">${esc(U.verifiedOn)}</p>
        <div class="t-tags">${seals.map(([k, label]) => `<span class="t-tag t-tag--ok">✓ ${esc(label)}</span>`).join('')}</div>
      </div>

      <div class="grid g2" style="margin-top:2rem">
        <div><h3>${esc(U.certs)}</h3><ul class="ticks">${x.certifications.map((c) => `<li>${esc(c)}</li>`).join('')}</ul></div>
        <div><h3>${esc(U.equip)}</h3><ul class="ticks">${x.equipment.map((c) => `<li>${esc(c)}</li>`).join('')}</ul></div>
        <div><h3>${esc(U.coverage)}</h3><p class="small muted">${esc(fmt(U.radius, { km: th.radiusKm, city: cityName }))}</p><div class="tag-row">${x.districts.map((d) => `<a href="${withBase(pathFor(locale, { t: 'city', city: th.city }))}">${esc(d)}</a>`).join('')}</div></div>
        <div><h3>${esc(U.avail)}</h3><ul class="ticks">${x.availability.map((a) => `<li>${esc(a)}</li>`).join('')}</ul></div>
      </div>

      <h3 style="margin-top:2.4rem">${esc(U.menu)}</h3>
      ${offered.map((s) => `<a class="menu-row" href="${withBase(pathFor(locale, { t: 'service', slug: s.slug }))}">
        <strong>${esc(s.i18n[locale].name)}</strong>
        <span class="menu-row__price">${esc(L.common.from)} ${esc(money(s.price[cur], cur))} <small>· ${s.durations.join('/')} ${esc(L.common.minutes)}</small></span>
        <p>${esc(s.i18n[locale].tagline)}</p></a>`).join('')}

      <h3 style="margin-top:2.4rem">${esc(U.reviews)}</h3>
      <div class="grid g3">${x.reviews.map((rv) => `<figure class="quote" style="margin:0"><span class="stars">${stars(rv.rating)}</span><p style="font-size:1rem;margin-top:.5rem">&ldquo;${esc(rv.text)}&rdquo;</p>
        <footer><b>${esc(rv.name)}</b> · ${esc(rv.date)} · ${esc(serviceBySlug[rv.service]?.i18n[locale].name || '')}</footer></figure>`).join('')}</div>
    </div>

    <aside class="panel" style="position:sticky;top:96px">
      <div class="card__price" style="font-size:2rem">${esc(money(Math.min(...offered.map((s) => s.price[cur])), cur))}<small style="margin-top:.4rem">${esc(L.common.from)} · 60 ${esc(L.common.minutes)}</small></div>
      <hr class="rule" style="margin:1.2rem 0">
      <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;font-size:.86rem">
        <dt class="muted">${esc(L.nav.cities)}</dt><dd style="margin:0">${esc(cityName)}</dd>
        <dt class="muted">${esc(L.match.radius)}</dt><dd style="margin:0">${th.radiusKm} km</dd>
        <dt class="muted">${esc(U.langs)}</dt><dd style="margin:0">${esc(th.languages.join(', '))}</dd>
      </dl>
      <a class="btn btn--gold btn--block" style="margin-top:1.4rem" href="${bookHref}">${esc(fmt(U.book, { name: th.name }))}</a>
      <p class="small muted center" style="margin-top:.8rem">${esc(L.booking.payLater)}</p>
    </aside>
  </div>
</section>

<section class="section section--paper">
  <div class="wrap">
    ${sectionHead(null, fmt(U.otherIn, { city: cityName }), null)}
    <div class="grid g3">${others.map((o) => therapistCard(locale, o, services)).join('')}</div>
  </div>
</section>`;

  return layout(base(locale, key, {
    title: `${th.fullName} — ${th.title}, ${cityName} | ${site.brand}`,
    description: fmt(U.metaDesc, { name: th.fullName, title: th.title, city: cityName, count: offered.length, rating: th.rating, reviews: th.reviews, km: th.radiusKm }),
    body,
    extraLd: [
      breadcrumbLd([
        { href: pathFor(locale, { t: 'home' }), label: L.dir },
        { href: pathFor(locale, { t: 'city', city: th.city }), label: cityName },
        { href: pathFor(locale, key), label: th.name }
      ]),
      {
        '@context': 'https://schema.org',
        '@type': 'Person',
        '@id': `${absolute(pathFor(locale, key))}#person`,
        name: th.fullName,
        jobTitle: th.title,
        knowsLanguage: th.languages,
        worksFor: { '@id': `${site.origin}/#organization` },
        homeLocation: { '@type': 'City', name: cityName },
        hasCredential: x.certifications.map((c) => ({ '@type': 'EducationalOccupationalCredential', name: c })),
        makesOffer: offered.map((s) => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', name: s.i18n[locale].name }, price: s.price[cur], priceCurrency: cur })),
        aggregateRating: { '@type': 'AggregateRating', ratingValue: th.rating, reviewCount: th.reviews, bestRating: 5 },
        review: x.reviews.map((rv) => ({ '@type': 'Review', author: { '@type': 'Person', name: rv.name }, datePublished: rv.date, reviewBody: rv.text, reviewRating: { '@type': 'Rating', ratingValue: rv.rating, bestRating: 5 } }))
      }
    ]
  }));
}
