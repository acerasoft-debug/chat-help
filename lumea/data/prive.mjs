/** Luméa Privé — the membership layer. Prices are monthly, EUR (CHF shown ×1.2 rounded). */
export const priveTiers = [
  { slug: 'essentiel', price: 189, accent: '#9FB8AD' },
  { slug: 'signature', price: 389, accent: '#C9A961', featured: true },
  { slug: 'residence', price: 1490, accent: '#16130f' }
];

export const prive = {
  de: {
    nav: 'Privé',
    eyebrow: 'Luméa Privé',
    title: 'Die Mitgliedschaft für Menschen, die nicht warten.',
    sub: 'Priorisierte Zeitfenster, eine feste Therapeutin, die Ihren Körper kennt, und ein Concierge, der um zwei Uhr nachts noch antwortet. Privé ist für Gäste, für die ein Termin keine Ausnahme, sondern Routine ist.',
    perMonth: 'pro Monat',
    cta: 'Mitglied werden',
    ctaSub: 'Monatlich kündbar. Erste Behandlung innerhalb von 72 Stunden.',
    tiers: {
      essentiel: { name: 'Essentiel', tag: 'Der Einstieg', desc: 'Für ein monatliches Ritual mit Vorrang und festem Preis.', includes: ['1 Behandlung à 90 Min. pro Monat inklusive', 'Priorisierte Zeitfenster vor Nicht-Mitgliedern', '15 % auf alle weiteren Behandlungen', 'Kostenfreie Stornierung bis 4 Stunden vorher', 'Ungenutzte Behandlungen 3 Monate übertragbar'] },
      signature: { name: 'Signature', tag: 'Meistgewählt', desc: 'Eine feste Therapeutin, die Ihren Körper, Ihren Druck und Ihren Zeitplan kennt.', includes: ['2 Behandlungen à 90 Min. pro Monat inklusive', 'Feste Therapeutin mit Vertretung bei Abwesenheit', 'Same-Day-Garantie in Kernstädten bis 16 Uhr', '20 % auf weitere Behandlungen und Ergänzungen', 'Behandlungsprotokoll und Fortschrittsnotizen', 'Gültig in allen 20 Städten — auch auf Reisen'] },
      residence: { name: 'Résidence', tag: 'Haushalt & Familie', desc: 'Für Familien, Haushalte, Yachten und Residenzen mit wöchentlichem Bedarf.', includes: ['Wöchentliche Behandlung für bis zu 4 Personen', 'Zwei feste Therapeut:innen (Massage & Kosmetik)', '24/7-Concierge per WhatsApp und Telefon', 'Late-Night- und Duo-Slots ohne Aufschlag', 'Reisebegleitung nach Absprache (Villa, Yacht, Chalet)', 'Vierteljährliche Haut- und Körperanalyse', 'Gästeprofile für Besuch und Familie'] }
    },
    faq: [
      { q: 'Kann ich meine Therapeutin wählen?', a: 'Ja. Nach Ihrer ersten Behandlung schlagen wir zwei Profile vor; Sie entscheiden. Wechsel ist jederzeit möglich.' },
      { q: 'Was passiert, wenn ich reise?', a: 'Ihre Mitgliedschaft gilt in allen 20 Städten. In Signature und Résidence übermitteln wir Ihr Behandlungsprotokoll vorab an die lokale Therapeutin.' },
      { q: 'Wie kündige ich?', a: 'Mit einem Klick im Konto, monatlich zum Laufzeitende. Übertragene Behandlungen bleiben drei Monate gültig.' }
    ]
  },
  en: {
    nav: 'Privé',
    eyebrow: 'Luméa Privé',
    title: 'The membership for people who do not wait.',
    sub: 'Priority slots, a dedicated therapist who knows your body, and a concierge who still answers at two in the morning. Privé is for guests for whom a treatment is a routine, not an exception.',
    perMonth: 'per month',
    cta: 'Become a member',
    ctaSub: 'Cancel monthly. First treatment within 72 hours.',
    tiers: {
      essentiel: { name: 'Essentiel', tag: 'The entry point', desc: 'For one monthly ritual with priority and a fixed price.', includes: ['1 × 90-minute treatment per month included', 'Priority slots ahead of non-members', '15 % off every additional treatment', 'Free cancellation up to 4 hours before', 'Unused treatments roll over for 3 months'] },
      signature: { name: 'Signature', tag: 'Most chosen', desc: 'A dedicated therapist who knows your body, your pressure and your calendar.', includes: ['2 × 90-minute treatments per month included', 'Dedicated therapist with cover during absence', 'Same-day guarantee in core cities until 4 pm', '20 % off additional treatments and add-ons', 'Treatment log and progress notes', 'Valid in all 20 cities — including while travelling'] },
      residence: { name: 'Résidence', tag: 'Household & family', desc: 'For families, households, yachts and residences with weekly needs.', includes: ['Weekly treatment for up to 4 people', 'Two dedicated therapists (massage & skincare)', '24/7 concierge via WhatsApp and phone', 'Late-night and duo slots without surcharge', 'Travel accompaniment by arrangement (villa, yacht, chalet)', 'Quarterly skin and body assessment', 'Guest profiles for visitors and family'] }
    },
    faq: [
      { q: 'Can I choose my therapist?', a: 'Yes. After your first treatment we propose two profiles; you decide. You can switch at any time.' },
      { q: 'What happens when I travel?', a: 'Your membership is valid in all 20 cities. On Signature and Résidence we forward your treatment log to the local therapist in advance.' },
      { q: 'How do I cancel?', a: 'One click in your account, monthly at the end of the term. Rolled-over treatments stay valid for three months.' }
    ]
  },
  es: {
    nav: 'Privé',
    eyebrow: 'Luméa Privé',
    title: 'La membresía para quienes no esperan.',
    sub: 'Franjas prioritarias, una terapeuta fija que conoce tu cuerpo y un conserje que responde a las dos de la madrugada. Privé es para clientes para quienes un tratamiento es rutina, no excepción.',
    perMonth: 'al mes',
    cta: 'Hacerme miembro',
    ctaSub: 'Cancelación mensual. Primer tratamiento en 72 horas.',
    tiers: {
      essentiel: { name: 'Essentiel', tag: 'La puerta de entrada', desc: 'Para un ritual mensual con prioridad y precio fijo.', includes: ['1 tratamiento de 90 min al mes incluido', 'Franjas prioritarias frente a no miembros', '15 % en cada tratamiento adicional', 'Cancelación gratuita hasta 4 horas antes', 'Los tratamientos no usados se acumulan 3 meses'] },
      signature: { name: 'Signature', tag: 'La más elegida', desc: 'Una terapeuta fija que conoce tu cuerpo, tu presión y tu agenda.', includes: ['2 tratamientos de 90 min al mes incluidos', 'Terapeuta fija con sustitución en ausencias', 'Garantía en el mismo día en ciudades principales hasta las 16 h', '20 % en tratamientos y complementos adicionales', 'Registro de tratamientos y notas de progreso', 'Válida en las 20 ciudades, también de viaje'] },
      residence: { name: 'Résidence', tag: 'Hogar y familia', desc: 'Para familias, hogares, yates y residencias con necesidad semanal.', includes: ['Tratamiento semanal para hasta 4 personas', 'Dos terapeutas fijas (masaje y estética)', 'Conserjería 24/7 por WhatsApp y teléfono', 'Franjas nocturnas y dúo sin recargo', 'Acompañamiento en viajes previo acuerdo (villa, yate, chalet)', 'Análisis trimestral de piel y cuerpo', 'Perfiles de invitados para visitas y familia'] }
    },
    faq: [
      { q: '¿Puedo elegir a mi terapeuta?', a: 'Sí. Tras tu primer tratamiento te proponemos dos perfiles y tú decides. Puedes cambiar cuando quieras.' },
      { q: '¿Qué pasa cuando viajo?', a: 'Tu membresía es válida en las 20 ciudades. En Signature y Résidence enviamos tu registro de tratamientos a la terapeuta local con antelación.' },
      { q: '¿Cómo cancelo?', a: 'Con un clic en tu cuenta, cada mes al final del periodo. Los tratamientos acumulados siguen siendo válidos tres meses.' }
    ]
  },
  fr: {
    nav: 'Privé',
    eyebrow: 'Luméa Privé',
    title: 'L’abonnement pour ceux qui n’attendent pas.',
    sub: 'Créneaux prioritaires, une thérapeute attitrée qui connaît votre corps, et un concierge qui répond encore à deux heures du matin. Privé s’adresse aux clients pour qui un soin est une routine, pas une exception.',
    perMonth: 'par mois',
    cta: 'Devenir membre',
    ctaSub: 'Résiliable chaque mois. Premier soin sous 72 heures.',
    tiers: {
      essentiel: { name: 'Essentiel', tag: 'L’entrée', desc: 'Pour un rituel mensuel avec priorité et prix fixe.', includes: ['1 soin de 90 min par mois inclus', 'Créneaux prioritaires avant les non-membres', '15 % sur chaque soin supplémentaire', 'Annulation gratuite jusqu’à 4 heures avant', 'Soins non utilisés reportables 3 mois'] },
      signature: { name: 'Signature', tag: 'Le plus choisi', desc: 'Une thérapeute attitrée qui connaît votre corps, votre pression et votre agenda.', includes: ['2 soins de 90 min par mois inclus', 'Thérapeute attitrée avec remplacement en cas d’absence', 'Garantie le jour même dans les villes principales jusqu’à 16 h', '20 % sur soins et options supplémentaires', 'Journal de soins et notes de progression', 'Valable dans les 20 villes — même en voyage'] },
      residence: { name: 'Résidence', tag: 'Maison & famille', desc: 'Pour les familles, foyers, yachts et résidences à besoin hebdomadaire.', includes: ['Soin hebdomadaire pour jusqu’à 4 personnes', 'Deux thérapeutes attitrées (massage & visage)', 'Concierge 24/7 par WhatsApp et téléphone', 'Créneaux de nuit et duo sans supplément', 'Accompagnement en voyage sur accord (villa, yacht, chalet)', 'Bilan peau et corps trimestriel', 'Profils invités pour visiteurs et famille'] }
    },
    faq: [
      { q: 'Puis-je choisir ma thérapeute ?', a: 'Oui. Après votre premier soin, nous proposons deux profils ; vous décidez. Vous pouvez changer à tout moment.' },
      { q: 'Et si je voyage ?', a: 'Votre abonnement est valable dans les 20 villes. En Signature et Résidence, nous transmettons votre journal de soins à la thérapeute locale à l’avance.' },
      { q: 'Comment résilier ?', a: 'En un clic dans votre compte, chaque mois à l’échéance. Les soins reportés restent valables trois mois.' }
    ]
  },
  it: {
    nav: 'Privé',
    eyebrow: 'Luméa Privé',
    title: 'L’abbonamento per chi non aspetta.',
    sub: 'Fasce prioritarie, una terapista fissa che conosce il tuo corpo e un concierge che risponde anche alle due di notte. Privé è per gli ospiti per cui un trattamento è una routine, non un’eccezione.',
    perMonth: 'al mese',
    cta: 'Diventa membro',
    ctaSub: 'Disdetta mensile. Primo trattamento entro 72 ore.',
    tiers: {
      essentiel: { name: 'Essentiel', tag: 'L’ingresso', desc: 'Per un rituale mensile con priorità e prezzo fisso.', includes: ['1 trattamento da 90 min al mese incluso', 'Fasce prioritarie rispetto ai non membri', '15 % su ogni trattamento aggiuntivo', 'Cancellazione gratuita fino a 4 ore prima', 'Trattamenti non usati riportabili per 3 mesi'] },
      signature: { name: 'Signature', tag: 'Il più scelto', desc: 'Una terapista fissa che conosce il tuo corpo, la tua pressione e la tua agenda.', includes: ['2 trattamenti da 90 min al mese inclusi', 'Terapista fissa con sostituzione in caso di assenza', 'Garanzia in giornata nelle città principali fino alle 16', '20 % su trattamenti ed extra aggiuntivi', 'Registro dei trattamenti e note di progresso', 'Valido in tutte le 20 città — anche in viaggio'] },
      residence: { name: 'Résidence', tag: 'Casa & famiglia', desc: 'Per famiglie, case, yacht e residenze con esigenza settimanale.', includes: ['Trattamento settimanale fino a 4 persone', 'Due terapiste fisse (massaggio & skincare)', 'Concierge 24/7 via WhatsApp e telefono', 'Fasce notturne e duo senza supplemento', 'Accompagnamento in viaggio su accordo (villa, yacht, chalet)', 'Analisi trimestrale di pelle e corpo', 'Profili ospiti per visitatori e famiglia'] }
    },
    faq: [
      { q: 'Posso scegliere la mia terapista?', a: 'Sì. Dopo il primo trattamento proponiamo due profili; decidi tu. Puoi cambiare in qualsiasi momento.' },
      { q: 'E se viaggio?', a: 'L’abbonamento vale in tutte le 20 città. Con Signature e Résidence inviamo in anticipo il tuo registro dei trattamenti alla terapista locale.' },
      { q: 'Come disdico?', a: 'Con un clic nell’account, ogni mese alla scadenza. I trattamenti riportati restano validi tre mesi.' }
    ]
  }
};
