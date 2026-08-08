<?php
/**
 * VESTRA — Verified B2B Fashion Wholesale · Live marketplace homepage
 */
$BRAND   = 'VESTRA';
$CONTACT = 'support@vestrasales.com';
$COMPANY = 'acerasoft LLC';
$ACCENT  = '#c9a86a';
/* Signed-in visitors get "open my dashboard" instead of register CTAs —
   register.php would only redirect them anyway (reads as a dead button). */
if (session_status() === PHP_SESSION_NONE) session_start();
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
        /* Sort key: category order first, then colourful names ahead of plain ones.
           The BALMAIN black tee that used to be pinned here has been dropped -- the
           operator asked for a second women's piece in that slot instead, and
           Women's T-Shirts now sits second in the running order. */
        $pool[] = [$pos, 0, preg_match($COLOUR, $nm) ? 0 : 1, $cat, $im];
    }
    usort($pool, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

    $HERO_FRAMES = array_slice($pinnedFrames, 0, 6);

    /* One frame per category so no two consecutive shots look alike. */
    $usedCat = [];
    foreach ($pool as [, , , $cat, $im]) {
        if (isset($usedCat[$cat]) || in_array($im, $HERO_FRAMES, true)) continue;
        $usedCat[$cat] = true;
        $HERO_FRAMES[] = $im;
        if (count($HERO_FRAMES) >= 6) break;
    }
    /* Short catalogue: top the sequence up with anything left rather than run a
       two-frame film. */
    if (count($HERO_FRAMES) < 4) {
        foreach ($pool as [, , , , $im]) {
            if (in_array($im, $HERO_FRAMES, true)) continue;
            $HERO_FRAMES[] = $im;
            if (count($HERO_FRAMES) >= 6) break;
        }
    }
}

$LANGS = ['en'=>'EN','fr'=>'FR','it'=>'IT','es'=>'ES','de'=>'DE'];
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
];
$t = $T[$lang];

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($BRAND.' — '.$t['tagline']) ?></title>
<meta name="description" content="<?= htmlspecialchars($t['meta']) ?>">
<?php
// ── SEO: canonical + multilingual hreflang + social + structured data ──
$SEO_HOST = 'https://vestrasales.com'; $OG_IMAGE = $SEO_HOST.'/inc/og-image.png';
$_hh = fn($l) => $SEO_HOST.'/'.($l === 'en' ? '' : '?lang='.$l);
$_ogloc = ['en'=>'en_US','de'=>'de_DE','fr'=>'fr_FR','it'=>'it_IT','es'=>'es_ES'][$lang] ?? 'en_US';
/* Brand names, taken from the LIVE catalogue rather than typed in. Two reasons.
   Truthfulness: the page can only ever name a house that is actually in stock, so
   the copy cannot drift into claiming a brand that was never carried. And reach:
   "Balenciaga wholesale" is what a buyer types, and it is a factual description of
   genuine EEA stock sold with an invoice trail -- nominative use, not a claim of
   authorisation or affiliation, which is why nothing here says "official" or
   "authorised dealer". Capped so the tags stay a summary rather than a keyword dump. */
$_brands = [];
if (function_exists('vestra_products')) {
    foreach (vestra_products() as $_bp) {
        $_b = trim((string)($_bp['brand'] ?? ''));
        if ($_b !== '' && !in_array($_b, $_brands, true)) $_brands[] = $_b;
    }
}
sort($_brands);
$_brandList = implode(', ', array_slice($_brands, 0, 14));
/* "<brand> wholesale" in the visitor's language -- the phrase buyers actually search. */
$_wholesaleWord = ['en'=>'wholesale','fr'=>'en gros','it'=>'ingrosso','es'=>'al por mayor','de'=>'Großhandel'][$lang] ?? 'wholesale';
$_brandKw = implode(', ', array_map(fn($b) => $b.' '.$_wholesaleWord, array_slice($_brands, 0, 12)));

