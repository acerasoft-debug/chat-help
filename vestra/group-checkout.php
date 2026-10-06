<?php
/**
 * VESTRA — group-buy deposit checkout.
 * POST /group-checkout  (id, qty, company, name, email, country, consent)
 *
 * Creates a Stripe Checkout Session for the pool's deposit percentage of
 * qty × unlocked price, and writes the commitment as `pending`. The commitment
 * only starts counting toward the pool target once Stripe confirms payment —
 * see group-confirm.php (return path) and inc/pools.php (why `pending` does not
 * count).
 *
 * Pools without a deposit percentage still use the old, free /group-join path;
 * this endpoint refuses them so a mis-linked form can never charge for a pool
 * that was never meant to take money.
 */
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/pools.php';
require_once __DIR__ . '/inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /groups'); exit; }

$id      = trim((string) ($_POST['id'] ?? ''));
$backUrl = '/group?id=' . rawurlencode($id);

if (!empty($_POST['website'])) { header('Location: ' . $backUrl); exit; } // honeypot

$p = $id !== '' ? vestra_group_pool($id) : null;
if (!$p || !pool_has_deposit($p)) { header('Location: /groups'); exit; }
if (($p['_status'] ?? '') !== 'open') { header('Location: ' . $backUrl); exit; }

$user = auth_user();
if (!$user) { header('Location: /login?back=' . urlencode($backUrl)); exit; }

if (empty($_POST['consent'])) { header('Location: ' . $backUrl . '&error=consent'); exit; }

$one     = fn($s) => trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', (string) $s)));
$company = $one($_POST['company'] ?? '');
$name    = $one($_POST['name'] ?? '');
$email   = trim((string) ($_POST['email'] ?? ''));
$country = $one($_POST['country'] ?? '');
if ($company === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $backUrl . '&error=fields'); exit;
}

/* Quantity is clamped to the pool's per-buyer minimum rather than rejected — the same
   forgiving behaviour group-join.php has always had for a too-small number. The pool
   minimum, not the product's moq: this pool asks 104 against a catalogue moq of 20,
   and charging a deposit on 20 units would undercharge every tampered form post. */
$qty = max(vestra_group_min_qty($p), (int) ($_POST['qty'] ?? 0));

/* Colour choice, validated SERVER-side. The page enforces the minimum in JS, but
   a deposit is real money: a tampered or JS-less post must not buy its way into
   the pool without the colour spread the pool is sold on. Only colours the
   listing actually offers count — an unknown value is dropped, not trusted. */
$minCol = vestra_group_min_colors($p);
$offered = array_values(array_filter(array_map('strval', (array) ($p['colors'] ?? []))));
$colors = [];
foreach ((array) ($_POST['colors'] ?? []) as $c) {
    $c = (string) $c;
    if (in_array($c, $offered, true) && !in_array($c, $colors, true)) $colors[] = $c;
}
if ($minCol > 0 && count($colors) < $minCol) {
    header('Location: ' . $backUrl . '&error=colors'); exit;
}

/* Unit price and deposit % are frozen onto the commitment: editing group_price
   or group_deposit_pct later must never change what an already-paying buyer
   owes, and the balance invoice is computed from these frozen numbers. */
$unit = (float) $p['_gprice'];
$pct  = pool_deposit_pct($p);
[$total, $deposit, $balance] = pool_split($p, $qty, $unit, $pct);

if ($deposit <= 0) { header('Location: ' . $backUrl . '&error=amount'); exit; }

if (!stripe_available()) { header('Location: ' . $backUrl . '&error=notready'); exit; }

$ref = 'GRP-' . strtoupper(bin2hex(random_bytes(4)));

pool_commit_save([
    'ref'         => $ref,
    'pool_id'     => $p['id'],
    'brand'       => (string) ($p['brand'] ?? ''),
    'product'     => (string) ($p['name'] ?? ''),
    'sku'         => (string) ($p['sku'] ?? ''),
    'buyer_id'    => $user['id'] ?? '',
    'company'     => $company,
    'name'        => $name,
    'email'       => $email,
    'country'     => $country,
    'qty'         => $qty,
    'colors'      => $colors,
    'unit'        => (string) ($p['unit'] ?? 'pc'),
    'unit_price'  => $unit,
    'total'       => $total,
    'deposit_pct' => $pct,
    'deposit'     => $deposit,
    'balance'     => $balance,
    'currency'    => 'eur',
    'status'      => 'pending',
    'consent'     => 'yes',
    'terms_version' => VESTRA_TERMS_VERSION,
    'created'     => date('c'),
]);

$pctLabel  = rtrim(rtrim(number_format($pct, 1), '0'), '.');
$lineName  = 'Group buy deposit — ' . trim(($p['brand'] ?? '') . ' ' . ($p['name'] ?? ''));
$lineDesc  = sprintf(
    '%s%% deposit on %d %s @ €%s (total €%s).%s Balance €%s due within %d days of the pool closing. Refunded in full if the pool does not reach its target.',
    $pctLabel, $qty, (string) ($p['unit'] ?? 'pc'), number_format($unit, 2),
    number_format($total, 2),
    $colors ? ' Colours: ' . implode(', ', $colors) . '.' : '',
    number_format($balance, 2), pool_balance_days($p)
);

$successUrl = 'https://vestrasales.com/group-confirm?ref=' . rawurlencode($ref) . '&paid=1';
$cancelUrl  = 'https://vestrasales.com' . $backUrl . '&error=cancelled';

try {
    /* Charged on VESTRA's own platform account on purpose, unlike a sample or a
       wholesale order: a deposit is refundable pool money that VESTRA may have to
       hand back in full, so it must not settle into a seller's connected balance
       where a refund would depend on that seller's funds. */
    $customerId = stripe_ensure_customer($user);

    $session = stripe_api('POST', '/v1/checkout/sessions', [
        'mode'                => 'payment',
        'customer'            => $customerId,
        'client_reference_id' => $ref,
        'line_items'          => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => (int) round($deposit * 100),
                'product_data' => ['name' => $lineName, 'description' => $lineDesc],
            ],
        ]],
        'metadata'            => ['kind' => 'pool_deposit', 'pool_ref' => $ref, 'pool_id' => $p['id']],
        'payment_intent_data' => ['metadata' => ['kind' => 'pool_deposit', 'pool_ref' => $ref, 'pool_id' => $p['id']]],
        'success_url'         => $successUrl,
        'cancel_url'          => $cancelUrl,
    ]);

    pool_commit_update($ref, ['session_id' => $session->id ?? '']);

    header('Location: ' . $session->url);
    exit;

} catch (\Throwable $e) {
    error_log('[VESTRA Pool] deposit checkout error ' . $ref . ': ' . $e->getMessage());
    header('Location: ' . $backUrl . '&error=1');
    exit;
}
