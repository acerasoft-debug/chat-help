/** Editorial long-tail content. One slug, three languages, same structure. */
export const journalMeta = {
  de: { title: 'Journal', sub: 'Ehrliche Antworten zu Körper, Haut und Ritual — geschrieben von unseren Therapeut:innen.', readMore: 'Weiterlesen', minutes: 'Min. Lesezeit', by: 'Von' },
  en: { title: 'Journal', sub: 'Honest answers on body, skin and ritual — written by our therapists.', readMore: 'Read on', minutes: 'min read', by: 'By' },
  es: { title: 'Journal', sub: 'Respuestas honestas sobre cuerpo, piel y ritual, escritas por nuestras terapeutas.', readMore: 'Seguir leyendo', minutes: 'min de lectura', by: 'Por' },
  fr: { title: 'Journal', sub: 'Des réponses honnêtes sur le corps, la peau et le rituel — écrites par nos thérapeutes.', readMore: 'Lire la suite', minutes: 'min de lecture', by: 'Par' },
  it: { title: 'Journal', sub: 'Risposte oneste su corpo, pelle e rituale — scritte dai nostri terapisti.', readMore: 'Continua a leggere', minutes: 'min di lettura', by: 'Di' }
};

export const articles = [
  {
    slug: 'cellulite-was-massage-wirklich-kann',
    date: '2026-08-21',
    author: 'Nadja Y., Med. Masseurin, Berlin',
    service: 'anti-cellulite',
    minutes: 5,
    de: {
      title: 'Cellulite: Was Massage wirklich kann — und was nicht',
      excerpt: 'Keine Behandlung „entfernt“ Cellulite. Aber eine gut geführte Kur verändert Hautbild, Umfang und Gefühl messbar. Hier ist die ehrliche Version.',
      body: [
        'Cellulite ist keine Krankheit und kein Fettproblem, sondern eine Strukturfrage: Bei etwa neun von zehn Frauen sind die Bindegewebsstränge senkrecht angeordnet, sodass Fettkammern nach oben drücken können. Kein Öl und keine Massage ändert diese Anatomie. Was sich ändern lässt, sind drei Dinge: die Mikrozirkulation im Gewebe, der Lymphabfluss und die Verklebungen der oberflächlichen Faszien.',
        'Genau daran arbeitet eine Anti-Cellulite-Kur. Kräftiges Rollen und Kneten lösen Faszienverklebungen, Vakuum-Schröpfen zieht Flüssigkeit aus dem Zwischenzellraum, die abschließende Lymphdrainage transportiert sie ab. Nach der zweiten bis dritten Sitzung ist die Haut sichtbar glatter, die Beine fühlen sich leichter an. Nach acht bis zehn wöchentlichen Terminen verändert sich in unserem Umfangsprotokoll typischerweise der Oberschenkelumfang um ein bis drei Zentimeter.',
        'Was die Kur nicht leistet: dauerhafte Ergebnisse ohne Erhaltung. Wer nach der Kur aufhört, verliert den Effekt über drei bis vier Monate. Wer alle vier Wochen einen Erhaltungstermin bucht, täglich trockenbürstet und ausreichend trinkt, hält ihn. Wir sagen das vor der ersten Buchung, nicht danach.'
      ]
    },
    en: {
      title: 'Cellulite: what massage can really do — and what it cannot',
      excerpt: 'No treatment "removes" cellulite. But a well-run course changes skin texture, circumference and how your legs feel, measurably. Here is the honest version.',
      body: [
        'Cellulite is neither a disease nor a fat problem; it is a structural question. In roughly nine out of ten women the connective-tissue strands run vertically, allowing fat chambers to push upwards. No oil and no massage changes that anatomy. What can change are three things: microcirculation in the tissue, lymphatic clearance, and adhesions in the superficial fascia.',
        'That is exactly what an anti-cellulite course works on. Firm rolling and kneading release fascial adhesions, vacuum cupping draws fluid out of the interstitial space, and the closing lymphatic drainage carries it away. After the second or third session the skin is visibly smoother and the legs feel lighter. After eight to ten weekly appointments our measurement log typically shows a change in thigh circumference of one to three centimetres.',
        'What the course does not deliver: permanent results without maintenance. Stop after the course and the effect fades over three to four months. Book a maintenance session every four weeks, dry-brush daily and drink enough, and it holds. We say this before the first booking, not after.'
      ]
    },
    fr: {
      title: "Cellulite : ce que le massage peut vraiment faire — et ce qu’il ne peut pas",
      excerpt: "Aucun soin « n’enlève » la cellulite. Mais une cure bien menée change la texture de la peau, le tour de cuisse et la sensation, de façon mesurable. Voici la version honnête.",
      body: [
        "La cellulite n’est ni une maladie ni un problème de graisse ; c’est une question de structure. Chez environ neuf femmes sur dix, les cloisons du tissu conjonctif sont verticales, ce qui laisse les lobules graisseux pousser vers le haut. Aucune huile, aucun massage ne change cette anatomie. Ce qui peut changer, ce sont trois choses : la microcirculation dans le tissu, le drainage lymphatique et les adhérences des fascias superficiels.",
        "C’est exactement là qu’agit une cure anti-cellulite. Roulements et pétrissages fermes libèrent les adhérences, les ventouses extraient le liquide de l’espace interstitiel, et le drainage final l’évacue. Après la deuxième ou troisième séance, la peau est visiblement plus lisse et les jambes plus légères. Après huit à dix rendez-vous hebdomadaires, notre suivi montre en général un changement de tour de cuisse de un à trois centimètres.",
        "Ce que la cure n’offre pas : des résultats permanents sans entretien. Arrêtez après la cure et l’effet s’estompe en trois à quatre mois. Réservez une séance d’entretien toutes les quatre semaines, brossez à sec chaque jour, buvez assez, et il tient. Nous le disons avant la première réservation, pas après."
      ]
    },
    it: {
      title: "Cellulite: cosa può fare davvero il massaggio — e cosa no",
      excerpt: "Nessun trattamento «elimina» la cellulite. Ma un ciclo ben condotto cambia texture della pelle, circonferenza e sensazione in modo misurabile. Ecco la versione onesta.",
      body: [
        "La cellulite non è una malattia né un problema di grasso: è una questione strutturale. In circa nove donne su dieci i setti del tessuto connettivo sono verticali e lasciano che i lobuli di grasso spingano verso l’alto. Nessun olio e nessun massaggio cambia questa anatomia. Ciò che può cambiare sono tre cose: il microcircolo nel tessuto, il drenaggio linfatico e le aderenze delle fasce superficiali.",
        "È esattamente lì che lavora un ciclo anticellulite. Rullamento e impastamento decisi sciolgono le aderenze, la coppettazione estrae liquido dallo spazio interstiziale e il drenaggio finale lo smaltisce. Dopo la seconda o terza seduta la pelle è visibilmente più liscia e le gambe più leggere. Dopo otto-dieci appuntamenti settimanali, il nostro registro mostra in genere un cambiamento della circonferenza della coscia di uno-tre centimetri.",
        "Cosa non offre il ciclo: risultati permanenti senza mantenimento. Se ti fermi dopo il ciclo, l’effetto svanisce in tre-quattro mesi. Con una seduta di mantenimento ogni quattro settimane, spazzolatura a secco quotidiana e buona idratazione, si mantiene. Lo diciamo prima della prima prenotazione, non dopo."
      ]
    },
    es: {
      title: 'Celulitis: lo que el masaje puede hacer de verdad, y lo que no',
      excerpt: 'Ningún tratamiento "elimina" la celulitis. Pero una cura bien dirigida cambia la textura, el contorno y la sensación de forma medible. Esta es la versión honesta.',
      body: [
        'La celulitis no es una enfermedad ni un problema de grasa, sino una cuestión estructural. En unas nueve de cada diez mujeres las fibras del tejido conectivo están dispuestas en vertical, lo que permite que las cámaras de grasa empujen hacia arriba. Ningún aceite ni masaje cambia esa anatomía. Lo que sí puede cambiar son tres cosas: la microcirculación del tejido, el drenaje linfático y las adherencias de la fascia superficial.',
        'Justo sobre eso trabaja una cura anticelulítica. El rodamiento y amasado firmes liberan adherencias fasciales, las ventosas de vacío extraen líquido del espacio intersticial y el drenaje linfático final lo transporta. Tras la segunda o tercera sesión la piel está visiblemente más lisa y las piernas se sienten más ligeras. Tras ocho o diez citas semanales, nuestro registro de medidas suele mostrar un cambio de uno a tres centímetros en el contorno del muslo.',
        'Lo que la cura no ofrece: resultados permanentes sin mantenimiento. Si paras al terminar, el efecto se desvanece en tres o cuatro meses. Si reservas una sesión de mantenimiento cada cuatro semanas, te cepillas en seco a diario y bebes suficiente, se mantiene. Lo decimos antes de la primera reserva, no después.'
      ]
    }
  },
  {
    slug: 'zuhause-vorbereiten-mobile-massage',
    date: '2026-07-30',
    author: 'Lena B., Physiotherapeutin, München',
    service: 'signature-lumea',
    minutes: 4,
    de: {
      title: 'Wie Sie Ihr Zuhause in zehn Minuten spa-fertig machen',
      excerpt: 'Sie müssen nichts besitzen und nichts kaufen. Aber vier kleine Handgriffe machen aus einer guten Behandlung eine außergewöhnliche.',
      body: [
        'Ihre Therapeutin bringt Liege, Wäsche, Öle, Musik und Duft mit. Ihr Beitrag ist Raum und Ruhe. Zwei mal zweieinhalb Meter freie Fläche genügen — das ist weniger als ein Doppelbett. Wohnzimmer sind ideal, weil der Boden meist eben ist und eine Steckdose in der Nähe liegt.',
        'Vier Dinge machen den Unterschied. Erstens Temperatur: Der Raum darf wärmer sein als üblich, etwa 23 bis 24 Grad, weil Sie liegen und nicht in Bewegung sind. Zweitens Licht: Dimmen oder eine einzelne Lampe statt Deckenlicht. Drittens Ton: Telefon auf lautlos, Türklingel aus, Haustiere in einem anderen Zimmer. Viertens Zeit danach: Planen Sie zwanzig Minuten ohne Termin ein, in denen Sie liegen bleiben und Tee trinken dürfen.',
        'Duschen vor der Behandlung ist angenehm, aber nicht nötig. Nach der Behandlung empfehlen wir, die Öle mindestens eine Stunde einziehen zu lassen. Und ja: Sie dürfen einschlafen. Das ist kein Fauxpas, sondern das beste Feedback, das Ihre Therapeutin bekommen kann.'
      ]
    },
    en: {
      title: 'How to make your home spa-ready in ten minutes',
      excerpt: 'You need to own nothing and buy nothing. But four small moves turn a good treatment into an exceptional one.',
      body: [
        'Your therapist brings the table, linen, oils, music and scent. Your contribution is space and quiet. Two by two-and-a-half metres of clear floor is enough — less than a double bed. Living rooms are ideal because the floor is usually level and a socket is nearby.',
        'Four things make the difference. First, temperature: the room can be warmer than usual, around 23 to 24 degrees, because you are lying still. Second, light: dim it, or use a single lamp instead of the ceiling light. Third, sound: phone on silent, doorbell off, pets in another room. Fourth, time afterwards: plan twenty minutes with no appointment in which you may stay lying down and drink tea.',
        'Showering before the treatment is pleasant but not necessary. Afterwards we recommend letting the oils absorb for at least an hour. And yes — you may fall asleep. That is not a faux pas; it is the best feedback your therapist can receive.'
      ]
    },
    fr: {
      title: "Préparer sa maison pour le spa en dix minutes",
      excerpt: "Vous n’avez rien à posséder ni à acheter. Mais quatre petits gestes transforment un bon soin en soin exceptionnel.",
      body: [
        "Votre thérapeute apporte la table, le linge, les huiles, la musique et le parfum. Votre contribution, c’est l’espace et le calme. Deux mètres sur deux mètres cinquante de sol libre suffisent — moins qu’un lit double. Le salon est idéal : le sol y est plat et une prise est à portée.",
        "Quatre choses font la différence. La température : la pièce peut être plus chaude que d’habitude, 23 à 24 degrés, car vous êtes allongé sans bouger. La lumière : tamisez, ou une seule lampe plutôt que le plafonnier. Le son : téléphone en silencieux, sonnette coupée, animaux dans une autre pièce. Le temps d’après : prévoyez vingt minutes sans rendez-vous pour rester allongé et boire un thé.",
        "Se doucher avant est agréable mais pas nécessaire. Après, laissez les huiles pénétrer au moins une heure. Et oui, vous pouvez vous endormir. Ce n’est pas un faux pas ; c’est le meilleur retour que votre thérapeute puisse recevoir."
      ]
    },
    it: {
      title: "Come preparare la casa per la spa in dieci minuti",
      excerpt: "Non devi possedere né comprare nulla. Ma quattro piccoli gesti trasformano un buon trattamento in uno eccezionale.",
      body: [
        "La terapista porta lettino, biancheria, oli, musica e profumo. Il tuo contributo è spazio e silenzio. Bastano due metri per due e mezzo di pavimento libero — meno di un letto matrimoniale. Il salotto è ideale: il pavimento è piano e una presa è vicina.",
        "Quattro cose fanno la differenza. La temperatura: la stanza può essere più calda del solito, 23–24 gradi, perché stai sdraiato senza muoverti. La luce: abbassala, o una sola lampada invece del lampadario. Il suono: telefono in silenzioso, campanello spento, animali in un’altra stanza. Il tempo di dopo: prevedi venti minuti senza impegni per restare sdraiato e bere un tè.",
        "Fare la doccia prima è piacevole ma non necessario. Dopo, lascia assorbire gli oli almeno un’ora. E sì, puoi addormentarti. Non è una gaffe: è il miglior feedback che la terapista possa ricevere."
      ]
    },
    es: {
      title: 'Cómo dejar tu casa lista para el spa en diez minutos',
      excerpt: 'No necesitas tener nada ni comprar nada. Pero cuatro gestos pequeños convierten un buen tratamiento en uno excepcional.',
      body: [
        'Tu terapeuta trae camilla, ropa, aceites, música y aroma. Tu aportación es espacio y silencio. Dos por dos metros y medio de suelo libre bastan: menos que una cama de matrimonio. El salón es ideal porque el suelo suele estar nivelado y hay un enchufe cerca.',
        'Cuatro cosas marcan la diferencia. Primero, la temperatura: la sala puede estar más cálida de lo habitual, unos 23 o 24 grados, porque estarás tumbada sin moverte. Segundo, la luz: atenúala o usa una sola lámpara en vez de la del techo. Tercero, el sonido: móvil en silencio, timbre apagado, mascotas en otra habitación. Cuarto, el tiempo de después: reserva veinte minutos sin compromisos para quedarte tumbada y tomar un té.',
        'Ducharse antes es agradable, pero no necesario. Después recomendamos dejar que los aceites se absorban al menos una hora. Y sí: puedes quedarte dormida. No es una descortesía; es el mejor feedback que puede recibir tu terapeuta.'
      ]
    }
  },
  {
    slug: 'lymphdrainage-nach-dem-flug',
    date: '2026-07-02',
    author: 'Céline F., Dipl. Masseurin EMR, Genf',
    service: 'lymphatic-drainage',
    minutes: 4,
    de: {
      title: 'Schwere Beine nach dem Langstreckenflug: Warum Lymphdrainage in 24 Stunden wirkt',
      excerpt: 'Zehn Stunden Sitzen, trockene Kabinenluft, Salz im Essen — der Körper speichert Flüssigkeit, wo er sie nicht braucht. Eine Behandlung am Ankunftstag ändert das.',
      body: [
        'Während eines Langstreckenflugs sinkt der Kabinendruck auf das Niveau von etwa 2.400 Metern Höhe. Die Gefäße weiten sich, Flüssigkeit tritt ins Gewebe über, und weil die Wadenmuskulatur beim Sitzen nicht pumpt, bleibt sie dort. Das Ergebnis kennen Sie: geschwollene Knöchel, enge Schuhe, ein Gesicht, das am nächsten Morgen aufgedunsen aussieht.',
        'Die manuelle Lymphdrainage macht genau das, was die Muskulatur im Flugzeug nicht konnte. Mit sehr leichtem Druck — deutlich weniger als bei einer Massage — werden zunächst die Lymphknoten am Hals geöffnet, dann Beine, Bauch und Arme in Richtung Abfluss ausgestrichen. Eine 60-Minuten-Sitzung am Ankunftstag reduziert Schwellungen meist noch am selben Abend spürbar; die Wirkung auf den Schlaf ist oft das, was Gäste am meisten überrascht.',
        'Wir empfehlen die Behandlung innerhalb von 24 Stunden nach Landung, für Geschäftsreisende gern direkt im Hotel. Kombiniert mit ausreichend Wasser und einer Nacht mit leicht erhöhten Beinen sind Sie am nächsten Tag wieder in Ihrem Körper angekommen.'
      ]
    },
    en: {
      title: 'Heavy legs after a long-haul flight: why lymphatic drainage works within 24 hours',
      excerpt: 'Ten hours seated, dry cabin air, salt in the food — the body stores fluid where it does not need it. A treatment on arrival day changes that.',
      body: [
        'During a long-haul flight the cabin pressure drops to the equivalent of roughly 2,400 metres of altitude. Vessels dilate, fluid moves into the tissue, and because the calf muscles do not pump while seated, it stays there. You know the result: swollen ankles, tight shoes, a face that looks puffy the next morning.',
        'Manual lymphatic drainage does exactly what the muscles could not do on the plane. With very light pressure — far less than a massage — the lymph nodes at the neck are opened first, then legs, abdomen and arms are cleared towards the drainage points. A 60-minute session on arrival day usually reduces swelling noticeably by the same evening; the effect on sleep is often what surprises guests most.',
        'We recommend the treatment within 24 hours of landing, for business travellers ideally straight in the hotel. Combined with plenty of water and a night with the legs slightly elevated, you are back in your body the next day.'
      ]
    },
    fr: {
      title: "Jambes lourdes après un long-courrier : pourquoi le drainage lymphatique agit en 24 heures",
      excerpt: "Dix heures assis, air sec, sel dans les repas — le corps stocke du liquide là où il n’en a pas besoin. Un soin le jour de l’arrivée change tout.",
      body: [
        "Pendant un vol long-courrier, la pression de cabine descend à l’équivalent d’environ 2 400 mètres d’altitude. Les vaisseaux se dilatent, le liquide passe dans les tissus, et comme les mollets ne pompent pas en position assise, il y reste. Vous connaissez le résultat : chevilles gonflées, chaussures serrées, visage bouffi le lendemain.",
        "Le drainage lymphatique manuel fait exactement ce que les muscles n’ont pas pu faire dans l’avion. Avec une pression très légère — bien moindre qu’un massage — on ouvre d’abord les ganglions du cou, puis on dégage jambes, ventre et bras vers les points d’évacuation. Une séance de 60 minutes le jour de l’arrivée réduit généralement le gonflement le soir même ; l’effet sur le sommeil est souvent ce qui surprend le plus.",
        "Nous recommandons le soin dans les 24 heures suivant l’atterrissage, idéalement à l’hôtel pour les voyageurs d’affaires. Avec beaucoup d’eau et une nuit jambes légèrement surélevées, vous êtes de retour dans votre corps le lendemain."
      ]
    },
    it: {
      title: "Gambe pesanti dopo un volo intercontinentale: perché il drenaggio linfatico agisce in 24 ore",
      excerpt: "Dieci ore seduti, aria secca, sale nel cibo — il corpo accumula liquidi dove non servono. Un trattamento il giorno dell’arrivo cambia tutto.",
      body: [
        "Durante un volo intercontinentale la pressione in cabina scende all’equivalente di circa 2.400 metri di quota. I vasi si dilatano, il liquido passa nei tessuti e, poiché i polpacci non pompano da seduti, resta lì. Il risultato lo conosci: caviglie gonfie, scarpe strette, viso gonfio la mattina dopo.",
        "Il drenaggio linfatico manuale fa esattamente ciò che i muscoli non hanno potuto fare in aereo. Con pressione molto leggera — molto meno di un massaggio — si aprono prima i linfonodi del collo, poi si liberano gambe, addome e braccia verso i punti di scarico. Una seduta di 60 minuti il giorno dell’arrivo riduce di solito il gonfiore già la sera stessa; l’effetto sul sonno è spesso ciò che sorprende di più.",
        "Consigliamo il trattamento entro 24 ore dall’atterraggio, per chi viaggia per lavoro direttamente in hotel. Con molta acqua e una notte a gambe leggermente sollevate, il giorno dopo sei di nuovo nel tuo corpo."
      ]
    },
    es: {
      title: 'Piernas pesadas tras un vuelo largo: por qué el drenaje linfático funciona en 24 horas',
      excerpt: 'Diez horas sentada, aire seco de cabina, sal en la comida: el cuerpo retiene líquido donde no lo necesita. Un tratamiento el día de llegada lo cambia.',
      body: [
        'Durante un vuelo de larga distancia la presión de cabina baja al equivalente de unos 2.400 metros de altitud. Los vasos se dilatan, el líquido pasa al tejido y, como los gemelos no bombean mientras estás sentada, se queda ahí. El resultado lo conoces: tobillos hinchados, zapatos que aprietan y una cara abotargada a la mañana siguiente.',
        'El drenaje linfático manual hace justo lo que los músculos no pudieron hacer en el avión. Con una presión muy ligera, mucho menor que la de un masaje, primero se abren los ganglios del cuello y después se despejan piernas, abdomen y brazos hacia los puntos de drenaje. Una sesión de 60 minutos el día de llegada suele reducir la hinchazón de forma perceptible esa misma noche; el efecto sobre el sueño es lo que más sorprende.',
        'Recomendamos el tratamiento dentro de las 24 horas posteriores al aterrizaje, para viajes de trabajo idealmente en el propio hotel. Con agua suficiente y una noche con las piernas ligeramente elevadas, al día siguiente vuelves a estar en tu cuerpo.'
      ]
    }
  },
  {
    slug: 'hautpflege-vor-dem-event-sieben-tage',
    date: '2026-06-11',
    author: 'Marta D., Esteticista titulada, Madrid',
    service: 'hydra-glow',
    minutes: 5,
    de: {
      title: 'Sieben Tage bis zum Event: Der Hautpflege-Fahrplan, der wirklich funktioniert',
      excerpt: 'Die häufigste Panne vor Hochzeiten und Shootings ist nicht zu wenig Pflege, sondern die falsche Behandlung zum falschen Zeitpunkt.',
      body: [
        'Tag 7: Falls Sie etwas Neues ausprobieren wollen — jetzt, nicht später. Ein Enzym-Peeling mit Barriereaufbau ist die sicherste Wahl, weil es erneuert, ohne zu reizen. Ab jetzt keine neuen Wirkstoffe mehr, kein Retinol, keine Säuren, die Sie nicht kennen.',
        'Tag 4 bis 5: Lifting-Facial mit Mikrostrom und Lymphdrainage, wenn Kontur und Schwellung das Thema sind. Die Muskulatur reagiert über mehrere Tage, das Ergebnis baut sich also bis zum Event auf. Wer zu Rötungen neigt, legt hier auch die letzte Ausreinigung hin — nicht später.',
        'Tag 1 bis 2: Hydra Glow. Poren leer, Wirkstoffinfusion, LED, Feuchtigkeitsmaske — sofort präsentabel, null Ausfallzeit. Das ist die Behandlung, die 24 bis 48 Stunden vor dem Termin am meisten sichtbar macht. Am Tag selbst: nur das Augen- und Dekolleté-Ritual mit Kryo-Kugeln, wenn die Nacht kurz war. Nichts Neues, nichts Aggressives, viel Wasser.'
      ]
    },
    en: {
      title: 'Seven days to the event: the skincare timeline that actually works',
      excerpt: 'The most common mishap before weddings and shoots is not too little care, but the wrong treatment at the wrong time.',
      body: [
        'Day 7: If you want to try something new — now, not later. An enzyme peel with barrier repair is the safest choice, because it renews without irritating. From here on, no new actives, no retinol, no acids you do not already know.',
        'Days 4 to 5: Lifting facial with microcurrent and lymphatic drainage if contour and puffiness are the concern. The muscles respond over several days, so the result builds towards the event. If you are prone to redness, this is also where the last extractions belong — not later.',
        'Days 1 to 2: Hydra Glow. Pores cleared, active infusion, LED, hydrating mask — presentable immediately, zero downtime. This is the treatment that shows most when done 24 to 48 hours before. On the day itself: only the eye and décolleté ritual with cryo globes if the night was short. Nothing new, nothing aggressive, plenty of water.'
      ]
    },
    fr: {
      title: "Sept jours avant l’événement : le calendrier peau qui fonctionne vraiment",
      excerpt: "L’erreur la plus fréquente avant un mariage ou un shooting n’est pas de trop peu se soigner, mais de faire le mauvais soin au mauvais moment.",
      body: [
        "Jour 7 : si vous voulez essayer quelque chose de nouveau — maintenant, pas plus tard. Un peeling enzymatique avec réparation de barrière est le choix le plus sûr, car il renouvelle sans irriter. À partir de là, aucun nouvel actif, ni rétinol ni acide inconnu.",
        "Jours 4 à 5 : soin lifting avec microcourant et drainage lymphatique si le contour et le gonflement sont le sujet. Les muscles répondent sur plusieurs jours, le résultat monte donc jusqu’à l’événement. En cas de tendance aux rougeurs, c’est aussi là que se placent les dernières extractions — pas plus tard.",
        "Jours 1 à 2 : Hydra Glow. Pores libérés, infusion d’actifs, LED, masque hydratant — présentable aussitôt, zéro récupération. C’est le soin qui se voit le plus 24 à 48 heures avant. Le jour même : uniquement le rituel yeux et décolleté aux globes cryo si la nuit a été courte. Rien de nouveau, rien d’agressif, beaucoup d’eau."
      ]
    },
    it: {
      title: "Sette giorni all’evento: il calendario skincare che funziona davvero",
      excerpt: "L’errore più comune prima di matrimoni e shooting non è curarsi poco, ma fare il trattamento sbagliato nel momento sbagliato.",
      body: [
        "Giorno 7: se vuoi provare qualcosa di nuovo — ora, non dopo. Un peeling enzimatico con riparazione della barriera è la scelta più sicura, perché rinnova senza irritare. Da qui in poi niente attivi nuovi, niente retinolo, niente acidi che non conosci.",
        "Giorni 4–5: facial lifting con microcorrenti e drenaggio linfatico se il tema è contorno e gonfiore. La muscolatura risponde per diversi giorni, quindi il risultato cresce fino all’evento. Se tendi al rossore, qui vanno anche le ultime estrazioni — non dopo.",
        "Giorni 1–2: Hydra Glow. Pori liberi, infusione di attivi, LED, maschera idratante — presentabile subito, zero recupero. È il trattamento che si nota di più fatto 24–48 ore prima. Il giorno stesso: solo il rituale occhi e décolleté con sfere crio se la notte è stata breve. Niente di nuovo, niente di aggressivo, molta acqua."
      ]
    },
    es: {
      title: 'Siete días para el evento: el calendario de cuidado facial que funciona de verdad',
      excerpt: 'El fallo más habitual antes de bodas y sesiones no es cuidarse poco, sino hacer el tratamiento equivocado en el momento equivocado.',
      body: [
        'Día 7: si quieres probar algo nuevo, ahora, no después. Un peeling enzimático con reparación de barrera es la opción más segura, porque renueva sin irritar. A partir de aquí, nada de activos nuevos, ni retinol ni ácidos que no conozcas.',
        'Días 4 y 5: facial lifting con microcorrientes y drenaje linfático si el tema es el contorno y la hinchazón. La musculatura responde durante varios días, así que el resultado va creciendo hasta el evento. Si tiendes al enrojecimiento, aquí van también las últimas extracciones, no más tarde.',
        'Días 1 y 2: Hydra Glow. Poros limpios, infusión de activos, LED, mascarilla hidratante: presentable al instante, sin recuperación. Es el tratamiento que más se nota hecho entre 24 y 48 horas antes. El mismo día: solo el ritual de ojos y escote con esferas frías si la noche fue corta. Nada nuevo, nada agresivo y mucha agua.'
      ]
    }
  }
];
