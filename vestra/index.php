<?php
/**
 * VESTRA — Verified B2B Fashion Wholesale · Live marketplace homepage
 */
$BRAND   = 'VESTRA';
$CONTACT = 'support@vestrasales.com';
$COMPANY = 'acerasoft LLC';
$ACCENT  = '#c9a86a';
require_once __DIR__.'/inc/i18n.php';

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
 'b_sell'=>'Register as Seller','b_buy'=>'Register as Buyer',
 'tr1'=>'KYC-verified sellers','tr2'=>'Invoice-based payment','tr3'=>'Transaction records',
 'p1t'=>'Verified sellers only','p1d'=>'Business KYC on every seller — VAT ID, registration, identity. No anonymous listings, no guesswork.',
 'p2t'=>'Buyer protection','p2d'=>'Pay by invoice with a full paper trail. If goods don\'t match the listing, a structured dispute process steps in.',
 'p3t'=>'Transaction integrity','p3d'=>'Every order is logged, timestamped, and tied to the verified seller account — a clear paper trail for both sides of the trade.',
 'hsub'=>'Trust, by design — for both sides of the trade.',
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
 'already'=>'Already have an account?','signin'=>'Sign in →',
],
'fr'=>[
 'tagline'=>"La vente en gros de mode B2B vérifiée en Europe",
 'meta'=>"Une marketplace B2B avec vérification KYC des vendeurs, pour la mode de marque et le textile. Inscrivez-vous gratuitement.",
 'why'=>'Pourquoi','how'=>'Comment ça marche','join_nav'=>'Commencer',
 'pill'=>'En ligne · Inscription ouverte',
 'h1'=>'La façon <span class="acc">vérifiée</span> de négocier<br>la mode de marque, en gros.',
 'sub'=>"Une marketplace B2B où chaque vendeur est vérifié (KYC), chaque commande repose sur des conditions de facturation claires et chaque transaction est documentée. Construite sur la vérification des vendeurs.",
 'b_sell'=>'Rejoindre comme Vendeur','b_buy'=>'Rejoindre comme Acheteur',
 'tr1'=>'Vendeurs vérifiés (KYC)','tr2'=>'Paiement sur facture','tr3'=>'Traçabilité des transactions',
 'p1t'=>'Vendeurs vérifiés uniquement','p1d'=>'KYC entreprise sur chaque vendeur — TVA, immatriculation, identité. Aucune annonce anonyme.',
 'p2t'=>'Protection acheteur','p2d'=>"Paiement sur facture avec une traçabilité complète. Si la marchandise ne correspond pas à l'annonce, un processus de litige structuré s'enclenche.",
 'p3t'=>'Intégrité des transactions','p3d'=>"Chaque commande est enregistrée, horodatée et liée au compte du vendeur vérifié — une trace claire pour les deux parties.",
 'hsub'=>'La confiance par conception — pour les deux parties.',
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
 'already'=>'Déjà un compte ?','signin'=>'Se connecter →',
],
'it'=>[
 'tagline'=>"Il commercio all'ingrosso di moda B2B verificato in Europa",
 'meta'=>"Un marketplace B2B con verifica KYC dei venditori, per moda di marca e tessile. Registrati gratuitamente.",
 'why'=>'Perché','how'=>'Come funziona','join_nav'=>'Inizia ora',
 'pill'=>'Online · Registrazione aperta',
 'h1'=>'Il modo <span class="acc">verificato</span> di commerciare<br>moda di marca, all\'ingrosso.',
 'sub'=>"Un marketplace B2B dove ogni venditore è verificato (KYC), ogni ordine si basa su chiare condizioni di fatturazione e ogni transazione è documentata.",
 'b_sell'=>'Registrati come Venditore','b_buy'=>'Registrati come Acquirente',
 'tr1'=>'Venditori verificati (KYC)','tr2'=>'Pagamento su fattura','tr3'=>'Registri delle transazioni',
 'p1t'=>'Solo venditori verificati','p1d'=>'KYC aziendale su ogni venditore — partita IVA, registrazione, identità. Nessun annuncio anonimo.',
 'p2t'=>'Protezione acquirente','p2d'=>"Pagamento su fattura con tracciabilità completa. Se la merce non corrisponde all'annuncio, si attiva un processo di reclamo strutturato.",
 'p3t'=>'Integrità delle transazioni','p3d'=>"Ogni ordine è registrato, con marca temporale e collegato all'account del venditore verificato — una traccia chiara per entrambe le parti.",
 'hsub'=>'Fiducia per progettazione — per entrambe le parti.',
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
 'already'=>'Hai già un account?','signin'=>'Accedi →',
],
'es'=>[
 'tagline'=>"La venta mayorista de moda B2B verificada en Europa",
 'meta'=>"Un marketplace B2B con verificación KYC de vendedores, para moda de marca y textil. Regístrate gratis.",
 'why'=>'Por qué','how'=>'Cómo funciona','join_nav'=>'Empezar',
 'pill'=>'En vivo · Registro abierto',
 'h1'=>'La forma <span class="acc">verificada</span> de comerciar<br>moda de marca, al por mayor.',
 'sub'=>'Un marketplace B2B donde cada vendedor está verificado (KYC), cada pedido se basa en condiciones de facturación claras y cada transacción queda registrada.',
 'b_sell'=>'Registrarse como Vendedor','b_buy'=>'Registrarse como Comprador',
 'tr1'=>'Vendedores verificados (KYC)','tr2'=>'Pago por factura','tr3'=>'Registros de transacciones',
 'p1t'=>'Solo vendedores verificados','p1d'=>'KYC empresarial en cada vendedor — IVA, registro, identidad. Sin anuncios anónimos.',
 'p2t'=>'Protección al comprador','p2d'=>'Pago por factura con trazabilidad completa. Si la mercancía no coincide con el anuncio, se activa un proceso de disputa estructurado.',
 'p3t'=>'Integridad de transacciones','p3d'=>'Cada pedido queda registrado, con marca de tiempo y vinculado a la cuenta del vendedor verificado — un historial claro para ambas partes.',
 'hsub'=>'Confianza por diseño — para ambas partes.',
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
 'already'=>'¿Ya tienes cuenta?','signin'=>'Iniciar sesión →',
],
'de'=>[
 'tagline'=>"Europas verifizierter B2B-Modegroßhandel",
 'meta'=>"Ein B2B-Großhandelsmarktplatz mit KYC-Verifizierung der Verkäufer für Marken- und Textilmode. Jetzt kostenlos registrieren.",
 'why'=>'Warum','how'=>"So funktioniert's",'join_nav'=>'Jetzt starten',
 'pill'=>'Live · Registrierung offen',
 'h1'=>'Der <span class="acc">verifizierte</span> Weg, Markenmode<br>im Großhandel zu handeln.',
 'sub'=>"Ein B2B-Marktplatz, auf dem jeder Verkäufer KYC-geprüft ist, jede Bestellung auf klaren Rechnungskonditionen läuft und jede Transaktion dokumentiert ist.",
 'b_sell'=>'Als Verkäufer registrieren','b_buy'=>'Als Käufer registrieren',
 'tr1'=>'KYC-verifizierte Verkäufer','tr2'=>'Zahlung auf Rechnung','tr3'=>'Transaktionsnachweise',
 'p1t'=>'Nur verifizierte Verkäufer','p1d'=>'Geschäftliche KYC-Prüfung bei jedem Verkäufer — USt-IdNr., Registrierung, Identität. Keine anonymen Inserate.',
 'p2t'=>'Käuferschutz','p2d'=>'Zahlung auf Rechnung mit lückenloser Dokumentation. Entspricht die Ware nicht dem Angebot, greift ein strukturiertes Streitverfahren.',
 'p3t'=>'Transaktionsintegrität','p3d'=>'Jede Bestellung wird protokolliert, mit Zeitstempel versehen und dem verifizierten Verkäuferkonto zugeordnet — ein klarer Nachweis für beide Seiten.',
 'hsub'=>'Vertrauen durch Design — für beide Seiten des Handels.',
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
 'already'=>'Bereits ein Konto?','signin'=>'Anmelden →',
],
];
$t = $T[$lang];

$favSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='#0e0e11'/><path d='M9 10l7 13 7-13' fill='none' stroke='{$ACCENT}' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'/></svg>";
$favicon = 'data:image/svg+xml,' . rawurlencode($favSvg);
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
?>
<link rel="canonical" href="<?= htmlspecialchars($_hh($lang)) ?>">
<?php foreach (array_keys($LANGS) as $_l): ?>
<link rel="alternate" hreflang="<?= $_l ?>" href="<?= htmlspecialchars($_hh($_l)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($_hh('en')) ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
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
  'logo'=>$OG_IMAGE,'email'=>$CONTACT,'areaServed'=>'EU',
  'description'=>'Verified B2B fashion wholesale marketplace — branded apparel and textile basics from KYC-verified sellers across Europe.',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode([
  '@context'=>'https://schema.org','@type'=>'WebSite','name'=>'VESTRA','url'=>$SEO_HOST,
  'potentialAction'=>['@type'=>'SearchAction','target'=>['@type'=>'EntryPoint','urlTemplate'=>$SEO_HOST.'/shop?q={search_term_string}'],'query-input'=>'required name=search_term_string'],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<link rel="icon" href="<?= $favicon ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0e0e11;--bg2:#15151a;--ink:#f4f1ea;--mut:#9a988f;
    --acc:<?= htmlspecialchars($ACCENT) ?>;--line:rgba(255,255,255,.08);}
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:var(--bg);color:var(--ink);
    font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
  a{color:inherit;text-decoration:none}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px}
  h1,h2,h3{font-family:'Playfair Display',Georgia,serif;font-weight:700;line-height:1.12;letter-spacing:-.5px}
  .acc{color:var(--acc)}
  section{scroll-margin-top:84px}
  svg{display:block}

  header{position:sticky;top:0;z-index:30;background:rgba(14,14,17,.72);
    backdrop-filter:saturate(140%) blur(12px);border-bottom:1px solid var(--line)}
  nav{display:flex;align-items:center;justify-content:space-between;height:66px}
  .logo{display:flex;align-items:center;gap:10px;font-family:'Playfair Display',serif;
    font-size:22px;font-weight:700;letter-spacing:1.5px}
  .logo .mark{width:30px;height:30px}
  .nav-links{display:flex;align-items:center;gap:26px;font-size:14px;font-weight:500}
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
  .hero h1{font-size:clamp(34px,6.2vw,62px);margin:0 0 20px}
  .hero>.wrap>p{font-size:clamp(16px,2.4vw,20px);color:var(--mut);max-width:630px;margin:0 auto 36px}
  .btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
  .btn{padding:15px 30px;border-radius:999px;font-weight:600;font-size:15px;cursor:pointer;
    border:1px solid var(--acc);transition:.2s;display:inline-flex;align-items:center;gap:9px}
  .btn-p{background:var(--acc);color:#1a1408}
  .btn-p:hover{filter:brightness(1.08);transform:translateY(-2px);box-shadow:0 10px 30px -10px rgba(201,168,106,.5)}
  .btn-o{background:transparent;color:var(--ink)}
  .btn-o:hover{background:rgba(201,168,106,.1);transform:translateY(-2px)}
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
  .join-note a:hover{text-decoration:underline}

  footer{border-top:1px solid var(--line);padding:46px 0;color:var(--mut);font-size:13px}
  .foot{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:center}
  .foot-links{display:flex;gap:22px}
  .foot-links a:hover{color:var(--ink)}

  .reveal{opacity:0;transform:translateY(20px);transition:opacity .7s ease,transform .7s ease}
  .reveal.in{opacity:1;transform:none}
  @media(prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none}.dot{animation:none}}

  @media(max-width:820px){.nav-links{display:none}.burger{display:block}}
  @media(max-width:760px){
    .pillars,.steps{grid-template-columns:1fr}
    .join-cards{grid-template-columns:1fr}
    .hero{padding:64px 0 44px}
    .trustline{gap:14px}
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
      <span><?= htmlspecialchars($BRAND) ?></span>
    </a>
    <div class="nav-links">
      <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
      <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
      <a href="/faq">FAQ</a>
      <a href="#how"><?= $t['how'] ?></a>
      <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
      <span class="langs">
        <?php $i=0; foreach($LANGS as $c=>$l){ echo $i++? '<span class="sep">·</span>':''; ?><a href="?lang=<?= $c ?>" class="<?= $c===$lang?'on':'' ?>"><?= $l ?></a><?php } ?>
      </span>
      <a href="/register" class="nav-cta"><?= $t['join_nav'] ?></a>
    </div>
    <button class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="mnav">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
  </nav></div>
  <div class="mnav" id="mnav">
    <a href="/shop"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
    <a href="/requests"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
    <a href="/faq">FAQ</a>
    <a href="#how"><?= $t['how'] ?></a>
    <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
    <a href="/register"><?= $t['join_nav'] ?></a>
    <div class="mlangs">
      <?php foreach($LANGS as $c=>$l){ ?><a href="?lang=<?= $c ?>" class="<?= $c===$lang?'on':'' ?>"><?= $l ?></a><?php } ?>
    </div>
  </div>
</header>

<span id="top"></span>
<section class="hero">
  <div class="wrap">
    <div class="pill"><span class="dot"></span> <?= $t['pill'] ?></div>
    <h1><?= $t['h1'] ?></h1>
    <p><?= $t['sub'] ?></p>
    <div class="btns">
      <a class="btn btn-p" href="/register?type=seller">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h13l5 5v5h-2"/><path d="M3 7v10h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
        <?= $t['b_sell'] ?>
      </a>
      <a class="btn btn-o" href="/register?type=buyer">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1 12H6L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        <?= $t['b_buy'] ?>
      </a>
    </div>
    <div class="trustline">
      <?php foreach(['tr1','tr2','tr3'] as $k){ ?>
      <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= $t[$k] ?></span>
      <?php } ?>
    </div>
  </div>
</section>

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

    <p class="join-note"><?= $t['already'] ?> <a href="/login"><?= $t['signin'] ?></a></p>
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
  if(!localStorage.getItem('vcookie_ok')){ document.getElementById('cnotice').style.display='flex'; }
  var burger=document.getElementById('burger'), mnav=document.getElementById('mnav');
  burger.addEventListener('click',function(){var o=mnav.classList.toggle('open');burger.setAttribute('aria-expanded',o);});
  mnav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){mnav.classList.remove('open');});});

  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el){io.observe(el);});
</script>
</body>
</html>
