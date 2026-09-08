import { cities, cityBySlug } from './cities.mjs';
import { services } from './services.mjs';

/**
 * Deterministic therapist directory.
 *
 * Profiles are generated from a fixed seed so that every build, the API and the
 * static pages agree on the same roster — no database required for the public
 * site, and the API can import the exact same records for its first seed.
 */
const FIRST = {
  DE: ['Lena', 'Marie', 'Jonas', 'Annika', 'Elif', 'Sophie', 'Maximilian', 'Nadja', 'Tobias', 'Hanna', 'Katrin', 'Bilal', 'Franziska', 'Jan', 'Mira'],
  AT: ['Theresa', 'Lukas', 'Magdalena', 'Felix', 'Johanna', 'Stefan', 'Verena', 'Clemens', 'Isabella', 'Matthias'],
  CH: ['Nina', 'Loris', 'Chantal', 'Yannick', 'Céline', 'Andrin', 'Sarah', 'Fabio', 'Livia', 'Damian'],
  ES: ['Lucía', 'Álvaro', 'Carmen', 'Javier', 'Marta', 'Sergio', 'Paula', 'Nuria', 'Diego', 'Inés', 'Rocío', 'Pablo']
};
const LAST = {
  DE: ['Brandt', 'Keller', 'Neumann', 'Wagner', 'Hoffmann', 'Yildirim', 'Schröder', 'Kruse', 'Lindner', 'Bauer', 'Roth', 'Vogel'],
  AT: ['Gruber', 'Steiner', 'Moser', 'Aigner', 'Hofer', 'Wallner', 'Pichler', 'Brunner'],
  CH: ['Meier', 'Zbinden', 'Rossi', 'Favre', 'Baumgartner', 'Curti', 'Iseli', 'Perrin'],
  ES: ['Navarro', 'Ferrer', 'Delgado', 'Ibáñez', 'Cabrera', 'Molina', 'Serrano', 'Ortega', 'Bermúdez', 'Reyes']
};
const LANGS = {
  DE: [['Deutsch', 'Englisch'], ['Deutsch', 'Englisch', 'Türkisch'], ['Deutsch'], ['Deutsch', 'Englisch', 'Spanisch'], ['Deutsch', 'Russisch']],
  AT: [['Deutsch', 'Englisch'], ['Deutsch'], ['Deutsch', 'Englisch', 'Italienisch']],
  CH: [['Deutsch', 'Englisch'], ['Französisch', 'Englisch'], ['Deutsch', 'Französisch', 'Englisch'], ['Italienisch', 'Deutsch', 'Englisch']],
  ES: [['Español', 'Inglés'], ['Español'], ['Español', 'Inglés', 'Alemán'], ['Español', 'Inglés', 'Francés']]
};
const TITLES = {
  DE: ['Physiotherapeutin & Massagetherapeutin', 'Med. Masseurin', 'Heilpraktikerin (Physiotherapie)', 'Staatl. gepr. Kosmetikerin', 'Sportmasseur', 'Wellness- & Spa-Therapeutin'],
  AT: ['Dipl. Massagetherapeutin', 'Heilmasseurin', 'Kosmetikerin & Fußpflegerin', 'Sportwissenschaftler & Masseur'],
  CH: ['Dipl. Masseurin EMR', 'Med. Masseur FA', 'Dipl. Kosmetikerin', 'Physiotherapeutin FH'],
  ES: ['Fisioterapeuta colegiada', 'Quiromasajista titulada', 'Esteticista titulada', 'Terapeuta deportivo']
};

/** xorshift32 — small, deterministic, no dependencies. */
function rng(seed) {
  let x = seed >>> 0 || 0x2f6e2b1;
  return () => {
    x ^= x << 13; x >>>= 0;
    x ^= x >> 17;
    x ^= x << 5; x >>>= 0;
    return x / 0xffffffff;
  };
}
const pick = (r, arr) => arr[Math.floor(r() * arr.length) % arr.length];

const allSlugs = services.map((s) => s.slug);
const skincareSlugs = services.filter((s) => s.category === 'skincare').map((s) => s.slug);
const bodySlugs = services.filter((s) => s.category !== 'skincare').map((s) => s.slug);

