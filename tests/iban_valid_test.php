<?php
/* IBAN dogrulamasi.
 *
 * Bu numara faturaya basiliyor ve alici parayi ORAYA gonderiyor. Yanlis bir
 * hane ya havaleyi geri cevirtir ya da baska bir hesaba dusurur; ikisi de
 * belgeyi kesenin sorunu. Panel bu yuzden gecersiz IBAN'da HICBIR alani
 * kaydetmiyor -- test o kapinin acik kalmasini sagliyor.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
preg_match('/^function vestra_iban_normalize\(.*?^}/ms', $src, $m1);
preg_match('/^function vestra_iban_valid\(.*?^}/ms',     $src, $m2);
eval($m1[0]); eval($m2[0]);

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

echo "\n== 1. Bicim normalize ediliyor ==\n";
$t('bosluk atiliyor',  vestra_iban_normalize('FR76 3000 4008 28')==='FR763000400828');
$t('tire atiliyor',    vestra_iban_normalize('DE89-3704-0044')==='DE8937040044');
$t('kucuk harf buyur', vestra_iban_normalize('de89370400440532013000')==='DE89370400440532013000');

echo "\n== 2. Gercek, gecerli IBAN'lar ==\n";
/* Bankalarin kendi dokumantasyonundaki ORNEK numaralar -- kimseye ait degiller. */
foreach ([
  'DE89 3704 0044 0532 0130 00'      => 'DE ornegi',
  'FR14 2004 1010 0505 0001 3M02 606' => 'FR ornegi (harf iceriyor)',
  'GB29 NWBK 6016 1331 9268 19'      => 'GB ornegi',
  'RO49 AAAA 1B31 0075 9384 0000'    => 'RO ornegi',
  'NL91 ABNA 0417 1643 00'           => 'NL ornegi',
  'IT60 X054 2811 1010 0000 0123 456'=> 'IT ornegi',
  'PL61 1090 1014 0000 0712 1981 2874'=> 'PL ornegi',
] as $iban => $why) $t("gecerli: $why", vestra_iban_valid($iban));

echo "\n== 3. BOZUK olanlar reddediliyor ==\n";
$t('tek hane degismis',  !vestra_iban_valid('DE89 3704 0044 0532 0130 01'));
$t('iki hane yer degis', !vestra_iban_valid('DE89 3704 0044 0532 0131 00'));
$t('KIRPILMIS (kisa)',   !vestra_iban_valid('FR14 2004 1010 0505 0001 3M02'));
$t('fazladan hane',      !vestra_iban_valid('DE89 3704 0044 0532 0130 000'));
$t('bos',                !vestra_iban_valid(''));
$t('sadece ulke kodu',   !vestra_iban_valid('FR'));
$t('hesap numarasi IBAN degil', !vestra_iban_valid('202515871492'));
$t('ulke kodu rakam',    !vestra_iban_valid('1289 3704 0044 0532 0130 00'));

echo "\n== 4. Tabloda olmayan ulke: yalnizca mod-97 ==\n";
/* Bilinmeyen bir ulke kodunu topluca reddetmek, gecerli bir hesabi
   girilemez yapardi. Saglama yine de tutmali. */
$t('bilinmeyen ulke + gecerli saglama', vestra_iban_valid('QA58 DOHB 0000 1234 5678 90AB CDEF G'));
$t('bilinmeyen ulke + BOZUK saglama',  !vestra_iban_valid('QA58 DOHB 0000 1234 5678 90AB CDEF H'));

echo "\n".($fail? "KALDI: $fail  (gecen: $ok)\n" : "hepsi gecti ($ok)\n");
exit($fail?1:0);
