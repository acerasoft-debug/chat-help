<?php
/**
 * VESTRA — group-buy deposit confirmation ("thank you") page.
 *
 * Mirrors sample-confirm.php: if the buyer returns from Stripe with ?paid=1 but
 * no webhook has landed yet, the Checkout Session is verified here and the
 * commitment marked paid on the spot — the pool must never depend on webhook
 * delivery timing to show a buyer as counted.
 *
 * Marking paid is keyed on the commitment ref and guarded by its status, so a
 * refresh (or a webhook arriving afterwards) cannot count the same deposit twice.
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/pools.php';
require_once __DIR__ . '/inc/notify.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_GET['ref'] ?? ''));
$rec = $ref !== '' ? pool_commit_get($ref) : null;
if (!$rec) { header('Location: /groups'); exit; }

$me      = auth_user();
$allowed = $me && ($rec['buyer_id'] ?? '') === ($me['id'] ?? '');
if (!$allowed && !empty($_SESSION['vadmin'])) $allowed = true;
if (!$allowed) { header('Location: /login?back=' . urlencode('/group-confirm?ref=' . $ref)); exit; }

if (($rec['status'] ?? '') === 'pending' && isset($_GET['paid'])) {
    require_once __DIR__ . '/inc/stripe.php';
    try {
        $sess = stripe_api('GET', '/v1/checkout/sessions/' . (string) ($rec['session_id'] ?? ''));
        if (($sess->payment_status ?? '') === 'paid') {
            $pi = is_string($sess->payment_intent ?? null) ? $sess->payment_intent : ($sess->payment_intent->id ?? '');
            $rec = pool_commit_update($ref, [
                'status'         => 'paid',
                'payment_intent' => $pi,
                'paid_at'        => date('c'),
            ]) ?? $rec;

            $p     = vestra_group_pool($rec['pool_id'] ?? '');
            $after = $p ? $p : null;
            $body  = "New VESTRA pool deposit {$ref}\n\n"
                   . "Pool: {$rec['brand']} {$rec['product']} (SKU {$rec['sku']})\n"
                   . "Buyer: {$rec['name']} / {$rec['company']} <{$rec['email']}>  " . ($rec['country'] ?: '') . "\n"
                   . "Committed: {$rec['qty']} {$rec['unit']} @ €" . number_format((float) $rec['unit_price'], 2)
                   . " = €" . number_format((float) $rec['total'], 2) . "\n"
                   . (!empty($rec['colors']) ? "Colours: " . implode(', ', (array) $rec['colors']) . "\n" : '')
                   . "Deposit paid: €" . number_format((float) $rec['deposit'], 2)
                   . "  ·  Balance due later: €" . number_format((float) $rec['balance'], 2) . "\n"
                   . ($after ? "Pool now: {$after['_committed']} / {$after['_target']} {$rec['unit']} ({$after['_pct']}%)"
                              . ($after['_committed'] >= $after['_target'] ? "  ← TARGET REACHED ✓" : "") . "\n" : '');
            vestra_notify("Pool deposit {$ref} — {$rec['brand']} {$rec['product']}", $body, $rec['email']);

            $days = $p ? pool_balance_days($p) : 7;
            $msg  = "Hello {$rec['name']},\n\n"
                  . "Your deposit for the {$rec['brand']} {$rec['product']} group buy is confirmed.\n\n"
                  . "Reference: {$ref}\n"
                  . "Committed: {$rec['qty']} {$rec['unit']} at €" . number_format((float) $rec['unit_price'], 2) . " per {$rec['unit']}\n"
                  . (!empty($rec['colors']) ? "Colours: " . implode(', ', (array) $rec['colors']) . "\n" : '')
                  . "Order value: €" . number_format((float) $rec['total'], 2) . "\n"
                  . "Deposit paid today: €" . number_format((float) $rec['deposit'], 2) . "\n"
                  . "Balance to follow: €" . number_format((float) $rec['balance'], 2) . "\n\n"
                  . "What happens next\n"
                  . "· Your quantity now counts toward the pool target.\n"
                  . "· When the pool reaches its target we invoice the balance, payable within {$days} days.\n"
                  . "· If the pool does not reach its target, we extend it once. If it still falls short, "
                  . "your deposit is refunded in full — you pay nothing.\n\n"
                  . "— VESTRA · Acerasoft LLC";
            vestra_send_mail($rec['email'], "VESTRA — deposit confirmed ({$rec['brand']} {$rec['product']})", $msg);
        }
    } catch (\Throwable $e) {
        error_log('[VESTRA Pool] confirm reconcile failed ' . $ref . ': ' . $e->getMessage());
    }
}

$paid = in_array($rec['status'] ?? '', pool_counting_statuses(), true);
$pool = vestra_group_pool($rec['pool_id'] ?? '');
$PAGE = $paid ? t('Deposit confirmed') : t('Finishing up…');
$NAV  = 'groups';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px;margin:60px auto;text-align:center">
  <?php if ($paid): ?>
    <div style="font-size:48px">✅</div>
    <h1><?= t('Deposit confirmed') ?></h1>
    <p class="hint"><?= t('Reference:') ?> <b><?= htmlspecialchars($rec['ref']) ?></b></p>
    <p><?= htmlspecialchars(($rec['brand'] ?? '') . ' ' . ($rec['product'] ?? '')) ?></p>
    <p class="hint">
      <?= htmlspecialchars((string) $rec['qty']) ?> <?= htmlspecialchars((string) $rec['unit']) ?>
      · <?= t('Deposit paid') ?>: <b><?= eur($rec['deposit']) ?></b>
      · <?= t('Balance later') ?>: <b><?= eur($rec['balance']) ?></b>
    </p>
    <?php if ($pool): ?>
      <div class="gbar" style="height:13px;border-radius:8px;background:rgba(255,255,255,.09);overflow:hidden;margin:18px 0 6px">
        <i style="display:block;height:100%;width:<?= (int) $pool['_pct'] ?>%;background:linear-gradient(90deg,var(--acc),#e7cd92)"></i>
      </div>
      <p class="hint"><?= number_format($pool['_committed']) ?> / <?= number_format($pool['_target']) ?>
        <?= htmlspecialchars((string) $pool['unit']) ?> · <b style="color:var(--acc)"><?= (int) $pool['_pct'] ?>%</b></p>
    <?php endif; ?>
    <p><?= t('A confirmation email is on its way to') ?> <b><?= htmlspecialchars($rec['email']) ?></b>.</p>
    <a class="btn btn-o" href="/group?id=<?= rawurlencode((string) $rec['pool_id']) ?>" style="margin-top:14px"><?= t('Back to the pool') ?></a>
  <?php else: ?>
    <div style="font-size:48px">⏳</div>
    <h1><?= t('Finishing up…') ?></h1>
    <p class="hint"><?= t('Your payment is still being confirmed. This page updates automatically.') ?></p>
    <script>setTimeout(function(){ location.reload(); }, 4000);</script>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/foot.php';