function buildFor(city, index) {
  const r = rng(city.slug.split('').reduce((a, c) => a * 31 + c.charCodeAt(0), 7) + index * 2654435761);
  const cc = city.country;
  const first = pick(r, FIRST[cc]);
  const last = pick(r, LAST[cc]);
  // Roughly a third of the roster are aestheticians, the rest bodywork therapists.
  const isSkincare = r() < 0.32;
  const pool = isSkincare ? skincareSlugs : bodySlugs;
  const count = 3 + Math.floor(r() * 4);
  const picked = new Set();
  while (picked.size < Math.min(count, pool.length)) picked.add(pick(r, pool));
  // Everyone carries one cross-discipline treatment so filters never dead-end.
  picked.add(pick(r, isSkincare ? bodySlugs : skincareSlugs));

  const years = 2 + Math.floor(r() * 18);
  const reviews = 8 + Math.floor(r() * 240);
  const rating = Number((4.5 + r() * 0.5).toFixed(1));
  // Home base jittered ~±6 km around the city centre so distance sorting is meaningful.
  const lat = Number((city.lat + (r() - 0.5) * 0.11).toFixed(5));
  const lng = Number((city.lng + (r() - 0.5) * 0.16).toFixed(5));

  return {
    id: `${city.slug}-${String(index + 1).padStart(2, '0')}`,
    name: `${first} ${last.charAt(0)}.`,
    fullName: `${first} ${last}`,
    title: pick(r, TITLES[cc]),
    city: city.slug,
    country: cc,
    lat,
    lng,
    radiusKm: [8, 12, 15, 20, 25, 30][Math.floor(r() * 6)],
    services: [...picked],
    languages: pick(r, LANGS[cc]),
    years,
    rating,
    reviews,
    responseMinutes: 4 + Math.floor(r() * 22),
    since: 2026 - Math.min(years, 7),
    verified: true,
    topRated: rating >= 4.8 && reviews > 60,
    acceptsShortNotice: r() < 0.45,
    lateNight: r() < 0.25,
    duo: r() < 0.35,
    initials: (first[0] + last[0]).toUpperCase(),
    hue: Math.floor(r() * 360)
  };
}

const popularSlugs = services.filter((s) => s.popular).map((s) => s.slug);

/**
 * Guarantee coverage: every city×popular-service landing page must resolve to
 * at least two bookable profiles, otherwise a generated page would advertise a
 * treatment nobody in that city offers.
 */
function ensureCoverage(roster) {
  for (const slug of popularSlugs) {
    const svc = services.find((s) => s.slug === slug);
    const wantSkincare = svc.category === 'skincare';
    let holders = roster.filter((t) => t.services.includes(slug));
    if (holders.length >= 2) continue;
    const eligible = roster
      .filter((t) => !t.services.includes(slug))
      .filter((t) => {
        const isSkincare = t.services.some((x) => skincareSlugs.includes(x)) &&
          t.services.filter((x) => skincareSlugs.includes(x)).length > t.services.length / 2;
        return wantSkincare ? isSkincare || roster.length < 6 : !isSkincare || roster.length < 6;
      })
      .sort((a, b) => a.services.length - b.services.length);
    const fallback = roster.filter((t) => !t.services.includes(slug)).sort((a, b) => a.services.length - b.services.length);
    for (const t of [...eligible, ...fallback]) {
      if (holders.length >= 2) break;
      if (t.services.includes(slug)) continue;
      t.services.push(slug);
      holders = roster.filter((x) => x.services.includes(slug));
    }
  }
  return roster;
}

/** Public directory — capped per city so the static build stays fast. */
export const therapists = cities.flatMap((city) => {
  const n = Math.max(5, Math.min(12, Math.round(city.therapists / 5)));
  const roster = Array.from({ length: n }, (_, i) => buildFor(city, i));
  return ensureCoverage(roster);
});

export const therapistsByCity = cities.reduce((acc, c) => {
  acc[c.slug] = therapists.filter((t) => t.city === c.slug);
  return acc;
}, {});

export function therapistsFor(citySlug, serviceSlug) {
  return (therapistsByCity[citySlug] || []).filter((t) => !serviceSlug || t.services.includes(serviceSlug));
}

