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
require_once __DIR__ . '/customers.php'; // alıcı hesapları (başlıktaki kişi ikonu)

/** Sürüm etiketi — CSS/JS önbelleğini deploy'da tazelemek için. */
function vr_asset_v(): string
{
    static $v = null;
    if ($v !== null) return $v;
    $f = VR_ROOT . '/assets/css/maxsales.css';
    return $v = is_readable($f) ? (string)filemtime($f) : '1';
}

/**
 * Sayfa başlangıcı.
 * $o: title, desc, canonical, body_class, jsonld (dizi|dizi listesi), robots,
 *     dark (bool → koyu üst çubuk), og_image
 */
function vr_layout_start(array $o = []): void
{
    $brand = (string)vr_config('brand');
    $title = trim((string)($o['title'] ?? ''));
    $full  = $title === '' ? $brand . ' — ' . t('tagline') : $title . ' · ' . $brand;
    $desc  = trim((string)($o['desc'] ?? t('tagline')));
    $canon = (string)($o['canonical'] ?? vr_origin() . strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?'));

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
<link rel="alternate" hreflang="<?= h($lg) ?>" href="<?= h(vr_origin() . vr_lang_url($lg)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= h(vr_origin() . vr_lang_url((string)vr_config('primary_lang', 'en'))) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= h($brand) ?>">
<meta property="og:title" content="<?= h($full) ?>">
<meta property="og:description" content="<?= h(mb_substr($desc, 0, 200)) ?>">
<meta property="og:url" content="<?= h($canon) ?>">
<meta property="og:image" content="<?= h(!empty($o['og_image']) ? (str_starts_with((string)$o['og_image'], 'http') ? (string)$o['og_image'] : vr_origin() . $o['og_image']) : vr_abs('assets/art.php', ['s' => 'maxsales', 'w' => 1200, 'h' => 630])) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0a0a0e">

<link rel="icon" href="<?= h(vr_url('assets/mark.php')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/maxsales.css', ['v' => vr_asset_v()])) ?>">
<?php // JS kapalıyken beliriş efekti hiç uygulanmasın (scripting:enabled'i
      // desteklemeyen eski tarayıcılar için ikinci emniyet). ?>
<noscript><style>[data-reveal]{opacity:1 !important;transform:none !important}</style></noscript>
<?php
    // JSON-LD: mağaza kimliği her sayfada, sayfaya özel şema varsa üstüne eklenir.
    $graph = [vr_jsonld_org()];
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
    ?>
<script src="<?= h(vr_url('assets/js/maxsales.js', ['v' => vr_asset_v()])) ?>" defer></script>
</body>
</html>
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
<div class="ticker" aria-hidden="true"><div class="ticker__track">
<?php for ($i = 0; $i < 3; $i++): ?><span class="ticker__item"><?= te('ticker', ['days' => $days]) ?></span><?php endfor; ?>
</div></div>

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

<div class="drawer" data-drawer hidden>
  <div class="drawer__panel">
    <button class="drawer__x" type="button" data-drawer-close aria-label="<?= te('close') ?>">&times;</button>
    <a href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>"><?= te('nav_new') ?></a>
    <a href="<?= h(vr_url('shop.php')) ?>"><?= te('nav_shop') ?></a>
    <a href="<?= h(vr_url('outlet.php')) ?>"><?= te('nav_outlet') ?></a>
    <a href="<?= h(vr_url('brands.php')) ?>"><?= te('nav_brands') ?></a>
    <a href="<?= h(vr_url('sell.php')) ?>"><?= te('nav_sell') ?></a>
    <a href="<?= h(vr_url('journal.php')) ?>"><?= te('nav_journal') ?></a>
    <a href="<?= h(vr_url('faq.php')) ?>"><?= te('nav_faq') ?></a>
    <a href="<?= h(vr_url('wishlist.php')) ?>"><?= te('nav_wish') ?></a>
    <a href="<?= h(vr_url($acctUrl)) ?>"><?= te('nav_account') ?></a>
    <div class="drawer__langs"><?= vr_lang_switch() ?></div>
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

    <nav class="foot__col" aria-label="<?= te('nav_shop') ?>">
      <h2><?= te('nav_shop') ?></h2>
      <a href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>"><?= te('nav_new') ?></a>
      <a href="<?= h(vr_url('outlet.php')) ?>"><?= te('nav_outlet') ?></a>
      <a href="<?= h(vr_url('brands.php')) ?>"><?= te('nav_brands') ?></a>
      <a href="<?= h(vr_url('shop.php', ['seller' => 'private'])) ?>"><?= te('seller_private') ?></a>
      <a href="<?= h(vr_url('wishlist.php')) ?>"><?= te('nav_wish') ?></a>
      <a href="<?= h(vr_url('journal.php')) ?>"><?= te('nav_journal') ?></a>
    </nav>

    <nav class="foot__col" aria-label="<?= te('footer_service') ?>">
      <h2><?= te('footer_service') ?></h2>
      <a href="<?= h(vr_url('faq.php')) ?>"><?= te('faq_title') ?></a>
      <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>
      <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>
      <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>
      <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a>
      <a href="<?= h(vr_url('account/login.php')) ?>"><?= te('acc_title') ?></a>
      <a href="<?= h(vr_url('size-guide.php')) ?>"><?= te('size_guide') ?></a>
      <a href="<?= h(vr_url('contact.php')) ?>"><?= te('nav_contact') ?></a>
    </nav>

    <nav class="foot__col" aria-label="<?= te('footer_company') ?>">
      <h2><?= te('footer_company') ?></h2>
      <a href="<?= h(vr_url('about.php')) ?>"><?= te('about_title') ?></a>
      <a href="<?= h(vr_url('journal.php')) ?>"><?= te('nav_journal') ?></a>
      <a href="<?= h(vr_url('sell.php')) ?>"><?= te('nav_sell') ?></a>
      <a href="<?= h(vr_url('seller/login.php')) ?>"><?= te('sell_login') ?></a>
      <a href="<?= h(vr_url('newsletter.php')) ?>"><?= te('news_title') ?></a>
      <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>
    </nav>

    <nav class="foot__col" aria-label="<?= te('footer_legal') ?>">
      <h2><?= te('footer_legal') ?></h2>
      <a href="<?= h(vr_url('legal/impressum.php')) ?>"><?= te('legal_imprint') ?></a>
      <a href="<?= h(vr_url('legal/agb.php')) ?>"><?= te('legal_terms') ?></a>
      <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>
      <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>
      <a href="<?= h(vr_url('legal/verkaeufer.php')) ?>"><?= te('legal_seller_terms') ?></a>
      <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>
      <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>
      <a href="<?= h(vr_url('legal/barrierefreiheit.php')) ?>"><?= te('legal_accessibility') ?></a>
    </nav>
  </div>

  <div class="foot__pay"><?php foreach (vr_payment_marks() as $m): ?><span class="pay"><?= h($m) ?></span><?php endforeach; ?></div>

  <div class="foot__bottom">
    <p>&copy; <?= date('Y') ?> <?= h((string)($co['legal_name'] ?? 'Acerasoft LLC')) ?>. <?= te('footer_rights') ?></p>
    <p><?= te('footer_vat_note') ?> <?= te('order_next_3', ['days' => $days]) ?></p>
  </div>
</footer>
<?php
}

function vr_payment_marks(): array
{
    return ['VISA', 'MASTERCARD', 'AMEX', 'APPLE PAY', 'GOOGLE PAY', 'KLARNA', 'SEPA'];
}

function vr_lang_switch(): string
{
    $out = '<div class="langs" role="group" aria-label="' . h(t('language')) . '">';
    foreach ((array)vr_config('languages', ['en']) as $lg) {
        $on = $lg === vr_lang();
        $out .= '<a class="lang' . ($on ? ' is-on' : '') . '" href="' . h(vr_lang_url($lg)) . '"'
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
function vr_card(array $p, array $o = []): void
{
    $seller = function_exists('vr_product_seller') ? vr_product_seller($p) : ['type' => 'vestra'];
    $soldOut = (int)$p['stock'] < 1;
    $low     = !$soldOut && (int)$p['stock'] <= 2;
?>
<article class="card<?= $soldOut ? ' is-out' : '' ?>" data-reveal>
  <a class="card__media" href="<?= h(vr_product_url($p)) ?>">
    <img src="<?= h(vr_product_image($p)) ?>" alt="<?= h($p['brand'] . ' ' . $p['name']) ?>"
         loading="<?= empty($o['eager']) ? 'lazy' : 'eager' ?>" decoding="async" width="600" height="750">
    <?php // İkinci kare yalnızca üzerine gelince görünür; dekoratif olduğu için
          // alt="" ve aria-hidden — ekran okuyucu aynı ürünü iki kez okumasın.
          $alt2 = vr_product_image_alt($p); if ($alt2 !== ''): ?>
      <img class="card__alt" src="<?= h($alt2) ?>" alt="" aria-hidden="true"
           loading="lazy" decoding="async" width="600" height="750">
    <?php endif; ?>
    <?php if (!empty($o['badge'])): ?><span class="card__badge"><?= h((string)$o['badge']) ?></span><?php endif; ?>
    <?php if ($seller['type'] === 'private'): ?><span class="card__badge card__badge--priv"><?= te('seller_private') ?></span><?php endif; ?>
    <?php if ($soldOut): ?><span class="card__out"><?= te('sold_out') ?></span><?php endif; ?>
  </a>
  <?php // Merkliste düğmesi bağlantının DIŞINDA: <a> içine <form> koymak
        // geçersiz HTML ve tıklama davranışı öngörülemez oluyor.
        vr_wish_button($p); ?>
  <div class="card__body">
    <p class="card__brand"><?= h($p['brand']) ?></p>
    <h3 class="card__name"><a href="<?= h(vr_product_url($p)) ?>"><?= h(vr_card_name($p)) ?></a></h3>
    <p class="card__price">
      <?= h(vr_money((int)$p['price_cents'])) ?>
      <?php if (!empty($p['rrp_cents']) && (int)$p['rrp_cents'] > (int)$p['price_cents']): ?>
        <s><?= h(vr_money((int)$p['rrp_cents'])) ?></s>
      <?php endif; ?>
    </p>
    <?php if (!empty($o['size_hint']) && $p['sizes']): ?>
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
    <img src="<?= h(vr_product_image($p)) ?>" alt="<?= h($p['brand'] . ' ' . $p['name']) ?>"
         loading="<?= empty($o['eager']) ? 'lazy' : 'eager' ?>" decoding="async" width="600" height="750">
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
    $grid = vr_photo_grid_index();

    foreach ([true, false] as $preferSingle) {
        foreach ($rows as $p) {
            foreach ((array)($p['images'] ?? []) as $img) {
                $img = (string)$img;
                if ($img === '' || in_array($img, $skip, true)) continue;
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
    $org = [
        '@type' => 'OnlineStore',
        '@id'   => vr_origin() . '/#store',
        'name'  => (string)vr_config('brand'),
        'url'   => vr_origin(),
        'slogan' => t('tagline'),
        'currenciesAccepted' => (string)vr_config('currency', 'EUR'),
        'paymentAccepted'    => implode(', ', vr_payment_methods()),
    ];
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
function vr_jsonld_product(array $p, ?int $overridePrice = null): array
{
    $price = $overridePrice ?? (int)$p['price_cents'];
    return array_filter([
        '@type'       => 'Product',
        'name'        => $p['brand'] . ' ' . vr_card_name($p),
        'brand'       => ['@type' => 'Brand', 'name' => $p['brand']],
        'sku'         => $p['sku'] ?: $p['id'],
        'category'    => $p['cat'],
        'description' => mb_substr($p['desc'] !== '' ? $p['desc'] : ($p['brand'] . ' ' . $p['name']), 0, 400),
        'image'       => vr_origin() . vr_product_image($p),
        'offers'      => [
            '@type'         => 'Offer',
            'url'           => vr_origin() . vr_product_url($p),
            'price'         => vr_amount($price),
            'priceCurrency' => (string)vr_config('currency', 'EUR'),
            'availability'  => (int)$p['stock'] > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => ($p['condition'] ?? 'new') === 'new'
                ? 'https://schema.org/NewCondition'
                : 'https://schema.org/UsedCondition',
            'seller'        => ['@type' => 'Organization', 'name' => vr_product_seller($p)['name']],
        ],
    ]);
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
