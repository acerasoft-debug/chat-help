<?php
/**
 * Merkliste — hem eylem ucu hem sayfa.
 * POST → çerezi güncelle → geldiği sayfaya 303 (PRG).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/lists.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();

    $id  = trim((string)($_POST['id'] ?? ''));
    $now = false;
    if ($id !== '' && vr_product($id) !== null) {
        $now = vr_wish_toggle($id);
        vr_flash($now ? t('wish_added') : t('wish_removed'), 'ok');
    }

    // JS ile gönderildiyse sayfa yenilemeden cevap ver (aynı uç, iki biçim).
    if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch') {
        vr_flash();                                  // biriken mesajı tüket
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'saved' => $now,
            'count' => vr_wish_count(),
            'label' => $now ? t('wish_saved') : t('wish_add'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // "back" istemciden geliyor: yalnızca kendi sitemizdeki bir yola dönüyoruz,
    // açık yönlendirme (open redirect) bırakmıyoruz.
    $back = (string)($_POST['back'] ?? '');
    $path = parse_url($back, PHP_URL_PATH) ?: '';
    $qs   = parse_url($back, PHP_URL_QUERY);
    $base = vr_base_url();

    if ($path !== '' && str_starts_with($path, $base === '' ? '/' : $base . '/')) {
        header('Location: ' . $path . ($qs ? '?' . $qs : ''), true, 303);
        exit;
    }
    vr_redirect('wishlist.php');
}

$rows = vr_wish_products();

vr_layout_start([
    'title'  => t('wish_title'),
    'robots' => 'noindex,nofollow',
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_flash_render(); ?>
    <div class="sechead">
      <div>
        <h1 class="sechead__t"><?= te('wish_title') ?></h1>
        <p class="sechead__s">
          <?= $rows ? te('results_n', ['n' => count($rows)]) . ' · ' . te('wish_note') : te('wish_empty_note') ?>
        </p>
      </div>
      <a class="sechead__more" href="<?= h(vr_url('shop.php')) ?>"><?= te('keep_shopping') ?><?= vr_icon('arrow', 16) ?></a>
    </div>

    <?php if (!$rows): ?>
      <div class="notice"><strong><?= te('wish_empty') ?></strong></div>
      <a class="btn" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('cart_empty_cta') ?></span></a>
    <?php else: ?>
      <?php vr_grid($rows, ['size_hint' => true, 'eager_first' => true, 'class' => 'grid--4']); ?>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
