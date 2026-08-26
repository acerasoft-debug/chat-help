<?php
/**
 * VESTRA — Dropshipping: single-piece purchase, no minimum order.
 * Lists every dropship-enabled product (today: just lac-polo-paris).
 * ?id=<product id> narrows the list to one item — the deep link a product
 * page's "Buy a single piece" button uses. No VESTRA login required, same
 * public-checkout model as the partner API (api/dropship.php).
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/products.php';

$onlyId = trim((string)($_GET['id'] ?? ''));
$items = array_values(array_filter(vestra_products(), function ($p) use ($onlyId) {
    if (empty($p['dropship']['enabled'])) return false;
    if ($onlyId !== '' && ($p['id'] ?? '') !== $onlyId) return false;
    return true;
}));
$dserr = (string)($_GET['dropship_error'] ?? '');

$PAGE = t('Dropshipping');
require __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:720px;margin:40px auto">
  <h1 style="margin-bottom:6px">📮 <?= t('Dropshipping') ?></h1>
  <p class="hint" style="margin-bottom:24px"><?= t('Buy a single piece, no minimum order — pay by card, we ship it out.') ?></p>

  <?php if (!$items): ?>
  <p class="hint"><?= t('Nothing available right now.') ?></p>
  <?php endif; ?>

  <?php foreach ($items as $p): $img = vestra_primary_image($p); ?>
  <div class="order-box" style="margin-bottom:20px">
    <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:12px">
      <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:10px;flex:none"><?php endif; ?>
      <div>
        <div class="hint"><?= htmlspecialchars((string)($p['brand'] ?? '')) ?></div>
        <div style="font-weight:600;font-size:16px"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></div>
        <div class="hint" style="margin-top:4px"><?= vestra_money((float)$p['dropship']['price']) ?> / <?= t('piece') ?> · <?= t('shipping') ?>: <?= t('France') ?> <?= vestra_money((float)$p['dropship']['ship_fr']) ?> · <?= t('rest of EU') ?> <?= vestra_money((float)$p['dropship']['ship_eu']) ?></div>
      </div>
    </div>

    <?php if ($dserr === 'out_of_stock'): ?>
    <div class="hint" style="margin-bottom:8px;color:#d66">⚠ <?= t('That colour/size just sold out — pick another one.') ?></div>
    <?php elseif ($dserr !== ''): ?>
    <div class="hint" style="margin-bottom:8px;color:#d66">⚠ <?= t('Could not start checkout — please try again.') ?></div>
    <?php endif; ?>

    <form method="post" action="/dropship-checkout">
      <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
      <label class="hint"><?= t('Colour / size') ?></label>
      <select name="variant" required style="width:100%">
        <?php foreach (($p['dropship']['stock'] ?? []) as $dcolour => $dsizes): foreach ($dsizes as $dsize => $dleft): ?>
        <option value="<?= htmlspecialchars($dcolour . '|' . $dsize) ?>"<?= $dleft < 1 ? ' disabled' : '' ?>><?= htmlspecialchars($dcolour . ' — ' . $dsize) ?> — <?= $dleft > 0 ? sprintf(t('%d left'), $dleft) : t('out of stock') ?></option>
        <?php endforeach; endforeach; ?>
      </select>
      <label class="hint" style="margin-top:8px;display:block"><?= t('Quantity') ?></label>
      <input type="number" name="qty" value="1" min="1" style="width:90px">
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Buy now') ?> — <?= vestra_money((float)$p['dropship']['price']) ?></button>
    </form>
    <div class="hint" style="margin-top:8px"><a href="/product?id=<?= urlencode($p['id']) ?>"><?= t('Full product details') ?> →</a></div>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
