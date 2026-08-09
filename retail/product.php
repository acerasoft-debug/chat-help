<?php
/**
 * Ürün sayfası
 * ------------
 * Perakendede satın alma kararını veren üç şey burada yan yana:
 *  1) fiyat + KDV/kargo bilgisi (PAngV §1: brüt fiyat, kargo bilgisi zorunlu),
 *  2) beden bazında GERÇEK stok (toptan satırındaki beden dökümünden geliyor),
 *  3) satıcının kim olduğu ve hangi hukuki rejimin geçerli olduğu
 *     (Händler → gewährleistung + widerruf, Privatverkäufer → yok).
 *
 * Üçüncüsü pazaryerlerinde genelde küçük puntoda saklanır; burada ödeme
 * düğmesinin hemen yanında duruyor. Hem DSA md. 30 (satıcı izlenebilirliği)
 * hem de basitçe doğru olan şey bu.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/copy.php';

$id = trim((string)($_GET['id'] ?? ''));
$p  = $id !== '' ? vr_product($id) : null;

if ($p === null) {
    /* Satılmış ya da yanlış yazılmış bir ürün adresi 404 SAYFASINA gidiyor.
       Burada kendi kısa mesajı vardı: "Nothing matches those filters. Try a
       wider price range, or clear the house filter." — mağaza ızgarasının
       boş sonuç metni, tek bir ürün adresinde hiçbir anlamı yok, üstelik
       ekranda arama kutusu ve tek bir öneri bile bırakmıyordu. 404 sayfası
       adresten model kodunu çıkarıp benzerlerini gösteriyor; tek parça
       satıldığında alıcının önüne konması gereken tam olarak o. */
    require __DIR__ . '/404.php';
    exit;
}

// Bu ürün Vault'ta mı? Öyleyse fiyat oradan gelir, kullanıcıyı oraya yolluyoruz.
$vaultLot = null;
foreach (vr_vault_lots(['include_sold' => true]) as $v) {
    if ($v['product']['id'] === $p['id']) { $vaultLot = $v; break; }
}

// Son bakılanlar çerezine ekle (görüntüleme kaydı yalnızca kullanıcının
// cihazında kalıyor; sunucuda profil oluşmuyor).
vr_seen_push((string)$p['id']);

$seller   = vr_product_seller($p);
$related  = vr_related($p, 4);
$soldOut  = (int)$p['stock'] < 1;
$vatRate  = (int)vr_config('vat_rate_bps', 1900) / 100;
$shipDe   = (array)vr_config('shipping')['de'];
$days     = (int)vr_config('return_days', 30);
$sizeErr  = !empty($_GET['nosize']);

