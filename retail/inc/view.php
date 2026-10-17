<?php
/**
 * Görünüm katmanı — düzen, başlık/altlık, ürün kartları, SEO
 * ----------------------------------------------------------
 * Şablon motoru yok: PHP zaten şablon motoru. Kural tek — çıktı DAİMA h()
 * üzerinden geçer, tek istisna bilinçli olarak HTML üreten yardımcılar.
 *
 * Tipografi sistem yazı tipleriyle kurulu. Google Fonts kasten kullanılmadı:
 * Almanya'da yazı tipini Google'ın sunucusundan çekmek ziyaretçinin IP'sini
 * ABD'ye aktarmak sayılıyor (LG München 3 O 17493/20) ve rıza gerektiriyor.
 * Rıza bandı istemediğimiz için hiçbir dış kaynak yok — bu yüzden bu site
 * çerez bandı OLMADAN da uyumlu.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/stripe.php';   // vr_payment_methods() ödeme güven satırı için
require_once __DIR__ . '/lists.php';    // Merkliste + son bakılanlar
require_once __DIR__ . '/sizing.php';   // beden tabloları — ürün sayfası ve rehber ortak
require_once __DIR__ . '/fx.php';       // gösterge para birimi (dünya alıcısı için)
require_once __DIR__ . '/customers.php'; // alıcı hesapları (başlıktaki kişi ikonu)
require_once __DIR__ . '/thumbs.php';    // hazır küçük kopyalar düz dosya olarak (PHP'siz)
require_once __DIR__ . '/stats.php';     // ziyaretçi istatistiği, IP engeli (yönetim paneli)

/** Sürüm etiketi — CSS/JS önbelleğini deploy'da tazelemek için. */
function vr_asset_v(): string
{
    static $v = null;
    if ($v !== null) return $v;
    /* Stil VE betik: yalniz CSS'e bakilinca sadece JS degisen bir yayinda surum ayni
       kaliyordu ve kurulu uygulamanin service worker'i eski betigi onbellekten
       vermeye devam ediyordu (12 Eki 2026, bildirim duzeltmesi). */
    $m = 0;
    foreach (['/assets/css/maxsales.css', '/assets/js/maxsales.js', '/assets/js/boot.js'] as $f) {
        if (is_readable(VR_ROOT . $f)) $m = max($m, (int)filemtime(VR_ROOT . $f));
    }
    return $v = $m ? (string)$m : '1';
}

/**
 * Sayfa başlangıcı.
 * $o: title, desc, canonical, body_class, jsonld (dizi|dizi listesi), robots,
 *     dark (bool → koyu üst çubuk), og_image
 */
function vr_layout_start(array $o = []): void
{
    vr_ip_gate();
    vr_track_view();
    $brand = (string)vr_config('brand');
    $title = trim((string)($o['title'] ?? ''));
    $full  = $title === '' ? $brand . ' — ' . t('tagline') : $title . ' · ' . $brand;
    $desc  = trim((string)($o['desc'] ?? t('tagline')));
    // Kendine-referanslı canonical: ASIL host (vr_canonical_origin) + mevcut
    // yol ve kimlik parametreleri (id/lot/brand/cat) + dil. Query'yi KESMİYORUZ
    // — eskiden strtok(...,'?') tüm sorguyu atıyor ve her ürün/los/yazı tek
    // çıplak /product.php (ya da /outlet.php, /journal.php) adresine çöküyordu;
    // bireysel sayfalar indeksten düşüyordu. Host da artık her zaman asıl adres,
    // www/test alt adlarında farklı canonical üretilmesin.
    $canon = (string)($o['canonical'] ?? vr_canonical_origin() . vr_lang_url(vr_lang()));

    // Yardımcı adreslerden (test alt adı, cPanel alt adları) gelen HTML
    // indekslenmesin: canonical zaten asıl adrese işaret ediyor; bir de sayfa
    // seviyesinde noindex veriyoruz ki robots.txt tek savunma kalmasın.
    if (!vr_is_canonical_host() && !headers_sent()) {
        header('X-Robots-Tag: noindex, follow');
    }

    // Open Graph görseli ve tipi: sayfa verirse onu, yoksa marka panosu.
    $ogType  = (string)($o['og_type'] ?? 'website');
    $ogImage = !empty($o['og_image'])
        ? (str_starts_with((string)$o['og_image'], 'http') ? (string)$o['og_image'] : vr_canonical_origin() . $o['og_image'])
        : vr_canonical_origin() . vr_base_url() . '/assets/art.php?' . http_build_query(['s' => strtolower((string)vr_config('brand')), 'w' => 1200, 'h' => 630]);
    $seo     = (array)vr_config('seo', []);
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="<?= h(vr_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($full) ?></title>
<meta name="description" content="<?= h(mb_substr($desc, 0, 300)) ?>">
<?php if (!empty($o['robots'])): ?>
<meta name="robots" content="<?= h((string)$o['robots']) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= h($canon) ?>">
<?php foreach ((array)vr_config('languages', ['en']) as $lg): ?>
<link rel="alternate" hreflang="<?= h($lg) ?>" href="<?= h(vr_canonical_origin() . vr_lang_url($lg)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= h(vr_canonical_origin() . vr_lang_url((string)vr_config('primary_lang', 'en'))) ?>">

<?php // Arama motoru doğrulaması: token girilmişse meta olarak basılır (boşsa hiç çıkmaz). ?>
<?php if (!empty($seo['google_verify'])): ?><meta name="google-site-verification" content="<?= h((string)$seo['google_verify']) ?>"><?php endif; ?>
<?php if (!empty($seo['bing_verify'])): ?><meta name="msvalidate.01" content="<?= h((string)$seo['bing_verify']) ?>"><?php endif; ?>
<?php if (!empty($seo['yandex_verify'])): ?><meta name="yandex-verification" content="<?= h((string)$seo['yandex_verify']) ?>"><?php endif; ?>

<meta property="og:type" content="<?= h($ogType) ?>">
<meta property="og:site_name" content="<?= h($brand) ?>">
<meta property="og:title" content="<?= h($full) ?>">
<meta property="og:description" content="<?= h(mb_substr($desc, 0, 200)) ?>">
<meta property="og:url" content="<?= h($canon) ?>">
<meta property="og:image" content="<?= h($ogImage) ?>">
<meta property="og:image:alt" content="<?= h($full) ?>">
<meta property="og:locale" content="<?= h(str_replace('-', '_', vr_locale())) ?>">
<?php foreach ((array)vr_config('languages', ['en']) as $lg): if ($lg === vr_lang()) continue; ?>
<meta property="og:locale:alternate" content="<?= h(str_replace('-', '_', vr_locale($lg))) ?>">
<?php endforeach; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($full) ?>">
<meta name="twitter:description" content="<?= h(mb_substr($desc, 0, 200)) ?>">
<meta name="twitter:image" content="<?= h($ogImage) ?>">
<meta name="theme-color" content="#000000">

<link rel="icon" href="<?= h(vr_url('assets/mark.php')) ?>" type="image/svg+xml">
<?php // Uygulama: Android "Yükle", iPhone "Ana Ekrana Ekle" — bkz. app.php ?>
<link rel="manifest" href="<?= h(vr_url('manifest.php')) ?>">
<link rel="apple-touch-icon" href="<?= h(vr_url('assets/app/apple-touch-icon.png')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h($brand) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/maxsales.css', ['v' => vr_asset_v()])) ?>">
<?php // Eşzamanlı ve küçük: cihaz türü ilk boyamadan önce belli olsun (bkz. assets/js/boot.js). ?>
<script src="<?= h(vr_url('assets/js/boot.js', ['v' => vr_asset_v()])) ?>"></script>
<?php // JS kapalıyken beliriş efekti hiç uygulanmasın (scripting:enabled'i
      // desteklemeyen eski tarayıcılar için ikinci emniyet). ?>
<noscript><style>[data-reveal]{opacity:1 !important;transform:none !important}</style></noscript>
<?php
    // JSON-LD: mağaza kimliği her sayfada, sayfaya özel şema varsa üstüne eklenir.
    $graph = [vr_jsonld_website(), vr_jsonld_org()];
    if (!empty($o['jsonld'])) {
        $extra = $o['jsonld'];
        $graph = array_merge($graph, isset($extra[0]) && is_array($extra[0]) ? $extra : [$extra]);
    }
?>
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body class="<?= h((string)($o['body_class'] ?? '')) ?>">
<a class="skip" href="#main"><?= te('skip_to_content') ?></a>
<?php
    vr_header();
    echo '<main id="main">';
    vr_flash_render();
}

function vr_layout_end(): void
{
    echo '</main>';
    vr_footer();
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($page !== 'checkout.php') vr_push_prompt();
    if (!in_array($page, ['app.php', 'checkout.php'], true)) vr_appbar();
    if ($page !== 'checkout.php') vr_tabbar($page);
    ?>
<script src="<?= h(vr_url('assets/js/maxsales.js', ['v' => vr_asset_v()])) ?>" defer></script>
</body>
</html>
<?php
}

