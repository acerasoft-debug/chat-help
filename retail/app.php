<?php
/**
 * Uygulama — iPhone ve Android'e kurulum
 * --------------------------------------
 * İki yol, ikisi de mağazanın kendisi:
 *   • Ana ekran uygulaması (manifest + servis çalışanı): iPhone'da Safari →
 *     Paylaş → "Ana Ekrana Ekle", Android'de Chrome'un "Yükle" penceresi.
 *     Tam ekran, kendi ikonu, çevrimdışı sayfası.
 *   • Android paketi (APK): sunucuda uploads/app/<MARKA>.apk varsa indirme
 *     düğmesi çıkıyor (bkz. .github/workflows/sarvesto-android.yml).
 * JS kapalıyken de sayfa eksiksiz: iki platformun adımları her zaman yazılı;
 * JS yalnızca kullanılan cihazı öne alıyor ve "Yükle" düğmesini açıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/uploads.php';

$brand = (string)vr_config('brand');
$host  = (string)(parse_url(vr_origin(), PHP_URL_HOST) ?: 'sarvesto.com');
$apkRel = '/uploads/app/' . preg_replace('/[^A-Za-z0-9]/', '', $brand) . '.apk';
$apkAbs = vr_doc_root() . $apkRel;
$apk    = is_file($apkAbs) ? ['url' => vr_url(ltrim($apkRel, '/'), ['v' => filemtime($apkAbs)]),
                               'size' => number_format(filesize($apkAbs) / 1048576, 1, ',', '.') . ' MB'] : null;

$ios  = trim((string)vr_config('app_store_url'));
$play = trim((string)vr_config('play_store_url'));
// Mağaza düğmesi: resmi rozet görseli yerine evin kendi düğme dili — Apple ve
// Google'ın marka kuralları rozetin değiştirilmesine izin vermiyor, siyah-beyaz
// vitrinde renkli rozet de yabancı duruyordu.
$storeBtn = static function (string $url, string $label, string $cls = '') : string {
    return '<a class="btn btn--brass btn--lg ' . h($cls) . '" href="' . h($url) . '" rel="noopener"><span>'
        . h($label) . '</span>' . vr_icon('arrow', 16) . '</a>';
};

$shots = vr_query(['per_page' => 6, 'in_stock' => true, 'exclude_vault' => true])['rows'];

vr_layout_start([
    'title' => t('app_nav'),
    'desc'  => t('app_lede'),
]);
?>
<section class="sec vault apphero">
  <div class="wrap apphero__grid">
    <div class="apphero__txt">
      <p class="eyebrow"><?= te('app_eyebrow', ['brand' => $brand]) ?></p>
      <h1 class="apphero__t"><?= te('app_title') ?></h1>
      <p class="lede"><?= te('app_lede') ?></p>

      <p class="apphero__in" data-app-only><?= vr_icon('check', 16) ?> <?= te('app_installed') ?></p>

      <div class="hero__cta apphero__cta">
        <?php if ($ios !== ''): ?><?= $storeBtn($ios, t('app_store_btn'), 'apphero__store apphero__store--ios') ?><?php endif; ?>
        <?php if ($play !== ''): ?><?= $storeBtn($play, t('play_store_btn'), 'apphero__store apphero__store--play') ?><?php endif; ?>
        <button class="btn btn--brass btn--lg" type="button" data-app-install hidden>
          <span><?= te('app_install_btn') ?></span><?= vr_icon('arrow', 16) ?>
        </button>
        <?php if ($apk): ?>
          <a class="btn btn--ghost btn--lg apphero__apk" href="<?= h($apk['url']) ?>" download>
            <span><?= te('app_apk_btn') ?></span>
          </a>
        <?php endif; ?>
        <a class="btn btn--ghost btn--lg apphero__ios" href="#ios"><span><?= te('app_iphone') ?></span></a>
      </div>
    </div>

    <?php // Telefon: mağazanın kendisi, küçültülmüş — ekran görüntüsü değil, canlı ürünler. ?>
    <div class="phone" aria-hidden="true">
      <div class="phone__screen">
        <div class="phone__bar"><span class="phone__logo"><?= h($brand) ?>.</span></div>
        <div class="phone__grid">
          <?php foreach ($shots as $p): ?>
            <figure class="phone__card">
              <img src="<?= h(vr_img(vr_card_image($p), 300)) ?>" alt="" loading="lazy" decoding="async" width="300" height="375">
              <figcaption><b><?= h(vr_brand_label((string)$p['brand'])) ?></b><span><?= h(vr_money((int)$p['price_cents'])) ?></span></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="appways">
      <article class="appway appway--ios" id="ios">
        <header class="appway__h">
          <img src="<?= h(vr_url('assets/app/apple-touch-icon.png')) ?>" alt="" width="52" height="52">
          <h2><?= te('app_iphone') ?></h2>
        </header>
        <?php if ($ios !== ''): ?>
          <?= $storeBtn($ios, t('app_store_btn'), 'btn--block') ?>
        <?php endif; ?>
        <ol class="appsteps">
          <li><?= te('app_ios_1', ['host' => $host]) ?></li>
          <li><span>
            <?= te('app_ios_2') ?>
            <svg class="appsteps__ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M6 11H5v10h14V11h-1"/></svg>
          </span></li>
          <li><?= te('app_ios_3', ['brand' => $brand]) ?></li>
        </ol>
      </article>

      <article class="appway appway--android" id="android">
        <header class="appway__h">
          <img src="<?= h(vr_url('assets/app/icon-192.png')) ?>" alt="" width="52" height="52">
          <h2><?= te('app_android') ?></h2>
        </header>
        <?php if ($play !== ''): ?>
          <?= $storeBtn($play, t('play_store_btn'), 'btn--block') ?>
        <?php endif; ?>
        <button class="btn btn--block" type="button" data-app-install hidden>
          <span><?= te('app_install_btn') ?></span><?= vr_icon('arrow', 16) ?>
        </button>
        <?php if ($apk): ?>
          <a class="btn btn--ghost btn--block" href="<?= h($apk['url']) ?>" download><span><?= te('app_apk_btn') ?></span></a>
          <p class="appway__note"><?= te('app_apk_note', ['size' => $apk['size']]) ?></p>
        <?php endif; ?>
        <p class="appway__note"><?= te('app_android_alt') ?></p>
      </article>

      <article class="appway appway--desktop" id="desktop">
        <header class="appway__h">
          <span class="appway__screen" aria-hidden="true"><img src="<?= h(vr_url('assets/app/icon-192.png')) ?>" alt="" width="26" height="26"></span>
          <h2><?= te('app_desk_t') ?></h2>
        </header>
        <ol class="appsteps">
          <li><?= te('app_desk_1') ?></li>
          <li><?= te('app_desk_2') ?></li>
        </ol>
        <p class="appway__note"><?= te('app_desk_3', ['brand' => $brand]) ?></p>
      </article>
    </div>

    <div class="appfeats">
      <?php foreach (['1' => 'tag', '2' => 'heart', '3' => 'shield'] as $n => $ico): ?>
        <div class="appfeat">
          <?= vr_icon($ico, 22) ?>
          <h3><?= te('app_f' . $n . '_t') ?></h3>
          <p><?= te('app_f' . $n . '_b') ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="appdesk" data-desktop-only><?= te('app_desktop', ['url' => $host . '/app']) ?></p>
  </div>
</section>

<?php vr_layout_end(); ?>
