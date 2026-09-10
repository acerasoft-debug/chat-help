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
/* Toptan erisim aboneligi (KURAL 16): acikse tek adet ZAMSIZ fiyattan.
   Tek karar noktasi kodda; sayfa yalnizca soruyor. */
$dsPlan    = vestra_dropship_plan_active($dsUser);
$dsPlanMsg = (string)($_GET['plan'] ?? '');

$onlyId = trim((string)($_GET['id'] ?? ''));

/* GEZILEBILIR DROPSHIP KATALOGU (operator, 8 Eyl 2026: "Dropshipping icin ayri
   bir sayfada acabiliriz").

   Eskiden bu sayfa urun SECTIRMIYORDU: kimlik verilmemisse 12 ornek basip
   /shop'a yolluyordu, ve /shop dropship'e gore suzulemiyor -- yani "tek adet
   alinabilecek seyler" listesine hicbir yerden ulasilamiyordu. Artik burasi o
   liste. ?id= verilince gene tek urunun satin alma formu aciliyor; iki gorunum
   ayni dosyada cunku ikisi de ayni kaynaktan (dropship'e ACIK kume) turuyor. */
$dsPool = array_values(array_filter(vestra_products(), 'vestra_dropship_enabled'));

/* SUZGEC SECENEKLERI, dropship'e ACIK kumeden turer -- butun katalogdan degil.
   Butun katalogdan turetilseydi listede tek adet alinamayan bir marka (Lacoste,
   Ralph Lauren) ya da bolme (ayakkabi) secilebilir ve sonuc hep bos cikardi:
   kullaniciya kendi kurdugumuz bir cikmaz sokak gostermek olurdu. */
$dsBrands = []; $dsCats = [];
foreach ($dsPool as $p) {
    $b = trim((string)($p['brand'] ?? '')); if ($b !== '') $dsBrands[$b] = ($dsBrands[$b] ?? 0) + 1;
    $c = trim((string)($p['cat']   ?? '')); if ($c !== '') $dsCats[$c]   = ($dsCats[$c]   ?? 0) + 1;
}
ksort($dsBrands, SORT_NATURAL | SORT_FLAG_CASE);
ksort($dsCats,   SORT_NATURAL | SORT_FLAG_CASE);

$fBrand = trim((string)($_GET['brand'] ?? ''));
$fCat   = trim((string)($_GET['cat']   ?? ''));
$fQ     = trim((string)($_GET['q']     ?? ''));
/* Gecersiz bir suzgec sessizce YOK SAYILIR, bos sonuc dondurmez: adres cubugundan
   gelen yanlis bir marka adi "bu sayfada hicbir sey yok" gibi okunurdu. */
if ($fBrand !== '' && !isset($dsBrands[$fBrand])) $fBrand = '';
if ($fCat   !== '' && !isset($dsCats[$fCat]))     $fCat   = '';

$items = array_values(array_filter($dsPool, function ($p) use ($onlyId, $fBrand, $fCat, $fQ) {
    if ($onlyId !== '') return ($p['id'] ?? '') === $onlyId;
    if ($fBrand !== '' && trim((string)($p['brand'] ?? '')) !== $fBrand) return false;
    if ($fCat   !== '' && trim((string)($p['cat']   ?? '')) !== $fCat)   return false;
    if ($fQ !== '') {
        $hay = mb_strtolower(trim(($p['brand'] ?? '').' '.($p['name'] ?? '').' '.($p['sku'] ?? '')));
        if (mb_strpos($hay, mb_strtolower($fQ)) === false) return false;
    }
    return true;
}));

$dsTotal   = count($dsPool);       // dropship'e acik TUM urunler
$dsMatched = count($items);        // suzgecten gecenler
$dsAll     = $onlyId !== '';       // tek urun gorunumu mu

/* Sayfalama. 24, ekranda dort sutunda alti sira demek; daha buyugu mobilde
   fotograf yukunu gereksiz buyutuyor. */
$dsPer  = 24;
$dsPage = max(1, (int)($_GET['page'] ?? 1));
$dsPages = $dsAll ? 1 : max(1, (int)ceil($dsMatched / $dsPer));
if ($dsPage > $dsPages) $dsPage = $dsPages;
if (!$dsAll) $items = array_slice($items, ($dsPage - 1) * $dsPer, $dsPer);

/* Suzgec/sayfa baglantilari kurulurken mevcut secimler korunur.
   SUZGEC degisirse sayfa 1'e doner: 3. sayfadayken marka secen biri, yeni
   suzgecte 3 sayfa olmadigi icin bos bir sayfaya duserdi. */
