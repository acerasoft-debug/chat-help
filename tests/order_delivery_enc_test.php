<?php
/* seller-products.yml -> admin_mode=order_delivery, payload='enc:<zarf>' (26 Eyl 2026).
 *
 * Bu adim siparisin teslimat adresini DUZ METIN olarak aliyordu ve ciktida da
 * duz basiyordu; 23 Eyl'de bir musterinin tam adresi boyle kosu basligina ve
 * kutuge girdi. Kural ("musteri adresini is akisi girdisine yazma") burada
 * uygulanamiyordu: cozulecek bir kayit yok, adres operatorden geliyor.
 * Zarf send-campaign-preview'in to=enc:'iyle AYNI bicim; govde {"address":"..."}.
 *
 * Test is akisindaki GERCEK blogu cikarip AYRI bir PHP surecinde kosturuyor
 * (blok hata halinde exit(1) cagiriyor) ve muhurleyenin (scripts/envelope_seal.php)
 * ayni bicimi urettigini gidis-donus olarak dogruluyor.
 *
 * IKI YON: dogru zarf ACILIR ve '|allow_invoiced=1' zarfin disinda da okunur;
 * yanlis anahtar, bozuk bicim, eksik alan ve eksik sunucu anahtari DURUR -- ve
 * hicbir durumda duz adres ciktiya DUSMEZ. Duz metin yolu eskisi gibi calisir.
 * (Ornek adres uydurma; gercek bir musteri adresi bu dosyaya YAZILMAZ.)
 */
require_once __DIR__.'/../scripts/envelope_seal.php';

$yml = (string)file_get_contents(__DIR__.'/../.github/workflows/seller-products.yml');
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

/* Adimin govdesi: order_delivery'nin kendi heredoc'u. */
if (!preg_match("~cat > /tmp/vestra_order_delivery\\.php <<'PHPEOF'\\n(.*?)\\n\\s*PHPEOF~s", $yml, $sm)) {
    echo "HATA: order_delivery govdesi bulunamadi\n"; exit(1);
}
$step = $sm[1];
/* Opt-in ayristirmasi + zarf blogu: ikisi birlikte, cunku '|allow_invoiced=1'
   zarfin DISINDA duruyor ve once o soyuluyor. */
if (!preg_match('~(\$allowInvoiced = str_contains\(\$addr, \'\|allow_invoiced=1\'\);.*?\} /\* enc: sonu \*/)~s', $step, $bm)) {
    echo "HATA: enc: blogu bulunamadi\n"; exit(1);
}
$block = $bm[1];

$tmp = sys_get_temp_dir().'/vestra_odenc_'.getmypid();
@mkdir($tmp.'/home', 0700, true);
@mkdir($tmp.'/nokey', 0700, true);
$mkKey = function (): array {
    $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($k, $priv);
    return [$priv, openssl_pkey_get_details($k)['key']];
};
[$priv1, $pub1] = $mkKey();
[$priv2, $pub2] = $mkKey();
file_put_contents($tmp.'/home/.vestra_inbox_key.pem', $priv1);

$ADDR  = "Ulica Testowa  7B/3,\n 00-950   Przykladowo, Poland";   /* uydurma; bosluk/satir sonu var */
$CLEAN = 'Ulica Testowa 7B/3, 00-950 Przykladowo, Poland';
$SECRET = 'Testowa';                                              /* sizinti olcutu */

$run = function (string $addr, string $home) use ($block, $tmp): array {
    $h = $tmp.'/harness_'.bin2hex(random_bytes(4)).'.php';
    $src = "<?php\n"
         . "putenv('HOME='.".var_export($home, true).");\n"
         . "\$addr = ".var_export($addr, true).";\n"
         . $block."\n"
         . "echo \"\\n@@RESULT@@\".json_encode(['addr' => \$addrClean, 'allow' => \$allowInvoiced]).\"\\n\";\n";
    file_put_contents($h, $src);
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $h], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $rc = proc_close($p);
    @unlink($h);
    $res = null;
    if (preg_match('/@@RESULT@@(.*)$/m', $out, $m)) $res = json_decode($m[1], true);
    $blockOut = preg_replace('/\n?@@RESULT@@.*$/m', '', $out);   /* harness'in kendi satiri olcum disi */
    return ['rc' => $rc, 'out' => $blockOut, 'err' => $err, 'res' => $res];
};
$leaks = fn(array $r): bool => stripos($r['out'].$r['err'], $SECRET) !== false;

$env1 = vestra_envelope_seal($pub1, ['address' => $ADDR]);

echo "\n== 1. Muhurleyen ==\n";
$t('uc parca, rakam yok',               count(explode('.', $env1)) === 3 && !preg_match('/[0-9]/', $env1));
$t('zarf alfabesinde | YOK (opt-in eki belirsiz degil)', strpos($env1, '|') === false);
$t('duz adres zarfta YOK',              stripos($env1, $SECRET) === false);

