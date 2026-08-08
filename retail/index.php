<?php
/**
 * Anasayfa
 * --------
 * Perakende anasayfası kutu içine kutu dizer; moda evinin anasayfası sayfayı
 * bir dergi gibi kurar. Buradaki akış o yüzden bölümlerden değil "sayfalardan"
 * oluşuyor:
 *
 *   sahne (kenardan kenara, filmli)  →  I. kuratöryel ray  →  manifesto bandı
 *   →  II. Vault odası  →  III. evler  →  ilkeler  →  yeni gelenler  →  üyelik
 *
 * Koyu/açık geçişleri "ayrı odalar" hissi veriyor; Premium Outlet böylece
 * sitenin içinde ayrı bir mekân gibi duruyor, sıradan bir "indirim" sekmesi
 * gibi değil. Rakamlı bölüm başlıkları ve kadrajdan taşan yatay ray da aynı
 * amaca hizmet ediyor: ızgara değil, sayfa düzeni.
 *
 * Hiçbir bölüm JavaScript'e bağlı değil. Sahnedeki film CSS animasyonu; asıl
 * video ancak operatör assets/media/hero.mp4 koyduğunda devreye giriyor ve
 * oynatmayı JS başlatıyor — JS kapalıysa hareket hiç başlamıyor (WCAG 2.2.2).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

// Vitrin ürünle açılıyor. Önceki hâlde ana sayfanın ilk ekranı sahne, ikinci
// ekranı yatay bir raydı; ziyaretçi ürün görmeden iki ekran kaydırıyordu.
// Şimdi sahnenin hemen altında 12'lik bir ızgara var.
$fresh   = vr_query(['per_page' => 8, 'sort' => 'new', 'in_stock' => true, 'exclude_vault' => true])['rows'];

/**
 * Vitrin seçkisi: marka başına bir parça, sırayla.
 *
 * Düz "ilk 12" kataloğun kendi sırasını alıyordu ve o sıra markaya göre:
 * ana sayfa on iki Balmain mayosuyla açılıyordu. Sıralı dağıtım (her
 * markadan bir tane, sonra ikinci tur) hem markaları hem kategorileri
 * ilk ekrana getiriyor.
 *
 * Fotoğrafı ızgara kontakt sayfası olanlar geriye atılıyor: vitrinde
 * tek çekim duruyor.
 */
$pool = vr_query(['per_page' => 400, 'in_stock' => true, 'exclude_vault' => true])['rows'];
$grid = vr_photo_grid_index();
$byBrand = [];
foreach ($pool as $p) {
    $first = (string)($p['images'][0] ?? '');
    $rank  = ($first !== '' && !isset($grid[$first])) ? 0 : 1;
    $byBrand[$p['brand']][$rank][] = $p;
}
$curated = [];
for ($round = 0; $round < 4 && count($curated) < 12; $round++) {
    foreach ($byBrand as $brand => $ranks) {
        if (count($curated) >= 12) break;
        $take = array_shift($ranks[0]) ?? array_shift($ranks[1]) ?? null;
        if ($take === null) continue;
        $byBrand[$brand] = $ranks;
        $curated[] = $take;
    }
}
$lots    = vr_vault_lots(['limit' => 6]);
$facets  = vr_facets();
$total   = vr_query(['per_page' => 1])['total'];
$nextDrop = vr_vault_next_drop();
$days    = (int)vr_config('return_days', 30);

// Sahne kareleri: haftada bir değişen tohum — katalog aynı kalsa da anasayfa
// her hafta başka bir kampanya kadrajıyla açılıyor.
$stageSeed  = 'maxsales-stage-' . gmdate('YW');
$stageFrames = [];
for ($i = 0; $i < 3; $i++) {
    $stageFrames[] = vr_url('assets/art.php', [
        's' => $stageSeed . '-' . $i, 'm' => 'campaign', 'w' => 1600, 'h' => 900,
    ]);
}
$stageMedia = vr_hero_media();

// Editoryal karolar: gerçek fotoğrafı olan ürünler öncelikli.
$tileSkip = [];
$tileA = vr_editorial_image($lots ? array_column($lots, 'product') : [], $stageSeed . '-a', $tileSkip);
$tileB = vr_editorial_image($curated, $stageSeed . '-b', $tileSkip);

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

