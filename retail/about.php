<?php
/**
 * Über uns
 * --------
 * Altlıkta "Unternehmen" sütunu vardı ama arkasında sayfa yoktu. Bu o sayfa.
 *
 * Metin sözlükte duruyor (about_*), yani on dilin hepsinde eksiksiz — hukuki
 * sayfaların aksine burada Almanca'ya bağlamak için bir sebep yok, bu bağlayıcı
 * bir metin değil.
 *
 * "In Zahlen" şeridi CANLI veriden hesaplanıyor: ürün, marka, kategori sayısı
 * katalogdan, dil sayısı yapılandırmadan, iade süresi yine yapılandırmadan.
 * Elle yazılmış bir sayı bir hafta sonra yalan olurdu.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

$co    = vr_config('company');
$name  = trim((string)($co['legal_name'] ?? '')) ?: (string)vr_config('brand');
$email = trim((string)($co['email'] ?? ''));

$facets = vr_facets();
$facts  = [
    [number_format(count(vr_catalog()), 0, ',', '.'),        t('about_f_items')],
    [number_format(count($facets['brands']), 0, ',', '.'),   t('about_f_brands')],
    [(string)count((array)vr_config('languages', ['en'])),   t('about_f_langs')],
    [(string)(int)vr_config('return_days', 30),              t('about_f_returns')],
];

vr_layout_start([
    'title'  => t('about_title'),
    'desc'   => t('about_sub'),
    'jsonld' => [
        [
            '@type'  => 'AboutPage',
            'name'   => t('about_title'),
            'about'  => [
                '@type' => 'Organization',
                'name'  => (string)vr_config('brand'),
                'legalName' => $name,
                'url'   => vr_origin() . vr_base_url() . '/',
            ],
        ],
        vr_jsonld_breadcrumbs([(string)vr_config('brand') => vr_url('/'), t('about_title') => null]),
    ],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('about_title') => null]); ?>

    <div class="doc">
      <h1><?= te('about_title') ?></h1>
      <p class="pdp__lead" style="margin:14px 0 4px"><?= te('about_sub') ?></p>

      <?php
      /* Bir moda evinin "hakkımızda" sayfası salt metin olamaz. Dört kadraj,
         vitrinlik fotoğraflar arasından ve dördü dört ayrı evden — sayfa
         mağazanın parçası gibi dursun, kurumsal bir bildiri gibi değil. */
      require_once __DIR__ . '/inc/catalog.php';
      $strip = [];
      $seenHouse = [];
      foreach (vr_query(['per_page' => 40, 'in_stock' => true, 'exclude_vault' => true])['rows'] as $sp) {
          if (isset($seenHouse[$sp['brand']])) continue;
          $img = vr_product_image($sp);
          if (!str_starts_with($img, '/uploads/')) continue;
          $seenHouse[$sp['brand']] = true;
          $strip[] = [$img, $sp];
          if (count($strip) >= 8) break;
      }
      // Sayfanın altındaki "This week, curated" ilk dördü gösteriyor; şerit
      // beşinciden başlıyor ki aynı dört parça iki kez çıkmasın.
      $strip = count($strip) > 4 ? array_slice($strip, 4, 4) : array_slice($strip, 0, 4);
      if ($strip): ?>
        <div class="aboutstrip">
          <?php foreach ($strip as $i => [$img, $sp]): ?>
            <a href="<?= h(vr_product_url($sp)) ?>" aria-label="<?= h($sp['brand'] . ' ' . vr_card_name($sp)) ?>">
              <?php vr_frame($img, [
                  'w'      => 420,
                  'widths' => [300, 420, 600],
                  'sizes'  => '(max-width:640px) 46vw, 200px',
                  'eager'  => $i === 0,
              ]); ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <hr class="rule" style="margin:34px 0 30px">

      <?php for ($i = 1; $i <= 5; $i++): ?>
        <h2><?= te("about_s{$i}_t") ?></h2>
        <p><?= te("about_s{$i}_b", ['company' => $name]) ?></p>
      <?php endfor; ?>

      <h2><?= te('about_facts') ?></h2>
      <div class="tablewrap" tabindex="0"><table>
        <tbody>
        <?php foreach ($facts as [$n, $label]): ?>
          <tr><td style="width:30%"><strong><?= h($n) ?></strong></td><td><?= h($label) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>

      <div class="doc__box" style="margin-top:34px">
        <strong><?= te('about_cta_t') ?></strong>
        <p style="margin-top:8px">
          <?= te('about_cta_b', ['email' => $email !== '' ? $email : '—']) ?>
        </p>
        <p style="margin-top:12px;font-size:13px;color:var(--muted)">
          <a class="link" href="<?= h(vr_url('faq.php')) ?>"><?= te('faq_title') ?></a> ·
          <a class="link" href="<?= h(vr_url('contact.php')) ?>"><?= te('nav_contact') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/impressum.php')) ?>"><?= te('legal_imprint') ?></a> ·
          <a class="link" href="<?= h(vr_url('journal.php')) ?>"><?= te('nav_journal') ?></a> ·
          <a class="link" href="<?= h(vr_url('sell.php')) ?>"><?= te('nav_sell') ?></a>
        </p>
      </div>
    </div>
  </div>
</section>

<?php /* Sayfa bir bağlantı listesiyle değil, satılan şeyle bitsin. */ ?>
<section class="sec sec--tight" style="padding-top:0">
  <div class="wrap"><?php vr_empty_suggestions(); ?></div>
</section>
<?php vr_layout_end(); ?>