// ------------------------------------------------------- uygulama + bildirim
/**
 * Bildirim kutusu. Durum (açık/kapalı/izin reddedildi/önce ana ekrana ekle)
 * cihazda belli olduğu için hepsi basılır, CSS yalnız data-state'e uyanı
 * gösterir (bkz. maxsales.js "Bildirimler"). JS yoksa kutu hiç görünmez —
 * bildirim zaten JS'siz olmuyor.
 * $variant: 'card' (uygulama sayfası, ana sayfa şeridi) | 'prompt' (alt kart)
 */
function vr_push_attrs(): string
{
    require_once __DIR__ . '/push.php';
    return ' data-push data-state="init"'
        . ' data-key="' . h(vr_push_public_key()) . '"'
        . ' data-csrf="' . h(vr_csrf_token()) . '"'
        . ' data-endpoint="' . h(vr_url('api/push.php')) . '"'
        . ' data-err="' . h(t('push_err')) . '"';
}

function vr_push_box(string $cls = ''): void
{
    ?>
<div class="pushbox <?= h($cls) ?>"<?= vr_push_attrs() ?>>
  <span class="pushbox__ico"><?= vr_icon('bell', 22) ?></span>
  <div class="pushbox__txt">
    <h3><?= te('push_t') ?></h3>
    <p><?= te('push_b') ?></p>
  </div>
  <div class="pushbox__act" aria-live="polite">
    <button class="btn btn--brass" type="button" data-push-on data-show="off"><span><?= te('push_on_btn') ?></span><?= vr_icon('bell', 15) ?></button>
    <p class="pushbox__ok" data-show="on"><?= vr_icon('check', 16) ?> <?= te('push_active') ?>
      <button class="pushbox__off" type="button" data-push-off><?= te('push_off_btn') ?></button></p>
    <p class="pushbox__note" data-show="denied"><?= te('push_denied') ?></p>
    <p class="pushbox__note" data-show="ios"><?= te('push_ios_hint', ['brand' => (string)vr_config('brand')]) ?>
      <a href="<?= h(vr_url('app.php')) ?>#ios"><?= te('push_ios_link') ?></a></p>
    <span class="pushbox__busy" data-show="busy" aria-hidden="true"></span>
    <p class="pushbox__msg" data-push-msg></p>
  </div>
</div>
<?php
}

/** Kurulu uygulamada bir kez sorulan alt kart ("Şimdi değil" → 14 gün sessiz). */
function vr_push_prompt(): void
{
    ?>
<aside class="pushprompt" data-push-prompt hidden<?= vr_push_attrs() ?> aria-label="<?= te('push_t') ?>">
  <span class="pushprompt__ico"><?= vr_icon('bell', 20) ?></span>
  <div class="pushprompt__txt">
    <b><?= te('push_prompt_t') ?></b>
    <span><?= te('push_prompt_b') ?></span>
  </div>
  <div class="pushprompt__act">
    <button class="btn btn--brass btn--sm" type="button" data-push-on><span><?= te('push_on_short') ?></span></button>
    <button class="pushprompt__later" type="button" data-push-later><?= te('push_later') ?></button>
  </div>
  <p class="pushbox__msg" data-push-msg></p>
</aside>
<?php
}

/** Sunucudaki Android paketi (uploads/app/<MARKA>.apk) — yoksa null. */
function vr_app_apk(): ?array
{
    require_once __DIR__ . '/uploads.php';
    $rel = '/uploads/app/' . preg_replace('/[^A-Za-z0-9]/', '', (string)vr_config('brand')) . '.apk';
    $abs = vr_doc_root() . $rel;
    if (!is_file($abs)) return null;
    return ['url'  => vr_url(ltrim($rel, '/'), ['v' => filemtime($abs)]),
            'size' => number_format(filesize($abs) / 1048576, 1, ',', '.') . ' MB'];
}

/** Telefondan tarayıcıyla gelen ziyaretçiye alttan uygulama şeridi (JS açar). */
function vr_appbar(): void
{
    $brand = (string)vr_config('brand');
    ?>
<div class="appbar" data-appbar data-href="<?= h(vr_url('app.php')) ?>" hidden>
  <button class="appbar__x" type="button" data-appbar-x aria-label="<?= te('close') ?>">&times;</button>
  <img class="appbar__icon" src="<?= h(vr_url('assets/app/icon-192.png')) ?>" alt="" width="44" height="44">
  <div class="appbar__txt">
    <b><?= h($brand) ?></b>
    <span><?= te('appbar_b') ?></span>
  </div>
  <a class="appbar__go" data-appbar-go href="<?= h(vr_url('app.php')) ?>"><?= te('appbar_btn') ?></a>
</div>
<?php
}

/**
 * Uygulamanın alt sekme çubuğu. Her sayfada basılıyor ama yalnız kurulu
 * uygulamada (data-platform="app", bkz. assets/js/boot.js) görünüyor:
 * tarayıcıda zaten başlık ve çekmece var. Saf bağlantılar — JS gerekmez.
 * Etkin sekmenin altındaki çizgi bir önceki sekmeden kayarak geliyor
 * (maxsales.js "sekme çubuğu").
 */
function vr_tabbar(string $page): void
{
    $tabs = [
        ['index.php',    vr_url('/'),            'home',  t('tab_home')],
        ['shop.php',     vr_url('shop.php'),     'grid',  t('nav_shop')],
        ['outlet.php',   vr_url('outlet.php'),   'tag',   t('tab_outlet')],
        // Arama sekmesi (14 Eki 2026, operatör: "arama butonu yok app'te").
        // Uygulamada başlık kayınca arama hiçbir yerde kalmıyordu; istek
        // listesi başlıktaki kalp ikonunda zaten duruyor. JS varsa sekme tam
        // ekran arama panelini açar (data-search-open), yoksa mağazaya gider.
        ['search',       vr_url('shop.php'),     'search', t('search_submit')],
        ['cart.php',     vr_url('cart.php'),     'bag',   t('nav_cart')],
    ];
    $on = -1;
    foreach ($tabs as $i => $tb) if ($tb[0] === $page) $on = $i;
    if ($on < 0 && in_array($page, ['product.php', 'brands.php', 'brand.php'], true)) $on = 1;
    $counts = [4 => vr_cart_count()];
    ?>
<nav class="tabbar" data-tabbar aria-label="<?= te('menu') ?>" style="--n:<?= count($tabs) ?>;--i:<?= max(0, $on) ?>"<?= $on < 0 ? ' data-none' : '' ?>>
  <?php foreach ($tabs as $i => [$file, $href, $ico, $label]): ?>
    <a class="tabbar__t<?= $i === $on ? ' is-on' : '' ?>" href="<?= h($href) ?>"<?= $i === $on ? ' aria-current="page"' : '' ?> data-tab="<?= $i ?>"<?= $file === 'search' ? ' data-search-open' : '' ?>>
      <span class="tabbar__ico"><?= vr_icon($ico, 22) ?><?php if (!empty($counts[$i])): ?><i class="tabbar__n"><?= (int)$counts[$i] ?></i><?php endif; ?></span>
      <span class="tabbar__l"><?= h($label) ?></span>
    </a>
  <?php endforeach; ?>
  <i class="tabbar__ind" aria-hidden="true"></i>
</nav>
<?php
}