<!-- ----------------------------------------------------------------- sahne -->
<section class="stage">
  <?php /* Duraklatma kutusu EN ÖNDE: kardeş seçicilerle hem filmi hem düğme
           yazısını çeviriyor, :has() gerektirmiyor. Görsel olarak gizli. */ ?>
  <input type="checkbox" id="stage-pause" class="stage__pausebox vh">

  <div class="stage__art">
    <?php if ($stageMedia && $stageMedia['kind'] === 'video'): ?>
      <?php /* autoplay ÖZELLİKLE yok: oynatmayı JS başlatıyor, böylece JS
               kapalıyken hareket hiç doğmuyor ve duraklatma düğmesinin
               çalışmadığı bir durum kalmıyor. */ ?>
      <video class="stage__vid" muted loop playsinline preload="none"
             poster="<?= h($stageFrames[0]) ?>" aria-hidden="true" tabindex="-1"
             data-autoplay>
        <source src="<?= h($stageMedia['src']) ?>" type="<?= h($stageMedia['type']) ?>">
      </video>
    <?php elseif ($stageMedia): ?>
      <?php /* Gerçek kampanya fotoğrafı: hareket yok, duraklatılacak bir şey de yok. */ ?>
      <img class="stage__still" src="<?= h($stageMedia['src']) ?>" alt="" aria-hidden="true"
           fetchpriority="high" decoding="async">
    <?php else: ?>
      <div class="stage__film">
        <?php foreach ($stageFrames as $i => $src): ?>
          <img src="<?= h($src) ?>" alt="" aria-hidden="true"
               <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
               decoding="async" width="1600" height="900">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <label class="stage__pause" for="stage-pause">
    <span class="stage__pause-i" aria-hidden="true"></span>
    <span class="vh stage__pause-a"><?= te('stage_pause') ?></span>
    <span class="vh stage__pause-b"><?= te('stage_play') ?></span>
  </label>

  <span class="stage__frame" aria-hidden="true"></span>

  <div class="stage__in">
    <span class="orn" aria-hidden="true"><i></i></span>
    <p class="stage__eyebrow"><?= te('hero_eyebrow') ?></p>
    <h1><?= te('hero_title') ?></h1>
    <p class="stage__sub"><?= te('hero_sub') ?></p>

    <div class="stage__act">
      <a class="btn btn--brass btn--lg" href="<?= h(vr_url('outlet.php')) ?>">
        <span><?= te('hero_cta') ?></span><?= vr_icon('arrow', 16) ?>
      </a>
      <a class="stage__link" href="<?= h(vr_url('shop.php', ['sort' => 'new'])) ?>">
        <span><?= te('hero_cta2') ?></span><?= vr_icon('arrow', 15) ?>
      </a>
    </div>
  </div>

  <div class="stage__meta">
    <div><b><?= (int)$total ?></b><span><?= te('hero_stat_lots') ?></span></div>
    <div><b><?= count($facets['brands']) ?></b><span><?= te('hero_stat_brands') ?></span></div>
    <?php if ($nextDrop): ?>
      <div><b><span data-countdown="<?= (int)$nextDrop ?>">—</span></b><span><?= te('hero_stat_drop') ?></span></div>
    <?php endif; ?>
  </div>
</section>

<div class="wrap"><?php vr_demo_banner(); ?></div>

<!-- ------------------------------------------------------- I. kuratöryel ray -->
<section class="sec">
  <div class="wrap">
    <?php vr_chapter('I', t('sec_curated'), t('sec_curated_sub'), vr_url('shop.php')); ?>
  </div>
  <?php /* Ray değil ızgara. Ray zarifti ama masaüstünde aynı anda dört kart
           gösteriyordu ve geri kalanı yatay kaydırmanın arkasına saklıyordu —
           ziyaretçinin ilk gördüğü şey bir avuç ürün oluyordu. Izgara on iki
           parçayı birden açıyor. */ ?>
  <div class="wrap">
    <?php vr_grid($curated, ['class' => 'grid--4', 'size_hint' => true, 'eager_first' => true]); ?>
    <p style="text-align:center;margin-top:clamp(28px,3.5vw,44px)">
      <a class="btn btn--ghost btn--lg" href="<?= h(vr_url('shop.php')) ?>">
        <span><?= te('sec_curated') ?> — <?= te('results_n', ['n' => (int)$total]) ?></span><?= vr_icon('arrow', 16) ?>
      </a>
    </p>
  </div>
</section>

<!-- ------------------------------------------------------- editoryal ikili
     İki tam boy kare, aralarında oluk yok, kenardan kenara. Perakende
     ızgarası burada susuyor ve sayfa bir kampanya sayfasına dönüyor.
     Görsel: elde gerçek ürün fotoğrafı varsa o, yoksa kampanya karesi. -->