vr_layout_start([
    'title'    => $p['brand'] . ' ' . vr_card_name($p),
    'desc'     => $p['brand'] . ' ' . vr_card_name($p) . ' — ' . vr_product_teaser($p),
    'og_image' => vr_product_image($p),
    'jsonld'   => [
        vr_jsonld_product($p, $vaultLot ? (int)$vaultLot['state']['cents'] : null),
        vr_jsonld_breadcrumbs([
            (string)vr_config('brand') => vr_url('/'),
            t('nav_shop')              => vr_url('shop.php'),
            $p['brand']                => vr_url('shop.php', ['brand' => $p['brand']]),
            vr_card_name($p)           => null,
        ]),
    ],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([
        t('nav_shop')    => vr_url('shop.php'),
        $p['brand']      => vr_url('shop.php', ['brand' => $p['brand']]),
        vr_cat_label($p['cat']) => vr_url('shop.php', ['cat' => $p['cat']]),
        vr_card_name($p) => null,
    ]); ?>

    <div class="pdp">
      <!-- ------------------------------------------------------- görseller
           Her görsel kendi dosyasına giden bir bağlantı: JavaScript kapalıyken
           tıklayınca tam boy açılıyor. JS varsa aynı bağlantıyı yakalayıp
           büyüteç katmanında gösteriyoruz (assets/js). -->
      <?php $shots = vr_product_gallery($p, 5); ?>
      <div class="pdp__media<?= count($shots) > 1 ? ' pdp__media--multi' : '' ?>" data-gallery>
        <?php foreach ($shots as $i => $src):
            $box = count($shots) > 1 ? 0.8 : 1.0;
            // Tek kareli üründe kutuyu fotoğrafa uyduruyoruz. Enlemesine bir
            // kareyi zorla 1:1'e sokmak, ürünün üstüne ve altına kocaman
            // bulanık şerit koymak demek — ürün sayfasında bunun mazereti yok.
            if (count($shots) === 1) {
                $r = (float)(vr_photo_shape_index()[$src]['r'] ?? 0);
                if ($r > 0) $box = max(0.62, min(1.5, $r));
            }
        ?>
          <a class="pdp__shot" href="<?= h($src) ?>" data-zoom
             <?= count($shots) === 1 ? 'style="aspect-ratio:' . h((string)round($box, 3)) . '"' : '' ?>
             aria-label="<?= te('zoom') ?>: <?= h($p['brand'] . ' ' . vr_card_name($p)) ?>">
            <?php vr_frame($src, [
                'box'    => $box,
                'w'      => 900,
                'widths' => [420, 600, 900, 1400],
                'sizes'  => '(max-width:940px) 96vw, 640px',
                'alt'    => $p['brand'] . ' ' . $p['name'] . ($i > 0 ? ' — ' . ($i + 1) : ''),
                'eager'  => $i === 0,
            ]); ?>
            <span class="pdp__zoom" aria-hidden="true"><?= vr_icon('search', 16) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ------------------------------------------------------- satın alma -->
      <div class="pdp__side">
        <div class="pdp__head">
          <div>
            <p class="pdp__brand"><a href="<?= h(vr_url('shop.php', ['brand' => $p['brand']])) ?>"><?= h($p['brand']) ?></a></p>
            <h1><?= h(vr_card_name($p)) ?></h1>
          </div>
          <?php vr_wish_button($p, true); ?>
        </div>

        <?php if ($vaultLot !== null): ?>
          <!-- Vault'taki bir parça: fiyatı plan belirliyor, satış oradan yürüyor -->
          <div class="notice" style="border-left-color:var(--ember)">
            <strong><?= te('nav_outlet') ?>.</strong>
            <?= te('vault_lede') ?>
          </div>
          <div class="pdp__price">
            <b><?= h(vr_money((int)$vaultLot['state']['cents'])) ?></b>
            <?php if ($vaultLot['state']['reference'] !== null): ?>
              <s><?= h(vr_money((int)$vaultLot['state']['reference'])) ?></s>
            <?php endif; ?>
          </div>
          <p class="pdp__vat"><?= te('vat_incl', ['rate' => $vatRate]) ?></p>
          <a class="btn btn--brass btn--block btn--lg" href="<?= h(vr_url('outlet.php', ['lot' => $vaultLot['lot_id']])) ?>">
            <span><?= $vaultLot['sold'] ? te('vault_gone') : te('vault_take_it', ['amount' => vr_money((int)$vaultLot['state']['cents'])]) ?></span>
          </a>

        <?php else: ?>
          <div class="pdp__price">
            <b><?= h(vr_money((int)$p['price_cents'])) ?></b>
            <?php if (!empty($p['rrp_cents']) && (int)$p['rrp_cents'] > (int)$p['price_cents']): ?>
              <s><?= h(vr_money((int)$p['rrp_cents'])) ?></s>
            <?php endif; ?>
          </div>
          <p class="pdp__vat">
            <?= te('vat_incl', ['rate' => $vatRate]) ?>
            <?php if ((int)($shipDe['free_over'] ?? 0) > 0): ?>
              · <?= te('free_ship_over', ['amount' => vr_money((int)$shipDe['free_over'])]) ?>
            <?php endif; ?>
          </p>

          <?php
          /* Renk şeridi.
             Toptancı beslemesi her rengi ayrı ürün olarak açtığı için vitrinde
             modelin tek karosu duruyor (bkz. vr_variant_index). Diğer renklere
             giden tek kapı burası — şerit olmazsa o satırlar erişilemez kalır. */
          $siblings = vr_variant_siblings($p);
          if (count($siblings) > 1): ?>
            <div class="swatches">
              <h2 class="panel__h"><span><?= te('select_colour', ['n' => count($siblings)]) ?></span></h2>
              <div class="swatches__row">
                <?php foreach ($siblings as $sv):
                    $on  = $sv['id'] === $p['id'];
                    $col = vr_variant_colour($sv);
                    $lbl = $col !== '' ? $col : vr_card_name($sv);
                ?>
                  <a class="swatch<?= $on ? ' is-on' : '' ?><?= (int)$sv['stock'] < 1 ? ' is-out' : '' ?>"
                     href="<?= h(vr_product_url($sv)) ?>" title="<?= h($lbl) ?>"
                     <?= $on ? 'aria-current="page"' : '' ?>>
                    <?php vr_frame(vr_card_image($sv), ['w' => 180, 'widths' => [180, 300], 'sizes' => '62px']); ?>
                    <span class="swatch__l"><?= h($lbl) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($soldOut): ?>
            <span class="btn btn--block is-disabled"><span><?= te('sold_out') ?></span></span>
            <p style="font-size:12.5px;color:var(--muted)"><?= te('you_may_like') ?> ↓</p>
          <?php else: ?>
            <form method="post" action="<?= h(vr_url('cart.php')) ?>" data-needs-size>
              <?= vr_csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="pid" value="<?= h($p['id']) ?>">

              <?php
              $single = count($p['sizes']) === 1 && ($p['sizes'][0]['label'] ?? '') === 'ONE';
              if ($single): ?>
                <input type="hidden" name="size" value="ONE">
                <p class="pdp__vat" style="margin-bottom:14px">
                  <?= te('one_of_one') ?> · <?= (int)$p['stock'] <= 2 ? te('low_stock', ['n' => (int)$p['stock']]) : te('in_stock') ?>
                </p>
              <?php else: ?>
                <div class="panel" style="border:0;padding-top:0;margin-bottom:16px">
                  <h2 class="panel__h">
                    <span><?= te('select_size') ?></span>
                    <a class="link" href="<?= h(vr_url('size-guide.php', ['cat' => $p['cat']])) ?>">
                      <?= vr_icon('ruler', 14) ?> <?= te('size_guide') ?>
                    </a>
                  </h2>
                  <div class="sizes">
                    <?php foreach ($p['sizes'] as $s): ?>
                      <label class="size">
                        <input type="radio" name="size" value="<?= h($s['label']) ?>">
                        <?= h($s['label']) ?>
                        <?php if ((int)$s['qty'] <= 2): ?><i><?= (int)$s['qty'] ?>×</i><?php endif; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <p class="field__err" data-size-error <?= $sizeErr ? '' : 'hidden' ?>><?= te('size_missing') ?></p>
                </div>
              <?php endif; ?>

              <button class="btn btn--block btn--lg" type="submit"><span><?= te('add_to_cart') ?></span></button>
            </form>
          <?php endif; ?>
        <?php endif; ?>

        <!-- --------------------------------------------------- satıcı / hukuk -->
        <div class="panel panel--seller">
          <?= vr_icon($seller['type'] === 'private' ? 'user' : 'shield', 22) ?>
          <div>
            <p class="seller__name"><?= h($seller['name']) ?></p>
            <p class="seller__type"><?= te('sold_by') ?> · <?= te('seller_' . $seller['type']) ?><?php
                if ($seller['country'] !== '') echo ' · ' . h($seller['country']);
            ?></p>
            <p class="seller__note"><?= te('seller_' . $seller['type'] . '_note', ['days' => $days]) ?></p>
          </div>
        </div>

        <!-- ------------------------------------------------------- herkunft -->
        <div class="panel">
          <h2><?= te('provenance') ?></h2>
          <p><?= te('provenance_body') ?></p>
        </div>

        <?php $copy = vr_product_copy($p); ?>
        <div class="panel">
          <h2><?= te('description') ?></h2>
          <?php if (($copy['lead'] ?? '') !== ''): ?>
            <p class="pdp__lead"><?= h($copy['lead']) ?></p>
          <?php endif; ?>
          <?php foreach ($copy['paras'] as $para): ?>
            <p><?= h($para) ?></p>
          <?php endforeach; ?>
        </div>

        <?php if ($copy['facts']): ?>
          <div class="panel">
            <h2><?= te('details') ?></h2>
            <dl class="dl">
              <?php foreach ($copy['facts'] as $label => $value): ?>
                <dt><?= h($label) ?></dt><dd><?= h($value) ?></dd>
              <?php endforeach; ?>
            </dl>
          </div>
        <?php endif; ?>

        <div class="panel">
          <h2><?= te('returns_title') ?></h2>
          <p><?= vr_icon('truck', 15) ?> <?= te('ships_in', ['days' => h((string)($shipDe['days'] ?? '1–3'))]) ?>
             · <?= h(vr_zone_label('de')) ?> <?= (int)($shipDe['cents'] ?? 0) === 0 ? te('shipping_free') : h(vr_money((int)$shipDe['cents'])) ?></p>
          <p><?= vr_icon('check', 15) ?> <?= te('order_next_3', ['days' => $days]) ?></p>
          <p style="font-size:12.5px;color:var(--muted)">
            <a class="link" href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> ·
            <a class="link" href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>
          </p>
        </div>

        <div class="panel">
          <h2><?= te('secure_payment') ?></h2>
          <p><?= vr_icon('lock', 15) ?> <?= te('payment_note') ?></p>
          <p style="font-size:11px;letter-spacing:.1em;color:var(--muted-2)"><?= h(implode(' · ', vr_payment_methods())) ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_section_head(t('you_may_like'), '', vr_url('shop.php', ['brand' => $p['brand']])); ?>
    <?php vr_grid($related, ['class' => 'grid--4']); ?>
  </div>
</section>
<?php endif; ?>

<?php vr_seen_strip((string)$p['id']); ?>

<?php vr_layout_end(); ?>
