<?php
/**
 * VESTRA — the wholesale price list as a page on the site.
 *
 *   /price-list              every brand
 *   /price-list?brand=Balmain  one brand
 *
 * The catalogue belongs on the site rather than in a file someone has to download and
 * open. A page is searchable by the browser, linkable to a specific brand, readable on a
 * phone without a PDF reader, and indexable — and every article links straight through to
 * its own product page, which a downloaded document can only imitate.
 *
 * The Excel export stays, because a buyer building an order genuinely needs to sort and
 * paste rows into their own sheet. The PDF is no longer offered here.
 *
 * SAME RULES AS THE EXPORTS, deliberately, so the three never disagree:
 *   - supplier and seller names appear nowhere;
 *   - ART. NO is the manufacturer's own number, the VESTRA reference is separate;
 *   - retail is the brand's 'rrp' where one is stored and wholesale x3 where it is not,
 *     and every row says which of the two it is.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/stock.php';
require_once __DIR__.'/inc/i18n.php';


$brandFilter = trim((string)($_GET['brand'] ?? ''));

$byBrand = [];
foreach (vestra_products() as $p) {
    $b = trim((string)($p['brand'] ?? ''));
    $price = (float)($p['list'] ?? $p['price'] ?? 0);
    if ($b === '' || $price <= 0) continue;
    if ($brandFilter !== '' && strcasecmp($b, $brandFilter) !== 0) continue;
    $byBrand[$b][] = $p;
}
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byBrand as &$rs) {
    usort($rs, fn($a, $b) => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
}
unset($rs);
$total = array_sum(array_map('count', $byBrand));

/* A filter that matches nothing is a mistyped brand, not an empty catalogue. Saying so
   beats rendering a blank table that reads as "we carry none of this". */
$unknownBrand = $brandFilter !== '' && $total === 0;

$PAGE = $brandFilter !== '' ? $brandFilter.' — '.t('wholesale price list') : t('Wholesale price list');
$NAV  = '';
$META = $brandFilter !== ''
    ? sprintf(t('%s wholesale prices — trade price per piece, minimum order quantity, sizes and article numbers. B2B only.'), $brandFilter)
    : t('VESTRA wholesale price list — every article with its trade price per piece, minimum order quantity, sizes, manufacturer article number and product link. B2B only.');

