<?php
// stripe-checkout.php — Checkout Session erstellen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
define('STRIPE_SECRET', 'sk_live_***REDACTED***');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$type   = $body['type'] ?? 'subscription'; // subscription | payment
$plan   = $body['plan'] ?? 'basic';
$docType= $body['doc_type'] ?? '';
$sessId = $body['session_id'] ?? '';
$email  = $body['email'] ?? '';

// ── Verify a completed Checkout Session (called on return) ──
if (($body['action'] ?? '') === 'verify') {
    $sid = $body['session_id'] ?? '';
    if (!$sid) { echo json_encode(['paid' => false, 'error' => 'no_session']); exit; }
    $vch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sid));
    curl_setopt_array($vch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET . ':',
        CURLOPT_TIMEOUT        => 15,
    ]);
    $vraw = curl_exec($vch);
    curl_close($vch);
    $vs = json_decode($vraw, true) ?: [];
    $pstatus = $vs['payment_status'] ?? '';
    $paid = in_array($pstatus, ['paid', 'no_payment_required'], true);
    echo json_encode([
        'paid'     => $paid,
        'plan'     => $vs['metadata']['plan'] ?? '',
        'doc_type' => $vs['metadata']['doc_type'] ?? '',
        'mode'     => $vs['mode'] ?? '',
    ]);
    exit;
}

$priceMap = [
    'basic'        => 'price_1Tf6cIAJdvZPpak7my3fN8qF',
    'pro'          => 'price_1Tf6i5AJdvZPpak7o3QIuHsF',
    'elite'        => 'price_1Tf6jqAJdvZPpak7Vp8SvOPW',
    'doc_einfach'  => 'price_1Tf71jAJdvZPpak76GX3MZID',
    'doc_standard' => 'price_1Tf75LAJdvZPpak75eZIpoj6',
    'doc_komplex'  => 'price_1Tf7H6AJdvZPpak7QSdWq61K',
];

$priceId = $priceMap[$plan] ?? null;
if (!$priceId) { echo json_encode(['error' => 'Invalid plan']); exit; }

$domain = 'https://chat-help.de/chat';

$params = [
    'mode'                  => $type,
    'success_url'           => $domain . '/?payment=success&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'            => $domain . '/?payment=cancel',
    'line_items[0][price]'  => $priceId,
    'line_items[0][quantity]' => '1',
    'metadata[plan]'        => $plan,
    'metadata[doc_type]'    => $docType,
    'metadata[session_id]'  => $sessId,
    'allow_promotion_codes' => 'true',
];

if ($email) $params['customer_email'] = $email;

// Build query string
$data = http_build_query($params);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_USERPWD        => STRIPE_SECRET . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
]);
$res  = curl_exec($ch);
curl_close($ch);
$resp = json_decode($res, true);

if (isset($resp['url'])) {
    echo json_encode(['url' => $resp['url']]);
} else {
    echo json_encode(['error' => $resp['error']['message'] ?? 'Stripe error']);
}
