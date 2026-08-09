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
/* "Yeni gelenler" sayfanın EN ALTINDA basılıyor ama sorgusu en üstte;
   defterin dolması için havuzu geniş alıp süzmeyi basma anına bırakıyoruz.
   Bkz. aşağıdaki $taken defteri. */
$freshPool = vr_query(['per_page' => 24, 'sort' => 'new', 'in_stock' => true, 'exclude_vault' => true])['rows'];

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

/* AYNI ÜRÜN SAYFADA İKİ KEZ ÇIKMASIN.
   Ölçüldü: ana sayfada 72 ürün bağlantısı, 32 farklı ürün — dördü iki ayrı
   bölümde birden görünüyordu. Seçki, kategori blokları ve "yeni gelenler"
   aynı havuzdan besleniyor ve birbirinden habersizdi. Ziyaretçi aynı
   sweatshirt'ü üç kez görünce katalog küçük sanıyor.

   Tek bir defter tutuyoruz: bir ürün basıldığı anda buraya yazılıyor,
   sonraki bölümler onu atlıyor. Sıra önemli — önce seçki, sonra kategori
   blokları, en sonda yeni gelenler; en iyi parçalar en üstte kalıyor. */
$taken = [];
$mark   = function (array $rows) use (&$taken): array {
    foreach ($rows as $r) $taken[(string)$r['id']] = true;
    return $rows;
};
/* DİKKAT: ok fonksiyonu (fn) dış değişkeni DEĞERE göre yakalıyor; öyle
   yazıldığında $taken hep tanımlandığı andaki boş hâliyle görülüyor ve
   süzme hiç çalışmıyordu — ölçtük, tekrar sayısı hiç değişmedi. Referansla
   yakalayan klasik closure gerekiyor. */
