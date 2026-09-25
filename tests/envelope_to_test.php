<?php
/* send-campaign-preview.yml -> reply_spec to=enc:<zarf> (25 Eyl 2026).
 *
 * Operator sohbette bir gmail adresi verip "bunda tum katalog fiyat
 * listelerini gonder" dedi ve adresin hangi kayda ait oldugu bilinmiyordu.
 * (Adres BURAYA YAZILMAZ -- bu dosya da herkese acik depoda.)
 * Eldeki yollar adresi herkese acik girdiye yaziyordu (21 Eyl: yerel kismi
 * find_ref'e, 23 Eyl: tam adres to='ya). Zarf bunu kapatiyor: adres sunucuda
 * cozuluyor, hesaba TAM eslesmeyle baglaniyor, ciktida yalniz MASKELI.
 *
 * Bu test is akisindaki GERCEK blogu cikarip AYRI bir PHP surecinde
 * kosturuyor (blok hata halinde exit(1) cagiriyor; ayni surecte eval testi
 * oldururdu) ve muhurleyen tarafin (scripts/envelope_seal.php) AYNI bicimi
 * urettigini gidis-donus olarak dogruluyor.
 *
 * IKI YON: dogru zarf ACILIR ve hesaba baglanir; yanlis anahtar, bozuk bicim,
 * gecersiz govde ve eksik anahtar DURUR -- ve hicbir durumda duz adres
 * ciktiya DUSMEZ (kutuk herkese acik).
 */
require_once __DIR__.'/../scripts/envelope_seal.php';

$ymlPath = __DIR__.'/../.github/workflows/send-campaign-preview.yml';
$yml = (string)file_get_contents($ymlPath);
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

if (!preg_match("/(if \\(str_starts_with\\(strtolower\\(\\\$to\\), 'enc:'\\)\\) \\{.*?\\} \\/\\* enc: sonu \\*\\/)/s", $yml, $bm)) {
    echo "HATA: enc: blogu bulunamadi\n"; exit(1);
}
$block = $bm[1];

$tmp = sys_get_temp_dir().'/vestra_envtest_'.getmypid();
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

$PLAIN = 'Ari.Fray@Example.com';          /* karisik harf: auth_find buyuk/kucuk harf duyarsiz */
$LOCAL = 'ari.fray';

/* Blogu ayri surecte kosturur. $home = HOME, $accts/$leads = stub kayitlar. */
$run = function (string $to, string $home, array $accts = [], array $leads = []) use ($block, $tmp): array {
    $h = $tmp.'/harness_'.bin2hex(random_bytes(4)).'.php';
    $src = "<?php\n"
         . "putenv('HOME='.".var_export($home, true).");\n"
         . "\$doc = ".var_export($tmp.'/nodoc', true).";\n"
         . "\$mask = fn(string \$e) => preg_replace('/^(.).*(@.*)\$/', '\$1***\$2', \$e);\n"
         . "\$GLOBALS['ACCTS'] = ".var_export($accts, true).";\n"
         . "\$GLOBALS['LEADS'] = ".var_export($leads, true).";\n"
         /* auth.php:105'in birebir davranisi: TAM, buyuk/kucuk harf duyarsiz. */
         . "function auth_find(string \$email): ?array { foreach (\$GLOBALS['ACCTS'] as \$a) if (strtolower(\$a['email'] ?? '') === strtolower(trim(\$email))) return \$a; return null; }\n"
         . "function vestra_leads(): array { return \$GLOBALS['LEADS']; }\n"
         . "\$to = ".var_export($to, true).";\n"
         . "\$acc = null;\n"
         . $block."\n"
         . "echo \"\\n@@RESULT@@\".json_encode(['to' => \$to, 'acc' => \$acc['id'] ?? null]).\"\\n\";\n";
    file_put_contents($h, $src);
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $h], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $rc = proc_close($p);
    @unlink($h);
    $res = null;
    if (preg_match('/@@RESULT@@(.*)$/m', $out, $m)) $res = json_decode($m[1], true);
    /* Sizinti kontrolu harness'in KENDI sonuc satirini disarida birakir:
       o satir testin olcumu, blogun ciktisi degil. */
    $blockOut = preg_replace('/\n?@@RESULT@@.*$/m', '', $out);
    return ['rc' => $rc, 'out' => $blockOut, 'err' => $err, 'res' => $res];
};
$leaks = fn(array $r): bool => stripos($r['out'].$r['err'], $LOCAL) !== false;

