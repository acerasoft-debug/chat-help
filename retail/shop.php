<?php
/**
 * Vitrin / katalog
 * ----------------
 * Filtreler saf GET bağlantısı: JavaScript kapalıyken de çalışır, sonuç
 * paylaşılabilir ve arama motoru tarayabilir. Filtre kombinasyonları
 * indekslenmesin diye (ince içerik) filtreli sayfalarda robots=noindex.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

$q      = trim((string)($_GET['q'] ?? ''));
$brand  = trim((string)($_GET['brand'] ?? ''));
$cat    = trim((string)($_GET['cat'] ?? ''));
$seller = trim((string)($_GET['seller'] ?? ''));
$sort   = (string)($_GET['sort'] ?? 'featured');
$min    = (string)($_GET['min'] ?? '');
$max    = (string)($_GET['max'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

if (!in_array($sort, ['featured', 'new', 'price_asc', 'price_desc'], true)) $sort = 'featured';
if (!in_array($seller, ['', 'business', 'private', 'vestra'], true))        $seller = '';

$args = [
    'q' => $q, 'brand' => $brand, 'cat' => $cat, 'seller' => $seller,
    'sort' => $sort, 'min' => $min, 'max' => $max, 'page' => $page,
    'exclude_vault' => true,
];
$res    = vr_query($args);

/* Sayımlar SEÇİLİ FİLTREYE göre.
   Önceden her iki liste de tüm katalogdan sayılıyordu: T-Shirts kategorisinde
   219 parça varken kenar çubuğu "Dolce & Gabbana 139" yazıyordu — o 139, tüm
   kategorilerdeki D&G sayısı. Tıklayınca 139 değil 41 sonuç geliyor ve rakam
   yalan çıkıyor.

   Her liste kendi ekseni HARİÇ diğer filtrelerle sayılıyor: markalar seçili
   kategoriye göre, kategoriler seçili markaya göre. Böylece rakam tıklama
   sonucunun ta kendisi oluyor ve sıfır sonuçlu bir satır hiç görünmüyor. */
$base   = ['exclude_vault' => true, 'q' => $q, 'seller' => $seller, 'min' => $min, 'max' => $max];
$facets = [
    'brands' => vr_facets($base + ['cat' => $cat])['brands'],
    'cats'   => vr_facets($base + ['brand' => $brand])['cats'],
];

$filtered = ($q !== '' || $brand !== '' || $cat !== '' || $seller !== '' || $min !== '' || $max !== '');

/** Mevcut filtreleri koruyarak bağlantı üret. */
$link = static function (array $over) use ($q, $brand, $cat, $seller, $sort, $min, $max): string {
    $p = array_filter(array_merge([
        'q' => $q, 'brand' => $brand, 'cat' => $cat, 'seller' => $seller,
        'sort' => $sort === 'featured' ? '' : $sort, 'min' => $min, 'max' => $max,
    ], $over), fn($v) => $v !== '' && $v !== null);
    return vr_url('shop.php', $p);
};

$title = $q !== '' ? $q
       : ($brand !== '' ? $brand : ($cat !== '' ? vr_cat_label($cat) : t('shop_title')));

