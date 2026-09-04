<?php
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/inc/products.php';
$PAGE=t('Your order'); $NAV='shop'; require __DIR__.'/inc/head.php';
$placed=isset($_GET['placed']);
$u = auth_user(); // logged-in user for pre-filling form

/* Escrow availability: build {productId: sellerUid} but only for products whose
   seller has finished Connect onboarding (cached escrow_ready flag — no API call).
   The cart JS offers the 🛡️ escrow option only when every cart item maps to one
   such seller (a direct charge is per connected account, so escrow is single-seller). */
require_once __DIR__.'/inc/stripe.php';
$escrowMap = [];
if (stripe_available()) {
  $readySellers = [];
  foreach (auth_accounts() as $a) {
    if (($a['type']??'')==='seller' && !empty($a['escrow_ready']) && !empty($a['stripe_account_id'])) $readySellers[$a['id']] = true;
  }
  if ($readySellers) {
    /* true: unlisted items (the Musterstueck sample) can sit in the cart via a direct
       link and must still get their seller's escrow option. */
    foreach (vestra_products(true) as $p) {
      $sid = $p['seller_uid'] ?? '';
      if ($sid !== '' && isset($readySellers[$sid])) $escrowMap[$p['id']] = $sid;
    }
  }
}
?>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <a href="/shop"><?= t('Catalog') ?></a> · <?= t('Order') ?></div>
    <h1><?= t('Your order') ?></h1>
  </div>

  <?php if($placed): ?>
    <div class="banner ok">✓ <?= t("Order request received. We'll confirm seller availability and send your invoice — goods ship after payment.") ?> <?= t('Reference:') ?> <b><?=htmlspecialchars(substr($_GET['ref']??'',0,20))?></b></div>
    <a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a>
  <?php else: ?>

  <?php if(isset($_GET['err']) && $_GET['err']==='colors'): ?>
    <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:18px">
      <?= t('Colour selection missing — open the product page, choose at least the required number of colours and add the item again.') ?></div>
  <?php endif; ?>
  <?php if(isset($_GET['err']) && $_GET['err']==='escrow'): ?>
    <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:18px">
      <?= t('Secure escrow couldn’t be started for this cart — it’s available only when all items are from a single verified seller. Please choose bank transfer instead.') ?></div>
  <?php endif; ?>
  <?php if(isset($_GET['err']) && $_GET['err']==='escrow_max'): ?>
    <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:18px">
      <?= htmlspecialchars(sprintf(t('Card escrow accepts orders up to %s. This order is above that, so please choose bank transfer — the invoice carries the same buyer protection on delivery.'), '€'.number_format((float)VESTRA_ESCROW_MAX, 2))) ?></div>
  <?php endif; ?>
  <?php /* Sunucu yetki kontrolunun karsiligi (order.php). Sepet dolu kaliyor:
           onay gelince ayni sepetle devam edebilsin. */ ?>
  <?php if(isset($_GET['err']) && $_GET['err']==='not_approved'): ?>
    <div class="banner info" style="margin-bottom:18px">⏳
      <?php /* Kapiyi ONAY acar (KURAL 2) -- belge durumu cumleyi degistirmez. */ ?>
      <?= t('Your account is being reviewed. You can place this order as soon as we activate it — your basket is kept.') ?>
      &nbsp;<a class="acc btn btn-sm btn-o" style="display:inline-flex;margin-left:6px" href="/buyer?tab=kyc"><?= t('Business verification') ?></a></div>
  <?php endif; ?>

  <div id="empty" class="empty" style="display:none">
    <?= t('Your order is empty.') ?> <a class="acc" href="/shop"><?= t('Browse the catalog →') ?></a>
  </div>

  <div id="filled" style="display:none">
    <table class="ctable">
      <thead><tr><th><?= t('Product') ?></th><th><?= t('Qty') ?></th><th class="r"><?= t('Unit') ?></th><th class="r"><?= t('Line total') ?></th><th></th></tr></thead>
      <tbody id="rows"></tbody>
    </table>

    <div class="summary"><div class="box">
      <div class="line"><span><?= t('Subtotal') ?></span><span id="sub"></span></div>
      <?php if (VESTRA_FEE_BUYER > 0): ?>
      <div class="line"><span><?= t('Buyer-protection fee') ?> (<?=round(VESTRA_FEE_BUYER*100)?>%)</span><span id="bfee"></span></div>
      <?php endif; ?>
      <div class="line" id="voucherLine" style="display:none;color:var(--acc)"><span>🎟️ <?= t('Voucher') ?> <span id="voucherCodeLbl"></span></span><span id="voucherAmt"></span></div>
      <div class="line" id="escrowFeeLine" style="display:none"><span>🛡️ <?= t('Buyer protection (escrow)') ?> (<?=round(VESTRA_ESCROW_FEE_BUYER*100,1)?>%)</span><span id="escrowFee"></span></div>
      <div class="line big"><span><?= t('Total (you pay)') ?></span><span id="grand"></span></div>
      <?php if (VESTRA_FEE_BUYER > 0): ?>
      <div class="hint" style="margin-top:8px"><?= sprintf(t('Includes a <b>%d%% buyer-protection fee</b> (verification + authenticity guarantee). The seller separately pays a %d%% commission.'), round(VESTRA_FEE_BUYER*100), round(VESTRA_FEE_SELLER*100)) ?></div>
      <?php else: ?>
      <div class="hint" style="margin-top:8px"><?= t('No platform fees — you pay exactly the goods total on the seller\'s invoice.') ?></div>
      <?php endif; ?>
      <div class="hint" style="margin-top:6px"><?= t('Two ways to pay: <b>🛡️ secure card escrow</b> (we hold the funds and release them to the seller only after you confirm delivery) or <b>🏦 bank transfer</b> by invoice. Choose below.') ?></div>
    </div></div>

    <form id="orderForm" method="post" action="/order">
      <input type="hidden" name="cart" id="cartField">
      <?php
      /* One-shot order token: order.php consumes it on the first POST and replays
         the SAME confirmation for any duplicate POST (double-tap, refresh-resend) —
         a multi-tapped "place order" can never create multiple orders/invoices. */
      $orderTok = bin2hex(random_bytes(12));
      $_SESSION['order_tokens'][$orderTok] = time();
      if (count($_SESSION['order_tokens']) > 20) {
        asort($_SESSION['order_tokens']);
        $_SESSION['order_tokens'] = array_slice($_SESSION['order_tokens'], -20, null, true);
      } ?>
      <input type="hidden" name="order_token" value="<?= $orderTok ?>">

      <h3 style="margin:24px 0 10px"><?= t('Voucher code') ?></h3>
      <div style="display:flex;gap:10px;align-items:center;max-width:680px;flex-wrap:wrap">
        <input name="voucher" id="voucherInput" value="<?= htmlspecialchars(strtoupper((string)($_GET['voucher'] ?? ''))) ?>"
               placeholder="<?= htmlspecialchars(t('e.g. VES-A1B2-C3D4')) ?>" autocomplete="off"
               style="flex:1;min-width:220px;text-transform:uppercase;letter-spacing:1.2px">
        <button class="btn" type="button" id="voucherBtn"><?= t('Apply') ?></button>
      </div>
      <p class="hint" id="voucherMsg" style="margin:8px 0 0;max-width:680px"></p>

      <h3 style="margin:24px 0 10px"><?= t('Payment method') ?></h3>
      <div class="paysel">
        <label class="payopt" id="payEscrowOpt">
          <input type="radio" name="pay" value="escrow" id="payEscrow">
          <span class="payopt-b">
            <b>🛡️ <?= t('Secure escrow (card)') ?> · +<?=round(VESTRA_ESCROW_FEE_BUYER*100,1)?>%</b>
            <span class="hint"><?= t('Pay now by card. VESTRA holds the funds and releases them to the seller only after you confirm delivery — full refund if anything goes wrong.') ?></span>
            <span class="hint payopt-lock" id="escrowLock" style="display:none;color:var(--mut)"><?= t('Available when your whole cart is from one verified seller.') ?></span>
          </span>
        </label>
        <label class="payopt">
          <input type="radio" name="pay" value="bank" id="payBank" checked>
          <span class="payopt-b">
            <b>🏦 <?= t('Bank transfer (invoice)') ?></b>
            <span class="hint"><?= t('We send a PDF invoice with the seller’s bank details. Goods ship after your transfer arrives.') ?></span>
          </span>
        </label>
      </div>

      <h3 style="margin:24px 0 10px"><?= t('Buyer details') ?></h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:680px">
        <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%" value="<?= htmlspecialchars($u['company']??'') ?>"></div>
        <div><label class="hint"><?= t('VAT / Tax ID') ?></label><input name="vat" style="width:100%" value="<?= htmlspecialchars($u['vat_id']??'') ?>"></div>
        <div><label class="hint"><?= t('Contact name') ?> *</label><input name="name" required style="width:100%" value="<?= htmlspecialchars($u['name']??'') ?>"></div>
        <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%" value="<?= htmlspecialchars($u['email']??'') ?>"></div>
        <div><label class="hint"><?= t('Billing address') ?></label><input name="address" style="width:100%" value="<?= htmlspecialchars($u['address']??'') ?>" placeholder="<?= htmlspecialchars(t('Street, postal code, city')) ?>"></div>
        <div><label class="hint"><?= t('Delivery address (if different)') ?></label><input name="ship_address" style="width:100%" value="<?= htmlspecialchars($u['ship_address']??'') ?>" placeholder="<?= htmlspecialchars(t('Leave empty to ship to the billing address')) ?>"></div>
        <div><label class="hint"><?= t('Country') ?></label><input name="country" style="width:100%" value="<?= htmlspecialchars($u['country']??'') ?>"></div>
        <div><label class="hint"><?= t('Phone') ?></label><input name="phone" style="width:100%" value="<?= htmlspecialchars($u['phone']??'') ?>"></div>
      </div>
      <p class="hint" style="margin:8px 0 0"><?= t('Billing details appear on your automatic PDF invoice.') ?></p>
      <div style="margin-top:10px;max-width:680px"><label class="hint"><?= t('Notes') ?></label><textarea name="notes" rows="2" style="width:100%"></textarea></div>
      <input type="text" name="website" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off">
      <?php /* Iade kurali siparis ANINDA da gorunuyor. Onay kutusunun metnine
               EKLENMEDI: o cumle sprintf ile 3 yer tutucu tasiyor ve degistirmek
               7 dilin cevirisini birden Ingilizceye dusururdu. Kural yerine
               Sozlesme'ye 3a maddesi eklendi, onay zaten Sozlesme'yi kapsiyor. */ ?>
      <div class="hint" style="max-width:680px;margin:14px 0 0">
        <?= t('Wholesale orders are closed to returns — wrong, missing or faulty goods only.') ?>
        <a href="/faq?cat=returns" target="_blank" class="acc"><?= t('Returns &amp; claims') ?></a>
      </div>
      <label style="display:flex;gap:9px;align-items:flex-start;margin:8px 0 4px;max-width:680px;font-size:13px;color:var(--mut);cursor:pointer">
        <input type="checkbox" name="consent" value="1" required style="margin-top:3px;flex:none">
        <span><?= sprintf(t('I have read and accept the %s, %s and %s, and I confirm I act as a business.'),
          '<a href="/legal?doc=terms" target="_blank" class="acc">'.t('Terms of Service').'</a>',
          '<a href="/legal?doc=privacy" target="_blank" class="acc">'.t('Privacy Policy').'</a>',
          '<a href="/legal?doc=payments" target="_blank" class="acc">'.t('Payments &amp; Escrow').'</a>') ?></span>
      </label>
      <button class="btn btn-p" type="submit" style="margin-top:14px" id="placeBtn"><?= t('Place order request') ?></button>
      <span class="hint" style="margin-left:12px" id="placeHint"><?= t('No payment now — we confirm availability, then send your invoice.') ?></span>
    </form>
  </div>

  <?php endif; ?>
