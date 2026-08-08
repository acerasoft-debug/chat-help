<?php
/**
 * Größenberatung
 * --------------
 * Neden ayrı ve ayrıntılı bir sayfa: bu katalogdaki markaların çoğu İTALYAN
 * beden sistemiyle etiketleniyor (46/48/50…), alıcıların çoğu DE/EU sistemini
 * biliyor. İade sebeplerinin en büyüğü de bu karışıklık. Tablolar burada
 * duruyor ve ürün sayfasındaki "Größenberatung" bağlantısı doğrudan ilgili
 * sekmeye açıyor (?cat=…).
 *
 * Ölçüler VÜCUT ölçüsüdür (giysi ölçüsü değil) — sayfada bu açıkça yazıyor,
 * çünkü ikisini karıştırmak bir beden hataya yol açıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

/** Ürün kategorisinden tabloyu seç. */
$catParam = trim((string)($_GET['cat'] ?? ''));
$k = mb_strtolower($catParam);
$tab = match (true) {
    (bool)preg_match('/jean|denim|trouser|pant|short|hose/u', $k) => 'bottoms',
    (bool)preg_match('/shoe|sneaker|schuh|boot/u', $k)            => 'shoes',
    (bool)preg_match('/belt|gürtel/u', $k)                        => 'belts',
    default                                                        => 'tops',
};
if (isset($_GET['tab']) && in_array((string)$_GET['tab'], ['tops', 'bottoms', 'shoes', 'belts'], true)) {
    $tab = (string)$_GET['tab'];
}

$tabs = [
    'tops'    => t('sg_tab_tops'),
    'bottoms' => t('sg_tab_bottoms'),
    'shoes'   => t('sg_tab_shoes'),
    'belts'   => t('sg_tab_belts'),
];

// Tablolar: [etiket satırı, veri satırları]
$tables = [
    'tops' => [
        'head' => ['IT', 'DE / EU', 'FR', 'UK / US', 'Intl.', t('sg_chest'), t('sg_waist')],
        'rows' => [
            ['44', '44', '38', '34', 'XS',  '86–90',   '72–76'],
            ['46', '46', '40', '36', 'S',   '90–94',   '76–80'],
            ['48', '48', '42', '38', 'M',   '94–98',   '80–84'],
            ['50', '50', '44', '40', 'L',   '98–102',  '84–88'],
            ['52', '52', '46', '42', 'XL',  '102–107', '88–94'],
            ['54', '54', '48', '44', 'XXL', '107–112', '94–100'],
            ['56', '56', '50', '46', '3XL', '112–118', '100–106'],
        ],
    ],
    'bottoms' => [
        'head' => ['IT', 'DE / EU', t('sg_inch'), 'UK / US', 'Intl.', t('sg_waist'), t('sg_hip')],
        'rows' => [
            ['44', '44', '28', '28', 'XS',  '72–76',   '88–92'],
            ['46', '46', '30', '30', 'S',   '76–80',   '92–96'],
            ['48', '48', '31', '31', 'M',   '80–84',   '96–100'],
            ['50', '50', '32', '32', 'L',   '84–88',   '100–104'],
            ['52', '52', '34', '34', 'XL',  '88–94',   '104–109'],
            ['54', '54', '36', '36', 'XXL', '94–100',  '109–114'],
            ['56', '56', '38', '38', '3XL', '100–106', '114–119'],
        ],
    ],
    'shoes' => [
        'head' => ['EU', 'UK', t('sg_us_m'), t('sg_us_w'), t('sg_footlen')],
        'rows' => [
            ['39', '5.5',  '6',    '8',    '24,8'],
            ['40', '6.5',  '7',    '9',    '25,4'],
            ['41', '7',    '7.5',  '9.5',  '26,0'],
            ['42', '8',    '8.5',  '10.5', '26,7'],
            ['43', '9',    '9.5',  '11.5', '27,3'],
            ['44', '9.5',  '10',   '12',   '28,0'],
            ['45', '10.5', '11',   '13',   '28,6'],
            ['46', '11.5', '12',   '14',   '29,2'],
        ],
    ],
    'belts' => [
        'head' => [t('sg_beltsize'), t('sg_waist'), t('sg_trouser_it'), 'Intl.'],
        'rows' => [
            ['80',  '72–78',   '44–46', 'XS'],
            ['85',  '78–83',   '46–48', 'S'],
            ['90',  '83–88',   '48–50', 'M'],
            ['95',  '88–93',   '50–52', 'L'],
            ['100', '93–98',   '52–54', 'XL'],
            ['105', '98–104',  '54–56', 'XXL'],
        ],
    ],
];

