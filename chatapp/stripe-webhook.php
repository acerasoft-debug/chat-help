<?php
// stripe-webhook.php — ChatHelp Zahlungsverarbeitung
require_once __DIR__ . '/config.php';

define('STRIPE_SECRET', 'sk_live_***REDACTED***');
define('WEBHOOK_SECRET', 'whsec_***REDACTED***'); // Stripe Dashboard -> Webhooks -> signing secret

$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify signature if webhook secret set
if (WEBHOOK_SECRET && $sig) {
    $parts = [];
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $ts = $parts['t'][0] ?? 0;
    $expected = hash_hmac('sha256', $ts . '.' . $payload, WEBHOOK_SECRET);
    if (!in_array($expected, $parts['v1'] ?? [])) {
        http_response_code(400);
        exit('Invalid signature');
    }
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';
$obj   = $event['data']['object'] ?? [];

$storeDir = __DIR__ . '/data/';
if (!file_exists($storeDir)) mkdir($storeDir, 0700, true);

switch ($type) {
    case 'checkout.session.completed':
        $sessionId  = $obj['id'] ?? '';
        $customerId = $obj['customer'] ?? '';
        $email      = $obj['customer_details']['email'] ?? $obj['customer_email'] ?? '';
        $mode       = $obj['mode'] ?? ''; // 'subscription' or 'payment'
        $meta       = $obj['metadata'] ?? [];
        $plan       = $meta['plan'] ?? 'basic';
        $docType    = $meta['doc_type'] ?? '';
        $sessionData= $meta['session_id'] ?? '';

        // Save user plan
        if ($email) {
            $userFile = $storeDir . 'user_' . md5($email) . '.json';
            $userData = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];
            $userData['email']      = $email;
            $userData['customer_id']= $customerId;

            if ($mode === 'subscription') {
                $userData['plan']       = $plan;
                $userData['plan_since'] = date('Y-m-d');
                $userData['sub_id']     = $obj['subscription'] ?? '';
            } elseif ($mode === 'payment') {
                // Single doc purchase — unlock for this session
                if (!isset($userData['unlocked_docs'])) $userData['unlocked_docs'] = [];
                $userData['unlocked_docs'][] = [
                    'session'  => $sessionData,
                    'doc_type' => $docType,
                    'paid_at'  => date('Y-m-d H:i:s'),
                ];
            }

            file_put_contents($userFile, json_encode($userData));
        }
        break;

    case 'customer.subscription.deleted':
        $customerId = $obj['customer'] ?? '';
        // Downgrade to free — find by customer ID
        foreach (glob($storeDir . 'user_*.json') as $f) {
            $u = json_decode(file_get_contents($f), true);
            if (($u['customer_id'] ?? '') === $customerId) {
                $u['plan'] = 'free';
                $u['plan_since'] = date('Y-m-d');
                file_put_contents($f, json_encode($u));
                break;
            }
        }
        break;
}

http_response_code(200);
echo 'OK';
