<?php
require_once __DIR__ . '/db.php';

define('STRIPE_SECRET', 'sk_live_***REDACTED***');
define('WEBHOOK_SECRET', 'whsec_***REDACTED***');

$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if(WEBHOOK_SECRET && $sig){
    $parts = [];
    foreach(explode(',', $sig) as $part){
        [$k,$v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $ts = $parts['t'][0] ?? 0;
    $expected = hash_hmac('sha256', $ts.'.'.$payload, WEBHOOK_SECRET);
    if(!in_array($expected, $parts['v1'] ?? [])){
        http_response_code(400);
        exit('Invalid signature');
    }
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';
$obj   = $event['data']['object'] ?? [];

$planMap = [
    'price_1TgwFoAJdvZPpak7CnLPOU2f' => 'basic',
    'price_1Tf6i5AJdvZPpak7o3QIuHsF' => 'pro',
    'price_1Tf6jqAJdvZPpak7Vp8SvOPW' => 'elite',
];

try {
    $pdo = db();

    switch($type){

        case 'checkout.session.completed':
            $email  = $obj['customer_details']['email'] ?? $obj['customer_email'] ?? '';
            $mode   = $obj['mode'] ?? '';
            $plan   = $obj['metadata']['plan'] ?? 'basic';
            $custId = $obj['customer'] ?? '';

            if($email && ($mode === 'subscription' || $mode === 'payment')){
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if($user){
                    $pdo->prepare('INSERT INTO user_plans (user_id,plan,stripe_customer_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE plan=?, stripe_customer_id=?')
                        ->execute([$user['id'], $plan, $custId, $plan, $custId]);
                    // Also update stripe_customer_id in users
                    $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')
                        ->execute([$custId, $user['id']]);
                }
            }
            break;

        case 'customer.subscription.updated':
            $custId = $obj['customer'] ?? '';
            $status = $obj['status'] ?? '';
            $priceId = $obj['items']['data'][0]['price']['id'] ?? '';
            $newPlan = $planMap[$priceId] ?? null;

            if($custId && $status === 'active' && $newPlan){
                $pdo->prepare('UPDATE user_plans SET plan=? WHERE user_id=(SELECT id FROM users WHERE stripe_customer_id=? LIMIT 1)')
                    ->execute([$newPlan, $custId]);
            }
            break;

        case 'customer.subscription.deleted':
        case 'customer.subscription.paused':
            $custId = $obj['customer'] ?? '';
            if($custId){
                $pdo->prepare('UPDATE user_plans SET status="cancelled" WHERE user_id=(SELECT id FROM users WHERE stripe_customer_id=? LIMIT 1)')
                    ->execute([$custId]);
            }
            break;

        case 'invoice.payment_failed':
            $custId = $obj['customer'] ?? '';
            if($custId){
                // Keep plan but log
                error_log('Payment failed for customer: '.$custId);
            }
            break;
    }

} catch(Exception $e){
    error_log('Webhook error: '.$e->getMessage());
}

http_response_code(200);
echo json_encode(['received' => true]);
