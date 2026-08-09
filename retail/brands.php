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
      <div class="grid grid--2">
        <?php
        arsort($brands);
        foreach ($brands as $brand => $n):
            $preview = vr_query(['brand' => $brand, 'per_page' => 3, 'in_stock' => true, 'exclude_vault' => true])['rows'];
            $r = $range[$brand] ?? ['min' => 0, 'max' => 0];
        ?>
          <article class="doc__box" style="margin:0" data-reveal>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:baseline;gap:6px 14px">
              <h2 style="font-family:var(--serif);font-size:23px;letter-spacing:.04em">
                <a href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>"><?= h((string)$brand) ?></a>
              </h2>
              <span style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)">
                <?= te('results_n', ['n' => (int)$n]) ?>
              </span>
            </div>

            <?php if ((int)$r['min'] > 0): ?>
              <p style="font-size:13px;color:var(--muted);margin-top:6px">
                <?= h(vr_money((int)$r['min'])) ?><?= (int)$r['max'] > (int)$r['min'] ? ' — ' . h(vr_money((int)$r['max'])) : '' ?>
              </p>
            <?php endif; ?>

            <?php if ($preview): ?>
              <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px">
                <?php foreach ($preview as $p): ?>
                  <a href="<?= h(vr_product_url($p)) ?>" style="display:block">
                    <span class="thumb45"><?php vr_frame(vr_card_image($p), [
                        'w'      => 300,
                        'widths' => [180, 300, 420],
                        'sizes'  => '(max-width:940px) 30vw, 180px',
                        'alt'    => $p['name'],
                    ]); ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <p style="margin-top:14px">
              <a class="sechead__more" href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>">
                <?= te('view_all') ?><?= vr_icon('arrow', 15) ?>
              </a>
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