$env1 = vestra_envelope_seal($pub1, ['email' => $PLAIN]);
$acc = ['id' => 'acc-ari', 'email' => 'ari.fray@example.com', 'company' => 'Ari Shop',
        'type' => 'buyer', 'status' => 'active', 'kyb_status' => 'approved'];
$other = ['id' => 'acc-x', 'email' => 'someone@example.com', 'company' => 'Paris Store',
          'type' => 'buyer', 'status' => 'active', 'kyb_status' => 'approved'];

echo "\n== 1. Muhurleyen: bicim ==\n";
$t('uc parca (KEY.IV.GOVDE)',           count(explode('.', $env1)) === 3);
$t('ASCII rakam YOK (22 maskesi)',      !preg_match('/[0-9]/', $env1));
$t('duz adres zarfta YOK',              stripos($env1, $LOCAL) === false);
$t('keygen base64 bicimi okunuyor',     vestra_envelope_pubkey('PUBKEY '.base64_encode($pub1)) === $pub1);
$t('PEM oldugu gibi okunuyor',          vestra_envelope_pubkey($pub1) === trim($pub1));

echo "\n== 2. Dogru zarf, hesap VAR ==\n";
$r = $run('enc:'.$env1, $tmp.'/home', [$other, $acc]);
$t('cikis 0',                           $r['rc'] === 0);
$t('adres cozuldu (kucuk harf)',        ($r['res']['to'] ?? '') === strtolower($PLAIN));
$t('hesaba TAM eslesmeyle baglandi',    ($r['res']['acc'] ?? '') === 'acc-ari');
$t('maskeli adres basildi',             str_contains($r['out'], 'adres cozuldu -> a***@example.com'));
$t('firma + id + kapi durumu basildi',  str_contains($r['out'], 'hesap: Ari Shop (acc-ari, buyer, active, kyb approved)'));
$t('duz adres ciktida YOK',             !$leaks($r));
$t('buyuk harfli on ek de calisir (ENC:)', $run('ENC:'.$env1, $tmp.'/home', [$acc])['rc'] === 0);

echo "\n== 3. Dogru zarf, hesap YOK, lead VAR ==\n";
$r = $run('enc:'.$env1, $tmp.'/home', [$other],
          [['email' => 'ARI.FRAY@example.com', 'company' => 'Ari Lead Co', 'status' => 'contacted', 'last_contacted_at' => '2026-09-01T10:00:00+00:00']]);
$t('cikis 0',                           $r['rc'] === 0);
$t('hesap YOK yaziyor',                 str_contains($r['out'], 'hesap: YOK'));
$t('hesap baglanmadi',                  ($r['res']['acc'] ?? null) === null);
$t('lead firma adi + durum yaziyor',    str_contains($r['out'], 'lead : Ari Lead Co (durum contacted, son yazisma 2026-09-01)'));
$t('duz adres ciktida YOK',             !$leaks($r));

echo "\n== 4. Dogru zarf, hicbir kayitta YOK ==\n";
$r = $run('enc:'.$env1, $tmp.'/home', [$other], [['email' => 'x@example.com', 'company' => 'X']]);
$t('cikis 0 (adres gecerli, gonderilebilir)', $r['rc'] === 0);
$t('lead YOK yaziyor',                  str_contains($r['out'], 'lead : YOK'));
$t('duz adres ciktida YOK',             !$leaks($r));

echo "\n== 5. Ters yon: DURMASI gerekenler ==\n";
$r = $run('enc:'.vestra_envelope_seal($pub2, ['email' => $PLAIN]), $tmp.'/home', [$acc]);
$t('yanlis anahtar -> cikis 1',         $r['rc'] === 1);
$t('yanlis anahtar -> sebep yaziyor',   str_contains($r['err'], 'anahtar acilamadi'));
$t('yanlis anahtar -> sizinti yok',     !$leaks($r));

