<?php
/**
 * ChatHelp — Länder-Fokus für die Startseite: welche Agenten ein Land zuerst braucht
 * ===================================================================================
 *   ch_country_focus('TR') → ['cc'=>'TR','profile'=>'TR','cats'=>[…],'boost'=>[…],'hero'=>['fh.wa',…],'why'=>['de'=>…,'en'=>…,…],'tuerkiye'=>true]
 *     cats     Kategorie-IDs aus CH_CATEGORIES in Prioritätsreihenfolge (die Startseite zeigt sie zuerst, der Rest folgt unverändert)
 *     boost    3–6 Plan-IDs aus CH_PLANS (kind agent oder privat-*), die das Abzeichen „Beliebt in <Land>“ bekommen
 *     hero     2–3 i18n-Schlüssel (fh.*) der Startseite – Nutzenversprechen im Hero
 *     why      ein Satz Begründung je Seitensprache (fehlt eine Sprache, blendet die Seite den Satz aus)
 *     tuerkiye true → Abschnitt „Türkiye · Saç & Diş“ rückt nach oben und wird hervorgehoben
 *              (die Türkiye-Agenten selbst bleiben NUR in diesem Abschnitt – nie im allgemeinen Finder, nie in boost)
 * Ohne Land (leer/ungültig) → []; unbekanntes Land → Profil DEFAULT (keine Umsortierung der Kategorien).
 * Reine Daten, keine Datenbank – locale-api.php ruft das bei jedem neuen Besucher auf.
 *
 * Begründung der Profile (Kurzfassung):
 *   DE/AT/CH  Fachkräftemangel, hohe Lohnkosten, volle Arztpraxen; Verwaltung, Hausverwaltung, Versicherung, Handwerk – AT/CH zusätzlich Tourismus bzw. Finanzplatz/Mehrsprachigkeit.
 *   FR/BE     Termin-Kultur (Arzt/Cabinet), Gastronomie, Behördengänge; BE zweisprachig FR/NL.
 *   ES/IT     Tourismus, Hotellerie, Gastronomie, Beauty; IT zusätzlich Mode/Einzelhandel und Online-Handel.
 *   MX/AR     WhatsApp ist der Standardkanal, viele Kleinbetriebe, Lieferdienste, private Praxen und Beauty.
 *   TR        WhatsApp-lastig, viele kleine Betriebe, Gesundheitstourismus (Haar/Zahn → Türkiye-Abschnitt), Restaurants/Lieferung, Taxi, Beauty.
 *   AZ        WhatsApp/Telefon, Gesundheit (private Kliniken), Handel, Dienste für Privatleute, Behördengänge (ASAN-Kultur); Behandlungen in der Türkei verbreitet.
 *   GB/US     Online-Handel und Kundenservice, Immobilien, private Gesundheitsversorgung (Praxis/Zahn), Vertrieb.
 *   NL/Nordics Hoch digitalisiert, Online-Handel, kommunale Dienste, Gesundheitstermine, Mietwohnungen; NO/JP: Personalkosten bzw. Tourismus.
 *   RU        Online-Handel, Lieferdienste, Beauty-Salons, Taxi.
 *   JP        Alternde Gesellschaft/Arbeitskräftemangel, Inbound-Tourismus (Hotel/Gastro), Kliniken.
 *   AE/SA     Tourismus und Hotellerie, Immobilien, Ästhetik/Beauty, Gesundheit, mehrsprachige Kundschaft, WhatsApp; SA: digitale Verwaltung (Vision 2030).
 */
require_once __DIR__.'/plans.php';   // CH_CATEGORIES, CH_PLANS (Validierung der IDs)