// ------------------------------------------------------------------- başlık
function vr_header(): void
{
    $cartN  = vr_cart_count();
    $seller = null;
    if (function_exists('vr_current_seller')) $seller = vr_current_seller();
    $customer = null;
    if (function_exists('vr_current_customer')) $customer = vr_current_customer();
    $days = (int)vr_config('return_days', 30);
?>
<?php
/* Üst şerit DURUYOR, kaymıyor.
   Kayan promosyon şeridi indirim mağazası dili — üst segment modada hiçbir
   evde yok. Altı iddiayı sonsuz döngüde geçirmek yerine üç tanesi sabit ve
   ortalanmış duruyor. Düşen fiyat ve "her hafta yeni parça" iddiaları şeritten
   çıktı: ikisi de reklam cümlesi, üstelik ikisi de sayfanın kendisinde zaten
   görünüyor. Kalanlar güven bilgisi ve hepsi doğrulanabilir. */
?>
<?php // Geniş ekranda tek satır; telefonda üç bölüm sırayla (tek satır, iki satıra taşmıyor). ?>
<div class="ticker" aria-hidden="true"><span class="ticker__item"><?php
    foreach (array_values(array_filter(array_map('trim', explode('·', t('topbar', ['days' => $days]))))) as $i => $part) {
        echo ($i ? '<i class="ticker__sep"> · </i>' : '') . '<span class="ticker__part">' . h($part) . '</span>';
    }
?></span></div>

<header class="site" data-header>
  <div class="site__inner">
    <button class="burger" type="button" data-drawer-open aria-label="<?= te('menu') ?>" aria-expanded="false">
      <span></span><span></span>
    </button>

    <a class="wordmark" href="<?= h(vr_url('/')) ?>">
      <?= h((string)vr_config('brand')) ?><i class="wordmark__dot"></i>
    </a>

    <nav class="nav" aria-label="<?= te('menu') ?>">
      <a href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>"><?= te('nav_new') ?></a>
      <a href="<?= h(vr_url('shop.php')) ?>"><?= te('nav_shop') ?></a>
      <a class="nav__vault" href="<?= h(vr_url('outlet.php')) ?>"><?= te('nav_outlet') ?><i class="pulse"></i></a>
      <a href="<?= h(vr_url('brands.php')) ?>"><?= te('nav_brands') ?></a>
      <?php /* "Verkaufen" bilerek YOK: üst gezinme alıcının yolu, satıcı
               çağrısı altlıkta duruyor. */ ?>
    </nav>

    <div class="tools">
      <form class="search" role="search" action="<?= h(vr_url('shop.php')) ?>" method="get">
        <label class="vh" for="q"><?= te('search_submit') ?></label>
        <input id="q" type="search" name="q" placeholder="<?= te('search_placeholder') ?>"
               value="<?= h((string)($_GET['q'] ?? '')) ?>" autocomplete="off">
        <button type="submit" aria-label="<?= te('search_submit') ?>"><?= vr_icon('search') ?></button>
      </form>

      <?php
      /* BÖLGE & PARA BİRİMİ — masaüstü başlıkta ince bir kontrol.
         Dil seçici yalnızca telefon çekmecesinde ve altlıktaydı; dünyanın her
         yerinden gelen alıcı için dil ve para birimi başlıkta durmalı. Salt
         HTML (details/summary), JS gerektirmiyor. Para birimi seçeneği
         yalnızca kur tablosu güncelse çıkıyor (inc/fx.php). */
      $fxCur = vr_fx_currency(); $fxAll = vr_fx_currencies();
      ?>
      <details class="region">
        <summary aria-label="<?= te('region_label') ?>">
          <?= h(strtoupper(vr_lang())) ?><i>·</i><?= $fxCur !== '' ? h($fxCur) : '€' ?>
        </summary>
        <div class="region__panel">
          <p class="region__h"><?= te('language') ?></p>
          <?= vr_lang_switch() ?>
          <?php if ($fxAll): ?>
            <p class="region__h"><?= te('currency_label') ?></p>
            <div class="langs" role="group" aria-label="<?= te('currency_label') ?>">
              <a class="lang<?= $fxCur === '' ? ' is-on' : '' ?>" href="<?= h(vr_url_with(['cur' => 'EUR'])) ?>">€</a>
              <?php foreach ($fxAll as $c): ?>
                <a class="lang<?= $fxCur === $c ? ' is-on' : '' ?>" href="<?= h(vr_url_with(['cur' => $c])) ?>"><?= h($c) ?></a>
              <?php endforeach; ?>
            </div>
            <p class="region__n"><?= te('fx_note') ?></p>
          <?php endif; ?>
        </div>
      </details>

      <?php /* Telefonda arama kutusu başlığa sığmıyor (gizli); yerine ikon,
               tam ekran arama panelini açar. JS yoksa mağazaya gider. */ ?>
      <a class="tool tool--search" href="<?= h(vr_url('shop.php')) ?>" data-search-open aria-label="<?= te('search_submit') ?>"><?= vr_icon('search') ?></a>

      <a class="tool tool--wish" href="<?= h(vr_url('wishlist.php')) ?>" aria-label="<?= te('nav_wish') ?>">
        <?= vr_icon('heart') ?><?php $wn = vr_wish_count(); if ($wn > 0): ?><i class="bag__n"><?= (int)$wn ?></i><?php endif; ?>
      </a>

      <?php // Kişi ikonu: müşteri girişliyse hesap paneli, satıcı girişliyse
            // satıcı paneli, ikisi de değilse müşteri girişi.
            $acctUrl = $customer !== null ? 'account/index.php'
                     : ($seller !== null ? 'seller/index.php' : 'account/login.php'); ?>
      <a class="tool" href="<?= h(vr_url($acctUrl)) ?>"
         aria-label="<?= te('nav_account') ?>"><?= vr_icon('user') ?></a>

      <a class="tool tool--bag" href="<?= h(vr_url('cart.php')) ?>" aria-label="<?= te('nav_cart') ?>">
        <?= vr_icon('bag') ?><?php if ($cartN > 0): ?><i class="bag__n"><?= (int)$cartN ?></i><?php endif; ?>
      </a>
    </div>
  </div>
</header>

<?php /* Tam ekran arama (telefon ve uygulama). Form JS'siz de çalışır:
         mağazaya q ile gider. JS canlı öneriyi api/suggest.php'den getirir. */
$qBrands = array_slice(array_keys(vr_facets(['exclude_vault' => true])['brands'] ?? []), 0, 14); ?>
<div class="qsheet" data-qsheet hidden>
  <div class="qsheet__panel" role="dialog" aria-modal="true" aria-label="<?= te('search_submit') ?>">
    <form class="qsheet__form" role="search" action="<?= h(vr_url('shop.php')) ?>" method="get">
      <?= vr_icon('search', 18) ?>
      <label class="vh" for="qs"><?= te('search_submit') ?></label>
      <input id="qs" type="search" name="q" placeholder="<?= te('search_placeholder') ?>" autocomplete="off" enterkeyhint="search" data-qsheet-input>
      <button class="qsheet__x" type="button" data-qsheet-close aria-label="<?= te('close') ?>">&times;</button>
    </form>
    <div class="qsheet__res" data-qsheet-res aria-live="polite"></div>
    <?php if ($qBrands): ?>
      <div class="qsheet__brands" data-qsheet-brands>
        <p class="qsheet__h"><?= te('nav_brands') ?></p>
        <div class="qsheet__chips">
          <?php foreach ($qBrands as $qi => $qb): ?>
            <a class="qsheet__chip" style="--i:<?= (int)$qi ?>" href="<?= h(vr_url('shop.php', ['brand' => $qb])) ?>"><?= h($qb) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="drawer" data-drawer hidden>
  <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="<?= te('menu') ?>">
    <?php /* Çekmece 12 Eki 2026'da yeniden düzenlendi: on bir aynı ağırlıkta satır
       ve "EN DE FR…" dil kutularının da 24 px serif menü satırı gibi basılması
       (genel `.drawer__panel a` kuralı onlara da uyuyordu) yerine üç katman:
       ana dört yol büyük, ikincil yollar küçük, hesap ve satış altta. */ ?>
    <div class="drawer__top">
      <a class="wordmark drawer__mark" href="<?= h(vr_url('/')) ?>"><?= h((string)vr_config('brand')) ?><i class="wordmark__dot"></i></a>
      <button class="drawer__x" type="button" data-drawer-close aria-label="<?= te('close') ?>">&times;</button>
    </div>
    <nav class="drawer__main" aria-label="<?= te('menu') ?>">
      <a href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>"><span><?= te('nav_new') ?></span><?= vr_icon('arrow', 16) ?></a>
      <a href="<?= h(vr_url('shop.php')) ?>"><span><?= te('nav_shop') ?></span><?= vr_icon('arrow', 16) ?></a>
      <a class="drawer__vault" href="<?= h(vr_url('outlet.php')) ?>"><span><?= te('nav_outlet') ?><i class="pulse"></i></span><?= vr_icon('arrow', 16) ?></a>
      <a href="<?= h(vr_url('brands.php')) ?>"><span><?= te('nav_brands') ?></span><?= vr_icon('arrow', 16) ?></a>
    </nav>
    <nav class="drawer__sub">
      <a href="<?= h(vr_url('journal.php')) ?>"><?= te('nav_journal') ?></a>
      <a href="<?= h(vr_url('private.php')) ?>"><?= te('nav_private') ?></a>
      <a href="<?= h(vr_url('wishlist.php')) ?>"><?= te('nav_wish') ?></a>
      <a href="<?= h(vr_url('app.php')) ?>"><?= te('app_nav') ?></a>
      <a href="<?= h(vr_url('faq.php')) ?>"><?= te('nav_faq') ?></a>
    </nav>
    <div class="drawer__acct">
      <a class="btn btn--ghost" href="<?= h(vr_url($acctUrl)) ?>"><span><?= te('nav_account') ?></span><?= vr_icon('user', 15) ?></a>
      <a class="drawer__sell" href="<?= h(vr_url('sell.php')) ?>"><?= te('nav_sell') ?><?= vr_icon('arrow', 13) ?></a>
    </div>
    <div class="drawer__langs"><?= vr_lang_switch() ?></div>
    <?php /* Para birimi telefonda da seçilebilmeli: başlıktaki bölge paneli
       900 pikselin altında gizli, yani bu olmadan dünya alıcısının telefonda
       gösterge fiyatı kapatma ya da değiştirme yolu kalmıyordu. */
       $dFx = vr_fx_currency(); $dAll = vr_fx_currencies();
       if ($dAll): ?>
      <div class="drawer__langs">
        <div class="langs" role="group" aria-label="<?= te('currency_label') ?>">
          <a class="lang<?= $dFx === '' ? ' is-on' : '' ?>" href="<?= h(vr_url_with(['cur' => 'EUR'])) ?>">€</a>
          <?php foreach ($dAll as $c): ?>
            <a class="lang<?= $dFx === $c ? ' is-on' : '' ?>" href="<?= h(vr_url_with(['cur' => $c])) ?>"><?= h($c) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
}

