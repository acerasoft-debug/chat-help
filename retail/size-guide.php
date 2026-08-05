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
    'tops'    => 'Oberteile · Tops',
    'bottoms' => 'Hosen · Bottoms',
    'shoes'   => 'Schuhe · Shoes',
    'belts'   => 'Gürtel · Belts',
];

// Tablolar: [etiket satırı, veri satırları]
$tables = [
    'tops' => [
        'head' => ['IT', 'DE / EU', 'FR', 'UK / US', 'Intl.', 'Brust (cm)', 'Taille (cm)'],
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
        'head' => ['IT', 'DE / EU', 'Zoll (Denim)', 'UK / US', 'Intl.', 'Taille (cm)', 'Hüfte (cm)'],
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
        'head' => ['EU', 'UK', 'US (Herren)', 'US (Damen)', 'Fußlänge (cm)'],
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
        'head' => ['Gürtelgröße', 'Taille (cm)', 'Passende Hosengröße IT', 'Intl.'],
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

      <div style="overflow-x:auto">
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
        <strong>Die Zahlen sind Körpermaße, nicht Kleidungsmaße.</strong>
        <p style="margin-top:8px">
          Messen Sie sich selbst und suchen Sie die Zeile, in die Ihre Maße fallen — nicht die Größe,
          die im letzten Kleidungsstück stand. Zwischen zwei Zeilen: bei körpernahen Schnitten
          (Hemden, Polos, Denim) die größere nehmen, bei weiten Schnitten (Hoodies, Mäntel) die kleinere.
        </p>
      </div>

      <h2>In zwei Minuten selbst messen</h2>
      <div class="measure">
        <div>
          <b>Brust</b>
          <p>Maßband waagerecht um die stärkste Stelle der Brust, unter den Achseln durch. Locker
             anliegend, nicht zusammenziehen. Arme entspannt hängen lassen.</p>
        </div>
        <div>
          <b>Taille</b>
          <p>Um die schmalste Stelle des Rumpfes, etwa auf Höhe des Bauchnabels. Normal atmen und
             den Bauch nicht einziehen — sonst sitzt später der Bund zu eng.</p>
        </div>
        <div>
          <b>Hüfte</b>
          <p>Um die breiteste Stelle des Gesäßes, Füße geschlossen. Für Jeans und Hosen der
             wichtigere der beiden Werte.</p>
        </div>
        <div>
          <b>Fußlänge</b>
          <p>Abends messen (Füße sind dann am größten): auf ein Blatt stellen, Ferse und längsten
             Zeh anzeichnen, Abstand messen. Beide Füße messen und den größeren Wert nehmen.</p>
        </div>
      </div>

      <h2>Was Sie über diese Häuser wissen sollten</h2>
      <table>
        <thead><tr><th>Haus</th><th>Passform</th><th>Empfehlung</th></tr></thead>
        <tbody>
          <tr><td>Balenciaga</td><td>oversized, betont weit geschnitten</td>
              <td>T-Shirts und Hoodies fallen ein bis zwei Größen größer aus — im Zweifel kleiner wählen</td></tr>
          <tr><td>Dsquared2</td><td>schmal, kurz geschnitten</td>
              <td>Denim und Shirts fallen klein aus — eine Größe größer nehmen</td></tr>
          <tr><td>Dolce&amp;Gabbana</td><td>italienisch tailliert</td>
              <td>entspricht der Tabelle; bei breiten Schultern eine Größe größer</td></tr>
          <tr><td>Balmain</td><td>schmale Schulter, taillierter Schnitt</td>
              <td>bei kräftigem Oberkörper eine Größe größer</td></tr>
          <tr><td>Burberry</td><td>klassisch, regular</td><td>entspricht der Tabelle</td></tr>
          <tr><td>Givenchy</td><td>gerade, leicht weit</td><td>entspricht der Tabelle</td></tr>
          <tr><td>Valentino</td><td>regular bis leicht weit</td><td>entspricht der Tabelle</td></tr>
          <tr><td>GCDS</td><td>Streetwear, weit</td><td>eine Größe kleiner, wenn Sie normal tragen möchten</td></tr>
        </tbody>
      </table>
      <p style="font-size:13px;color:var(--muted)">
        Die Angaben beruhen auf den Rückläufern und Messungen aus unserem eigenen Bestand. Sie sind
        Erfahrungswerte, keine Herstellerangaben — einzelne Modelle können abweichen. Steht in der
        Artikelbeschreibung ein konkretes Maß, hat dieses Vorrang.
      </p>

      <h2>Immer noch unsicher?</h2>
      <p>
        Schreiben Sie uns mit dem Modellcode und Ihren Maßen — wir messen das konkrete Stück nach und
        antworten mit den echten Zentimetern, bevor Sie bestellen.
      </p>
      <p>
        <a class="btn btn--ghost" href="<?= h(vr_url('contact.php')) ?>"><span><?= te('nav_contact') ?></span><?= vr_icon('arrow', 16) ?></a>
      </p>

      <p style="font-size:13px;color:var(--muted);margin-top:26px">
        Passt es trotzdem nicht? <?= te('order_next_3', ['days' => (int)vr_config('return_days', 30)]) ?>
        <a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>
      </p>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
