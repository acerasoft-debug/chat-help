<?php
/**
 * VESTRA — Verified B2B Fashion Wholesale · Live marketplace homepage
 */
$BRAND   = 'VESTRA';
$CONTACT = 'support@vestrasales.com';
$COMPANY = 'Acerasoft LLC';
$ACCENT  = '#c9a86a';
/* Signed-in visitors get "open my dashboard" instead of register CTAs —
   register.php would only redirect them anyway (reads as a dead button).

   The session MUST be started by inc/auth.php, never by a bare session_start()
   here. auth.php sets the 90-day cookie, points the store at data/sessions, and
   restores a "remember me" login. Starting the session first meant this page ran
   with PHP's defaults instead: a cookie that dies when the browser closes, and the
   shared /tmp store, which the host's own garbage collector empties within minutes.
   Login writes to data/sessions and the homepage looked in /tmp -- same session id,
   two different drawers -- so a signed-in visitor was shown "Register" here for
   every visit, no matter how many times they signed in. */
require_once __DIR__.'/inc/auth.php';
$LOGGED    = !empty($_SESSION['uid']);
$panelHref = ($_SESSION['utype'] ?? '') === 'seller' ? '/seller' : '/buyer';
require_once __DIR__.'/inc/i18n.php';

/* Hero film — a slow cross-fade of real catalogue photography behind the headline.
   Frames are drawn from the live catalogue rather than a stock clip: they are the
   operator's own product shots, so there is no licensing question and no external
   host to fetch from (the site's CSP would block one anyway). It is an image
   sequence, not an <video>, which keeps it to a few hundred KB, needs no codec and
   still reads as a fashion film. Falls back silently to the plain hero if the
   catalogue has no photos yet. */
$HERO_FRAMES = [];
if (@include_once __DIR__.'/inc/products.php') {
    /* The film should look like a season, not a rail of black t-shirts. Frames are
       picked to be visually different from each other: a women's swimsuit and a pair
       of jeans are requested outright, and the rest favour pieces whose name says
       there is colour or a print in the shot. Category order below IS the running
       order, so the sequence opens on swimwear and denim rather than on whatever
       happens to sort first. */
    $WANT = ["Women's Swimwear", "Women's T-Shirts", 'Jeans', 'Swim Shorts',
             'Hoodies & Sweatshirts', "Women's Polos", 'Polos', 'T-Shirts', 'Tracksuit Sets'];
    /* Colour and print words that actually appear in these product names. A photo of
       a "Red Print T-Shirt" carries the screen; a "Logo T-Shirt, Black" does not. */
    $COLOUR = '/\b(red|pink|orange|yellow|green|blue|purple|turquoise|teal|cream|beige|camel|
                 print|printed|floral|check|monogram|allover|rainbow|stripe|striped|
                 tiger|baroque|distressed|wings|crest)\b/ix';

    /* Frames the operator has taken out of the film by name. Kept as a list rather
       than a one-off condition because "not that one" is a recurring note and the
       product stays perfectly saleable -- it is only excluded from the homepage.
       Matched against the image PATH, so a folder-level fragment ('rl/csf-polo')
       takes a whole colourway line out in one entry. */
    $HERO_SKIP = ['burberry-8039175', 'rl/csf-polo'];

    /* Which houses lead the homepage and which one steps back. Matched as a
       lowercase substring of the product's brand, so "DSQUARED2" and "Dsquared2"
       both land, and a brand the catalogue spells slightly differently still does.
       Kept as two lists next to $HERO_SKIP because "put this label forward" is the
       same kind of recurring operator note as "not that one" -- a name added here
       should not need anything else changed. */
    $HERO_BRAND_FIRST = ['balmain', 'dsquared'];
    $HERO_BRAND_LAST  = ['lacoste'];

    /* The film crops full-bleed across a wide, short band (background-size:cover),
       so a tall product packshot (a plain flat-lay/carton photo, common on freshly
       imported catalogue lines) gets zoomed into a near-abstract sliver of fabric
       instead of reading as a garment. Reject only clearly-unsuited outliers -- this
       is a sanity floor, not a strict landscape requirement. */
    $heroFits = function(string $im): bool {
        $dim = @getimagesize(__DIR__.$im);
        if (!$dim || $dim[0] <= 0 || $dim[1] <= 0) return true; // can't measure -> don't block it
        return ($dim[1] / $dim[0]) <= 1.6;
    };

    $pool = []; $pinnedFrames = [];
    foreach (vestra_products() as $hp) {
        $im = !empty($hp['images'][0]) ? $hp['images'][0]
            : (function_exists('vestra_primary_image') ? vestra_primary_image($hp) : '');
        if (!$im || $im[0] !== '/' || !is_file(__DIR__.$im)) continue;
        foreach ($HERO_SKIP as $sk) { if (stripos($im, $sk) !== false) continue 2; }
        if (!$heroFits($im)) continue;
        /* A product the operator has pinned (currently also pinned to the top of
           /shop) opens the film regardless of category -- it is the flagship piece
           of the moment, so it should lead rather than wait its turn in $WANT. */
        if (!empty($hp['pinned']) && !in_array($im, $pinnedFrames, true)) $pinnedFrames[] = $im;
        $cat = (string)($hp['cat'] ?? '');
        $pos = array_search($cat, $WANT, true);
        if ($pos === false) continue;
        $nm = (string)($hp['name'] ?? '');
        /* Brand rank, second in the sort key -- so within each category the featured
           houses are picked first and Lacoste last. It has to sit BELOW category
           rather than above it: the strip deals one frame per category in rotation,
           so a brand-first sort would fill the whole homepage with one label and lose
           the range the catalogue is supposed to show. Ranked this way, every
           category still gets a turn, but the piece it sends forward is the Balmain
           or DSQUARED2 one where such a piece exists.
           An unlisted brand ranks 1: featured houses lead, Lacoste follows, and
           everything else keeps its place in between rather than being pushed to the
           back by a rule that never named it. */
        $brandL = strtolower(trim((string)($hp['brand'] ?? '')));
        $brandRank = 1;
        foreach ($HERO_BRAND_FIRST as $b) { if ($brandL !== '' && str_contains($brandL, $b)) { $brandRank = 0; break; } }
        if ($brandRank === 1) {
            foreach ($HERO_BRAND_LAST as $b) { if ($brandL !== '' && str_contains($brandL, $b)) { $brandRank = 2; break; } }
        }
        /* Sort key: category order, then brand rank, then colourful names ahead of
           plain ones. The BALMAIN black tee that used to be pinned here has been
           dropped -- the operator asked for a second women's piece in that slot
           instead, and Women's T-Shirts now sits second in the running order. */
        $pool[] = [$pos, $brandRank, preg_match($COLOUR, $nm) ? 0 : 1, $cat, $im];
    }
    usort($pool, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

    /* Five plates hold up to six frames each, so thirty is the most the strip can
       show. The old cap was six — one frame per category — which left every plate
       holding a single photograph that never changed. */
    $HERO_MAX = 30;
    $HERO_FRAMES = array_slice($pinnedFrames, 0, 3);

    /* Round-robin across categories rather than one frame each: taking one per
       category filled the strip with the first six categories in $WANT and the
       jeans and sweatshirts further down the order never appeared at all. Dealing
       in rotation gives every category a turn before any category gets a second. */
    $byCat = [];
    foreach ($pool as [, , , $cat, $im]) {
        if (in_array($im, $HERO_FRAMES, true)) continue;
        $byCat[$cat][] = $im;
    }
    while (count($HERO_FRAMES) < $HERO_MAX && $byCat) {
        foreach ($byCat as $cat => $ims) {
            if (!$ims) { unset($byCat[$cat]); continue; }
            $HERO_FRAMES[] = array_shift($byCat[$cat]);
            if (count($HERO_FRAMES) >= $HERO_MAX) break 2;
        }
    }
}

/* The same film as an actual video clip. It is built from these very packshots by
   tools/hero-film/build.sh (garments on lit plates travelling across a dark stage),
   NOT from stock footage -- there is no licence to honour and the pieces on screen
   are ones a buyer can actually order.
   Presence of the file is the switch: drop hero.mp4 in and the homepage uses it,
   remove it and the CSS plate strip below takes over again. Nothing to configure.
   filemtime is appended so a re-cut clip is not served from a stale cache. */
$HERO_VIDEO = null;
if (is_file(__DIR__.'/assets/hero/hero.mp4')) {
    $_hvq = '?v='.@filemtime(__DIR__.'/assets/hero/hero.mp4');
    $HERO_VIDEO = ['mp4' => '/assets/hero/hero.mp4'.$_hvq, 'webm' => '', 'poster' => ''];
    if (is_file(__DIR__.'/assets/hero/hero.webm'))
        $HERO_VIDEO['webm'] = '/assets/hero/hero.webm'.$_hvq;
    if (is_file(__DIR__.'/assets/hero/hero-poster.jpg'))
        $HERO_VIDEO['poster'] = '/assets/hero/hero-poster.jpg'.$_hvq;
}

$LANGS = vlang_list();   // derived: the language switcher and hreflang follow inc/i18n.php
$lang  = vlang();

