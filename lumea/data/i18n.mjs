/** All UI chrome. Service and city copy lives in data/content and data/cities.mjs. */
export const t = {
  de: {
    dir: 'Startseite',
    tagline: 'Privates Spa. Bei Ihnen zu Hause.',
    metaHomeTitle: 'Mobile Premium-Massage & Hautpflege zu Hause | {brand}',
    metaHomeDesc: 'Geprüfte Therapeutinnen und Kosmetikerinnen kommen zu Ihnen — in Deutschland, Österreich, der Schweiz und Spanien. Von Anti-Cellulite bis Signature Facial, buchbar in 60 Sekunden.',
    nav: { services: 'Behandlungen', skincare: 'Hautpflege', cities: 'Städte', how: 'Ablauf', therapists: 'Für Therapeut:innen', journal: 'Journal', login: 'Anmelden', book: 'Jetzt buchen', account: 'Mein Konto' },
    hero: {
      eyebrow: 'Deutschland · Österreich · Schweiz · Spanien',
      title: 'Das Spa kommt zu Ihnen.',
      titleAccent: 'In 90 Minuten.',
      sub: 'Geprüfte Massage-Therapeut:innen und Kosmetiker:innen mit eigener Liege, Bio-Ölen und Musik — bei Ihnen zu Hause, im Hotel oder im Büro. Verfügbarkeit in Ihrer Nähe wird automatisch erkannt.',
      ctaPrimary: 'Verfügbarkeit prüfen',
      ctaSecondary: 'Behandlungen ansehen',
      locating: 'Standort wird erkannt …',
      locatedIn: 'Erkannt: {city} — {count} Therapeut:innen in Ihrer Nähe',
      locateFail: 'Stadt wählen',
      trustLine: '{therapists}+ geprüfte Therapeut:innen · {rating}/5 aus {reviews} Bewertungen · Antwort in ⌀ {minutes} Min.'
    },
    quickBook: { title: 'Verfügbarkeit in Ihrer Nähe', service: 'Behandlung', city: 'Stadt oder Postleitzahl', when: 'Wann', duration: 'Dauer', search: 'Therapeut:innen anzeigen', useLocation: 'Meinen Standort verwenden', today: 'Heute', tomorrow: 'Morgen', thisWeek: 'Diese Woche', flexible: 'Flexibel' },
    sections: {
      popular: 'Meistgebucht',
      popularSub: 'Die Behandlungen, die unsere Gäste in allen vier Ländern am häufigsten wählen.',
      catalogue: 'Das vollständige Menü',
      catalogueSub: 'Zweiundzwanzig Behandlungen — von therapeutischer Tiefenarbeit bis zu apparativer Hautpflege. Alles zu Ihnen nach Hause.',
      how: 'So funktioniert es',
      howSub: 'Vier Schritte von der Anfrage bis zur Nachruhe.',
      cities: 'Wo wir arbeiten',
      citiesSub: 'Zwanzig Städte in vier Ländern — und laufend neue.',
      why: 'Warum {brand}',
      whySub: 'Wir sind keine Vermittlungsbörse. Jede Therapeutin wird persönlich geprüft.',
      testimonials: 'Was Gäste sagen',
      faq: 'Häufige Fragen',
      therapistCta: 'Sie sind Therapeut:in?',
      nearby: 'In Ihrer Nähe verfügbar',
      relatedServices: 'Passt auch dazu',
      relatedCities: 'Auch verfügbar in',
      addons: 'Optionale Ergänzungen',
      districts: 'Stadtteile mit Anfahrt',
      hotels: 'Hotelpartner vor Ort'
    },
    how: [
      { t: 'Standort & Wunsch', d: 'Wir erkennen Ihre Stadt automatisch über Ihre IP oder Ihren Gerätestandort. Sie wählen Behandlung, Dauer und Zeitfenster.' },
      { t: 'Passende Therapeut:in', d: 'Unser Matching vergleicht Entfernung, Spezialisierung, Sprache und Bewertung und zeigt die drei besten Treffer in Ihrer Nähe.' },
      { t: 'Bestätigung in Minuten', d: 'Ihre Therapeutin bestätigt im Schnitt in {minutes} Minuten. Bezahlt wird erst nach der Behandlung.' },
      { t: 'Aufbau & Ritual', d: 'Liege, Wäsche, Öle, Duft und Musik werden mitgebracht und in unter zehn Minuten aufgebaut — und rückstandslos wieder abgebaut.' }
    ],
    why: [
      { t: 'Geprüft, nicht nur registriert', d: 'Ausweis, Ausbildungsnachweis, Berufshaftpflicht und Führungszeugnis werden von unserem Prüfteam einzeln freigegeben. Ohne vollständige Identitätsprüfung arbeitet niemand — ohne Ausnahme.' },
      { t: 'Ausschließlich therapeutisch', d: 'Luméa ist ein Wellness- und Kosmetiknetzwerk. Anfragen außerhalb dieses Rahmens führen zur sofortigen Sperrung des Kontos.' },
      { t: 'Sicherheit auf beiden Seiten', d: 'Verifizierte Konten, geteilter Terminstatus, Notfallkontakt in der App und ein Vertrauensteam, das rund um die Uhr erreichbar ist.' },
      { t: 'Faire Vergütung', d: 'Gäste zahlen im Voraus; der Betrag wird treuhänderisch gehalten und nach der Behandlung ausgezahlt. Therapeut:innen behalten 80 % und das volle Trinkgeld — ohne Zahlungsausfall.' },
      { t: 'Alles inklusive', d: 'Liege, beheizte Auflage, frische Wäsche, Bio-Öle, Duft, Musik und Anfahrt sind im angezeigten Preis enthalten.' },
      { t: 'Vier Länder, eine Handschrift', d: 'Dieselben Standards in Berlin, Wien, Zürich und Marbella — mit lokal ausgebildeten Therapeut:innen.' }
    ],
    faq: [
      { q: 'Wie schnell kann jemand da sein?', a: 'In den Kernstädten liegt die kürzeste Vorlaufzeit bei etwa 90 Minuten. Regulär empfehlen wir eine Buchung 24 Stunden im Voraus, für Duo-Rituale drei Tage.' },
      { q: 'Was ist im Preis enthalten?', a: 'Anfahrt, professionelle Massageliege, beheizte Auflage, frische Wäsche, Bio-Öle, Duft, Musik sowie Auf- und Abbau. Es gibt keine Zusatzkosten außer den optionalen Ergänzungen.' },
      { q: 'Wie wird bezahlt?', a: 'Bei der Buchung im Voraus per Karte, Apple Pay, Google Pay, SEPA-Lastschrift oder in der Schweiz per TWINT. Der Betrag wird treuhänderisch gehalten und erst nach abgeschlossener Behandlung an die Therapeutin ausgezahlt — bei Absage vor Ort oder Nichterscheinen der Therapeutin erhalten Sie den vollen Betrag zurück. Trinkgeld ist optional und geht vollständig an die Therapeutin.' },
      { q: 'Ist jede Therapeutin identitätsgeprüft?', a: 'Ja, ausnahmslos. Ohne geprüften Ausweis, geprüften Ausbildungsnachweis und gültige Berufshaftpflicht wird kein Profil freigeschaltet — auch nicht vorübergehend. Zusätzlich prüfen wir Führungszeugnis und Gewerbeanmeldung. Die Prüfsiegel sehen Sie auf jedem Profil.' },
      { q: 'Kann ich kostenlos stornieren?', a: 'Ja, bis 12 Stunden vor dem Termin kostenfrei. Danach berechnen wir 50 %, da die Therapeutin die Anfahrt bereits eingeplant hat.' },
      { q: 'Ist das Angebot ausschließlich therapeutisch?', a: 'Ja. Luméa vermittelt ausschließlich professionelle Wellness-, Massage- und Kosmetikbehandlungen. Jede Anfrage darüber hinaus führt zur sofortigen und dauerhaften Sperrung.' },
      { q: 'Arbeiten Sie auch mit Hotels und Unternehmen?', a: 'Ja. Wir betreuen Hotelsuiten, Ferienvillen, Yachten und Firmenstandorte in allen zwanzig Städten. Anfragen laufen über das Kontaktformular.' },
      { q: 'Wie werden Therapeut:innen geprüft?', a: 'Vier Stufen: Dokumentenprüfung (Ausweis, Ausbildungsnachweis, Berufshaftpflicht, Führungszeugnis, Gewerbeanmeldung), Identitätsabgleich per Video, Referenzen und ein persönliches Probe-Treffen. Erst nach Freigabe aller Pflichtdokumente durch unser Prüfteam ist ein Profil buchbar. Etwa jede vierte Bewerbung wird angenommen.' },
      { q: 'Was, wenn ich wenig Platz habe?', a: 'Etwa 2 × 2,5 Meter genügen. Ist noch weniger vorhanden, arbeiten wir mit Massagestuhl oder auf der Matte — sagen Sie einfach Bescheid.' }
    ],
    service: { from: 'ab', per: 'für {min} Min.', book: 'Diese Behandlung buchen', duration: 'Dauer', pressure: 'Intensität', included: 'Enthalten', ritual: 'Der Ablauf', benefits: 'Wirkung', forWhom: 'Geeignet für', faqTitle: 'Fragen zu dieser Behandlung', bookIn: '{service} in {city} buchen', allCities: 'Verfügbar in allen Städten' },
    city: { title: '{service} in {city}', heroTitle: 'Mobile Massage & Hautpflege in {city}', intro: 'Geprüfte Therapeut:innen kommen zu Ihnen — nach Hause, ins Hotel oder ins Büro. {count} Profile sind derzeit in {city} aktiv.', therapistCount: '{count} aktive Therapeut:innen', bookNow: 'Verfügbarkeit in {city} prüfen', servicesIn: 'Behandlungen in {city}', localTitle: '{city} im Detail' },
    auth: {
      loginTitle: 'Willkommen zurück',
      loginSub: 'Melden Sie sich an, um Termine zu verwalten, Favoriten zu speichern und schneller zu buchen.',
      registerTitle: 'Konto erstellen',
      registerSub: 'Zwei Minuten — danach buchen Sie mit einem Klick.',
      email: 'E-Mail-Adresse', password: 'Passwort', passwordHint: 'Mindestens 10 Zeichen', name: 'Vor- und Nachname', phone: 'Telefonnummer',
      login: 'Anmelden', register: 'Konto erstellen', logout: 'Abmelden',
      asClient: 'Ich möchte buchen', asTherapist: 'Ich bin Therapeut:in',
      haveAccount: 'Schon ein Konto?', noAccount: 'Noch kein Konto?',
      forgot: 'Passwort vergessen?', remember: 'Angemeldet bleiben',
      terms: 'Mit der Registrierung akzeptieren Sie AGB und Datenschutzerklärung.',
      loginIpNote: 'Aus Sicherheitsgründen protokollieren wir IP-Adresse, Zeitpunkt und Gerät jeder Anmeldung. Sie sehen alle Sitzungen in Ihrem Konto.'
    },
    apply: {
      title: 'Werden Sie Teil von {brand}',
      sub: 'Wir nehmen etwa jede vierte Bewerbung an — und zahlen dafür 80 % Anteil, garantierte Anfahrtspauschale und vollständige Terminhoheit.',
      steps: ['Person', 'Qualifikation', 'Behandlungen', 'Einsatzgebiet', 'Prüfung'],
      fields: {
        firstName: 'Vorname', lastName: 'Nachname', email: 'E-Mail', phone: 'Telefon / WhatsApp',
        country: 'Land', city: 'Stadt', postal: 'Postleitzahl', radius: 'Einsatzradius (km)',
        languages: 'Sprachen', years: 'Berufserfahrung (Jahre)', qualification: 'Ausbildung / Abschluss',
        certificates: 'Zertifikate (Nachweise später hochladen)', insurance: 'Berufshaftpflicht vorhanden',
        services: 'Angebotene Behandlungen', equipment: 'Eigene Ausstattung', availability: 'Verfügbarkeit',
        website: 'Website oder Instagram (optional)', about: 'Kurzvorstellung', password: 'Passwort für Ihr Konto'
      },
      equipmentOptions: ['Mobile Massageliege', 'Beheizte Auflage', 'Massagestuhl', 'Thai-Futonmatte', 'Hot-Stone-Set', 'Apparative Kosmetik', 'LED-Gerät', 'Eigene Bio-Öle'],
      availabilityOptions: ['Werktags vormittags', 'Werktags nachmittags', 'Werktags abends', 'Wochenende', 'Late Night (22–02 Uhr)', 'Kurzfristig (unter 3 Std.)'],
      submit: 'Bewerbung absenden',
      next: 'Weiter', back: 'Zurück',
      success: 'Bewerbung eingegangen. Wir prüfen Ihre Angaben und melden uns innerhalb von 48 Stunden mit dem nächsten Schritt.',
      benefitsTitle: 'Was Sie bekommen',
      benefits: [
        { t: '80 % Anteil, volles Trinkgeld', d: 'Gäste zahlen im Voraus — Sie tragen kein Zahlungsrisiko. Auszahlung nach jeder abgeschlossenen Behandlung, gesammelt jeden Dienstag. Keine Aufnahme- oder Monatsgebühr.' },
        { t: 'Sie bestimmen Radius und Zeiten', d: 'Einsatzgebiet, Verfügbarkeit und Preisniveau legen Sie selbst fest und ändern sie jederzeit.' },
        { t: 'Geprüfte Gäste', d: 'Jeder Gast ist per E-Mail und Telefon verifiziert. Anfragen außerhalb des therapeutischen Rahmens werden sofort gesperrt.' },
        { t: 'Sicherheitssystem', d: 'Geteilter Terminstatus, Notfallkontakt, Check-in vor Ort und ein Vertrauensteam rund um die Uhr.' },
        { t: 'Planbare Auslastung', d: 'Stammgäste, Hotelpartner und Firmenverträge sorgen für wiederkehrende Termine statt Einzelanfragen.' },
        { t: 'Weiterbildung', d: 'Zugang zu Fachfortbildungen in Onkologiemassage, Schwangerschaftsmassage und apparativer Kosmetik.' }
      ],
      requirementsTitle: 'Voraussetzungen',
      requirements: ['Gültiger Ausweis oder Reisepass (Identitätsprüfung mit Video-Abgleich)', 'Abgeschlossene Ausbildung in Massage, Physiotherapie oder Kosmetik — Nachweis wird geprüft', 'Berufshaftpflichtversicherung (Police wird geprüft)', 'Einwandfreies Führungszeugnis, nicht älter als 3 Monate', 'Gewerbeanmeldung oder Selbstständigkeit im Einsatzland', 'Eigene mobile Ausstattung und mindestens zwei Jahre Berufserfahrung']
    },
    booking: {
      title: 'Termin anfragen', step1: 'Behandlung', step2: 'Ort & Zeit', step3: 'Kontakt', step4: 'Bestätigung',
      address: 'Adresse', addressHint: 'Straße, Hausnummer, Etage/Klingel', notes: 'Anmerkungen für Ihre Therapeut:in',
      notesHint: 'Beschwerden, Wunschdruck, Allergien, Haustiere, Parksituation …',
      date: 'Datum', time: 'Uhrzeit', persons: 'Personen', place: 'Wo?',
      placeOptions: ['Zuhause', 'Hotel / Suite', 'Büro', 'Ferienhaus / Villa', 'Yacht'],
      summary: 'Zusammenfassung', total: 'Gesamt', payLater: 'Sichere Vorauszahlung — treuhänderisch gehalten, Auszahlung an die Therapeutin erst nach der Behandlung',
      submit: 'Unverbindlich anfragen', success: 'Anfrage gesendet. Ihre Therapeutin bestätigt in der Regel innerhalb von {minutes} Minuten.'
    },
    match: { idOk: 'Ausweis geprüft', certOk: 'Ausbildung geprüft', insOk: 'Versichert', bgOk: 'Führungszeugnis', title: 'Ihre besten Treffer', distance: '{km} km entfernt', rating: '{rating} ({count})', speaks: 'Spricht', verified: 'Geprüft', topRated: 'Top bewertet', since: 'Seit {year} dabei', bookWith: 'Mit {name} buchen', noResults: 'Für diese Kombination haben wir noch kein Profil in Reichweite. Erweitern Sie den Radius oder hinterlassen Sie Ihre Anfrage — wir melden uns.', radius: 'Umkreis', profile: 'Profil ansehen', responds: 'Antwortet in ⌀ {min} Min.' },
    footer: { services: 'Behandlungen', company: 'Unternehmen', cities: 'Städte', legal: 'Rechtliches', about: 'Über uns', careers: 'Karriere', press: 'Presse', contact: 'Kontakt', imprint: 'Impressum', privacy: 'Datenschutz', terms: 'AGB', cookies: 'Cookie-Einstellungen', therapists: 'Therapeut:in werden', gift: 'Gutscheine', corporate: 'Für Unternehmen', hotels: 'Für Hotels', rights: 'Alle Rechte vorbehalten.', claim: 'Ausschließlich professionelle Wellness- und Kosmetikbehandlungen.' },
    common: { readMore: 'Mehr erfahren', book: 'Buchen', from: 'ab', minutes: 'Min.', all: 'Alle', close: 'Schließen', chooseCity: 'Stadt wählen', chooseService: 'Behandlung wählen', required: 'Pflichtfeld', optional: 'optional', yes: 'Ja', no: 'Nein', or: 'oder', sending: 'Wird gesendet …', backHome: 'Zur Startseite' }
  },

  en: {
    dir: 'Home',
    tagline: 'Private spa. At your door.',
    metaHomeTitle: 'Premium Mobile Massage & Skincare at Home | {brand}',
    metaHomeDesc: 'Vetted therapists and aestheticians come to you — across Germany, Austria, Switzerland and Spain. From anti-cellulite to signature facials, booked in 60 seconds.',
    nav: { services: 'Treatments', skincare: 'Skincare', cities: 'Cities', how: 'How it works', therapists: 'For therapists', journal: 'Journal', login: 'Sign in', book: 'Book now', account: 'My account' },
    hero: {
      eyebrow: 'Germany · Austria · Switzerland · Spain',
      title: 'The spa comes to you.',
      titleAccent: 'Within 90 minutes.',
      sub: 'Vetted massage therapists and aestheticians arrive with their own table, organic oils and music — at home, in your hotel or at the office. Availability near you is detected automatically.',
      ctaPrimary: 'Check availability',
      ctaSecondary: 'Browse treatments',
      locating: 'Detecting your location …',
      locatedIn: 'Detected: {city} — {count} therapists near you',
      locateFail: 'Choose a city',
      trustLine: '{therapists}+ vetted therapists · {rating}/5 from {reviews} reviews · avg. reply in {minutes} min'
    },
    quickBook: { title: 'Availability near you', service: 'Treatment', city: 'City or postcode', when: 'When', duration: 'Duration', search: 'Show therapists', useLocation: 'Use my location', today: 'Today', tomorrow: 'Tomorrow', thisWeek: 'This week', flexible: 'Flexible' },
    sections: {
      popular: 'Most booked',
      popularSub: 'The treatments our guests choose most often across all four countries.',
      catalogue: 'The full menu',
      catalogueSub: 'Twenty-two treatments — from deep therapeutic work to device-assisted skincare. All delivered to your door.',
      how: 'How it works',
      howSub: 'Four steps from request to the quiet afterwards.',
      cities: 'Where we operate',
      citiesSub: 'Twenty cities across four countries — and more each season.',
      why: 'Why {brand}',
      whySub: 'We are not a listing board. Every therapist is vetted in person.',
      testimonials: 'What guests say',
      faq: 'Frequently asked',
      therapistCta: 'Are you a therapist?',
      nearby: 'Available near you',
      relatedServices: 'Pairs well with',
      relatedCities: 'Also available in',
      addons: 'Optional add-ons',
      districts: 'Districts we cover',
      hotels: 'Local hotel partners'
    },
    how: [
      { t: 'Location & intent', d: 'We detect your city automatically from your IP or device location. You choose treatment, duration and time window.' },
      { t: 'The right therapist', d: 'Our matching weighs distance, specialisation, language and rating, and surfaces the three best profiles near you.' },
      { t: 'Confirmed in minutes', d: 'Your therapist confirms in {minutes} minutes on average. Payment happens only after the treatment.' },
      { t: 'Set-up & ritual', d: 'Table, linen, oils, scent and music arrive with her, go up in under ten minutes — and leave no trace behind.' }
    ],
    why: [
      { t: 'Vetted, not merely listed', d: 'ID, qualification certificate, liability insurance and criminal record are each approved individually by our review team. Nobody works without complete identity verification — no exceptions.' },
      { t: 'Strictly therapeutic', d: 'Luméa is a wellness and skincare network. Any request beyond that scope results in immediate, permanent account closure.' },
      { t: 'Safety on both sides', d: 'Verified accounts, shared appointment status, in-app emergency contact and a trust team reachable around the clock.' },
      { t: 'Fair pay', d: 'Guests pay upfront; the amount is held in escrow and paid out after the treatment. Therapists keep 80 % and 100 % of tips — with no payment risk.' },
      { t: 'Everything included', d: 'Table, heated pad, fresh linen, organic oils, scent, music and travel are all inside the displayed price.' },
      { t: 'Four countries, one standard', d: 'The same standards in Berlin, Vienna, Zurich and Marbella — delivered by locally trained therapists.' }
    ],
    faq: [
      { q: 'How fast can someone arrive?', a: 'In core cities the shortest lead time is around 90 minutes. We normally recommend booking 24 hours ahead, and three days for duo rituals.' },
      { q: 'What is included in the price?', a: 'Travel, a professional massage table, heated pad, fresh linen, organic oils, scent, music and full set-up and take-down. There are no extras beyond the optional add-ons.' },
      { q: 'How do I pay?', a: 'Upfront at booking by card, Apple Pay, Google Pay, SEPA direct debit, or TWINT in Switzerland. The amount is held in escrow and released to the therapist only after the treatment is completed — if the therapist cancels or does not show, you are refunded in full. Tips are optional and go entirely to the therapist.' },
      { q: 'Is every therapist identity-verified?', a: 'Yes, without exception. No profile goes live — not even temporarily — without a verified ID, a verified qualification certificate and valid professional liability insurance. We additionally check the criminal record certificate and business registration. The verification seals are shown on every profile.' },
      { q: 'Can I cancel free of charge?', a: 'Yes, free up to 12 hours before the appointment. After that we charge 50 %, since your therapist has already committed the travel slot.' },
      { q: 'Is the service strictly therapeutic?', a: 'Yes. Luméa arranges professional wellness, massage and skincare treatments only. Any request beyond that leads to immediate and permanent removal.' },
      { q: 'Do you work with hotels and companies?', a: 'Yes. We serve hotel suites, holiday villas, yachts and corporate sites in all twenty cities. Enquiries go through the contact form.' },
      { q: 'How are therapists vetted?', a: 'Four stages: document review (ID, qualification certificate, liability insurance, criminal record certificate, business registration), identity match by video, references and an in-person trial session. A profile becomes bookable only after our review team has approved every mandatory document. Roughly one in four applications is accepted.' },
      { q: 'What if I have very little space?', a: 'About 2 × 2.5 metres is enough. With less, we work with a massage chair or on a mat — just tell us in advance.' }
    ],
    service: { from: 'from', per: 'for {min} min', book: 'Book this treatment', duration: 'Duration', pressure: 'Intensity', included: 'Included', ritual: 'The sequence', benefits: 'What it does', forWhom: 'Suitable for', faqTitle: 'Questions about this treatment', bookIn: 'Book {service} in {city}', allCities: 'Available in every city' },
    city: { title: '{service} in {city}', heroTitle: 'Mobile massage & skincare in {city}', intro: 'Vetted therapists come to you — at home, in your hotel or at the office. {count} profiles are currently active in {city}.', therapistCount: '{count} active therapists', bookNow: 'Check availability in {city}', servicesIn: 'Treatments in {city}', localTitle: '{city} in detail' },
    auth: {
      loginTitle: 'Welcome back',
      loginSub: 'Sign in to manage appointments, save favourites and book faster.',
      registerTitle: 'Create your account',
      registerSub: 'Two minutes — then you book in a single click.',
      email: 'Email address', password: 'Password', passwordHint: 'At least 10 characters', name: 'Full name', phone: 'Phone number',
      login: 'Sign in', register: 'Create account', logout: 'Sign out',
      asClient: 'I want to book', asTherapist: 'I am a therapist',
      haveAccount: 'Already have an account?', noAccount: 'No account yet?',
      forgot: 'Forgot password?', remember: 'Keep me signed in',
      terms: 'By registering you accept the Terms and the Privacy Policy.',
      loginIpNote: 'For security we log the IP address, time and device of every sign-in. You can review all sessions in your account.'
    },
    apply: {
      title: 'Join {brand}',
      sub: 'We accept about one in four applications — and pay an 80 % share, a guaranteed travel fee and full control of your own calendar.',
      steps: ['About you', 'Qualification', 'Treatments', 'Coverage', 'Review'],
      fields: {
        firstName: 'First name', lastName: 'Last name', email: 'Email', phone: 'Phone / WhatsApp',
        country: 'Country', city: 'City', postal: 'Postcode', radius: 'Coverage radius (km)',
        languages: 'Languages', years: 'Years of experience', qualification: 'Training / qualification',
        certificates: 'Certificates (upload later)', insurance: 'Professional liability insurance',
        services: 'Treatments you offer', equipment: 'Your equipment', availability: 'Availability',
        website: 'Website or Instagram (optional)', about: 'Short introduction', password: 'Password for your account'
      },
      equipmentOptions: ['Mobile massage table', 'Heated table pad', 'Massage chair', 'Thai futon mat', 'Hot stone set', 'Skincare devices', 'LED device', 'Own organic oils'],
      availabilityOptions: ['Weekday mornings', 'Weekday afternoons', 'Weekday evenings', 'Weekends', 'Late night (10pm–2am)', 'Short notice (under 3 hrs)'],
      submit: 'Submit application',
      next: 'Continue', back: 'Back',
      success: 'Application received. We review your details and come back within 48 hours with the next step.',
      benefitsTitle: 'What you get',
      benefits: [
        { t: '80 % share, all tips yours', d: 'Guests pay upfront — you carry no payment risk. Payout after every completed treatment, settled every Tuesday. No joining or monthly fee.' },
        { t: 'You set radius and hours', d: 'Coverage area, availability and price level are yours to define and change at any time.' },
        { t: 'Verified guests', d: 'Every guest is verified by email and phone. Requests outside the therapeutic scope are removed immediately.' },
        { t: 'Safety system', d: 'Shared appointment status, emergency contact, on-site check-in and a trust team around the clock.' },
        { t: 'Predictable demand', d: 'Regulars, hotel partners and corporate contracts create recurring appointments instead of one-offs.' },
        { t: 'Continued training', d: 'Access to advanced training in oncology massage, prenatal massage and device-assisted skincare.' }
      ],
      requirementsTitle: 'Requirements',
      requirements: ['Valid ID or passport (identity check with video match)', 'Completed training in massage, physiotherapy or aesthetics — certificate is verified', 'Professional liability insurance (policy is verified)', 'Clean criminal record certificate, no older than 3 months', 'Registered self-employment in your country of operation', 'Your own mobile equipment and at least two years of experience']
    },
    booking: {
      title: 'Request an appointment', step1: 'Treatment', step2: 'Place & time', step3: 'Contact', step4: 'Confirmation',
      address: 'Address', addressHint: 'Street, number, floor/buzzer', notes: 'Notes for your therapist',
      notesHint: 'Complaints, preferred pressure, allergies, pets, parking …',
      date: 'Date', time: 'Time', persons: 'People', place: 'Where?',
      placeOptions: ['Home', 'Hotel / suite', 'Office', 'Holiday home / villa', 'Yacht'],
      summary: 'Summary', total: 'Total', payLater: 'Secure prepayment — held in escrow, released to the therapist only after the treatment',
      submit: 'Send a no-obligation request', success: 'Request sent. Your therapist usually confirms within {minutes} minutes.'
    },
    match: { idOk: 'ID verified', certOk: 'Qualification verified', insOk: 'Insured', bgOk: 'Background checked', title: 'Your best matches', distance: '{km} km away', rating: '{rating} ({count})', speaks: 'Speaks', verified: 'Vetted', topRated: 'Top rated', since: 'With us since {year}', bookWith: 'Book with {name}', noResults: 'We have no profile in range for this combination yet. Widen the radius or leave your request — we will come back to you.', radius: 'Radius', profile: 'View profile', responds: 'Replies in ~{min} min' },
    footer: { services: 'Treatments', company: 'Company', cities: 'Cities', legal: 'Legal', about: 'About us', careers: 'Careers', press: 'Press', contact: 'Contact', imprint: 'Imprint', privacy: 'Privacy', terms: 'Terms', cookies: 'Cookie settings', therapists: 'Become a therapist', gift: 'Gift vouchers', corporate: 'For companies', hotels: 'For hotels', rights: 'All rights reserved.', claim: 'Professional wellness and skincare treatments only.' },
    common: { readMore: 'Read more', book: 'Book', from: 'from', minutes: 'min', all: 'All', close: 'Close', chooseCity: 'Choose a city', chooseService: 'Choose a treatment', required: 'Required', optional: 'optional', yes: 'Yes', no: 'No', or: 'or', sending: 'Sending …', backHome: 'Back to home' }
  },

  es: {
    dir: 'Inicio',
    tagline: 'Spa privado. En tu puerta.',
    metaHomeTitle: 'Masaje y estética premium a domicilio | {brand}',
    metaHomeDesc: 'Terapeutas y esteticistas verificados van a tu casa en Alemania, Austria, Suiza y España. Del anticelulítico al facial signature, reservado en 60 segundos.',
    nav: { services: 'Tratamientos', skincare: 'Estética', cities: 'Ciudades', how: 'Cómo funciona', therapists: 'Para terapeutas', journal: 'Journal', login: 'Entrar', book: 'Reservar', account: 'Mi cuenta' },
    hero: {
      eyebrow: 'Alemania · Austria · Suiza · España',
      title: 'El spa viene a ti.',
      titleAccent: 'En 90 minutos.',
      sub: 'Terapeutas de masaje y esteticistas verificados llegan con su propia camilla, aceites ecológicos y música: en casa, en tu hotel o en la oficina. Detectamos automáticamente la disponibilidad cerca de ti.',
      ctaPrimary: 'Ver disponibilidad',
      ctaSecondary: 'Ver tratamientos',
      locating: 'Detectando tu ubicación …',
      locatedIn: 'Detectado: {city} — {count} terapeutas cerca de ti',
      locateFail: 'Elige tu ciudad',
      trustLine: '{therapists}+ terapeutas verificados · {rating}/5 de {reviews} reseñas · respuesta media en {minutes} min'
    },
    quickBook: { title: 'Disponibilidad cerca de ti', service: 'Tratamiento', city: 'Ciudad o código postal', when: 'Cuándo', duration: 'Duración', search: 'Ver terapeutas', useLocation: 'Usar mi ubicación', today: 'Hoy', tomorrow: 'Mañana', thisWeek: 'Esta semana', flexible: 'Flexible' },
    sections: {
      popular: 'Los más reservados',
      popularSub: 'Los tratamientos que más eligen nuestros clientes en los cuatro países.',
      catalogue: 'La carta completa',
      catalogueSub: 'Veintidós tratamientos, del trabajo terapéutico profundo a la estética con aparatología. Todo en tu domicilio.',
      how: 'Cómo funciona',
      howSub: 'Cuatro pasos desde la solicitud hasta la calma final.',
      cities: 'Dónde operamos',
      citiesSub: 'Veinte ciudades en cuatro países, y más cada temporada.',
      why: 'Por qué {brand}',
      whySub: 'No somos un tablón de anuncios. Cada terapeuta pasa una verificación personal.',
      testimonials: 'Lo que dicen los clientes',
      faq: 'Preguntas frecuentes',
      therapistCta: '¿Eres terapeuta?',
      nearby: 'Disponible cerca de ti',
      relatedServices: 'Combina bien con',
      relatedCities: 'También disponible en',
      addons: 'Complementos opcionales',
      districts: 'Barrios que cubrimos',
      hotels: 'Hoteles asociados'
    },
    how: [
      { t: 'Ubicación e intención', d: 'Detectamos tu ciudad automáticamente por IP o por la ubicación del dispositivo. Tú eliges tratamiento, duración y franja horaria.' },
      { t: 'La terapeuta adecuada', d: 'Nuestro algoritmo pondera distancia, especialización, idioma y valoración, y muestra los tres mejores perfiles cerca de ti.' },
      { t: 'Confirmado en minutos', d: 'Tu terapeuta confirma en {minutes} minutos de media. El pago se realiza solo después del tratamiento.' },
      { t: 'Montaje y ritual', d: 'Camilla, ropa limpia, aceites, aroma y música llegan con ella, se montan en menos de diez minutos y no dejan rastro.' }
    ],
    why: [
      { t: 'Verificados, no solo registrados', d: 'DNI, titulación, seguro de responsabilidad civil y antecedentes penales se aprueban uno a uno por nuestro equipo de verificación. Nadie trabaja sin la identidad completamente verificada, sin excepciones.' },
      { t: 'Estrictamente terapéutico', d: 'Luméa es una red de bienestar y estética. Cualquier solicitud fuera de ese marco supone el cierre inmediato y permanente de la cuenta.' },
      { t: 'Seguridad para ambas partes', d: 'Cuentas verificadas, estado de la cita compartido, contacto de emergencia en la app y un equipo de confianza disponible 24/7.' },
      { t: 'Retribución justa', d: 'Los clientes pagan por adelantado; el importe queda en depósito y se abona tras el tratamiento. Las terapeutas se quedan el 80 % y el 100 % de las propinas, sin riesgo de impago.' },
      { t: 'Todo incluido', d: 'Camilla, manta térmica, ropa limpia, aceites ecológicos, aroma, música y desplazamiento están en el precio mostrado.' },
      { t: 'Cuatro países, una firma', d: 'Los mismos estándares en Berlín, Viena, Zúrich y Marbella, con terapeutas formadas localmente.' }
    ],
    faq: [
      { q: '¿En cuánto tiempo puede llegar alguien?', a: 'En las ciudades principales el plazo mínimo es de unos 90 minutos. Recomendamos reservar con 24 horas de antelación, y tres días para los rituales dúo.' },
      { q: '¿Qué incluye el precio?', a: 'Desplazamiento, camilla profesional, manta térmica, ropa limpia, aceites ecológicos, aroma, música y montaje y desmontaje completos. No hay extras salvo los complementos opcionales.' },
      { q: '¿Cómo se paga?', a: 'Por adelantado al reservar, con tarjeta, Apple Pay, Google Pay, domiciliación SEPA o TWINT en Suiza. El importe queda en depósito y se transfiere a la terapeuta solo cuando el tratamiento se ha completado; si la terapeuta cancela o no acude, se te devuelve íntegramente. La propina es opcional y va íntegra a la terapeuta.' },
      { q: '¿Todas las terapeutas tienen la identidad verificada?', a: 'Sí, sin excepción. Ningún perfil se publica, ni siquiera temporalmente, sin documento de identidad verificado, titulación verificada y seguro de responsabilidad civil vigente. Además comprobamos el certificado de antecedentes penales y el alta de actividad. Los sellos de verificación se muestran en cada perfil.' },
      { q: '¿Puedo cancelar gratis?', a: 'Sí, sin coste hasta 12 horas antes. A partir de ahí cobramos el 50 %, porque tu terapeuta ya ha reservado el desplazamiento.' },
      { q: '¿El servicio es estrictamente terapéutico?', a: 'Sí. Luméa gestiona únicamente tratamientos profesionales de bienestar, masaje y estética. Cualquier solicitud fuera de ese marco conlleva la expulsión inmediata y permanente.' },
      { q: '¿Trabajáis con hoteles y empresas?', a: 'Sí. Atendemos suites de hotel, villas vacacionales, yates y sedes corporativas en las veinte ciudades. Las solicitudes van por el formulario de contacto.' },
      { q: '¿Cómo verificáis a las terapeutas?', a: 'Cuatro fases: revisión documental (DNI/pasaporte, titulación, seguro de responsabilidad civil, antecedentes penales, alta de actividad), cotejo de identidad por vídeo, referencias y una sesión de prueba presencial. Un perfil solo puede reservarse cuando nuestro equipo ha aprobado todos los documentos obligatorios. Se acepta aproximadamente una de cada cuatro candidaturas.' },
      { q: '¿Y si tengo poco espacio?', a: 'Bastan unos 2 × 2,5 metros. Con menos trabajamos con silla de masaje o sobre colchoneta; solo tienes que avisarnos.' }
    ],
    service: { from: 'desde', per: 'por {min} min', book: 'Reservar este tratamiento', duration: 'Duración', pressure: 'Intensidad', included: 'Incluido', ritual: 'La secuencia', benefits: 'Qué aporta', forWhom: 'Indicado para', faqTitle: 'Preguntas sobre este tratamiento', bookIn: 'Reservar {service} en {city}', allCities: 'Disponible en todas las ciudades' },
    city: { title: '{service} en {city}', heroTitle: 'Masaje y estética a domicilio en {city}', intro: 'Terapeutas verificados van a ti: a tu casa, a tu hotel o a la oficina. Ahora mismo hay {count} perfiles activos en {city}.', therapistCount: '{count} terapeutas activos', bookNow: 'Ver disponibilidad en {city}', servicesIn: 'Tratamientos en {city}', localTitle: '{city} en detalle' },
    auth: {
      loginTitle: 'Bienvenido de nuevo',
      loginSub: 'Entra para gestionar tus citas, guardar favoritos y reservar más rápido.',
      registerTitle: 'Crea tu cuenta',
      registerSub: 'Dos minutos y reservas con un solo clic.',
      email: 'Correo electrónico', password: 'Contraseña', passwordHint: 'Mínimo 10 caracteres', name: 'Nombre y apellidos', phone: 'Teléfono',
      login: 'Entrar', register: 'Crear cuenta', logout: 'Salir',
      asClient: 'Quiero reservar', asTherapist: 'Soy terapeuta',
      haveAccount: '¿Ya tienes cuenta?', noAccount: '¿Aún sin cuenta?',
      forgot: '¿Olvidaste la contraseña?', remember: 'Mantener la sesión',
      terms: 'Al registrarte aceptas las Condiciones y la Política de Privacidad.',
      loginIpNote: 'Por seguridad registramos la dirección IP, la hora y el dispositivo de cada inicio de sesión. Puedes revisar todas las sesiones en tu cuenta.'
    },
    apply: {
      title: 'Únete a {brand}',
      sub: 'Aceptamos aproximadamente una de cada cuatro candidaturas, y pagamos un 80 % de participación, desplazamiento garantizado y control total de tu agenda.',
      steps: ['Sobre ti', 'Titulación', 'Tratamientos', 'Cobertura', 'Revisión'],
      fields: {
        firstName: 'Nombre', lastName: 'Apellidos', email: 'Correo electrónico', phone: 'Teléfono / WhatsApp',
        country: 'País', city: 'Ciudad', postal: 'Código postal', radius: 'Radio de cobertura (km)',
        languages: 'Idiomas', years: 'Años de experiencia', qualification: 'Formación / titulación',
        certificates: 'Certificados (los subes después)', insurance: 'Seguro de responsabilidad civil',
        services: 'Tratamientos que ofreces', equipment: 'Tu equipamiento', availability: 'Disponibilidad',
        website: 'Web o Instagram (opcional)', about: 'Breve presentación', password: 'Contraseña de tu cuenta'
      },
      equipmentOptions: ['Camilla portátil', 'Manta térmica', 'Silla de masaje', 'Futón tailandés', 'Set de piedras calientes', 'Aparatología estética', 'Equipo LED', 'Aceites ecológicos propios'],
      availabilityOptions: ['Mañanas entre semana', 'Tardes entre semana', 'Noches entre semana', 'Fines de semana', 'Franja nocturna (22–02 h)', 'Aviso corto (menos de 3 h)'],
      submit: 'Enviar candidatura',
      next: 'Continuar', back: 'Atrás',
      success: 'Candidatura recibida. Revisamos tus datos y te respondemos en 48 horas con el siguiente paso.',
      benefitsTitle: 'Qué obtienes',
      benefits: [
        { t: '80 % de participación y todas las propinas', d: 'Los clientes pagan por adelantado: no asumes riesgo de impago. Abono tras cada tratamiento completado, liquidado cada martes. Sin cuota de alta ni mensual.' },
        { t: 'Tú fijas radio y horarios', d: 'Zona de cobertura, disponibilidad y nivel de precios los defines tú y los cambias cuando quieras.' },
        { t: 'Clientes verificados', d: 'Cada cliente está verificado por correo y teléfono. Las solicitudes fuera del marco terapéutico se eliminan de inmediato.' },
        { t: 'Sistema de seguridad', d: 'Estado de cita compartido, contacto de emergencia, check-in en destino y equipo de confianza 24/7.' },
        { t: 'Demanda previsible', d: 'Clientes habituales, hoteles asociados y contratos corporativos generan citas recurrentes en vez de encargos sueltos.' },
        { t: 'Formación continua', d: 'Acceso a formación avanzada en masaje oncológico, masaje prenatal y estética con aparatología.' }
      ],
      requirementsTitle: 'Requisitos',
      requirements: ['DNI o pasaporte vigente (verificación de identidad con cotejo por vídeo)', 'Formación completa en masaje, fisioterapia o estética; la titulación se verifica', 'Seguro de responsabilidad civil profesional (la póliza se verifica)', 'Certificado de antecedentes penales sin anotaciones, de menos de 3 meses', 'Alta como autónomo en el país de trabajo', 'Equipamiento móvil propio y al menos dos años de experiencia']
    },
    booking: {
      title: 'Solicitar cita', step1: 'Tratamiento', step2: 'Lugar y hora', step3: 'Contacto', step4: 'Confirmación',
      address: 'Dirección', addressHint: 'Calle, número, piso/portero', notes: 'Notas para tu terapeuta',
      notesHint: 'Molestias, presión preferida, alergias, mascotas, aparcamiento …',
      date: 'Fecha', time: 'Hora', persons: 'Personas', place: '¿Dónde?',
      placeOptions: ['Casa', 'Hotel / suite', 'Oficina', 'Casa de vacaciones / villa', 'Yate'],
      summary: 'Resumen', total: 'Total', payLater: 'Pago anticipado seguro: queda en depósito y se transfiere a la terapeuta solo tras el tratamiento',
      submit: 'Enviar solicitud sin compromiso', success: 'Solicitud enviada. Tu terapeuta suele confirmar en unos {minutes} minutos.'
    },
    match: { idOk: 'Identidad verificada', certOk: 'Titulación verificada', insOk: 'Asegurada', bgOk: 'Antecedentes comprobados', title: 'Tus mejores coincidencias', distance: 'a {km} km', rating: '{rating} ({count})', speaks: 'Habla', verified: 'Verificada', topRated: 'Mejor valorada', since: 'Con nosotros desde {year}', bookWith: 'Reservar con {name}', noResults: 'Todavía no tenemos un perfil en rango para esta combinación. Amplía el radio o déjanos tu solicitud y te contactamos.', radius: 'Radio', profile: 'Ver perfil', responds: 'Responde en ~{min} min' },
    footer: { services: 'Tratamientos', company: 'Empresa', cities: 'Ciudades', legal: 'Legal', about: 'Sobre nosotros', careers: 'Empleo', press: 'Prensa', contact: 'Contacto', imprint: 'Aviso legal', privacy: 'Privacidad', terms: 'Condiciones', cookies: 'Cookies', therapists: 'Hazte terapeuta', gift: 'Tarjetas regalo', corporate: 'Para empresas', hotels: 'Para hoteles', rights: 'Todos los derechos reservados.', claim: 'Únicamente tratamientos profesionales de bienestar y estética.' },
    common: { readMore: 'Saber más', book: 'Reservar', from: 'desde', minutes: 'min', all: 'Todos', close: 'Cerrar', chooseCity: 'Elige ciudad', chooseService: 'Elige tratamiento', required: 'Obligatorio', optional: 'opcional', yes: 'Sí', no: 'No', or: 'o', sending: 'Enviando …', backHome: 'Volver al inicio' }
  }
};

/** Simple {placeholder} interpolation. */
export const fmt = (str, vars = {}) => String(str).replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
