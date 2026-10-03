<?php
/* NUMUNE IPTAL + SIL (28 Eyl 2026 — Odzież Premium; operator: "diger tüm
 * siparislerini iptal et ve sil"). Odenmemis bir numune linkini geri cekmenin
 * yolu yoktu. IKI YON: pending kayit silinir, yedeklenir, geri okunur —
 * ODENMIS kayit ve BASKA alicinin kaydi yerinde kalir. Is akisi adimi Stripe
 * oturumunu kayittan ONCE kapatmali ve Stripe "paid" derse durmali. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_spldel_'.getmypid();
@mkdir($sand, 0777, true);
define('VESTRA_SAMPLES', $sand.'/samples.json');
require_once $root.'/vestra/inc/samples.php';
$t('kum havuzu: samples_file gercek dosya DEGIL', samples_file() === $sand.'/samples.json');

$mk = fn(string $ref, string $buyer, string $st) => ['ref'=>$ref, 'via'=>'link', 'buyer_id'=>$buyer, 'status'=>$st,
    'sku'=>'SH9608', 'amount'=>60.0, 'currency'=>'eur', 'pay_token'=>str_repeat('a', 32), 'session_id'=>'cs_test_x'];
file_put_contents(samples_file(), json_encode([
    'SPL-AAAA0001' => $mk('SPL-AAAA0001', 'buyerA', 'pending'),
    'SPL-AAAA0002' => $mk('SPL-AAAA0002', 'buyerA', 'paid'),
    'SPL-BBBB0001' => $mk('SPL-BBBB0001', 'buyerB', 'pending'),
], JSON_PRETTY_PRINT));

echo "== 1. pending kayit SILINIR ==\n";
$r = sample_delete('SPL-AAAA0001');
$t('ok=true', !empty($r['ok']));
$t('kayit gitti (geri okuma)', sample_get('SPL-AAAA0001') === null);
$t('yedek dosyasi var', is_file((string)($r['backup'] ?? '')));
$bk = json_decode((string)@file_get_contents((string)($r['backup'] ?? '')), true);
$t('yedek SILINEN kaydi tasiyor', ($bk['SPL-AAAA0001']['status'] ?? '') === 'pending');
$t('yedek sample_backups altinda', str_contains((string)($r['backup'] ?? ''), '/sample_backups/SPL-AAAA0001-'));
$t('yedek YALNIZ silinen kaydi tasiyor', is_array($bk) && count($bk) === 1);

echo "\n== 2. ters yon: DOKUNULMAMASI gerekenler ==\n";
$t('ayni alicinin ODENMIS kaydi yerinde', (sample_get('SPL-AAAA0002')['status'] ?? '') === 'paid');
$t('BASKA alicinin kaydi yerinde', (sample_get('SPL-BBBB0001')['status'] ?? '') === 'pending');
$r2 = sample_delete('SPL-AAAA0002');
$t('ODENMIS kayit REDDEDILIR', empty($r2['ok']) && str_contains((string)($r2['error'] ?? ''), 'paid'));
$t('reddedilen kayit hala yerinde', sample_get('SPL-AAAA0002') !== null);
$t('reddedilen kayit icin yedek YAZILMADI', !isset($r2['backup']));
$r3 = sample_delete('SPL-YOK00000');
$t('olmayan ref REDDEDILIR', empty($r3['ok']));
$t('ikinci silme REDDEDILIR (kayit yok)', empty(sample_delete('SPL-AAAA0001')['ok']));
$t('dosyada tam 2 kayit kaldi', count(samples_all()) === 2);

echo "\n== 3. is akisi adimi (sample_cancel) ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a = strpos($wf, "admin_mode == 'sample_cancel'");
$b = $a === false ? false : strpos($wf, 'PHPEOF', strpos($wf, "<<'PHPEOF'", $a) + 10);
$body = ($a !== false && $b !== false) ? substr($wf, $a, $b - $a) : '';
$t('adim var', $body !== '');
$code = preg_replace('~/\*.*?\*/~s', '', $body);
$t('varsayilan KURU KOSU (move_apply)', str_contains($code, "SC_APPLY")) ;
$t('uygulama move_apply=true ister', (bool)preg_match('/\$apply\s*=\s*strtolower\(trim\(\(string\)getenv\("SC_APPLY"\)\)\)\s*===\s*\'true\'/', $code));
$t('hesap ID TAM esitlik', str_contains($code, "mb_strtolower((string)(\$a['id'] ?? '')) === \$who"));
$t('yalniz o alicinin kayitlari', str_contains($code, "(string)(\$rec['buyer_id'] ?? '') !== (string)\$acc['id']"));
$pExp = strpos($code, "/expire'");
$pDel = strpos($code, 'sample_delete($ref)');
$t('Stripe oturumu KAYITTAN ONCE kapatilir', $pExp !== false && $pDel !== false && $pExp < $pDel);
$t('Stripe "paid" derse DURUR', (bool)preg_match("/if \(\\\$sp === 'paid'\)\s*\{[^\n]*exit\(1\)/", $code));
$t('kapatma geri okunur (expired)', str_contains($code, "(\$o->status ?? '') !== 'expired'"));
$t('odenmis/serbest kayit ATLANIR', str_contains($code, "if (\$st !== 'pending')"));
$t('jeton BASILMAZ', !str_contains($code, "pay_token']") );
$t('oturum kimligi BASILMAZ', !preg_match('/(printf|echo)\([^;]*\$sid\b/', $code));   // \b: $sidMode (yalniz MOD) $sid degil
$t('musteriye mektup GITMEZ', !str_contains($code, 'vestra_send_mail'));

