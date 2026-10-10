<?php
/**
 * Häuser — marka dizini
 * Her ev için parça sayısı, fiyat aralığı ve ilk üç parçanın önizlemesi.
 * Amaç bir "logo duvarı" değil: hangi evde gerçekten derinlik var, orası görünsün.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

$facets = vr_facets(['exclude_vault' => true]);
$brands = $facets['brands'];

// Marka başına fiyat aralığı — tek geçişte topluyoruz.
$range = [];
foreach (vr_catalog() as $p) {
    $b = (string)$p['brand'];
    $c = (int)$p['price_cents'];
    if (!isset($range[$b])) $range[$b] = ['min' => $c, 'max' => $c];
    $range[$b]['min'] = min($range[$b]['min'], $c);
    $range[$b]['max'] = max($range[$b]['max'], $c);
}

vr_layout_start([
    'title' => t('sec_brands'),
    'desc'  => t('sec_brands') . ' — ' . t('tagline'),
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_brands')            => null,
    ])],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('nav_brands') => null]); ?>
    <div class="sechead">
      <div>
        <h1 class="sechead__t"><?= te('sec_brands') ?></h1>
        <p class="sechead__s"><?= te('footer_marketplace') ?></p>
      </div>
    </div>

    <?php if (!$brands): ?>
      <div class="notice"><strong><?= te('no_results') ?></strong></div>
    <?php else: ?>
      <div class="housegrid">
        <?php
        arsort($brands);
        foreach ($brands as $brand => $n):
            $preview = vr_query(['brand' => $brand, 'per_page' => 3, 'in_stock' => true, 'exclude_vault' => true])['rows'];
            $r = $range[$brand] ?? ['min' => 0, 'max' => 0];
        ?>
          <article class="housecard" data-reveal>
            <header class="housecard__head">
              <h2 class="housecard__t">
                <a href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>"><?= h(vr_brand_label((string)$brand)) ?></a>
              </h2>
              <p class="housecard__meta">
                <span><?= te('results_n', ['n' => (int)$n]) ?></span>
                <?php if ((int)$r['min'] > 0): ?>
                  <span><?= h(vr_money((int)$r['min'])) ?><?= (int)$r['max'] > (int)$r['min'] ? ' — ' . h(vr_money((int)$r['max'])) : '' ?></span>
                <?php endif; ?>
              </p>
            </header>

            <?php if ($preview): ?>
              <div class="housecard__row">
                <?php foreach ($preview as $p): ?>
                  <a href="<?= h(vr_product_url($p)) ?>" class="housecard__shot">
                    <span class="thumb45"><?php vr_frame(vr_card_image($p), [
                        'w'      => 300,
                        'widths' => [180, 300, 420],
                        'sizes'  => '(max-width:940px) 30vw, 200px',
                        'alt'    => $p['name'],
                    ]); ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <a class="sechead__more housecard__more" href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>">
              <?= te('view_all') ?><?= vr_icon('arrow', 15) ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
