<?php
/**
 * VESTRA — Verified B2B Fashion Wholesale · Phase 0 landing + waitlist
 * --------------------------------------------------------------------
 * Tek dosya, sade PHP. Hosteurope dâhil her paylaşımlı hostingte çalışır.
 * Diller: EN / FR / IT / ES (?lang=fr ...). Metinler aşağıdaki $T dizisinde.
 * Markayı/iletişimi değiştirmek için sadece şu satırları düzenle:
 */
$BRAND   = 'VESTRA';                                          // ← marka adı (placeholder)
$CONTACT = 'hello@vestra.example';                            // ← iletişim e-postan
$COMPANY = '[Company Name LLC · US] — legal info pending';    // ← şirket bilgin (şeffaflık!)
$ACCENT  = '#c9a86a';

$LANGS = ['en'=>'EN','fr'=>'FR','it'=>'IT','es'=>'ES','de'=>'DE'];
$lang  = $_GET['lang'] ?? 'en';
if (!isset($LANGS[$lang])) $lang = 'en';

$T = [
'en'=>[
 'tagline'=>"Europe's verified B2B fashion wholesale",
 'meta'=>"A verified, authenticity-first B2B wholesale marketplace for branded and textile fashion. Join the founding waitlist.",
 'why'=>'Why','how'=>'How it works','join_nav'=>'Join the waitlist',
 'pill'=>'Invitation-only · Launching soon',
 'h1'=>'The <span class="acc">verified</span> way to trade<br>branded fashion, wholesale.',
 'sub'=>'A B2B marketplace where every seller is KYC-checked, every order is escrow-protected, and every product is traceable. Built authenticity-first — not as an afterthought.',
 'b_sell'=>'Join as a Seller','b_buy'=>'Join as a Buyer',
 'tr1'=>'KYC-verified members','tr2'=>'Escrow protection','tr3'=>'EU DPP-ready',
 'p1t'=>'Verified sellers only','p1d'=>'Business KYC on every seller — VAT ID, registration, identity. No anonymous listings, no guesswork.',
 'p2t'=>'Escrow & authenticity','p2d'=>'Funds release only after the order is received and confirmed. Counterfeit & grey-market screening built in.',
 'p3t'=>'Digital Product Passport','p3d'=>"Every item carries a traceable provenance record — ready for the EU's incoming DPP rules, today.",
 'hsub'=>'Trust, by design — for both sides of the trade.',
 's1t'=>'Get verified','s1d'=>'Sellers and buyers complete a quick business verification. Approved members see live wholesale pricing.',
 's2t'=>'Source & order','s2d'=>'Browse verified listings — branded & textile basics. Order with escrow protection and clear terms.',
 's3t'=>'Trade with confidence','s3d'=>'Authenticity checks, provenance records and a fair dispute process protect every transaction.',
 'ok'=>"✓ You're on the list. We'll be in touch as we open the founding cohort.",
 'bad'=>'Please enter at least your name and a valid email.',
 'jt'=>'Join the founding waitlist','js'=>'Be among the first verified members. No payment, no commitment.',
 'tg_s'=>"I'm a Seller",'tg_b'=>"I'm a Buyer",
 'f_name'=>'Full name','f_co'=>'Company','f_email'=>'Work email','f_country'=>'Country',
 'm_s'=>'What do you sell? (brands, categories)','m_b'=>'What are you looking to source?',
 'submit'=>'Request early access',
 'note'=>'By joining you agree to be contacted about the launch. We never share your data.',
],
'fr'=>[
 'tagline'=>"La vente en gros de mode B2B vérifiée en Europe",
 'meta'=>"Une marketplace B2B vérifiée, centrée sur l'authenticité, pour la mode de marque et le textile. Rejoignez la liste d'attente.",
 'why'=>'Pourquoi','how'=>'Comment ça marche','join_nav'=>"Rejoindre la liste",
 'pill'=>'Sur invitation · Lancement imminent',
 'h1'=>'La façon <span class="acc">vérifiée</span> de négocier<br>la mode de marque, en gros.',
 'sub'=>"Une marketplace B2B où chaque vendeur est vérifié (KYC), chaque commande est protégée par séquestre et chaque produit est traçable. Conçue pour l'authenticité, dès le départ.",
 'b_sell'=>'Rejoindre en Vendeur','b_buy'=>'Rejoindre en Acheteur',
 'tr1'=>'Membres vérifiés (KYC)','tr2'=>'Protection par séquestre','tr3'=>"Prêt pour le DPP de l'UE",
 'p1t'=>'Vendeurs vérifiés uniquement','p1d'=>'KYC entreprise sur chaque vendeur — numéro de TVA, immatriculation, identité. Aucune annonce anonyme, aucune incertitude.',
 'p2t'=>'Séquestre & authenticité','p2d'=>'Les fonds ne sont libérés qu\'après réception et confirmation de la commande. Détection des contrefaçons et du marché gris intégrée.',
 'p3t'=>'Passeport Numérique de Produit','p3d'=>"Chaque article dispose d'un historique de provenance traçable — prêt dès aujourd'hui pour la future réglementation DPP de l'UE.",
 'hsub'=>'La confiance par conception — pour les deux parties.',
 's1t'=>'Faites-vous vérifier','s1d'=>'Vendeurs et acheteurs effectuent une vérification d\'entreprise rapide. Les membres approuvés voient les prix de gros en direct.',
 's2t'=>'Sourcez & commandez','s2d'=>'Parcourez des annonces vérifiées — articles de marque et basiques textiles. Commandez avec séquestre et conditions claires.',
 's3t'=>'Échangez en confiance','s3d'=>'Contrôles d\'authenticité, historiques de provenance et un processus de litige équitable protègent chaque transaction.',
 'ok'=>"✓ Vous êtes sur la liste. Nous vous contacterons à l'ouverture du premier groupe.",
 'bad'=>'Veuillez saisir au moins votre nom et un e-mail valide.',
 'jt'=>"Rejoignez la liste d'attente",'js'=>'Soyez parmi les premiers membres vérifiés. Sans paiement, sans engagement.',
 'tg_s'=>'Je suis Vendeur','tg_b'=>'Je suis Acheteur',
 'f_name'=>'Nom complet','f_co'=>'Entreprise','f_email'=>'E-mail professionnel','f_country'=>'Pays',
 'm_s'=>'Que vendez-vous ? (marques, catégories)','m_b'=>'Que cherchez-vous à sourcer ?',
 'submit'=>'Demander un accès anticipé',
 'note'=>"En vous inscrivant, vous acceptez d'être contacté au sujet du lancement. Nous ne partageons jamais vos données.",
],
'it'=>[
 'tagline'=>"Il commercio all'ingrosso di moda B2B verificato in Europa",
 'meta'=>"Un marketplace B2B verificato, incentrato sull'autenticità, per moda di marca e tessile. Unisciti alla lista d'attesa.",
 'why'=>'Perché','how'=>'Come funziona','join_nav'=>"Unisciti alla lista",
 'pill'=>'Su invito · Lancio imminente',
 'h1'=>'Il modo <span class="acc">verificato</span> di commerciare<br>moda di marca, all\'ingrosso.',
 'sub'=>"Un marketplace B2B dove ogni venditore è verificato (KYC), ogni ordine è protetto da deposito a garanzia e ogni prodotto è tracciabile. Costruito sull'autenticità, fin dall'inizio.",
 'b_sell'=>'Unisciti come Venditore','b_buy'=>'Unisciti come Acquirente',
 'tr1'=>'Membri verificati (KYC)','tr2'=>'Protezione con deposito','tr3'=>'Pronto per il DPP UE',
 'p1t'=>'Solo venditori verificati','p1d'=>'KYC aziendale su ogni venditore — partita IVA, registrazione, identità. Nessun annuncio anonimo, nessuna incertezza.',
 'p2t'=>'Deposito & autenticità','p2d'=>"I fondi vengono rilasciati solo dopo la ricezione e la conferma dell'ordine. Controllo di contraffazione e mercato grigio integrato.",
 'p3t'=>'Passaporto Digitale di Prodotto','p3d'=>"Ogni articolo ha una cronologia di provenienza tracciabile — già pronto per le prossime regole DPP dell'UE.",
 'hsub'=>'Fiducia per progettazione — per entrambe le parti.',
 's1t'=>'Verificati','s1d'=>'Venditori e acquirenti completano una rapida verifica aziendale. I membri approvati vedono i prezzi all\'ingrosso in tempo reale.',
 's2t'=>'Cerca & ordina','s2d'=>'Sfoglia annunci verificati — capi di marca e basici tessili. Ordina con deposito a garanzia e condizioni chiare.',
 's3t'=>'Commercia con fiducia','s3d'=>'Controlli di autenticità, registri di provenienza e un processo di reclamo equo proteggono ogni transazione.',
 'ok'=>"✓ Sei in lista. Ti contatteremo all'apertura del primo gruppo.",
 'bad'=>"Inserisci almeno il tuo nome e un'email valida.",
 'jt'=>"Unisciti alla lista d'attesa",'js'=>'Sii tra i primi membri verificati. Nessun pagamento, nessun impegno.',
 'tg_s'=>'Sono un Venditore','tg_b'=>'Sono un Acquirente',
 'f_name'=>'Nome completo','f_co'=>'Azienda','f_email'=>'Email aziendale','f_country'=>'Paese',
 'm_s'=>'Cosa vendi? (marche, categorie)','m_b'=>'Cosa stai cercando?',
 'submit'=>"Richiedi l'accesso anticipato",
 'note'=>'Iscrivendoti accetti di essere contattato riguardo al lancio. Non condividiamo mai i tuoi dati.',
],
'es'=>[
 'tagline'=>"La venta mayorista de moda B2B verificada en Europa",
 'meta'=>"Un marketplace B2B verificado, centrado en la autenticidad, para moda de marca y textil. Únete a la lista de espera.",
 'why'=>'Por qué','how'=>'Cómo funciona','join_nav'=>"Únete a la lista",
 'pill'=>'Solo por invitación · Próximo lanzamiento',
 'h1'=>'La forma <span class="acc">verificada</span> de comerciar<br>moda de marca, al por mayor.',
 'sub'=>'Un marketplace B2B donde cada vendedor está verificado (KYC), cada pedido está protegido con depósito en garantía y cada producto es trazable. Construido con la autenticidad por delante.',
 'b_sell'=>'Únete como Vendedor','b_buy'=>'Únete como Comprador',
 'tr1'=>'Miembros verificados (KYC)','tr2'=>'Protección con depósito','tr3'=>'Listo para el DPP de la UE',
 'p1t'=>'Solo vendedores verificados','p1d'=>'KYC empresarial en cada vendedor — número de IVA, registro, identidad. Sin anuncios anónimos, sin conjeturas.',
 'p2t'=>'Depósito & autenticidad','p2d'=>'Los fondos se liberan solo tras recibir y confirmar el pedido. Detección de falsificaciones y mercado gris integrada.',
 'p3t'=>'Pasaporte Digital de Producto','p3d'=>'Cada artículo lleva un registro de procedencia trazable — listo hoy para las próximas normas DPP de la UE.',
 'hsub'=>'Confianza por diseño — para ambas partes.',
 's1t'=>'Verifícate','s1d'=>'Vendedores y compradores completan una rápida verificación empresarial. Los miembros aprobados ven los precios mayoristas en vivo.',
 's2t'=>'Busca & pide','s2d'=>'Explora anuncios verificados — productos de marca y básicos textiles. Pide con depósito en garantía y condiciones claras.',
 's3t'=>'Comercia con confianza','s3d'=>'Comprobaciones de autenticidad, registros de procedencia y un proceso de disputa justo protegen cada transacción.',
 'ok'=>'✓ Estás en la lista. Te contactaremos al abrir el primer grupo.',
 'bad'=>'Introduce al menos tu nombre y un correo válido.',
 'jt'=>'Únete a la lista de espera','js'=>'Sé de los primeros miembros verificados. Sin pago, sin compromiso.',
 'tg_s'=>'Soy Vendedor','tg_b'=>'Soy Comprador',
 'f_name'=>'Nombre completo','f_co'=>'Empresa','f_email'=>'Correo profesional','f_country'=>'País',
 'm_s'=>'¿Qué vendes? (marcas, categorías)','m_b'=>'¿Qué buscas comprar?',
 'submit'=>'Solicita acceso anticipado',
 'note'=>'Al unirte, aceptas que te contactemos sobre el lanzamiento. Nunca compartimos tus datos.',
],
'de'=>[
 'tagline'=>"Europas verifizierter B2B-Modegroßhandel",
 'meta'=>"Ein verifizierter, auf Echtheit ausgerichteter B2B-Großhandelsmarktplatz für Marken- und Textilmode. Tragen Sie sich in die Warteliste ein.",
 'why'=>'Warum','how'=>"So funktioniert's",'join_nav'=>"Warteliste beitreten",
 'pill'=>'Nur auf Einladung · Bald verfügbar',
 'h1'=>'Der <span class="acc">verifizierte</span> Weg, Markenmode<br>im Großhandel zu handeln.',
 'sub'=>"Ein B2B-Marktplatz, auf dem jeder Verkäufer KYC-geprüft ist, jede Bestellung durch Treuhand geschützt wird und jedes Produkt rückverfolgbar ist. Von Anfang an auf Echtheit ausgelegt.",
 'b_sell'=>'Als Verkäufer beitreten','b_buy'=>'Als Käufer beitreten',
 'tr1'=>'KYC-verifizierte Mitglieder','tr2'=>'Treuhand-Schutz','tr3'=>'EU-DPP-bereit',
 'p1t'=>'Nur verifizierte Verkäufer','p1d'=>'Geschäftliche KYC-Prüfung bei jedem Verkäufer — USt-IdNr., Registrierung, Identität. Keine anonymen Inserate, keine Unsicherheit.',
 'p2t'=>'Treuhand & Echtheit','p2d'=>'Gelder werden erst nach Erhalt und Bestätigung der Bestellung freigegeben. Fälschungs- und Graumarkt-Prüfung integriert.',
 'p3t'=>'Digitaler Produktpass','p3d'=>'Jeder Artikel trägt einen rückverfolgbaren Herkunftsnachweis — schon heute bereit für die kommende EU-DPP-Pflicht.',
 'hsub'=>'Vertrauen durch Design — für beide Seiten des Handels.',
 's1t'=>'Verifizieren lassen','s1d'=>'Verkäufer und Käufer durchlaufen eine schnelle Geschäftsverifizierung. Freigegebene Mitglieder sehen Live-Großhandelspreise.',
 's2t'=>'Finden & bestellen','s2d'=>'Durchstöbern Sie verifizierte Inserate — Markenware & Textil-Basics. Bestellen Sie mit Treuhand-Schutz und klaren Konditionen.',
 's3t'=>'Mit Vertrauen handeln','s3d'=>'Echtheitsprüfungen, Herkunftsnachweise und ein faires Streitverfahren schützen jede Transaktion.',
 'ok'=>'✓ Sie stehen auf der Liste. Wir melden uns, sobald wir die Gründungsgruppe öffnen.',
 'bad'=>'Bitte geben Sie mindestens Ihren Namen und eine gültige E-Mail-Adresse an.',
 'jt'=>'Der Gründungs-Warteliste beitreten','js'=>'Gehören Sie zu den ersten verifizierten Mitgliedern. Keine Zahlung, keine Verpflichtung.',
 'tg_s'=>'Ich bin Verkäufer','tg_b'=>'Ich bin Käufer',
 'f_name'=>'Vollständiger Name','f_co'=>'Unternehmen','f_email'=>'Geschäftliche E-Mail','f_country'=>'Land',
 'm_s'=>'Was verkaufen Sie? (Marken, Kategorien)','m_b'=>'Was möchten Sie beziehen?',
 'submit'=>'Frühzugang anfragen',
 'note'=>'Mit der Anmeldung stimmen Sie zu, zum Launch kontaktiert zu werden. Wir geben Ihre Daten niemals weiter.',
],
];
$t = $T[$lang];

