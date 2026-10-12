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
$apk   = vr_app_apk();

$ios  = trim((string)vr_config('app_store_url'));
$play = trim((string)vr_config('play_store_url'));
// Mağaza düğmesi: resmi rozet görseli yerine evin kendi düğme dili — Apple ve
// Google'ın marka kuralları rozetin değiştirilmesine izin vermiyor, siyah-beyaz
// vitrinde renkli rozet de yabancı duruyordu.
$storeBtn = static function (string $url, string $label, string $cls = '') : string {
    return '<a class="btn btn--brass btn--lg ' . h($cls) . '" href="' . h($url) . '" rel="noopener"><span>'
        . h($label) . '</span>' . vr_icon('arrow', 16) . '</a>';
};

// Telefon ekranları için kareler: temiz stüdyo çekimi, tek özne, dikey.
// Kapak için biraz daha uzun bir kare (manken), kartlar için 4:5'e yakın.
$shape = vr_photo_shape_index();
$pool  = vr_query(['per_page' => 40, 'in_stock' => true, 'exclude_vault' => true])['rows'];
$pick  = static function (array $rows, float $lo, float $hi, int $n, array &$used) use ($shape): array {
    $out = [];
    foreach ($rows as $p) {
        if (isset($used[$p['id']]) || count($out) >= $n) continue;
        $img = vr_card_image($p); $s = $shape[$img] ?? null;
        if (!is_array($s) || (int)($s['n'] ?? 1) > 1 || (float)($s['r'] ?? 0) < $lo || (float)($s['r'] ?? 0) > $hi) continue;
        $out[] = $p; $used[$p['id']] = 1;
    }
    return $out;
};
$used = [];
$cover = $pick($pool, 0.55, 0.80, 1, $used);   // kapak: dikey, manken olabilir
$cards = $pick($pool, 0.70, 0.95, 4, $used);   // kartlar: 4:5'e yakın stüdyo
$pdp   = $pick($pool, 0.70, 0.95, 1, $used);   // arka telefon: ürün sayfası
if (!$cards) $cards = array_slice($pool, 0, 4);
$cover = $cover[0] ?? ($pool[0] ?? null);
$pdp   = $pdp[0]   ?? ($pool[1] ?? null);

vr_layout_start([
    'title' => t('app_nav'),
    'desc'  => t('app_lede'),
]);
?>
<section class="sec vault apphero">
  <div class="wrap apphero__grid">
    <div class="apphero__txt">
      <div class="apphero__brand">
        <img class="apphero__icon" src="<?= h(vr_url('assets/app/icon-192.png')) ?>" alt="" width="60" height="60">
        <div>
          <p class="eyebrow"><?= te('app_eyebrow', ['brand' => $brand]) ?></p>
          <p class="apphero__meta"><?= te('app_iphone') ?> · <?= te('app_android') ?></p>
        </div>
      </div>
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

    <?php // Telefonlar: mağazanın kendisi, küçültülmüş — ekran görüntüsü değil,
          // canlı ürünler. Önde ana sayfa, arkada bir ürün sayfası. ?>
    <div class="phones" aria-hidden="true">
      <?php if ($pdp): ?>
      <div class="phone phone--back">
        <div class="phone__screen">
          <div class="phone__status"><span>9:41</span><span class="phone__sig"><i class="phone__bars"></i><i class="phone__wifi"></i><i class="phone__batt"></i></span></div>
          <span class="phone__island"></span>
          <div class="phone__bar"><span class="phone__logo"><?= h($brand) ?></span></div>
          <div class="phone__pdp">
            <?php vr_frame(vr_card_image($pdp), ['box' => 0.8, 'w' => 420, 'widths' => [300, 420, 600], 'sizes' => '280px']); ?>
          </div>
          <div class="phone__info">
            <b><?= h(vr_brand_label((string)$pdp['brand'])) ?></b>
            <span><?= h(vr_card_name($pdp)) ?></span>
            <strong><?= h(vr_money((int)$pdp['price_cents'])) ?></strong>
            <div class="phone__sizes"><i>S</i><i class="is-on">M</i><i>L</i><i>XL</i></div>
            <div class="phone__btn"><?= te('add_to_cart') ?></div>
          </div>
          <span class="phone__home"></span>
        </div>
      </div>
      <?php endif; ?>
      <div class="phone phone--front">
        <div class="phone__screen">
          <div class="phone__status"><span>9:41</span><span class="phone__sig"><i class="phone__bars"></i><i class="phone__wifi"></i><i class="phone__batt"></i></span></div>
          <span class="phone__island"></span>
          <div class="phone__bar"><span class="phone__logo"><?= h($brand) ?></span></div>
          <?php if ($cover): ?>
          <div class="phone__cover">
            <?php vr_frame(vr_card_image($cover), ['box' => 0.78, 'w' => 420, 'widths' => [300, 420, 600], 'sizes' => '280px', 'no_pad' => true]); ?>
            <div class="phone__coverttl">
              <small><?= te('nav_outlet') ?></small>
              <span><?= h(vr_brand_label((string)$cover['brand'])) ?></span>
            </div>
          </div>
          <?php endif; ?>
          <div class="phone__grid">
            <?php foreach ($cards as $p): ?>
              <figure class="phone__card">
                <?php vr_frame(vr_card_image($p), ['box' => 0.8, 'w' => 300, 'widths' => [180, 300], 'sizes' => '130px', 'tile' => true]); ?>
                <figcaption><b><?= h(vr_brand_label((string)$p['brand'])) ?></b><span><?= h(vr_money((int)$p['price_cents'])) ?></span></figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
          <span class="phone__home"></span>
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

    <?php // Bildirimler: kurulu uygulamada da görünür (appways orada gizli). ?>
    <div class="apppush" id="notifications">
      <?php vr_push_box('pushbox--wide'); ?>
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
