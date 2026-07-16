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
    foreach (vestra_products() as $p) {
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
      <label style="display:flex;gap:9px;align-items:flex-start;margin:14px 0 4px;max-width:680px;font-size:13px;color:var(--mut);cursor:pointer">
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
var PAY_LBL = {
  escrowBtn: <?= json_encode(t('Pay securely →')) ?>,
  escrowHint: <?= json_encode(t('You pay now by card; funds are held in escrow until you confirm delivery.')) ?>,
  bankBtn: <?= json_encode(t('Place order request')) ?>,
  bankHint: <?= json_encode(t('No payment now — we confirm availability, then send your invoice.')) ?>
};
function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }
/* Escrow is offered only when EVERY cart item maps to ONE verified (Connect-ready)
   seller — a direct charge is per connected account. Otherwise force bank transfer. */
function escrowEligible(c){
  if(!c.length) return false;
  var sid=null;
  for(var i=0;i<c.length;i++){
    var s=ESCROW_MAP[c[i].id];
    if(!s) return false;            // item has no escrow-ready seller
    if(sid===null) sid=s; else if(sid!==s) return false; // mixed sellers
  }
  return true;
}
function syncPay(c){
  var opt=document.getElementById('payEscrowOpt'), rEsc=document.getElementById('payEscrow'),
      rBank=document.getElementById('payBank'), lock=document.getElementById('escrowLock');
  if(!opt) return;
  var ok=escrowEligible(c);
  rEsc.disabled=!ok;
  opt.classList.toggle('disabled',!ok);
  if(lock) lock.style.display=ok?'none':'block';
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
  syncPay(c); // enable/disable escrow (may force bank) before pricing the fee
  var escR=document.getElementById('payEscrow'); var isEsc=escR&&escR.checked;
  var efee=isEsc?sub*ESCROW_FEE_RATE:0;
  var feeLine=document.getElementById('escrowFeeLine'); if(feeLine) feeLine.style.display=isEsc?'':'none';
  var efeeEl=document.getElementById('escrowFee'); if(efeeEl) efeeEl.textContent=eur(efee);
  document.getElementById('sub').textContent=eur(sub);
  var bfeeEl=document.getElementById('bfee'); if(bfeeEl) bfeeEl.textContent=eur(sub*<?=VESTRA_FEE_BUYER?>);
  document.getElementById('grand').textContent=eur(sub+efee);
  document.getElementById('cartField').value=JSON.stringify(c);
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
/* VCart is defined in foot.php which loads after this block — use DOMContentLoaded */
document.addEventListener('DOMContentLoaded', function(){ render(); });
</script>
<?php endif; ?>
<?php require __DIR__.'/inc/foot.php';
