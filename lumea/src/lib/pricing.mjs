/**
 * One price engine for the site, the booking form (mirrored in app.js) and the
 * API: the server never trusts a client-side total.
 *
 *   per session   = base(currency) × DURATION_FACTOR[duration]        (rounded to 5)
 *   persons = 2   = × 1.9 (second therapist, same slot)  — duo rituals already include two
 *   package       = 6 sessions −10 %, 10 sessions −15 %  (body & skincare only)
 *   add-ons       = flat, per booking
 *   voucher       = deducted last, never below 0
 */
import { services, serviceBySlug, addons } from '../../data/services.mjs';

export const DURATION_FACTOR = { 30: 0.6, 45: 0.8, 60: 1, 75: 1.2, 90: 1.4, 120: 1.8 };
export const PACKAGES = [
  { sessions: 1, discount: 0 },
  { sessions: 6, discount: 0.10 },
  { sessions: 10, discount: 0.15 }
];
export const PACKAGE_CATEGORIES = ['body', 'skincare'];
export const round5 = (n) => Math.round(n / 5) * 5;

/** The 60-minute price is the advertised "from" price and stays exact; other durations round to 5. */
export const priceFor = (service, duration, currency = 'EUR') => {
  const f = DURATION_FACTOR[duration] || 1;
  return f === 1 ? service.price[currency] : round5(service.price[currency] * f);
};

export function quote({ service, duration = 60, persons = 1, addons: chosen = [], sessions = 1, currency = 'EUR', voucherBalance = 0 }) {
  const s = typeof service === 'string' ? serviceBySlug[service] : service;
  if (!s) throw new Error('unknown service');
  const dur = s.durations.includes(Number(duration)) ? Number(duration) : s.durations[0];
  const isDuo = s.slug === 'duo-couples';
  const people = isDuo ? 2 : Math.min(Math.max(Number(persons) || 1, 1), 2);
  const perSession = priceFor(s, dur, currency) * (people === 2 && !isDuo ? 1.9 : 1);
  const pkg = PACKAGE_CATEGORIES.includes(s.category) ? PACKAGES.find((p) => p.sessions === Number(sessions)) || PACKAGES[0] : PACKAGES[0];
  const subtotal = round5(perSession * pkg.sessions);
  const packageDiscount = round5(subtotal * pkg.discount);
  const addonItems = [].concat(chosen || []).map((slug) => addons.find((a) => a.slug === slug)).filter(Boolean);
  const addonTotal = addonItems.reduce((n, a) => n + (a.price[currency] || 0), 0);
  const beforeVoucher = subtotal - packageDiscount + addonTotal;
  const voucher = Math.min(Math.max(Number(voucherBalance) || 0, 0), beforeVoucher);
  return {
    currency, duration: dur, persons: people, sessions: pkg.sessions,
    perSession: round5(perSession), subtotal, packageDiscount, packagePct: pkg.discount * 100,
    addons: addonItems.map((a) => ({ slug: a.slug, price: a.price[currency] || 0 })), addonTotal,
    voucher, total: beforeVoucher - voucher
  };
}

/** Price table for a service page: every offered duration in both currencies. */
export const priceTable = (service) => service.durations.map((d) => ({ duration: d, EUR: priceFor(service, d, 'EUR'), CHF: priceFor(service, d, 'CHF') }));

/** Compact table the browser mirrors (see app.js → quoteLocal). */
export const pricingForClient = () => ({
  factors: DURATION_FACTOR, packages: PACKAGES, packageCategories: PACKAGE_CATEGORIES,
  services: Object.fromEntries(services.map((s) => [s.slug, { EUR: s.price.EUR, CHF: s.price.CHF, durations: s.durations, category: s.category }])),
  addons: Object.fromEntries(addons.map((a) => [a.slug, a.price]))
});
