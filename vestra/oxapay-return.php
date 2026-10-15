<?php
/**
 * VESTRA — GET /orders/{ref}/payment-return  (oxapay.return)
 * OxaPay'den dönüş: teşekkür sayfası. Durum webhook'un yazdığı kayıttan okunur (burada OxaPay'e
 * çıkılmaz): paid → ödendi; confirming → onaylanıyor; pending → henüz görülmedi; expired/failed →
 * yeniden ödeme düğmesi. Erişim order-confirm ile aynı (oturumdaki ref, sahibi, admin).
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/oxapay.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['ref'] ?? ''));
if ($ref === '') { header('Location: /shop'); exit; }
$row = oxapay_order_row($ref);
if (!$row) { header('Location: /shop'); exit; }
$me = auth_user();
$allowed = !empty($_SESSION['order_refs'][$ref]);
if (!$allowed && $me && strtolower((string)($row['email'] ?? '')) === strtolower((string)($me['email'] ?? ''))) $allowed = true;
if (!$allowed && !empty($_SESSION['vadmin'])) $allowed = true;
if (!$allowed) { header('Location: /login?back='.urlencode('/orders/'.$ref.'/payment-return')); exit; }

$entry = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
$ps = (string)($entry['payment_status'] ?? '');
$settled = vestra_order_payment_settled($ref, $entry)['settled'] || $ps === 'paid';
$state = $settled ? 'paid' : (in_array($ps, ['confirming'], true) ? 'confirming' : (in_array($ps, ['expired', 'failed', 'refunded'], true) ? 'failed' : 'pending'));
$canRetry = !$settled && oxapay_enabled() && $me && vestra_order_crypto_block($row) === '';

$PAGE = t('Payment'); $NAV = 'shop'; require __DIR__.'/inc/head.php';
?>
<div class="wrap" style="max-width:760px">
  <div style="text-align:center;padding:44px 16px 8px">
    <div style="font-size:52px;line-height:1;margin-bottom:14px"><?= $state === 'paid' ? '✓' : ($state === 'failed' ? '✕' : '⏳') ?></div>
    <h1 style="margin:0 0 8px"><?php
      echo $state === 'paid' ? t('Payment received — thank you!') : ($state === 'confirming' ? t('Payment is being confirmed') : ($state === 'failed' ? t('Payment not completed') : t('Waiting for your payment')));
    ?></h1>
    <p style="color:var(--mut);margin:0"><?= t('Reference:') ?> <b style="color:var(--acc)"><?= htmlspecialchars($ref) ?></b></p>
  </div>

  <?php if ($state === 'paid'): ?>
  <div class="banner ok" style="margin:22px 0;font-size:13.5px">
    ₿ <b><?= t('Your crypto payment is confirmed.') ?></b>
    <?= t('The seller is preparing your shipment; you will receive tracking by email once it ships. Follow everything under My orders.') ?>
  </div>
  <?php elseif ($state === 'confirming'): ?>
  <div class="banner info" style="margin:22px 0;font-size:13.5px">
    ⏳ <b><?= t('Your transaction has been seen on the network and is being confirmed.') ?></b>
    <?= t('This usually takes a few minutes. We email you as soon as it is final — no need to pay again.') ?>
  </div>
  <?php elseif ($state === 'failed'): ?>
  <div class="banner" style="margin:22px 0;font-size:13.5px;background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">
    <b><?= t('The payment window closed before a payment arrived.') ?></b>
    <?= t('Nothing was charged. You can open a new crypto payment below, or pay by bank transfer against your invoice.') ?>
  </div>
  <?php else: ?>
  <div class="banner info" style="margin:22px 0;font-size:13.5px">
    ⏳ <b><?= t('We have not seen your payment yet.') ?></b>
    <?= t('If you just paid, give the network a few minutes and refresh this page. If you left the payment page, you can reopen it below.') ?>
    <span class="hint" style="display:block;margin-top:6px"><?= t('No crypto wallet? Buy USDT or BTC on any exchange (Binance, Kraken, Coinbase, OKX…) and send the exact amount shown on the payment page — or pay by bank transfer against your invoice instead.') ?></span>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin:26px 0 50px">
    <?php if ($canRetry): ?>
      <form method="post" action="/orders/<?= htmlspecialchars($ref) ?>/pay/crypto" style="display:inline">
        <button class="btn btn-p" type="submit">₿ <?= $state === 'failed' ? t('Pay with crypto again') : t('Reopen crypto payment') ?></button>
      </form>
    <?php endif; ?>
    <a class="btn <?= $canRetry ? 'btn-o' : 'btn-p' ?>" href="/buyer?tab=orders"><?= t('Track in My orders →') ?></a>
    <a class="btn btn-o" href="/order-confirm?ref=<?= htmlspecialchars($ref) ?>"><?= t('Order details') ?></a>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
