<?php
/**
 * Journal — hem liste hem tek yazı (?a=<slug>).
 * Görseller yine üretilmiş kompozisyon; her yazı kendi tohumunu kullanıyor,
 * yani başlığı değişmedikçe görseli de değişmiyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/journal-content.php';

$entries = vr_journal_entries();
$slug    = trim((string)($_GET['a'] ?? ''));
$one     = $slug !== '' ? ($entries[$slug] ?? null) : null;

if ($slug !== '' && $one === null) {
    http_response_code(404);
    vr_redirect('journal.php');
}

/**
 * Yazının görseli. Önce katalogdan gerçek bir kadraj denenir; katalog boşsa
 * (kurulumun ilk günü) üretilen kompozisyona düşülür — sayfa hiçbir durumda
 * görselsiz kalmasın.
 */
$art = static function (string $seed, int $w = 900, int $h = 560): string {
    $real = vr_journal_image($seed);
    if ($real !== null) return $real;
    return vr_url('assets/art.php', ['s' => 'journal-' . $seed, 'c' => 'editorial', 'w' => $w, 'h' => $h]);
};

// ---------------------------------------------------------------- tek yazı
if ($one !== null) {
    $words = 0;
    foreach ($one['body'] as $para) $words += str_word_count(strip_tags($para));

    vr_layout_start([
        'title'    => $one['title'],
        'desc'     => $one['lead'],
        'og_image' => $art($slug, 1200, 630),
        'jsonld'   => [
            [
                '@type'         => 'Article',
                'headline'      => $one['title'],
                'description'   => $one['lead'],
                'articleSection' => $one['kicker'],
                'inLanguage'    => vr_locale(),
                'wordCount'     => $words,
                'image'         => vr_origin() . $art($slug, 1200, 630),
                'publisher'     => ['@type' => 'Organization', 'name' => (string)vr_config('brand')],
            ],
            vr_jsonld_breadcrumbs([
                (string)vr_config('brand') => vr_url('/'),
                t('nav_journal')           => vr_url('journal.php'),
                $one['title']              => null,
            ]),
        ],
    ]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('nav_journal') => vr_url('journal.php'), $one['kicker'] => null]); ?>

    <article class="doc">
      <p class="jcard__kicker"><?= h($one['kicker']) ?></p>
      <h1 style="margin:10px 0 14px"><?= h($one['title']) ?></h1>
      <p style="font-family:var(--serif);font-size:clamp(17px,2vw,21px);color:var(--muted);margin-bottom:30px">
        <?= h($one['lead']) ?>
      </p>

      <img src="<?= h($art($slug, 1200, 700)) ?>" alt="" width="1200" height="700"
           style="width:100%;margin-bottom:32px" loading="eager">

      <?php foreach ($one['body'] as $para): ?>
        <p style="font-size:16px;line-height:1.75;margin-bottom:18px"><?= h($para) ?></p>
      <?php endforeach; ?>

      <hr class="rule" style="margin:34px 0 24px">

      <div class="grid grid--2" style="gap:14px">
        <a class="btn btn--ghost" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('nav_shop') ?></span></a>
        <a class="btn btn--ghost" href="<?= h(vr_url('outlet.php')) ?>"><span><?= te('nav_outlet') ?></span></a>
      </div>
    </article>
  </div>
</section>

<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_section_head(t('nav_journal'), '', vr_url('journal.php')); ?>
    <div class="jgrid">
      <?php
      $shown = 0;
      foreach ($entries as $key => $e):
          if ($key === $slug) continue;
          if (++$shown > 3) break; ?>
        <article class="jcard" data-reveal>
          <?php /* Kadraj bağlantısının erişilebilir adı yok: içinde yalnızca
                   dekoratif bir görsel var ve ekran okuyucu "bağlantı" deyip
                   susuyordu. Aynı yere giden başlık bağlantısı hemen altında,
                   yani bu ikinci bağlantı yardımcı teknoloji için gürültü —
                   ağaçtan çıkarıyoruz. Fare ve dokunma davranışı değişmiyor. */ ?>
          <a class="jcard__media" href="<?= h(vr_url('journal.php', ['a' => $key])) ?>"
             aria-hidden="true" tabindex="-1">
            <img src="<?= h($art($key, 700, 440)) ?>" alt="" width="700" height="440" loading="lazy">
          </a>
          <div class="jcard__body">
            <p class="jcard__kicker"><?= h($e['kicker']) ?></p>
            <?php /* h2: kart başlıkları sayfanın h1'inden sonraki ilk düzey.
                     h3 olduğunda ekran okuyucu bir düzey atlıyor (WCAG 1.3.1). */ ?>
            <h2 class="jcard__t"><a href="<?= h(vr_url('journal.php', ['a' => $key])) ?>"><?= h($e['title']) ?></a></h2>
            <p><?= h(mb_substr($e['lead'], 0, 120)) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
    vr_layout_end();
    exit;
}

// ------------------------------------------------------------------- liste
vr_layout_start([
    'title'  => t('nav_journal'),
    'desc'   => t('journal_sub'),
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_journal')           => null,
    ])],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('nav_journal') => null]); ?>

    <div class="sechead">
      <div>
        <h1 class="sechead__t"><?= te('nav_journal') ?></h1>
        <p class="sechead__s"><?= te('journal_sub') ?></p>
      </div>
    </div>

    <div class="jgrid">
      <?php foreach ($entries as $key => $e): ?>
        <article class="jcard" data-reveal>
          <?php /* Kadraj bağlantısının erişilebilir adı yok: içinde yalnızca
                   dekoratif bir görsel var ve ekran okuyucu "bağlantı" deyip
                   susuyordu. Aynı yere giden başlık bağlantısı hemen altında,
                   yani bu ikinci bağlantı yardımcı teknoloji için gürültü —
                   ağaçtan çıkarıyoruz. Fare ve dokunma davranışı değişmiyor. */ ?>
          <a class="jcard__media" href="<?= h(vr_url('journal.php', ['a' => $key])) ?>"
             aria-hidden="true" tabindex="-1">
            <img src="<?= h($art($key, 700, 440)) ?>" alt="" width="700" height="440" loading="lazy">
          </a>
          <div class="jcard__body">
            <p class="jcard__kicker"><?= h($e['kicker']) ?></p>
            <?php /* h2: kart başlıkları sayfanın h1'inden sonraki ilk düzey.
                     h3 olduğunda ekran okuyucu bir düzey atlıyor (WCAG 1.3.1). */ ?>
            <h2 class="jcard__t"><a href="<?= h(vr_url('journal.php', ['a' => $key])) ?>"><?= h($e['title']) ?></a></h2>
            <p><?= h($e['lead']) ?></p>
            <span class="jcard__more"><?= te('more') ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