function vr_flash_render(): void
{
    $msgs = vr_flash();
    if (!$msgs) return;
    echo '<div class="flashes">';
    foreach ($msgs as $m) {
        echo '<div class="flash flash--' . h($m['type']) . '">' . h($m['msg']) . '</div>';
    }
    echo '</div>';
}

// ------------------------------------------------------------------- altlık
function vr_footer(): void
{
    $co   = vr_config('company');
    $days = (int)vr_config('return_days', 30);
?>
<footer class="foot">
  <div class="foot__top">
    <div class="foot__brand">
      <div class="wordmark wordmark--foot"><?= h((string)vr_config('brand')) ?><i class="wordmark__dot"></i></div>
      <p class="foot__tag"><?= te('tagline') ?></p>
      <p class="foot__note"><?= te('footer_marketplace') ?></p>
      <?= vr_lang_switch() ?>
    </div>

    <?php
    /* Sütunlar. Her bağlantı YALNIZCA bir sütunda: eskiden Journal hem
       Mağaza'da hem Şirket'te, Streitbeilegung hem Şirket'te hem Hukuk'ta
       duruyordu. Alt bilgide aynı adı iki kez görmek, hangisinin doğru
       olduğunu düşündürüyor. */
    vr_foot_col(t('nav_shop'), [
        [vr_url('shop.php', ['sort' => 'new']),          t('nav_new')],
        [vr_url('outlet.php'),                           t('nav_outlet')],
        [vr_url('brands.php'),                           t('nav_brands')],
        [vr_url('shop.php', ['seller' => 'private']),    t('seller_private')],
        [vr_url('wishlist.php'),                         t('nav_wish')],
    ]);
    vr_foot_col(t('footer_service'), [
        [vr_url('faq.php'),                  t('faq_title')],
        [vr_url('legal/versand.php'),        t('legal_shipping')],
        [vr_url('legal/zahlung.php'),        t('legal_payment')],
        [vr_url('legal/rueckgabe.php'),      t('legal_returns')],
        [vr_url('order.php'),                t('order_status')],
        [vr_url('account/login.php'),        t('acc_title')],
        [vr_url('size-guide.php'),           t('size_guide')],
        [vr_url('contact.php'),              t('nav_contact')],
        [vr_url('private.php'),              t('nav_private')],
    ]);
    vr_foot_col(t('footer_company'), [
        [vr_url('about.php'),        t('about_title')],
        [vr_url('journal.php'),      t('nav_journal')],
        [vr_url('sell.php'),         t('nav_sell')],
        [vr_url('seller/login.php'), t('sell_login')],
        [vr_url('app.php'),          t('app_nav')],
        [vr_url('newsletter.php'),   t('news_title')],
    ]);
    vr_foot_col(t('footer_legal'), [
        [vr_url('legal/impressum.php'),       t('legal_imprint')],
        [vr_url('legal/agb.php'),             t('legal_terms')],
        [vr_url('legal/widerruf.php'),        t('legal_withdrawal')],
        [vr_url('legal/datenschutz.php'),     t('legal_privacy')],
        [vr_url('legal/verkaeufer.php'),      t('legal_seller_terms')],
        [vr_url('legal/cookies.php'),         t('legal_cookies')],
        [vr_url('legal/streitbeilegung.php'), t('legal_disputes')],
        [vr_url('legal/barrierefreiheit.php'), t('legal_accessibility')],
    ]);
    ?>
  </div>

  <div class="foot__pay"><?php foreach (vr_payment_marks() as $m): ?><span class="pay"><?= h($m) ?></span><?php endforeach; ?></div>

  <div class="foot__bottom">
    <p>&copy; <?= date('Y') ?> <?= h((string)($co['legal_name'] ?? 'Acerasoft LLC')) ?>. <?= te('footer_rights') ?></p>
    <p><?= te('footer_vat_note') ?> <?= te('order_next_3', ['days' => $days]) ?></p>
  </div>
</footer>
<?php
}

/**
 * Alt bilgi sütunu.
 *
 * Telefonda dört sütun alt alta iniyor ve alt bilgi yirmi altı satırlık bir
 * merdivene dönüşüyordu — ekranın üç boyu. Sütunlar artık <details>: dar
 * ekranda başlığa dokununca açılıyor, geniş ekranda CSS içeriği koşulsuz
 * gösterdiği için açık/kapalı durumun bir hükmü kalmıyor.
 *
 * JavaScript yok. CSS hiç yüklenmese bile bağlantılar dokunarak açılıyor,
 * yani en kötü durumda erişilebilirlik kaybı olmuyor.
 */
function vr_foot_col(string $label, array $links): void
{
?>
<nav class="foot__col" aria-label="<?= h($label) ?>">
  <details class="foot__d">
    <summary><h2><?= h($label) ?></h2></summary>
    <div class="foot__links">
      <?php foreach ($links as [$href, $text]): ?>
        <a href="<?= h($href) ?>"><?= h($text) ?></a>
      <?php endforeach; ?>
    </div>
  </details>
</nav>
<?php
}

function vr_payment_marks(): array
{
    return ['VISA', 'MASTERCARD', 'AMEX', 'APPLE PAY', 'GOOGLE PAY', 'KLARNA', 'SEPA'];
}

/** Mevcut adres + ek sorgu parametreleri (bölge kontrolü için). */
function vr_url_with(array $add): string
{
    $path  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($path);
    $q     = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $q);
    foreach ($add as $k => $v) $q[$k] = $v;
    return ($parts['path'] ?? '/') . ($q ? '?' . http_build_query($q) : '');
}

function vr_lang_switch(): string
{
    $out = '<div class="langs" role="group" aria-label="' . h(t('language')) . '">';
    foreach ((array)vr_config('languages', ['en']) as $lg) {
        $on = $lg === vr_lang();
        $out .= '<a class="lang' . ($on ? ' is-on' : '') . '" href="' . h(vr_lang_url($lg, true)) . '"'
             . ($on ? ' aria-current="true"' : '') . '>' . h(strtoupper($lg)) . '</a>';
    }
    return $out . '</div>';
}

// ------------------------------------------------------------------ ikonlar
/** Satır içi SVG — dış istek yok, renk currentColor'dan gelir. */
function vr_icon(string $name, int $size = 20): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'bag'    => '<path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user'   => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5"/>',
        'arrow'  => '<path d="M5 12h13"/><path d="M13 6l6 6-6 6"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'lock'   => '<rect x="5" y="11" width="14" height="9" rx="1.6"/><path d="M8.5 11V8a3.5 3.5 0 0 1 7 0v3"/>',
        'clock'  => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.4l3 1.8"/>',
        'tag'    => '<path d="M4 12.5L12.5 4H20v7.5L11.5 20 4 12.5z"/><circle cx="16" cy="8" r="1.3"/>',
        'doc'    => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M10 12h6M10 16h6"/>',
        'truck'  => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.7"/><circle cx="17" cy="18" r="1.7"/>',
        'shield' => '<path d="M12 3l7 3v6c0 4-3 7.4-7 9-4-1.6-7-5-7-9V6l7-3z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
        'heart'  => '<path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/>',
        'ruler'  => '<rect x="2.5" y="8.5" width="19" height="7" rx="1"/><path d="M7 8.5v3M11 8.5v4M15 8.5v3M19 8.5v4"/>',
        'mail'   => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'star'   => '<path d="M12 4l2.4 5 5.6.8-4 3.9 1 5.5-5-2.6-5 2.6 1-5.5-4-3.9 5.6-.8z"/>',
        'bell'   => '<path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 1.5H5z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>',
        'phone'  => '<rect x="7" y="2.8" width="10" height="18.4" rx="2.2"/><path d="M10.8 18.2h2.4"/>',
        'home'   => '<path d="M4 10.5L12 4l8 6.5"/><path d="M6 9v11h12V9"/><path d="M10 20v-5.5h4V20"/>',
        'grid'   => '<rect x="4" y="4" width="6.5" height="6.5"/><rect x="13.5" y="4" width="6.5" height="6.5"/><rect x="4" y="13.5" width="6.5" height="6.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $d . '</svg>';
}

// -------------------------------------------------------------- ürün kartı
/**
 * Vitrin kartı. $o: eager (ilk ekrandaki görseller için lazy kapalı),
 * badge (üst köşe etiketi), size_hint (beden satırı göster)
 */