<section class="editorial">
  <a class="etile" href="<?= h(vr_url('outlet.php')) ?>">
    <?php
    /* Karo da hareketli olabiliyor. assets/media/band-vault.webm varsa video,
       yoksa fotoğraf — ikisi de yoksa üretilmiş kampanya karesi. Oynatmayı
       yine JS başlatıyor: JS kapalıysa poster duruyor ve hareket hiç doğmuyor
       (WCAG 2.2.2, sahnedekiyle aynı kural). */
    $bandA = vr_hero_media('band-vault');
    ?>
    <?php if ($bandA && $bandA['kind'] === 'video'): ?>
      <video class="etile__vid" muted loop playsinline preload="none" data-autoplay
             poster="<?= h($tileA) ?>" aria-hidden="true" tabindex="-1">
        <source src="<?= h($bandA['src']) ?>" type="<?= h($bandA['type']) ?>">
      </video>
    <?php else: ?>
      <img src="<?= h($tileA) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="etile__in">
      <span class="orn" aria-hidden="true"><i></i></span>
      <h2><?= te('vault_title') ?></h2>
      <p><?= te('sec_vault_sub') ?></p>
      <span class="etile__go"><?= te('nav_outlet') ?><?= vr_icon('arrow', 15) ?></span>
    </div>
  </a>
  <?php
  /* İkinci karo eskiden "Verkaufen" idi. Ana sayfanın yarısını satıcı
     çağrısına vermek alıcıyı ürüne değil kayıt formuna yolluyordu; o çağrı
     artık yalnızca altlıkta duruyor. Yerine en dolu kategori geçti. */
  $topCat = $facets['cats'] ?: [];
  arsort($topCat);
  $catName = (string)(array_key_first($topCat) ?? '');
  ?>
  <a class="etile" href="<?= h(vr_url('shop.php', $catName !== '' ? ['cat' => $catName] : [])) ?>">
    <?php $bandB = vr_hero_media('band-shop'); ?>
    <?php if ($bandB && $bandB['kind'] === 'video'): ?>
      <video class="etile__vid" muted loop playsinline preload="none" data-autoplay
             poster="<?= h($tileB) ?>" aria-hidden="true" tabindex="-1">
        <source src="<?= h($bandB['src']) ?>" type="<?= h($bandB['type']) ?>">
      </video>
    <?php else: ?>
      <img src="<?= h($tileB) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="etile__in">
      <span class="orn" aria-hidden="true"><i></i></span>
      <h2><?= h($catName !== '' ? $catName : t('nav_shop')) ?></h2>
      <p><?= te('sec_curated_sub') ?></p>
      <span class="etile__go">
        <?= $catName !== '' ? te('results_n', ['n' => (int)$topCat[$catName]]) : te('nav_shop') ?>
        <?= vr_icon('arrow', 15) ?>
      </span>
    </div>
  </a>
</section>

<!-- ------------------------------------------------------------- manifesto -->
<section class="manifesto">
  <div class="manifesto__in" data-reveal>
    <span class="orn" aria-hidden="true"><i></i></span>
    <p><?= te('home_manifesto') ?></p>
    <p class="manifesto__note"><?= te('home_manifesto_note') ?></p>
  </div>
</section>

<!-- --------------------------------------------------------- II. Vault odası -->
<section class="sec vault">
  <div class="wrap">
    <?php vr_chapter('II', t('sec_vault'), t('sec_vault_sub'), vr_url('outlet.php'), t('vault_title')); ?>
  </div>

  <?php if ($lots): ?>
    <div class="rail">
      <div class="rail__track"><?php foreach ($lots as $v) vr_vault_card($v); ?></div>
    </div>
  <?php else: ?>
    <div class="wrap"><p class="lede"><?= te('vault_empty') ?></p></div>
  <?php endif; ?>

  <div class="wrap">
    <hr class="rule" style="margin:clamp(38px,5vw,64px) 0;background:rgba(245,242,236,.16)">

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

<!-- ---------------------------------------------------------------- III. evler -->
<?php if ($facets['brands']): ?>
<section class="sec">
  <div class="wrap">
    <?php vr_chapter('III', t('sec_brands'), '', vr_url('brands.php')); ?>
    <?php
    // En çok ürünü olan sekiz ev — vitrinde derinliği olan isimler önce.
    $top = $facets['brands'];
    arsort($top);
    ?>
    <div class="houselist" data-reveal>
      <?php foreach (array_slice($top, 0, 8, true) as $brand => $n): ?>
        <a href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>">
          <span><?= h($brand) ?></span><i><?= te('results_n', ['n' => (int)$n]) ?></i>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- -------------------------------------------------------------- güven şeridi -->
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_chapter('IV', t('sec_editorial')); ?>
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

<!-- ------------------------------------------------------------- yeni gelenler -->
<?php if ($fresh): ?>
<section class="sec">
  <div class="wrap">
    <?php vr_chapter('V', t('sec_new'), '', vr_url('shop.php', ['sort' => 'new'])); ?>
    <?php vr_grid($fresh, ['class' => 'grid--4']); ?>
  </div>
</section>
<?php endif; ?>

<!-- ---------------------------------------------------------------- üyelik -->
<section class="sec sec--tight" id="member">
  <div class="wrap">
    <div class="member" data-reveal>
      <span class="orn" aria-hidden="true"><i></i></span>
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