$joined = isset($_GET['joined']);
$error  = isset($_GET['error']);

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

  .join{margin:90px auto;background:linear-gradient(180deg,var(--bg2),#101015);
    border:1px solid var(--line);border-radius:24px;padding:50px;max-width:720px;position:relative;overflow:hidden}
  .join:before{content:"";position:absolute;inset:-60% 0 auto 0;height:300px;
    background:radial-gradient(50% 60% at 50% 0,rgba(201,168,106,.12),transparent 70%)}
  .toggle{display:flex;background:#0c0c0f;border:1px solid var(--line);border-radius:999px;
    padding:5px;margin:0 auto 26px;width:fit-content;position:relative;z-index:1}
  .toggle button{border:0;background:transparent;color:var(--mut);padding:11px 24px;border-radius:999px;
    cursor:pointer;font:inherit;font-weight:600;font-size:14px;transition:.25s}
  .toggle button.on{background:var(--acc);color:#1a1408}
  form{display:grid;gap:14px;position:relative;z-index:1}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  label{font-size:13px;color:var(--mut);display:block;margin-bottom:6px}
  input,textarea{width:100%;background:#0c0c0f;border:1px solid var(--line);
    border-radius:10px;padding:13px 14px;color:var(--ink);font:inherit;font-size:15px;transition:border-color .2s}
  input:focus,textarea:focus{outline:0;border-color:var(--acc)}
  .hp{position:absolute;left:-9999px}
  .note{font-size:13px;color:var(--mut);text-align:center;margin-top:14px}
  .banner{padding:14px 18px;border-radius:12px;margin-bottom:22px;font-size:15px;text-align:center;position:relative;z-index:1}
  .ok{background:rgba(80,200,120,.12);border:1px solid rgba(80,200,120,.4);color:#9fe3b4}
  .bad{background:rgba(230,90,90,.12);border:1px solid rgba(230,90,90,.4);color:#f0a8a8}

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
    .row{grid-template-columns:1fr}
    .join{padding:32px 22px;margin:64px auto}
    .hero{padding:64px 0 44px}
    .trustline{gap:14px}
  }
</style>
</head>
<body>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="?lang=<?= $lang ?>#top" aria-label="<?= htmlspecialchars($BRAND) ?>">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span><?= htmlspecialchars($BRAND) ?></span>
    </a>
    <div class="nav-links">
      <a href="shop.php"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
      <a href="requests.php"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
      <a class="hidem-s" href="#how"><?= $t['how'] ?></a>
      <a class="hidem-s" href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
      <span class="langs">
        <?php $i=0; foreach($LANGS as $c=>$l){ echo $i++? '<span class="sep">·</span>':''; ?><a href="?lang=<?= $c ?>" class="<?= $c===$lang?'on':'' ?>"><?= $l ?></a><?php } ?>
      </span>
      <a href="#join" class="nav-cta"><?= $t['join_nav'] ?></a>
    </div>
    <button class="burger" id="burger" aria-label="Menu" aria-expanded="false" aria-controls="mnav">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
  </nav></div>
  <div class="mnav" id="mnav">
    <a href="shop.php"><?= ['en'=>'Catalog','fr'=>'Catalogue','it'=>'Catalogo','es'=>'Catálogo','de'=>'Katalog'][$lang] ?></a>
    <a href="requests.php"><?= ['en'=>'Requests','fr'=>'Demandes','it'=>'Richieste','es'=>'Solicitudes','de'=>'Anfragen'][$lang] ?></a>
    <a href="#how"><?= $t['how'] ?></a>
    <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
    <a href="#join"><?= $t['join_nav'] ?></a>
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
      <a class="btn btn-p" href="#join" onclick="setType('seller')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h13l5 5v5h-2"/><path d="M3 7v10h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
        <?= $t['b_sell'] ?>
      </a>
      <a class="btn btn-o" href="#join" onclick="setType('buyer')">
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
    <?php if ($joined): ?><div class="banner ok"><?= $t['ok'] ?></div>
    <?php elseif ($error): ?><div class="banner bad"><?= $t['bad'] ?></div><?php endif; ?>
    <h2 class="sec-title" style="font-size:30px"><?= $t['jt'] ?></h2>
    <p class="sec-sub"><?= $t['js'] ?></p>
    <div class="toggle">
      <button type="button" id="t-seller" class="on" onclick="setType('seller')"><?= $t['tg_s'] ?></button>
      <button type="button" id="t-buyer" onclick="setType('buyer')"><?= $t['tg_b'] ?></button>
    </div>
    <form action="join.php" method="post">
      <input type="hidden" name="type" id="type" value="seller">
      <input type="hidden" name="lang" value="<?= $lang ?>">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="row">
        <div><label><?= $t['f_name'] ?> *</label><input name="name" required></div>
        <div><label><?= $t['f_co'] ?></label><input name="company"></div>
      </div>
      <div class="row">
        <div><label><?= $t['f_email'] ?> *</label><input type="email" name="email" required></div>
        <div><label><?= $t['f_country'] ?></label><input name="country"></div>
      </div>
      <div><label id="msglabel"><?= $t['m_s'] ?></label><textarea name="message" rows="3"></textarea></div>
      <button class="btn btn-p" type="submit" style="justify-content:center"><?= $t['submit'] ?></button>
      <p class="note"><?= $t['note'] ?></p>
    </form>
  </section>
</div>

<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)"><?= htmlspecialchars($BRAND) ?></b> — <?= $t['tagline'] ?>
      <div style="margin-top:6px;opacity:.8"><?= htmlspecialchars($COMPANY) ?></div></div>
    <div class="foot-links">
      <a href="#why"><?= $t['why'].' '.htmlspecialchars($BRAND) ?></a>
      <a href="#how"><?= $t['how'] ?></a>
      <a href="mailto:<?= htmlspecialchars($CONTACT) ?>" class="acc"><?= htmlspecialchars($CONTACT) ?></a>
    </div>
  </div>
</footer>

<script>
  var burger=document.getElementById('burger'), mnav=document.getElementById('mnav');
  burger.addEventListener('click',function(){var o=mnav.classList.toggle('open');burger.setAttribute('aria-expanded',o);});
  mnav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){mnav.classList.remove('open');});});

  var MSG={s:<?= json_encode($t['m_s']) ?>,b:<?= json_encode($t['m_b']) ?>};
  function setType(t){
    document.getElementById('type').value=t;
    document.getElementById('t-seller').classList.toggle('on',t==='seller');
    document.getElementById('t-buyer').classList.toggle('on',t==='buyer');
    document.getElementById('msglabel').textContent = t==='seller'?MSG.s:MSG.b;
  }

  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el){io.observe(el);});
</script>
</body>
</html>