$t = $tables[$tab];

vr_layout_start([
    'title' => t('size_guide'),
    'desc'  => t('size_guide_sub'),
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('size_guide')            => null,
    ])],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('footer_service') => null, t('size_guide') => null]); ?>

    <div class="doc" style="max-width:none">
      <h1><?= te('size_guide') ?></h1>
      <p class="doc__meta"><?= te('size_guide_sub') ?></p>

      <nav class="sizetabs" aria-label="<?= te('size_guide') ?>">
        <?php foreach ($tabs as $key => $label): ?>
          <a class="<?= $tab === $key ? 'is-on' : '' ?>"
             href="<?= h(vr_url('size-guide.php', ['tab' => $key])) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="tablewrap" tabindex="0">
        <table>
          <thead><tr><?php foreach ($t['head'] as $th): ?><th><?= h($th) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($t['rows'] as $row): ?>
            <tr><?php foreach ($row as $i => $td): ?>
              <td<?= $i === 0 ? ' style="font-weight:600"' : '' ?>><?= h($td) ?></td>
            <?php endforeach; ?></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="doc__box">
        <strong><?= te('sg_body_t') ?></strong>
        <p style="margin-top:8px"><?= te('sg_body_b') ?></p>
      </div>

      <h2><?= te('sg_measure_t') ?></h2>
      <div class="measure">
        <?php foreach ([['sg_m_chest','sg_m_chest_b'],['sg_m_waist','sg_m_waist_b'],
                        ['sg_m_hip','sg_m_hip_b'],['sg_m_foot','sg_m_foot_b']] as [$lab, $body]): ?>
          <div>
            <b><?= te($lab) ?></b>
            <p><?= te($body) ?></p>
          </div>
        <?php endforeach; ?>
      </div>

      <?php
      /* Ev tablosu artık BURADA yazmıyor: kaynak inc/copy.php içindeki
         vr_house_notes(). İki yerde iki farklı kalıp notu, bedenini ona göre
         seçen müşteriyi yanıltıyor — ürün sayfası ile bu sayfa aynı cümleyi
         göstermeli. Yalnızca katalogda gerçekten bulunan evler listeleniyor. */
      require_once __DIR__ . '/inc/copy.php';
      $lang    = vr_lang() === 'de' ? 'de' : 'en';
      $inStock = array_change_key_case(vr_facets()['brands'], CASE_UPPER);
      $notes   = [];
      foreach (vr_house_notes() as $house => $n) {
          if (!isset($inStock[strtoupper($house)])) continue;
          $fit = trim((string)($n[$lang][1] ?? $n['en'][1] ?? ''));
          if ($fit !== '') $notes[$house] = $fit;
      }
      ksort($notes, SORT_NATURAL | SORT_FLAG_CASE);
      if ($notes):
      ?>
      <h2><?= te('sg_houses_t') ?></h2>
      <div class="tablewrap" tabindex="0">
      <table>
        <thead><tr><th><?= te('sg_th_house') ?></th><th><?= te('sg_th_fit') ?></th></tr></thead>
        <tbody>
          <?php foreach ($notes as $house => $fit): ?>
            <tr><td style="font-weight:600"><?= h($house) ?></td><td><?= h($fit) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p style="font-size:13px;color:var(--muted)"><?= te('sg_houses_note') ?></p>
      <?php endif; ?>

      <h2><?= te('sg_unsure_t') ?></h2>
      <p><?= te('sg_unsure_b') ?></p>
      <p>
        <a class="btn btn--ghost" href="<?= h(vr_url('contact.php')) ?>"><span><?= te('nav_contact') ?></span><?= vr_icon('arrow', 16) ?></a>
      </p>

      <p style="font-size:13px;color:var(--muted);margin-top:26px">
        <?= te('sg_still') ?> <?= te('order_next_3', ['days' => (int)vr_config('return_days', 30)]) ?>
        <a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>
      </p>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
