<?php
/* GERCEK BANKA HESABI DEPOYA GIRMEZ.
 *
 * 19 Eyl 2026: operatorun verdigi gercek IBAN, KURAL 5r'nin FIKSTURU olarak
 * `invoice_payment_gap_test.php`'e yazildi ve herkese acik depoya push edildi.
 * Kurali yazan cumlenin altinda kural cigneniyordu; sizinti taramam da
 * "temiz" dedi cunku yalnizca is akislarini ve kaynak dosyalarini taramisti,
 * TESTLERI degil. Bu dosya o boslugu kapatiyor.
 *
 * OLCUT MOD-97, desen degil. Rastgele bir buyuk-harf+rakam dizisi (SKU, base64,
 * hash) IBAN saglamasindan pratik olarak gecmez, yani tarama gurultusuz:
 * olculdu, depoda tam 4 gecerli IBAN var ve dorduu de belgelerde ornek olarak
 * gecen sentetik numaralar. Desenle tarasaydim her SKU'ya takilir ve testi
 * kimse ciddiye almazdi.
 *
 * IZIN LISTESI DAR ve her satirin NEDEN orada oldugu yazili. Yeni bir numara
 * eklemek isteyen once "bu gercek bir hesap mi" sorusunu cevaplamak zorunda --
 * kapinin var olma sebebi bu.
 */
$root = dirname(__DIR__);
$src  = (string)file_get_contents($root.'/vestra/inc/invoice.php');
foreach (['vestra_iban_normalize','vestra_iban_valid'] as $f) {
    if (preg_match('/^function '.$f.'\(.*?^}/ms', $src, $m)) eval($m[0]);
}

/* Sentetik, herkesin belgelerinde gecen ornek numaralar. */
$ALLOW = [
    'DE89370400440532013000' => 'Deutsche Bank ornek IBAN (her IBAN belgesinde)',
    'FR1420041010050500013M02606' => 'La Banque Postale ornek IBAN',
    'FR4720041010125740964U03334' => 'ornek FR IBAN (iban_valid_test)',
    'NL02ABNA0123456789'          => 'ornek NL IBAN (0123456789 dizisi)',
];

/** Bir dosya listesinde izin listesinde OLMAYAN gecerli IBAN arar. */
$scan = function (array $files, string $base) use ($ALLOW): array {
    $bad = [];
    foreach ($files as $f) {
        $p = $base.'/'.$f;
        if (!is_file($p) || filesize($p) > 2000000) continue;
        $t = @file_get_contents($p);
        if ($t === false || !preg_match_all('/\b[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\b/', $t, $m)) continue;
        foreach (array_unique($m[0]) as $c) {
            if (isset($ALLOW[$c])) continue;
            if (vestra_iban_valid($c)) $bad[] = $f.': '.substr($c, 0, 6).'…';
        }
    }
    return $bad;
};

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

echo "== 1. Depoda izinsiz gecerli IBAN yok ==\n";
$tracked = array_filter(explode("\n", trim((string)shell_exec('cd '.escapeshellarg($root).' && git ls-files'))));
$t('git ls-files gercekten dosya veriyor (olcum bos degil)', count($tracked) > 50);
$bad = $scan($tracked, $root);
if ($bad) foreach ($bad as $b) echo "       -> $b\n";
$t('izin listesi disinda gecerli IBAN YOK', $bad === []);

echo "\n== 2. Tarama gercekten DUSEBILIYOR ==\n";
/* Tek yon olculseydi hicbir seyi taramayan bir tarama da yesil kalirdi. */
$tmp = sys_get_temp_dir().'/vestra-iban-scan-'.getmypid();
@mkdir($tmp, 0777, true);
/* Sizinti fiksturunun numarasi PARCALARDAN kuruluyor, dosyaya duz
   yazilmiyor: bu dosya commit edilince `git ls-files`'a girdi ve tarama
   KENDI fiksturunu yakaladi -- taramanin gercekten calistiginin kaniti, ama
   testi kirmiziya boyuyordu. Izin listesine eklemek de olmazdi: o zaman
   asagidaki "yakalaniyor" iddiasi atlanan bir numarayi olcerdi, yani hic
   dusemezdi. (Olcumu dosya HENUZ TAKIPSIZKEN yapmistim; takip edilmeyen
   dosya taramada yok -- olcumu, olculen kumeyi degistiren adimdan SONRA
   tekrarla.) Parcalardan kurarken numarayi bir kez YANLIS birlestirdim ve
   mod-97'den dusdu -- iddia "yakalanmiyor" diye kirmiziya dondu ve HAKLIYDI:
   gecersiz bir numara zaten sizinti degil. */
$leak = 'GB33' . 'BUKB' . '2020' . '1' . str_repeat('5', 9);
file_put_contents($tmp.'/leak.php', "<?php \$acc = ['bank_iban' => '$leak'];\n");
file_put_contents($tmp.'/clean.php', "<?php \$sku = 'LAC-SH9608-00'; \$ref = 'VES-1F0C9350';\n");
$t('sizintili dosya YAKALANIYOR', $scan(['leak.php'], $tmp) !== []);
$t('temiz dosya (SKU + siparis ref) GECIYOR', $scan(['clean.php'], $tmp) === []);
file_put_contents($tmp.'/allow.php', "<?php \$i = 'DE89370400440532013000';\n");
$t('izin listesindeki sentetik numara GECIYOR', $scan(['allow.php'], $tmp) === []);
@array_map('unlink', glob($tmp.'/*')); @rmdir($tmp);

echo "\n-- $ok ok, $fail HATA --\n";
exit($fail === 0 ? 0 : 1);