require __DIR__.'/inc/head.php';
?>
<style>
  .pc-wrap{max-width:1180px;margin:0 auto;padding:0 24px 90px}
  .pc-head{padding:48px 0 22px;border-bottom:1px solid var(--line)}
  .pc-head h1{font-size:clamp(28px,4.4vw,42px);margin:0 0 12px}
  .pc-sub{color:var(--mut);max-width:66ch;line-height:1.6;margin:0 0 4px}
  .pc-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:24px 0 0}
  .pc-tools input{background:var(--bg2);border:1px solid var(--line);color:var(--ink);
    font:inherit;font-size:14px;padding:10px 14px;border-radius:8px;min-width:250px;outline:none}
  .pc-tools input:focus{border-color:rgba(201,168,106,.45)}
  .pc-xls{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:8px;
    background:var(--acc);color:#1a1408;font-weight:700;font-size:13px;letter-spacing:.02em}
  .pc-xls:hover{filter:brightness(1.08)}
  .pc-back{font-size:13px;color:var(--mut)}
  .pc-back:hover{color:var(--acc)}
  .pc-brand{margin:44px 0 0;padding:0 0 8px;border-bottom:1px solid var(--line);
    display:flex;align-items:baseline;justify-content:space-between;gap:14px}
  .pc-brand h2{font-size:22px;margin:0;letter-spacing:.02em}
  .pc-brand span{color:var(--mut);font-size:13px;white-space:nowrap}
  .pc-row{display:grid;grid-template-columns:74px 1fr 130px 96px 116px 116px;gap:16px;
    align-items:center;padding:14px 0;border-bottom:1px solid var(--line)}
  .pc-row:hover{background:rgba(255,255,255,.02)}
  .pc-th{display:grid;grid-template-columns:74px 1fr 130px 96px 116px 116px;gap:16px;
    padding:12px 0 8px;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);font-weight:600}
  .pc-ph{width:74px;height:92px;border-radius:8px;overflow:hidden;background:#f4f1ec;
    display:grid;place-items:center;border:1px solid var(--line)}
  .pc-ph img{width:100%;height:100%;object-fit:cover;display:block}
  .pc-ph .none{font-size:9px;color:#8a8272;letter-spacing:.08em}
  .pc-art{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;
    font-weight:700;letter-spacing:.02em}
  .pc-ref{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:10.5px;color:var(--mut);margin-left:8px}
  .pc-name{font-size:14.5px;line-height:1.4;margin:4px 0 3px}
  .pc-cat{font-size:11.5px;color:var(--mut)}
  .pc-num{font-variant-numeric:tabular-nums;white-space:nowrap}
  .pc-sizes{font-size:13px;color:var(--mut)}
  .pc-stock{margin-top:5px;display:flex;gap:6px;flex-wrap:wrap;align-items:baseline;font-size:12px}
  .pc-stock span{background:rgba(201,168,106,.10);border-radius:4px;padding:2px 6px;white-space:nowrap;
    font-variant-numeric:tabular-nums;color:var(--ink)}
  .pc-stock span b{color:var(--acc);font-weight:700}
  .pc-stock em{font-style:normal;color:var(--mut);white-space:nowrap}
  .pc-moq{font-size:14px;font-weight:600;text-align:right}
  .pc-price{text-align:right}
  .pc-lock{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;white-space:nowrap;
    padding:3px 9px;border:1px solid var(--line);border-radius:999px;color:var(--acc);text-decoration:none}
  .pc-lock:hover{border-color:var(--acc);background:rgba(169,127,44,.08)}
  .pc-price b{font-size:17px;font-weight:700;letter-spacing:-.01em}
  .pc-price .from{display:block;font-size:10px;color:var(--mut);letter-spacing:.1em;text-transform:uppercase}
  .pc-rrp{text-align:right}
  .pc-rrp b{font-size:14px;font-weight:600;color:var(--mut)}
  .pc-rrp .src{display:block;font-size:9.5px;letter-spacing:.1em;text-transform:uppercase;margin-top:2px}
  .pc-rrp .real{color:var(--ok)}
  .pc-rrp .guide{color:#8a8272}
  .pc-empty{padding:60px 0;color:var(--mut);text-align:center}
  .pc-foot{margin-top:44px;padding-top:20px;border-top:1px solid var(--line);
    color:var(--mut);font-size:13px;line-height:1.7;max-width:78ch}
  .pc-foot b{color:var(--ink);font-weight:600}
  @media(max-width:900px){
    .pc-th{display:none}
    .pc-row{grid-template-columns:64px 1fr;gap:14px;padding:16px 0}
    .pc-ph{width:64px;height:80px}
    .pc-meta{grid-column:1/-1;display:flex;gap:18px;flex-wrap:wrap;align-items:baseline}
    .pc-moq,.pc-price,.pc-rrp{text-align:left}
  }
</style>

<div class="pc-wrap">
  <div class="pc-head">
    <h1><?= htmlspecialchars($brandFilter !== '' ? $brandFilter : t('Wholesale price list')) ?></h1>
    <p class="pc-sub"><?= t('Trade prices per piece in EUR, excluding VAT and freight. MOQ is the minimum for that article — there is no seasonal or collection minimum on top.') ?></p>
    <?php if(($__cn = vestra_money_note()) !== ''): ?>
      <p class="curnote" style="margin-top:10px">💱 <?= htmlspecialchars($__cn) ?></p>
    <?php endif; ?>
    <?php if (!$PRICES): ?>
    <div class="banner info" style="margin:14px 0 0;text-align:left">🔒
      <?php if ($PRICE_GATE === 'doc'): ?>
        <?= t('Wholesale prices open as soon as you upload your trade licence / business registration.') ?>
        &nbsp;<a class="btn btn-sm btn-p" style="display:inline-flex;margin-left:6px" href="<?= htmlspecialchars($KYC_URL) ?>"><?= t('Upload document') ?></a>
      <?php else: ?>
        <?= t('Trade prices are shown to registered businesses. Registration is free — you will be asked for your trade licence / business registration.') ?>
        &nbsp;<a class="btn btn-sm btn-p" style="display:inline-flex;margin-left:6px" href="/register"><?= t('Register free') ?></a>
        <a class="btn btn-sm btn-o" style="display:inline-flex;margin-left:6px" href="/login?back=/price-list"><?= t('Sign in') ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <p class="pc-sub"><?= t('ART. NO is the manufacturer\'s own article number; the grey code beside it is the VESTRA reference. Every row links to the product page.') ?></p>
    <div class="pc-tools">
      <input id="pcq" type="search" placeholder="<?= htmlspecialchars(t('Filter by name, article number or category…')) ?>" autocomplete="off">
      <a class="pc-xls" href="/wholesale-list.xlsx<?= $brandFilter !== '' ? '?brand='.rawurlencode($brandFilter) : '' ?>"><?= t('Excel') ?> ↓</a>
      <?php if ($brandFilter !== ''): ?>
        <a class="pc-back" href="/price-list">← <?= t('all brands') ?></a>
      <?php else: ?>
        <a class="pc-back" href="/price-lists"><?= t('by brand') ?> →</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($unknownBrand): ?>
    <div class="pc-empty">
      <?= sprintf(t('No brand called "%s" in the catalogue.'), htmlspecialchars($brandFilter)) ?><br>
      <a class="pc-back" href="/price-lists"><?= t('See every brand we carry') ?> →</a>
    </div>
  <?php else: ?>

  <?php foreach ($byBrand as $b => $rows): ?>
    <div class="pc-brand">
      <h2><?= htmlspecialchars($b) ?></h2>
      <span><?= sprintf(t('%d articles'), count($rows)) ?></span>
    </div>
    <div class="pc-th">
      <div><?= t('Photo') ?></div><div><?= t('Article') ?></div><div><?= t('Sizes') ?></div>
      <div style="text-align:right"><?= t('MOQ') ?></div>
      <div style="text-align:right"><?= t('Wholesale') ?></div>
      <div style="text-align:right"><?= t('Retail') ?></div>
    </div>
    <?php foreach ($rows as $p):
      /* TEK fiyat. Once burada urunun en ucuz kademesi "from EUR 25.00" diye
         yaziliyordu; o rakam 320 adetlik bir basamaga aitti, yani listenin en gorunur
         sayisi listedeki en zor ulasilan sarttı. Alici tek bir sayi ariyor, ve o sayi
         MOQ'da gecerli olan fiyat olmali. */
      $price = (float)($p['list'] ?? $p['price'] ?? 0);
      /* Perakende SADECE markanin kendi yayinladigi fiyat. Tahmin (toptan x3) satiri
         tamamen kalkti: bir tahmini perakende fiyatinin yaninda "guide" yazsa bile,
         listeden fiyat okuyan biri onu marka fiyati saniyor. Bilmiyorsak bos. */
      $rrp = (float)($p['rrp'] ?? 0);
      $realRrp = $rrp > 0;
      $id = (string)($p['id'] ?? '');
      $ident = trim((string)($p['sku'] ?? ''));
      if ($ident === '') $ident = strtoupper(preg_replace('/^[a-z]{2,4}-/', '', $id));
      $img = function_exists('vestra_primary_image') ? vestra_primary_image($p) : '';
      $href = '/product?id='.rawurlencode($id);
      /* One searchable haystack per row for the filter box, so a buyer typing an article
         number finds it without the page reloading or the server being asked again. */
      $hay = mb_strtolower($ident.' '.$id.' '.($p['name'] ?? '').' '.($p['cat'] ?? '').' '.$b);
    ?>
    <div class="pc-row" data-q="<?= htmlspecialchars($hay) ?>">
      <a class="pc-ph" href="<?= htmlspecialchars($href) ?>">
        <?php if ($img !== ''): ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars((string)($p['name'] ?? '')) ?>" loading="lazy" decoding="async">
        <?php else: ?><span class="none"><?= t('no photo') ?></span><?php endif; ?>
      </a>
      <div>
        <span class="pc-art"><?= htmlspecialchars($ident) ?></span><span class="pc-ref"><?= htmlspecialchars($id) ?></span>
        <div class="pc-name"><a href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></a></div>
        <div class="pc-cat"><?= htmlspecialchars((string)($p['cat'] ?? '')) ?></div>
      </div>
      <div class="pc-sizes">
        <?= htmlspecialchars((string)($p['sizes'] ?? '—')) ?>
        <?php if (vestra_stock_enabled($p)): $st = vestra_stock_for($p); ?>
          <div class="pc-stock">
            <?php foreach ($st['sizes'] as $sz => $q): ?><span><b><?= htmlspecialchars((string)$sz) ?></b> <?= (int)$q ?></span><?php endforeach; ?>
            <em><?= sprintf(t('%d pcs in stock'), $st['total']) ?></em>
          </div>
        <?php endif; ?>
      </div>
      <div class="pc-moq pc-num"><?= htmlspecialchars((string)($p['moq'] ?? '—')) ?> <?= htmlspecialchars((string)($p['unit'] ?? 'pc')) ?></div>
      <div class="pc-price pc-num">
        <?php /* Toptan fiyat, /shop ile AYNI kapiya bagli. Ayri tutulsaydi kural
                 sozde kalirdi: katalogda gizlenen rakam bu sayfadan okunurdu.
                 Satirin geri kalani (urun, MOQ, beden, art. no, perakende fiyat)
                 herkese acik kaliyor -- sayfanin arama motorlarindaki degeri ve
                 kampanya baglantisinin gittigi yer bozulmasin diye. */ ?>
        <?php if ($PRICES): ?>
          <b><?= vestra_money((float)$price) ?></b>
        <?php else: ?>
          <a class="pc-lock" href="<?= htmlspecialchars($PRICE_GATE === 'doc' ? $KYC_URL : '/register') ?>">🔒 <?= $PRICE_GATE === 'doc' ? t('Upload document') : t('Trade only') ?></a>
        <?php endif; ?>
      </div>
      <div class="pc-rrp pc-num">
        <?php if ($realRrp): ?>
          <b><?= vestra_money((float)$rrp) ?></b>
          <span class="src real"><?= t('RRP') ?></span>
        <?php else: ?><span class="src none">—</span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <p class="pc-foot">
    <b><?= t('Payment') ?>.</b> <?= t('Escrow-protected up to €3,000 per order: the platform holds the payment and releases it to the seller only after the goods reach you and you confirm they are as described. Above that, or on request, we invoice and the goods are released for dispatch once payment is received.') ?><br>
    <b><?= t('Delivery') ?>.</b> <?= t('Within the EU, typically 7–14 working days from release of the order. Freight is quoted per order.') ?><br>
    <b><?= t('Retail column') ?>.</b> <?= t('“RRP” is the brand’s own recommended price, taken from the brand’s own site. Where a brand publishes none for an article, the column is left empty — we do not estimate a retail price on a brand’s behalf.') ?>
  </p>
  <?php endif; ?>
</div>

<script>
/* Client-side filter. The whole list is already in the page, so filtering here is instant
   and costs no request; each row carries its own lower-cased haystack. Brand headings and
   their column captions hide when nothing under them survives, otherwise the page keeps
   headings for sections that appear empty. */
(function () {
  var q = document.getElementById('pcq');
  if (!q) return;
  var rows = Array.prototype.slice.call(document.querySelectorAll('.pc-row'));
  q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase();
    rows.forEach(function (r) {
      r.style.display = (v === '' || r.dataset.q.indexOf(v) !== -1) ? '' : 'none';
    });
    document.querySelectorAll('.pc-brand').forEach(function (h) {
      var n = h.nextElementSibling, any = false;
      while (n && !n.classList.contains('pc-brand')) {
        if (n.classList.contains('pc-row') && n.style.display !== 'none') { any = true; break; }
        n = n.nextElementSibling;
      }
      h.style.display = any ? '' : 'none';
      var th = h.nextElementSibling;
      if (th && th.classList.contains('pc-th')) th.style.display = any ? '' : 'none';
    });
  });
})();
</script>

<?php require __DIR__.'/inc/foot.php'; ?>