</div>

<?php if(!$placed): ?>
<script>
var ESCROW_MAP = <?= json_encode($escrowMap, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
var ESCROW_FEE_RATE = <?= json_encode((float)VESTRA_ESCROW_FEE_BUYER) ?>;
var ESCROW_MAX = <?= json_encode((float)VESTRA_ESCROW_MAX) ?>;
var PAY_LBL = {
  escrowBtn: <?= json_encode(t('Pay securely →')) ?>,
  escrowHint: <?= json_encode(t('You pay now by card; funds are held in escrow until you confirm delivery.')) ?>,
  bankBtn: <?= json_encode(t('Place order request')) ?>,
  bankHint: <?= json_encode(t('No payment now — we confirm availability, then send your invoice.')) ?>,
  escrowSeller: <?= json_encode(t('Available when your whole cart is from one verified seller.')) ?>,
  /* Sepetteki butun tutarlar EUR basiliyor (eur() her zaman € yaziyor), sinir da
     EUR uzerinden sinaniyor -- burada goruntuleme para birimine cevirmek, sinirla
     ekrandaki rakami farkli birimlere dusururdu. */
  escrowMax: <?= json_encode(sprintf(t('Card escrow accepts orders up to %s. Larger orders are paid by bank transfer.'), '€'.number_format((float)VESTRA_ESCROW_MAX, 2))) ?>
};
function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }
/* Escrow is offered only when EVERY cart item maps to ONE verified (Connect-ready)
   seller — a direct charge is per connected account. Otherwise force bank transfer. */