[$k0, $iv0] = explode('.', $env1);
$r = $run('enc:'.$k0.'.'.$iv0, $tmp.'/home', [$acc]);
$t('iki parca -> cikis 1 (bicim hatali)', $r['rc'] === 1 && str_contains($r['err'], 'bicim hatali'));

$bad = vestra_envelope_seal($pub1, ['email' => 'not-an-email-ari.fray']);
$r = $run('enc:'.$bad, $tmp.'/home', [$acc]);
$t('gecersiz adres -> cikis 1',         $r['rc'] === 1 && str_contains($r['err'], 'adres gecersiz'));
$t('gecersiz deger hata metnine DUSMEZ', !$leaks($r));

$r = $run('enc:'.vestra_envelope_seal($pub1, ['mail' => $PLAIN]), $tmp.'/home', [$acc]);
$t('email alani yok -> cikis 1',        $r['rc'] === 1);

$parts = explode('.', $env1);
$parts[2] = strrev($parts[2]);
$r = $run('enc:'.implode('.', $parts), $tmp.'/home', [$acc]);
$t('bozulmus govde -> cikis 1',         $r['rc'] === 1);
$t('bozulmus govde -> sizinti yok',     !$leaks($r));

$r = $run('enc:'.$env1, $tmp.'/nokey', [$acc]);
$t('sunucuda anahtar yok -> cikis 1',   $r['rc'] === 1 && str_contains($r['err'], 'anahtar yok'));

$r = $run('account:ari', $tmp.'/home', [$acc]);
$t('enc: olmayan girdiye DOKUNMAZ',     $r['rc'] === 0 && ($r['res']['to'] ?? '') === 'account:ari' && ($r['res']['acc'] ?? null) === null);

echo "\n== 6. Kablolama ==\n";
$pTo   = strpos($yml, "\$to = trim(\$E('to'));");
$pEnc  = strpos($yml, "if (str_starts_with(strtolower(\$to), 'enc:')) {");
$pAcct = strpos($yml, "if (str_starts_with(strtolower(\$to), 'account:')) {");
$pFind = strpos($yml, "if (!\$acc) \$acc = auth_find(\$to);");
$t('to okunduktan SONRA',               $pTo !== false && $pEnc !== false && $pEnc > $pTo);
$t('account:/order:/lead: cozumunden ONCE', $pAcct !== false && $pEnc < $pAcct);
$t('genel auth_find satirindan ONCE',   $pFind !== false && $pEnc < $pFind);
$t('ayni reply isinde (buyer_reply)',   $pEnc > (int)strpos($yml, "\n  buyer_reply:") && $pEnc < (int)strpos($yml, "\n  send:"));
/* Reply isinde adresi DUZ basan tek satir kalmamali: zarfla gelen adres
   oraya dusseydi zarfin korudugu seyi arkadan acardi. */
$reply = substr($yml, (int)strpos($yml, "\n  buyer_reply:"), (int)strpos($yml, "\n  send:") - (int)strpos($yml, "\n  buyer_reply:"));
$t('reply isinde {$to} duz basilmiyor', !preg_match('/(echo|fwrite|printf)[^;\n]*\{\$to\}/', $reply));
$t('HESAP YOK satiri maskeli',          str_contains($reply, 'HESAP YOK: ".$mask($to)."'));
$t('girdi aciklamasi enc: anlatiyor',   (bool)preg_match('/reply_spec:\s*\n\s*description: "[^"]*enc:</', $yml));
/* Uc acici AYNI rakam haritasini kullanmali; biri degisirse zarf yalniz bir
   yerde acilir ve digerleri "anahtar acilamadi" der. */
$sp = (string)file_get_contents(__DIR__.'/../.github/workflows/seller-products.yml');
$t('seller-products ayni rakam haritasi', substr_count($sp, "strtr(\$x, '!\$%&#,;?@~', '0123456789')") >= 2);
$t('reply blogu ayni rakam haritasi',   str_contains($block, "strtr(\$x, '!\$%&#,;?@~', '0123456789')"));

array_map('unlink', glob($tmp.'/home/.*.pem') ?: []);
@rmdir($tmp.'/home'); @rmdir($tmp.'/nokey'); @rmdir($tmp);

echo "\nenvelope_to_test: {$ok} iddia gecti".($fail ? ", {$fail} HATA" : '')."\n";
exit($fail ? 1 : 0);
