<?php
/** VESTRA shared header — start session, member gate, nav. Set $PAGE before include. */
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/money.php';   // gosterim para birimi (EUR tabani, USD/AUD/CAD cevrimi)

/* Never let a cache hold on to a rendered page. These are dynamic: /shop reflects
   listings.json, and prices, MOQs and size runs change without any file being
   deployed, so there is no URL change for a cache to notice. A CDN or browser
   holding an old copy shows a catalogue that silently disagrees with the database
   -- which is exactly what happened here: the server was rendering 96 products
   while the site still showed a much older 8.
   Sent before any output; guarded because some entry points emit earlier. */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    // Cloudflare and other edges honour this even under a "cache everything" rule.
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
    header('Vary: Cookie, Accept-Language');   // member vs guest, and per language
}

if (session_status() === PHP_SESSION_NONE) session_start();
$AUTH_USER = auth_user();
/* The site owner (admin session) sees the catalogue exactly as a fully-verified
   member does — real product photos, prices and seller names — so reviewing a
   product via the admin "View ↗" link never shows the locked brand card. */
$IS_ADMIN = !empty($_SESSION['vadmin']);
$MEMBER = $IS_ADMIN || $AUTH_USER !== null || !empty($_SESSION['member']);

/* vestra_join_cta() -- "giris yapmisa Kayit Ol gosterme" kurali -- inc/i18n.php'de.
   Buradan tasindi: index.php kendi <head>'ini basip head.php'yi dahil etmiyor,
   dolayisiyla ana sayfa fonksiyonu bulamayip fatal veriyordu. i18n.php'yi
   head.php de index.php de yukluyor. */
/* Freischaltung gate: product photos, prices and seller identities are visible only to
   APPROVED accounts — signed in AND activated by the owner (status 'active' /
   kyb_status 'approved').
   Submitting a document is no longer enough on its own; the comment used to say it was,
   and said so for a while after the rule changed. A stale comment about an access rule
   is worse than none: the next person reads it and believes uploading opens the account. */
$APPROVED = $IS_ADMIN || auth_user_approved($AUTH_USER);
/* Fiyat kapisi = onay kapisi (KURAL 2, 31 Agu 2026): toptan fiyat, siparis ve
   line-sheet operatorun hesabi acmasiyla birlikte acilir. Belge yuklemek
   kapiyi ACMAZ; belge uyari olarak istenir. $PRICES ile $APPROVED bu yuzden
   ayni cevabi verir; ikisi de tutuluyor cunku sayfalar ikisini de okuyor. */
$PRICES     = $IS_ADMIN || auth_prices_unlocked($AUTH_USER);
$PRICE_GATE = $IS_ADMIN ? '' : auth_price_gate_reason($AUTH_USER);   // '' | 'guest' | 'approval'
/* Kilidi gosteren her sayfa "peki nereye yukleyecegim" sorusunu da cevaplamali;
   belge sekmesi alici ve satici panelinde ayri adreste. */
$KYC_URL = ($AUTH_USER && ($AUTH_USER['type'] ?? '') === 'seller') ? '/seller?tab=kyc' : '/buyer?tab=kyc';
$MSG_UNREAD = 0;
if ($AUTH_USER) { require_once __DIR__.'/messages.php'; $MSG_UNREAD = vestra_msg_unread_count($AUTH_USER['id']); }
$BRAND  = 'VESTRA';
$PAGE   = $PAGE ?? $BRAND;
$ACC    = '#c9a86a';
?><!DOCTYPE html>
<html lang="<?= vlang() ?>" dir="<?= vlang_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?></title>
<?php
$META = $META ?? t('Verified B2B fashion wholesale — branded apparel & textile basics from KYC-verified sellers. Invoice-based ordering across Europe.');
// ── SEO: canonical + multilingual hreflang (self-referencing per language) ──
$SEO_HOST  = 'https://vestrasales.com';
$_seoPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$_seoPath  = preg_replace('/\.php$/', '', $_seoPath) ?: '/';   // canonical uses clean URLs (.htaccess serves them)
/* Read the query from the URI the visitor actually requested, NOT from $_GET.
   .htaccess rewrites clean URLs into internal parameters -- /wholesale/lacoste arrives
   as wholesale.php?brand=lacoste -- and $_GET shows the rewritten form. Building the
   canonical from it published /wholesale/lacoste?brand=lacoste: the same page under a
   second address, which is exactly the duplicate a canonical exists to prevent. */
parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $_seoQ);
unset($_seoQ['lang']);                                 // keep product id etc., drop lang
$_seoBase  = $_seoPath . (($qs = http_build_query($_seoQ)) !== '' ? '?'.$qs : '');
$_seoSep   = ($qs !== '' ? '&' : '?');
$_seoHref  = fn($l) => $SEO_HOST.$_seoBase.($l === 'en' ? '' : $_seoSep.'lang='.$l);
$CANONICAL = $_seoHref(vlang());
$OG_IMAGE  = $OG_IMAGE ?? $SEO_HOST.'/inc/og-image.png';   // pages may set a specific image (e.g. product photo)
$OG_LOCALES = ['en'=>'en_US','fr'=>'fr_FR','es'=>'es_ES','it'=>'it_IT','de'=>'de_DE','pt'=>'pt_PT','ru'=>'ru_RU','ar'=>'ar_AR'];
$OG_LOCALE  = $OG_LOCALES[vlang()] ?? 'en_US';
$NOINDEX   = $NOINDEX ?? false;
?>
<meta name="description" content="<?= htmlspecialchars($META) ?>">
<?php
/* Keywords, per language. Google has ignored this tag for over a decade — it is here
   because the operator asked for it and it is harmless; Bing/Yandex still read it
   weakly. It is NOT what ranks the site: the <title>, the description above and real
   indexable content do that. Translated so it at least matches the page language. */
$KEYWORDS = $KEYWORDS ?? t('wholesale fashion, B2B fashion marketplace, designer clothing wholesale, branded apparel wholesale, boutique supplier, buy wholesale clothing Europe, verified wholesale sellers, fashion sourcing');
/* Append the houses actually in stock, as "<brand> <wholesale-word-in-this-language>".
   Category terms alone match nobody: the query a boutique types is a brand name plus
   "Großhandel" / "al por mayor" / "ingrosso". Built from live inventory (see
   vestra_seo_brand_keywords) so it never advertises a brand we no longer carry. */
if (function_exists('vestra_seo_brand_keywords')) {
    $_bkw = vestra_seo_brand_keywords(vlang());
    if ($_bkw !== '') $KEYWORDS = ($KEYWORDS !== '' ? $KEYWORDS.', ' : '').$_bkw;
}
/* And the categories actually in stock, the other half of what a buyer types
   ("sneakers wholesale", "T-Shirts Großhandel"); localised, from live inventory. */
if (function_exists('vestra_seo_cat_keywords')) {
    $_ckw = vestra_seo_cat_keywords(vlang());
    if ($_ckw !== '') $KEYWORDS = ($KEYWORDS !== '' ? $KEYWORDS.', ' : '').$_ckw;
}
?>
<meta name="keywords" content="<?= htmlspecialchars($KEYWORDS) ?>">
<link rel="canonical" href="<?= htmlspecialchars($CANONICAL) ?>">
<?php /* Base languages plus their regional variants (de-AT, fr-BE, en-NL, pt-BR …), all
         pointing at the same language page -- see vlang_hreflang_map() in i18n.php. */
      foreach (vlang_hreflang_map() as $_hl => $_l): ?>