/* ---- 3b. GERCEK PHP: okunamayan Stripe oturumu (3 Eki 2026, TYREX'in 2 Agustos
   numuneleri). Adim "oturum okunamadi -- odenmis olabilir, silmiyorum" diye DURDU ve
   nedenini soylemedi: iki kayit 2 aydir bekleyen, test modunda acilmis olabilecek
   oturumlardi. Karar iki sarta bagli (oturum TEST, anahtar CANLI): Stripe'ta modlar
   ayri, cs_test_ oturumu gercek tahsilat uretemez. Her diger durumda durus AYNEN
   gecerli -- "okunamadi" gercek bir belirsizlik. Stripe'a ag yok: sahte proxy
   (kapali port) her cagriyi ANINDA basarisiz kilar, yani adim ayni "okunamadi" yoluna
   duser. ---- */
echo "\n== 3b. is akisi adimi GERCEK PHP: okunamayan Stripe oturumu ==\n";
$phpBody = preg_match("~<<'PHPEOF'\n(.*)\\z~s", $body, $mm) ? preg_replace('/^            /m', '', $mm[1]) : '';
$run = str_replace('$doc = $home."/public_html";', '$doc = '.var_export($root.'/vestra', true).';', $phpBody);
$t('adimin PHP govdesi bulundu ve belge koku satiri kum havuzuna yonlendirildi', $phpBody !== '' && $run !== $phpBody);
$prel = fn(string $s) => "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($s.'/data', true).");\n"
      . "define('VESTRA_ACCOUNTS', ".var_export($s.'/data/accounts.json', true).");\n"
      . "define('VESTRA_SAMPLES', ".var_export($s.'/data/samples.json', true).");\n";
$SID_TEST = 'cs_test_a1B2c3D4e5F6g7H8SECRETSESSION'; $SID_LIVE = 'cs_live_a1B2c3D4e5F6g7H8SECRETSESSION';
$rec = fn(string $ref, string $st, string $sid) => ['ref'=>$ref, 'buyer_id'=>'buyerA', 'seller_uid'=>'sellerX', 'status'=>$st, 'sku'=>'LAC-L1212',
    'amount'=>50.0, 'currency'=>'eur', 'created'=>'2026-08-02T10:00:00+00:00', 'pay_token'=>str_repeat('b', 32), 'session_id'=>$sid];
