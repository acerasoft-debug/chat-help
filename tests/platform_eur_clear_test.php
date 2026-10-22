<?php
/* PLATFORMUN AVRUPA HESAPLARINI KALDIRMA (22 Eki 2026).
 *
 * Operator: "Acerasoft'un tum Avrupa banka hesaplarini sil". Duz EUR rayi
 * (bank_iban + bank_eur_*) ve `banks` icindeki her EUR profili kalkar; USD rayi
 * ve USD profili aynen kalir. Kuru kosu dosyaya dokunmaz; uygulama once yedek
 * alir, sonra GERI OKUR. Kum havuzu: VESTRA_DATA_DIR gecici dizin. IBAN'lar
 * SENTETIK (tests/no_real_iban_test.php izin listesi).
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-eurclr-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/invoice.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
file_put_contents($sand.'/data/accounts.json', json_encode([]));

$DE_IBAN = 'DE89370400440532013000';
$NL_IBAN = 'NL91ABNA0417164300';
$r = vestra_platform_seller_save([
    'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021', 'bank_acct_type' => 'Checking',
    'bank_iban' => $DE_IBAN, 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank DE', 'bank_eur_address' => 'Berlin, Germany',
]);
$t('duz kunye yazildi', !empty($r['ok']));
$t('NL EUR profili yazildi', !empty(vestra_platform_bank_save('nl', ['label' => 'Airwallex NL', 'currency' => 'EUR', 'bank_iban' => $NL_IBAN, 'bank_bic' => 'ABNANL2A'])['ok']));
$t('USD profili yazildi', !empty(vestra_platform_bank_save('us2', ['label' => 'Second US', 'currency' => 'USD', 'bank_account' => '987654321', 'bank_routing' => '091311229'])['ok']));
vestra_write_json('order_statuses.json', ['VES-OPEN' => ['status' => 'pending', 'invoice_bank' => 'nl'],
                                          'VES-DONE' => ['status' => 'shipped', 'invoice_bank' => 'nl'],
                                          'VES-US'   => ['status' => 'pending', 'invoice_bank' => 'us2']]);
vestra_write_json('offer_responses.json', ['O1' => ['invoice_bank' => 'nl'], 'O2' => ['invoice_bank' => 'us2']]);

echo "-- baslangic --\n";
$t('EUR rayi kutu uretiyor', vestra_payment_rails(vestra_platform_seller(), 'EUR') !== []);
$t('USD rayi kutu uretiyor', vestra_payment_rails(vestra_platform_seller(), 'USD') !== []);

echo "-- kuru kosu --\n";
$before = file_get_contents($sand.'/data/platform_seller.json');
$d = vestra_platform_eur_clear(false);
$t('kuru kosu ok', $d['ok'] && !$d['applied']);
$t('kuru kosu dosyaya dokunmadi', file_get_contents($sand.'/data/platform_seller.json') === $before);
$t('duz EUR alanlari listelendi', $d['flat_cleared'] === ['bank_iban', 'bank_eur_bic', 'bank_eur_name', 'bank_eur_address']);
$t('yalniz EUR profili listelendi', array_keys($d['profiles_removed']) === ['nl']);
$t('etkilenen kayitlar: acik siparis + teklif, kapali siparis degil', $d['refs_affected'] === ['VES-OPEN', 'O1']);
$t('yedek yok (kuru kosu)', glob($sand.'/data/platform_seller.json.bak-eur-*') === []);

echo "-- uygulama --\n";
$a = vestra_platform_eur_clear(true);
$t('uygulama ok', $a['ok'] && $a['applied']);
$baks = glob($sand.'/data/platform_seller.json.bak-eur-*');
$t('yedek alindi', count($baks) === 1 && $a['backup'] === basename($baks[0]));
$t('yedek eski kaydi tasiyor', str_contains((string)file_get_contents($baks[0]), $DE_IBAN));
$t('yedek 0600', (fileperms($baks[0]) & 0777) === 0600);
$acc = vestra_platform_seller();
$t('IBAN gitti', trim((string)($acc['bank_iban'] ?? '')) === '');
$t('EUR banka adi/adresi/BIC gitti', !isset($acc['bank_eur_bic']) && !isset($acc['bank_eur_name']) && !isset($acc['bank_eur_address']));
$t('EUR rayi artik kutu uretmiyor', vestra_payment_rails($acc, 'EUR') === [] && $a['eur_rails_after'] === 0);
$t('USD rayi aynen', vestra_payment_rails($acc, 'USD') !== [] && $acc['bank_account'] === '123456789012' && $acc['bank_routing'] === '021000021');
$t('USD alanlari dokunulmadi', $acc['bank_holder'] === 'Acerasoft LLC' && $acc['bank_name'] === 'US Test Bank' && $acc['bank_bic'] === 'CHASUS33');
$banks = vestra_platform_banks();
$t('NL profili silindi', !isset($banks['nl']));
$t('USD profili kaldi', isset($banks['us2']) && $banks['us2']['bank_account'] === '987654321');
$t('kesim muhafazasi EUR icin kutu yok der', vestra_invoice_payment_gap(null, 'EUR', false) !== '');
$t('kesim muhafazasi USD icin susar', vestra_invoice_payment_gap(null, 'USD', false) === '');
$t('silinmis profil secimi uyusmazlik verir', vestra_platform_bank_mismatch('nl', 'EUR') !== '');
$t('dosya hala 0600', (fileperms($sand.'/data/platform_seller.json') & 0777) === 0600);

echo "-- ikinci uygulama: silinecek bir sey yok --\n";
$again = vestra_platform_eur_clear(true);
$t('ikinci kosu ok ve dosyaya dokunmadi', $again['ok'] && !$again['applied'] && count(glob($sand.'/data/platform_seller.json.bak-eur-*')) === 1);

echo "\n{$ok} ok, {$fail} hata\n";
exit($fail ? 1 : 0);
