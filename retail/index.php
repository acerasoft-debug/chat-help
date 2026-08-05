<?php
/**
 * Anasayfa
 * --------
 * Akış bilinçli: KOYU sinematik hero → açık kuratöryel ızgara → KOYU Vault
 * odası → güven şeridi → evler → üyelik. Koyu/açık geçişleri "ayrı odalar"
 * hissi veriyor; Premium Outlet böylece sitenin içinde ayrı bir mekân gibi
 * duruyor, sıradan bir "indirim" sekmesi gibi değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

$curated = vr_query(['per_page' => 8, 'in_stock' => true, 'exclude_vault' => true])['rows'];
$fresh   = vr_query(['per_page' => 4, 'sort' => 'new', 'in_stock' => true, 'exclude_vault' => true])['rows'];
$lots    = vr_vault_lots(['limit' => 3]);
$facets  = vr_facets();
$total   = vr_query(['per_page' => 1])['total'];
$nextDrop = vr_vault_next_drop();
$days    = (int)vr_config('return_days', 30);
$heroArt = vr_url('assets/art.php', ['s' => 'vestra-hero-' . gmdate('YW'), 'w' => 1600, 'h' => 1000]);
$heroImg = !empty($curated[0]['images'][0]) ? $curated[0]['images'][0] : $heroArt;

vr_layout_start([
    'desc'    => t('hero_sub'),
    'jsonld'  => [[
        '@type'  => 'WebSite',
        'name'   => (string)vr_config('brand'),
        'url'    => vr_origin(),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => vr_abs('shop.php') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ]],
]);
?>

<!-- ------------------------------------------------------------------ hero -->
<section class="hero">
  <div class="hero__art"><img src="<?= h($heroImg) ?>" alt="" fetchpriority="high" width="1600" height="1000"></div>
  <div class="hero__in">
    <p class="eyebrow"><?= te('hero_eyebrow') ?></p>
    <h1><?= te('hero_title') ?></h1>
    <p class="hero__sub"><?= te('hero_sub') ?></p>

    <div class="hero__cta">
      <a class="btn btn--brass btn--lg" href="<?= h(vr_url('outlet.php')) ?>"><span><?= te('hero_cta') ?></span><?= vr_icon('arrow', 16) ?></a>
      <a class="btn btn--ghost btn--lg" style="color:var(--bone);border-color:rgba(245,242,236,.4)" href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>"><span><?= te('hero_cta2') ?></span></a>
    </div>

    <div class="hero__stats">
      <div class="hero__stat"><b><?= (int)$total ?></b><span><?= te('hero_stat_lots') ?></span></div>
      <div class="hero__stat"><b><?= count($facets['brands']) ?></b><span><?= te('hero_stat_brands') ?></span></div>
      <?php if ($nextDrop): ?>
        <div class="hero__stat">
          <b><span data-countdown="<?= (int)$nextDrop ?>">—</span></b>
          <span><?= te('hero_stat_drop') ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="wrap"><?php vr_demo_banner(); ?></div>

<!-- -------------------------------------------------------------- kuratöryel -->
<section class="sec">
  <div class="wrap">
    <?php vr_section_head(t('sec_curated'), t('sec_curated_sub'), vr_url('shop.php')); ?>
    <?php vr_grid($curated, ['size_hint' => true, 'eager_first' => true, 'class' => 'grid--4']); ?>
  </div>
</section>

<!-- --------------------------------------------------------------- Vault odası -->
<section class="sec vault">
  <div class="wrap">
    <?php vr_section_head(t('sec_vault'), t('sec_vault_sub'), vr_url('outlet.php'), t('vault_title')); ?>

    <?php if ($lots): ?>
      <div class="lots"><?php foreach ($lots as $v) vr_vault_card($v); ?></div>
    <?php else: ?>
      <p class="lede"><?= te('vault_empty') ?></p>
    <?php endif; ?>

    <hr class="rule" style="margin:clamp(38px,5vw,64px) 0">

    <div class="steps">
      <div class="step" data-reveal>
        <b>01</b>
        <h3><?= te('vault_step1_t') ?></h3>
        <p><?= te('vault_step1_b', ['steps' => (int)vr_config('vault_steps', 6) + 1]) ?></p>
      </div>
      <div class="step" data-reveal>
        <b>02</b>
        <h3><?= te('vault_step2_t') ?></h3>
        <p><?= te('vault_step2_b', ['hours' => (int)vr_config('vault_step_hours', 24)]) ?></p>
      </div>
      <div class="step" data-reveal>
        <b>03</b>
        <h3><?= te('vault_step3_t') ?></h3>
        <p><?= te('vault_step3_b') ?></p>
      </div>
    </div>
  </div>
</section>

<!-- -------------------------------------------------------------- güven şeridi -->
<section class="sec">
  <div class="wrap">
    <?php vr_section_head(t('sec_editorial')); ?>
    <div class="trust">
      <?php
      $trust = [
          ['doc', 'trust_1_title', 'trust_1_body'],
          ['tag', 'trust_2_title', 'trust_2_body'],
          ['user', 'trust_3_title', 'trust_3_body'],
          ['shield', 'trust_4_title', 'trust_4_body'],
      ];
      foreach ($trust as [$ico, $tk, $bk]): ?>
        <div data-reveal>
          <div class="trust__i"><?= vr_icon($ico, 26) ?></div>
          <h3><?= te($tk) ?></h3>
          <p><?= te($bk) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ------------------------------------------------------------------- evler -->
<?php if ($facets['brands']): ?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_section_head(t('sec_brands'), '', vr_url('brands.php')); ?>
    <div class="brands" data-reveal>
      <?php
      // En çok ürünü olan 8 ev — vitrinde derinliği olan isimler önce.
      $top = $facets['brands'];
      arsort($top);
      foreach (array_slice($top, 0, 8, true) as $brand => $n): ?>
        <a href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>">
          <?= h($brand) ?><i><?= te('results_n', ['n' => (int)$n]) ?></i>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------- yeni gelenler -->
<?php if ($fresh): ?>
<section class="sec">
  <div class="wrap">
    <?php vr_section_head(t('sec_new'), '', vr_url('shop.php', ['sort' => 'new'])); ?>
    <?php vr_grid($fresh, ['class' => 'grid--4']); ?>
  </div>
</section>
<?php endif; ?>

<!-- ---------------------------------------------------------------- üyelik -->
<section class="sec sec--tight" id="member">
  <div class="wrap">
    <div class="member" data-reveal>
      <h2><?= te('news_title') ?></h2>
      <p><?= te('news_sub', ['hours' => (int)vr_config('vault_member_head_start_hours', 12)]) ?></p>

      <form method="post" action="<?= h(vr_url('newsletter.php')) ?>">
        <?= vr_csrf_field() ?>
        <label class="vh" for="news-mail"><?= te('news_email') ?></label>
        <input id="news-mail" type="email" name="email" required placeholder="<?= te('news_email') ?>" autocomplete="email">
        <button class="btn btn--brass" type="submit"><span><?= te('news_cta') ?></span></button>

        <label class="consent">
          <input type="checkbox" name="consent" value="1" required>
          <span><?= te('news_consent') ?></span>
        </label>
      </form>
    </div>
  </div>
</section>

<?php vr_layout_end(); ?>