/** "−35 %" — doğrulanmış UVP'ye göre indirim oranı, tam sayıya aşağı yuvarlanmış. */
function vr_discount_label(array $p): string
{
    $rrp = (int)($p['rrp_cents'] ?? 0); $pr = (int)($p['price_cents'] ?? 0);
    if ($rrp <= 0 || $pr >= $rrp) return '';
    return "\u{2212}" . (int)floor(100 - $pr * 100 / $rrp) . "\u{00A0}%";
}

function vr_card(array $p, array $o = []): void
{
    $seller = function_exists('vr_product_seller') ? vr_product_seller($p) : ['type' => 'vestra'];
    $soldOut = (int)$p['stock'] < 1;
    $low     = !$soldOut && (int)$p['stock'] <= 2;
?>
<article class="card<?= $soldOut ? ' is-out' : '' ?>" data-reveal>
  <?php $cimg = vr_card_image($p);
        $alt2 = vr_product_image_alt($p);
        $solo = ($alt2 === '' || $alt2 === $cimg); ?>
  <a class="card__media<?= $solo ? ' card__media--solo' : '' ?>" href="<?= h(vr_product_url($p)) ?>">
    <?php vr_frame($cimg, [
              'alt'   => $p['brand'] . ' ' . $p['name'],
              'sizes' => '(max-width:640px) 48vw, (max-width:1100px) 32vw, 300px',
              'eager' => !empty($o['eager']),
              'tile'  => true,
          ]);
          // İkinci kare yalnızca üzerine gelince görünür; dekoratif olduğu için
          // alt="" ve aria-hidden — ekran okuyucu aynı ürünü iki kez okumasın.
          if (!$solo) {
              vr_frame($alt2, [
                  'class'  => 'card__alt',
                  'tile'   => true,
                  'widths' => [300, 600],
                  'sizes'  => '(max-width:640px) 48vw, 300px',
              ]);
          } ?>
    <?php if (!empty($o['badge'])): ?><span class="card__badge"><?= h((string)$o['badge']) ?></span><?php endif; ?>
    <?php if ($seller['type'] === 'private'): ?><span class="card__badge card__badge--priv"><?= te('seller_private') ?></span><?php endif; ?>
    <?php if ($soldOut): ?><span class="card__out"><?= te('sold_out') ?></span><?php endif; ?>
  </a>
  <?php // Merkliste düğmesi bağlantının DIŞINDA: <a> içine <form> koymak
        // geçersiz HTML ve tıklama davranışı öngörülemez oluyor.
        vr_wish_button($p); ?>
  <div class="card__body">
    <p class="card__brand"><?= h($p['brand']) ?><?php
      /* Kadın parçalar erkek kataloğunun içinde işaretsiz duruyordu. */
      if (($p['audience'] ?? '') === 'women') echo ' · ' . te('audience_women');
    ?></p>
    <h3 class="card__name"><a href="<?= h(vr_product_url($p)) ?>"><?= h(vr_card_name($p)) ?></a></h3>
    <p class="card__price">
      <?= h(vr_money((int)$p['price_cents'])) ?>
      <?php if (!empty($p['rrp_cents']) && (int)$p['rrp_cents'] > (int)$p['price_cents']): ?>
        <span class="was"><?= te('uvp') ?> <s><?= h(vr_money((int)$p['rrp_cents'])) ?></s></span>
        <span class="disc"><?= h(vr_discount_label($p)) ?></span>
      <?php endif; ?>
      <?php $lx = vr_money_local((int)$p['price_cents']); if ($lx !== ''): ?>
        <span class="fx"><?= h($lx) ?></span>
      <?php endif; ?>
    </p>
    <?php
    /* Kaç renk var. Vitrinde modelin tek karosu duruyor; bu satır olmadan
       müşteri diğer sekiz rengin varlığını hiç bilmiyor. */
    $nCol = count(vr_variant_siblings($p));
    if ($nCol > 1): ?>
      <p class="card__sizes card__sizes--col"><?= te('n_colours', ['n' => $nCol]) ?></p>
    <?php elseif (!empty($o['size_hint']) && $p['sizes']): ?>
      <p class="card__sizes"><?php
        echo h(implode(' · ', array_map(fn($s) => $s['label'] === 'ONE' ? t('one_of_one') : $s['label'], array_slice($p['sizes'], 0, 6))));
      ?></p>
    <?php elseif ($low): ?>
      <p class="card__sizes card__sizes--low"><?= te('low_stock', ['n' => (int)$p['stock']]) ?></p>
    <?php endif; ?>
  </div>
</article>
<?php
}

/** Ürün ızgarası. */
function vr_grid(array $rows, array $o = []): void
{
    if (!$rows) return;
    echo '<div class="grid' . (!empty($o['class']) ? ' ' . h((string)$o['class']) : '') . '">';
    $i = 0;
    foreach ($rows as $p) {
        vr_card($p, array_merge($o, ['eager' => $i < 4 && !empty($o['eager_first'])]));
        $i++;
    }
    echo '</div>';
}

// -------------------------------------------------------------- Vault kartı
function vr_vault_card(array $v, array $o = []): void
{
    $p  = $v['product'];
    $st = $v['state'];
    $locked = $v['members_only'] && !vr_is_member();
?>
<article class="lot<?= $v['sold'] ? ' is-sold' : '' ?><?= $locked ? ' is-locked' : '' ?>" data-reveal
         <?= $st['next_at'] ? 'data-next="' . (int)$st['next_at'] . '"' : '' ?>>
  <a class="lot__media" href="<?= h(vr_url('outlet.php', ['lot' => $v['lot_id']])) ?>">
    <?php vr_frame(vr_card_image($p), [
        'box'    => 1.0,          // Vault karosu kare
        'no_pad' => true,         // koyu zeminde beyaz şerit istemiyoruz
        'w'      => 600,
        'sizes'  => '(max-width:640px) 92vw, 380px',
        'alt'    => $p['brand'] . ' ' . $p['name'],
        'eager'  => !empty($o['eager']),
    ]); ?>
    <span class="lot__step"><?= te('vault_stage', ['x' => (int)$st['step'] + 1, 'y' => (int)$st['steps'] + 1]) ?></span>
    <?php if ($v['sold']): ?><span class="lot__sold"><?= te('vault_gone') ?></span><?php endif; ?>
  </a>
  <?php vr_wish_button($p); ?>

  <div class="lot__body">
    <p class="lot__brand"><?= h($p['brand']) ?></p>
    <h3 class="lot__name"><a href="<?= h(vr_url('outlet.php', ['lot' => $v['lot_id']])) ?>"><?= h(vr_card_name($p)) ?></a></h3>

    <div class="lot__price">
      <span class="lot__now"><?= h(vr_money((int)$st['cents'])) ?></span>
      <?php if ($st['reference'] !== null): ?>
        <s class="lot__ref"><?= h(vr_money((int)$st['reference'])) ?></s>
      <?php endif; ?>
    </div>

    <?php if ($st['reference'] !== null): ?>
      <p class="lot__legal"><?= te('vault_lowest30', ['amount' => vr_money((int)$st['reference'])]) ?></p>
    <?php endif; ?>

    <?php if (!$v['sold'] && $st['next_at']): ?>
      <p class="lot__drop">
        <?= vr_icon('clock', 15) ?>
        <span class="lot__cd" data-countdown="<?= (int)$st['next_at'] ?>">—</span>
        <span class="lot__then"><?= te('vault_next_price', ['amount' => vr_money((int)$st['next_cents'])]) ?></span>
      </p>
    <?php elseif (!$v['sold']): ?>
      <p class="lot__drop lot__drop--floor"><?= te('vault_at_floor') ?> · <?= te('vault_floor_note') ?></p>
    <?php endif; ?>

    <div class="lot__meter" aria-hidden="true">
      <span style="--p:<?= (int)round(($st['step'] / max(1, $st['steps'])) * 100) ?>%"></span>
    </div>

    <?php if ($locked): ?>
      <a class="btn btn--ghost btn--sm" href="<?= h(vr_url('/', [])) ?>#member"><?= te('vault_early', ['time' => vr_until($v['members_until'])]) ?></a>
    <?php elseif ($v['sold']): ?>
      <span class="btn btn--sm is-disabled"><?= te('vault_gone') ?></span>
    <?php elseif (!$v['available']): ?>
      <span class="btn btn--sm is-disabled"><?= te('vault_gone') ?></span>
    <?php else: ?>
      <form method="post" action="<?= h(vr_url('cart.php')) ?>">
        <?= vr_csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="pid" value="<?= h($p['id']) ?>">
        <input type="hidden" name="lot" value="<?= h($v['lot_id']) ?>">
        <input type="hidden" name="size" value="<?= h($p['sizes'][0]['label'] ?? 'ONE') ?>">
        <button class="btn btn--brass btn--sm" type="submit"><?= te('vault_take_it', ['amount' => vr_money((int)$st['cents'])]) ?></button>
      </form>
    <?php endif; ?>
  </div>
</article>
<?php
}

