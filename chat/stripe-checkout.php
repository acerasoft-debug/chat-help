<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
define('STRIPE_SECRET', 'sk_live_***REDACTED***');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$type   = $body['type'] ?? 'subscription';
$plan   = $body['plan'] ?? 'basic';
$docType= $body['doc_type'] ?? '';
$sessId = $body['session_id'] ?? '';
$email  = $body['email'] ?? '';

// Dynamic domain — works for both chat-help.de and chat-help.com
$host   = $_SERVER['HTTP_HOST'] ?? 'chat-help.de';
$domain = 'https://' . $host . '/chat';

// Verify session
if (($body['action'] ?? '') === 'verify') {
    $sid = $body['session_id'] ?? '';
    if (!$sid) { echo json_encode(['paid' => false, 'error' => 'no_session']); exit; }
    $vch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sid));
    curl_setopt_array($vch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>STRIPE_SECRET.':', CURLOPT_TIMEOUT=>15]);
    $vs = json_decode(curl_exec($vch), true) ?: [];
    curl_close($vch);
    $paid = in_array($vs['payment_status']??'', ['paid','no_payment_required'], true);
    echo json_encode(['paid'=>$paid, 'plan'=>$vs['metadata']['plan']??'', 'doc_type'=>$vs['metadata']['doc_type']??'', 'mode'=>$vs['mode']??'']);
    exit;
}

$priceMap = [
    'basic'        => 'price_1TgwFoAJdvZPpak7CnLPOU2f',
    'pro'          => 'price_1Tf6i5AJdvZPpak7o3QIuHsF',
    'elite'        => 'price_1Tf6jqAJdvZPpak7Vp8SvOPW',
    'einfach'      => 'price_1Tf71jAJdvZPpak76GX3MZID',
    'doc_einfach'  => 'price_1Tf71jAJdvZPpak76GX3MZID',
    'standard'     => 'price_1Tf75LAJdvZPpak75eZIpoj6',
    'doc_standard' => 'price_1Tf75LAJdvZPpak75eZIpoj6',
    'komplex'      => 'price_1Tf7H6AJdvZPpak7QSdWq61K',
    'doc_komplex'  => 'price_1Tf7H6AJdvZPpak7QSdWq61K',
];

$priceId = $priceMap[$plan] ?? null;
if (!$priceId) { echo json_encode(['error' => 'Invalid plan: '.$plan]); exit; }

$params = [
    'mode'                    => $type,
    'success_url'             => $domain.'/?payment=success&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'              => $domain.'/?payment=cancel',
    'line_items[0][price]'    => $priceId,
    'line_items[0][quantity]' => '1',
    'metadata[plan]'          => $plan,
    'metadata[doc_type]'      => $docType,
    'metadata[session_id]'    => $sessId,
    'metadata[domain]'        => $host,
    'allow_promotion_codes'   => 'true',
];
if ($email) $params['customer_email'] = $email;

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_USERPWD        => STRIPE_SECRET.':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

echo json_encode(isset($resp['url']) ? ['url'=>$resp['url']] : ['error'=>$resp['error']['message']??'Stripe error']);
