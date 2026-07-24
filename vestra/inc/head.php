<?php
/** VESTRA shared header — start session, member gate, nav. Set $PAGE before include. */
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$AUTH_USER = auth_user();
/* The site owner (admin session) sees the catalogue exactly as a fully-verified
   member does — real product photos, prices and seller names — so reviewing a
   product via the admin "View ↗" link never shows the locked brand card. */
$IS_ADMIN = !empty($_SESSION['vadmin']);
$MEMBER = $IS_ADMIN || $AUTH_USER !== null || !empty($_SESSION['member']);
/* Freischaltung gate: product photos + seller identities are visible only to APPROVED
   accounts — signed in AND (active / KYB-approved / verification documents submitted).
   A mere registration without documents never unlocks photos or seller names. */
$APPROVED = $IS_ADMIN || auth_user_approved($AUTH_USER);
$MSG_UNREAD = 0;
if ($AUTH_USER) { require_once __DIR__.'/messages.php'; $MSG_UNREAD = vestra_msg_unread_count($AUTH_USER['id']); }
$BRAND  = 'VESTRA';
$PAGE   = $PAGE ?? $BRAND;
$ACC    = '#c9a86a';
?><!DOCTYPE html>
<html lang="<?= vlang() ?>">
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
$_seoQ     = $_GET; unset($_seoQ['lang']);            // keep product id etc., drop lang
$_seoBase  = $_seoPath . (($qs = http_build_query($_seoQ)) !== '' ? '?'.$qs : '');
$_seoSep   = ($qs !== '' ? '&' : '?');
$_seoHref  = fn($l) => $SEO_HOST.$_seoBase.($l === 'en' ? '' : $_seoSep.'lang='.$l);
$CANONICAL = $_seoHref(vlang());
$OG_IMAGE  = $SEO_HOST.'/inc/og-image.png';
$OG_LOCALE = ['en'=>'en_US','de'=>'de_DE','fr'=>'fr_FR','it'=>'it_IT','es'=>'es_ES'][vlang()] ?? 'en_US';
$NOINDEX   = $NOINDEX ?? false;
?>
<meta name="description" content="<?= htmlspecialchars($META) ?>">
<link rel="canonical" href="<?= htmlspecialchars($CANONICAL) ?>">
<?php foreach (array_keys(vlang_list()) as $_l): ?>
<link rel="alternate" hreflang="<?= $_l ?>" href="<?= htmlspecialchars($_seoHref($_l)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($_seoHref('en')) ?>">
<meta name="robots" content="<?= $NOINDEX ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>">
<meta property="og:site_name" content="VESTRA">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?>">
<meta property="og:description" content="<?= htmlspecialchars($META) ?>">
<meta property="og:url" content="<?= htmlspecialchars($CANONICAL) ?>">
<meta property="og:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<meta property="og:locale" content="<?= $OG_LOCALE ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($META) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
<meta name="theme-color" content="#0e0e11">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/inc/style.css">
<?php
// ── Structured data (JSON-LD): site-wide Organization + WebSite, plus any
//    page-specific schema a page set in $JSONLD before including this header. ──
$_ld = array_merge([
  [
    '@context' => 'https://schema.org', '@type' => 'Organization',
    'name' => 'VESTRA', 'url' => $SEO_HOST, 'logo' => $OG_IMAGE,
    'description' => 'Verified B2B fashion wholesale marketplace — branded apparel and textile basics from KYC-verified sellers across Europe.',
    'areaServed' => 'EU', 'email' => 'support@vestrasales.com',
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
</script>