/** "3 Std 12 Min" gibi kalan süre metni. */
function vr_until(int $ts): string
{
    $d = max(0, $ts - time());
    if ($d >= 86400) return (int)floor($d / 86400) . 'd ' . (int)floor(($d % 86400) / 3600) . 'h';
    if ($d >= 3600)  return (int)floor($d / 3600) . 'h ' . (int)floor(($d % 3600) / 60) . 'm';
    return max(1, (int)ceil($d / 60)) . 'm';
}

// ----------------------------------------------------------------- bölümler
function vr_section_head(string $title, string $sub = '', ?string $moreUrl = null, string $moreLabel = ''): void
{
?>
<div class="sechead" data-reveal>
  <div>
    <h2 class="sechead__t"><?= h($title) ?></h2>
    <?php if ($sub !== ''): ?><p class="sechead__s"><?= h($sub) ?></p><?php endif; ?>
  </div>
  <?php if ($moreUrl): ?>
    <a class="sechead__more" href="<?= h($moreUrl) ?>"><?= h($moreLabel ?: t('view_all')) ?><?= vr_icon('arrow', 16) ?></a>
  <?php endif; ?>
</div>
<?php
}

/**
 * Anasayfanın bölüm başlığı: solda bölüm numarası, ortada başlık, sağda
 * "hepsini gör". Numara dile bağlı değil, o yüzden çeviri gerektirmiyor.
 */
function vr_chapter(string $n, string $title, string $sub = '', ?string $moreUrl = null, string $moreLabel = ''): void
{
?>
<div class="chap" data-reveal>
  <div>
    <span class="chap__n" aria-hidden="true"><?= h($n) ?></span>
    <h2 class="chap__t"><?= h($title) ?></h2>
    <?php if ($sub !== ''): ?><p class="chap__s"><?= h($sub) ?></p><?php endif; ?>
  </div>
  <?php if ($moreUrl): ?>
    <a class="chap__more" href="<?= h($moreUrl) ?>"><?= h($moreLabel ?: t('view_all')) ?><?= vr_icon('arrow', 15) ?></a>
  <?php endif; ?>
</div>
<?php
}

/**
 * Sahnenin kampanya görseli. Sıra:
 *   1. assets/media/hero.(mp4|webm)  → gerçek kampanya filmi
 *   2. assets/media/hero.(jpg|webp|png) → gerçek kampanya fotoğrafı
 *   3. hiçbiri yoksa null → üretilen kampanya kareleri sinematik geçişle döner
 *
 * Yani gerçek malzeme geldiği anda kod değişmeden devreye giriyor; gelmezse
 * anasayfa boş kalmıyor. Dosya yoksa hiçbir şey bozulmuyor.
 */
function vr_hero_media(string $name = 'hero'): ?array
{
    $name  = preg_replace('/[^a-z0-9-]/', '', $name) ?: 'hero';
    $kinds = [
        ["{$name}.mp4",  'video', 'video/mp4'],
        ["{$name}.webm", 'video', 'video/webm'],
        ["{$name}.jpg",  'image', 'image/jpeg'],
        ["{$name}.jpeg", 'image', 'image/jpeg'],
        ["{$name}.webp", 'image', 'image/webp'],
        ["{$name}.png",  'image', 'image/png'],
    ];
    foreach ($kinds as [$file, $kind, $type]) {
        $path = VR_ROOT . '/assets/media/' . $file;
        if (is_file($path) && filesize($path) > 0) {
            // Dosya değişince tarayıcı eskisine yapışmasın.
            return [
                'kind'   => $kind,
                'type'   => $type,
                'src'    => vr_url('assets/media/' . $file, ['v' => (string)filemtime($path)]),
                'poster' => $kind === 'video' ? vr_hero_poster($name) : null,
            ];
        }
    }
    return null;
}

/**
 * Filmin kendi ilk karesi. build-hero.php filmi yazarken yanına
 * "{$name}-poster.jpg" bırakıyor; varsa afiş olarak o kullanılmalı.
 *
 * Önemi şu: film preload="none" ile duruyor ve oynatmayı JS başlatıyor. Yani
 * ilk saniyede — ve JS hiç çalışmazsa hep — ekranda yalnızca afiş var. Afiş
 * üretilmiş soyut kare olursa ziyaretçi ana sayfayı ürünsüz, boş bir zemin
 * olarak görüyor. Filmin kendi karesi gerçek ürünleri gösteriyor.
 */
function vr_hero_poster(string $name): ?string
{
    foreach (['jpg', 'webp', 'png'] as $ext) {
        $file = "{$name}-poster.{$ext}";
        $path = VR_ROOT . '/assets/media/' . $file;
        if (is_file($path) && filesize($path) > 0) {
            return vr_url('assets/media/' . $file, ['v' => (string)filemtime($path)]);
        }
    }
    return null;
}

/**
 * Editoryal karo için görsel seçer: elde GERÇEK fotoğrafı olan ürün varsa
 * onunkini, yoksa üretilen kampanya karesini döndürür. Karolar tam genişlikte
 * ve dikey olduğu için ürün fotoğrafı burada iyi duruyor.
 *
 * $skip: aynı fotoğraf iki karoda çıkmasın diye daha önce kullanılanlar.
 */
function vr_editorial_image(array $rows, string $seed, array &$skip): string
{
    // İki tur: önce tek özneli çekimler, sonra kalan her şey.
    //
    // Toptancının dosyalarının bir kısmı ızgara kontakt sayfası — bir kadrajda
    // sekiz küçük ürün ve altta model kodu. Ürün sayfasında sorun değil, ama
    // tam boy editoryal karoda site anında depo çıktısına benziyor. İndeks
    // (data/photo-quality.json) hangisinin öyle olduğunu önceden biliyor;
    // indeks yoksa hiçbir şey değişmiyor, eski davranış geçerli.
    $grid  = vr_photo_grid_index();
    $shape = vr_photo_shape_index();

    foreach ([true, false] as $preferSingle) {
        foreach ($rows as $p) {
            foreach ((array)($p['images'] ?? []) as $img) {
                $img = (string)$img;
                if ($img === '' || in_array($img, $skip, true)) continue;
                // Tam boy bantta iki mankenli kare de aynı sorunu yapıyor:
                // aynı yüz iki kez, sayfa kopyalanmış gibi görünüyor.
                if ($preferSingle && (int)($shape[$img]['n'] ?? 1) > 1) continue;
                if ($preferSingle && isset($grid[$img])) continue;
                $skip[] = $img;
                return $img;
            }
        }
    }
    return vr_url('assets/art.php', ['s' => $seed, 'm' => 'campaign', 'w' => 1200, 'h' => 1500]);
}


/** Demo veriyle çalışıyorsak bunu gizlemiyoruz — dürüstlük meselesi. */
function vr_demo_banner(): void
{
    if (!vr_catalog_is_demo()) return;
    echo '<div class="notice notice--demo">'
       . '<strong>Demo-Katalog.</strong> data/listings.json noch nicht gefunden — angezeigt werden Beispieldaten aus data/seed/. '
       . 'Sobald der echte Katalog erreichbar ist, verschwindet dieser Hinweis automatisch.'
       . '</div>';
}

// --------------------------------------------------------------------- SEO
function vr_jsonld_org(): array
{
    $co = vr_config('company');
    $seo = (array)vr_config('seo', []);
    $org = [
        '@type' => 'OnlineStore',
        '@id'   => vr_canonical_origin() . '/#store',
        'name'  => (string)vr_config('brand'),
        'url'   => vr_canonical_origin() . '/',
        'slogan' => t('tagline'),
        'currenciesAccepted' => (string)vr_config('currency', 'EUR'),
        'paymentAccepted'    => implode(', ', vr_payment_methods()),
    ];
    // Logo: JSON-LD için mutlak raster (Google en az 112x112 ve taranabilir ister).
    if (trim((string)($seo['logo'] ?? '')) !== '') {
        $logo = (string)$seo['logo'];
        $org['logo'] = str_starts_with($logo, 'http') ? $logo : vr_canonical_origin() . vr_base_url() . $logo;
        $org['image'] = $org['logo'];
    }
    // Sosyal profiller (sameAs) — yalnızca gerçek adresler config'de varsa.
    $social = array_values(array_filter((array)($seo['social'] ?? []), 'strlen'));
    if ($social) $org['sameAs'] = $social;
    // İletişim noktası — e-posta ve/veya telefon varsa.
    $cp = array_filter([
        '@type'       => 'ContactPoint',
        'contactType' => 'customer service',
        'email'       => (string)($co['email'] ?? ''),
        'telephone'   => (string)($co['phone'] ?? ''),
        'availableLanguage' => array_map('strtoupper', (array)vr_config('languages', ['en'])),
    ]);
    if (!empty($cp['email']) || !empty($cp['telephone'])) $org['contactPoint'] = $cp;
    if (trim((string)($co['legal_name'] ?? '')) !== '') {
        $org['parentOrganization'] = ['@type' => 'Organization', 'name' => (string)$co['legal_name']];
    }
    if (trim((string)($co['street'] ?? '')) !== '') {
        $org['address'] = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => (string)($co['street'] ?? ''),
            'addressLocality' => (string)($co['city'] ?? ''),
            'postalCode'      => (string)($co['zip'] ?? ''),
            'addressRegion'   => (string)($co['state'] ?? ''),
            'addressCountry'  => (string)($co['country'] ?? ''),
        ]);
    }
    if (trim((string)($co['email'] ?? '')) !== '') $org['email'] = (string)$co['email'];
    return $org;
}

