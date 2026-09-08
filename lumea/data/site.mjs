/** Global brand + deployment configuration. Everything the build reads lives here. */
export const site = {
  brand: 'LUMÉA',
  brandAscii: 'LUMEA',
  legalName: 'Luméa Private Spa Network',
  // Change this to the production domain before the first deploy: it drives
  // canonical URLs, hreflang, sitemap.xml, robots.txt and every JSON-LD @id.
  origin: process.env.LUMEA_ORIGIN || 'https://lumea.spa',
  // Set to a repo sub-path (e.g. '/lumea-private-spa') when hosting on GitHub Pages.
  basePath: process.env.LUMEA_BASE || '',
  defaultLocale: 'de',
  locales: ['de', 'en', 'es'],
  email: 'concierge@lumea.spa',
  phone: '+49 30 5679 8840',
  phoneHref: '+493056798840',
  whatsapp: '+493056798840',
  founded: 2019,
  // Where booking + therapist applications are POSTed. Leave empty to run the
  // built-in demo mode (localStorage + pre-filled mailto handover).
  formEndpoint: process.env.LUMEA_FORM_ENDPOINT || '',
  social: {
    instagram: 'https://instagram.com/lumea.spa',
    linkedin: 'https://www.linkedin.com/company/lumea-spa',
    pinterest: 'https://pinterest.com/lumeaspa'
  },
  // Verification tokens — drop real values in before launch.
  verification: {
    google: process.env.LUMEA_GSC || '',
    bing: process.env.LUMEA_BING || ''
  },
  analytics: {
    plausibleDomain: process.env.LUMEA_PLAUSIBLE || ''
  },
  trust: {
    therapists: 480,
    cities: 20,
    countries: 4,
    rating: 4.9,
    reviewCount: 3162,
    responseMinutes: 12
  },
  currencyByCountry: { DE: 'EUR', AT: 'EUR', CH: 'CHF', ES: 'EUR' }
};

export const localeMeta = {
  de: { htmlLang: 'de', hreflang: 'de', label: 'Deutsch', flag: 'DE', dir: 'ltr' },
  en: { htmlLang: 'en', hreflang: 'en', label: 'English', flag: 'EN', dir: 'ltr' },
  es: { htmlLang: 'es', hreflang: 'es', label: 'Español', flag: 'ES', dir: 'ltr' }
};
