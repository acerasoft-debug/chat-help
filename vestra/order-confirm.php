<?php
/**
 * VESTRA — order confirmation ("thank you") page.
 * Shown right after checkout: per-seller payment instructions (bank details
 * as they appear on the freshly issued invoice) + invoice PDF downloads.
 *
 * Access: the browser session that just placed the order (works for guest
 * checkout — order.php stamps $_SESSION['order_refs']), a logged-in buyer
 * whose email matches the order, or an admin session.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/invoice.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['ref'] ?? '');
if ($ref === '') { header('Location: /shop'); exit; }

$orderRow = null;
foreach (vestra_read_csv('orders.csv') as $row) {
    if (($row['ref'] ?? '') === $ref) { $orderRow = $row; break; }
}
if (!$orderRow) { header('Location: /shop'); exit; }

$me = auth_user();
$allowed = !empty($_SESSION['order_refs'][$ref]);
if (!$allowed && $me && strtolower($orderRow['email'] ?? '') === strtolower($me['email'] ?? '')) $allowed = true;
if (!$allowed && !empty($_SESSION['vadmin'])) $allowed = true;
if (!$allowed) { header('Location: /login?back='.urlencode('/order-confirm?ref='.$ref)); exit; }

$ld = vestra_order_lines($orderRow);
$lines = $ld['lines'];

/* Kripto (OxaPay, 14 Eki 2026): odeme durumu kayittan; odenmemisse "kripto ile ode" dugmesi
   (yaptirimli ulke/bolge ve anahtarsiz kurulumda cizilmez). ?crypto=error|blocked bandi. */
require_once __DIR__.'/inc/oxapay.php';
$oxEntry  = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
$oxMethod = (string)($oxEntry['payment_method'] ?? '');
$oxPaid   = ($oxEntry['payment_status'] ?? '') === 'paid';
$oxChosen = $oxMethod === 'oxapay' || str_contains((string)($orderRow['notes'] ?? ''), 'Payment: Crypto (OxaPay).');
$oxCanPay = !$oxPaid && oxapay_enabled() && $me && strtolower((string)($orderRow['email'] ?? '')) === strtolower((string)($me['email'] ?? ''))
            && !vestra_order_payment_settled($ref, $oxEntry)['settled'] && (($oxEntry['status'] ?? '') !== 'cancelled') && vestra_order_crypto_block($orderRow) === '';
$oxFlag   = (string)($_GET['crypto'] ?? '');

/* Escrow: if the buyer just returned from Stripe (paid=1) but the webhook hasn't
   marked the order held yet — e.g. connected-account events aren't enabled — verify
   the Checkout Session directly and fulfil it here. Makes escrow reliable without
   depending on webhook delivery. */
require_once __DIR__.'/inc/escrow.php';
$escrow = escrow_get($ref);
if ($escrow && ($escrow['status'] ?? '') === 'pending' && isset($_GET['paid'])) {
    require_once __DIR__.'/inc/stripe.php';
    try {
        $sess = stripe_api('GET', '/v1/checkout/sessions/'.$escrow['session_id'], [], $escrow['acct_id']);
        if (($sess->payment_status ?? '') === 'paid') {
            $pi  = is_string($sess->payment_intent ?? null) ? $sess->payment_intent : ($sess->payment_intent->id ?? '');
            $rec = escrow_mark_paid($ref, $pi);
            if ($rec) { escrow_fulfill($rec); $escrow = escrow_get($ref); }
        }
    } catch (\Throwable $e) { error_log('[VESTRA Escrow] confirm reconcile failed '.$ref.': '.$e->getMessage()); }
}
$isEscrow = $escrow && in_array($escrow['status'] ?? '', ['held','released','refunded'], true);

/* Group goods per seller — mirrors the per-seller invoice grouping in order.php */
$bySeller = [];
foreach ($lines as $l) { $bySeller[$l['seller_uid'] ?: 'vestra'][] = $l; }

/* Invoice list (one per seller), keyed by seller for the cards below */
$invByKey = [];
foreach (vestra_invoices_for_ref($ref) as $iv) { $invByKey[$iv['seller_key']] = $iv; }

$accounts = auth_accounts();
$accById = [];
foreach ($accounts as $a) { $accById[$a['id'] ?? ''] = $a; }