/** Profile: [cats, boost, hero, why[lang]] – IDs müssen in CH_CATEGORIES / CH_PLANS existieren (ch_country_focus filtert Unbekanntes heraus). */
const CH_COUNTRY_PROFILES = [
  'DE' => [['gesundheit','immobilien','dienstleistung','oeffentlich','finanzen','recht'],
           ['praxis-agent','agent-hausverwaltung','agent-termine','agent-behoerde','agent-versicherung','agent-phone'],['fh.office','fh.appt','fh.gov'],[
    'de'=>'Fachkräftemangel und volle Wartezimmer: Praxen, Hausverwaltungen, Handwerk, Versicherungen und Bürgerservice verlieren hier die meisten Anrufe.',
    'en'=>'Staff shortages and full waiting rooms: practices, property managers, trades, insurers and citizen services miss the most calls here.',
    'fr'=>'Pénurie de personnel et salles d’attente pleines : cabinets, gestionnaires d’immeubles, artisans, assureurs et services publics perdent le plus d’appels.',
    'tr'=>'Personel açığı ve dolu bekleme salonları: muayenehaneler, site yönetimleri, ustalar, sigortacılar ve vatandaş hizmetleri en çok aramayı kaçırıyor.',
    'az'=>'Kadr çatışmazlığı və dolu gözləmə zalları: klinikalar, mənzil idarələri, ustalar, sığortaçılar və vətəndaş xidmətləri ən çox zəngi buraxır.',
    'es'=>'Falta de personal y salas de espera llenas: consultas, administradores de fincas, oficios, aseguradoras y atención ciudadana pierden más llamadas.',
    'it'=>'Carenza di personale e sale d’attesa piene: studi medici, amministratori di condominio, artigiani, assicurazioni e uffici pubblici perdono più chiamate.']],
  'AT' => [['gesundheit','gastro','dienstleistung','immobilien','oeffentlich','finanzen'],
           ['praxis-agent','agent-hotel','agent-termine','agent-hausverwaltung','gastro-agent'],['fh.office','fh.tourism','fh.appt'],[
    'de'=>'Tourismus und Kassenpraxen: Hotels, Gastronomie und Ordinationen verlieren in der Saison die meisten Anfragen.',
    'en'=>'Tourism and busy practices: hotels, restaurants and doctors’ offices lose the most enquiries in peak season.',
    'fr'=>'Tourisme et cabinets débordés : hôtels, restaurants et médecins perdent le plus de demandes en haute saison.',
    'tr'=>'Turizm ve yoğun muayenehaneler: oteller, restoranlar ve doktorlar sezonda en çok talebi kaçırıyor.',
    'az'=>'Turizm və sıx klinikalar: otellər, restoranlar və həkimlər mövsümdə ən çox müraciəti itirir.',
    'es'=>'Turismo y consultas saturadas: hoteles, restaurantes y médicos pierden más solicitudes en temporada alta.',
    'it'=>'Turismo e studi medici affollati: hotel, ristoranti e medici perdono più richieste in alta stagione.']],
  'CH' => [['gesundheit','finanzen','gastro','immobilien','recht','dienstleistung'],
           ['praxis-agent','agent-versicherung','agent-bank','agent-hotel','immo-agent','agent-steuer'],['fh.multi','fh.office','fh.health'],[
    'de'=>'Hohe Lohnkosten und vier Landessprachen: Praxen, Versicherungen, Treuhand und Hotels profitieren am stärksten von einem mehrsprachigen Agenten.',
    'en'=>'High wages and four national languages: practices, insurers, fiduciaries and hotels gain most from a multilingual agent.',
    'fr'=>'Salaires élevés et quatre langues nationales : cabinets, assurances, fiduciaires et hôtels profitent le plus d’un agent multilingue.',
    'tr'=>'Yüksek ücretler ve dört resmî dil: muayenehaneler, sigortalar, mali müşavirler ve oteller çok dilli bir ajandan en çok fayda sağlar.',
    'az'=>'Yüksək əmək haqqı və dörd dövlət dili: klinikalar, sığortalar, maliyyə məsləhətçiləri və otellər çoxdilli agentdən ən çox qazanır.',
    'es'=>'Salarios altos y cuatro idiomas oficiales: consultas, aseguradoras, fiduciarias y hoteles son los que más ganan con un agente multilingüe.',
    'it'=>'Salari alti e quattro lingue nazionali: studi medici, assicurazioni, fiduciarie e hotel guadagnano di più da un agente multilingue.']],
  'FR' => [['gesundheit','dienstleistung','gastro','bestellung','oeffentlich','immobilien','beauty'],
           ['praxis-agent','agent-termine','gastro-agent','agent-bestellung','agent-behoerde','immo-agent'],['fh.appt','fh.health','fh.gov'],[
    'de'=>'Arzttermine, Restaurants und Behördengänge: Praxen und Geschäfte um die Ecke ertrinken in Anrufen.',
    'en'=>'Medical appointments, restaurants and paperwork: practices and local shops are swamped with calls.',
    'fr'=>'Rendez-vous médicaux, restaurants et démarches : cabinets et commerces de proximité croulent sous les appels.',
    'tr'=>'Doktor randevuları, restoranlar ve resmî işlemler: muayenehaneler ve mahalle esnafı aramalara boğuluyor.',
    'az'=>'Həkim növbələri, restoranlar və rəsmi işlər: klinikalar və yerli dükanlar zənglərdən boğulur.',
    'es'=>'Citas médicas, restaurantes y trámites: consultas y comercios de barrio están desbordados de llamadas.',
    'it'=>'Visite mediche, ristoranti e pratiche: studi e negozi di quartiere sono sommersi dalle chiamate.']],
  'BE' => [['gesundheit','dienstleistung','gastro','oeffentlich','immobilien','finanzen'],
           ['praxis-agent','agent-termine','gastro-agent','agent-behoerde','immo-agent'],['fh.multi','fh.appt','fh.gov'],[
    'de'=>'Zweisprachig und termingetrieben: Praxen, Gastronomie und Gemeinden brauchen Antworten auf Französisch und Niederländisch.',
    'en'=>'Bilingual and appointment-driven: practices, restaurants and municipalities need answers in French and Dutch.',
    'fr'=>'Bilingue et rythmé par les rendez-vous : cabinets, restaurants et communes doivent répondre en français et en néerlandais.',
    'nl'=>'Tweetalig en gedreven door afspraken: praktijken, horeca en gemeenten moeten antwoorden in het Frans én het Nederlands.',
    'tr'=>'İki dilli ve randevu odaklı: muayenehaneler, restoranlar ve belediyeler Fransızca ve Felemenkçe yanıt vermeli.',
    'az'=>'İkidilli və növbəyə əsaslanan: klinikalar, restoranlar və bələdiyyələr fransız və niderland dilində cavab verməlidir.',
    'es'=>'Bilingüe y basado en citas: consultas, restaurantes y ayuntamientos necesitan responder en francés y neerlandés.',
    'it'=>'Bilingue e fatto di appuntamenti: studi, ristoranti e comuni devono rispondere in francese e olandese.']],
  'ES' => [['gastro','bestellung','beauty','dienstleistung','immobilien','mobilitaet','gesundheit'],
           ['agent-hotel','gastro-agent','agent-bestellung','agent-beauty','immo-agent','agent-taxi'],['fh.tourism','fh.wa','fh.order'],[
    'de'=>'Tourismus, Gastronomie und Beauty: Reservierungen und Bestellungen per WhatsApp zu jeder Uhrzeit – in der Sprache des Gastes.',
    'en'=>'Tourism, hospitality and beauty: bookings and orders on WhatsApp at any hour – in the guest’s language.',
    'fr'=>'Tourisme, restauration et beauté : réservations et commandes sur WhatsApp à toute heure, dans la langue du client.',
    'tr'=>'Turizm, yeme-içme ve güzellik: rezervasyon ve siparişler her saatte WhatsApp’tan – misafirin dilinde.',
    'az'=>'Turizm, iaşə və gözəllik: rezervasiya və sifarişlər istənilən saatda WhatsApp-da – qonağın dilində.',
    'es'=>'Turismo, hostelería y belleza: reservas y pedidos por WhatsApp a cualquier hora, en el idioma del cliente.',
    'it'=>'Turismo, ristorazione e bellezza: prenotazioni e ordini su WhatsApp a ogni ora, nella lingua del cliente.']],
  'MX' => [['bestellung','dienstleistung','beauty','gesundheit','handel','immobilien','mobilitaet'],
           ['agent-bestellung','agent-termine','agent-beauty','praxis-agent','ec-support','agent-taxi'],['fh.wa','fh.small','fh.order'],[
    'de'=>'WhatsApp ist der Standardkanal: Kleinbetriebe, Lieferdienste, Salons und private Praxen gewinnen, wenn sie sofort antworten.',
    'en'=>'WhatsApp is the default channel: small businesses, delivery, salons and private practices win by answering instantly.',
    'fr'=>'WhatsApp est le canal par défaut : petites entreprises, livraison, salons et cabinets privés gagnent en répondant tout de suite.',
    'tr'=>'WhatsApp varsayılan kanal: küçük işletmeler, paket servisler, salonlar ve özel muayenehaneler anında yanıt vererek kazanır.',
    'az'=>'WhatsApp əsas kanaldır: kiçik bizneslər, çatdırılma, salonlar və özəl klinikalar dərhal cavab verməklə qazanır.',
    'es'=>'WhatsApp es el canal de siempre: negocios pequeños, entregas a domicilio, salones y consultorios privados ganan respondiendo al instante.',
    'it'=>'WhatsApp è il canale abituale: piccole attività, consegne, saloni e studi privati vincono rispondendo subito.']],
  'AR' => [['bestellung','gesundheit','dienstleistung','handel','beauty','gastro'],
           ['agent-bestellung','praxis-agent','agent-termine','ec-support','agent-beauty'],['fh.wa','fh.small','fh.health'],[
    'de'=>'WhatsApp und knappe Budgets: Lieferdienste, private Kliniken, Salons und Händler brauchen einen günstigen Agenten statt Zusatzpersonal.',
    'en'=>'WhatsApp and tight budgets: delivery, private clinics, salons and retailers need an affordable agent instead of extra staff.',
    'fr'=>'WhatsApp et budgets serrés : livraison, cliniques privées, salons et commerçants ont besoin d’un agent abordable plutôt que de personnel en plus.',
    'tr'=>'WhatsApp ve kısıtlı bütçeler: paket servisler, özel klinikler, salonlar ve satıcılar ek personel yerine uygun fiyatlı bir ajana ihtiyaç duyar.',
    'az'=>'WhatsApp və məhdud büdcə: çatdırılma, özəl klinikalar, salonlar və satıcılar əlavə işçi yerinə sərfəli agentə ehtiyac duyur.',
    'es'=>'WhatsApp y presupuestos ajustados: delivery, clínicas privadas, peluquerías y comercios necesitan un agente accesible en lugar de más personal.',
    'it'=>'WhatsApp e budget ridotti: consegne, cliniche private, saloni e negozi hanno bisogno di un agente economico invece di altro personale.']],
  'TR' => [['bestellung','mobilitaet','beauty','gastro','dienstleistung','gesundheit','handel'],
           ['agent-bestellung','agent-taxi','agent-beauty','agent-hotel','agent-termine','agent-aesthetic'],['fh.wa','fh.medtour','fh.order'],[
    'de'=>'WhatsApp ist Alltag: Restaurants, Taxi, Salons und Hotels – und Kliniken mit Patienten aus aller Welt – antworten hier rund um die Uhr.',
    'en'=>'WhatsApp is everyday life: restaurants, taxis, salons and hotels – and clinics with patients from all over the world – answer around the clock.',
    'fr'=>'WhatsApp fait partie du quotidien : restaurants, taxis, salons, hôtels – et cliniques accueillant des patients du monde entier – répondent jour et nuit.',
    'tr'=>'WhatsApp hayatın içinde: restoranlar, taksiler, salonlar, oteller ve dünyanın her yerinden hasta ağırlayan klinikler 7/24 yanıt veriyor.',
    'az'=>'WhatsApp gündəlik həyatdır: restoranlar, taksilər, salonlar, otellər və bütün dünyadan xəstə qəbul edən klinikalar gecə-gündüz cavab verir.',
    'es'=>'WhatsApp es el día a día: restaurantes, taxis, salones, hoteles y clínicas con pacientes de todo el mundo responden a todas horas.',
    'it'=>'WhatsApp è quotidianità: ristoranti, taxi, saloni, hotel e cliniche con pazienti da tutto il mondo rispondono giorno e notte.']],
  'AZ' => [['gesundheit','handel','privat','dienstleistung','bestellung','oeffentlich','mobilitaet'],
           ['praxis-agent','ec-support','privat-behoerde','agent-termine','agent-bestellung','agent-taxi'],['fh.wa','fh.private','fh.trade'],[
    'de'=>'WhatsApp und Telefon zuerst: private Kliniken, Händler und Dienstleister – und Hilfe bei Behördengängen und Papieren für Privatleute.',
    'en'=>'WhatsApp and phone first: private clinics, retailers and service providers – plus help with paperwork and public offices for individuals.',
    'fr'=>'WhatsApp et téléphone d’abord : cliniques privées, commerçants et prestataires – et une aide aux démarches pour les particuliers.',
    'tr'=>'Önce WhatsApp ve telefon: özel klinikler, satıcılar ve hizmet verenler – bireyler için de resmî işlem ve evrak desteği.',
    'az'=>'Əvvəlcə WhatsApp və telefon: özəl klinikalar, satıcılar və xidmət göstərənlər – fərdlər üçün isə sənəd və dövlət işlərində kömək.',
    'es'=>'Primero WhatsApp y teléfono: clínicas privadas, comercios y servicios, y ayuda con trámites y papeles para particulares.',
    'it'=>'Prima WhatsApp e telefono: cliniche private, negozi e servizi, più aiuto con pratiche e uffici pubblici per i privati.',
    'ru'=>'Сначала WhatsApp и телефон: частные клиники, магазины и сервисы – а для частных лиц помощь с документами и госуслугами.']],
  'IT' => [['gastro','bestellung','handel','beauty','dienstleistung','gesundheit','immobilien'],
           ['gastro-agent','agent-hotel','agent-bestellung','ec-support','agent-beauty','agent-termine'],['fh.tourism','fh.ecom','fh.order'],[
    'de'=>'Gastronomie, Hotellerie, Mode und Einzelhandel: Reservierungen, Bestellungen und Kundenfragen kommen zu jeder Uhrzeit.',
    'en'=>'Restaurants, hotels, fashion and retail: bookings, orders and customer questions arrive at all hours.',
    'fr'=>'Restauration, hôtellerie, mode et commerce : réservations, commandes et questions clients arrivent à toute heure.',
    'tr'=>'Yeme-içme, otelcilik, moda ve perakende: rezervasyon, sipariş ve müşteri soruları her saatte geliyor.',
    'az'=>'İaşə, otelçilik, moda və pərakəndə: rezervasiya, sifariş və müştəri sualları hər saat gəlir.',
    'es'=>'Restauración, hoteles, moda y comercio: reservas, pedidos y preguntas de clientes llegan a cualquier hora.',
    'it'=>'Ristorazione, hotel, moda e commercio: prenotazioni, ordini e domande dei clienti arrivano a ogni ora.']],
  'GB' => [['handel','immobilien','gesundheit','dienstleistung','finanzen','bestellung'],
           ['ec-support','agent-support','immo-agent','praxis-agent','agent-termine','agent-phone'],['fh.ecom','fh.estate','fh.health'],[
    'de'=>'Online-Handel, Immobilien und private Gesundheitsversorgung: Kunden erwarten Antworten in Minuten, nicht am nächsten Werktag.',
    'en'=>'E-commerce, property and private healthcare: customers expect answers in minutes, not the next working day.',
    'fr'=>'E-commerce, immobilier et santé privée : les clients attendent une réponse en quelques minutes, pas le lendemain.',
    'tr'=>'E-ticaret, emlak ve özel sağlık: müşteriler yanıtı ertesi iş günü değil, dakikalar içinde bekliyor.',
    'az'=>'E-ticarət, daşınmaz əmlak və özəl səhiyyə: müştərilər cavabı növbəti iş günü yox, dəqiqələr ərzində gözləyir.',
    'es'=>'Comercio electrónico, inmobiliaria y sanidad privada: los clientes esperan respuesta en minutos, no al día siguiente.',
    'it'=>'E-commerce, immobiliare e sanità privata: i clienti si aspettano risposte in pochi minuti, non il giorno lavorativo dopo.']],
  'US' => [['handel','immobilien','gesundheit','dienstleistung','finanzen','marketing'],
           ['ec-support','immo-agent','praxis-agent','agent-phone','agent-sales','agent-versicherung'],['fh.ecom','fh.estate','fh.office'],[
    'de'=>'Online-Handel, Immobilien, Praxen und Versicherungen: Wer zuerst antwortet, gewinnt den Kunden – auch am Wochenende.',
    'en'=>'E-commerce, real estate, clinics and insurance: whoever answers first wins the customer – weekends included.',
    'fr'=>'E-commerce, immobilier, cliniques et assurances : celui qui répond en premier gagne le client, même le week-end.',
    'tr'=>'E-ticaret, emlak, klinikler ve sigorta: ilk yanıt veren müşteriyi kazanır – hafta sonu da.',
    'az'=>'E-ticarət, daşınmaz əmlak, klinikalar və sığorta: ilk cavab verən müştərini qazanır – həftə sonu da.',
    'es'=>'Comercio electrónico, bienes raíces, clínicas y seguros: quien responde primero gana al cliente, también el fin de semana.',
    'it'=>'E-commerce, immobiliare, cliniche e assicurazioni: chi risponde per primo vince il cliente, anche nel weekend.']],
  'NL' => [['handel','dienstleistung','gastro','immobilien','gesundheit','bestellung'],
           ['ec-support','agent-termine','gastro-agent','agent-hausverwaltung','agent-bestellung'],['fh.ecom','fh.appt','fh.multi'],[
    'de'=>'Online-Handel, Termine und Horeca: Niederländer buchen und fragen digital – und erwarten Antworten auf Niederländisch und Englisch.',
    'en'=>'E-commerce, appointments and hospitality: the Dutch book and ask online – and expect answers in Dutch and English.',
    'nl'=>'Webwinkels, afspraken en horeca: Nederlanders boeken en vragen alles online – en verwachten antwoord in het Nederlands en Engels.',
    'fr'=>'E-commerce, rendez-vous et restauration : les Néerlandais réservent en ligne et attendent une réponse en néerlandais et en anglais.',
    'tr'=>'E-ticaret, randevular ve yeme-içme: Hollandalılar her şeyi çevrim içi yapıyor ve Felemenkçe ile İngilizce yanıt bekliyor.',
    'az'=>'E-ticarət, növbələr və iaşə: niderlandlılar hər şeyi onlayn edir və niderland və ingilis dilində cavab gözləyir.',
    'es'=>'Comercio electrónico, citas y hostelería: los neerlandeses reservan en línea y esperan respuesta en neerlandés e inglés.',
    'it'=>'E-commerce, appuntamenti e ristorazione: gli olandesi prenotano online e si aspettano risposte in olandese e inglese.']],
  'SE' => [['handel','oeffentlich','gesundheit','dienstleistung','immobilien'],
           ['ec-support','agent-behoerde','praxis-agent','agent-termine','agent-hausverwaltung'],['fh.ecom','fh.gov','fh.appt'],[
    'de'=>'Digital zuerst: Online-Handel, Kommunen, Gesundheitstermine und Mietwohnungen – Schweden erwarten Selbstbedienung rund um die Uhr.',
    'en'=>'Digital first: e-commerce, municipalities, health appointments and rentals – Swedes expect self-service around the clock.',
    'sv'=>'Digitalt först: e-handel, kommuner, vårdbesök och hyresrätter – svenskar förväntar sig självservice dygnet runt.',
    'fr'=>'Le numérique d’abord : e-commerce, communes, rendez-vous de santé et locations – les Suédois attendent un self-service 24 h/24.',
    'tr'=>'Önce dijital: e-ticaret, belediyeler, sağlık randevuları ve kiralık konut – İsveçliler 7/24 self servis bekliyor.',
    'az'=>'Əvvəlcə rəqəmsal: e-ticarət, bələdiyyələr, tibbi növbələr və kirayə mənzillər – isveçlilər gecə-gündüz özünəxidmət gözləyir.',
    'es'=>'Primero lo digital: comercio electrónico, municipios, citas médicas y alquileres – los suecos esperan autoservicio a todas horas.',
    'it'=>'Prima il digitale: e-commerce, comuni, visite mediche e affitti – gli svedesi si aspettano self-service 24 ore su 24.']],
  'NO' => [['dienstleistung','gastro','handel','gesundheit','immobilien','oeffentlich'],
           ['agent-termine','agent-hotel','ec-support','praxis-agent','agent-phone'],['fh.appt','fh.tourism','fh.office'],[
    'de'=>'Hohe Personalkosten und viel Tourismus: Termine, Hotels und Kundenservice laufen besser mit einem Agenten, der nie schließt.',
    'en'=>'High staff costs and plenty of tourism: appointments, hotels and customer service run better with an agent that never closes.',
    'no'=>'Høye lønnskostnader og mye turisme: timebestilling, hoteller og kundeservice går bedre med en agent som aldri stenger.',
    'fr'=>'Coûts salariaux élevés et beaucoup de tourisme : rendez-vous, hôtels et service client fonctionnent mieux avec un agent toujours ouvert.',
    'tr'=>'Yüksek personel maliyeti ve yoğun turizm: randevu, otel ve müşteri hizmetleri hiç kapanmayan bir ajanla daha iyi işler.',
    'az'=>'Yüksək işçi xərcləri və güclü turizm: növbələr, otellər və müştəri xidməti heç bağlanmayan agentlə daha yaxşı işləyir.',
    'es'=>'Costes de personal altos y mucho turismo: citas, hoteles y atención al cliente funcionan mejor con un agente que nunca cierra.',
    'it'=>'Costi del personale alti e molto turismo: appuntamenti, hotel e assistenza clienti vanno meglio con un agente sempre aperto.']],
  'FI' => [['oeffentlich','gesundheit','handel','dienstleistung','immobilien'],
           ['agent-behoerde','praxis-agent','ec-support','agent-termine','agent-hausverwaltung'],['fh.gov','fh.health','fh.ecom'],[
    'de'=>'Kommunen, Gesundheit und Online-Handel: Finnen erledigen fast alles digital – Wartezeiten am Telefon fallen sofort auf.',
    'en'=>'Municipalities, healthcare and e-commerce: Finns do almost everything online – phone queues stand out immediately.',
    'fi'=>'Kunnat, terveydenhuolto ja verkkokauppa: suomalaiset hoitavat lähes kaiken verkossa – puhelinjonot huomataan heti.',
    'fr'=>'Communes, santé et e-commerce : les Finlandais font presque tout en ligne – l’attente au téléphone se remarque tout de suite.',
    'tr'=>'Belediyeler, sağlık ve e-ticaret: Finler neredeyse her şeyi çevrim içi hallediyor – telefonda bekleme hemen göze batıyor.',
    'az'=>'Bələdiyyələr, səhiyyə və e-ticarət: finlər demək olar hər şeyi onlayn həll edir – telefonda gözləmə dərhal nəzərə çarpır.',
    'es'=>'Municipios, sanidad y comercio electrónico: los finlandeses lo hacen casi todo en línea y las esperas al teléfono se notan enseguida.',
    'it'=>'Comuni, sanità ed e-commerce: i finlandesi fanno quasi tutto online e le attese al telefono si notano subito.']],
  'RU' => [['handel','bestellung','beauty','dienstleistung','mobilitaet','gesundheit'],
           ['ec-support','agent-bestellung','agent-beauty','agent-termine','agent-taxi'],['fh.ecom','fh.order','fh.appt'],[
    'de'=>'Online-Handel, Lieferdienste, Beauty-Salons und Taxi: Kunden schreiben im Messenger und erwarten sofort eine Antwort.',
    'en'=>'E-commerce, delivery, beauty salons and taxis: customers write in messengers and expect an instant reply.',
    'ru'=>'Интернет-магазины, доставка, салоны красоты и такси: клиенты пишут в мессенджеры и ждут ответа сразу.',
    'fr'=>'E-commerce, livraison, salons de beauté et taxis : les clients écrivent sur messagerie et attendent une réponse immédiate.',
    'tr'=>'E-ticaret, paket servis, güzellik salonları ve taksi: müşteriler mesajlaşma uygulamasından yazıyor ve anında yanıt bekliyor.',
    'az'=>'E-ticarət, çatdırılma, gözəllik salonları və taksi: müştərilər messencerdə yazır və dərhal cavab gözləyir.',
    'es'=>'Comercio electrónico, reparto, salones de belleza y taxis: los clientes escriben por mensajería y esperan respuesta inmediata.',
    'it'=>'E-commerce, consegne, saloni di bellezza e taxi: i clienti scrivono in chat e si aspettano una risposta immediata.']],
  'JP' => [['gastro','gesundheit','dienstleistung','immobilien','handel'],
           ['agent-hotel','gastro-agent','praxis-agent','agent-termine','agent-phone'],['fh.tourism','fh.office','fh.multi'],[
    'de'=>'Arbeitskräftemangel und Rekord-Tourismus: Hotels, Restaurants und Kliniken brauchen Gastfreundschaft in vielen Sprachen – rund um die Uhr.',
    'en'=>'Labour shortages and record tourism: hotels, restaurants and clinics need hospitality in many languages – around the clock.',
    'ja'=>'人手不足と過去最高の訪日客：ホテル・飲食店・クリニックには、多言語で24時間のおもてなしが必要です。',
    'fr'=>'Pénurie de main-d’œuvre et tourisme record : hôtels, restaurants et cliniques ont besoin d’un accueil multilingue jour et nuit.',
    'tr'=>'İş gücü açığı ve rekor turizm: oteller, restoranlar ve klinikler birçok dilde, gece gündüz misafirperverlik istiyor.',
    'az'=>'İşçi qıtlığı və rekord turizm: otellər, restoranlar və klinikalar bir çox dildə gecə-gündüz qonaqpərvərlik tələb edir.',
    'es'=>'Escasez de personal y turismo récord: hoteles, restaurantes y clínicas necesitan hospitalidad en muchos idiomas, a todas horas.',
    'it'=>'Carenza di personale e turismo record: hotel, ristoranti e cliniche hanno bisogno di ospitalità in molte lingue, giorno e notte.']],
  'AE' => [['immobilien','gastro','beauty','gesundheit','handel','mobilitaet'],
           ['immo-agent','agent-hotel','agent-aesthetic','agent-beauty','ec-support','agent-taxi'],['fh.multi','fh.estate','fh.wa'],[
    'de'=>'Immobilien, Hotellerie und Ästhetik mit Kundschaft aus aller Welt: Anfragen kommen per WhatsApp, auf Arabisch, Englisch, Russisch und mehr.',
    'en'=>'Real estate, hospitality and aesthetics with clients from everywhere: enquiries arrive on WhatsApp in Arabic, English, Russian and more.',
    'fr'=>'Immobilier, hôtellerie et esthétique avec une clientèle internationale : les demandes arrivent sur WhatsApp en arabe, anglais, russe…',
    'tr'=>'Dünyanın her yerinden müşterili emlak, otelcilik ve estetik: talepler WhatsApp’tan Arapça, İngilizce, Rusça ve daha fazla dilde geliyor.',
    'az'=>'Bütün dünyadan müştərisi olan daşınmaz əmlak, otelçilik və estetika: müraciətlər WhatsApp-da ərəb, ingilis, rus və digər dillərdə gəlir.',
    'es'=>'Inmobiliaria, hostelería y estética con clientes de todo el mundo: las consultas llegan por WhatsApp en árabe, inglés, ruso y más.',
    'it'=>'Immobiliare, ospitalità ed estetica con clienti da tutto il mondo: le richieste arrivano su WhatsApp in arabo, inglese, russo e altro.',
    'ru'=>'Недвижимость, гостиницы и эстетика с клиентами со всего мира: запросы приходят в WhatsApp на арабском, английском, русском и других языках.']],
  'SA' => [['gesundheit','gastro','handel','immobilien','oeffentlich','mobilitaet'],
           ['praxis-agent','agent-hotel','ec-support','immo-agent','agent-taxi','agent-behoerde'],['fh.wa','fh.health','fh.multi'],[
    'de'=>'Vision 2030: Tourismus, Gesundheit, Handel und digitale Verwaltung wachsen schnell – Kunden schreiben per WhatsApp, auf Arabisch und Englisch.',
    'en'=>'Vision 2030: tourism, healthcare, retail and digital government are growing fast – customers write on WhatsApp in Arabic and English.',
    'fr'=>'Vision 2030 : tourisme, santé, commerce et administration numérique croissent vite – les clients écrivent sur WhatsApp en arabe et en anglais.',
    'tr'=>'Vizyon 2030: turizm, sağlık, ticaret ve dijital kamu hızla büyüyor – müşteriler WhatsApp’tan Arapça ve İngilizce yazıyor.',
    'az'=>'Vizyon 2030: turizm, səhiyyə, ticarət və rəqəmsal idarəetmə sürətlə böyüyür – müştərilər WhatsApp-da ərəb və ingilis dilində yazır.',
    'es'=>'Visión 2030: turismo, sanidad, comercio y administración digital crecen rápido; los clientes escriben por WhatsApp en árabe e inglés.',
    'it'=>'Vision 2030: turismo, sanità, commercio e amministrazione digitale crescono in fretta – i clienti scrivono su WhatsApp in arabo e inglese.']],
  'DEFAULT' => [[],
           ['agent-phone','agent-termine','ec-support','agent-bestellung'],['fh.wa','fh.small','fh.multi'],[
    'de'=>'Telefon, WhatsApp und Chat in einem Agenten – die Agenten, die kleine Betriebe weltweit am häufigsten zuerst einsetzen.',
    'en'=>'Phone, WhatsApp and chat in one agent – the agents small businesses worldwide start with most often.',
    'fr'=>'Téléphone, WhatsApp et chat dans un seul agent : les agents par lesquels les petites entreprises commencent le plus souvent.',
    'tr'=>'Telefon, WhatsApp ve sohbet tek ajanda – dünyada küçük işletmelerin en sık ilk kullandığı ajanlar.',
    'az'=>'Telefon, WhatsApp və çat bir agentdə – dünyada kiçik bizneslərin ən çox ilk istifadə etdiyi agentlər.',
    'es'=>'Teléfono, WhatsApp y chat en un solo agente: los agentes con los que más empiezan los pequeños negocios en todo el mundo.',
    'it'=>'Telefono, WhatsApp e chat in un solo agente: gli agenti con cui le piccole attività iniziano più spesso nel mondo.',
    'ru'=>'Телефон, WhatsApp и чат в одном агенте – с них чаще всего начинают малые предприятия по всему миру.',
    'nl'=>'Telefoon, WhatsApp en chat in één agent – de agents waarmee kleine bedrijven wereldwijd het vaakst beginnen.',
    'sv'=>'Telefon, WhatsApp och chatt i en agent – de agenter som småföretag världen över oftast börjar med.',
    'no'=>'Telefon, WhatsApp og chat i én agent – agentene små bedrifter over hele verden oftest starter med.',
    'fi'=>'Puhelin, WhatsApp ja chat yhdessä agentissa – agentit, joilla pienyritykset aloittavat useimmin.',
    'ja'=>'電話・WhatsApp・チャットを1つのエージェントで ― 世界中の小規模事業者が最初に導入するエージェントです。']],
];
/** Länder ohne eigenes Profil → ähnlichstes Profil (Sprache, Wirtschaftsstruktur). */
const CH_COUNTRY_ALIAS = ['LI'=>'CH','LU'=>'BE','MC'=>'FR','AD'=>'ES','SM'=>'IT','VA'=>'IT','IE'=>'GB','CA'=>'US','AU'=>'GB','NZ'=>'GB','DK'=>'SE','IS'=>'NO',
  'PT'=>'ES','GR'=>'ES','CY'=>'TR','HR'=>'IT','MT'=>'IT','CO'=>'MX','PE'=>'MX','CL'=>'AR','UY'=>'AR','EC'=>'MX','VE'=>'MX','QA'=>'AE','KW'=>'AE','BH'=>'AE','OM'=>'AE',
  'BY'=>'RU','KZ'=>'RU','UZ'=>'AZ','TM'=>'AZ','KG'=>'RU','GE'=>'AZ','EE'=>'FI'];