/** Ürün için schema.org — zengin sonuçlarda fiyat/stok görünsün. */
/**
 * WebSite düğümü — her sayfada. SearchAction, Google'ın site içi arama
 * kutusunu (sitelinks searchbox) tetikler. Hedef adres doğrudan kanonik
 * kökenden kuruluyor: vr_abs/vr_url ana-dışı dillerde ?lang ekleyip
 * `shop.php?lang=de?q=...` gibi çift-? bozuk URL üretiyordu.
 */
function vr_jsonld_website(): array
{
    return [
        '@type'      => 'WebSite',
        '@id'        => vr_canonical_origin() . '/#website',
        'name'       => (string)vr_config('brand'),
        'url'        => vr_canonical_origin() . '/',
        'publisher'  => ['@id' => vr_canonical_origin() . '/#store'],
        'inLanguage' => vr_locale(),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => vr_canonical_origin() . vr_base_url() . '/shop.php?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function vr_jsonld_product(array $p, ?int $overridePrice = null): array
{
    $price = $overridePrice ?? (int)$p['price_cents'];
    $cur   = (string)vr_config('currency', 'EUR');
    $url   = vr_canonical_origin() . vr_product_url($p);

    // Görseller: galerinin ilk birkaç karesi mutlak adresle (Google dizi kabul eder).
    $imgs = [];
    foreach (array_slice(vr_product_gallery($p, 4), 0, 4) as $g) {
        $imgs[] = str_starts_with($g, 'http') ? $g : vr_canonical_origin() . vr_img_dynamic($g, 1400);
    }
    if (!$imgs) $imgs[] = vr_canonical_origin() . vr_img_dynamic(vr_product_image($p), 1400);

    // Fiyat geçerlilik tarihi: fırsat bitişi varsa o, yoksa bugünden +30 gün.
    $validUntil = !empty($p['deal_ends'])
        ? date('Y-m-d', (int)$p['deal_ends'])
        : date('Y-m-d', time() + 30 * 86400);

    // İade politikası (config return_days) ve kargo (DE bölgesi) — Google
    // ücretsiz ürün listelemeleri ve zengin sonuç için.
    $retDays = (int)vr_config('return_days', 30);
    $ship    = (array)(vr_config('shipping')['de'] ?? []);
    $shipCents = (int)($ship['cents'] ?? 0);
    $freeOver  = (int)($ship['free_over'] ?? 0);
    $effShip   = ($freeOver > 0 && $price >= $freeOver) ? 0 : $shipCents;
    preg_match_all('/\d+/', (string)($ship['days'] ?? ''), $dm);
    $tMin = isset($dm[0][0]) ? (int)$dm[0][0] : 1;
    $tMax = isset($dm[0][1]) ? (int)$dm[0][1] : $tMin;

    $offer = array_filter([
        '@type'         => 'Offer',
        'url'           => $url,
        'price'         => vr_amount($price),
        'priceCurrency' => $cur,
        'priceValidUntil' => $validUntil,
        'availability'  => (int)$p['stock'] > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
        'itemCondition' => ($p['condition'] ?? 'new') === 'new'
            ? 'https://schema.org/NewCondition'
            : 'https://schema.org/UsedCondition',
        'seller'        => ['@type' => 'Organization', 'name' => vr_product_seller($p)['name']],
        'hasMerchantReturnPolicy' => [
            '@type'               => 'MerchantReturnPolicy',
            'applicableCountry'   => 'DE',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'  => $retDays,
            'returnMethod'        => 'https://schema.org/ReturnByMail',
            'returnFees'          => 'https://schema.org/FreeReturn',
        ],
        'shippingDetails' => [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => vr_amount($effShip), 'currency' => $cur],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'DE'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                'transitTime'  => ['@type' => 'QuantitativeValue', 'minValue' => $tMin, 'maxValue' => $tMax, 'unitCode' => 'DAY'],
            ],
        ],
    ]);

    // Model kodu varsa mpn (üretici parça no). Gerçek GTIN elimizde yok, uydurmuyoruz.
    $mpn = function_exists('vr_model_code') ? trim((string)vr_model_code($p)) : '';

    return array_filter([
        '@type'       => 'Product',
        '@id'         => $url . '#product',
        'name'        => $p['brand'] . ' ' . vr_card_name($p),
        'brand'       => ['@type' => 'Brand', 'name' => $p['brand']],
        'sku'         => $p['sku'] ?: $p['id'],
        'mpn'         => $mpn !== '' ? $mpn : null,
        'category'    => $p['cat'],
        'description' => mb_substr($p['desc'] !== '' ? $p['desc'] : ($p['brand'] . ' ' . $p['name']), 0, 400),
        'image'       => $imgs,
        'url'         => $url,
        'offers'      => $offer,
    ]);
}

/**
 * ItemList — liste/koleksiyon sayfaları için (shop, marka, kategori, vault).
 * Sıralı ürün/sayfa adreslerini verir; Google koleksiyonu tek sonuçta
 * gruplayabilir. $urls göreli ya da mutlak olabilir.
 */
function vr_jsonld_itemlist(array $urls, int $limit = 30): array
{
    $items = [];
    $i = 1;
    foreach (array_slice(array_values(array_filter($urls, 'strlen')), 0, $limit) as $u) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i++,
            'url'      => str_starts_with($u, 'http') ? $u : vr_canonical_origin() . $u,
        ];
    }
    return ['@type' => 'ItemList', 'itemListElement' => $items];
}

