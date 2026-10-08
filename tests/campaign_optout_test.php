<?php
/**
 * vestra_campaign_optout(): hesapta no_campaigns isareti olan adres hicbir
 * kampanya partisine girmez (operator, 8 Eki 2026: odenmemis fatura nedeniyle
 * askiya alinan alicilar "kampanyalardan uzaklastir"). Kapi
 * vestra_lead_is_blocked() -- uye, lead ve elle verilen liste oradan geciyor.
 * Kullanim: php tests/campaign_optout_test.php
 */
$tmp = sys_get_temp_dir().'/vestra_optout_'.getmypid().'.json';
file_put_contents($tmp, json_encode([
  ['id'=>'a1','email'=>'Owes.Money@Example.com ','type'=>'buyer','status'=>'suspended','no_campaigns'=>true],
  ['id'=>'a2','email'=>'paid@example.com','type'=>'buyer','status'=>'active'],
  ['id'=>'a3','email'=>'flag-false@example.com','type'=>'buyer','status'=>'active','no_campaigns'=>false],
  ['id'=>'a4','email'=>'','type'=>'buyer','status'=>'active','no_campaigns'=>true],
]));
define('VESTRA_ACCOUNTS', $tmp);
require_once __DIR__.'/../vestra/inc/notify.php';
$ok = 0; $bad = 0;
$is = function (string $label, bool $got, bool $want) use (&$ok, &$bad) {
  if ($got === $want) { $ok++; echo "  ok   $label\n"; } else { $bad++; echo "  FAIL $label (beklenen ".($want?'true':'false').")\n"; }
};
$is('isaretli adres kampanya disi', vestra_campaign_optout('owes.money@example.com'), true);
$is('buyuk harf + bosluk farki onemsiz', vestra_campaign_optout('  OWES.MONEY@example.COM'), true);
$is('isaretsiz adres serbest', vestra_campaign_optout('paid@example.com'), false);
$is('no_campaigns=false serbest', vestra_campaign_optout('flag-false@example.com'), false);
$is('bos adres false', vestra_campaign_optout(''), false);
$is('alt dize eslesmez (TAM esitlik)', vestra_campaign_optout('money@example.com'), false);
$is('kapi: uye kampanyasi cagrisi eler', vestra_lead_is_blocked(['company'=>'Fashion Co','email'=>'owes.money@example.com','website'=>'']), true);
$is('kapi: isaretsiz musteri gecer', vestra_lead_is_blocked(['company'=>'Nouck Mode','email'=>'paid@example.com','website'=>'']), false);
@unlink($tmp);
echo "\nTOPLAM: $ok gecti, $bad kaldi\n";
exit($bad ? 1 : 0);