$T = [
'en'=>[
 'tagline'=>"Europe's verified B2B fashion wholesale",
 'meta'=>"A KYC-verified B2B wholesale marketplace for branded and textile fashion. Every seller is background-checked. Register free as a seller or buyer.",
 'why'=>'Why','how'=>'How it works','join_nav'=>'Get started',
 'pill'=>'Live · Open registration',
 'h1'=>'The <span class="acc">verified</span> way to trade<br>branded fashion, wholesale.',
 'sub'=>'A B2B marketplace where every seller is KYC-verified, every order runs on clear invoice terms, and every transaction is documented. Built on seller verification — not empty promises.',
 'b_sell'=>'Register as Seller','b_buy'=>'Register as Buyer','b_panel'=>'Open my dashboard',
 'tr1'=>'KYC-verified sellers','tr2'=>'Invoice-based payment','tr3'=>'Transaction records',
 'p1t'=>'Verified sellers only','p1d'=>'Business KYC on every seller — VAT ID, registration, identity. No anonymous listings, no guesswork.',
 'p2t'=>'Buyer protection','p2d'=>'Pay by invoice with a full paper trail. If goods don\'t match the listing, a structured dispute process steps in.',
 'p3t'=>'Transaction integrity','p3d'=>'Every order is logged, timestamped, and tied to the verified seller account — a clear paper trail for both sides of the trade.',
 'hsub'=>'Trust, by design — for both sides of the trade.',
 'brands_t'=>'The houses in stock','brands_s'=>'A live snapshot of verified inventory — every name below is currently sourced through a KYC-checked seller.',
 's1t'=>'Get verified','s1d'=>'Sellers and buyers complete a quick business verification. Approved members see live wholesale pricing.',
 's2t'=>'Source & order','s2d'=>'Browse listings from verified seller businesses — branded & textile basics. Order on clear invoice terms.',
 's3t'=>'Trade with confidence','s3d'=>'Verified seller credentials, documented invoicing and a structured dispute process cover every trade.',
 'jt'=>'Start trading on VESTRA','js'=>'Free registration. No commitment. Join hundreds of verified seller and buyer businesses.',
 'sell_title'=>"I'm a Seller",
 'sell_desc'=>'List your branded and textile products. Reach verified wholesale buyer businesses across Europe.',
 'sell_f1'=>'Documented invoice terms on every order',
 'sell_f2'=>'Commission from just 2.8%, lower on higher plans',
 'sell_f3'=>'Full control over pricing, MOQ and tiers',
 'sell_cta'=>'Register as Seller',
 'buy_title'=>"I'm a Buyer",
 'buy_desc'=>'Source fashion from KYC-verified seller businesses. Every transaction is documented and protected.',
 'buy_f1'=>'Only KYC-verified, background-checked sellers',
 'buy_f2'=>'Structured dispute process on every order',
 'buy_f3'=>'Free to browse and request quotes',
 'buy_cta'=>'Register as Buyer',
 'app_t'=>'VESTRA as an app','app_s'=>'Install straight from your browser — no App Store, no Play Store. Live prices, orders and messages, with push notifications.','app_and'=>'Install on Android','app_and_hint'=>'In Chrome: menu ⋮ → \'Install app\'.','app_apk'=>'Download APK','app_ios'=>'Install on iPhone','app_ios_hint'=>'Open in Safari → Share → \'Add to Home Screen\'.','app_noti'=>'Enable notifications','app_noti_ok'=>'Notifications are on ✓','app_noti_no'=>'Notifications blocked — allow them in your browser settings.','app_signin'=>'Sign in first to receive notifications.',
 'already'=>'Already have an account?','signin'=>'Sign in →',
],
'fr'=>[
 'tagline'=>"La vente en gros de mode B2B vérifiée en Europe",
 'meta'=>"Une marketplace B2B avec vérification KYC des vendeurs, pour la mode de marque et le textile. Inscrivez-vous gratuitement.",
 'why'=>'Pourquoi','how'=>'Comment ça marche','join_nav'=>'Commencer',
 'pill'=>'En ligne · Inscription ouverte',
 'h1'=>'La façon <span class="acc">vérifiée</span> de négocier<br>la mode de marque, en gros.',
 'sub'=>"Une marketplace B2B où chaque vendeur est vérifié (KYC), chaque commande repose sur des conditions de facturation claires et chaque transaction est documentée. Construite sur la vérification des vendeurs.",
 'b_sell'=>'Rejoindre comme Vendeur','b_buy'=>'Rejoindre comme Acheteur','b_panel'=>'Ouvrir mon tableau de bord',
 'tr1'=>'Vendeurs vérifiés (KYC)','tr2'=>'Paiement sur facture','tr3'=>'Traçabilité des transactions',
 'p1t'=>'Vendeurs vérifiés uniquement','p1d'=>'KYC entreprise sur chaque vendeur — TVA, immatriculation, identité. Aucune annonce anonyme.',
 'p2t'=>'Protection acheteur','p2d'=>"Paiement sur facture avec une traçabilité complète. Si la marchandise ne correspond pas à l'annonce, un processus de litige structuré s'enclenche.",
 'p3t'=>'Intégrité des transactions','p3d'=>"Chaque commande est enregistrée, horodatée et liée au compte du vendeur vérifié — une trace claire pour les deux parties.",
 'hsub'=>'La confiance par conception — pour les deux parties.',
 'brands_t'=>'Les maisons en stock','brands_s'=>'Un aperçu en direct du stock vérifié — chaque nom ci-dessous provient d\'un vendeur vérifié KYC.',
 's1t'=>'Faites-vous vérifier','s1d'=>'Vendeurs et acheteurs effectuent une vérification d\'entreprise rapide. Les membres approuvés voient les prix en direct.',
 's2t'=>'Sourcez & commandez','s2d'=>'Parcourez des annonces de vendeurs vérifiés — articles de marque et basiques textiles. Commandez sur facture, en toute clarté.',
 's3t'=>'Échangez en confiance','s3d'=>'Vérification des vendeurs, facturation documentée et processus de litige structuré.',
 'jt'=>'Commencez à trader sur VESTRA','js'=>'Inscription gratuite. Sans engagement. Rejoignez des centaines d\'entreprises vendeuses et acheteuses vérifiées.',
 'sell_title'=>'Je suis Vendeur',
 'sell_desc'=>'Listez vos produits de marque et textiles. Atteignez des entreprises acheteuses en gros vérifiées en Europe.',
 'sell_f1'=>'Conditions de facturation claires sur chaque commande',
 'sell_f2'=>'Commission dès 2,8% seulement, moins élevée avec les formules supérieures',
 'sell_f3'=>'Contrôle total sur les prix, MOQ et paliers',
 'sell_cta'=>'S\'inscrire comme Vendeur',
 'buy_title'=>'Je suis Acheteur',
 'buy_desc'=>'Sourcez de la mode auprès d\'entreprises vendeuses vérifiées KYC. Chaque transaction est documentée et protégée.',
 'buy_f1'=>'Uniquement des vendeurs KYC vérifiés',
 'buy_f2'=>'Processus de litige structuré sur chaque commande',
 'buy_f3'=>'Navigation et demandes de devis gratuites',
 'buy_cta'=>'S\'inscrire comme Acheteur',
 'app_t'=>'VESTRA en application','app_s'=>'Installez directement depuis le navigateur — sans App Store ni Play Store. Prix en direct, commandes et messages, avec notifications push.','app_and'=>'Installer sur Android','app_and_hint'=>'Dans Chrome : menu ⋮ → « Installer l’appli ».','app_apk'=>'Télécharger l’APK','app_ios'=>'Installer sur iPhone','app_ios_hint'=>'Dans Safari : Partager → « Sur l’écran d’accueil ».','app_noti'=>'Activer les notifications','app_noti_ok'=>'Notifications activées ✓','app_noti_no'=>'Notifications bloquées — autorisez-les dans les réglages du navigateur.','app_signin'=>'Connectez-vous d’abord pour recevoir les notifications.',
 'already'=>'Déjà un compte ?','signin'=>'Se connecter →',
],
'it'=>[
 'tagline'=>"Il commercio all'ingrosso di moda B2B verificato in Europa",
 'meta'=>"Un marketplace B2B con verifica KYC dei venditori, per moda di marca e tessile. Registrati gratuitamente.",
 'why'=>'Perché','how'=>'Come funziona','join_nav'=>'Inizia ora',
 'pill'=>'Online · Registrazione aperta',
 'h1'=>'Il modo <span class="acc">verificato</span> di commerciare<br>moda di marca, all\'ingrosso.',
 'sub'=>"Un marketplace B2B dove ogni venditore è verificato (KYC), ogni ordine si basa su chiare condizioni di fatturazione e ogni transazione è documentata.",
 'b_sell'=>'Registrati come Venditore','b_buy'=>'Registrati come Acquirente','b_panel'=>'Apri la mia dashboard',
 'tr1'=>'Venditori verificati (KYC)','tr2'=>'Pagamento su fattura','tr3'=>'Registri delle transazioni',
 'p1t'=>'Solo venditori verificati','p1d'=>'KYC aziendale su ogni venditore — partita IVA, registrazione, identità. Nessun annuncio anonimo.',
 'p2t'=>'Protezione acquirente','p2d'=>"Pagamento su fattura con tracciabilità completa. Se la merce non corrisponde all'annuncio, si attiva un processo di reclamo strutturato.",
 'p3t'=>'Integrità delle transazioni','p3d'=>"Ogni ordine è registrato, con marca temporale e collegato all'account del venditore verificato — una traccia chiara per entrambe le parti.",
 'hsub'=>'Fiducia per progettazione — per entrambe le parti.',
 'brands_t'=>'Le maison disponibili','brands_s'=>'Un\'istantanea live dello stock verificato — ogni marchio qui sotto proviene da un venditore verificato KYC.',
 's1t'=>'Verificati','s1d'=>'Venditori e acquirenti completano una rapida verifica aziendale. I membri approvati vedono i prezzi in tempo reale.',
 's2t'=>'Cerca & ordina','s2d'=>'Sfoglia annunci di venditori verificati — capi di marca e basici tessili. Ordina con chiare condizioni di fatturazione.',
 's3t'=>'Commercia con fiducia','s3d'=>'Verifica dei venditori, fatturazione documentata e processo di reclamo strutturato per ogni transazione.',
 'jt'=>'Inizia a fare trading su VESTRA','js'=>'Registrazione gratuita. Senza impegno. Unisciti a centinaia di aziende venditrici e acquirenti verificate.',
 'sell_title'=>'Sono un Venditore',
 'sell_desc'=>'Elenca i tuoi prodotti di marca e tessili. Raggiungi aziende acquirenti all\'ingrosso verificate in tutta Europa.',
 'sell_f1'=>'Condizioni di fatturazione chiare su ogni ordine',
 'sell_f2'=>'Commissione a partire dal 2,8%, più bassa con i piani superiori',
 'sell_f3'=>'Controllo totale su prezzi, MOQ e fasce',
 'sell_cta'=>'Registrati come Venditore',
 'buy_title'=>'Sono un Acquirente',
 'buy_desc'=>'Acquista moda da aziende venditrici verificate KYC. Ogni transazione è documentata e protetta.',
 'buy_f1'=>'Solo venditori KYC verificati',
 'buy_f2'=>'Processo di reclamo strutturato su ogni ordine',
 'buy_f3'=>'Navigazione e richieste di preventivo gratuite',
 'buy_cta'=>'Registrati come Acquirente',
 'app_t'=>'VESTRA come app','app_s'=>'Installa direttamente dal browser — senza App Store né Play Store. Prezzi live, ordini e messaggi, con notifiche push.','app_and'=>'Installa su Android','app_and_hint'=>'In Chrome: menu ⋮ → “Installa app”.','app_apk'=>'Scarica APK','app_ios'=>'Installa su iPhone','app_ios_hint'=>'In Safari: Condividi → “Aggiungi a Home”.','app_noti'=>'Attiva le notifiche','app_noti_ok'=>'Notifiche attive ✓','app_noti_no'=>'Notifiche bloccate — consentile nelle impostazioni del browser.','app_signin'=>'Accedi prima per ricevere le notifiche.',
 'already'=>'Hai già un account?','signin'=>'Accedi →',
],
'es'=>[
 'tagline'=>"La venta mayorista de moda B2B verificada en Europa",
 'meta'=>"Un marketplace B2B con verificación KYC de vendedores, para moda de marca y textil. Regístrate gratis.",
 'why'=>'Por qué','how'=>'Cómo funciona','join_nav'=>'Empezar',
 'pill'=>'En vivo · Registro abierto',
 'h1'=>'La forma <span class="acc">verificada</span> de comerciar<br>moda de marca, al por mayor.',
 'sub'=>'Un marketplace B2B donde cada vendedor está verificado (KYC), cada pedido se basa en condiciones de facturación claras y cada transacción queda registrada.',
 'b_sell'=>'Registrarse como Vendedor','b_buy'=>'Registrarse como Comprador','b_panel'=>'Abrir mi panel',
 'tr1'=>'Vendedores verificados (KYC)','tr2'=>'Pago por factura','tr3'=>'Registros de transacciones',
 'p1t'=>'Solo vendedores verificados','p1d'=>'KYC empresarial en cada vendedor — IVA, registro, identidad. Sin anuncios anónimos.',
 'p2t'=>'Protección al comprador','p2d'=>'Pago por factura con trazabilidad completa. Si la mercancía no coincide con el anuncio, se activa un proceso de disputa estructurado.',
 'p3t'=>'Integridad de transacciones','p3d'=>'Cada pedido queda registrado, con marca de tiempo y vinculado a la cuenta del vendedor verificado — un historial claro para ambas partes.',
 'hsub'=>'Confianza por diseño — para ambas partes.',
 'brands_t'=>'Las maisons disponibles','brands_s'=>'Una instantánea en vivo del stock verificado — cada firma aquí procede de un vendedor verificado KYC.',
 's1t'=>'Verifícate','s1d'=>'Vendedores y compradores completan una verificación empresarial rápida. Los miembros aprobados ven precios mayoristas en vivo.',
 's2t'=>'Busca & pide','s2d'=>'Explora anuncios de vendedores verificados — productos de marca y básicos textiles. Pide con condiciones de facturación claras.',
 's3t'=>'Comercia con confianza','s3d'=>'Verificación de vendedores, facturación documentada y proceso de disputa estructurado en cada operación.',
 'jt'=>'Empieza a operar en VESTRA','js'=>'Registro gratuito. Sin compromiso. Únete a cientos de empresas vendedoras y compradoras verificadas.',
 'sell_title'=>'Soy Vendedor',
 'sell_desc'=>'Lista tus productos de marca y textiles. Llega a empresas compradoras mayoristas verificadas en toda Europa.',
 'sell_f1'=>'Condiciones de facturación claras en cada pedido',
 'sell_f2'=>'Comisión desde solo el 2,8%, más baja en los planes superiores',
 'sell_f3'=>'Control total sobre precios, MOQ y tramos',
 'sell_cta'=>'Registrarse como Vendedor',
 'buy_title'=>'Soy Comprador',
 'buy_desc'=>'Abastécete de moda de empresas vendedoras verificadas KYC. Cada transacción queda documentada y protegida.',
 'buy_f1'=>'Solo vendedores KYC verificados',
 'buy_f2'=>'Proceso de disputa estructurado en cada pedido',
 'buy_f3'=>'Navegación y solicitudes de presupuesto gratuitas',
 'buy_cta'=>'Registrarse como Comprador',
 'app_t'=>'VESTRA como app','app_s'=>'Instálala directamente desde el navegador — sin App Store ni Play Store. Precios en vivo, pedidos y mensajes, con notificaciones push.','app_and'=>'Instalar en Android','app_and_hint'=>'En Chrome: menú ⋮ → “Instalar aplicación”.','app_apk'=>'Descargar APK','app_ios'=>'Instalar en iPhone','app_ios_hint'=>'En Safari: Compartir → “Añadir a pantalla de inicio”.','app_noti'=>'Activar notificaciones','app_noti_ok'=>'Notificaciones activadas ✓','app_noti_no'=>'Notificaciones bloqueadas — permítelas en los ajustes del navegador.','app_signin'=>'Inicia sesión primero para recibir notificaciones.',
 'already'=>'¿Ya tienes cuenta?','signin'=>'Iniciar sesión →',
],
'de'=>[
 'tagline'=>"Europas verifizierter B2B-Modegroßhandel",
 'meta'=>"Ein B2B-Großhandelsmarktplatz mit KYC-Verifizierung der Verkäufer für Marken- und Textilmode. Jetzt kostenlos registrieren.",
 'why'=>'Warum','how'=>"So funktioniert's",'join_nav'=>'Jetzt starten',
 'pill'=>'Live · Registrierung offen',
 'h1'=>'Der <span class="acc">verifizierte</span> Weg, Markenmode<br>im Großhandel zu handeln.',
 'sub'=>"Ein B2B-Marktplatz, auf dem jeder Verkäufer KYC-geprüft ist, jede Bestellung auf klaren Rechnungskonditionen läuft und jede Transaktion dokumentiert ist.",
 'b_sell'=>'Als Verkäufer registrieren','b_buy'=>'Als Käufer registrieren','b_panel'=>'Mein Dashboard öffnen',
 'tr1'=>'KYC-verifizierte Verkäufer','tr2'=>'Zahlung auf Rechnung','tr3'=>'Transaktionsnachweise',
 'p1t'=>'Nur verifizierte Verkäufer','p1d'=>'Geschäftliche KYC-Prüfung bei jedem Verkäufer — USt-IdNr., Registrierung, Identität. Keine anonymen Inserate.',
 'p2t'=>'Käuferschutz','p2d'=>'Zahlung auf Rechnung mit lückenloser Dokumentation. Entspricht die Ware nicht dem Angebot, greift ein strukturiertes Streitverfahren.',
 'p3t'=>'Transaktionsintegrität','p3d'=>'Jede Bestellung wird protokolliert, mit Zeitstempel versehen und dem verifizierten Verkäuferkonto zugeordnet — ein klarer Nachweis für beide Seiten.',
 'hsub'=>'Vertrauen durch Design — für beide Seiten des Handels.',
 'brands_t'=>'Die Marken im Bestand','brands_s'=>'Eine Live-Momentaufnahme des verifizierten Bestands — jeder Name hier stammt von einem KYC-geprüften Verkäufer.',
 's1t'=>'Verifizieren lassen','s1d'=>'Verkäufer und Käufer durchlaufen eine schnelle Geschäftsverifizierung. Freigegebene Mitglieder sehen Live-Großhandelspreise.',
 's2t'=>'Finden & bestellen','s2d'=>'Inserate von verifizierten Verkäufer-Unternehmen durchstöbern — Markenware & Textil-Basics. Auf klare Rechnungskonditionen bestellen.',
 's3t'=>'Mit Vertrauen handeln','s3d'=>'Verifizierte Verkäuferdaten, dokumentierte Rechnungsstellung und ein strukturiertes Streitverfahren sichern jeden Handel.',
 'jt'=>'Jetzt auf VESTRA handeln','js'=>'Kostenlose Registrierung. Kein Risiko. Tausende verifizierter Verkäufer- und Käufer-Unternehmen.',
 'sell_title'=>'Ich bin Verkäufer',
 'sell_desc'=>'Inserieren Sie Ihre Marken- und Textilprodukte. Erreichen Sie verifizierte Großhändler-Unternehmen in ganz Europa.',
 'sell_f1'=>'Klare Rechnungskonditionen bei jeder Bestellung',
 'sell_f2'=>'Provision ab nur 2,8%, niedriger in höheren Plänen',
 'sell_f3'=>'Volle Kontrolle über Preise, Mindestmenge und Staffeln',
 'sell_cta'=>'Als Verkäufer registrieren',
 'buy_title'=>'Ich bin Käufer',
 'buy_desc'=>'Beziehen Sie Mode von KYC-verifizierten Verkäufer-Unternehmen. Jede Transaktion ist dokumentiert und geschützt.',
 'buy_f1'=>'Nur KYC-verifizierte Verkäufer-Unternehmen',
 'buy_f2'=>'Strukturiertes Streitverfahren bei jeder Bestellung',
 'buy_f3'=>'Kostenlos stöbern und Angebote anfragen',
 'buy_cta'=>'Als Käufer registrieren',
 'app_t'=>'VESTRA als App','app_s'=>'Direkt aus dem Browser installieren — ohne App Store und Play Store. Live-Preise, Bestellungen & Nachrichten, mit Push-Benachrichtigungen.','app_and'=>'Auf Android installieren','app_and_hint'=>'In Chrome: Menü ⋮ → „App installieren“.','app_apk'=>'APK herunterladen','app_ios'=>'Auf dem iPhone installieren','app_ios_hint'=>'In Safari: Teilen → „Zum Home-Bildschirm“.','app_noti'=>'Benachrichtigungen aktivieren','app_noti_ok'=>'Benachrichtigungen aktiv ✓','app_noti_no'=>'Benachrichtigungen blockiert — bitte in den Browser-Einstellungen erlauben.','app_signin'=>'Bitte zuerst anmelden, um Benachrichtigungen zu erhalten.',
 'already'=>'Bereits ein Konto?','signin'=>'Anmelden →',
],
/* PT and RU added 3 Sep 2026 (operator: "6-7 dil yap — rusca ve portekizce ekle").
   Portuguese is European Portuguese (pt-PT) -- the market is Portugal first, Brazil is
   reached through the pt-BR hreflang variant. */
'pt'=>[
 'tagline'=>"O grossista B2B de moda verificado da Europa",
 'meta'=>"Um marketplace grossista B2B com verificação KYC para moda de marca e têxtil. Todos os vendedores são verificados. Registe-se gratuitamente como vendedor ou comprador.",
 'why'=>'Porquê','how'=>'Como funciona','join_nav'=>'Começar',
 'pill'=>'Ativo · Registo aberto',
 'h1'=>'A forma <span class="acc">verificada</span> de comprar<br>moda de marca por grosso.',
 'sub'=>'Um marketplace B2B onde todos os vendedores têm verificação KYC, todas as encomendas seguem condições claras de pagamento por fatura e todas as transações ficam documentadas. Assente na verificação dos vendedores — não em promessas vazias.',
 'b_sell'=>'Registar como vendedor','b_buy'=>'Registar como comprador','b_panel'=>'Abrir o meu painel',
 'tr1'=>'Vendedores com verificação KYC','tr2'=>'Pagamento por fatura','tr3'=>'Registo de transações',
 'p1t'=>'Só vendedores verificados','p1d'=>'KYC empresarial em todos os vendedores — NIF/IVA, registo, identidade. Sem anúncios anónimos, sem adivinhações.',
 'p2t'=>'Proteção do comprador','p2d'=>'Pague por fatura com rasto documental completo. Se a mercadoria não corresponder ao anúncio, entra em ação um processo estruturado de litígio.',
 'p3t'=>'Integridade da transação','p3d'=>'Cada encomenda é registada, datada e associada à conta verificada do vendedor — um rasto documental claro para ambas as partes.',
 'hsub'=>'Confiança por conceção — para ambos os lados do negócio.',
 'brands_t'=>'As casas em stock','brands_s'=>'Um retrato em direto do inventário verificado — cada nome abaixo é atualmente fornecido por um vendedor com verificação KYC.',
 's1t'=>'Verifique-se','s1d'=>'Vendedores e compradores concluem uma verificação empresarial rápida. Os membros aprovados veem os preços de grosso em direto.',
 's2t'=>'Encontre e encomende','s2d'=>'Explore anúncios de empresas vendedoras verificadas — marcas e básicos têxteis. Encomende com condições claras de pagamento por fatura.',
 's3t'=>'Negoceie com confiança','s3d'=>'Credenciais verificadas do vendedor, faturação documentada e um processo estruturado de litígio cobrem cada negócio.',
 'jt'=>'Comece a negociar na VESTRA','js'=>'Registo gratuito. Sem compromisso. Junte-se a centenas de empresas vendedoras e compradoras verificadas.',
 'sell_title'=>"Sou vendedor",
 'sell_desc'=>'Anuncie os seus produtos de marca e têxteis. Chegue a empresas compradoras grossistas verificadas em toda a Europa.',
 'sell_f1'=>'Condições de fatura documentadas em cada encomenda',
 'sell_f2'=>'Comissão a partir de 2,8 %, mais baixa nos planos superiores',
 'sell_f3'=>'Controlo total sobre preço, MOQ e escalões',
 'sell_cta'=>'Registar como vendedor',
 'buy_title'=>"Sou comprador",
 'buy_desc'=>'Compre moda a empresas vendedoras com verificação KYC. Cada transação é documentada e protegida.',
 'buy_f1'=>'Só vendedores com verificação KYC e antecedentes confirmados',
 'buy_f2'=>'Processo estruturado de litígio em cada encomenda',
 'buy_f3'=>'Grátis para explorar e pedir cotações',
 'buy_cta'=>'Registar como comprador',
 'app_t'=>'VESTRA como aplicação','app_s'=>'Instale diretamente a partir do navegador — sem App Store nem Play Store. Preços em direto, encomendas e mensagens, com notificações push.','app_and'=>'Instalar no Android','app_and_hint'=>'No Chrome: menu ⋮ → «Instalar aplicação».','app_apk'=>'Transferir APK','app_ios'=>'Instalar no iPhone','app_ios_hint'=>'Abra no Safari → Partilhar → «Adicionar ao ecrã principal».','app_noti'=>'Ativar notificações','app_noti_ok'=>'Notificações ativas ✓','app_noti_no'=>'Notificações bloqueadas — permita-as nas definições do navegador.','app_signin'=>'Inicie sessão primeiro para receber notificações.',
 'already'=>'Já tem conta?','signin'=>'Iniciar sessão →',
],
'ru'=>[
 'tagline'=>"Проверенная B2B-оптовая торговля модой в Европе",
 'meta'=>"B2B-маркетплейс оптовой торговли брендовой одеждой и текстилем с KYC-проверкой продавцов. Каждый продавец проходит проверку. Зарегистрируйтесь бесплатно как продавец или покупатель.",
 'why'=>'Почему','how'=>'Как это работает','join_nav'=>'Начать',
 'pill'=>'Онлайн · Регистрация открыта',
 'h1'=>'<span class="acc">Проверенный</span> способ торговать<br>брендовой одеждой оптом.',
 'sub'=>'B2B-маркетплейс, где каждый продавец прошёл KYC-проверку, каждый заказ оформляется по понятным условиям оплаты по счёту, а каждая сделка документируется. Основано на проверке продавцов — а не на пустых обещаниях.',
 'b_sell'=>'Регистрация продавца','b_buy'=>'Регистрация покупателя','b_panel'=>'Открыть мой кабинет',
 'tr1'=>'Продавцы с KYC-проверкой','tr2'=>'Оплата по счёту','tr3'=>'Учёт сделок',
 'p1t'=>'Только проверенные продавцы','p1d'=>'Бизнес-KYC для каждого продавца — номер НДС, регистрация, личность. Никаких анонимных объявлений и догадок.',
 'p2t'=>'Защита покупателя','p2d'=>'Оплата по счёту с полным документальным следом. Если товар не соответствует объявлению, запускается структурированная процедура спора.',
 'p3t'=>'Целостность сделки','p3d'=>'Каждый заказ регистрируется, фиксируется по времени и привязывается к проверенному аккаунту продавца — понятный документальный след для обеих сторон.',
 'hsub'=>'Доверие по замыслу — для обеих сторон сделки.',
 'brands_t'=>'Бренды в наличии','brands_s'=>'Актуальный срез проверенного склада — каждое имя ниже сейчас поставляется через продавца, прошедшего KYC-проверку.',
 's1t'=>'Пройдите проверку','s1d'=>'Продавцы и покупатели проходят быструю проверку бизнеса. Одобренные участники видят актуальные оптовые цены.',
 's2t'=>'Выбирайте и заказывайте','s2d'=>'Просматривайте предложения проверенных компаний-продавцов — брендовые вещи и текстильный базовый ассортимент. Заказывайте по понятным условиям оплаты по счёту.',
 's3t'=>'Торгуйте уверенно','s3d'=>'Проверенные данные продавца, документированное выставление счетов и структурированная процедура спора защищают каждую сделку.',
 'jt'=>'Начните торговать на VESTRA','js'=>'Бесплатная регистрация. Без обязательств. Присоединяйтесь к сотням проверенных компаний-продавцов и покупателей.',
 'sell_title'=>"Я продавец",
 'sell_desc'=>'Размещайте брендовую одежду и текстиль. Выходите на проверенных оптовых покупателей по всей Европе.',
 'sell_f1'=>'Документированные условия оплаты по счёту в каждом заказе',
 'sell_f2'=>'Комиссия от 2,8 %, ниже на старших тарифах',
 'sell_f3'=>'Полный контроль над ценой, MOQ и ценовыми уровнями',
 'sell_cta'=>'Регистрация продавца',
 'buy_title'=>"Я покупатель",
 'buy_desc'=>'Закупайте одежду у компаний-продавцов с KYC-проверкой. Каждая сделка документируется и защищена.',
 'buy_f1'=>'Только продавцы, прошедшие KYC и проверку',
 'buy_f2'=>'Структурированная процедура спора по каждому заказу',
 'buy_f3'=>'Бесплатный просмотр и запрос цен',
 'buy_cta'=>'Регистрация покупателя',
 'app_t'=>'VESTRA как приложение','app_s'=>'Установите прямо из браузера — без App Store и Play Store. Актуальные цены, заказы и сообщения, с push-уведомлениями.','app_and'=>'Установить на Android','app_and_hint'=>'В Chrome: меню ⋮ → «Установить приложение».','app_apk'=>'Скачать APK','app_ios'=>'Установить на iPhone','app_ios_hint'=>'Откройте в Safari → Поделиться → «На экран „Домой“».','app_noti'=>'Включить уведомления','app_noti_ok'=>'Уведомления включены ✓','app_noti_no'=>'Уведомления заблокированы — разрешите их в настройках браузера.','app_signin'=>'Сначала войдите, чтобы получать уведомления.',
 'already'=>'Уже есть аккаунт?','signin'=>'Войти →',
],
/* Arabic (operator, 3 Sep 2026: "arapcada yap"). Modern Standard Arabic; the page is
   rendered right-to-left (vlang_dir), so the "forward" arrows point left. */
'ar'=>[
 'tagline'=>"سوق الجملة B2B الموثوق للأزياء في أوروبا",
 'meta'=>"سوق جملة B2B مع تحقق KYC من البائعين، للأزياء ذات العلامات التجارية والمنسوجات. كل بائع يخضع للتحقق. سجّل مجانًا كبائع أو مشترٍ.",
 'why'=>'لماذا','how'=>'كيف يعمل','join_nav'=>'ابدأ الآن',
 'pill'=>'مباشر · التسجيل مفتوح',
 'h1'=>'الطريقة <span class="acc">الموثوقة</span> لتجارة<br>الأزياء ذات العلامات التجارية بالجملة.',
 'sub'=>'سوق B2B حيث يخضع كل بائع لتحقق KYC، وتُنفَّذ كل طلبية بشروط فوترة واضحة، وتُوثَّق كل معاملة. مبني على التحقق من البائعين — لا على وعود فارغة.',
 'b_sell'=>'التسجيل كبائع','b_buy'=>'التسجيل كمشترٍ','b_panel'=>'فتح لوحتي',
 'tr1'=>'بائعون موثوقون بتحقق KYC','tr2'=>'الدفع بالفاتورة','tr3'=>'سجلات المعاملات',
 'p1t'=>'بائعون موثوقون فقط','p1d'=>'تحقق تجاري (KYC) من كل بائع — رقم الضريبة، السجل التجاري، الهوية. لا إعلانات مجهولة ولا تخمين.',
 'p2t'=>'حماية المشتري','p2d'=>'ادفع بالفاتورة مع مسار مستندي كامل. إذا لم تطابق البضاعة الإعلان، تتدخل عملية نزاع منظمة.',
 'p3t'=>'سلامة المعاملة','p3d'=>'كل طلبية تُسجَّل وتُختَم بالوقت وتُربَط بحساب البائع الموثوق — مسار مستندي واضح لطرفي الصفقة.',
 'hsub'=>'الثقة بالتصميم — لطرفي الصفقة.',
 'brands_t'=>'العلامات المتوفرة في المخزون','brands_s'=>'لقطة حية للمخزون الموثوق — كل اسم أدناه يُورَّد حاليًا عبر بائع خضع لتحقق KYC.',
 's1t'=>'احصل على التحقق','s1d'=>'يكمل البائعون والمشترون تحققًا تجاريًا سريعًا. يرى الأعضاء المعتمدون أسعار الجملة الحية.',
 's2t'=>'اختر واطلب','s2d'=>'تصفح إعلانات شركات بائعة موثوقة — علامات تجارية وأساسيات نسيجية. اطلب بشروط فوترة واضحة.',
 's3t'=>'تاجر بثقة','s3d'=>'بيانات بائع موثقة، وفوترة مسجلة، وعملية نزاع منظمة تغطي كل صفقة.',
 'jt'=>'ابدأ التجارة على VESTRA','js'=>'تسجيل مجاني. بلا التزام. انضم إلى مئات الشركات البائعة والمشترية الموثوقة.',
 'sell_title'=>"أنا بائع",
 'sell_desc'=>'اعرض منتجاتك ذات العلامات التجارية والمنسوجات. صِل إلى شركات مشترية بالجملة موثوقة في أنحاء أوروبا.',
 'sell_f1'=>'شروط فوترة موثقة في كل طلبية',
 'sell_f2'=>'عمولة تبدأ من 2.8٪، وأقل في الخطط الأعلى',
 'sell_f3'=>'تحكم كامل في السعر والحد الأدنى للطلب والشرائح',
 'sell_cta'=>'التسجيل كبائع',
 'buy_title'=>"أنا مشترٍ",
 'buy_desc'=>'اشترِ الأزياء من شركات بائعة خضعت لتحقق KYC. كل معاملة موثقة ومحمية.',
 'buy_f1'=>'بائعون خضعوا لتحقق KYC وفحص الخلفية فقط',
 'buy_f2'=>'عملية نزاع منظمة في كل طلبية',
 'buy_f3'=>'التصفح وطلب عروض الأسعار مجانًا',
 'buy_cta'=>'التسجيل كمشترٍ',
 'app_t'=>'VESTRA كتطبيق','app_s'=>'ثبّته مباشرة من المتصفح — بلا App Store ولا Play Store. أسعار حية وطلبيات ورسائل، مع إشعارات فورية.','app_and'=>'التثبيت على Android','app_and_hint'=>'في Chrome: القائمة ⋮ ← «تثبيت التطبيق».','app_apk'=>'تنزيل APK','app_ios'=>'التثبيت على iPhone','app_ios_hint'=>'افتح في Safari ← مشاركة ← «إضافة إلى الشاشة الرئيسية».','app_noti'=>'تفعيل الإشعارات','app_noti_ok'=>'الإشعارات مفعّلة ✓','app_noti_no'=>'الإشعارات محظورة — اسمح بها من إعدادات المتصفح.','app_signin'=>'سجّل الدخول أولًا لتلقي الإشعارات.',
 'already'=>'لديك حساب بالفعل؟','signin'=>'تسجيل الدخول ←',
],
];
$t = $T[$lang] ?? $T['en'];

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= vlang_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($BRAND.' — '.$t['tagline']) ?></title>
<meta name="description" content="<?= htmlspecialchars($t['meta']) ?>">
<?php
// ── SEO: canonical + multilingual hreflang + social + structured data ──
$SEO_HOST = 'https://vestrasales.com'; $OG_IMAGE = $SEO_HOST.'/inc/og-image.png';
$_hh = fn($l) => $SEO_HOST.'/'.($l === 'en' ? '' : '?lang='.$l);
$_ogloc = ['en'=>'en_US','de'=>'de_DE','fr'=>'fr_FR','it'=>'it_IT','es'=>'es_ES','pt'=>'pt_PT','ru'=>'ru_RU','ar'=>'ar_AR'][$lang] ?? 'en_US';
/* Brand names, taken from the LIVE catalogue rather than typed in. Two reasons.
   Truthfulness: the page can only ever name a house that is actually in stock, so
   the copy cannot drift into claiming a brand that was never carried. And reach:
   "Balenciaga wholesale" is what a buyer types, and it is a factual description of
   genuine EEA stock sold with an invoice trail -- nominative use, not a claim of
   authorisation or affiliation, which is why nothing here says "official" or
   "authorised dealer". Capped so the tags stay a summary rather than a keyword dump. */