$PAGE = t('Order received'); $NAV = 'shop'; require __DIR__.'/inc/head.php';
?>
<div class="wrap" style="max-width:860px">

  <div style="text-align:center;padding:44px 16px 8px">
    <div style="font-size:52px;line-height:1;margin-bottom:14px">✓</div>
    <h1 style="margin:0 0 8px"><?= t('Order received') ?></h1>
    <p style="color:var(--mut);margin:0"><?= t('Reference:') ?> <b style="color:var(--acc)"><?= htmlspecialchars($ref) ?></b>
      · <?= t('A confirmation email is on its way to') ?> <b><?= htmlspecialchars($orderRow['email'] ?? '') ?></b></p>
  </div>

  <?php if ($oxFlag === 'blocked'): ?>
  <div class="banner" style="margin:22px 0;font-size:13.5px;background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">
    <?= t('Crypto payment is not available for this delivery or billing country. Please pay by bank transfer against your invoice.') ?>
  </div>
  <?php elseif ($oxFlag === 'error' && $oxCanPay): ?>
  <div class="banner" style="margin:22px 0;font-size:13.5px;background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">
    <?= t('The crypto payment page could not be opened right now. Your order is saved — try again below, or pay by bank transfer.') ?>
  </div>
  <?php endif; ?>

  <?php if ($oxPaid): ?>
  <div class="banner ok" style="margin:22px 0;font-size:13.5px">
    ₿ <b><?= t('Paid with crypto — thank you.') ?></b>
    <?= t('The seller is preparing your shipment; you will receive tracking by email once it ships. Follow everything under My orders.') ?>
  </div>
  <?php elseif ($oxCanPay && $oxChosen): ?>
  <div class="banner info" style="margin:22px 0;font-size:13.5px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <span>₿ <b><?= t('Your order is saved. Complete the crypto payment to have it shipped.') ?></b>
      <span class="hint"><?= t('USDT, BTC, ETH and more via OxaPay. You pay VESTRA directly — never the seller; VESTRA releases the payment to the seller only after your goods are dispatched.') ?>
      <?= t('No crypto wallet? Buy USDT or BTC on any exchange (Binance, Kraken, Coinbase, OKX…) and send the exact amount shown on the payment page — or pay by bank transfer against your invoice instead.') ?></span></span>
    <form method="post" action="/orders/<?= htmlspecialchars($ref) ?>/pay/crypto" style="display:inline;flex:none">
      <button class="btn btn-p" type="submit">₿ <?= t('Pay with crypto →') ?></button>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($isEscrow): ?>
  <div class="banner ok" style="margin:22px 0;font-size:13.5px">
    🛡️ <b><?= t('Paid — your money is protected in escrow.') ?></b>
    <?= t('The seller ships your goods; the funds are released to them only after you confirm delivery under My orders. If anything goes wrong before then, open a dispute for a full refund.') ?>
  </div>
  <?php elseif (!$oxPaid): ?>
  <div class="banner info" style="margin:22px 0;font-size:13.5px">
    ⏳ <b><?= t('Stock is being confirmed.') ?></b>
    <?= t("Within 24 hours of your purchase, your invoice — with the seller's bank details — is emailed to you and added to your account. Payment is by bank transfer against that invoice; goods ship after payment arrives. Track everything under My orders.") ?>
  </div>
  <?php endif; ?>

  <?php foreach ($bySeller as $sid => $sellerLines):
    $acc   = $sid !== 'vestra' ? ($accById[$sid] ?? null) : null;
    $label = $acc ? ($acc['company'] ?: ($acc['name'] ?: t('Seller'))) : 'VESTRA';
    $goods = round(array_sum(array_column($sellerLines, 'line')), 2);
    $iv    = $invByKey[$sid] ?? null;
    $hasBank = $acc && !empty($acc['bank_iban']);
  ?>
  <div class="panelcard" style="margin-bottom:18px">
    <div class="pcfhead" style="flex-wrap:wrap;gap:10px">
      <h3 style="margin:0"><?= t('Seller') ?>: <?= htmlspecialchars($label) ?></h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="status offers"><?= eur($goods) ?></span>
        <?php if ($iv): ?>
          <a class="btn btn-p btn-sm" href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener">📄 <?= t('Download invoice') ?> <?= htmlspecialchars(vestra_invoice_link_label($iv)) ?></a>
        <?php else: ?>
          <span class="status offers" style="opacity:.9">⏳ <?= t('Invoice within the day') ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="padding:16px 18px">
      <?php foreach ($sellerLines as $l): ?>
        <div style="display:flex;justify-content:space-between;gap:12px;font-size:13.5px;padding:4px 0;border-bottom:1px dashed var(--line)">
          <span><?= (int)$l['qty'] ?>× <b><?= htmlspecialchars($l['brand']) ?></b> <?= htmlspecialchars($l['name']) ?>
            <span class="hint">(<?= htmlspecialchars($l['sku']) ?>)</span>
            <?= $l['colors'] ? '<span class="hint"> · '.htmlspecialchars(implode(', ', $l['colors'])).'</span>' : '' ?></span>
          <span style="white-space:nowrap"><?= eur($l['line']) ?></span>
        </div>
      <?php endforeach; ?>

      <?php if ($isEscrow): ?>
      <div style="margin-top:14px;background:rgba(122,214,160,.06);border:1px solid rgba(122,214,160,.3);border-radius:10px;padding:14px 16px">
        <div style="font-weight:600;color:var(--ok)">🛡️ <?= t('Paid via secure escrow') ?> — <?= eur($goods) ?></div>
        <div class="hint" style="margin-top:6px"><?= t('These funds are held safely. Confirm delivery under My orders to release them to the seller.') ?></div>
      </div>
      <?php elseif ($hasBank): ?>
      <div style="margin-top:14px;background:rgba(201,168,106,.05);border:1px solid rgba(201,168,106,.25);border-radius:10px;padding:14px 16px">
        <div style="font-weight:600;margin-bottom:8px">🏦 <?= t('Payment details — bank transfer') ?></div>
        <table style="font-size:13px;border-collapse:collapse">
          <?php if (!empty($acc['bank_name'])): ?><tr><td style="color:var(--mut);padding:2px 14px 2px 0"><?= t('Bank') ?></td><td><?= htmlspecialchars($acc['bank_name']) ?></td></tr><?php endif; ?>
          <?php if (!empty($acc['bank_holder'])): ?><tr><td style="color:var(--mut);padding:2px 14px 2px 0"><?= t('Account holder') ?></td><td><?= htmlspecialchars($acc['bank_holder']) ?></td></tr><?php endif; ?>
          <tr><td style="color:var(--mut);padding:2px 14px 2px 0">IBAN</td><td style="font-family:monospace"><?= htmlspecialchars($acc['bank_iban']) ?></td></tr>
          <?php if (!empty($acc['bank_bic'])): ?><tr><td style="color:var(--mut);padding:2px 14px 2px 0">BIC / SWIFT</td><td style="font-family:monospace"><?= htmlspecialchars($acc['bank_bic']) ?></td></tr><?php endif; ?>
          <tr><td style="color:var(--mut);padding:2px 14px 2px 0"><?= t('Amount') ?></td><td><b><?= eur($goods) ?></b></td></tr>
          <tr><td style="color:var(--mut);padding:2px 14px 2px 0"><?= t('Reference') ?></td><td><b><?= htmlspecialchars(($iv['no'] ?? '').' / '.$ref) ?></b></td></tr>
        </table>
        <div class="hint" style="margin-top:8px"><?= t('Payment due within 14 days. Goods ship after the transfer arrives.') ?></div>
      </div>
      <?php else: ?>
      <div class="hint" style="margin-top:14px">
        ⏳ <?= t("The seller's bank details will be confirmed with your proforma invoice by email before payment.") ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin:26px 0 50px">
    <?php if ($oxCanPay && !$oxChosen): ?>
      <form method="post" action="/orders/<?= htmlspecialchars($ref) ?>/pay/crypto" style="display:inline">
        <button class="btn btn-o" type="submit">₿ <?= t('Pay with crypto instead') ?></button>
      </form>
    <?php endif; ?>
    <?php if ($me): ?>
      <a class="btn btn-p" href="/buyer?tab=orders"><?= t('Track in My orders →') ?></a>
    <?php else: ?>
      <a class="btn btn-p" href="/register?type=buyer"><?= t('Create a free account to track this order') ?></a>
    <?php endif; ?>
    <a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a>
  </div>
</div>

<script>
/* The order is placed — empty the cart so it doesn't reappear on the next visit. */
document.addEventListener('DOMContentLoaded', function(){ if (window.VCart) VCart.clear(); });
</script>
<?php require __DIR__.'/inc/foot.php';
