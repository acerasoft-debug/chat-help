<?php
/**
 * VESTRA — Dropship partner API.
 *
 *   GET  /api/dropship.php?a=stock[&id=lac-polo-paris]
 *        → current price, EU/France shipping, per-colour/size stock.
 *
 *   POST /api/dropship.php?a=order
 *        JSON body: { "id"?, "colour", "size", "qty"?, "reference"?,
 *                      "customer_email"?, "customer_name"? }
 *        → creates a pending order + a Stripe Checkout Session, returns
 *          { ok, ref, checkout_url }. Whoever completes checkout_url pays;
 *          the site then collects their shipping address itself (France vs
 *          rest-of-EU delivery are offered as named shipping options).
 *
 * Auth: every request needs  Authorization: Bearer <DROPSHIP_API_KEY>.
 * id defaults to lac-polo-paris — the only dropship-enabled product today —
 * but any product with a `dropship.enabled` listing field works the same way.
 */
require_once __DIR__ . '/../inc/api_auth.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/products.php';
require_once __DIR__ . '/../inc/stripe.php';
require_once __DIR__ . '/../inc/escrow.php';
require_once __DIR__ . '/../inc/dropship.php';

api_require_key();

const DROPSHIP_DEFAULT_ID = 'lac-polo-paris';

$action = (string)($_GET['a'] ?? '');

if ($action === 'stock' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = trim((string)($_GET['id'] ?? DROPSHIP_DEFAULT_ID));
    $p = vestra_find($id);
    if (!$p || empty($p['dropship']['enabled'])) {
        api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);
    }
    api_json([
        'ok'       => true,
        'id'       => $p['id'],
        'brand'    => (string)($p['brand'] ?? ''),
        'name'     => (string)($p['name'] ?? ''),
        'currency' => 'eur',
        'price'    => (float)$p['dropship']['price'],
        'shipping' => [
            'FR' => (float)$p['dropship']['ship_fr'],
            'EU' => (float)$p['dropship']['ship_eu'],
        ],
        'stock'    => $p['dropship']['stock'] ?? [],
    ]);
}

if ($action === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = api_json_body();

    $id         = trim((string)($body['id'] ?? DROPSHIP_DEFAULT_ID));
    $colour     = trim((string)($body['colour'] ?? ''));
    $size       = trim((string)($body['size'] ?? ''));
    $qty        = max(1, (int)($body['qty'] ?? 1));
    $partnerRef = trim((string)($body['reference'] ?? ''));
    $custEmail  = trim((string)($body['customer_email'] ?? ''));
    $custName   = trim((string)($body['customer_name'] ?? ''));

    $p = vestra_find($id);
    if (!$p) api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);

    $r = dropship_create_order($p, $colour, $size, $qty, $custEmail, $custName, $partnerRef);
    $status = $r['ok'] ? 200 : ($r['status'] ?? 400);
    unset($r['status']);
    api_json($r, $status);
}

api_json(['ok' => false, 'error' => 'unknown_action'], 400);
