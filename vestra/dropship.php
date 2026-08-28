<?php
/**
 * VESTRA — Dropshipping: single-piece purchase, no minimum order.
 * ?id=<product id> narrows the list to one item — the deep link a product
 * page's "Buy a single piece" button uses.
 *
 * TICARI HESABA KAPALI BIR SAYFA, VE BU BILEREK BOYLE.
 * Once girissizdi: odeme baglantisini kim acarsa o oduyordu, yani son tuketici
 * de odeyebiliyordu. Ama sitenin kendi kullanim sartlari "business users only
 * (no consumers) ... consumer-withdrawal rights do not apply" diyor. Girissiz
 * bir tuketici odemesi, yayindaki metnin YAPMADIGIMIZI soyledigi seyi yapmak
 * demekti -- hukuken de, Stripe incelemesi acisindan da en kotu hal.
 *
 * Model artik net: siparisi DOGRULANMIS TICARI ORTAK veriyor, kendi musterisi
 * icin. Teslimat adresi ve beden alanlarina musterinin bilgilerini o giriyor;
 * son alici ile VESTRA arasinda tuketici sozlesmesi kurulmuyor.
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/dropship.php';

$dsUser    = auth_user();
$dsAllowed = $dsUser && auth_prices_unlocked($dsUser);

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
  <?php /* Kosullar (fiyat kurali, bolgeler, sureler, gumruk, stok, fotograf)
           anlatim sayfasinda ve orasi HERKESE ACIK. Buraya kopyalamak, iki
           yerde bakim gerektiren tek bir metin demek olurdu. */ ?>
  <p class="hint" style="margin-bottom:24px"><?= t('Buy a single piece, no minimum order — pay by card, we ship it out.') ?>
    <a class="acc" href="/dropshipping"><?= t('How it works, prices and delivery times') ?> →</a></p>

  <?php if (!$dsAllowed): ?>
  <div class="order-box">
    <p style="margin:0 0 10px"><b><?= t('Dropshipping is for verified trade accounts.') ?></b></p>
    <p class="hint" style="margin:0 0 14px">
      <?= t('Orders are placed by a trade partner on behalf of their own customer — you enter your customer\'s delivery address and size at checkout. VESTRA does not sell to consumers.') ?>
    </p>
    <?php if (!$dsUser): ?>
      <a class="btn btn-p" href="/login?back=<?= urlencode('/dropship'.($onlyId!==''?'?id='.$onlyId:'')) ?>"><?= t('Sign in') ?></a>
      <a class="btn btn-o" href="/register" style="margin-left:8px"><?= t('Create a trade account') ?></a>
    <?php else: ?>
      <a class="btn btn-p" href="/buyer?tab=kyc"><?= t('Upload your trade licence') ?></a>
    <?php endif; ?>
  </div>
  <?php else: ?>

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
          <?php /* Onbir bolgeyi tek tek yazmak bu satiri okunmaz yapiyordu.
                   Ayni ucreti tasiyanlar birlestirilip kod olarak listeleniyor;
                   tam adlar zaten asagidaki acilir listede duruyor. */
                $byFee = [];
                foreach (vestra_dropship_zones() as $zc => [$zlabel, $zfee]) $byFee[(string)$zfee][] = $zc;
                $zz = [];
                foreach ($byFee as $zfee => $codes) $zz[] = implode('/', $codes).' '.vestra_money((float)$zfee);
                echo htmlspecialchars(implode(' · ', $zz)); ?>
          <br><?= htmlspecialchars(t('Delivery times are shown next to each region below.')) ?></div>
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
      <?php else: /* katalog geneli: elle kurulmus stok haritasi yok */ ?>
      <?php /* Renk ve beden, tipki teslimat adresi gibi, ORTAGIN kendi musterisi
               icin doldurdugu alanlar. Ama ilanda zaten belli olani ona SORMAK
               gereksiz bir karar: tek renkli bir urunde "renk seciniz" demek,
               secenegi olmayan bir secim.
               Kural: TEK deger varsa alan HIC CIZILMIYOR -- ne etiket ne kutu,
               yalnizca gizli alan; deger zaten baslikta yaziyor. Birden fazlasi
               acilir liste olur. Hicbiri cozulemezse serbest metne dusulur. */ ?>
      <?php
        $dsCols  = vestra_colour_options($p);
        $dsSizes = vestra_size_options($p);
        $colOne  = count($dsCols)  === 1;
        $sizeOne = count($dsSizes) === 1;
      ?>
      <?php if ($colOne): ?>
      <input type="hidden" name="colour" value="<?= htmlspecialchars((string)$dsCols[0]) ?>">
      <?php endif; ?>
      <?php if ($sizeOne): ?>
      <input type="hidden" name="size" value="<?= htmlspecialchars((string)$dsSizes[0]) ?>">
      <?php endif; ?>
      <?php if ($colOne || $sizeOne): ?>
      <p class="hint" style="margin:0 0 10px"><?= t('This article ships as') ?>
        <b><?= htmlspecialchars(implode(' · ', array_map(fn($v) => t((string)$v),
              array_merge($colOne ? [$dsCols[0]] : [], $sizeOne ? [$dsSizes[0]] : [])))) ?></b>.</p>
      <?php endif; ?>
      <?php if (!$colOne || !$sizeOne): ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if (!$colOne): ?>
        <div style="flex:1;min-width:140px">
          <label class="hint"><?= t('Colour') ?></label>
          <?php if ($dsCols): ?>
          <select name="colour" required style="width:100%">
            <?php foreach ($dsCols as $c): ?><option value="<?= htmlspecialchars((string)$c) ?>"><?= htmlspecialchars(t((string)$c)) ?></option><?php endforeach; ?>
          </select>
          <?php else: ?>
          <input type="text" name="colour" required style="width:100%" placeholder="<?= htmlspecialchars(t('e.g. black')) ?>">
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!$sizeOne): ?>
        <div style="flex:1;min-width:140px">
          <label class="hint"><?= t('Size') ?></label>
          <?php if ($dsSizes): ?>
          <select name="size" required style="width:100%">
            <?php foreach ($dsSizes as $sz): ?><option value="<?= htmlspecialchars((string)$sz) ?>"><?= htmlspecialchars((string)$sz) ?></option><?php endforeach; ?>
          </select>
          <?php else: ?>
          <input type="text" name="size" required style="width:100%" placeholder="<?= htmlspecialchars(t('e.g. M')) ?>">
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <p class="hint" style="margin:8px 0 0;font-size:12px">
        <?= t('Pick the colour and size your customer ordered. You enter their delivery address on the next step.') ?><br>
        <?= t('Availability is confirmed with the seller after the order; if the size is unavailable you are refunded in full.') ?><br>
        <?php /* Gumruk satiri odeme ONCESINDE ve gorunur yerde duruyor. Sartlara
                 yazmak yeterli degil: paketi kapida reddettiren sey, alicinin
                 beklemedigi bir fatura -- ve kimse siparis verirken sartlari
                 okumuyor. */ ?>
        <b><?= t('Duties and import taxes in the destination country are not included and are payable on delivery.') ?></b>
        <?= t('You can settle them yourself or leave them to your customer.') ?>
      </p>
      <?php endif; ?>
      <label class="hint" style="margin-top:8px;display:block"><?= t('Delivery region') ?></label>
      <?php /* Bolge burada seciliyor, odeme sayfasinda degil: Stripe kargo
               secenegini adrese gore kisitlamiyor, ucunu birden koyarsak
               Tokyo'ya giden siparis Avrupa ucretiyle odenebiliyor. */ ?>
      <?php /* Sure secenegin ICINDE: alici bolgeyi secerken "ne kadar surer"
               sorusunu da soruyor, cevabi baska bir satirda aramak zorunda
               kalmasin. Is gunu oldugu acikca yaziliyor -- "7-14 gun" okuyan
               biri musterisine takvim gunu sozu verir. */ ?>
      <select name="zone" required style="width:100%">
        <?php foreach (vestra_dropship_zones() as $zc => [$zl, $zf, $zd]): ?>
        <option value="<?= htmlspecialchars($zc) ?>"><?= htmlspecialchars(t($zl)) ?> — <?= vestra_money((float)$zf) ?> · <?= htmlspecialchars(sprintf(t('%s working days'), $zd)) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="hint" style="margin-top:8px;display:block"><?= t('Quantity') ?></label>
      <input type="number" name="qty" value="1" min="1" style="width:90px">
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Buy now') ?> — <?= vestra_money((float)$ds['price']) ?></button>
    </form>
    <div class="hint" style="margin-top:8px"><a href="/product?id=<?= urlencode($p['id']) ?>"><?= t('Full product details') ?> →</a></div>
  </div>
  <?php endforeach; ?>
  <?php endif; /* $dsAllowed */ ?>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
