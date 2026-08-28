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

require_once __DIR__ . '/inc/dropship.php';

$onlyId = trim((string)($_GET['id'] ?? ''));
$items = array_values(array_filter(vestra_products(), function ($p) use ($onlyId) {
    if (!vestra_dropship_enabled($p)) return false;
    if ($onlyId !== '' && ($p['id'] ?? '') !== $onlyId) return false;
    return true;
}));
/* Dropship artik katalogun neredeyse tamamina acik. Uc yuz urunu tek sayfaya
   dizmek, bu sayfanin isi degil -- katalogun isi. Burasi bir SATIN ALMA
   sayfasi; urun secimi /shop'ta yapiliyor ve buraya ?id= ile geliniyor.
   Kimlik verilmemisse yalnizca bir avuc ornek gosterip katalogu isaret et. */
$dsTotal = count($items);
$dsAll   = $onlyId !== '';
if (!$dsAll) $items = array_slice($items, 0, 12);
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

  <?php if (!$dsAll && $dsTotal > count($items)): ?>
  <p class="hint" style="margin:-14px 0 22px">
    <?= sprintf(t('%d articles are available for single-piece purchase.'), $dsTotal) ?>
    <a href="/shop"><?= t('Browse the catalogue') ?> →</a>
  </p>
  <?php endif; ?>

  <?php foreach ($items as $p): $img = vestra_primary_image($p); $ds = vestra_dropship_of($p); ?>
  <div class="order-box" style="margin-bottom:20px">
    <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:12px">
      <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:10px;flex:none"><?php endif; ?>
      <div>
        <div class="hint"><?= htmlspecialchars((string)($p['brand'] ?? '')) ?></div>
        <div style="font-weight:600;font-size:16px"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></div>
        <div class="hint" style="margin-top:4px"><?= vestra_money((float)$ds['price']) ?> / <?= t('piece') ?> · <?= t('shipping') ?>:
          <?php $zz = []; foreach (vestra_dropship_zones() as [$zlabel, $zfee]) $zz[] = htmlspecialchars(t($zlabel)).' '.vestra_money((float)$zfee);
                echo implode(' · ', $zz); ?></div>
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
      <?php if (!empty($ds['stock'])): /* elle kurulmus ilan: gercek harita var */ ?>
      <label class="hint"><?= t('Colour / size') ?></label>
      <select name="variant" required style="width:100%">
        <?php foreach ($ds['stock'] as $dcolour => $dsizes): foreach ($dsizes as $dsize => $dleft): ?>
        <option value="<?= htmlspecialchars($dcolour . '|' . $dsize) ?>"<?= $dleft < 1 ? ' disabled' : '' ?>><?= htmlspecialchars($dcolour . ' — ' . $dsize) ?> — <?= $dleft > 0 ? sprintf(t('%d left'), $dleft) : t('out of stock') ?></option>
        <?php endforeach; endforeach; ?>
      </select>
      <?php else: /* katalog geneli: secilecek harita yok, alici yaziyor */ ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <div style="flex:1;min-width:140px">
          <label class="hint"><?= t('Colour') ?></label>
          <?php $cols = array_values(array_filter((array)($p['colors'] ?? []))); ?>
          <?php if ($cols): ?>
          <select name="colour" required style="width:100%">
            <?php foreach ($cols as $c): ?><option value="<?= htmlspecialchars((string)$c) ?>"><?= htmlspecialchars((string)$c) ?></option><?php endforeach; ?>
          </select>
          <?php else: ?>
          <input type="text" name="colour" required style="width:100%" placeholder="<?= htmlspecialchars(t('e.g. black')) ?>">
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:140px">
          <label class="hint"><?= t('Size') ?></label>
          <input type="text" name="size" required style="width:100%"
                 placeholder="<?= htmlspecialchars(vestra_sizes_label((string)($p['sizes'] ?? '')) ?: t('e.g. M')) ?>">
        </div>
      </div>
      <?php /* Beden serbest metin, cunku katalogda beden bir LISTE degil, paket
               kuralini anlatan bir cumle ("Cartons of 10 · sizes S-XXL"). Ondan
               makineyle secenek uretmek, olmayan bedeni varmis gibi gostermek
               olurdu. Alicinin yazdigi beden siparise gecer, teyit satiicidan. */ ?>
      <p class="hint" style="margin:8px 0 0;font-size:12px">
        <?= t('Availability is confirmed with the seller after the order; if the size is unavailable you are refunded in full.') ?>
      </p>
      <?php endif; ?>
      <label class="hint" style="margin-top:8px;display:block"><?= t('Quantity') ?></label>
      <input type="number" name="qty" value="1" min="1" style="width:90px">
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Buy now') ?> — <?= vestra_money((float)$ds['price']) ?></button>
    </form>
    <div class="hint" style="margin-top:8px"><a href="/product?id=<?= urlencode($p['id']) ?>"><?= t('Full product details') ?> →</a></div>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