$freeOf = function (array $p) use (&$taken): bool {
    return !isset($taken[(string)$p['id']]);
};
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
$mark($curated);
$lots    = vr_vault_lots(['limit' => 6]);
// Sayimlar magaza sayfasiyla AYNI temelde: orada Vault losu listelenmiyor.
// Ayni temeli kullanmazsak karoda "50 parca" yaziyor, tiklayinca 45 geliyor.
$facets  = vr_facets(['exclude_vault' => true]);
$total   = vr_query(['per_page' => 1, 'exclude_vault' => true])['total'];
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
      <?php /* Afiş filmin kendi ilk karesi; yoksa üretilen kampanya karesi.
               Film oynamaya başlayana kadar (ve JS kapalıysa hep) ekranda
               duran şey bu — gerçek ürünler olmalı. */ ?>
      <video class="stage__vid" muted loop playsinline preload="none"
             poster="<?= h($stageMedia['poster'] ?? $stageFrames[0]) ?>" aria-hidden="true" tabindex="-1"
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
             poster="<?= h(vr_img($bandA['poster'] ?? $tileA, 900)) ?>" aria-hidden="true" tabindex="-1">
        <source src="<?= h($bandA['src']) ?>" type="<?= h($bandA['type']) ?>">
      </video>
    <?php else: ?>
      <img src="<?= h(vr_img($tileA, 900)) ?>" srcset="<?= h(vr_img_srcset($tileA, [420, 600, 900])) ?>"
             sizes="(max-width:900px) 96vw, 48vw" alt="" aria-hidden="true" loading="lazy" decoding="async">
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
             poster="<?= h(vr_img($bandB['poster'] ?? $tileB, 900)) ?>" aria-hidden="true" tabindex="-1">
        <source src="<?= h($bandB['src']) ?>" type="<?= h($bandB['type']) ?>">
      </video>
    <?php else: ?>
      <img src="<?= h(vr_img($tileB, 900)) ?>" srcset="<?= h(vr_img_srcset($tileB, [420, 600, 900])) ?>"
             sizes="(max-width:900px) 96vw, 48vw" alt="" aria-hidden="true" loading="lazy" decoding="async">
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
    <?php
    /* Vault boşken burası yedi yüz piksel "şu anda los yok" yazısıydı.
       Bir vitrinde boş oda olmaz: onun yerine kataloğun en pahalı parçaları
       geçiyor. Hem oda dolu duruyor hem de ziyaretçi Vault'un ne tür bir
       malı düşürdüğünü görüyor. Loslar açılır açılmaz bu blok kendiliğinden
       kayboluyor. */
    /* Vault boşken gösterilen teaser da deftere uyuyor: aynı parça hem
       seçkide hem burada çıkarsa bölüm "Vault'ta ne var" sorusunu değil
       "bu sweatshirt'ü kaç kez göreceğim" sorusunu doğuruyor. */
    $teaser = [];
    foreach (vr_query(['per_page' => 24, 'sort' => 'price_desc', 'in_stock' => true, 'exclude_vault' => true])['rows'] as $tp) {
        if (count($teaser) >= 6) break;
        if (!$freeOf($tp)) continue;
        $teaser[] = $tp;
    }
    $mark($teaser);
    ?>
    <div class="wrap"><p class="lede" style="margin-bottom:clamp(24px,3vw,38px)"><?= te('vault_empty') ?></p></div>
    <?php if ($teaser): ?>
      <div class="rail">
        <div class="rail__track"><?php foreach ($teaser as $p) vr_card($p, ['size_hint' => true]); ?></div>
      </div>
    <?php endif; ?>
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
    <?php
    /* Eskiden burası bin iki yüz piksellik düz bir metin listesiydi: sekiz
       marka adı ve yanlarında sayı. Bir moda evinin vitrininde marka, adıyla
       değil malıyla tanıtılır — her karo artık o markanın gerçek bir
       parçasını gösteriyor.
       Görsel seçimi: ızgara kontakt sayfası olmayan ilk fotoğraf. */
    $gridIx = vr_photo_grid_index();
    $shot = [];
    foreach ($pool as $p) {
        $b = $p['brand'];
        if (isset($shot[$b])) continue;
        foreach ((array)$p['images'] as $img) {
            if ($img !== '' && !isset($gridIx[$img])) { $shot[$b] = $img; break; }
        }
    }
    ?>
    <div class="houses" data-reveal>
      <?php foreach (array_slice($top, 0, 8, true) as $brand => $n): ?>
        <a class="house" href="<?= h(vr_url('shop.php', ['brand' => $brand])) ?>">
          <span class="house__art">
            <?php if (!empty($shot[$brand])) vr_frame($shot[$brand], [
                'box'    => 0.75,      // .house__art 3/4
                'no_pad' => true,      // perde altında bulanık zemin işe yaramaz
                'w'      => 420,
                'widths' => [180, 300, 420],
                'sizes'  => '(max-width:640px) 46vw, 240px',
            ]); ?>
          </span>
          <span class="house__in">
            <b><?= h($brand) ?></b>
            <i><?= te('results_n', ['n' => (int)$n]) ?></i>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------ kategoriler
     İki katman. Üstte kategori karoları — ad fotoğrafın ÜZERİNDE, altında
     değil: küçük bir etiket satırı kadraja eşlik eden bir yazı gibi durmuyor,
     ızgaraya iliştirilmiş bir dipnot gibi duruyordu.

     Altta ise asıl istenen şey: her kategorinin İÇİNDE ürünler. Kategoriye
     tıklamadan da ne satıldığı görünüyor, ve iki bölüm arasındaki ölü boşluk
     ürünle doluyor. -->
<?php
/* Kategori başına bir kapak fotoğrafı — HER KAREDE BAŞKA BİR EV.
   İlk denemede beş karonun beşi de Balmain'di: havuz markaya göre sıralı
   olduğu için her kategorinin ilk ürünü aynı evden çıkıyordu. */
$cats = $facets['cats'];
arsort($cats);
$catShot = [];
$usedBrand = [];
foreach ([true, false] as $freshBrand) {
    foreach ($cats as $c => $_) {
        if (isset($catShot[$c])) continue;
        foreach ($pool as $p) {
            if ($p['cat'] !== $c) continue;
            if ($freshBrand && isset($usedBrand[$p['brand']])) continue;
            foreach ((array)$p['images'] as $img) {
                if ($img !== '' && !isset($gridIx[$img])) {
                    $catShot[$c] = $img;
                    $usedBrand[$p['brand']] = true;
                    break 2;
                }
            }
        }
    }
}