/* Shared with every other page via inc/head.php — see vestra_seo_brands() in
   inc/products.php. This used to be a second copy of the same list-building here, which
   meant the homepage and the rest of the site could disagree about what we stock. */
$_brands        = function_exists('vestra_seo_brands') ? vestra_seo_brands(0) : [];
$_brandList     = implode(', ', array_slice($_brands, 0, 14));
$_wholesaleWord = function_exists('vestra_seo_wholesale_word') ? vestra_seo_wholesale_word($lang) : 'wholesale';
$_brandKw       = function_exists('vestra_seo_brand_keywords') ? vestra_seo_brand_keywords($lang, 12) : '';

// Keyword sets (category / buyer-intent terms; localized), with the stocked houses appended.
$_kw = [
 'en'=>'B2B fashion wholesale, branded fashion wholesale, authentic designer wholesale, wholesale clothing marketplace, KYC-verified suppliers, boutique wholesale supplier, multi-brand wholesale, designer clothing wholesale Europe, verified wholesale fashion, buy wholesale clothing online',
 'fr'=>'grossiste mode B2B, vente en gros de marque, grossiste vêtements de créateur authentiques, marketplace de gros, fournisseurs vérifiés KYC, grossiste multimarque, mode de créateur en gros Europe, acheter vêtements en gros',
 'it'=>'moda ingrosso B2B, ingrosso abbigliamento firmato, capi di design autentici ingrosso, marketplace ingrosso moda, fornitori verificati KYC, ingrosso multimarca, moda firmata ingrosso Europa, comprare abbigliamento ingrosso',
 'es'=>'moda al por mayor B2B, mayorista de marca, ropa de diseñador auténtica al por mayor, marketplace mayorista de moda, proveedores verificados KYC, mayorista multimarca, moda de diseñador al por mayor Europa, comprar ropa al por mayor',
 'de'=>'B2B Mode Großhandel, Marken Großhandel, authentische Designer Großhandel, Großhandel Bekleidung, KYC-verifizierte Lieferanten, Multibrand Großhandel, Designermode Großhandel Europa, Bekleidung im Großhandel kaufen',
 'pt'=>'moda B2B por grosso, grossista de moda de marca, roupa de designer autêntica por grosso, marketplace grossista de moda, fornecedores verificados KYC, grossista multimarca, moda de designer por grosso Europa, comprar roupa por grosso',
 'ru'=>'оптовая мода B2B, брендовая одежда оптом, оригинальная дизайнерская одежда оптом, оптовый маркетплейс одежды, поставщики с KYC-проверкой, мультибрендовый опт, дизайнерская одежда оптом Европа, купить одежду оптом',
 'ar'=>'أزياء بالجملة B2B, ملابس ماركات بالجملة, ملابس مصممين أصلية بالجملة, سوق جملة للأزياء, موردون موثوقون KYC, جملة متعددة العلامات, أزياء مصممين بالجملة أوروبا, شراء ملابس بالجملة',
][$lang] ?? '';
if ($_brandKw !== '') $_kw = ($_kw !== '' ? $_kw.', ' : '').$_brandKw;
/* Live categories too ("Sneaker Großhandel", "Polos en gros") -- localised, from stock. */
$_catKw = function_exists('vestra_seo_cat_keywords') ? vestra_seo_cat_keywords($lang, 12) : '';
if ($_catKw !== '') $_kw = ($_kw !== '' ? $_kw.', ' : '').$_catKw;
?>
<link rel="canonical" href="<?= htmlspecialchars($_hh($lang)) ?>">
<?php /* Base languages + regional variants, same map as inc/head.php. */
      foreach (vlang_hreflang_map() as $_hl => $_l): ?>
