<?php
/**
 * VESTRA — Sample order Checkout session creator ("Muster Bestellung").
 * POST /sample-checkout  (body: id=<product id>, note=<optional size/note>)
 * Single-unit purchase at $p['sample_price'], charged on VESTRA's own Stripe
 * account (mode=payment) — not the per-seller escrow/Connect path, since
 * VESTRA is collecting the sample payment itself, not a connected seller.
 */
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/stripe.php';
require_once __DIR__ . '/inc/samples.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

$id = trim((string)($_POST['id'] ?? ''));
$backUrl = '/product?id=' . rawurlencode($id);

if (!empty($_POST['website'])) { header('Location: ' . $backUrl); exit; } // honeypot

$user = auth_user();
if (!$user) {
    header('Location: /login?back=' . urlencode($backUrl));
    exit;
}

$p = $id !== '' ? vestra_find($id) : null;
if (!$p || empty($p['sample_price']) || !is_numeric($p['sample_price']) || (float)$p['sample_price'] <= 0) {
    // Not sample-eligible (or tampered id) — nothing to sell here.
    header('Location: ' . $backUrl);
    exit;
}

if (!stripe_available()) {
    header('Location: ' . $backUrl . '&error=notready');
    exit;
}

$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 200) $note = mb_substr($note, 0, 200);

$amount = (float)$p['sample_price'];
$cents  = (int) round($amount * 100);

$ref = 'SPL-' . strtoupper(bin2hex(random_bytes(4)));

sample_save([
    'ref'            => $ref,
    'product_id'     => $p['id'],
    'brand'          => (string)($p['brand'] ?? ''),
    'name'           => (string)($p['name'] ?? ''),
    'sku'            => (string)($p['sku'] ?? ''),
    'buyer_id'       => $user['id'] ?? '',
    'buyer_email'    => $user['email'] ?? '',
    'buyer_name'     => $user['name'] ?? '',
    'buyer_company'  => $user['company'] ?? '',
    'note'           => $note,
    'amount'         => $amount,
    'currency'       => 'eur',
    'status'         => 'pending',
    'created'        => date('c'),
]);

// EU-wide only — the sample price already includes shipping within the EU.
$EU_COUNTRIES = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
                  'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];

try {
    $customerId = stripe_ensure_customer($user);

    $session = stripe_api('POST', '/v1/checkout/sessions', [
        'mode'                       => 'payment',
        'customer'                   => $customerId,
        'client_reference_id'        => $ref,
        'line_items'                 => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => $cents,
                'product_data' => [
                    'name'        => 'Sample — ' . trim(($p['brand'] ?? '') . ' ' . ($p['name'] ?? '')),
                    'description' => 'EU-wide shipping included. ' . ($note !== '' ? 'Size/note: ' . $note : ''),
                ],
            ],
        ]],
        'shipping_address_collection' => ['allowed_countries' => $EU_COUNTRIES],
        'metadata'                   => ['kind' => 'sample', 'order_ref' => $ref],
        'payment_intent_data'        => ['metadata' => ['kind' => 'sample', 'order_ref' => $ref]],
        'success_url'                => 'https://vestrasales.com/sample-confirm?ref=' . rawurlencode($ref) . '&paid=1',
        'cancel_url'                 => 'https://vestrasales.com' . $backUrl,
    ]);

    sample_update($ref, ['session_id' => $session->id ?? '']);

    header('Location: ' . $session->url);
    exit;

} catch (\Throwable $e) {
    error_log('[VESTRA Sample] Checkout error: ' . $e->getMessage());
    header('Location: ' . $backUrl . '&error=1');
    exit;
}