/* Uygunsuzluk sebebini DONDURUYOR, sadece true/false degil: "tek saticidan
   olmali" ile "tutar sinirin ustunde" ayni kilidi gosterse de alicinin yapacagi
   sey farkli, ve tek bir cumle onu "ne yapmaliyim" sorusuyla birakiyordu. */
function escrowBlockedBy(c, net){
  if(!c.length) return 'empty';
  var sid=null;
  for(var i=0;i<c.length;i++){
    var s=ESCROW_MAP[c[i].id];
    if(!s) return 'seller';         // item has no escrow-ready seller
    if(sid===null) sid=s; else if(sid!==s) return 'seller'; // mixed sellers
  }
  /* Olcu SIPARIS tutari (kupon sonrasi mal bedeli), koruma ucreti dahil degil --
     bkz. VESTRA_ESCROW_MAX. Tam sinirdaki siparis kayan nokta yuzunden
     dusmesin diye kucuk bir tolerans var. */
  if(net > ESCROW_MAX + 0.005) return 'max';
  return '';
}
function syncPay(c, net){
  var opt=document.getElementById('payEscrowOpt'), rEsc=document.getElementById('payEscrow'),
      rBank=document.getElementById('payBank'), lock=document.getElementById('escrowLock');
  if(!opt) return;
  var why=escrowBlockedBy(c, net||0), ok=(why==='');
  rEsc.disabled=!ok;
  opt.classList.toggle('disabled',!ok);
  if(lock){
    lock.style.display=ok?'none':'block';
    if(why==='max') lock.textContent=PAY_LBL.escrowMax;
    else if(why==='seller') lock.textContent=PAY_LBL.escrowSeller;
  }
  if(!ok && rEsc.checked){ rBank.checked=true; }
  var esc=rEsc.checked;
  var btn=document.getElementById('placeBtn'), hint=document.getElementById('placeHint');
  if(btn)  btn.textContent = esc?PAY_LBL.escrowBtn:PAY_LBL.bankBtn;
  if(hint) hint.textContent= esc?PAY_LBL.escrowHint:PAY_LBL.bankHint;
}
function render(){
  var c=VCart.all();
  document.getElementById('empty').style.display = c.length?'none':'block';
  document.getElementById('filled').style.display = c.length?'block':'none';
  var rows='', sub=0;
  c.forEach(function(x){
    var line=x.qty*x.unit; sub+=line;
    var cols = (x.colors && x.colors.length) ? ' · '+x.colors.map(esc).join(', ') : '';
    rows+='<tr><td><b>'+esc(x.brand)+'</b> — '+esc(x.name)+'<div class="hint">SKU '+esc(x.sku)+cols+'</div></td>'+
      '<td>'+Number(x.qty)+' '+esc(x.unitLabel)+'</td><td class="r">'+eur(x.unit)+'</td><td class="r">'+eur(line)+'</td>'+
      '<td class="x" data-remove-id="'+esc(x.id)+'" title="<?= htmlspecialchars(t('Remove')) ?>">✕</td></tr>';
  });
  document.getElementById('rows').innerHTML=rows;
  /* The voucher comes off the goods value FIRST, so the escrow fee is charged on what the
     buyer actually pays rather than on the pre-discount figure. order.php applies the same
     order server-side; this is only the preview. Net is computed BEFORE syncPay because
     the escrow ceiling is a test on what the card is charged. */
  var disc = VOUCHER.discount>0 ? Math.min(VOUCHER.discount, sub) : 0;
  var net  = sub - disc;
  syncPay(c, net); // enable/disable escrow (may force bank) before pricing the fee
  var escR=document.getElementById('payEscrow'); var isEsc=escR&&escR.checked;
  var efee=isEsc?net*ESCROW_FEE_RATE:0;
  var feeLine=document.getElementById('escrowFeeLine'); if(feeLine) feeLine.style.display=isEsc?'':'none';
  var efeeEl=document.getElementById('escrowFee'); if(efeeEl) efeeEl.textContent=eur(efee);
  var vLine=document.getElementById('voucherLine');
  if(vLine){
    vLine.style.display = disc>0 ? '' : 'none';
    document.getElementById('voucherCodeLbl').textContent = disc>0 ? ('('+VOUCHER.code+')') : '';
    document.getElementById('voucherAmt').textContent = '−'+eur(disc);
  }
  document.getElementById('sub').textContent=eur(sub);
  var bfeeEl=document.getElementById('bfee'); if(bfeeEl) bfeeEl.textContent=eur(net*<?=VESTRA_FEE_BUYER?>);
  document.getElementById('grand').textContent=eur(net+efee);
  document.getElementById('cartField').value=JSON.stringify(c);
}

