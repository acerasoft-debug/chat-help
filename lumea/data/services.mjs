import { de } from './content/services.de.mjs';
import { en } from './content/services.en.mjs';
import { es } from './content/services.es.mjs';

/**
 * Catalogue core: locale-independent facts (slug, price, duration, category).
 * Localised copy is merged in from data/content/services.<locale>.mjs so that
 * translators never have to touch pricing logic.
 */
export const categories = [
  { slug: 'signature', order: 1, de: 'Signature Rituale', en: 'Signature Rituals', es: 'Rituales Signature' },
  { slug: 'body', order: 2, de: 'Körper & Contouring', en: 'Body & Contouring', es: 'Cuerpo & Contorno' },
  { slug: 'therapy', order: 3, de: 'Therapeutisch & Regeneration', en: 'Therapeutic & Recovery', es: 'Terapéutico & Recuperación' },
  { slug: 'skincare', order: 4, de: 'Gesicht & Hautpflege', en: 'Face & Skincare', es: 'Rostro & Cuidado de la piel' }
];

const core = [
  { slug: 'anti-cellulite', category: 'body', durations: [60, 90], price: { EUR: 149, CHF: 179 }, popular: true, hero: true, accent: '#C9A961', pressure: 3 },
  { slug: 'lymphatic-drainage', category: 'body', durations: [60, 90], price: { EUR: 139, CHF: 169 }, popular: true, accent: '#9FB8AD', pressure: 1 },
  { slug: 'body-sculpt-wrap', category: 'body', durations: [90], price: { EUR: 189, CHF: 229 }, accent: '#B98E6F', pressure: 2 },
  { slug: 'cupping-fascia', category: 'body', durations: [60, 90], price: { EUR: 155, CHF: 185 }, accent: '#A8837A', pressure: 4 },
  { slug: 'signature-lumea', category: 'signature', durations: [90, 120], price: { EUR: 219, CHF: 259 }, popular: true, hero: true, accent: '#C9A961', pressure: 2 },
  { slug: 'aromatherapy', category: 'signature', durations: [60, 90], price: { EUR: 145, CHF: 175 }, popular: true, accent: '#C08B7A', pressure: 2 },
  { slug: 'hot-stone', category: 'signature', durations: [90], price: { EUR: 175, CHF: 209 }, accent: '#B4733F', pressure: 2 },
  { slug: 'lomi-lomi', category: 'signature', durations: [90], price: { EUR: 169, CHF: 199 }, accent: '#D2A679', pressure: 2 },
  { slug: 'duo-couples', category: 'signature', durations: [60, 90], price: { EUR: 289, CHF: 349 }, popular: true, accent: '#C9A961', pressure: 2 },
  { slug: 'classic-swedish', category: 'therapy', durations: [60, 90], price: { EUR: 129, CHF: 155 }, popular: true, accent: '#9AA7B5', pressure: 2 },
  { slug: 'deep-tissue', category: 'therapy', durations: [60, 90], price: { EUR: 149, CHF: 179 }, popular: true, hero: true, accent: '#7E8C99', pressure: 5 },
  { slug: 'sports-recovery', category: 'therapy', durations: [60, 90], price: { EUR: 155, CHF: 185 }, accent: '#6F8A94', pressure: 4 },
  { slug: 'prenatal', category: 'therapy', durations: [60], price: { EUR: 139, CHF: 169 }, accent: '#D6B2B8', pressure: 1 },
  { slug: 'thai-yoga', category: 'therapy', durations: [90, 120], price: { EUR: 165, CHF: 199 }, accent: '#8FA37E', pressure: 4 },
  { slug: 'reflexology', category: 'therapy', durations: [60], price: { EUR: 125, CHF: 149 }, accent: '#A9967E', pressure: 3 },
  { slug: 'head-neck-shoulder', category: 'therapy', durations: [30, 60], price: { EUR: 89, CHF: 109 }, accent: '#8E97A8', pressure: 3 },
  { slug: 'signature-facial', category: 'skincare', durations: [60, 90], price: { EUR: 159, CHF: 189 }, popular: true, hero: true, accent: '#E0C9A6', pressure: 1 },
  { slug: 'hydra-glow', category: 'skincare', durations: [60, 90], price: { EUR: 189, CHF: 225 }, popular: true, accent: '#A8C4C9', pressure: 1 },
  { slug: 'lifting-facial', category: 'skincare', durations: [75], price: { EUR: 209, CHF: 249 }, accent: '#C9A961', pressure: 1 },
  { slug: 'enzyme-peel', category: 'skincare', durations: [45, 60], price: { EUR: 135, CHF: 165 }, accent: '#D9B48F', pressure: 1 },
  { slug: 'mens-facial', category: 'skincare', durations: [60], price: { EUR: 149, CHF: 179 }, accent: '#8B8D8F', pressure: 1 },
  { slug: 'eye-decollete', category: 'skincare', durations: [45], price: { EUR: 119, CHF: 145 }, accent: '#CDBBC4', pressure: 1 },
  { slug: 'hifu-lifting', category: 'skincare', durations: [60, 90], price: { EUR: 349, CHF: 419 }, popular: true, hero: true, accent: '#B8A27A', pressure: 2 }
];

const copy = { de, en, es };

export const services = core.map((s) => ({
  ...s,
  i18n: Object.fromEntries(
    Object.entries(copy).map(([loc, dict]) => {
      const entry = dict[s.slug];
      if (!entry) throw new Error(`Missing ${loc} copy for service "${s.slug}"`);
      return [loc, entry];
    })
  )
}));

export const serviceBySlug = Object.fromEntries(services.map((s) => [s.slug, s]));

/** Services that get their own city landing page (service × city programmatic SEO). */
export const moneyServices = services.filter((s) => s.popular).map((s) => s.slug);

/** Optional extras offered at checkout, priced as flat add-ons. */
export const addons = [
  { slug: 'hot-towels', price: { EUR: 0, CHF: 0 }, de: 'Warme Kompressen-Ritual', en: 'Hot towel ritual', es: 'Ritual de toallas calientes' },
  { slug: 'dry-brushing', price: { EUR: 19, CHF: 25 }, de: 'Trockenbürsten-Vorbereitung', en: 'Dry brushing prep', es: 'Cepillado en seco' },
  { slug: 'scalp-ritual', price: { EUR: 29, CHF: 35 }, de: 'Kopfhaut- & Haaröl-Ritual (15 Min.)', en: 'Scalp & hair-oil ritual (15 min)', es: 'Ritual de cuero cabelludo (15 min)' },
  { slug: 'cbd-oil', price: { EUR: 25, CHF: 30 }, de: 'Bio-CBD-Öl Upgrade', en: 'Organic CBD oil upgrade', es: 'Upgrade de aceite CBD ecológico' },
  { slug: 'sound-bath', price: { EUR: 39, CHF: 49 }, de: 'Klangschalen-Abschluss', en: 'Singing-bowl finish', es: 'Cierre con cuencos tibetanos' },
  { slug: 'second-therapist', price: { EUR: 120, CHF: 145 }, de: 'Vier-Hände (zweite Therapeutin)', en: 'Four-hands (second therapist)', es: 'Cuatro manos (segunda terapeuta)' },
  { slug: 'late-night', price: { EUR: 45, CHF: 55 }, de: 'Late-Night-Slot (22–02 Uhr)', en: 'Late-night slot (10pm–2am)', es: 'Franja nocturna (22:00–02:00)' }
];