<link rel="alternate" hreflang="<?= $_hl ?>" href="<?= htmlspecialchars($_hh($_l)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($_hh('en')) ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<?php if ($_kw !== ''): ?><meta name="keywords" content="<?= htmlspecialchars($_kw) ?>"><?php endif; ?>
<meta property="og:site_name" content="VESTRA">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($BRAND.' — '.$t['tagline']) ?>">
<meta property="og:description" content="<?= htmlspecialchars($t['meta']) ?>">
<meta property="og:url" content="<?= htmlspecialchars($_hh($lang)) ?>">
<meta property="og:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<meta property="og:locale" content="<?= $_ogloc ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($BRAND.' — '.$t['tagline']) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($t['meta']) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<script type="application/ld+json"><?= json_encode([
  '@context'=>'https://schema.org','@type'=>'Organization','name'=>'VESTRA','url'=>$SEO_HOST,
  'logo'=>$OG_IMAGE,'email'=>$CONTACT,'areaServed'=>'EU','slogan'=>$t['tagline'],
  'description'=>'Verified B2B fashion wholesale marketplace — authentic branded apparel from KYC-verified sellers across Europe'.($_brandList !== '' ? '. Stocked houses: '.$_brandList.'.' : '.'),
  /* knowsAbout carries the stocked houses into structured data. meta keywords are
     ignored by every major engine; JSON-LD is not, and this is the field that tells
     a crawler what the business actually deals in. Same live list as the tags, so it
     can never name a brand the catalogue does not hold. */
  'knowsAbout'=>array_merge(
     ['B2B fashion wholesale','authentic branded apparel','designer clothing wholesale',
      'multi-brand boutique sourcing','textile wholesale','KYC-verified suppliers'],
     array_slice($_brands, 0, 14),
     /* … and the live categories, localised ("Sneaker", "Schuhe" on the German page). */
     function_exists('vestra_seo_knows_about') ? vestra_seo_knows_about(12) : []),
  'keywords'=>'B2B fashion wholesale, branded fashion wholesale, authentic designer wholesale, KYC-verified suppliers, multi-brand boutique wholesale, wholesale clothing marketplace Europe',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode([
  '@context'=>'https://schema.org','@type'=>'WebSite','name'=>'VESTRA','url'=>$SEO_HOST,
  'potentialAction'=>['@type'=>'SearchAction','target'=>['@type'=>'EntryPoint','urlTemplate'=>$SEO_HOST.'/shop?q={search_term_string}'],'query-input'=>'required name=search_term_string'],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0e0e11;--bg2:#15151a;--ink:#f4f1ea;--mut:#9a988f;
    --acc:<?= htmlspecialchars($ACCENT) ?>;--line:rgba(255,255,255,.08);
    /* Same house curve as inc/style.css — this page carries its own copy because it
       does not load the shared stylesheet. */
    --ease:cubic-bezier(.16,.66,.25,1);}
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:var(--bg);color:var(--ink);
    font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
    text-rendering:optimizeLegibility;overflow-x:hidden}
  a{color:inherit;text-decoration:none}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px}
  /* Tracking in em so the correction scales with the size; the display step is tighter
     than the card-heading step, which a single -.5px could never express. */
  h1,h2,h3{font-family:'Playfair Display',Georgia,serif;font-weight:700;line-height:1.12;
    letter-spacing:-.012em;text-wrap:balance}
  h1{letter-spacing:-.022em}
  ::selection{background:rgba(201,168,106,.28);color:var(--ink)}
  :where(a,button,input,select,textarea,summary,[tabindex]):focus-visible{
    outline:2px solid var(--acc);outline-offset:2px;border-radius:4px}
  .acc{color:var(--acc)}
  section{scroll-margin-top:84px}
  svg{display:block}

  header{position:sticky;top:0;z-index:30;background:rgba(14,14,17,.72);
    backdrop-filter:saturate(140%) blur(12px);border-bottom:1px solid var(--line)}
  /* The nav carries 6 links + 5 languages + sign-in + CTA; that does not fit the 1080px
     reading measure the rest of the page uses, and .wrap capped it there no matter how
     wide the window got. The header gets its own measure so the menu has somewhere to go. */
  header .wrap{max-width:1280px}
  nav{display:flex;align-items:center;justify-content:space-between;height:66px;gap:18px}
  /* The logo must never shrink or be crowded: .nav-links is white-space:nowrap, so
     without a floor of its own the menu grew until it sat flush against the wordmark
     (measured gap: 0px at every desktop width, in every language). */
  .logo{display:flex;align-items:center;gap:10px;flex:0 0 auto;font-family:'Playfair Display',serif;
    font-size:22px;font-weight:700;letter-spacing:1.5px;margin-right:clamp(1.25rem,3vw,2.5rem)}
  .logo .mark{width:30px;height:30px}
  .logo-sub{font-family:'Inter',system-ui,sans-serif;font-weight:500;font-size:.62em;letter-spacing:1.1px;
    margin-left:.22em;text-transform:lowercase;font-style:normal;
    background:linear-gradient(100deg,#e8cf95,var(--acc) 55%,#a8854a);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:var(--acc)}
  /* 16px, not 19: French is the widest of the five languages and left only ~14px of slack.
     The webfonts load after this CSS, so the measure has to survive a slightly wider
     Playfair/Inter than the fallback — the tighter gap buys that headroom. */
  .nav-links{display:flex;align-items:center;gap:16px;font-size:14px;font-weight:500;white-space:nowrap}
  .nav-links>a{color:var(--mut);transition:color .2s}
  .nav-links>a:hover{color:var(--ink)}
  .nav-cta{border:1px solid var(--line);padding:9px 18px;border-radius:999px;color:var(--ink)!important;transition:.2s}
  .nav-cta:hover{border-color:var(--acc);color:var(--acc)!important}
  /* Dil secici (acilir). inc/style.css'teki para birimi menusuyle ayni davranis;
     bu sayfa o dosyayi yuklemedigi icin kurallar burada tekrar yazili -- sayfanin
     geri kalani da ayni sebeple kendi kopyasini tasiyor. */
  .langsw{position:relative;display:inline-block}
  .langsw>summary{display:flex;align-items:center;gap:5px;cursor:pointer;
    font-size:12.5px;color:var(--mut);padding:4px 8px;border-radius:6px;
    line-height:1;list-style:none;user-select:none;transition:color .2s,background .2s}
  .langsw>summary::-webkit-details-marker{display:none}
  .langsw>summary::marker{content:''}
  .langsw>summary:hover,.langsw[open]>summary{color:var(--ink);background:rgba(255,255,255,.06)}
  .langsw>summary svg{opacity:.6;transition:transform .18s ease}
  .langsw[open]>summary svg{transform:rotate(180deg)}
  .lswcur{font-weight:600;letter-spacing:.3px}
  .lswmenu{position:absolute;top:calc(100% + 9px);right:0;z-index:60;
    padding:5px;display:grid;grid-template-columns:1fr 1fr;gap:2px;
    background:rgba(20,20,25,.98);backdrop-filter:saturate(140%) blur(14px);
    border:1px solid var(--line);border-radius:10px;
    box-shadow:0 18px 40px -12px rgba(0,0,0,.65)}
  .lswmenu a{font-size:12.5px;font-weight:600;letter-spacing:.3px;color:var(--mut);
    padding:7px 14px;border-radius:6px;line-height:1;text-align:center;white-space:nowrap;
    transition:color .2s,background .2s}
  .lswmenu a:hover{color:var(--ink);background:rgba(255,255,255,.07)}
  .lswmenu a.on{color:var(--acc);background:rgba(201,168,106,.12)}
  .burger{display:none;background:none;border:0;cursor:pointer;padding:8px;color:var(--ink)}
  .mnav{display:none;border-top:1px solid var(--line);background:rgba(14,14,17,.97);backdrop-filter:blur(12px)}
  .mnav a{display:block;padding:16px 24px;border-bottom:1px solid var(--line);color:var(--ink);font-weight:500}
  .mnav .mlangs{display:flex;gap:16px;padding:16px 24px}
  .mnav .mlangs a{border:0;padding:0;font-size:14px;color:var(--ink)}
  .mnav .mlangs a.on{color:var(--acc);font-weight:600}
  .mnav.open{display:block;animation:drop .25s ease}
  @keyframes drop{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}

  .hero{padding:96px 0 70px;text-align:center;position:relative}
  .hero:before{content:"";position:absolute;inset:-30% 0 auto 0;height:560px;z-index:-1;
    background:radial-gradient(58% 60% at 50% 0,rgba(201,168,106,.18),transparent 72%)}
  .pill{display:inline-flex;align-items:center;gap:9px;font-size:12.5px;letter-spacing:2px;
    text-transform:uppercase;color:var(--acc);border:1px solid rgba(201,168,106,.3);
    padding:7px 15px;border-radius:999px;margin-bottom:26px;background:rgba(201,168,106,.06)}
  .pill .dot{width:7px;height:7px;border-radius:50%;background:var(--acc);animation:pulse 2.4s infinite}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(201,168,106,.5)}70%{box-shadow:0 0 0 8px rgba(201,168,106,0)}100%{box-shadow:0 0 0 0 rgba(201,168,106,0)}}
  /* Hero film: each frame fades up, drifts slowly, fades out; the sequence loops.
     Total cycle = frames x 6s, and each frame's delay is staggered by 6s so exactly
     one is visible at a time. Sits behind everything (z-index 0) with the content
     lifted above it. */
  /* Give the film band a floor so a contained packshot has room to read as a garment
     rather than a stamp. Capped in vh so it never pushes the CTAs below the fold. */
  .hero.hasfilm{padding:132px 0 104px;min-height:min(80vh,720px);display:flex;align-items:center}
  .hero.hasfilm>.wrap{position:relative;z-index:2;width:100%}
  .herofilm{position:absolute;inset:0;overflow:hidden;z-index:0;pointer-events:none}

  /* ── Hero film, video cut ───────────────────────────────────────────────────
     The clip is already graded and vignetted when it is cut, so what goes over it
     here is only a scrim for type contrast -- laying the full .herofilm-veil on top
     of an already-graded clip crushed it to near-black. */
  /* Anchored to the BOTTOM, not the centre. The band is wider than the clip's 16:9,
     so `cover` has to lose some height -- centred it ate the bottom of the frame and
     sliced every garment off mid-chest at the section seam. Anchoring the bottom
     spends the crop on empty stage at the top instead, and the rail lands flush on
     the seam where it belongs. */
  .herovid-poster,.herovid{position:absolute;inset:0;width:100%;height:100%;
    object-fit:cover;object-position:center bottom;
    background-size:cover;background-position:center bottom}
  .herovid{opacity:0;transition:opacity .9s ease}
  .herovid.on{opacity:1}
  /* Type to the top, rail along the base. Centred content sat right on the plates.
     The band is also taller than the still version: with the clip anchored to its
     bottom edge the rail always claims the last ~190px, and at 80vh the trust line
     landed inside that. The extra height is what buys the two a lane each. */
  /* The bottom padding is the RAIL'S LANE, and it has to be reserved explicitly.
     The clip is bottom-anchored and covers by width, so the plates always occupy a
     fixed band above the section seam -- roughly an eighth of the viewport width --
     no matter how tall the band is. Leaving that to min-height worked at 1600x900
     and failed at 1440x780: the hero became content-driven, its bottom edge rose,
     and the plates cut straight through "Invoice-based payment · Transaction
     records". Reserving the band in vw, the same unit the rail scales in, keeps the
     two apart at every width instead of at the one that happened to be tested. */
  .hero.hasvideo{padding:48px 0 clamp(180px,17vw,275px);align-items:flex-start;
    min-height:min(88vh,820px)}
  @media(max-height:840px){ .hero.hasvideo{padding-top:32px} }
  /* The film claims only the LOWER part of the band, not all of it.
     Full-bleed, the clip's own lit stage ran up behind the two registration buttons
     and the eye went to the moving garments instead of to the thing the page is
     asking for. Handing the top third back to the plain graded stage puts the
     headline and both calls to action on quiet ground, and drops the garments
     visibly further down the page.
     The top edge is MASKED rather than cut: a hard horizontal line across a dark
     hero reads as a grey box with a straight edge -- the exact defect this hero was
     rebuilt to get rid of. The fade makes the film emerge out of the stage instead. */
  .hero.hasvideo .herofilm{top:auto;bottom:0;height:70%;
    -webkit-mask-image:linear-gradient(to bottom,transparent 0,#000 20%,#000 100%);
            mask-image:linear-gradient(to bottom,transparent 0,#000 20%,#000 100%)}
  /* Short viewports have no room to give a third away -- there the film keeps more
     of the band, or the garments fall off the bottom of the screen entirely. */
  @media(max-height:760px){ .hero.hasvideo .herofilm{height:82%} }
  /* Wide screens get the clip, so the still strip stands down. Below the breakpoint
     the two swap -- see the phone rule further down. 700px matches the width the
     loader script tests before it will fetch anything, so the picture and the
     download decision can never disagree. */
  .hero.hasvideo .herostrip{display:none}
  .herofilm-scrim{position:absolute;inset:0;
    background:
      /* Weight on the type, not on the rail. The clip already carries its own grade,
         so a heavy bottom stop here lands on the garments a SECOND time -- the pair
         of them turned the rail to mud. Bottom is deliberately barely tinted. */
      radial-gradient(64% 50% at 50% 36%,rgba(13,13,16,.74),rgba(13,13,16,.30) 74%,transparent),
      linear-gradient(to bottom,rgba(13,13,16,.62) 0%,rgba(13,13,16,.12) 34%,
                      rgba(13,13,16,.04) 66%,rgba(13,13,16,.16) 100%)}

  /* The catalogue is packshots — a garment on a studio sweep — and a packshot cannot be
     bled across a dark page: its light background arrives with it and shows as a grey
     rectangle with a straight edge, while a black polo on a near-black stage disappears
     entirely. Measured on this hero: at every point in the cycle the garment read as a
     smudge and the sweep as a box behind the headline.
     So the photography is presented as what it is — plates. Each piece sits on its own
     lit card in a strip below the call to action, where a light ground is correct and a
     hairline frame makes it look deliberate. The headline gets a clean graded stage with
     nothing behind it, so the type is crisp at every moment instead of fighting a photo
     whose brightness changes every seven seconds. */
  .herostrip{display:flex;gap:13px;justify-content:center;margin:46px auto 0;max-width:660px;
    padding:0 4px}
  .hplate{position:relative;flex:1 1 0;aspect-ratio:3/4;border-radius:14px;overflow:hidden;
    /* Near-white, because the packshot arrives with its own white sweep: a warmer plate
       showed that sweep as a second rectangle inside the first. Matching them makes the
       garment sit on the plate instead of on a card on a plate. */
    background:linear-gradient(165deg,#fbfaf8,#eeebe5);
    border:1px solid rgba(201,168,106,.30);
    box-shadow:0 18px 40px -22px rgba(0,0,0,.9), inset 0 1px 0 rgba(255,255,255,.5);
    transition:transform .5s cubic-bezier(.4,0,.2,1), box-shadow .5s}
  .hplate:hover{transform:translateY(-6px);box-shadow:0 26px 54px -22px rgba(0,0,0,.95)}
  /* `contain`: the plate is 3:4 and so is the shot, but a squarer photo must still fit
     whole rather than be cropped into an abstract sliver of fabric. */
  .hplate .hf{position:absolute;inset:0;background-size:contain;background-position:center;
    background-repeat:no-repeat;opacity:0;will-change:opacity,transform;
    animation-timing-function:cubic-bezier(.4,0,.2,1);animation-iteration-count:infinite;
    /* The plate stagger is applied as a NEGATIVE delay, which starts the animation
       already part-way through instead of holding it back: a positive offset left the
       later plates blank for their first few seconds, because the frame's own opening
       keyframe is transparent and no fill-mode can conjure a picture out of it.
       backwards still covers the first frame of the first plate, whose delay is 0. */
    animation-fill-mode:backwards}
  /* A soft sheen across the plate so five identical cards do not read as a spreadsheet. */
  .hplate:after{content:'';position:absolute;inset:0;pointer-events:none;
    background:linear-gradient(150deg,rgba(255,255,255,.30),transparent 44%),
               radial-gradient(80% 60% at 50% 108%,rgba(60,52,40,.13),transparent 70%)}

  /* Each frame owns 1/N of the cycle, so the visible window has to be written as a
     share of the whole — a fixed window would leave the column empty between frames
     on a short catalogue. The count is emitted as a class rather than sniffed out of
     the inline style, so it stays correct however many frames the catalogue yields. */
  .hfn1 .hf{animation-name:hf1} .hfn2 .hf{animation-name:hf2} .hfn3 .hf{animation-name:hf3}
  .hfn4 .hf{animation-name:hf4} .hfn5 .hf{animation-name:hf5} .hfn6 .hf{animation-name:hf6}
  /* Each frame holds its whole slot and only fades out while the next one is fading in.
     Written as separate windows the plate went blank between frames -- a frame ended at
     ~80% of its slot and the next did not start until 100%, so five white cards blinked
     empty every few seconds. The 10% overlap is what makes the strip continuous. */
  @keyframes hf1{0%,100%{opacity:1;transform:scale(1)}50%{opacity:1;transform:scale(1.05)}}
  @keyframes hf2{0%{opacity:0;transform:scale(1) translateY(5px)}5.0%{opacity:1;transform:scale(1.01)}50.0%{opacity:1}55.0%{opacity:0;transform:scale(1.06) translateY(-5px)}100%{opacity:0;transform:scale(1.06) translateY(-5px)}}
  @keyframes hf3{0%{opacity:0;transform:scale(1) translateY(5px)}3.33%{opacity:1;transform:scale(1.01)}33.33%{opacity:1}36.67%{opacity:0;transform:scale(1.06) translateY(-5px)}100%{opacity:0;transform:scale(1.06) translateY(-5px)}}
  @keyframes hf4{0%{opacity:0;transform:scale(1) translateY(5px)}2.5%{opacity:1;transform:scale(1.01)}25.0%{opacity:1}27.5%{opacity:0;transform:scale(1.06) translateY(-5px)}100%{opacity:0;transform:scale(1.06) translateY(-5px)}}
  @keyframes hf5{0%{opacity:0;transform:scale(1) translateY(5px)}2.0%{opacity:1;transform:scale(1.01)}20.0%{opacity:1}22.0%{opacity:0;transform:scale(1.06) translateY(-5px)}100%{opacity:0;transform:scale(1.06) translateY(-5px)}}
  @keyframes hf6{0%{opacity:0;transform:scale(1) translateY(5px)}1.67%{opacity:1;transform:scale(1.01)}16.67%{opacity:1}18.33%{opacity:0;transform:scale(1.06) translateY(-5px)}100%{opacity:0;transform:scale(1.06) translateY(-5px)}}

  /* The stage the type stands on: a warm key from the upper right, a cool floor at the
     lower left, and a vignette pinning the corners. Gradients rather than a photograph,
     so the headline's contrast is the same at every moment. */
  .herofilm-veil{position:absolute;inset:0;
    background:
      radial-gradient(70% 60% at 50% 34%,rgba(38,34,30,.55),rgba(14,14,17,.96) 78%),
      radial-gradient(42% 48% at 80% 8%,rgba(201,168,106,.24),transparent 72%),
      radial-gradient(48% 44% at 14% 98%,rgba(92,112,152,.14),transparent 74%),
      linear-gradient(to bottom,rgba(14,14,17,.55) 0%,rgba(14,14,17,.18) 40%,rgba(14,14,17,.92) 100%)}
  /* Fine film grain, the same trick the journal covers use: it costs one inline SVG and
     stops the large flat gradients from banding on a wide screen. */
  .herofilm-grain{position:absolute;inset:0;opacity:.055;mix-blend-mode:overlay;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2'/><feColorMatrix type='saturate' values='0'/></filter><rect width='140' height='140' filter='url(%23n)'/></svg>")}
  /* A hairline of light where the band meets the page below — the seam a graded still
     would have, and the cue that the hero is a frame rather than a background colour. */
  .hero.hasfilm:after{content:'';position:absolute;left:0;right:0;bottom:0;height:1px;z-index:1;
    background:linear-gradient(to right,transparent,rgba(201,168,106,.34),transparent)}
  /* Fewer plates as the strip narrows — five cards on a phone would be thumbnails. */
  @media(max-width:820px){ .herostrip{gap:10px;max-width:520px} .hplate:nth-child(n+4){display:none} }
  @media(max-width:520px){ .herostrip{max-width:340px} .hplate:nth-child(n+3){display:none} }
  /* Phones: the clip and its poster step aside and the plate strip comes back. A 16:9
     rail cropped to a phone's narrow band is one plate and a lot of empty stage, and
     it landed behind the call to action. The scrim and grain stay -- they are the
     hero's grade, not part of the film. */
  @media(max-width:700px){
    .hero.hasvideo .herovid,.hero.hasvideo .herovid-poster{display:none}
    .hero.hasvideo .herostrip{display:flex}
    .hero.hasvideo{align-items:center;padding:76px 0 56px;min-height:auto}
  }
  @media(prefers-reduced-motion:reduce){
    .hplate .hf{animation:none;opacity:0;transform:none}
    .hplate .hf:first-child{opacity:1}
    /* the loader already declines to fetch the clip here; this is the belt to that
       brace, so a cached <video> cannot fade itself in either */
    .herovid{display:none}
  }
  .hero h1{font-size:clamp(34px,6.2vw,62px);margin:0 0 20px}
  .hero>.wrap>p{font-size:clamp(16px,2.4vw,20px);color:var(--mut);max-width:630px;margin:0 auto 36px}
  .btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
  .btn{padding:15px 30px;border-radius:999px;font-weight:600;font-size:15px;cursor:pointer;
    border:1px solid var(--acc);transition:.2s;display:inline-flex;align-items:center;gap:9px}
  .btn-p{background:var(--acc);color:#1a1408}
  .btn-p:hover{filter:brightness(1.08);transform:translateY(-2px);box-shadow:0 10px 30px -10px rgba(201,168,106,.5)}
  .btn-o{background:transparent;color:var(--ink)}
  .btn-o:hover{background:rgba(201,168,106,.1);transform:translateY(-2px)}
  /* The two registration buttons are what the homepage is FOR, so on the hero they
     are given a size of their own rather than the sitewide one. The outline button
     also stops being fully transparent: over the film it was reading as a hairline
     ring and the "buy" half of the marketplace looked like an afterthought next to
     the filled "sell" half. A dark, slightly blurred fill keeps it clearly secondary
     while making it a button rather than an outline. */
  .hero .btns{gap:16px}
  .hero .btns .btn{padding:17px 34px;font-size:16px}
  .hero .btn-p{box-shadow:0 14px 34px -14px rgba(201,168,106,.55)}
  .hero .btn-o{background:rgba(13,13,16,.55);backdrop-filter:blur(3px);
    border-color:rgba(201,168,106,.75)}
  .hero .btn-o:hover{background:rgba(201,168,106,.16)}
  .btn:active{transform:scale(.96)}
  .trustline{margin-top:34px;font-size:13px;color:var(--mut);display:flex;gap:22px;justify-content:center;flex-wrap:wrap}
  .trustline span{display:inline-flex;align-items:center;gap:7px}

  .pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:84px 0}
  .card{background:linear-gradient(180deg,var(--bg2),#101015);border:1px solid var(--line);
    border-radius:18px;padding:30px;transition:.3s}
  .card:hover{border-color:rgba(201,168,106,.35);transform:translateY(-4px)}
  .card .ic{width:50px;height:50px;display:grid;place-items:center;border-radius:13px;
    background:rgba(201,168,106,.1);color:var(--acc);margin-bottom:18px}
  .card .ic svg{width:26px;height:26px}
  .card h3{font-size:19px;margin:0 0 8px}
  .card p{color:var(--mut);font-size:15px;margin:0}

  /* ── Brand wall — every house currently live in the catalogue ──
     auto-fit stretched each cell to fill the row, so a short brand list rendered as
     a handful of enormous boxes around a 150px logo (~55% dead space). auto-fill with
     a max column width keeps the cell close to the wordmark it holds, and the grid
     centres so a partial last row doesn't read as a broken table. */
  .catstrip{padding:56px 0 8px}
  .cs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:26px}
  .cs-cell{display:flex;flex-direction:column;gap:4px;padding:14px 16px;border:1px solid var(--line);border-radius:12px;background:var(--bg2);transition:border-color .2s var(--ease)}
  .cs-cell:hover{border-color:var(--acc)}
  .cs-coll{border-color:rgba(201,168,106,.45)}
  .cs-name{font-weight:600;font-size:14px}
  .cs-n{font-size:12px;color:var(--mut)}
  .shoe-cats{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:18px}
  .shoe-cats a{border:1px solid var(--line);border-radius:999px;padding:6px 14px;font-size:13px;color:var(--mut);transition:border-color .2s var(--ease),color .2s var(--ease)}
  .shoe-cats a:hover{color:var(--ink);border-color:var(--acc)}
  .brandwall{background:linear-gradient(180deg,var(--bg2),var(--bg));
    border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:76px 0 80px}
  .bw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    max-width:960px;margin:0 auto;justify-content:center;
    border-top:1px solid var(--line);border-left:1px solid var(--line)}
  .bw-cell{border-right:1px solid var(--line);border-bottom:1px solid var(--line);
    display:flex;align-items:center;justify-content:center;aspect-ratio:16/9;min-height:104px;
    padding:22px 18px;text-decoration:none;position:relative;
    transition:background .35s var(--ease)}
  .bw-cell:hover{background:rgba(201,168,106,.07)}
  /* Gold hairline draws in from the centre on hover — the one flourish on this module. */
  .bw-cell::after{content:'';position:absolute;left:50%;right:50%;bottom:-1px;height:1px;
    background:var(--acc);opacity:0;transition:left .45s var(--ease),right .45s var(--ease),opacity .45s var(--ease)}
  .bw-cell:hover::after{left:0;right:0;opacity:.75}
  .bw-cell .brand-logo{width:100%;max-width:132px;height:auto;opacity:.72;
    filter:drop-shadow(0 1px 8px rgba(0,0,0,.5));
    transition:opacity .35s var(--ease),transform .35s var(--ease)}
  .bw-cell:hover .brand-logo{opacity:1;transform:translateY(-2px)}
  /* Monogram fallback for any house with no drawn wordmark. The base rules live in
     inc/style.css, which this page does not load — without them the mark rendered in
     body Inter, lowercase and with no rule under it. Restated here. */
  .bw-cell .bmono{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;
    gap:5px;padding:0 12px;text-align:center}
  .bw-cell .bmono-mark{font-family:'Playfair Display',serif;font-weight:700;line-height:1;
    color:#fff;font-size:26px;letter-spacing:.1em;text-shadow:0 2px 16px rgba(0,0,0,.62)}
  .bw-cell .bmono-name{font-family:'Inter',Arial,sans-serif;font-size:8.5px;font-weight:600;
    letter-spacing:.24em;text-transform:uppercase;color:rgba(255,255,255,.66);
    padding-top:6px;border-top:1px solid rgba(255,255,255,.24);max-width:100%;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

  .sec-title{text-align:center;margin:0 0 8px;font-size:clamp(26px,4vw,38px)}
  .sec-sub{text-align:center;color:var(--mut);margin:0 auto 46px;max-width:520px}
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:26px}
  .step .n{font-family:'Playfair Display',serif;font-size:42px;color:var(--acc);opacity:.45;line-height:1}
  .step h3{font-size:18px;margin:10px 0 6px}
  .step p{color:var(--mut);font-size:15px;margin:0}

  /* ── Registration cards ── */
  .join{margin:90px auto 80px;max-width:960px;text-align:center}
  .join-cards{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:40px;text-align:left}
  .jcard{background:linear-gradient(160deg,var(--bg2),#101015);border:1px solid var(--line);
    border-radius:22px;padding:36px;transition:.3s;display:flex;flex-direction:column;gap:20px;position:relative;overflow:hidden}
  .jcard:before{content:"";position:absolute;inset:0;border-radius:22px;opacity:0;transition:.3s;
    pointer-events:none;
    background:radial-gradient(60% 50% at 50% 0,rgba(201,168,106,.07),transparent 70%)}
  .jcard:hover:before{opacity:1}
  .jcard:hover{border-color:rgba(201,168,106,.4);transform:translateY(-4px);box-shadow:0 20px 50px -20px rgba(0,0,0,.5)}
  .jcard-icon{width:54px;height:54px;display:grid;place-items:center;border-radius:15px;
    background:rgba(201,168,106,.12);color:var(--acc)}
  .jcard-icon svg{width:28px;height:28px}
  .jcard h3{font-size:24px;margin:0}
  .jcard .jdesc{color:var(--mut);font-size:15px;margin:0;line-height:1.6}
  .jcard .jfeats{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:9px}
  .jcard .jfeats li{display:flex;align-items:center;gap:10px;font-size:14px;color:var(--mut)}
  .jcard .jfeats li:before{content:"✓";color:var(--acc);font-weight:700;flex:none;font-size:15px}
  .jcard .jbtn{margin-top:auto;display:inline-flex;align-items:center;justify-content:center;
    gap:9px;padding:15px 28px;border-radius:999px;font-weight:600;font-size:15px;border:1px solid var(--acc);
    transition:.2s;text-decoration:none;width:100%}
  .jcard.seller .jbtn{background:var(--acc);color:#1a1408}
  .jcard.seller .jbtn:hover{filter:brightness(1.08);transform:translateY(-2px);box-shadow:0 10px 28px -8px rgba(201,168,106,.5)}
  .jcard.buyer .jbtn{background:transparent;color:var(--ink)}
  .jcard.buyer .jbtn:hover{background:rgba(201,168,106,.12);transform:translateY(-2px)}
  .join-note{margin-top:22px;font-size:14px;color:var(--mut)}
  .join-note a{color:var(--acc);font-weight:500}
  /* "VESTRA as an app" — store-independent install box (PWA + push) */
  .appbox{position:relative;display:flex;gap:22px;align-items:flex-start;
    width:min(760px,calc(100% - 40px));margin:6px auto 64px;padding:26px 28px;
    border-radius:20px;border:1px solid rgba(201,168,106,.35);overflow:hidden;
    background:linear-gradient(160deg,rgba(201,168,106,.10),rgba(20,20,24,.92) 55%)}
  .appbox-glow{position:absolute;top:-70px;left:-50px;width:240px;height:240px;border-radius:50%;
    background:radial-gradient(circle,rgba(201,168,106,.22),transparent 70%);pointer-events:none}
  .appbox-icon{flex:none;width:64px;height:64px;border-radius:16px;background:#101014;
    border:1px solid var(--line);display:grid;place-items:center;position:relative}
  .appbox-tx h3{margin:0 0 6px;font-size:20px;font-family:'Playfair Display',serif}
  .appbox-tx p{margin:0 0 14px;color:var(--mut);font-size:14px;line-height:1.6;max-width:480px}
  .appbox-btns{display:flex;gap:10px;flex-wrap:wrap}
  .appbox-hint{margin-top:12px;font-size:13px;color:var(--acc);background:rgba(201,168,106,.08);
    border:1px solid rgba(201,168,106,.25);border-radius:10px;padding:9px 13px;display:inline-block}
  @media(max-width:640px){
    .appbox{flex-direction:column;align-items:stretch;width:calc(100% - 28px);padding:22px 18px;gap:14px;margin-bottom:44px}
    .appbox-icon{width:56px;height:56px}
    .appbox-btns{flex-direction:column}
    .appbox-btns .btn{width:100%;justify-content:center}
  }
  .join-note a:hover{text-decoration:underline}

  footer{border-top:1px solid var(--line);padding:46px 0;color:var(--mut);font-size:13px}
  .foot{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:center}
  .foot-links{display:flex;gap:22px}
  .foot-links a:hover{color:var(--ink)}

  /* Scroll-reveal is progressive enhancement ONLY: the keyframe fallback forces every
     section visible ~1s after load even if ALL JavaScript fails (old browser, blocked
     storage, extension error) — the register cards must never stay hidden. */
  .reveal{opacity:0;transform:translateY(20px);transition:opacity .7s ease,transform .7s ease;
    animation:revealauto .7s ease 1.1s forwards}
  .reveal.in{animation:none;opacity:1;transform:none}
  @keyframes revealauto{to{opacity:1;transform:none}}
  @media(prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none;animation:none}.dot{animation:none}}

  /* 1280, not 1024: the full menu genuinely needs ~1266px in French (the longest of the
     five languages). Below that it used to overflow and jam against the logo instead of
     collapsing, so the drawer — which carries every item — takes over earlier. */
  @media(max-width:1280px){.nav-links{display:none}.burger{display:block}}
  @media(max-width:760px){
    .pillars,.steps{grid-template-columns:1fr}
    .join-cards{grid-template-columns:1fr}
    .hero{padding:64px 0 44px}
    /* On a phone the band is nearly square, so the contained packshot already fills
       most of the width -- it needs less height here than on a wide desktop, and the
       headline has to stay above the fold. */
    .hero.hasfilm{padding:76px 0 56px;min-height:auto}
    /* The clip is a wide 16:9 rail. Cropped to a phone's near-square band it becomes
       two plates and a lot of empty stage, so the phone keeps the still frame and the
       loader never fetches the megabytes over cellular. */
    .hero.hasvideo{padding:76px 0 64px;min-height:56vh}
    .trustline{gap:14px}
    .brandwall{padding:56px 0 60px}
    .bw-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr));max-width:none}
    .bw-cell{min-height:84px;padding:18px 12px}
  }
</style>
</head>
<body>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="/?lang=<?= $lang ?>" aria-label="<?= htmlspecialchars($BRAND) ?>">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span><?= htmlspecialchars($BRAND) ?><span class="logo-sub">sales</span></span>
    </a>
    <div class="nav-links">
      <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog','pt'=>'Catálogo','ru'=>'Каталог','ar'=>'الكتالوج'][$lang] ?? 'Catalog' ?></a>
      <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen','pt'=>'Pedidos','ru'=>'Запросы','ar'=>'الطلبات'][$lang] ?? 'Requests' ?></a>
      <a href="/faq">FAQ</a>
      <?php /* short label here, not $t['brands_t'] — that is a full section heading
               ("Die Marken im Bestand", "Las maisons disponibles") and as a nav item it
               alone pushed the menu ~120px wider than the header could hold. */ ?>
      <?php if ($_brands): ?><a href="#brands"><?= ['en'=>'Brands','fr'=>'Marques','it'=>'Marchi','es'=>'Marcas','de'=>'Marken','pt'=>'Marcas','ru'=>'Бренды','ar'=>'العلامات التجارية'][$lang] ?? 'Brands' ?></a><?php endif; ?>
      <a href="#how"><?= $t['how'] ?></a>
      <?php /* brand name dropped from this label only (the drawer and footer keep it) —
               redundant three centimetres from the wordmark, and it cost ~65px of header
               width in every language. */ ?>
      <a href="#why"><?= $t['why'] ?></a>
      <?php /* Sekiz dil kodunu nokta ayracla yan yana basmak ust cubukta ~150px
               yiyordu ve dil eklendikce buyuyordu. Artik kapaliyken tek kod +
               ok, tiklaninca aciliyor. Isaretleme inc/i18n.php'deki ORTAK
               bileseneden geliyor (vlang_switcher): bu sayfa kendi kopyasini
               tasiyordu ve iki kopya er gec ayrisirdi -- nitekim ayrismisti,
               buradaki baglantilar sorgu parametrelerini korumuyordu. */ ?>
      <?= vlang_switcher() ?>
      <?php if(!$LOGGED): ?><a href="/login" class="nav-signin"><?= ['en'=>'Sign in','fr'=>'Se connecter','it'=>'Accedi','es'=>'Iniciar sesión','de'=>'Anmelden','pt'=>'Iniciar sessão','ru'=>'Войти','ar'=>'تسجيل الدخول'][$lang] ?? 'Sign in' ?></a><?php endif; ?>
      <a href="<?= $LOGGED ? $panelHref : '/register' ?>" class="nav-cta"><?= $LOGGED ? $t['b_panel'] : $t['join_nav'] ?></a>
    </div>
    <button class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="mnav">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
  </nav></div>
  <div class="mnav" id="mnav">
    <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog','pt'=>'Catálogo','ru'=>'Каталог','ar'=>'الكتالوج'][$lang] ?? 'Catalog' ?></a>
    <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen','pt'=>'Pedidos','ru'=>'Запросы','ar'=>'الطلبات'][$lang] ?? 'Requests' ?></a>
    <a href="/faq">FAQ</a>
    <?php if ($_brands): ?><a href="#brands"><?= ['en'=>'Brands','fr'=>'Marques','it'=>'Marchi','es'=>'Marcas','de'=>'Marken','pt'=>'Marcas','ru'=>'Бренды','ar'=>'العلامات التجارية'][$lang] ?? 'Brands' ?></a><?php endif; ?>
    <a href="#how"><?= $t['how'] ?></a>
    <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
    <?php if(!$LOGGED): ?><a href="/login"><?= ['en'=>'Sign in','fr'=>'Se connecter','it'=>'Accedi','es'=>'Iniciar sesión','de'=>'Anmelden','pt'=>'Iniciar sessão','ru'=>'Войти','ar'=>'تسجيل الدخول'][$lang] ?? 'Sign in' ?></a><?php endif; ?>
    <a href="<?= $LOGGED ? $panelHref : '/register' ?>"><?= $LOGGED ? $t['b_panel'] : $t['join_nav'] ?></a>
    <?php /* Cekmecede DUZ: acilir menu burada bir sey kazandirmaz, cekmece
             zaten tam ekran bir katman. */ ?>
    <?= vlang_switcher('mlangs','flat') ?>
  </div>
</header>

<span id="top"></span>
<section class="hero<?= ($HERO_FRAMES || $HERO_VIDEO) ? ' hasfilm' : '' ?><?= $HERO_VIDEO ? ' hasvideo' : '' ?>">
  <?php if($HERO_VIDEO): ?>
  <div class="herofilm" aria-hidden="true">
    <?php /* The poster is painted as a background rather than left to the <video>
             poster attribute alone: it is up on the first paint, so the band is never
             a black hole while the clip is still arriving -- and on the devices below
             that deliberately never fetch the clip, it IS the hero.
             preload="none" + a data-src the script promotes: a phone on cellular, a
             visitor who asked for less motion, and a narrow screen all get the still
             and never spend the megabytes. */ ?>
    <div class="herovid-poster"<?= $HERO_VIDEO['poster'] ? ' style="background-image:url('.htmlspecialchars($HERO_VIDEO['poster']).')"' : '' ?>></div>
    <video class="herovid" muted loop playsinline preload="none" tabindex="-1"
           disablepictureinpicture disableremoteplayback
           <?= $HERO_VIDEO['poster'] ? 'poster="'.htmlspecialchars($HERO_VIDEO['poster']).'"' : '' ?>
           <?= $HERO_VIDEO['webm'] ? 'data-webm="'.htmlspecialchars($HERO_VIDEO['webm']).'"' : '' ?>
           data-mp4="<?= htmlspecialchars($HERO_VIDEO['mp4']) ?>"></video>
    <div class="herofilm-scrim"></div>
    <div class="herofilm-grain"></div>
  </div>
  <?php elseif($HERO_FRAMES): ?>
  <div class="herofilm" aria-hidden="true">
    <div class="herofilm-veil"></div>
    <div class="herofilm-grain"></div>
  </div>
  <?php endif; ?>
  <div class="wrap">
    <div class="pill"><span class="dot"></span> <?= $t['pill'] ?></div>
    <h1><?= $t['h1'] ?></h1>
    <p><?= $t['sub'] ?></p>
    <div class="btns">
      <?php if($LOGGED): ?>
      <a class="btn btn-p" href="<?= $panelHref ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        <?= $t['b_panel'] ?>
      </a>
      <?php else: ?>
      <?php /* Dolu (sari) dugme ALICIDA, cerceveli olan saticida. Ikisi de ayni
               yere goturuyor, fark hangisinin once denendigi: dolu dugme sayfanin
               birincil eylemi olarak okunuyor. */ ?>
      <a class="btn btn-o" href="/register?type=seller">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h13l5 5v5h-2"/><path d="M3 7v10h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
        <?= $t['b_sell'] ?>
      </a>
      <a class="btn btn-p" href="/register?type=buyer">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1 12H6L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        <?= $t['b_buy'] ?>
      </a>
      <?php endif; ?>
    </div>
    <div class="trustline">
      <?php foreach(['tr1','tr2','tr3'] as $k){ ?>
      <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= $t[$k] ?></span>
      <?php } ?>
    </div>
    <?php if($HERO_FRAMES):
      /* Five plates, each cycling through its own share of the running order. Frames are
         dealt round-robin so neighbouring plates never hold the same piece, and each
         plate is offset in time so the strip never changes all at once.
         Printed even when the clip exists, and hidden by CSS on wide screens: which of
         the two the visitor gets is a question about the VIEWPORT, and PHP cannot see
         one. Cropping a wide rail to a phone left a single plate wedged behind the call
         to action -- worse than the strip a phone gets today -- so the phone keeps the
         strip and the clip is hidden there instead. */
      $HP = 5; $slot = 7;
      $plates = array_fill(0, $HP, []);
      foreach ($HERO_FRAMES as $i => $f) $plates[$i % $HP][] = $f;
      $plates = array_values(array_filter($plates));
    ?>
    <div class="herostrip" aria-hidden="true">
      <?php foreach ($plates as $pi => $frames): $n = max(1, min(6, count($frames))); ?>
        <div class="hplate hfn<?= $n ?>">
          <?php foreach (array_slice($frames, 0, 6) as $i => $f): ?>
            <div class="hf" style="background-image:url('<?= htmlspecialchars($f) ?>');
              animation-duration:<?= $n * $slot ?>s;
              animation-delay:<?= round($i * $slot - $pi * 1.3, 1) ?>s"></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
/* ── Yakinda geliyor / Coming soon ─────────────────────────────────────────
   Iceriyi DOSYALAR belirler, kod degil. uploads/coming-soon/ altindaki HER
   ALT KLASOR bir marka sekmesi, icindeki her gorsel bir karttir. Yeni parti
   geldiginde klasor acilir; klasor bosalinca sekme kendini cizmez, hepsi
   bosalinca bolum hic basilmaz.

   Dosya adi rozeti verir: "M7535__03.jpg" -> M7535 (cift alt tireden oncesi).
   Ayni modelin renk varyantlari boylece tek koda toplanir, dosya adi da
   benzersiz kalir. Alt tire yoksa tum ad kullanilir (gdt01.jpg -> GDT01).

   Her klasorde istege bagli _meta.txt: 1. satir marka adi, 2. satir alt
   baslik, 3. satir siralama agirligi (kucuk once, varsayilan 50). Yoksa ad
   klasor adindan uretilir -- yani yeni marka eklemek icin PHP'ye dokunmak
   gerekmiyor.

   UC MARKA TEK BOLUMDE: sekmeler saf CSS (gizli radio + :checked ~ ...), JS
   yok. Alt alta uc serit koymak bolumu uc katina cikariyordu; sekme yuksekligi
   sabit tutuyor -- "beyaz bolumler cok uzun olmasin".

   Kartlar bilerek TIKLANMAZ: urunler henuz katalogda yok, olmayan bir sayfaya
   baglanti 404'ten farksiz. Tek eylem, kapinin zaten actigi sey -- misafir
   kayda, uye paneline (vestra_join_cta karar veriyor).
   Konum: hero'nun hemen ardi. Urun gecisleri (hero film + serit) oldugu yerde,
   bu bolumun ustunde donmeye devam ediyor. */
$soonDir    = __DIR__.'/uploads/coming-soon';
$soonBrands = [];
foreach (glob($soonDir.'/*', GLOB_ONLYDIR) ?: [] as $bdir) {
    $imgs = glob($bdir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    if (!$imgs) continue;
    natcasesort($imgs); $imgs = array_values($imgs);
    $slug = basename($bdir);
    $name = ucwords(str_replace('-', ' ', $slug));
    $sub  = ''; $ord = 50;
    if (is_file($bdir.'/_meta.txt')) {
        $ln = array_map('trim', explode("\n", (string)@file_get_contents($bdir.'/_meta.txt')));
        if (($ln[0] ?? '') !== '') $name = $ln[0];
        if (($ln[1] ?? '') !== '') $sub  = $ln[1];
        if (isset($ln[2]) && is_numeric($ln[2])) $ord = (int)$ln[2];
    }
    /* CSS secicisine ve id'ye giriyor: harf/rakam/tire disini eleyip basa harf
       zorluyoruz, boylece klasor adi ne olursa olsun gecerli bir seci uretilir. */
    $css = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
    $css = trim($css, '-'); if ($css === '' || !ctype_alpha($css[0])) $css = 'b'.$css;
    $soonBrands[] = ['css' => $css, 'name' => $name, 'sub' => $sub, 'ord' => $ord,
                     'dir' => $slug, 'imgs' => $imgs];
}
/* Eski duzen: dogrudan coming-soon/ altina birakilmis gevsek dosyalar. Bir
   klasore tasindilar, ama biri elle buraya birakirsa gorsel kaybolmasin. */
$loose = glob($soonDir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
if ($loose) {
    natcasesort($loose);
    $soonBrands[] = ['css' => 'more', 'name' => t('New arrivals'), 'sub' => '', 'ord' => 90,
                     'dir' => '', 'imgs' => array_values($loose)];
}
usort($soonBrands, fn($a, $b) => [$a['ord'], $a['name']] <=> [$b['ord'], $b['name']]);
if ($soonBrands):
?>
<style>
.soon{padding:56px 0 48px;background:
  radial-gradient(900px 340px at 50% -80px, rgba(201,168,106,.07), transparent 70%)}
.soon .wrap{max-width:1180px}
.soon-kick{display:flex;align-items:center;gap:14px;justify-content:center;margin-bottom:12px}
.soon-kick .ln{height:1px;width:min(120px,18vw);background:linear-gradient(90deg,transparent,rgba(201,168,106,.55));}
.soon-kick .ln:last-child{transform:scaleX(-1)}
.soon-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(201,168,106,.45);
  color:var(--acc);font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
  padding:7px 16px;border-radius:999px;white-space:nowrap}
.soon-pill .dot{width:6px;height:6px;border-radius:50%;background:var(--acc);
  animation:soonPulse 2.2s ease-in-out infinite}
@keyframes soonPulse{0%,100%{opacity:.35;transform:scale(.8)}50%{opacity:1;transform:scale(1)}}
@media (prefers-reduced-motion:reduce){.soon-pill .dot{animation:none}}
.soon h2.sec-title{margin-bottom:6px}
/* Sekmeler. Radio'lar ekranda yok ama KLAVYEDE var: gizlemek icin display:none
   kullanilsaydi sekmeler sekme tusuyla gezilemezdi. */
.soon-radio{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.soon-tabs{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin:18px 0 4px}
.soon-tabs label{cursor:pointer;font-size:12px;font-weight:600;letter-spacing:.1em;
  text-transform:uppercase;color:var(--mut);padding:8px 16px;border-radius:999px;
  border:1px solid rgba(201,168,106,.22);transition:color .25s,border-color .25s,background .25s}
.soon-tabs label:hover{color:var(--ink,#f3ede1);border-color:rgba(201,168,106,.5)}
.soon-panel{display:none}
.soon-strip{display:flex;gap:16px;overflow-x:auto;padding:8px 4px 14px;margin-top:20px;
  scroll-snap-type:x mandatory;scrollbar-width:none;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent);
          mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}
.soon-strip::-webkit-scrollbar{display:none}
/* Kart zemini BEYAZ, cunku urun fotograflarinin zemini beyaz: kremrengi bir
   kartta her fotograf gorunur bir beyaz dikdortgen olarak duruyordu. */
.soon-card{flex:0 0 clamp(168px,20vw,224px);scroll-snap-align:start;border-radius:16px;
  background:#fff;position:relative;overflow:hidden;
  box-shadow:0 1px 0 rgba(255,255,255,.05), 0 14px 34px -22px rgba(0,0,0,.65)}
.soon-card::after{content:"";position:absolute;inset:0;border-radius:inherit;
  box-shadow:inset 0 0 0 1px rgba(26,20,8,.06)}
/* height:auto SART. <img> etiketindeki height="900" bir "presentational hint"
   olarak height:900px veriyor; genislik de %100 oldugu icin IKI olcu de kesin
   hale geliyor ve aspect-ratio yok sayiliyor -- kartlar 280px yerine 900px
   yukseklikte ciziliyordu. Bu iki oznitelik yine de duruyor: yuklenmeden once
   yer ayirip sayfanin zipla masini onluyorlar. */
.soon-card img{display:block;width:100%;height:auto;aspect-ratio:4/5;object-fit:contain;
  padding:10px 8px 30px;transition:transform .45s cubic-bezier(.2,.7,.2,1)}
.soon-card:hover img{transform:scale(1.045)}
.soon-tag{position:absolute;left:12px;bottom:10px;font-size:10.5px;font-weight:700;
  letter-spacing:.18em;color:#6f6a61}
.soon-sub{text-align:center;max-width:600px;margin:0 auto;font-size:13.5px;color:var(--mut);min-height:1.4em}
.soon-foot{display:flex;gap:10px 18px;align-items:center;justify-content:center;flex-wrap:wrap;margin-top:14px}
.soon-foot .hint{font-size:12.5px;color:var(--mut);letter-spacing:.02em}
@media (max-width:640px){.soon{padding:44px 0 38px}.soon-tabs label{padding:7px 12px;font-size:11px}}
<?php foreach ($soonBrands as $b): $c = $b['css']; ?>
#st-<?= $c ?>:checked ~ .soon-tabs label[for="st-<?= $c ?>"]{color:#14110c;background:var(--acc);
  border-color:var(--acc)}
#st-<?= $c ?>:focus-visible ~ .soon-tabs label[for="st-<?= $c ?>"]{outline:2px solid var(--acc);outline-offset:3px}
#st-<?= $c ?>:checked ~ .soon-panels .sp-<?= $c ?>{display:block}
<?php endforeach; ?>
</style>
<section class="soon reveal" id="coming-soon">
  <div class="wrap">
    <div class="soon-kick"><span class="ln"></span>
      <span class="soon-pill"><span class="dot"></span><?= t('Coming soon') ?></span>
    <span class="ln"></span></div>
    <h2 class="sec-title" style="text-align:center"><?= t('Winter 26/27') ?></h2>
    <p class="soon-sub" style="margin-bottom:2px">
      <?= t('New houses landing at VESTRA. Preview the drop below; ordering opens shortly.') ?>
    </p>
    <?php /* Radio'lar sekmelerden ve panellerden ONCE gelmeli: CSS'in "~" secicisi
             sadece SONRAKI kardeslere ulasir. */
    foreach ($soonBrands as $i => $b): ?>
    <input class="soon-radio" type="radio" name="soontab" id="st-<?= $b['css'] ?>"<?= $i === 0 ? ' checked' : '' ?>>
    <?php endforeach; ?>
    <div class="soon-tabs" role="tablist">
      <?php foreach ($soonBrands as $b): ?>
      <label for="st-<?= $b['css'] ?>"><?= htmlspecialchars($b['name']) ?></label>
      <?php endforeach; ?>
    </div>
    <div class="soon-panels">
      <?php foreach ($soonBrands as $b): $base = '/uploads/coming-soon'.($b['dir'] !== '' ? '/'.rawurlencode($b['dir']) : ''); ?>
      <div class="soon-panel sp-<?= $b['css'] ?>">
        <?php if ($b['sub'] !== ''): ?>
        <p class="soon-sub"><?= htmlspecialchars($b['sub']) ?></p>
        <?php endif; ?>
        <div class="soon-strip">
          <?php foreach ($b['imgs'] as $si):
            /* Rozet = cift alt tireden onceki kisim; ayni modelin renkleri tek koda
               toplansin. Bastaki "01-" sadece siralama icin, rozete girmiyor. Kod
               bilinmiyorsa dosya "05-__01.jpg" gibi adlandirilir: rozet bos kalir ve
               basilmaz -- olmayan bir model numarasi uydurmaktansa hic yazmamak. */
            $tag = strtoupper(explode('__', pathinfo($si, PATHINFO_FILENAME))[0]);
            $tag = preg_replace('/^\d+[-_]/', '', $tag); ?>
          <figure class="soon-card" style="margin:0">
            <img src="<?= $base ?>/<?= rawurlencode(basename($si)) ?>" alt="<?= htmlspecialchars(trim($b['name'].' '.$tag)) ?>" loading="lazy" width="720" height="900">
            <?php if ($tag !== ''): ?><figcaption class="soon-tag"><?= htmlspecialchars($tag) ?></figcaption><?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="soon-foot">
      <?= vestra_join_cta(t('Register for first access'), 'btn btn-o') ?>
      <span class="hint">🇪🇺 <?= t('Full size series · ships from the EU') ?></span>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* ── Ayakkabi bolmesi ──────────────────────────────────────────────────────
   Operator karari, 3 Eyl 2026: "ayakkabilari ana sayfaya al yeni gelen
   urunlerin altina ... ana sayfadan da direkt girilebilsin, tiklandiginda".
   Konum bilerek "Coming soon"un HEMEN ALTI.

   Ustteki bolumden farki: bu urunler katalogda GERCEKTEN var, o yuzden kartlar
   TIKLANIR ve dogrudan urun sayfasina gider (Coming soon kartlari bilerek
   tiklanmiyor, cunku hedef sayfa yok). Icerik canli ilanlardan okunuyor --
   elle bir liste tutulsaydi satilan/duzenlenen ilan ana sayfada olu baglanti
   olarak kalirdi.

   FIYAT BASILMIYOR: toptan fiyat dogrulanmis alici kapisinin arkasinda
   (auth_prices_unlocked) ve ana sayfa herkese acik.

   Kendi CSS'i var, ustteki .soon-* siniflarini KULLANMIYOR: o stil blogu
   yalnizca uploads/coming-soon dolu oldugunda basiliyor, klasor bosalsa bu
   bolum stilsiz kalirdi. */
$shoePicks = []; $shoeTotal = 0;
if (function_exists('vestra_products') && function_exists('vestra_product_section')) {
    $shoeByCat = [];
    foreach (vestra_products() as $sp) {
        if (vestra_product_section($sp) !== 'footwear') continue;
        $shoeTotal++;
        /* Taban fotografi ("SUELA") vitrin karti olmaz -- ayni olcut fotograflari
           siniflandirirken de kullanildi. Bulunamazsa ilk gorsele duser. */
        $simg = '';
        foreach ((array)($sp['images'] ?? []) as $si) {
            if (!is_string($si) || $si === '') continue;
            if ($simg === '') $simg = $si;
            if (stripos(basename($si), 'suela') === false) { $simg = $si; break; }
        }
        if ($simg === '' || ($sp['id'] ?? '') === '') continue;
        $shoeByCat[(string)($sp['cat'] ?? 'Other')][] = [
            'id'  => (string)$sp['id'],
            'name'=> (string)($sp['name'] ?? ''),
            'cat' => (string)($sp['cat'] ?? ''),
            'img' => $simg,
        ];
    }
    /* Kategoriler arasinda SIRAYLA: dosya sirasindan 12 tane almak 12 spor
       ayakkabi demekti; serit bolmenin genisligini gostermeli. */
    ksort($shoeByCat);
    for ($rnd = 0; count($shoePicks) < 12; $rnd++) {
        $addedRound = false;
        foreach ($shoeByCat as $catItems) {
            if (!isset($catItems[$rnd])) continue;
            $shoePicks[] = $catItems[$rnd];
            $addedRound = true;
            if (count($shoePicks) >= 12) break;
        }
        if (!$addedRound) break;
    }
}
if ($shoePicks):
?>
<style>
.shoeband{padding:52px 0 46px;background:
  radial-gradient(880px 320px at 50% -70px, rgba(201,168,106,.06), transparent 70%);
  border-top:1px solid rgba(201,168,106,.12)}
.shoeband .wrap{max-width:1180px}
.shoe-kick{display:flex;align-items:center;gap:14px;justify-content:center;margin-bottom:12px}
.shoe-kick .ln{height:1px;width:min(120px,18vw);background:linear-gradient(90deg,transparent,rgba(201,168,106,.55))}
.shoe-kick .ln:last-child{transform:scaleX(-1)}
.shoe-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(201,168,106,.45);
  color:var(--acc);font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
  padding:7px 16px;border-radius:999px;white-space:nowrap}
.shoeband h2.sec-title{margin-bottom:6px}
.shoe-sub{text-align:center;max-width:620px;margin:0 auto;font-size:13.5px;color:var(--mut)}
.shoe-strip{display:flex;gap:16px;overflow-x:auto;padding:8px 4px 14px;margin-top:22px;
  scroll-snap-type:x mandatory;scrollbar-width:none;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent);
          mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}
.shoe-strip::-webkit-scrollbar{display:none}
/* Kart zemini BEYAZ: urun fotograflarinin zemini de beyaz, kremrengi bir kartta
   her fotograf gorunur bir dikdortgen olarak duruyordu (ayni ders yukarida). */
.shoe-card{flex:0 0 clamp(168px,20vw,224px);scroll-snap-align:start;border-radius:16px;
  background:#fff;position:relative;overflow:hidden;text-decoration:none;display:block;
  box-shadow:0 1px 0 rgba(255,255,255,.05), 0 14px 34px -22px rgba(0,0,0,.65);
  transition:transform .35s cubic-bezier(.2,.7,.2,1),box-shadow .35s}
.shoe-card:hover{transform:translateY(-3px);box-shadow:0 1px 0 rgba(255,255,255,.06), 0 22px 40px -22px rgba(0,0,0,.75)}
.shoe-card::after{content:"";position:absolute;inset:0;border-radius:inherit;
  box-shadow:inset 0 0 0 1px rgba(26,20,8,.06);pointer-events:none}
.shoe-card img{display:block;width:100%;height:auto;aspect-ratio:4/5;object-fit:contain;
  padding:10px 8px 34px;transition:transform .45s cubic-bezier(.2,.7,.2,1)}
.shoe-card:hover img{transform:scale(1.045)}
.shoe-meta{position:absolute;left:12px;right:12px;bottom:9px;display:flex;align-items:baseline;gap:6px}
.shoe-name{font-size:11px;font-weight:700;letter-spacing:.02em;color:#2a2620;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.shoe-cat{font-size:10px;color:#8c857a;white-space:nowrap;margin-left:auto}
.shoe-foot{display:flex;gap:10px 18px;align-items:center;justify-content:center;flex-wrap:wrap;margin-top:16px}
.shoe-foot .hint{font-size:12.5px;color:var(--mut)}
@media (max-width:640px){.shoeband{padding:40px 0 36px}}
</style>
<section class="shoeband reveal" id="footwear">
  <div class="wrap">
    <div class="shoe-kick"><span class="ln"></span>
      <span class="shoe-pill"><?= t('In stock now') ?></span>
    <span class="ln"></span></div>
    <h2 class="sec-title" style="text-align:center"><?= t('Footwear') ?></h2>
    <p class="shoe-sub">
      <?= sprintf(t('Spanish-made shoes for children, women and men — %d references, ordered by the size series.'), $shoeTotal) ?>
    </p>
    <div class="shoe-strip">
      <?php foreach ($shoePicks as $sc): ?>
      <a class="shoe-card" href="/product?id=<?= urlencode($sc['id']) ?>">
        <img src="<?= htmlspecialchars($sc['img']) ?>" alt="<?= htmlspecialchars($sc['name']) ?>" loading="lazy" width="720" height="900">
        <span class="shoe-meta">
          <span class="shoe-name"><?= htmlspecialchars($sc['name']) ?></span>
          <?php if ($sc['cat'] !== ''): ?><span class="shoe-cat"><?= htmlspecialchars(t($sc['cat'])) ?></span><?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="shoe-foot">
      <a class="btn btn-p" href="/shop?section=footwear"><?= sprintf(t('Browse all %d shoes'), $shoeTotal) ?> →</a>
      <span class="hint">🇪🇸 <?= t('Made in Spain · full size series') ?></span>
    </div>
    <?php /* One link per shoe category, each to its own landing page -- "boots wholesale"
             has an address now, and the band is where a crawler finds it. */
          $_fw = function_exists('vestra_seo_resolve') ? vestra_seo_resolve('footwear') : null;
          if ($_fw && count($_fw['cats']) > 1): $_cw = vestra_seo_wholesale_word($lang); ?>
    <div class="shoe-cats">
      <?php foreach ($_fw['cats'] as $_c => $_n): ?>
      <a href="/b2b/<?= urlencode(vestra_seo_cat_slug($_c)) ?>"><?= htmlspecialchars(t($_c).' '.$_cw) ?> · <?= (int)$_n ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($_brands): ?>
<section class="brandwall reveal" id="brands">
  <div class="wrap">
    <h2 class="sec-title"><?= $t['brands_t'] ?></h2>
    <p class="sec-sub" style="margin-bottom:34px"><?= $t['brands_s'] ?></p>
    <div class="bw-grid">
      <?php foreach ($_brands as $_b): ?>
      <?php /* Each house links to its own landing page, not the generic catalogue: the
               brand wall is the strongest internal link on the site and it used to
               point every crawler at one URL. */ ?>
      <a class="bw-cell" href="<?= function_exists('vestra_brand_slug') ? '/wholesale/'.urlencode(vestra_brand_slug($_b)) : '/shop' ?>" title="<?= htmlspecialchars($_b) ?>"><?= vestra_brand_card($_b) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* Category strip: every live category with its count, linking to /b2b/<slug>. The
         brand wall says which houses; this says what we actually sell -- and for a search
         engine these are the internal links that make "sneakers wholesale" resolve to a
         page. Counts are live, so an emptied category drops out on its own. */
      if (function_exists('vestra_seo_cats') && ($_cats = vestra_seo_cats())):
        $_cw = vestra_seo_wholesale_word($lang); ?>
<section class="catstrip reveal" id="categories">
  <div class="wrap">
    <h2 class="sec-title"><?= t('Wholesale by category') ?></h2>
    <p class="sec-sub"><?= t('Every category below is live stock — counts update as listings change.') ?></p>
    <div class="cs-grid">
      <?php foreach (vestra_seo_collections() as $_cs => $_sec): $_r = vestra_seo_resolve($_cs); if (!$_r) continue; ?>
      <a class="cs-cell cs-coll" href="/b2b/<?= $_cs ?>"><span class="cs-name"><?= htmlspecialchars(t(vestra_section_label($_sec)).' '.$_cw) ?></span><span class="cs-n"><?= htmlspecialchars(sprintf(t('%d listings'), count($_r['items']))) ?></span></a>
      <?php endforeach; foreach (array_slice($_cats, 0, 18, true) as $_c => $_n): ?>
      <a class="cs-cell" href="/b2b/<?= urlencode(vestra_seo_cat_slug($_c)) ?>"><span class="cs-name"><?= htmlspecialchars(t($_c).' '.$_cw) ?></span><span class="cs-n"><?= htmlspecialchars(sprintf(t('%d listings'), $_n)) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="wrap">
  <section class="pillars" id="why">
    <div class="card reveal">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.6-3 7.7-7 9-4-1.3-7-4.4-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg></div>
      <h3><?= $t['p1t'] ?></h3><p><?= $t['p1d'] ?></p>
    </div>
    <div class="card reveal">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2.2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><circle cx="12" cy="15" r="1.3"/></svg></div>
      <h3><?= $t['p2t'] ?></h3><p><?= $t['p2d'] ?></p>
    </div>
    <div class="card reveal">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8"/><path d="M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8"/><path d="M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16"/><path d="M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16"/><rect x="8.5" y="8.5" width="7" height="7" rx="1.2"/></svg></div>
      <h3><?= $t['p3t'] ?></h3><p><?= $t['p3d'] ?></p>
    </div>
  </section>

  <section id="how">
    <h2 class="sec-title reveal"><?= $t['how'] ?></h2>
    <p class="sec-sub reveal"><?= $t['hsub'] ?></p>
    <div class="steps">
      <div class="step reveal"><div class="n">01</div><h3><?= $t['s1t'] ?></h3><p><?= $t['s1d'] ?></p></div>
      <div class="step reveal"><div class="n">02</div><h3><?= $t['s2t'] ?></h3><p><?= $t['s2d'] ?></p></div>
      <div class="step reveal"><div class="n">03</div><h3><?= $t['s3t'] ?></h3><p><?= $t['s3d'] ?></p></div>
    </div>
  </section>

  <?php /* Giris yapmis kullaniciya KENDI kayit formunu gosterme. Bolumun en alt
           satiri ("zaten hesabiniz var mi? Giris yapin") zaten !$LOGGED ile
           gizleniyordu; iki kayit karti ise gizlenmiyordu -- yani bolum yarim
           gizlenmisti ve uye asagi kaydirinca "Register as Seller" goruyordu.
           Ust menu ve hero dogru davraniyordu, sikayetin kaynagi burasiydi. */ ?>
  <?php if(!$LOGGED): ?>
  <section class="join reveal" id="join">
    <h2 class="sec-title"><?= $t['jt'] ?></h2>
    <p class="sec-sub" style="margin-bottom:0"><?= $t['js'] ?></p>

    <div class="join-cards">
      <!-- Seller card -->
      <div class="jcard seller reveal">
        <div class="jcard-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7h13l5 5v5h-2"/><path d="M3 7v10h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/>
          </svg>
        </div>
        <h3><?= $t['sell_title'] ?></h3>
        <p class="jdesc"><?= $t['sell_desc'] ?></p>
        <ul class="jfeats">
          <li><?= $t['sell_f1'] ?></li>
          <li><?= $t['sell_f2'] ?></li>
          <li><?= $t['sell_f3'] ?></li>
        </ul>
        <a class="jbtn" href="/register?type=seller">
          <?= $t['sell_cta'] ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>

      <!-- Buyer card -->
      <div class="jcard buyer reveal">
        <div class="jcard-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 7h14l-1 12H6L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>
          </svg>
        </div>
        <h3><?= $t['buy_title'] ?></h3>
        <p class="jdesc"><?= $t['buy_desc'] ?></p>
        <ul class="jfeats">
          <li><?= $t['buy_f1'] ?></li>
          <li><?= $t['buy_f2'] ?></li>
          <li><?= $t['buy_f3'] ?></li>
        </ul>
        <a class="jbtn" href="/register?type=buyer">
          <?= $t['buy_cta'] ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </div>

    <p class="join-note"><?= $t['already'] ?> <a href="/login"><?= $t['signin'] ?></a></p>
  </section>
  <?php endif; ?>

  <!-- ── VESTRA as an app: store-independent install (PWA) + push opt-in ── -->
  <?php $apkExists = is_file(__DIR__.'/app/vestra.apk'); ?>
  <section class="appbox reveal" id="app">
    <div class="appbox-glow" aria-hidden="true"></div>
    <div class="appbox-icon" aria-hidden="true">
      <svg viewBox="0 0 32 32" fill="none" width="40" height="40"><rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/><path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="appbox-tx">
      <h3><?= $t['app_t'] ?></h3>
      <p><?= $t['app_s'] ?></p>
      <div class="appbox-btns">
        <?php if($apkExists): ?>
          <a class="btn btn-p" href="/app/vestra.apk" download>🤖 <?= $t['app_apk'] ?></a>
        <?php else: ?>
          <button class="btn btn-p" id="btnAndroid" type="button">🤖 <?= $t['app_and'] ?></button>
        <?php endif; ?>
        <button class="btn btn-o" id="btnIos" type="button"> <?= $t['app_ios'] ?></button>
        <button class="btn btn-o" id="btnNoti" type="button">🔔 <?= $t['app_noti'] ?></button>
      </div>
      <div class="appbox-hint" id="appHint" style="display:none"></div>
    </div>
  </section>
</div>

<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)"><?= htmlspecialchars($BRAND) ?></b> — <?= $t['tagline'] ?>
      <div style="margin-top:6px;opacity:.8"><?= htmlspecialchars($COMPANY) ?></div></div>
    <div class="foot-links">
      <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog','pt'=>'Catálogo','ru'=>'Каталог','ar'=>'الكتالوج'][$lang] ?? 'Catalog' ?></a>
      <a href="/faq">FAQ</a>
      <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
      <a href="mailto:<?= htmlspecialchars($CONTACT) ?>" class="acc"><?= htmlspecialchars($CONTACT) ?></a>
    </div>
  </div>
</footer>

<div id="cnotice" style="display:none;position:fixed;left:14px;right:14px;bottom:14px;z-index:90;max-width:520px;margin:0 auto;background:#17181c;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:13px 16px;font-size:13px;color:#9a9ba1;box-shadow:0 10px 34px rgba(0,0,0,.45);gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <span>🍪 <?= t('VESTRA only uses essential cookies (session & language preference) — no tracking, no ads.') ?>
    <a href="/legal?doc=privacy" style="color:<?= $ACCENT ?>;margin-left:4px"><?= t('Privacy') ?></a></span>
  <button onclick="localStorage.setItem('vcookie_ok','1');document.getElementById('cnotice').style.display='none'" style="background:<?= $ACCENT ?>;color:#0e0e11;border:0;border-radius:8px;padding:7px 16px;font-weight:600;cursor:pointer">OK</button>
</div>
<script>
  /* Every block below is independent and guarded — one failure (blocked storage,
     missing element, old browser) must never take the others down with it. */
  try{ if(!localStorage.getItem('vcookie_ok')){ document.getElementById('cnotice').style.display='flex'; } }catch(e){}
  try{
    var burger=document.getElementById('burger'), mnav=document.getElementById('mnav');
    if(burger&&mnav){
      /* Dil menusu: disariya tiklayinca ve Escape'te kapansin. <details> kendi
         basina kapanmaz; secilince sayfa zaten yenileniyor ama fikrini
         degistiren kullaniciyi acik menuyle birakmayalim. */
      document.addEventListener('click',function(e){
        document.querySelectorAll('details.langsw[open]').forEach(function(d){
          if(!d.contains(e.target)) d.removeAttribute('open');
        });
      });
      document.addEventListener('keydown',function(e){
        if(e.key==='Escape') document.querySelectorAll('details.langsw[open]').forEach(function(d){
          d.removeAttribute('open');
        });
      });
      burger.addEventListener('click',function(){var o=mnav.classList.toggle('open');burger.setAttribute('aria-expanded',o);});
      mnav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){mnav.classList.remove('open');});});
    }
  }catch(e){}
  /* ── PWA install + push opt-in (app box) ── */
  try{
    if('serviceWorker' in navigator){ navigator.serviceWorker.register('/sw.js').catch(function(){}); }
    var appHint=document.getElementById('appHint');
    function appSay(m){ if(appHint){ appHint.textContent=m; appHint.style.display='inline-block'; } }
    var deferredInstall=null;
    window.addEventListener('beforeinstallprompt',function(e){ e.preventDefault(); deferredInstall=e; });
    var bA=document.getElementById('btnAndroid'), bI=document.getElementById('btnIos'), bN=document.getElementById('btnNoti');
    if(bA) bA.addEventListener('click',function(){
      if(deferredInstall){ deferredInstall.prompt(); deferredInstall=null; }
      else appSay(<?= json_encode($t['app_and_hint']) ?>);
    });
    if(bI) bI.addEventListener('click',function(){ appSay(<?= json_encode($t['app_ios_hint']) ?>); });
    if(window.matchMedia && matchMedia('(display-mode: standalone)').matches){ if(bA)bA.style.display='none'; if(bI)bI.style.display='none'; }
    async function vestraPushOptIn(){
      if(!('Notification' in window)||!('serviceWorker' in navigator)||!('PushManager' in window)) return 'unsupported';
      var reg=await navigator.serviceWorker.ready;
      if(await Notification.requestPermission()!=='granted') return 'denied';
      var vk=(await (await fetch('/push?a=vapid')).json()).publicKey;
      if(!vk) return 'error';
      var pad='='.repeat((4-vk.length%4)%4), raw=atob((vk+pad).replace(/-/g,'+').replace(/_/g,'/'));
      var key=new Uint8Array(raw.length); for(var i2=0;i2<raw.length;i2++) key[i2]=raw.charCodeAt(i2);
      var sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
      var r=await fetch('/push?a=subscribe',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(sub)});
      if(r.status===401) return 'signin';
      return (await r.json()).ok?'ok':'error';
    }
    if(bN) bN.addEventListener('click',async function(){
      bN.disabled=true;
      var res='error'; try{ res=await vestraPushOptIn(); }catch(e){}
      bN.disabled=false;
      if(res==='ok'){ bN.textContent=<?= json_encode('✓ '.$t['app_noti_ok']) ?>; appSay(<?= json_encode($t['app_noti_ok']) ?>); }
      else if(res==='signin'){ appSay(<?= json_encode($t['app_signin']) ?>); }
      else { appSay(<?= json_encode($t['app_noti_no']) ?>); }
    });
  }catch(e){}

  try{
    if('IntersectionObserver' in window){
      var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{threshold:.12});
      document.querySelectorAll('.reveal').forEach(function(el){io.observe(el);});
    }else{
      document.querySelectorAll('.reveal').forEach(function(el){el.classList.add('in');});
    }
  }catch(e){ document.querySelectorAll('.reveal').forEach(function(el){el.classList.add('in');}); }

  /* Hero film. The <video> ships with preload="none" and no src at all, so the
     default state of this page is "poster only, nothing downloaded". The clip is
     attached solely when it is both wanted and affordable:
       - the visitor has not asked for reduced motion
       - the browser is not reporting Save-Data or a 2g-class connection
       - the viewport is wide enough for a 16:9 rail to still read as one
     Any of those failing is not an error -- the poster is a finished hero on its own.
     It also pauses off-screen, because a looping clip under the footer is spending
     battery to be seen by nobody. */
  try{
    var hv = document.querySelector('.herovid');
    if (hv) {
      var rm  = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
      var con = navigator.connection || {};
      var thin = con.saveData === true || /(^|\-)2g$/.test(con.effectiveType || '');
      if (!rm && !thin && Math.min(innerWidth, screen.width || innerWidth) >= 700) {
        var wb = hv.getAttribute('data-webm');
        if (wb && hv.canPlayType && hv.canPlayType('video/webm; codecs="vp9"')) {
          hv.src = wb;
        } else {
          hv.src = hv.getAttribute('data-mp4');
        }
        hv.addEventListener('canplay', function(){ hv.classList.add('on'); }, { once:true });
        /* Autoplay can still be refused (a power-saving phone, a browser setting).
           Leaving the element faded in but frozen would show one arbitrary frame over
           the poster, so a refusal drops us back to the poster instead. */
        var pr = hv.play();
        if (pr && pr.catch) pr.catch(function(){ hv.classList.remove('on'); });

        if ('IntersectionObserver' in window) {
          new IntersectionObserver(function(es){
            es.forEach(function(e){
              if (e.isIntersecting) { var p = hv.play(); if (p && p.catch) p.catch(function(){}); }
              else hv.pause();
            });
          }, { threshold: 0.01 }).observe(hv);
        }
      }
    }
  }catch(e){}
</script>
<?php require_once __DIR__.'/inc/tabbar.php'; ?>
</body>
</html>
