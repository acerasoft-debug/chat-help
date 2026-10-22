<?php
/**
 * ChatHelp — dump-webhook — stripe-webhook.php TAM icerigi (OKUR). Amac: 'basic'
 *  plana geri donme bug'inin sunucu-taraf kok nedeni. INSERT mi UPSERT mi yapiyor?
 *  Event sirasi/idempotency kontrolu var mi?
 * KULLANIM: pull2.php?key=...&files=dump-webhook.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk_live_|sk_test_|whsec_)[A-Za-z0-9]{10,}/','$1***MASKED***',$s); }
$D=__DIR__;
echo "== dump-webhook ==\n\n";

$wh=@file_get_contents("$D/stripe-webhook.php");
if($wh===false){ echo "stripe-webhook.php YOK\n"; exit; }
echo "=== stripe-webhook.php TAM icerik (".strlen($wh)." B, maskeli) ===\n\n";
echo mask($wh)."\n";

echo "\n== BITTI ==\n";