$dsUrl = function (array $over = []) use ($fBrand, $fCat, $fQ, $dsPage) {
    $q = ['brand' => $fBrand, 'cat' => $fCat, 'q' => $fQ, 'page' => (string)($dsPage > 1 ? $dsPage : '')];
    if (array_intersect_key($over, ['brand' => 1, 'cat' => 1, 'q' => 1])) $q['page'] = '';
    foreach ($over as $k => $v) $q[$k] = (string)$v;
    $q = array_filter($q, fn($v) => $v !== '');
    return '/dropship' . ($q ? '?' . http_build_query($q) : '');
};
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

  <?php /* ODEME DURDURULDU (operator, 7 Eyl 2026): satin alma formu HIC
           cizilmiyor. Gosterilip reddedilen bir dugme, olmayan dugmeden kotu --
           KURAL 4'un karsi teklif alaninda ogrendigi ders. Sayfa ayakta kaliyor
           (fiyatlar, bolgeler, kosullar okunabilir); kapali olan odeme.
           Kapinin kendisi sunucuda: dropship_create_order(). */
     if (!vestra_dropship_payments_enabled()): ?>
  <div class="order-box">
    <p style="margin:0 0 10px"><b><?= t('Single-piece ordering is paused right now.') ?></b></p>
    <p class="hint" style="margin:0">
      <?= t('Dropshipping is being reworked and card payment for single pieces is switched off for the moment. Wholesale ordering with the usual minimums is unaffected.') ?>
      <a class="acc" href="/shop"><?= t('Go to the catalogue') ?> →</a>
    </p>
  </div>
  <?php elseif (!$dsAllowed): ?>
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

  <?php /* ABONELIK DURUM MESAJLARI. Stripe'tan donen her hal bir cumle yaziyor;
           sessiz donus, "odedim mi odemedim mi" sorusunu ziyaretcide birakir. */
     $planMsgs = [
       'ok'       => ['ok',  t('Wholesale access is active. Single pieces are now billed at the wholesale price.')],
       'cancel'   => ['no',  t('Checkout was cancelled — nothing was charged.')],
       'already'  => ['ok',  t('You already have wholesale access.')],
       'gate'     => ['no',  t('Dropshipping is for verified trade accounts.')],
       'notready' => ['no',  t('Card payment is not available right now. Please try again later.')],
       'error'    => ['no',  t('Something went wrong starting the checkout. Please try again.')],
     ];
     if (isset($planMsgs[$dsPlanMsg])): [$kind, $text] = $planMsgs[$dsPlanMsg]; ?>
  <div class="banner <?= $kind === 'ok' ? 'info' : '' ?>" style="margin-bottom:20px"><?= $kind === 'ok' ? '✓' : '•' ?> <?= htmlspecialchars($text) ?></div>
  <?php endif; ?>

  <?php /* TOPTAN ERISIM (operator, 8 Eyl 2026). Zam kalkmiyor; abonelik onu
           kaldiran sey. Rakam koddan geliyor (VESTRA_DROPSHIP_PLAN_PRICE),
           metne gomulmuyor -- KURAL 6'nin dersi. */ ?>
  <?php if (!$dsPlan): ?>
  <div class="order-box" style="margin-bottom:24px;border-color:var(--acc)">
    <div style="font-weight:600;font-size:16px;margin-bottom:6px">🔓 <?= t('Wholesale access') ?></div>
    <p class="hint" style="margin:0 0 12px">
      <?= sprintf(t('Single pieces are priced at wholesale + %d%%. With wholesale access you pay the plain wholesale price on every single-piece order — %s per month, cancel any time.'), (int)round(VESTRA_DROPSHIP_MARKUP * 100), vestra_dropship_plan_label()) ?>
    </p>
    <form method="post" action="/stripe/dropship-plan" style="margin:0">
      <button class="btn btn-p" type="submit"><?= t('Activate wholesale access') ?> — <?= vestra_dropship_plan_label() ?>/<?= t('month') ?></button>
    </form>
  </div>
  <?php else: ?>
  <div class="order-box" style="margin-bottom:24px;border-color:var(--acc)">
    <div style="font-weight:600;margin-bottom:4px">✓ <?= t('Wholesale access') ?></div>
    <p class="hint" style="margin:0">
      <?= t('Active — single pieces are billed at the wholesale price, with no single-piece surcharge.') ?>
    </p>
    <?php /* Portal POST istiyor; duz bir baglanti GET atar ve sessizce geri
             doner -- "iptal edemiyorum" sikayeti tam olarak buradan cikardi. */ ?>
    <form method="post" action="/stripe/portal" style="margin:8px 0 0">
      <button class="btn btn-o" type="submit" style="font-size:13px"><?= t('Manage or cancel') ?></button>
    </form>
    <p class="hint" style="margin:0">
    </p>
  </div>
  <?php endif; ?>

  <?php if (!$items): ?>
  <?php /* Bos sonucun SEBEBI yaziliyor. "Nothing available right now" bir
           suzgec yuzunden bosaldiginda yanlis: katalog dolu, secim dar. */ ?>
    <?php if (!$dsAll && ($fBrand !== '' || $fCat !== '' || $fQ !== '')): ?>
    <p class="hint"><?= t('No article matches this filter.') ?>
      <a class="acc" href="/dropship"><?= t('Clear') ?> →</a></p>
    <?php else: ?>
    <p class="hint"><?= t('Nothing available right now.') ?></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ── KATALOG GORUNUMU (?id= yokken) ─────────────────────────────────
           Suzgec + izgara + sayfalama. Satin alma formu burada CIZILMIYOR:
           renk/beden/bolge/adet secimi tek urunun sayfasina ait ve yirmi dort
           urunun formunu ust uste basmak, secim yapilacak sayfayi doldurulacak
           bir kagit yiginina cevirirdi. ─────────────────────────────────── */
     if (!$dsAll): ?>

  <form method="get" action="/dropship" class="order-box" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div style="flex:1 1 180px">
      <label class="hint" style="display:block"><?= t('Brand') ?></label>
      <select name="brand" style="width:100%">
        <option value=""><?= t('All brands') ?></option>
        <?php foreach ($dsBrands as $b => $n): ?>
        <option value="<?= htmlspecialchars($b) ?>"<?= $fBrand === $b ? ' selected' : '' ?>><?= htmlspecialchars($b) ?> (<?= (int)$n ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1 1 180px">
      <label class="hint" style="display:block"><?= t('Category') ?></label>
      <select name="cat" style="width:100%">
        <option value=""><?= t('All categories') ?></option>
        <?php foreach ($dsCats as $c => $n): ?>
        <option value="<?= htmlspecialchars($c) ?>"<?= $fCat === $c ? ' selected' : '' ?>><?= htmlspecialchars(t($c)) ?> (<?= (int)$n ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1 1 160px">
      <label class="hint" style="display:block"><?= t('Search') ?></label>
      <input type="text" name="q" value="<?= htmlspecialchars($fQ) ?>" placeholder="<?= htmlspecialchars(t('name or article no.')) ?>" style="width:100%">
    </div>
    <button class="btn btn-p" type="submit"><?= t('Filter') ?></button>
    <?php if ($fBrand !== '' || $fCat !== '' || $fQ !== ''): ?>
    <a class="btn btn-o" href="/dropship"><?= t('Clear') ?></a>
    <?php endif; ?>
  </form>

  <p class="hint" style="margin:-8px 0 18px">
    <?= sprintf(t('%1$s of %2$s articles can be bought as a single piece.'), number_format($dsMatched), number_format($dsTotal)) ?>
    · <?= t('Card payment is taken in US dollars.') ?>
    <?php if ($dsPages > 1): ?> · <?= sprintf(t('page %1$d / %2$d'), $dsPage, $dsPages) ?><?php endif; ?>
  </p>

  <div class="shopgrid" style="margin-bottom:24px">
    <?php foreach ($items as $gp):
      $gimg  = vestra_primary_image($gp);
      $gunit = vestra_dropship_unit_price($gp, $dsUser);
      $gwh   = vestra_dropship_wholesale_price($gp);
      $gsave = (!$dsPlan && $gwh !== null && $gunit !== null && $gwh < $gunit); ?>
    <a class="scard" href="/dropship?id=<?= urlencode((string)$gp['id']) ?>">
      <?php if ($gimg): ?><img src="<?= htmlspecialchars($gimg) ?>" alt="" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px"><?php endif; ?>
      <div style="padding:8px 2px 2px">
        <div class="hint" style="font-size:11px"><?= htmlspecialchars((string)($gp['brand'] ?? '')) ?></div>
        <div style="font-size:13px;line-height:1.3;margin:2px 0 4px"><?= htmlspecialchars((string)($gp['name'] ?? '')) ?></div>
        <div style="font-weight:600"><?= $gunit !== null ? vestra_money((float)$gunit) : '—' ?>
          <?php if ($dsPlan): ?><span class="hint" style="font-weight:400">· <?= t('wholesale price') ?></span><?php endif; ?></div>
        <?php if ($gsave): ?><div class="hint" style="font-size:11px;color:var(--acc)"><?= sprintf(t('%s with wholesale access'), vestra_money((float)$gwh)) ?></div><?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($dsPages > 1): ?>
  <div style="display:flex;gap:8px;justify-content:center;align-items:center;margin-bottom:24px;flex-wrap:wrap">
    <?php if ($dsPage > 1): ?><a class="btn btn-o" href="<?= htmlspecialchars($dsUrl(['page' => $dsPage - 1])) ?>">← <?= t('Previous') ?></a><?php endif; ?>
    <span class="hint"><?= sprintf(t('page %1$d / %2$d'), $dsPage, $dsPages) ?></span>
    <?php if ($dsPage < $dsPages): ?><a class="btn btn-o" href="<?= htmlspecialchars($dsUrl(['page' => $dsPage + 1])) ?>"><?= t('Next') ?> →</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; /* katalog gorunumu */ ?>

  <?php /* TEK URUN GORUNUMU: yalnizca ?id= verildiginde satin alma formu. */ ?>
  <?php if ($dsAll): foreach ($items as $p): $img = vestra_primary_image($p); $ds = vestra_dropship_of($p);
        /* Gosterilen fiyat, bu ziyaretcinin GERCEKTEN odeyecegi fiyat.
           Sayfada bir, kasada baska rakam gostermek bu depoda zaten bir
           kez yasandi (KURAL 6, escrow tavani). */
        $dsUnit  = vestra_dropship_unit_price($p, $dsUser);
        $dsUsd   = vestra_dropship_usd_unit($p, $dsUser);   // Stripe'in cekecegi tutar
        $dsWhole = vestra_dropship_wholesale_price($p);
        $dsSaves = (!$dsPlan && $dsWhole !== null && $dsUnit !== null && $dsWhole < $dsUnit); ?>
  <div class="order-box" style="margin-bottom:20px">
    <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:12px">
      <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:10px;flex:none"><?php endif; ?>
      <div>
        <div class="hint"><?= htmlspecialchars((string)($p['brand'] ?? '')) ?></div>
        <div style="font-weight:600;font-size:16px"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></div>
        <div class="hint" style="margin-top:4px"><b><?= vestra_money((float)$dsUnit) ?></b> / <?= t('piece') ?>
          <?php if ($dsPlan): ?><span style="color:var(--acc)">· <?= t('wholesale price') ?></span><?php endif; ?>
          <?php if ($dsSaves): ?><br><span style="color:var(--acc)"><?= sprintf(t('%s per piece with wholesale access'), vestra_money((float)$dsWhole)) ?></span><?php endif; ?>
          <br><?= t('shipping') ?>:
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
      <?php /* Transit suresi bolgeye gore degisiyor ama CIKIS noktasi degismiyor;
               alicinin gumruk beklentisi de tam buradan cikiyor, o yuzden sure
               satirinin hemen altinda duruyor. */ ?>
      <?php /* Bugun buraya hicbir Marca Online ilani dusmuyor (ic camasiri dropship'e
               kapali, KURAL 21) -- kontrol yine de burada, cunku olcut BOLME degil
               SATICI: o kural bir gun degisirse satir sessizce geri gelirdi. */
            if (!vestra_hides_ships_from($p)): ?>
      <div class="hint" style="margin-top:6px"><?= vestra_ships_from_flag($p) ?> <?= htmlspecialchars(vestra_ships_from_label($p)) ?></div>
      <?php endif; ?>
      <label class="hint" style="margin-top:8px;display:block"><?= t('Quantity') ?></label>
      <input type="number" name="qty" value="1" min="1" style="width:90px">
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Buy now') ?> — <?= vestra_money((float)$dsUnit) ?></button>
      <?php /* TAHSILAT USD (operator, 8 Eyl 2026). Katalog EUR tabanli, kart
               USD cekiliyor; alici bunu Stripe sayfasinda degil BURADA gormeli.
               Kur ve kaynagi da yaziliyor: rakami dogrulayamadigi bir cevrim,
               alicinin muhasebesine acik soru olarak duser. */ ?>
      <?php if ($dsUsd !== null): ?>
      <p class="hint" style="margin:8px 0 0;font-size:11.5px">
        <?= sprintf(t('Charged in US dollars: %1$s per piece, at 1 EUR = %2$s.'),
                    'US$'.number_format($dsUsd, 2), number_format(vestra_fx('USD'), 4)) ?>
        <?php if (($__fxd = vestra_fx_date()) !== ''): ?> (<?= htmlspecialchars($__fxd) ?>)<?php endif; ?>
        <?= t('Shipping is converted at the same rate.') ?>
      </p>
      <?php else: ?>
      <p class="hint" style="margin:8px 0 0;color:var(--bad)"><?= t('The EUR/USD rate is unavailable right now, so orders are paused rather than charged at a guessed rate.') ?></p>
      <?php endif; ?>
    </form>
    <div class="hint" style="margin-top:8px"><a href="/product?id=<?= urlencode($p['id']) ?>"><?= t('Full product details') ?> →</a></div>
  </div>
  <?php endforeach; endif; /* tek urun gorunumu */ ?>
  <?php endif; /* $dsAllowed */ ?>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