echo "\n== 2. Dogru zarf ==\n";
$r = $run('enc:'.$env1, $tmp.'/home');
$t('cikis 0',                           $r['rc'] === 0);
$t('adres cozuldu, bosluk toplandi',    ($r['res']['addr'] ?? '') === $CLEAN);
$t('opt-in yok',                        ($r['res']['allow'] ?? null) === false);
$t('yalniz UZUNLUK basildi',            str_contains($r['out'], 'zarf         : adres cozuldu ('.mb_strlen($CLEAN).' karakter)'));
$t('duz adres ciktida YOK',             !$leaks($r));
$r = $run('enc:'.$env1.'|allow_invoiced=1', $tmp.'/home');
$t('opt-in zarfin DISINDA okunuyor',    $r['rc'] === 0 && ($r['res']['allow'] ?? null) === true && ($r['res']['addr'] ?? '') === $CLEAN);
$t('buyuk harfli on ek (ENC:)',         $run('ENC:'.$env1, $tmp.'/home')['rc'] === 0);
$r = $run('enc:'.vestra_envelope_seal($pub1, ['address' => '']), $tmp.'/home');
$t('bos adres (SILME) zarfla da gecer', $r['rc'] === 0 && ($r['res']['addr'] ?? 'x') === '');

echo "\n== 3. Ters yon: DURMASI gerekenler ==\n";
$r = $run('enc:'.vestra_envelope_seal($pub2, ['address' => $ADDR]), $tmp.'/home');
$t('yanlis anahtar -> cikis 1 + sebep', $r['rc'] === 1 && str_contains($r['err'], 'anahtar acilamadi'));
$t('yanlis anahtar -> sizinti yok',     !$leaks($r));
$r = $run('enc:'.implode('.', array_slice(explode('.', $env1), 0, 2)), $tmp.'/home');
$t('iki parca -> cikis 1 + sebep',      $r['rc'] === 1 && str_contains($r['err'], 'bicim hatali'));
$r = $run('enc:'.vestra_envelope_seal($pub1, ['email' => 'x@example.com']), $tmp.'/home');
$t("'address' alani yok -> cikis 1",    $r['rc'] === 1 && str_contains($r['err'], "'address' alani yok"));
$r = $run('enc:'.vestra_envelope_seal($pub1, ['address' => ['dizi']]), $tmp.'/home');
$t('address metin degil -> cikis 1',    $r['rc'] === 1);
$p = explode('.', $env1); $p[2] = strrev($p[2]);
$r = $run('enc:'.implode('.', $p), $tmp.'/home');
$t('bozuk govde -> cikis 1',            $r['rc'] === 1);
$r = $run('enc:'.$env1, $tmp.'/nokey');
$t('sunucuda anahtar yok -> cikis 1',   $r['rc'] === 1 && str_contains($r['err'], 'sunucuda anahtar yok'));

echo "\n== 4. Duz metin yolu eskisi gibi ==\n";
$r = $run('Rue Exemple 5, 75001 Paris|allow_invoiced=1', $tmp.'/home');
$t('duz adres dokunulmadan gecer',      $r['rc'] === 0 && ($r['res']['addr'] ?? '') === 'Rue Exemple 5, 75001 Paris');
$t('duz yolda opt-in okunur',           ($r['res']['allow'] ?? null) === true);
$t('duz yolda zarf satiri basilmaz',    !str_contains($r['out'], 'zarf'));

echo "\n== 5. Cikti MASKELI (adimin tamami) ==\n";
/* $desc'in kendisi: tanim adimdan cikarilip kosturuluyor. */
if (preg_match('~(\$desc = fn\(string \$a\): string => .*?;)\n~s', $step, $dm)) {
    require_once __DIR__.'/../vestra/inc/addresses.php';
    $desc = eval('return '.substr(trim($dm[1]), strlen('$desc = '), -1).';');
    $d = $desc($CLEAN);
    $t('maske: ilk harf + uzunluk',     str_starts_with($d, 'U*** ('.mb_strlen($CLEAN).' karakter'));
    $t('maske: posta kodu VAR/YOK',     str_contains($d, 'posta kodu VAR') && str_contains($desc('Ulica Testowa 7B'), 'posta kodu YOK'));
    $t('maske: adres metni YOK',        stripos($d, $SECRET) === false);
    $t('maske: bos = (yok)',            $desc('') === '(yok)');
} else {
    $t('$desc tanimi bulundu', false);
}
$t('mevcut adres maskeli basiliyor',    str_contains($step, '"mevcut adres : ".$desc($cur)'));
$t('yeni adres maskeli basiliyor',      str_contains($step, '$desc($addrClean)'));
$t('yazilan adres maskeli basiliyor',   str_contains($step, "\$desc((string)\$r['address'])"));
$t('faturanin gordugu maskeli + karsilastirma',
   str_contains($step, "\$desc((string)(\$r['on_invoice'] ?? ''))") && str_contains($step, 'yazilanla AYNI'));
/* Eski duz basimlar geri gelmesin. */
$t('eski duz basim YOK (mevcut)',       !str_contains($step, '($cur !== \'\' ? $cur :'));
$t('eski duz basim YOK (yeni)',         !str_contains($step, '($addrClean !== \'\' ? $addrClean :'));
$t("eski duz basim YOK (yazilan)",      !str_contains($step, "(\$r['address'] !== '' ? \$r['address'] :"));
$t('ayni yazici cagriliyor',            str_contains($step, 'vestra_order_set_delivery($ref, $addrClean, $allowInvoiced)'));
$t('addresses.php acikca yukleniyor',   str_contains($step, 'require_once $doc."/inc/addresses.php";'));
$t('muhurleyen belgesi bu yeri sayiyor', str_contains((string)file_get_contents(__DIR__.'/../scripts/envelope_seal.php'), 'order_delivery'));

array_map('unlink', glob($tmp.'/home/*') ?: []); @unlink($tmp.'/home/.vestra_inbox_key.pem');
@rmdir($tmp.'/home'); @rmdir($tmp.'/nokey'); @rmdir($tmp);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
