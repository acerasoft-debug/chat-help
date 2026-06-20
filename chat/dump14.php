<?php
/**
 * ChatHelp — dump14 (SADECE OKUR): Stripe doc/staatliche ödeme akışı
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump14.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$co=@file_get_contents(__DIR__.'/stripe-checkout.php');
$wh=@file_get_contents(__DIR__.'/stripe-webhook.php');
$idx=@file_get_contents(__DIR__.'/index.php');
$fe=@file_get_contents(__DIR__.'/form-engine.js');
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";if($s===false){echo "(dosya yok)\n";return;}$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### stripe-checkout.php (boyut=".($co===false?'YOK':strlen($co)).") ###\n";
if($co!==false){
  echo substr($co,0,2600)."\n";
}

echo "\n\n### stripe-webhook.php: tek doc ödeme işleme ###\n";
R($wh,'payment',-200,500,'payment / one-time handling');
R($wh,'doc_',-150,400,"doc_ ödeme tanıma");
R($wh,'user_doc',-100,300,'user_documents / erişim verme');

echo "\n\n### DOC_PRICES / DOC_TIER (index) ###\n";
R($idx,'DOC_PRICES',0,700,'DOC_PRICES tablosu');
R($idx,'DOC_TIER',0,700,'DOC_TIER tablosu');

echo "\n\n### STAATLICHE ödeme (form-engine.js) ###\n";
echo "  form-engine.js boyut=".($fe===false?'YOK':strlen($fe))."\n";
R($fe,'exportFormPDF',0,900,'exportFormPDF (PDF/ödeme kontrolü)');
R($fe,'stripe',-150,400,'form-engine stripe çağrısı');
R($fe,'paid',-120,300,'paid/erişim kontrolü');

echo "\n=== BİTTİ. SİL: rm dump14.php ===\n";