function vr_jsonld_breadcrumbs(array $items): array
{
    $list = [];
    $i = 1;
    foreach ($items as $label => $url) {
        $list[] = array_filter([
            '@type'    => 'ListItem',
            'position' => $i++,
            'name'     => (string)$label,
            'item'     => $url ? vr_origin() . $url : null,
        ]);
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/** "Son bakılanlar" şeridi — boşsa hiç basılmaz. */
function vr_seen_strip(string $excludeId = ''): void
{
    $rows = vr_seen_products($excludeId, 6);
    if (count($rows) < 2) return;      // tek parça şerit olmaz

    echo '<section class="sec sec--tight"><div class="wrap">';
    vr_section_head(t('seen_title'));
    vr_grid($rows, ['class' => 'grid--6']);
    echo '</div></section>';
}

/** Kırıntı yolu (görsel). */
function vr_breadcrumbs(array $items): void
{
    echo '<nav class="crumbs" aria-label="Breadcrumb"><ol>';
    foreach ($items as $label => $url) {
        echo '<li>' . ($url ? '<a href="' . h($url) . '">' . h((string)$label) . '</a>' : '<span>' . h((string)$label) . '</span>') . '</li>';
    }
    echo '</ol></nav>';
}

/**
 * Boş durumların altına konan öneri şeridi.
 *
 * Boş sepet ve boş liste sayfaları tek bir uyarı kutusu ve bir düğmeden
 * ibaretti — ekranın onda dokuzu boş. Bir mağazada kimse müşteriyi boş bir
 * odada bırakmıyor. Dört parça, vitrin sırasından: her biri başka bir evden,
 * fotoğrafı vitrinlik olanlardan.
 */
function vr_empty_suggestions(int $n = 4): void
{
    $rows = vr_query([
        'per_page'      => $n,
        'in_stock'      => true,
        'exclude_vault' => true,
    ])['rows'];
    if (!$rows) return;

    echo '<hr class="rule" style="margin:clamp(34px,4.4vw,56px) 0">';
    vr_section_head(t('sec_curated'), '', vr_url('shop.php'));
    vr_grid($rows, ['class' => 'grid--4', 'size_hint' => true]);
}

// ------------------------------------------------------------- görsel ölçek
/**
 * Ürün fotoğrafının belirli genişlikteki hâli.
 *
 * Toptancı dosyaları 2000–3500 piksel; kart telefonda 170 piksel. Ölçekleyici
 * (assets/img.php) araya girmezse yirmi dört kartlık bir sayfa 7 MB iniyor.
 * /uploads/ dışındaki yollar (üretilen kampanya kareleri, art.php) olduğu
 * gibi geçiyor — onların zaten istenen boyutu var.
 */
function vr_img(string $rel, int $w = 600): string
{
    if ($rel === '' || !str_starts_with($rel, '/uploads/')) return $rel;
    // Kopya hazırsa düz dosya: sunucuda PHP çalışmıyor (bkz. inc/thumbs.php).
    // Hazır değilse img.php üretir; bir sonraki sayfa düz dosyayı alır.
    return vr_thumb_static($rel, $w) ?? vr_img_dynamic($rel, $w);
}

/** Her zaman img.php — tarayıcıya göre webp ya da jpeg (paylaşım önizlemeleri, tarayıcı dışı istemciler). */
function vr_img_dynamic(string $rel, int $w = 600): string
{
    if ($rel === '' || !str_starts_with($rel, '/uploads/')) return $rel;
    return vr_url('assets/img.php', ['p' => $rel, 'w' => (string)$w]);
}

/**
 * srcset dizesi. Tarayıcı ekran genişliğine ve piksel yoğunluğuna göre
 * hangisini indireceğine kendi karar versin.
 */
function vr_img_srcset(string $rel, array $widths = [300, 600, 900]): string
{
    if ($rel === '' || !str_starts_with($rel, '/uploads/')) return '';
    $out = [];
    foreach ($widths as $w) $out[] = vr_img($rel, (int)$w) . ' ' . (int)$w . 'w';
    return implode(', ', $out);
}

/**
 * Bir kareyi verilen kutuya nasıl yerleştireceğimiz.
 *
 * Kart 4:5 (0.80). Elimizdeki kareler 0.33 ile 2.2 arasında geziyor: askıda
 * çekilmiş palto upuzun, ızgaradan ayrılmış aksesuar paneli enlemesine. Hepsine
 * cover verirsek uzunun yüzü, geniş olanın omuzları kesiliyor.
 *
 * Kural iki basamaklı:
 *   oran kutuya yakınsa      → cover, özne merkezine göre hafifçe kaydırılmış
 *   oran kutudan uzaksa      → contain; arkası paket çekimiyse karenin kendi
 *                              kenar rengiyle, değilse aynı karenin bulanık
 *                              büyütmesiyle dolduruluyor (ek indirme yok,
 *                              tarayıcı 180 px'lik hâli zaten önbellekte
 *                              tutuyor).
 *
 * İndeks yoksa (data/photo-shape.json) her şey eski davranışa, düz cover'a
 * düşüyor — ölçüm katmanı isteğe bağlı.
 */
function vr_photo_shape_index(): array
{
    static $ix = null;
    if ($ix === null) {
        $raw = vr_store_read('photo-shape.json', []);
        $ix  = is_array($raw) ? $raw : [];
    }
    return $ix;
}

/**
 * @param float $box hedef kutunun en/boy oranı (kart 0.8)
 * @return array{mode:string,style:string} mode: 'cover' | 'pad' | 'tint'
 */
function vr_img_fit(string $rel, float $box = 0.8, bool $allowPad = true, bool $tile = false): array
{
    $s = vr_photo_shape_index()[$rel] ?? null;
    if (!is_array($s) || empty($s['r'])) return ['mode' => 'cover', 'style' => ''];

    $r = (float)$s['r'];
    // Pervazı kırpılan kare: kopyanın oranı ve kenar rengi içteki fotoğrafın.
    if (function_exists('vr_photo_trim') && ($t = vr_photo_trim($rel))) {
        [$a, $b, $c, $d] = array_map('floatval', $t['c']);
        if ($d - $b > 0) $r = $r * ($c - $a) / ($d - $b);
        $s['bg'] = $s['side'] = (string)($t['bg'] ?? '');
        unset($s['flat']);
    }

    // Kutuya yakın: kırpma payı her iki eksende de %18'in altında kalıyor,
    // göz bunu kırpma olarak görmüyor.
    //
    // $allowPad false ise (Vault: koyu zeminli karo) oran ne olursa olsun
    // cover kalıyor; beyaz paket çekimini koyu kartın ortasına beyaz bir kare
    // olarak oturtmak bölümün bütün havasını bozuyor. Orada tek yaptığımız,
    // kırpmayı öznenin bulunduğu yere doğru kaydırmak.
    // VİTRİN KAROSU DAR KAREYİ KIRPAR — ürün sayfası kırpmaz.
    //
    // Çok uzun kareler (r < 0.62, çoğu boy çekimi) karoya sığdırılınca ürün
    // ortada küçücük kalıyor ve iki yanı bulanık dolgu bandı oluyordu; 64
    // kartta böyleydi. Öncesi/sonrası kolajı yapıldı: on iki örneğin
    // hepsinde kırpılmış hâl daha iyi — giysi kadrajı dolduruyor, desen
    // okunuyor, bant yok. Kırpma özne merkezine göre yapılıyor, o yüzden
    // giysi değil çoğunlukla arka plan gidiyor.
    //
    // Bu YALNIZCA karo için. Ürün sayfasında amaç parçayı bütün görmek;
    // orada dolgu doğru davranış, kırpmak müşteriden bilgi saklamak olur.
    $tileCrop = $tile && $r > 0 && $r < 0.62;

    if (!$allowPad || $tileCrop || ($r > $box * 0.82 && $r < $box * 1.22)) {
        // Özne merkezi ortadaysa hiç uğraşma; kenara kaymışsa kırpmayı oraya
        // doğru çek — uzun bir paltoda alt kenar yerine yakayı korumak için.
        $cx = isset($s['cx']) ? max(20, min(80, (int)$s['cx'])) : 50;
        $cy = isset($s['cy']) ? max(20, min(80, (int)$s['cy'])) : 50;
        $st = ($cx === 50 && $cy === 50) ? '' : sprintf('--pos:%d%% %d%%', $cx, $cy);
        return ['mode' => 'cover', 'style' => $st];
    }

    $bg = preg_match('/^#[0-9a-f]{6}$/i', (string)($s['bg'] ?? '')) ? $s['bg'] : '#f4f2ee';
    // Dolgu sağa-sola geliyor: yan kenarların medyan rengi (yoksa ortalama).
    $side = !empty($s['flat']) ? $bg
          : (preg_match('/^#[0-9a-f]{6}$/i', (string)($s['side'] ?? '')) ? $s['side'] : $bg);

    // KATMANLI GÖRÜNÜM (14 Eki 2026, operatör: "bazı fotolar görünüşte
    // katmanlı duruyor"). Sığdırılan kare kartın taş tonunun ortasında kendi
    // gri stüdyo zemininin dikdörtgeni olarak kalıyordu. Artık:
    //   · <img> kutusu fotoğrafın kendi oranında (--fw/--fh), böylece kenar
    //     yumuşatma maskesi tam fotoğrafın kenarına oturuyor;
    //   · zemini beyaza yakın olmayan karede kart o fotoğrafın kendi kenar
    //     rengini alıyor (fr--own) — dikdörtgen yerine tek parça yüzey.
    $fw = $r < $box ? round($r / $box * 100, 2) : 100;
    $fh = $r < $box ? 100 : round($box / $r * 100, 2);
    $own = vr_hex_is_light($side) ? '' : ' own';
    $st  = '--tint:' . $side . ';--fw:' . $fw . '%;--fh:' . $fh . '%;--fd:' . ($r < $box ? '90deg' : '180deg');
    return ['mode' => (!empty($s['flat']) ? 'tint' : 'pad') . $own, 'style' => $st];
}

/** Zemin rengi "beyaz say" eşiğinde mi: açık ve renksiz (multiply onu kartın tonuna eritir). */
function vr_hex_is_light(string $hex): bool
{
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) return true;
    $c = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    return min($c) >= 236 && max($c) - min($c) <= 12;
}

/**
 * Kartın yüzü ve üzerine gelince beliren ikinci kare — <span class="fr">
 * sarmalıyla birlikte.
 *
 * Sarmal şart: bulanık zemin ayrı bir katman (::before) ve fotoğrafın kendisi
 * onun üstünde duruyor; ikisi tek <img> üzerinde olamıyor çünkü filter
 * fotoğrafı da bulandırırdı.
 */
function vr_frame(string $rel, array $o = []): void
{
    if ($rel === '') return;
    $fit  = vr_img_fit($rel, (float)($o['box'] ?? 0.8), empty($o['no_pad']), !empty($o['tile']));
    $w    = (int)($o['w'] ?? 600);
    $set  = (array)($o['widths'] ?? [300, 600, 900]);
    $cls  = 'fr fr--' . str_replace(' own', ' fr--own', $fit['mode']) . (!empty($o['class']) ? ' ' . $o['class'] : '');
?>
<span class="<?= h($cls) ?>"<?= $fit['style'] !== '' ? ' style="' . h($fit['style']) . '"' : '' ?>>
  <img src="<?= h(vr_img($rel, $w)) ?>" srcset="<?= h(vr_img_srcset($rel, $set)) ?>"
       sizes="<?= h((string)($o['sizes'] ?? '300px')) ?>"
       alt="<?= h((string)($o['alt'] ?? '')) ?>"<?= empty($o['alt']) ? ' aria-hidden="true"' : '' ?>
       loading="<?= empty($o['eager']) ? 'lazy' : 'eager' ?>" decoding="async"
       width="<?= $w ?>" height="<?= (int)round($w / (float)($o['box'] ?? 0.8)) ?>">
</span>
<?php
}