/* Voucher preview. Everything here is cosmetic: order.php revalidates the code against the
   signed-in account and recomputes the discount, so a tampered VOUCHER object buys nothing. */
var VOUCHER={code:'',discount:0};
function cartSubtotal(){ var s=0; VCart.all().forEach(function(x){ s+=x.qty*x.unit; }); return s; }
function voucherApply(){
  var inp=document.getElementById('voucherInput'), msg=document.getElementById('voucherMsg');
  if(!inp) return;
  var code=(inp.value||'').trim().toUpperCase();
  inp.value=code;
  if(!code){ VOUCHER={code:'',discount:0}; msg.textContent=''; msg.style.color=''; render(); return; }
  msg.style.color=''; msg.textContent='…';
  var body='code='+encodeURIComponent(code)+'&subtotal='+encodeURIComponent(cartSubtotal());
  fetch('/voucher-check',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if(d && d.ok){ VOUCHER={code:d.code,discount:Number(d.discount)||0}; msg.style.color='var(--acc)'; }
      else { VOUCHER={code:'',discount:0}; msg.style.color='#d9534f'; }
      msg.textContent=(d && d.msg)||'';
      render();
    })
    .catch(function(){ msg.style.color='#d9534f'; msg.textContent=<?= json_encode(t('Could not check the code right now — it will still be applied to your order if valid.')) ?>; });
}
document.getElementById('rows').addEventListener('click', function(e){
  var id = e.target && e.target.dataset ? e.target.dataset.removeId : null;
  if (id) { VCart.remove(id); render(); }
});
['payEscrow','payBank'].forEach(function(id){
  var el=document.getElementById(id); if(el) el.addEventListener('change', function(){ render(); });
});
document.getElementById('orderForm') && document.getElementById('orderForm').addEventListener('submit',function(){
  document.getElementById('cartField').value=JSON.stringify(VCart.all());
});
(function(){
  var b=document.getElementById('voucherBtn'), i=document.getElementById('voucherInput');
  if(b) b.addEventListener('click', voucherApply);
  /* Enter inside the code box must check the code, not submit the whole order — an order
     placed by pressing Enter after typing a voucher would skip the preview entirely. */
  if(i) i.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); voucherApply(); } });
})();
/* VCart is defined in foot.php which loads after this block — use DOMContentLoaded */
document.addEventListener('DOMContentLoaded', function(){
  render();
  /* A code arriving as ?voucher=… (the link in the welcome mail) checks itself on load. */
  var i=document.getElementById('voucherInput'); if(i && i.value.trim()) voucherApply();
});
</script>
<?php endif; ?>
<?php require __DIR__.'/inc/foot.php';