vr_layout_start([
    'title'  => $title,
    'desc'   => $brand !== ''
        ? $brand . ' — ' . t('shop_sub') . ' ' . t('tagline')
        : t('shop_sub') . ' ' . t('tagline'),
    'robots' => $filtered || $page > 1 ? 'noindex,follow' : null,
    'jsonld' => [vr_jsonld_breadcrumbs(array_filter([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_shop')              => $brand !== '' || $cat !== '' ? vr_url('shop.php') : null,
        $title                     => null,
    ], fn($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH))],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_demo_banner(); ?>
    <?php vr_breadcrumbs(array_filter([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_shop')              => ($brand !== '' || $cat !== '' || $q !== '') ? vr_url('shop.php') : null,
        $title                     => null,
    ])); ?>

    <?php
    /* KATEGORİ VE MARKA SAYFALARINA KİMLİK.
       Bir kategoriye girince ekranda yalnızca "T-Shirts" başlığı ve altında
       mağazanın genel sloganı vardı: 219 parçalık bir bölümün kapağı düz
       yazıydı. Buradaki şerit o bölümün kendi parçalarından üç kadraj
       gösteriyor — kapak fotoğrafı satın alınmıyor, koleksiyonun içinden
       çıkıyor. Yalnızca filtresiz ilk sayfada; arama sonucunda ya da fiyat
       filtresi varken kapak koymak yanıltıcı olurdu. */
    $hero = [];
    if (($cat !== '' || $brand !== '') && $q === '' && $page === 1 && $min === '' && $max === '') {
        $gridIx = vr_photo_grid_index();
        $seen   = [];
        /* Kapak kadrajları ilk satırdan SONRA başlıyor: aksi hâlde şeritteki
           üç fotoğraf, hemen altındaki ilk üç kartın aynısı oluyor ve sayfa
           kekeliyor. Yeterli ürün yoksa baştan alınıyor. */
        $pick = count($res['rows']) > 9 ? array_slice($res['rows'], 6) : $res['rows'];
        foreach ($pick as $hp) {
            if (count($hero) >= 3) break;
            if (isset($seen[$hp['brand'] . '|' . $hp['cat']])) continue;
            foreach ((array)$hp['images'] as $img) {
                $img = (string)$img;
                if ($img === '' || !str_starts_with($img, '/uploads/') || isset($gridIx[$img])) continue;
                $hero[] = $img;
                $seen[$hp['brand'] . '|' . $hp['cat']] = true;
                break;
            }
        }
    }
    ?>
    <?php if (count($hero) >= 2): ?>
      <div class="taxo">
        <div class="taxo__art" aria-hidden="true">
          <?php foreach ($hero as $i => $img): ?>
            <img src="<?= h(vr_img($img, 600)) ?>" srcset="<?= h(vr_img_srcset($img, [300, 600, 900])) ?>"
                 sizes="(max-width:640px) 48vw, 33vw"
                 alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async">
          <?php endforeach; ?>
        </div>
        <div class="taxo__in">
          <h1><?= h($title) ?></h1>
          <p><?= te('results_n', ['n' => (int)$res['total']]) ?> · <?= te('shop_sub') ?></p>
        </div>
      </div>
    <?php else: ?>
      <div class="sechead">
        <div>
          <h1 class="sechead__t"><?= h($title) ?></h1>
          <p class="sechead__s"><?= $q !== '' ? te('results_n', ['n' => (int)$res['total']]) : te('shop_sub') ?></p>
        </div>
      </div>
    <?php endif; ?>

    <div class="shop">
      <!-- ------------------------------------------------------- filtreler -->
      <?php
      /* Telefonda filtre paneli KAPALI açılıyor.
         Aksi hâlde mağaza sayfası on üç ev + on bir kategori + satıcı tipi +
         fiyat ile başlıyor: alıcı ilk ürünü görmek için iki ekran boyu filtre
         geçiyordu. Masaüstünde panel her zaman açık (aşağıdaki medya
         sorgusu summary'yi gizleyip gövdeyi görünür tutuyor), yani JS'siz
         çalışıyor ve klavye/ekran okuyucu için <details> zaten doğru öğe.
         Bir filtre seçiliyse panel açık geliyor — seçimini görmek isteyen
         kişi onu aramamalı. */
      ?>
      <?php
      /* Panel telefonda ne zaman AÇIK gelir?
         "Bir filtre var" ölçütü fazla genişti: ana sayfadan bir kategoriye
         tıklayan kişi de filtreli sayılıyor ve karşısında yine iki ekranlık
         liste buluyordu. Tek başına kategori ya da tek başına marka bir
         filtre değil, sayfanın kendisi. Panel ancak kullanıcı GERÇEKTEN
         daralttıysa açılıyor: arama, fiyat aralığı, satıcı tipi ya da marka
         ve kategorinin birlikte seçilmesi. */
      $narrowed = $q !== '' || $seller !== '' || $min !== '' || $max !== ''
               || ($brand !== '' && $cat !== '');
      ?>
      <details class="filters"<?= $narrowed ? ' open' : '' ?>>
        <summary class="filters__toggle">
          <span><?= te('filter_open') ?></span>
          <?= vr_icon('arrow', 15) ?>
        </summary>
        <div class="filters__body">
        <?php if ($filtered): ?>
          <a class="btn btn--ghost btn--sm" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('reset') ?></span></a>
        <?php endif; ?>

        <div>
          <h2><?= te('filter_brand') ?></h2>
          <a class="<?= $brand === '' ? 'is-on' : '' ?>" href="<?= h($link(['brand' => '', 'page' => ''])) ?>">
            <span><?= te('all') ?></span>
          </a>
          <?php foreach ($facets['brands'] as $b => $n): ?>
            <a class="<?= strcasecmp($b, $brand) === 0 ? 'is-on' : '' ?>" href="<?= h($link(['brand' => $b, 'page' => ''])) ?>">
              <span><?= h($b) ?></span><i><?= (int)$n ?></i>
            </a>
          <?php endforeach; ?>
        </div>

        <div>
          <h2><?= te('filter_cat') ?></h2>
          <a class="<?= $cat === '' ? 'is-on' : '' ?>" href="<?= h($link(['cat' => '', 'page' => ''])) ?>">
            <span><?= te('all') ?></span>
          </a>
          <?php foreach ($facets['cats'] as $c => $n): ?>
            <a class="<?= strcasecmp($c, $cat) === 0 ? 'is-on' : '' ?>" href="<?= h($link(['cat' => $c, 'page' => ''])) ?>">
              <span><?= h(vr_cat_label($c)) ?></span><i><?= (int)$n ?></i>
            </a>
          <?php endforeach; ?>
        </div>

        <div>
          <h2><?= te('filter_seller') ?></h2>
          <?php foreach (['' => t('all'), 'vestra' => t('seller_vestra'), 'business' => t('seller_business'), 'private' => t('seller_private')] as $k => $label): ?>
            <a class="<?= $seller === $k ? 'is-on' : '' ?>" href="<?= h($link(['seller' => $k, 'page' => ''])) ?>">
              <span><?= h($label) ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <form method="get" action="<?= h(vr_url('shop.php')) ?>">
          <h2><?= te('filter_price') ?></h2>
          <?php foreach (['q' => $q, 'brand' => $brand, 'cat' => $cat, 'seller' => $seller, 'sort' => $sort] as $k => $v): ?>
            <?php if ($v !== '' && $v !== 'featured'): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endif; ?>
          <?php endforeach; ?>
          <div class="filters__price">
            <input type="number" name="min" min="0" step="10" placeholder="0" value="<?= h($min) ?>" aria-label="min">
            <span>—</span>
            <input type="number" name="max" min="0" step="10" placeholder="9999" value="<?= h($max) ?>" aria-label="max">
          </div>
          <button class="btn btn--ghost btn--sm" style="margin-top:10px;width:100%" type="submit"><span><?= te('apply') ?></span></button>
        </form>
        </div>
      </details>

      <!-- ---------------------------------------------------------- sonuçlar -->
      <div>
        <div class="shopbar">
          <p class="shopbar__n">
            <?= (int)$res['total'] === 1 ? te('result_1') : te('results_n', ['n' => (int)$res['total']]) ?>
          </p>

          <form method="get" action="<?= h(vr_url('shop.php')) ?>">
            <?php foreach (['q' => $q, 'brand' => $brand, 'cat' => $cat, 'seller' => $seller, 'min' => $min, 'max' => $max] as $k => $v): ?>
              <?php if ($v !== ''): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <label class="vh" for="sort"><?= te('sort') ?></label>
            <select class="select" id="sort" name="sort" data-autosubmit>
              <?php foreach (['featured' => t('sort_featured'), 'new' => t('sort_new'), 'price_asc' => t('sort_price_asc'), 'price_desc' => t('sort_price_desc')] as $k => $label): ?>
                <option value="<?= h($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--ghost btn--sm" type="submit"><span><?= te('apply') ?></span></button></noscript>
          </form>
        </div>

        <?php if (!$res['rows']): ?>
          <div class="notice">
            <strong><?= te('no_results') ?></strong><br><?= te('no_results_hint') ?>
          </div>
          <a class="btn btn--ghost" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('reset') ?></span></a>
        <?php else: ?>
          <?php vr_grid($res['rows'], ['size_hint' => true, 'eager_first' => true]); ?>

          <?php if ((int)$res['pages'] > 1): ?>
            <nav class="pager" aria-label="<?= te('page_x_of_y', ['x' => (int)$res['page'], 'y' => (int)$res['pages']]) ?>">
              <?php if ((int)$res['page'] > 1): ?>
                <a href="<?= h($link(['page' => (int)$res['page'] - 1])) ?>" rel="prev"><?= te('prev') ?></a>
              <?php endif; ?>

              <?php
              // Sayfa numaralarını sınırlıyoruz: 200 sayfada 200 bağlantı basmak
              // ne kullanıcıya ne tarayıcıya iyi geliyor.
              $from = max(1, (int)$res['page'] - 2);
              $to   = min((int)$res['pages'], $from + 4);
              for ($i = $from; $i <= $to; $i++):
                  if ($i === (int)$res['page']): ?>
                    <span class="is-on" aria-current="page"><?= $i ?></span>
                  <?php else: ?>
                    <a href="<?= h($link(['page' => $i])) ?>"><?= $i ?></a>
                  <?php endif;
              endfor; ?>

              <?php if ((int)$res['page'] < (int)$res['pages']): ?>
                <a href="<?= h($link(['page' => (int)$res['page'] + 1])) ?>" rel="next"><?= te('next') ?></a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php vr_seen_strip(); ?>

<?php vr_layout_end(); ?>