/* Her kategorinin kendi ürünleri. Kartlar marka çeşitliliğine göre seçiliyor:
   bir kategorinin dört karesi de aynı evden olursa bölüm katalog değil tek
   marka vitrini gibi duruyor. */
$catRows = [];
foreach (array_slice($cats, 0, 4, true) as $cat => $n) {
    $seenBrand = [];
    $rows = [];
    // Kapak karesi bloğun içinde TEKRAR ETMESİN: aynı fotoğraf üstte büyük,
    // hemen altında küçük görününce özensiz duruyor.
    $cover = (string)($catShot[$cat] ?? '');

    // Havuz ilk 400 satır; bir kategoride dört AYRI ev çıkmayabiliyor ve
    // eskiden ikinci tur aynı evden ikinci bir kareyi alıyordu — ekranda iki
    // mavi Lacoste yan yana. Artık ikinci tur yok; yetmezse o kategorinin
    // kendi sorgusundan devam ediyoruz, aynı ev kuralı hiç gevşemiyor.
    $sources = [$pool, null];
    foreach ($sources as $si => $src) {
        if (count($rows) >= 4) break;
        if ($src === null) {
            $src = vr_query([
                'cat' => $cat, 'in_stock' => true, 'exclude_vault' => true,
                'per_page' => 60, 'sort' => 'featured',
            ])['rows'];
        }
        foreach ($src as $p) {
            if (count($rows) >= 4) break;
            if ($p['cat'] !== $cat) continue;
            if (!$freeOf($p)) continue;                 // başka bölümde çıktı
            if (isset($seenBrand[$p['brand']])) continue;
            foreach ($rows as $r) if ($r['id'] === $p['id']) continue 2;
            $first = (string)($p['images'][0] ?? '');
            if ($first === '' || isset($gridIx[$first])) continue;
            if ($cover !== '' && $first === $cover) continue;
            $seenBrand[$p['brand']] = true;
            $rows[] = $p;
        }
    }
    if (count($rows) >= 4) $catRows[$cat] = $mark($rows);
}
?>
<?php if (count($cats) > 2): ?>
<section class="sec">
  <div class="wrap">
    <?php vr_chapter('IV', t('sec_cats'), t('sec_cats_sub'), vr_url('shop.php')); ?>
  </div>
  <div class="rail">
    <div class="rail__track cats">
      <?php foreach ($cats as $cat => $n): ?>
        <a class="cat" href="<?= h(vr_url('shop.php', ['cat' => $cat])) ?>">
          <span class="cat__art">
            <?php if (!empty($catShot[$cat])) vr_frame($catShot[$cat], [
                'box'    => 0.75,      // .cat__art 3/4
                'no_pad' => true,
                'w'      => 420,
                'widths' => [300, 420, 600],
                'sizes'  => '(max-width:640px) 62vw, 300px',
            ]); ?>
            <span class="cat__in">
              <b><?= h(vr_cat_label($cat)) ?></b>
              <i><?= te('results_n', ['n' => (int)$n]) ?></i>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php foreach ($catRows as $cat => $rows): ?>
    <div class="wrap catblock">
      <div class="catblock__head">
        <h3><?= h(vr_cat_label($cat)) ?></h3>
        <a class="sechead__more" href="<?= h(vr_url('shop.php', ['cat' => $cat])) ?>">
          <?= te('results_n', ['n' => (int)$cats[$cat]]) ?><?= vr_icon('arrow', 14) ?>
        </a>
      </div>
      <?php vr_grid($rows, ['class' => 'grid--4', 'size_hint' => true]); ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- -------------------------------------------------------------- güven şeridi -->
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_chapter('V', t('sec_editorial')); ?>
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
<?php
/* Defterde olmayan ilk sekiz yeni parça. Süzme burada, çünkü defter ancak
   seçki ve kategori blokları basıldıktan sonra tamamlanıyor. */
$fresh = [];
foreach ($freshPool as $fp) {
    if (count($fresh) >= 8) break;
    if (!$freeOf($fp)) continue;
    $fresh[] = $fp;
}
$mark($fresh);
?>
<?php if ($fresh): ?>
<section class="sec">
  <div class="wrap">
    <?php vr_chapter('VI', t('sec_new'), '', vr_url('shop.php', ['sort' => 'new'])); ?>
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