/** Great-circle distance in kilometres. Shared by the site and the API. */
export function haversineKm(aLat, aLng, bLat, bLng) {
  const R = 6371;
  const dLat = ((bLat - aLat) * Math.PI) / 180;
  const dLng = ((bLng - aLng) * Math.PI) / 180;
  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((aLat * Math.PI) / 180) * Math.cos((bLat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

export { allSlugs };

/* ------------------------------------------------------------------------
   Rich profile layer: bios, certifications, specialties, sample reviews.
   Deterministic per therapist id so static pages and the API agree.
   ------------------------------------------------------------------------ */
const CERTS = {
  body: [
    { de: 'Manuelle Lymphdrainage (Vodder, 4 Wochen)', en: 'Manual Lymphatic Drainage (Vodder, 4 weeks)', es: 'Drenaje Linfático Manual (Vodder, 4 semanas)' , fr: 'Drainage lymphatique manuel (Vodder, 4 semaines)', it: 'Drenaggio linfatico manuale (Vodder, 4 settimane)' },
    { de: 'Sportmassage & Faszientherapie', en: 'Sports Massage & Fascia Therapy', es: 'Masaje Deportivo y Terapia Fascial' , fr: 'Massage sportif & thérapie des fascias', it: 'Massaggio sportivo & terapia fasciale' },
    { de: 'Zertifizierte Schwangerschaftsmassage', en: 'Certified Prenatal Massage', es: 'Masaje Prenatal Certificado' , fr: 'Massage prénatal certifié', it: 'Massaggio prenatale certificato' },
    { de: 'Lomi Lomi Nui (Level II)', en: 'Lomi Lomi Nui (Level II)', es: 'Lomi Lomi Nui (Nivel II)' , fr: 'Lomi Lomi Nui (niveau II)', it: 'Lomi Lomi Nui (livello II)' },
    { de: 'Traditionelle Thai-Massage (Wat Po, Bangkok)', en: 'Traditional Thai Massage (Wat Po, Bangkok)', es: 'Masaje Tailandés Tradicional (Wat Po, Bangkok)' , fr: 'Massage thaï traditionnel (Wat Po, Bangkok)', it: 'Massaggio thai tradizionale (Wat Po, Bangkok)' },
    { de: 'Hot-Stone & Wärmetherapie', en: 'Hot Stone & Thermotherapy', es: 'Piedras Calientes y Termoterapia' , fr: 'Pierres chaudes & thermothérapie', it: 'Hot stone & termoterapia' },
    { de: 'Onkologische Massage (S4OM)', en: 'Oncology Massage (S4OM)', es: 'Masaje Oncológico (S4OM)' , fr: 'Massage oncologique (S4OM)', it: 'Massaggio oncologico (S4OM)' },
    { de: 'Erste Hilfe & Notfallmanagement', en: 'First Aid & Emergency Response', es: 'Primeros Auxilios y Emergencias' , fr: 'Premiers secours & urgences', it: 'Primo soccorso & emergenze' }
  ],
  skincare: [
    { de: 'Staatlich geprüfte Kosmetikerin', en: 'State-certified Aesthetician', es: 'Esteticista con titulación oficial' , fr: 'Esthéticienne diplômée d’État', it: 'Estetista qualificata' },
    { de: 'HIFU-Anwenderzertifikat (Gerätehersteller)', en: 'HIFU Practitioner Certificate (manufacturer)', es: 'Certificado HIFU (fabricante)' , fr: 'Certificat praticienne HIFU (fabricant)', it: 'Certificato operatrice HIFU (produttore)' },
    { de: 'Mikrostrom & Radiofrequenz', en: 'Microcurrent & Radiofrequency', es: 'Microcorrientes y Radiofrecuencia' , fr: 'Microcourant & radiofréquence', it: 'Microcorrenti & radiofrequenza' },
    { de: 'Apparative Tiefenreinigung (Hydra)', en: 'Device-assisted Deep Cleanse (Hydra)', es: 'Limpieza Profunda con Aparatología (Hydra)' , fr: 'Nettoyage profond par appareil (Hydra)', it: 'Pulizia profonda con apparecchiatura (Hydra)' },
    { de: 'Chemische Peelings (Level 1–2)', en: 'Chemical Peels (Level 1–2)', es: 'Peelings Químicos (Nivel 1–2)' , fr: 'Peelings chimiques (niveau 1–2)', it: 'Peeling chimici (livello 1–2)' },
    { de: 'Gua Sha & Buccal-Technik', en: 'Gua Sha & Buccal Technique', es: 'Gua Sha y Técnica Bucal' , fr: 'Gua sha & technique buccale', it: 'Gua sha & tecnica buccale' },
    { de: 'Hygiene- & Infektionsschutz', en: 'Hygiene & Infection Control', es: 'Higiene y Control de Infecciones' , fr: 'Hygiène & prévention des infections', it: 'Igiene & controllo delle infezioni' }
  ]
};

const BIO = {
  de: [
    (t, c) => `${t.fullName.split(' ')[0]} arbeitet seit ${t.years} Jahren als ${t.title} und ist seit ${t.since} Teil von Luméa in ${c}. Der Schwerpunkt liegt auf präziser, ruhiger Arbeit: erst zuhören, dann behandeln. Gäste beschreiben den Stil als klar, warm und ohne Eile.`,
    (t, c) => `Nach Ausbildung und mehreren Jahren in Praxis und Spa hat sich ${t.fullName.split(' ')[0]} auf mobile Behandlungen in ${c} spezialisiert — mit eigener Liege, beheizter Auflage und einem festen Ritual, das in jedem Zuhause gleich gut funktioniert. ${t.years} Jahre Erfahrung, ${t.reviews} verifizierte Bewertungen.`,
    (t, c) => `„Der Körper sagt, was er braucht — man muss nur genau hinsehen.“ ${t.fullName.split(' ')[0]} verbindet die Arbeit als ${t.title} mit einem strukturierten Befund vor jeder Behandlung. In ${c} seit ${t.since}, Einsatzradius ${t.radiusKm} km, Antwort in der Regel innerhalb von ${t.responseMinutes} Minuten.`
  ],
  en: [
    (t, c) => `${t.fullName.split(' ')[0]} has worked for ${t.years} years as a ${t.title.toLowerCase()} and has been part of Luméa in ${c} since ${t.since}. The focus is on precise, calm work: listen first, then treat. Guests describe the style as clear, warm and unhurried.`,
    (t, c) => `After training and several years in clinic and spa settings, ${t.fullName.split(' ')[0]} specialised in mobile treatments across ${c} — with own table, heated pad and a fixed ritual that works equally well in every home. ${t.years} years of experience, ${t.reviews} verified reviews.`,
    (t, c) => `"The body tells you what it needs — you only have to look properly." ${t.fullName.split(' ')[0]} pairs ${t.title.toLowerCase()} with a structured assessment before every treatment. In ${c} since ${t.since}, coverage radius ${t.radiusKm} km, usually replies within ${t.responseMinutes} minutes.`
  ],
  es: [
    (t, c) => `${t.fullName.split(' ')[0]} lleva ${t.years} años como ${t.title.toLowerCase()} y forma parte de Luméa en ${c} desde ${t.since}. Su foco es el trabajo preciso y tranquilo: primero escuchar, después tratar. Los clientes describen su estilo como claro, cálido y sin prisas.`,
    (t, c) => `Tras su formación y varios años en clínica y spa, ${t.fullName.split(' ')[0]} se especializó en tratamientos a domicilio en ${c}, con camilla propia, manta térmica y un ritual fijo que funciona igual de bien en cualquier casa. ${t.years} años de experiencia y ${t.reviews} reseñas verificadas.`,
    (t, c) => `«El cuerpo dice lo que necesita; solo hay que mirar bien.» ${t.fullName.split(' ')[0]} combina ${t.title.toLowerCase()} con una valoración estructurada antes de cada tratamiento. En ${c} desde ${t.since}, radio de ${t.radiusKm} km y respuesta habitual en ${t.responseMinutes} minutos.`
  ],
  fr: [
    (t, c) => `${t.fullName.split(' ')[0]} exerce depuis ${t.years} ans (${t.title}) et fait partie de Luméa à ${c} depuis ${t.since}. L’accent est mis sur un travail précis et calme : écouter d’abord, traiter ensuite. Les clients décrivent le style comme clair, chaleureux et sans hâte.`,
    (t, c) => `Après sa formation et plusieurs années en cabinet et en spa, ${t.fullName.split(' ')[0]} s’est spécialisée dans les soins à domicile à ${c} — avec sa propre table, un matelas chauffant et un rituel fixe qui fonctionne aussi bien dans chaque maison. ${t.years} ans d’expérience, ${t.reviews} avis vérifiés.`,
    (t, c) => `« Le corps dit ce dont il a besoin — il suffit de bien regarder. » ${t.fullName.split(' ')[0]} associe son métier (${t.title}) à un bilan structuré avant chaque soin. À ${c} depuis ${t.since}, rayon de ${t.radiusKm} km, réponse généralement sous ${t.responseMinutes} minutes.`
  ],
  it: [
    (t, c) => `${t.fullName.split(' ')[0]} lavora da ${t.years} anni (${t.title}) e fa parte di Luméa a ${c} dal ${t.since}. Il focus è un lavoro preciso e calmo: prima ascoltare, poi trattare. Gli ospiti descrivono lo stile come chiaro, caldo e senza fretta.`,
    (t, c) => `Dopo la formazione e diversi anni in studio e spa, ${t.fullName.split(' ')[0]} si è specializzata nei trattamenti a domicilio a ${c} — con lettino proprio, materassino riscaldato e un rituale fisso che funziona altrettanto bene in ogni casa. ${t.years} anni di esperienza, ${t.reviews} recensioni verificate.`,
    (t, c) => `«Il corpo dice ciò di cui ha bisogno — basta guardare bene.» ${t.fullName.split(' ')[0]} unisce la professione (${t.title}) a una valutazione strutturata prima di ogni trattamento. A ${c} dal ${t.since}, raggio di ${t.radiusKm} km, risposta di solito entro ${t.responseMinutes} minuti.`
  ]
};

const REVIEWS = {
  de: [
    'Pünktlich, leise, absolut professionell. Der Druck war genau richtig und wurde zweimal nachgefragt.',
    'Ich hatte seit Wochen Nackenschmerzen — nach der Behandlung zum ersten Mal wieder durchgeschlafen.',
    'Aufbau in fünf Minuten, alles mitgebracht, Wohnung danach wie vorher. Sehr angenehme Person.',
    'Ehrliche Beratung, keine Verkaufsgespräche. Wir buchen jetzt monatlich.',
    'Die beste Lymphdrainage, die ich je hatte, und ich hatte viele. Beine am Abend deutlich leichter.',
    'Perfekt vor unserer Hochzeit. Haut sah am nächsten Tag wirklich anders aus.'
  ],
  en: [
    'Punctual, quiet, completely professional. Pressure was exactly right and checked twice.',
    'Weeks of neck pain — after the treatment I slept through the night for the first time.',
    'Set up in five minutes, brought everything, flat left exactly as before. Lovely person.',
    'Honest advice, no sales talk. We now book monthly.',
    'Best lymphatic drainage I have ever had, and I have had many. Legs noticeably lighter by evening.',
    'Perfect before our wedding. Skin genuinely looked different the next day.'
  ],
  es: [
    'Puntual, silenciosa, totalmente profesional. La presión fue exacta y la comprobó dos veces.',
    'Semanas de dolor cervical; tras el tratamiento dormí toda la noche por primera vez.',
    'Montaje en cinco minutos, trajo todo y el piso quedó como estaba. Una persona encantadora.',
    'Consejo honesto, sin venta. Ahora reservamos cada mes.',
    'El mejor drenaje linfático que he tenido, y he tenido muchos. Piernas más ligeras esa misma noche.',
    'Perfecto antes de nuestra boda. La piel se veía realmente distinta al día siguiente.'
  ],
  fr: [
    'Ponctuelle, discrète, totalement professionnelle. La pression était exacte et vérifiée deux fois.',
    'Des semaines de douleurs cervicales — après le soin, j’ai dormi toute la nuit pour la première fois.',
    'Installée en cinq minutes, tout apporté, l’appartement laissé comme avant. Une personne charmante.',
    'Conseil honnête, pas de discours commercial. Nous réservons chaque mois maintenant.',
    'Le meilleur drainage lymphatique que j’aie eu, et j’en ai eu beaucoup. Jambes nettement plus légères le soir.',
    'Parfait avant notre mariage. La peau avait vraiment changé le lendemain.'
  ],
  it: [
    'Puntuale, silenziosa, assolutamente professionale. La pressione era giusta ed è stata verificata due volte.',
    'Settimane di dolore cervicale — dopo il trattamento ho dormito tutta la notte per la prima volta.',
    'Montaggio in cinque minuti, tutto portato, casa lasciata come prima. Una persona deliziosa.',
    'Consiglio onesto, niente vendita. Ora prenotiamo ogni mese.',
    'Il miglior drenaggio linfatico che abbia mai fatto, e ne ho fatti tanti. Gambe nettamente più leggere la sera.',
    'Perfetto prima del nostro matrimonio. La pelle era davvero diversa il giorno dopo.'
  ]
};
const REVIEWERS = ['Anna K.', 'M. Berger', 'S. Öztürk', 'Julia R.', 'D. Martín', 'C. Weber', 'L. Fischer', 'P. Navarro', 'T. Huber', 'E. Rossi'];

/** Everything a profile page needs beyond the directory record. Safe to call for any id. */
export function profileExtras(th, locale = 'de') {
  const seed = th.id.split('').reduce((a, c) => a * 33 + c.charCodeAt(0), 11) >>> 0;
  const r = rng(seed);
  const city = cityBySlug[th.city];
  const isSkincare = th.services.filter((s) => skincareSlugs.includes(s)).length > th.services.length / 2;
  const pool = isSkincare ? CERTS.skincare : CERTS.body;
  const certs = new Set();
  while (certs.size < 3 + Math.floor(r() * 2)) certs.add(pick(r, pool));
  const districts = [...city.districts].sort(() => r() - 0.5).slice(0, 3 + Math.floor(r() * 3));
  const availability = [
    { de: 'Werktags vormittags', en: 'Weekday mornings', es: 'Mañanas entre semana' , fr: 'Matins en semaine', it: 'Mattine feriali' },
    { de: 'Werktags abends', en: 'Weekday evenings', es: 'Noches entre semana' , fr: 'Soirs en semaine', it: 'Sere feriali' },
    { de: 'Wochenende', en: 'Weekends', es: 'Fines de semana' , fr: 'Week-ends', it: 'Weekend' },
    ...(th.lateNight ? [{ de: 'Late Night (22–02 Uhr)', en: 'Late night (10pm–2am)', es: 'Franja nocturna (22–02 h)' , fr: 'Nuit (22h–2h)', it: 'Notte (22–02)' }] : []),
    ...(th.acceptsShortNotice ? [{ de: 'Kurzfristig (unter 3 Std.)', en: 'Short notice (under 3 hrs)', es: 'Aviso corto (menos de 3 h)' , fr: 'Court préavis (moins de 3 h)', it: 'Breve preavviso (meno di 3 h)' }] : [])
  ].filter(() => r() < 0.85);
  const reviews = Array.from({ length: 3 }, (_, i) => {
    const d = new Date(2026, 7 - i * 2, 4 + Math.floor(r() * 20));
    return { name: pick(r, REVIEWERS), rating: 5 - (r() < 0.2 ? 1 : 0), date: d.toISOString().slice(0, 10), text: REVIEWS[locale][(seed + i * 7) % REVIEWS[locale].length], service: th.services[i % th.services.length] };
  });
  return {
    bio: BIO[locale][seed % BIO[locale].length](th, city.name[locale]),
    certifications: [...certs].map((c) => c[locale]),
    districts,
    availability: availability.map((a) => a[locale]),
    reviews,
    equipment: (isSkincare
      ? [{ de: 'Apparative Kosmetik', en: 'Skincare devices', es: 'Aparatología estética' , fr: 'Appareils esthétiques', it: 'Apparecchiature estetiche' }, { de: 'LED-Gerät', en: 'LED device', es: 'Equipo LED' , fr: 'Appareil LED', it: 'Dispositivo LED' }, { de: 'Lupenlampe', en: 'Magnifying lamp', es: 'Lámpara lupa' , fr: 'Lampe loupe', it: 'Lampada a lente' }]
      : [{ de: 'Mobile Massageliege', en: 'Mobile massage table', es: 'Camilla portátil' , fr: 'Table de massage portable', it: 'Lettino portatile' }, { de: 'Beheizte Auflage', en: 'Heated table pad', es: 'Manta térmica' , fr: 'Matelas chauffant', it: 'Materassino riscaldato' }, { de: 'Bio-Öle & Klangschale', en: 'Organic oils & singing bowl', es: 'Aceites ecológicos y cuenco' , fr: 'Huiles bio & bol chantant', it: 'Oli biologici & campana tibetana' }]
    ).map((e) => e[locale]),
    verification: { identity: true, qualification: true, insurance: true, background: true },
    isSkincare
  };
}
