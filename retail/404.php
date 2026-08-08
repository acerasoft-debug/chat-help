<?php
/**
 * 404 — bulunamadı
 * ----------------
 * Tek parça satan bir vitrinde 404 normal bir olay: parça satılır, bağlantı
 * eskir. Bu yüzden sayfa özür dilemekle yetinmiyor — arama kutusu, adresteki
 * kelimelerden çıkarılmış öneriler ve canlı Vault losları sunuyor.
 *
 * Sunucu tarafında bağlanması: .htaccess içinde ErrorDocument 404.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

http_response_code(404);

// Adresten anlamlı kelimeleri çıkar: /product.php?id=blm-ah0eg000 → "blm ah0eg000"
$path = (string)($_SERVER['REQUEST_URI'] ?? '');
$hint = trim((string)($_GET['q'] ?? ''));
if ($hint === '') {
    $words = preg_split('/[^A-Za-z0-9]+/', urldecode($path)) ?: [];
    $words = array_filter($words, static function ($w) {
        if (in_array(strtolower($w), ['php', 'www', 'html', 'index', 'product', 'shop', 'retail', 'outlet', 'lot', 'id'], true)) {
            return false;
        }
        // Salt rakam ATILIYOR. Adresteki "404" model kodlarının içinde
        // rastlantıyla geçiyor (BWB640410, BWB64041001 …) ve 404 sayfası
        // "bunu mu aradınız?" başlığıyla üç mayo öneriyordu. Bir kelime ya
        // en az üç HARF içermeli ya da harf+rakam karışımı bir model kodu
        // uzunluğunda olmalı.
        $letters = preg_match_all('/[A-Za-z]/', $w);
        return $letters >= 3 || (mb_strlen($w) >= 6 && $letters > 0);
    });
    $hint = implode(' ', array_slice(array_values($words), 0, 3));
}

$guesses = $hint !== '' ? vr_query(['q' => $hint, 'per_page' => 4, 'in_stock' => true, 'exclude_vault' => true])['rows'] : [];
if (!$guesses) {
    // Tahmin tutmadıysa da boş sayfa göstermeyelim: haftanın seçkisi.
    $guesses = vr_query(['per_page' => 4, 'in_stock' => true, 'exclude_vault' => true])['rows'];
    $hint = '';
}

vr_layout_start([
    'title'  => t('nf_title'),
    'robots' => 'noindex,follow',
]);
?>
<section class="sec">
  <div class="wrap">
    <div class="nf">
      <div class="nf__code" aria-hidden="true">404</div>
      <h1><?= te('nf_title') ?></h1>
      <p><?= te('nf_body') ?></p>

      <form method="get" action="<?= h(vr_url('shop.php')) ?>" role="search">
        <label class="vh" for="nf-q"><?= te('search_submit') ?></label>
        <input id="nf-q" type="search" name="q" value="<?= h($hint) ?>"
               placeholder="<?= te('search_placeholder') ?>" autofocus>
        <button class="btn" type="submit"><span><?= te('search_submit') ?></span></button>
      </form>

      <p style="font-size:12.5px;color:var(--muted-2)">
        <a class="link" href="<?= h(vr_url('/')) ?>"><?= te('nav_shop') ?></a> ·
        <a class="link" href="<?= h(vr_url('outlet.php')) ?>"><?= te('nav_outlet') ?></a> ·
        <a class="link" href="<?= h(vr_url('brands.php')) ?>"><?= te('nav_brands') ?></a> ·
        <a class="link" href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> ·
        <a class="link" href="<?= h(vr_url('contact.php')) ?>"><?= te('nav_contact') ?></a>
      </p>
    </div>
  </div>
</section>

<?php if ($guesses): ?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_section_head($hint !== '' ? t('you_may_like') : t('sec_curated'), '', vr_url('shop.php')); ?>
    <?php vr_grid($guesses, ['class' => 'grid--4']); ?>
  </div>
</section>
<?php endif; ?>

<?php
$lots = vr_vault_lots(['limit' => 3]);
if ($lots): ?>
<section class="sec sec--tight vault">
  <div class="wrap">
    <?php vr_section_head(t('sec_vault'), t('sec_vault_sub'), vr_url('outlet.php')); ?>
    <div class="lots"><?php foreach ($lots as $v) vr_vault_card($v); ?></div>
  </div>
</section>
<?php endif; ?>

<?php vr_layout_end(); ?>
