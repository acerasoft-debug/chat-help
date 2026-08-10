<?php
/**
 * Hilfe & FAQ
 * -----------
 * Gruplu, <details> ile açılıp kapanan liste — JavaScript'e ihtiyaç yok.
 * FAQPage JSON-LD'si de basılıyor; Google zengin sonuçta gösterebilsin.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/faq-content.php';

$groups = vr_faq_groups();

// JSON-LD: tüm soru/cevaplar tek FAQPage altında.
$qa = [];
foreach ($groups as $items) {
    foreach ($items as [$q, $a]) {
        $qa[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
        ];
    }
}

$email = (string)(vr_config('company')['email'] ?? '');

vr_layout_start([
    'title'  => t('faq_title'),
    'desc'   => t('faq_sub'),
    'jsonld' => [
        ['@type' => 'FAQPage', 'mainEntity' => $qa],
        vr_jsonld_breadcrumbs([(string)vr_config('brand') => vr_url('/'), t('faq_title') => null]),
    ],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <div class="faq">
      <?php /* Kırıntı yolu ortalanmış sütunun İÇİNDE — dışarıdayken sayfanın
         sol kenarına (51 px) yapışıyor, başlık ise 223'ten başlıyordu. */ ?>
      <?php vr_breadcrumbs([t('faq_title') => null]); ?>
      <h1 class="sechead__t"><?= te('faq_title') ?></h1>
      <p class="sechead__s" style="margin:12px 0 8px"><?= te('faq_sub') ?></p>

      <!-- hızlı geçiş -->
      <nav class="doc__toc" style="margin:26px 0 34px">
        <?php foreach (array_keys($groups) as $key): ?>
          <a href="#<?= h($key) ?>"><?= te($key) ?></a>
        <?php endforeach; ?>
      </nav>

      <?php foreach ($groups as $key => $items): ?>
        <section class="faq__grp" id="<?= h($key) ?>">
          <h2><?= te($key) ?></h2>
          <?php foreach ($items as [$q, $a]): ?>
            <details class="qa">
              <summary><?= h($q) ?></summary>
              <div class="qa__a"><p><?= h($a) ?></p></div>
            </details>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <div class="doc__box">
        <strong><?= te('faq_contact_t') ?></strong>
        <p style="margin-top:8px">
          <?= te('faq_contact_b', ['email' => $email !== '' ? $email : '—']) ?>
        </p>
        <p style="margin-top:12px;font-size:13px;color:var(--muted)">
          <a class="link" href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/agb.php')) ?>"><?= te('legal_terms') ?></a> ·
          <a class="link" href="<?= h(vr_url('account/login.php')) ?>"><?= te('acc_title') ?></a> ·
          <a class="link" href="<?= h(vr_url('size-guide.php')) ?>"><?= te('size_guide') ?></a> ·
          <a class="link" href="<?= h(vr_url('about.php')) ?>"><?= te('about_title') ?></a>
        </p>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