$mkSand = function (array $samples): string {
  $s = sys_get_temp_dir().'/vestra_spl3b_'.bin2hex(random_bytes(4));
  mkdir($s.'/data', 0777, true);
  file_put_contents($s.'/data/accounts.json', json_encode([['id'=>'buyerA', 'type'=>'seller', 'company'=>'Probe Buyer BV', 'status'=>'active', 'email'=>'probe@example.test']]));
  file_put_contents($s.'/data/samples.json', json_encode($samples, JSON_PRETTY_PRINT));
  return $s;
};
$runStep = function (string $s, string $keyEnv, string $apply) use ($run, $prel): array {
  file_put_contents($s.'/step.php', $prel($s).preg_replace('/^<\?php\s*/', '', $run));
  $env = 'SC_WHO=buyerA SC_SPEC= SC_APPLY='.escapeshellarg($apply).' STRIPE_SECRET_KEY='.escapeshellarg($keyEnv)
       .' https_proxy=http://127.0.0.1:9 HTTPS_PROXY=http://127.0.0.1:9 no_proxy= NO_PROXY=';
  $lines = []; exec($env.' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($s.'/step.php').' 2>&1', $lines, $rc);
  return [implode("\n", $lines), $rc];
};
$ids = fn(string $s) => array_keys(json_decode((string)file_get_contents($s.'/data/samples.json'), true) ?: []);
$bakN = fn(string $s) => count(glob($s.'/data/sample_backups/*') ?: []);

/* A. TEST oturumu + CANLI anahtar: odenmemis sayilir, kuru kosu veriye dokunmaz */
$s = $mkSand(['SPL-T0000001' => $rec('SPL-T0000001', 'pending', $SID_TEST)]);
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', '');
$t('A kuru kosu: cikis 0, "silinecek: 1 kayit", test-modu cumlesi', $rc === 0 && str_contains($out, 'silinecek: 1 kayit') && str_contains($out, 'test-modu oturum'));
$t('A okunamayan oturumda iki MOD ve sebep basiliyor', str_contains($out, 'oturum modu: test | anahtar modu: live | sebep: '));
$t('A kuru kosu kaydi silmedi, yedek yazmadi', $ids($s) === ['SPL-T0000001'] && $bakN($s) === 0);
$t('A oturum kimligi / jeton / anahtar cikti DISINDA', !str_contains($out, 'SECRETSESSION') && !str_contains($out, 'cs_test_') && !str_contains($out, str_repeat('b', 32)) && !str_contains($out, 'dummyKEY'));
$t('A PHP uyarisi / hatasi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $out));
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', 'true');
$t('A uygulama: cikis 0, SILINDI, geri okuma kayitta yok', $rc === 0 && str_contains($out, 'SPL-T0000001: SILINDI') && str_contains($out, 'kayitta yok (dogrulandi)'));
$t('A kayit gitti ve yedegi var', $ids($s) === [] && $bakN($s) === 1);
$t('A Stripe KAPATMA denenmedi (kapatilacak oturum yok)', !str_contains($out, 'KAPATILDI') && !str_contains($out, 'kapatma hatasi'));

/* B. ters yon: CANLI oturum + canli anahtar okunamazsa DURUR */
$s = $mkSand(['SPL-L0000001' => $rec('SPL-L0000001', 'pending', $SID_LIVE)]);
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', 'true');
$t('B canli oturum okunamadi: cikis 1, "odenmis olabilir", kayit YERINDE', $rc === 1 && str_contains($out, 'odenmis olabilir') && $ids($s) === ['SPL-L0000001'] && $bakN($s) === 0);
$t('B modlar yazili: oturum modu: live | anahtar modu: live', str_contains($out, 'oturum modu: live | anahtar modu: live'));
/* C. ters yon: TEST oturumu + TEST anahtar -- anahtar o oturumu OKUYABILIRDI, okunamamasi gercek belirsizlik */
$s = $mkSand(['SPL-T0000002' => $rec('SPL-T0000002', 'pending', $SID_TEST)]);
[$out, $rc] = $runStep($s, 'sk_test_dummyKEYdummyKEY', 'true');
$t('C test oturumu + TEST anahtar okunamadi: DURUR, kayit YERINDE', $rc === 1 && str_contains($out, 'odenmis olabilir') && $ids($s) === ['SPL-T0000002'] && $bakN($s) === 0);
/* D. ters yon: oturum modu BILINMIYOR (onek yok) */
$s = $mkSand(['SPL-W0000001' => $rec('SPL-W0000001', 'pending', 'garip_oturum_kimligi_12345')]);
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', 'true');
$t('D onekisiz/bilinmeyen oturum: DURUR, kayit YERINDE', $rc === 1 && str_contains($out, 'oturum modu: ? ') && $ids($s) === ['SPL-W0000001'] && $bakN($s) === 0);
/* E. anahtar modu bilinmiyor */
$s = $mkSand(['SPL-T0000003' => $rec('SPL-T0000003', 'pending', $SID_TEST)]);
[$out, $rc] = $runStep($s, 'dummyKEYwithoutPrefix', 'true');
$t('E anahtar modu bilinmiyor: DURUR, kayit YERINDE', $rc === 1 && str_contains($out, 'anahtar modu: ?') && $ids($s) === ['SPL-T0000003'] && $bakN($s) === 0);
/* F. HEPSI YA DA HICBIRI: biri uygun, biri belirsiz -> uygun olan da SILINMEZ */
$s = $mkSand(['SPL-T0000004' => $rec('SPL-T0000004', 'pending', $SID_TEST), 'SPL-L0000002' => $rec('SPL-L0000002', 'pending', $SID_LIVE)]);
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', 'true');
$t('F karisik parti: cikis 1 ve IKI kayit da YERINDE (uygun olan da silinmedi)', $rc === 1 && $ids($s) === ['SPL-T0000004', 'SPL-L0000002'] && $bakN($s) === 0);
/* G. odenmis kayit test oturumuyla bile ATLANIR */
$s = $mkSand(['SPL-P0000001' => $rec('SPL-P0000001', 'paid', $SID_TEST)]);
[$out, $rc] = $runStep($s, 'sk_live_dummyKEYdummyKEY', 'true');
$t('G odenmis kayit: ATLANDI, cikis 0, yerinde', $rc === 0 && str_contains($out, 'ATLANDI') && str_contains($out, 'silinecek: 0 kayit') && $ids($s) === ['SPL-P0000001']);
/* H. temizleyici: kimlikler maskelenir, sebep sozcukleri kalir */
$pc = preg_replace('~/\*.*?\*/~s', '', $phpBody);
$t('temizleyici deseni adimin icinde', (bool)preg_match("~preg_replace\('(/[^']+/i)', '<kimlik>'~", $pc, $rx));
if (!empty($rx[1])) {
  $clean = fn(string $m) => (string)preg_replace($rx[1], '<kimlik>', $m);
  $t('H cs_/sk_/pi_ kimlikleri maskelenir', !str_contains($clean("No such checkout.session: 'cs_test_a1B2c3D4e5F6'"), 'a1B2')
     && !str_contains($clean('Invalid API Key provided: sk_live_********abcd'), 'abcd') && !str_contains($clean('payment_intent pi_3Ab9XyZ12345 not found'), '3Ab9'));
  $t('H sebep sozcukleri kalir (resource_missing, host adi)', str_contains($clean('code resource_missing for api.stripe.com'), 'resource_missing') && str_contains($clean('Could not resolve host: api.stripe.com'), 'api.stripe.com'));
}
$t('anahtar degeri hicbir printf/echo icinde yok (yalniz onek modu okunuyor)', !preg_match('/(printf|echo)[^;]*getenv\([\'"]STRIPE_SECRET_KEY/', $pc) && !preg_match('/(printf|echo)[^;]*\$keyMode.*getenv/', $pc));
$t('kural IKI sarta bagli: oturum TEST ve anahtar CANLI', (bool)preg_match("/\\\$ss === 'OKUNAMADI' && \\\$sidMode === 'test' && \\\$keyMode === 'live'/", $pc));
$t('kural "odenmis olabilir" durusundan ONCE', ($p1 = strpos($pc, "\$sidMode === 'test' && \$keyMode === 'live'")) !== false && ($p2 = strpos($pc, 'odenmis olabilir, silmiyorum')) !== false && $p1 < $p2);
foreach (glob(sys_get_temp_dir().'/vestra_spl3b_*') ?: [] as $d) { foreach (glob($d.'/data/sample_backups/*') ?: [] as $f) @unlink($f); @rmdir($d.'/data/sample_backups'); foreach (glob($d.'/data/*') ?: [] as $f) @unlink($f); @rmdir($d.'/data'); @unlink($d.'/step.php'); @rmdir($d); }

echo "\n== 4. siparis silme: parasi gelmis / dekontlu siparis silinmez ==\n";
$a2 = strpos($wf, "admin_mode == 'order_delete'");
$b2 = $a2 === false ? false : strpos($wf, 'PHPEOF', strpos($wf, "<<'PHPEOF'", $a2) + 10);
$od = ($a2 !== false && $b2 !== false) ? preg_replace('~/\*.*?\*/~s', '', substr($wf, $a2, $b2 - $a2)) : '';
$pPaid = strpos($od, 'vestra_order_payment_settled($ref');
$pInv  = strpos($od, '$inv = vestra_invoices_for_ref($ref)');
$pDo   = strpos($od, 'vestra_order_delete($ref)');
$t('odeme sorusu var', $pPaid !== false);
$t('odeme sorusu fatura muhafazasindan ONCE', $pPaid !== false && $pInv !== false && $pPaid < $pInv);
$t('odenmis ya da dekontlu -> exit(1)', (bool)preg_match("/if \(!empty\(\\\$settled\['settled'\]\) \|\| \\\$receipt\) \{[^}]*exit\(1\)/s", $od));
$t('silmeden ONCE', $pPaid !== false && $pDo !== false && $pPaid < $pDo);
$t('kalem sayisi SATIRLARI sayar (dizinin anahtarlarini degil)', str_contains($od, "count(vestra_order_lines(\$row)['lines'] ?? [])") && !str_contains($od, 'count(vestra_order_lines($row)))'));

array_map('unlink', glob($sand.'/sample_backups/*') ?: []);
@rmdir($sand.'/sample_backups'); @unlink(samples_file()); @rmdir($sand);
echo "\n".($bad ? "HATA: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