// Keyword sets (category / buyer-intent terms; localized), with the stocked houses appended.
$_kw = [
 'en'=>'B2B fashion wholesale, branded fashion wholesale, authentic designer wholesale, wholesale clothing marketplace, KYC-verified suppliers, boutique wholesale supplier, multi-brand wholesale, designer clothing wholesale Europe, verified wholesale fashion, buy wholesale clothing online',
 'fr'=>'grossiste mode B2B, vente en gros de marque, grossiste vêtements de créateur authentiques, marketplace de gros, fournisseurs vérifiés KYC, grossiste multimarque, mode de créateur en gros Europe, acheter vêtements en gros',
 'it'=>'moda ingrosso B2B, ingrosso abbigliamento firmato, capi di design autentici ingrosso, marketplace ingrosso moda, fornitori verificati KYC, ingrosso multimarca, moda firmata ingrosso Europa, comprare abbigliamento ingrosso',
 'es'=>'moda al por mayor B2B, mayorista de marca, ropa de diseñador auténtica al por mayor, marketplace mayorista de moda, proveedores verificados KYC, mayorista multimarca, moda de diseñador al por mayor Europa, comprar ropa al por mayor',
 'de'=>'B2B Mode Großhandel, Marken Großhandel, authentische Designer Großhandel, Großhandel Bekleidung, KYC-verifizierte Lieferanten, Multibrand Großhandel, Designermode Großhandel Europa, Bekleidung im Großhandel kaufen',
][$lang] ?? '';
if ($_brandKw !== '') $_kw = ($_kw !== '' ? $_kw.', ' : '').$_brandKw;
?>
<link rel="canonical" href="<?= htmlspecialchars($_hh($lang)) ?>">
<?php foreach (array_keys($LANGS) as $_l): ?>
<link rel="alternate" hreflang="<?= $_l ?>" href="<?= htmlspecialchars($_hh($_l)) ?>">
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
     array_slice($_brands, 0, 14)),
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
  .langs{display:flex;gap:9px;align-items:center}
  .langs a{font-size:12.5px;color:var(--mut);transition:color .2s}
  .langs a:hover{color:var(--ink)}
  .langs a.on{color:var(--acc);font-weight:600}
  .langs .sep{opacity:.3}
  .burger{display:none;background:none;border:0;cursor:pointer;padding:8px;color:var(--ink)}
  .mnav{display:none;border-top:1px solid var(--line);background:rgba(14,14,17,.97);backdrop-filter:blur(12px)}
  .mnav a{display:block;padding:16px 24px;border-bottom:1px solid var(--line);color:var(--ink);font-weight:500}
  .mnav .mlangs{display:flex;gap:16px;padding:16px 24px}
  .mnav .mlangs a{border:0;padding:0;font-size:14px}
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
  .hero.hasfilm{padding:120px 0 92px;min-height:min(78vh,700px);display:flex;align-items:center}
  .hero.hasfilm>.wrap{position:relative;z-index:2;width:100%}
  .herofilm{position:absolute;inset:0;overflow:hidden;z-index:0;pointer-events:none}
  /* A soft spotlight behind the garment so a contained frame reads as a lit product
     on a dark stage rather than a small picture floating in an empty band. */
  .herofilm::before{content:'';position:absolute;inset:0;z-index:0;
    background:radial-gradient(42% 58% at 50% 46%,rgba(255,255,255,.075),transparent 72%)}
  /* `contain`, not `cover`. These are tall product packshots and the hero is a wide,
     short band: covering a 3:4 shot into a ~3:1 band scales it to the band's WIDTH,
     so roughly three quarters of the garment is cropped away and the visitor sees an
     abstract sliver of fabric. Containing it fits the frame by its long edge instead,
     so the whole piece is always in shot -- identical behaviour on a phone, where the
     band is nearly square and the same image fits by width. Nothing is ever cut off.
     min-height gives the contained frame real presence on a desktop viewport. */
  .herofilm .hf{position:absolute;inset:0;background-size:contain;background-position:center;
    background-repeat:no-repeat;
    opacity:0;transform:scale(1.02);will-change:opacity,transform;
    animation:heroFilm var(--hfdur,36s) cubic-bezier(.4,0,.2,1) infinite}
  /* Each frame owns 1/N of the cycle and the stagger is 6s, so the visible slice has to
     be expressed as a share of the whole: N frames x 6s = cycle, one frame's slot = 100/N%.
     Written for the 6-frame case and overridden below when there are fewer, because a
     fixed 18% window would leave the hero empty between frames on a short catalogue. */
  @keyframes heroFilm{
    0%{opacity:0;transform:scale(1.02)}
    4%{opacity:.82}
    14%{opacity:.82}
    18%{opacity:0;transform:scale(1.07)}
    100%{opacity:0;transform:scale(1.07)}
  }
  .herofilm[style*="24s"] .hf{animation-name:heroFilm4}
  @keyframes heroFilm4{
    0%{opacity:0;transform:scale(1.02)}
    6%{opacity:.82} 21%{opacity:.82}
    27%{opacity:0;transform:scale(1.07)}
    100%{opacity:0;transform:scale(1.07)}
  }
  .herofilm[style*="30s"] .hf{animation-name:heroFilm5}
  @keyframes heroFilm5{
    0%{opacity:0;transform:scale(1.02)}
    5%{opacity:.82} 17%{opacity:.82}
    22%{opacity:0;transform:scale(1.07)}
    100%{opacity:0;transform:scale(1.07)}
  }
  .herofilm[style*="18s"] .hf{animation-name:heroFilm3}
  @keyframes heroFilm3{
    0%{opacity:0;transform:scale(1.02)}
    8%{opacity:.82} 27%{opacity:.82}
    35%{opacity:0;transform:scale(1.07)}
    100%{opacity:0;transform:scale(1.07)}
  }
  /* The veil is what keeps the headline legible over any photograph — without it the
     contrast swings with every frame and the type becomes unreadable on the light ones. */
  .herofilm-veil{position:absolute;inset:0;
    background:linear-gradient(to bottom,rgba(14,14,17,.80) 0%,rgba(14,14,17,.46) 42%,rgba(14,14,17,.90) 100%),
               radial-gradient(62% 58% at 50% 34%,rgba(201,168,106,.16),transparent 70%)}
  @media(prefers-reduced-motion:reduce){
    .herofilm .hf{animation:none;opacity:0;transform:none}
    .herofilm .hf:first-child{opacity:.5}
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
      <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
      <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
      <a href="/faq">FAQ</a>
      <?php /* short label here, not $t['brands_t'] — that is a full section heading
               ("Die Marken im Bestand", "Las maisons disponibles") and as a nav item it
               alone pushed the menu ~120px wider than the header could hold. */ ?>
      <?php if ($_brands): ?><a href="#brands"><?= ['en'=>'Brands','fr'=>'Marques','it'=>'Marchi','es'=>'Marcas','de'=>'Marken'][$lang] ?></a><?php endif; ?>
      <a href="#how"><?= $t['how'] ?></a>
      <?php /* brand name dropped from this label only (the drawer and footer keep it) —
               redundant three centimetres from the wordmark, and it cost ~65px of header
               width in every language. */ ?>
      <a href="#why"><?= $t['why'] ?></a>
      <span class="langs">
        <?php $i=0; foreach($LANGS as $c=>$l){ echo $i++? '<span class="sep">·</span>':''; ?><a href="?lang=<?= $c ?>" class="<?= $c===$lang?'on':'' ?>"><?= $l ?></a><?php } ?>
      </span>
      <?php if(!$LOGGED): ?><a href="/login" class="nav-signin"><?= ['en'=>'Sign in','fr'=>'Se connecter','it'=>'Accedi','es'=>'Iniciar sesión','de'=>'Anmelden'][$lang] ?></a><?php endif; ?>
      <a href="<?= $LOGGED ? $panelHref : '/register' ?>" class="nav-cta"><?= $LOGGED ? $t['b_panel'] : $t['join_nav'] ?></a>
    </div>
    <button class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="mnav">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
  </nav></div>
  <div class="mnav" id="mnav">
    <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
    <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
    <a href="/faq">FAQ</a>
    <?php if ($_brands): ?><a href="#brands"><?= ['en'=>'Brands','fr'=>'Marques','it'=>'Marchi','es'=>'Marcas','de'=>'Marken'][$lang] ?></a><?php endif; ?>
    <a href="#how"><?= $t['how'] ?></a>
    <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
    <?php if(!$LOGGED): ?><a href="/login"><?= ['en'=>'Sign in','fr'=>'Se connecter','it'=>'Accedi','es'=>'Iniciar sesión','de'=>'Anmelden'][$lang] ?></a><?php endif; ?>
    <a href="<?= $LOGGED ? $panelHref : '/register' ?>"><?= $LOGGED ? $t['b_panel'] : $t['join_nav'] ?></a>
    <div class="mlangs">
      <?php foreach($LANGS as $c=>$l){ ?><a href="?lang=<?= $c ?>" class="<?= $c===$lang?'on':'' ?>"><?= $l ?></a><?php } ?>
    </div>
  </div>
</header>

<span id="top"></span>
<section class="hero<?= $HERO_FRAMES ? ' hasfilm' : '' ?>">
  <?php if($HERO_FRAMES): ?>
  <div class="herofilm" aria-hidden="true" style="--hfdur:<?= count($HERO_FRAMES) * 6 ?>s">
    <?php foreach($HERO_FRAMES as $i=>$f): ?>
      <div class="hf" style="background-image:url('<?= htmlspecialchars($f) ?>');animation-delay:<?= $i * 6 ?>s"></div>
    <?php endforeach; ?>
    <div class="herofilm-veil"></div>
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
      <a class="btn btn-p" href="/register?type=seller">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h13l5 5v5h-2"/><path d="M3 7v10h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
        <?= $t['b_sell'] ?>
      </a>
      <a class="btn btn-o" href="/register?type=buyer">
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
  </div>
</section>

<?php if ($_brands): ?>
<section class="brandwall reveal" id="brands">
  <div class="wrap">
    <h2 class="sec-title"><?= $t['brands_t'] ?></h2>
    <p class="sec-sub" style="margin-bottom:34px"><?= $t['brands_s'] ?></p>
    <div class="bw-grid">
      <?php foreach ($_brands as $_b): ?>
      <a class="bw-cell" href="/shop" title="<?= htmlspecialchars($_b) ?>"><?= vestra_brand_card($_b) ?></a>
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

    <?php if(!$LOGGED): ?><p class="join-note"><?= $t['already'] ?> <a href="/login"><?= $t['signin'] ?></a></p><?php endif; ?>
  </section>

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
      <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
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
</script>
<?php require_once __DIR__.'/inc/tabbar.php'; ?>
</body>
</html>
