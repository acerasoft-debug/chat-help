<?php
/**
 * ChatHelp — dump15 (SADECE OKUR): ödeme + teslim akışı (TAM)
 * Dilekçe paywall + ödeme sonrası teslim + form-engine (staatliche) ödeme.
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump15.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
$fe=@file_get_contents(__DIR__.'/form-engine.js');
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";if($s===false){echo "(dosya yok)\n";return;}$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### 1) genDoc TAM (paywall + free-use + sonuç + pay) ###\n";
R($idx,'async function genDoc',0,2600,'genDoc');

echo "\n### 2) showPaywall TAM (pay + abo butonları) ###\n";
R($idx,'function showPaywall',0,2600,'showPaywall');

echo "\n### 3) Ödeme dönüşü (payment=success) ###\n";
R($idx,'payment',-60,200,'payment param ilk geçiş');
R($idx,"payment=='success'",-200,500,'success handler');
R($idx,"=== 'success'",-200,500,'success handler 2');
R($idx,'_pendingDoc',-150,500,'pendingDoc teslim');
R($idx,'stripe-checkout.php',-200,400,'verify çağrısı');

echo "\n### 4) stripeCheckout çağrı yerleri (kim tetikliyor) ###\n";
R($idx,'stripeCheckout(',-120,260,'stripeCheckout çağrısı 1');

echo "\n### 5) form-engine (staatliche) ödeme/erişim ###\n";
R($fe,'function getUserPlan',0,300,'getUserPlan');
R($fe,'function isLoggedIn',0,300,'isLoggedIn');
R($fe,'function showPaywall',0,1200,'form-engine showPaywall (mesaj)');
R($fe,'exportFormPDF',0,700,'exportFormPDF');

echo "\n=== BİTTİ. SİL: rm dump15.php ===\n";
