<?php
// check-plan.php — Nutzerplan prüfen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email   = $_GET['email'] ?? '';
$sessId  = $_GET['session_id'] ?? '';
$docType = $_GET['doc_type'] ?? '';

$storeDir = __DIR__ . '/data/';

if ($email) {
    $f = $storeDir . 'user_' . md5($email) . '.json';
    if (file_exists($f)) {
        $u = json_decode(file_get_contents($f), true);
        $plan = $u['plan'] ?? 'free';
        
        // Check doc quota
        $quotas = ['basic'=>20, 'pro'=>100, 'elite'=>999];
        $quota  = $quotas[$plan] ?? 0;
        
        // Count docs this month
        $docs  = array_filter($u['docs'] ?? [], fn($d)=>substr($d['date']??'',0,7)===date('Y-m'));
        $used  = count($docs);
        $remaining = max(0, $quota - $used);
        
        // Check single doc unlock
        $unlocked = false;
        if ($sessId && $docType) {
            foreach ($u['unlocked_docs'] ?? [] as $ud) {
                if ($ud['session'] === $sessId && $ud['doc_type'] === $docType) {
                    $unlocked = true; break;
                }
            }
        }
        
        echo json_encode(['plan'=>$plan,'remaining'=>$remaining,'used'=>$used,'unlocked'=>$unlocked,'email'=>$email]);
        exit;
    }
}

echo json_encode(['plan'=>'free','remaining'=>0,'used'=>0,'unlocked'=>false]);