/** Fokus für ein Land (ISO 3166-1 alpha-2). Leer/ungültig → [] (Startseite bleibt in der Standard-Reihenfolge). */
function ch_country_focus(string $cc): array {
    $cc=strtoupper(trim($cc)); if(!preg_match('/^[A-Z]{2}$/',$cc) || $cc==='XX' || $cc==='T1') return [];
    $pk=isset(CH_COUNTRY_PROFILES[$cc]) ? $cc : (CH_COUNTRY_ALIAS[$cc] ?? 'DEFAULT');
    [$cats,$boost,$hero,$why]=CH_COUNTRY_PROFILES[$pk];
    $trOnly=['agent-hair-tr','agent-dental-tr','buy-hair-tr','buy-dental-tr'];
    $cats=array_values(array_filter($cats,fn($c)=>isset(CH_CATEGORIES[$c]) && $c!=='medtour'));
    $boost=array_values(array_filter($boost,fn($p)=>isset(CH_PLANS[$p]) && (CH_PLANS[$p][4]==='agent' || str_starts_with($p,'privat-')) && !in_array($p,$trOnly,true)));
    return ['cc'=>$cc,'profile'=>$pk,'cats'=>$cats,'boost'=>array_slice($boost,0,6),'hero'=>array_slice($hero,0,3),'why'=>$why,'tuerkiye'=>in_array($cc,['TR','AZ','CY'],true)];
}