<link rel="alternate" hreflang="<?= $_hl ?>" href="<?= htmlspecialchars($_seoHref($_l)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($_seoHref('en')) ?>">
<meta name="robots" content="<?= $NOINDEX ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>">
<?php
/* Arama motoru panel dogrulamasi. Token .env'den geliyor (public_html disinda),
   koda gomulu degil -- boylece token degistiginde dosya duzenlemeye gerek yok.
   Degisken bos ise HIC etiket basilmiyor: bos content'li bir dogrulama etiketi
   Google'da "dogrulama basarisiz" olarak gorunur, hic olmamasindan kotudur.
   Etiket sadece ana sayfada degil HER sayfada cikiyor; Google dogrulamayi
   genelde kokten yapar ama alt sayfadan da kontrol edebiliyor. */
$_verify = [
  'google-site-verification' => trim((string)($_ENV['GOOGLE_SITE_VERIFICATION'] ?? '')),
  'msvalidate.01'            => trim((string)($_ENV['BING_SITE_VERIFICATION']   ?? '')),
  'yandex-verification'      => trim((string)($_ENV['YANDEX_SITE_VERIFICATION'] ?? '')),
];
foreach ($_verify as $_vName => $_vTok):
  if ($_vTok === '') continue; ?>
<meta name="<?= $_vName ?>" content="<?= htmlspecialchars($_vTok, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
<meta property="og:site_name" content="VESTRA">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?>">
<meta property="og:description" content="<?= htmlspecialchars($META) ?>">
<meta property="og:url" content="<?= htmlspecialchars($CANONICAL) ?>">
<meta property="og:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<meta property="og:locale" content="<?= $OG_LOCALE ?>">
<?php foreach ($OG_LOCALES as $_l => $_loc): if ($_l === vlang() || !isset(vlang_list()[$_l])) continue; ?>
<meta property="og:locale:alternate" content="<?= $_loc ?>">
<?php endforeach; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($META) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<meta name="theme-color" content="#0e0e11">
<?php /* Installed-app behaviour. iOS does not read the manifest's `display` on older
         versions, so without apple-mobile-web-app-capable an "Add to Home Screen"
         icon opened inside Safari's chrome instead of as a standalone app —
         Android honoured `display: standalone` all along, which is why this only
         ever looked broken on iPhone. mobile-web-app-capable is the standard
         spelling of the same thing. Status bar is opaque black to match the dark
         header; black-translucent would draw under the clock and needs
         safe-area padding the layout does not carry yet. */ ?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="VESTRA">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/inc/style.css?v=<?= @filemtime(__DIR__.'/style.css') ?: '1' ?>">
