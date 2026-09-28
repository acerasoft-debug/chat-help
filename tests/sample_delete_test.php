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
$t('oturum kimligi BASILMAZ', !preg_match('/printf\([^;]*\$sid/', $code));
$t('musteriye mektup GITMEZ', !str_contains($code, 'vestra_send_mail'));

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

array_map('unlink', glob($sand.'/sample_backups/*') ?: []);
@rmdir($sand.'/sample_backups'); @unlink(samples_file()); @rmdir($sand);
echo "\n".($bad ? "HATA: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