<?php
// ── Structured data (JSON-LD): site-wide Organization + WebSite, plus any
//    page-specific schema a page set in $JSONLD before including this header. ──
$_ld = array_merge([
  [
    '@context' => 'https://schema.org', '@type' => 'Organization',
    'name' => 'VESTRA', 'url' => $SEO_HOST, 'logo' => $OG_IMAGE,
    /* Localised: a search engine reads this in the language of the page it found, and an
       English sentence on a German page is a mismatch it can see. The brands come from
       live stock so the entity description names what is actually sold here. */
    'description' => trim(t('Verified B2B fashion wholesale marketplace — branded apparel and textile basics from KYC-verified sellers across Europe.')
        .(($_ldBrands = (function_exists('vestra_seo_brands') ? implode(', ', vestra_seo_brands(10)) : '')) !== ''
            ? ' '.sprintf(t('Houses in stock: %s.'), $_ldBrands) : '')),
    /* Not 'EU' any more, and that mattered: the marketplace ships to Japan, Korea,
       Australia, the Gulf, Brazil and Chile, and an entity that declares itself European
       is telling every search engine outside Europe that it is not for them. */
    'areaServed' => ['Europe', 'Asia', 'Oceania', 'South America', 'Middle East', 'Africa'],
    'email' => 'support@vestrasales.com',
    'inLanguage' => vlang(),
    /* What the business deals in, as an entity: the houses in stock and the live
       categories (localised), so a crawler reading the German page sees "Sneaker",
       "Schuhe" next to the brand names. Same live lists as the keyword tag. */
    'knowsAbout' => array_values(array_unique(array_merge(
        function_exists('vestra_seo_brands') ? vestra_seo_brands(14) : [],
        function_exists('vestra_seo_knows_about') ? vestra_seo_knows_about(14) : []))),
  ],
  [
    '@context' => 'https://schema.org', '@type' => 'WebSite',
    'name' => 'VESTRA', 'url' => $SEO_HOST,
    'potentialAction' => [
      '@type' => 'SearchAction',
      'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $SEO_HOST.'/shop?q={search_term_string}'],
      'query-input' => 'required name=search_term_string',
    ],
  ],
], $JSONLD ?? []);
foreach ($_ld as $_schema) {
  echo '<script type="application/ld+json">'.json_encode($_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>'."\n";
}
?>
</head>
<body>
<?php $panel = ($AUTH_USER['type'] ?? 'buyer') === 'seller' ? '/seller' : '/buyer'; ?>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="/">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span><?= $BRAND ?><span class="logo-sub">sales</span></span>
    </a>
    <div class="nav-links" id="navmenu">
      <a href="/shop" class="<?= ($NAV ?? '')==='shop'?'on':'' ?>"><?= t('Catalog') ?></a>
      <a href="/groups" class="<?= ($NAV ?? '')==='groups'?'on':'' ?>"><?= t('Group buys') ?></a>
      <a href="/requests" class="<?= ($NAV ?? '')==='requests'?'on':'' ?>"><?= t('Requests') ?></a>
      <a href="/journal" class="<?= ($NAV ?? '')==='journal'?'on':'' ?>"><?= t('Journal') ?></a>
      <a href="/help" class="<?= ($NAV ?? '')==='help'?'on':'' ?>"><?= t('Help') ?></a>
      <a href="/seller" class="<?= ($NAV ?? '')==='sell'?'on':'' ?>"><?= t('Sell') ?></a>
      <?php if ($MEMBER): ?>
        <?php if (($AUTH_USER['type']??'') === 'seller'): ?>
          <a href="/membership" class="<?= ($NAV ?? '')==='membership'?'on':'' ?>"><?= t('Membership') ?></a>
        <?php endif; ?>
        <a href="<?= $panel ?>" class="<?= ($NAV ?? '')==='account'?'on':'' ?>"><?= t('Account') ?></a>
        <span class="memberpill">✓</span>
      <?php else: ?>
        <a href="/login"><?= t('Sign in') ?></a>
      <?php endif; ?>
      <?= vlang_switcher() ?>
      <?= vestra_cur_switcher() ?>
    </div>
    <div class="nav-actions">
      <?php if ($AUTH_USER): ?>
      <a class="cartlink" href="<?= $panel ?>?tab=messages" aria-label="<?= htmlspecialchars(t('Messages')) ?>" title="<?= htmlspecialchars(t('Messages')) ?>">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v12H8l-4 4V5z"/></svg>
        <?php if ($MSG_UNREAD > 0): ?><span class="badge"><?= $MSG_UNREAD ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
      <a class="cartlink" href="/cart" aria-label="<?= htmlspecialchars(t('Cart')) ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1.2 11H6.2L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        <span class="badge" id="cartCount" style="display:none">0</span>
      </a>
      <button class="navtoggle" type="button" aria-label="Menu" aria-controls="navmenu" aria-expanded="false"
        onclick="var m=document.getElementById('navmenu');var o=m.classList.toggle('open');this.setAttribute('aria-expanded',o);this.classList.toggle('on',o)">
        <svg class="ico-bars" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg class="ico-x" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
  </nav></div>
</header>
<?php
/* Bekleyen hesap uyarisi. Giris yapmis ama henuz aktiflestirilmemis kullanici
   fiyatlari ve fotograflari goremiyor -- ve NEDENINI bilmiyor. Belge yukleme
   sayfasi acik (buyer.php / seller.php onay sarti tasimiyor), ama kimse oraya
   yonlendirmiyorsa acik olmasinin bir anlami yok: kullanici bos bir katalog
   gorup gidiyor.
   Yonetici bu uyariyi gormez ($IS_ADMIN zaten APPROVED). */
if ($AUTH_USER && !$APPROVED):
  $kycUrl = (($AUTH_USER['type'] ?? '') === 'seller') ? '/seller?tab=kyc' : '/buyer?tab=kyc';
?>
<div class="vpending">
  <strong><?= t('Your account is being reviewed.') ?></strong>
  <?php /* Belge adi ziyaretcinin ULKESINE gore: beyan ettigi ulke, yoksa kayit
           IP'sinin ulkesi. Almanyali icin "(Gewerbeschein)", Fransizli icin
           "(extrait Kbis)", bilinmeyen ulkede sadece notr ifade. */
        $docPhrase = auth_trade_doc_phrase(vestra_visitor_cc($AUTH_USER));
        /* Kapiyi ONAY acar (KURAL 2). Eski metin "belgeyi yukleyince fiyatlar
           aninda acilir" diyordu -- 31 Agustos'tan beri yanlis. Belgesi bizde
           olana "yukleyin" de denmez; ona yalnizca bekledigi soylenir. */
        $docDone = in_array(auth_trade_doc_status($AUTH_USER), ['uploaded','approved'], true); ?>
  <?php if ($docDone): ?>
    <?= t('Your documents are with us. Trade prices, ordering and line sheets open as soon as we activate the account — usually the same day.') ?>
  <?php else: ?>
    <?= sprintf(t('Trade prices, ordering and line sheets open as soon as we activate it — usually the same day. You can add your %s in the meantime.'), $docPhrase) ?>
    <a href="<?= $kycUrl ?>"><?= t('Add document') ?> →</a>
  <?php endif; ?>
</div>
<style>
  /* Renkler SABIT, tema degiskeni degil: bu bant baslik ile sayfa govdesi
     arasinda duruyor ve acik temali sayfalarda (katalog, paneller) --ink hala
     koyu temanin krem degeriydi -- yazi bej zeminde GORUNMUYORDU (2 Eyl 2026
     ekran goruntusu). Sicak bej zemin + koyu yazi her iki temada okunur. */
  .vpending{background:#efe4cf;border-bottom:1px solid #c9a86a;
    padding:12px 20px;font-size:14px;line-height:1.5;color:#2a2418;
    display:flex;gap:6px 10px;align-items:baseline;flex-wrap:wrap;justify-content:center;text-align:center}
  .vpending strong{color:#7d5f22}
  .vpending a{font-weight:700;color:#7d5f22;white-space:nowrap;text-decoration:underline}
  @media(max-width:640px){.vpending{font-size:13px;padding:10px 16px}}
</style>
<?php endif; ?>
<script>
/* close the mobile menu on outside tap / Escape */
document.addEventListener('click',function(e){
  var m=document.getElementById('navmenu'),t=document.querySelector('.navtoggle');
  if(m&&m.classList.contains('open')&&!m.contains(e.target)&&!t.contains(e.target)){
    m.classList.remove('open');t.classList.remove('on');t.setAttribute('aria-expanded','false');
  }
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){var m=document.getElementById('navmenu'),t=document.querySelector('.navtoggle');
    if(m){m.classList.remove('open');t.classList.remove('on');t.setAttribute('aria-expanded','false');}}
});
/* Para birimi menusu: disariya tiklayinca ve Escape'te kapansin. <details>
   kendi basina kapanmaz; secenege tiklandiginda sayfa zaten yenileniyor ama
   fikrini degistiren kullaniciyi acik bir menuyle birakmayalim. */
document.addEventListener('click',function(e){
  document.querySelectorAll('details.cursw[open]').forEach(function(d){
    if(!d.contains(e.target)) d.removeAttribute('open');
  });
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape') document.querySelectorAll('details.cursw[open]').forEach(function(d){
    d.removeAttribute('open');
  });
});
</script>
