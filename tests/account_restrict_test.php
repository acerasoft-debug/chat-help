<?php
/*
 * 30 GUNLUK HESAP KISITLAMASI (operator, 10 Eki 2026: "30 gun boyunca mesaj
 * atamazsiniz ve odeme faturasi alamazsiniz ... hesaplarinda gorunsun ...
 * tekrar edilirse kalici olarak kapatilacak ... email gonderme").
 *
 * Olculen (kum havuzu):
 *   §1 auth_restricted_until: gelecek tarih -> kisitli, gecmis/bos -> degil
 *   §2 vestra_msg_send: kisitli GONDEREN durur ve HICBIR SEY yazilmaz;
 *      kontrol gondereni soruyor, kisitli hesaba YAZILABILIR
 *   §3 fatura: kisitli alicida YENI numara yanmaz, nedeni doner; kisitsiz
 *      alicida kapi bos
 *   §4 alici paneli bandi + mesaj hatasi + 8 dilin cevirisi; mektup yolu YOK
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-restrict-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
define('VESTRA_MESSAGES', $sand.'/data/messages.json');
define('VESTRA_BLOCKED_MESSAGES', $sand.'/data/blocked_messages.json');
require_once $root.'/inc/i18n.php';
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/messages.php';
require_once $root.'/inc/invoice.php';

$future = date('c', time() + 30 * 86400);
$past   = date('c', time() - 86400);
file_put_contents(VESTRA_ACCOUNTS, json_encode([
    ['id' => 'r1', 'type' => 'buyer', 'status' => 'active', 'company' => 'Restricted Co', 'email' => 'r@example.com', 'restricted_until' => $future],
    ['id' => 'o1', 'type' => 'buyer', 'status' => 'active', 'company' => 'Old Co', 'email' => 'o@example.com', 'restricted_until' => $past],
    ['id' => 'b1', 'type' => 'buyer', 'status' => 'active', 'company' => 'Free Co', 'email' => 'b@example.com'],
    ['id' => 's1', 'type' => 'seller', 'status' => 'active', 'company' => 'Seller Co', 'email' => 's@example.com'],
]));

echo "\n== 1. kisitlama tarihi ==\n";
$t('gelecek tarih -> kisitli', auth_restricted_uid('r1') === strtotime($future));
$t('gecmis tarih -> kendiliginden kalkmis', auth_restricted_uid('o1') === 0);
$t('alan yok -> kisitli degil', auth_restricted_uid('b1') === 0);
$t('bilinmeyen hesap -> 0', auth_restricted_uid('nope') === 0);
$t('null hesap -> 0', auth_restricted_until(null) === 0);

echo "\n== 2. mesaj ==\n";
$r = vestra_msg_send('r1', 's1', 'r1', 'hello', 'p1');
$t('kisitli alici GONDEREMEZ', ($r['ok'] ?? true) === false && ($r['error'] ?? '') === 'restricted');
$t('bitis tarihi donuyor', (int)($r['until'] ?? 0) === strtotime($future));
$t('hicbir sey yazilmadi', !is_file(VESTRA_MESSAGES) || vestra_msg_threads() === []);
$src = file_get_contents($root.'/inc/messages.php');
$t('kapi GONDERENI soruyor (kisitli hesaba yazilabilir)', str_contains($src, 'auth_restricted_uid($fromUid)'));
$t('VESTRA Support kapidan muaf', str_contains($src, 'if ($fromUid !== VESTRA_SUPPORT_UID) {'));
$t('bos metin kontrolu kapidan ONCE', strpos($src, "'error'=>'empty'];") < strpos($src, "'error'=>'restricted'"));

echo "\n== 3. fatura ==\n";
$order = ['ref' => 'VES-RTEST', 'date' => date('c'), 'currency' => 'EUR',
          'buyer' => ['company' => 'Restricted Co', 'email' => 'r@example.com', 'country' => 'France']];
$t('kisitli alici -> neden doner', str_contains(vestra_invoice_buyer_restriction($order), 'kısıtlı'));
$t('kisitsiz alici -> kapi bos', vestra_invoice_buyer_restriction(['buyer' => ['email' => 'b@example.com']]) === '');
$t('kisitlamasi gecmis -> kapi bos', vestra_invoice_buyer_restriction(['buyer' => ['email' => 'o@example.com']]) === '');
$t('e-postasiz yuk -> kapi bos', vestra_invoice_buyer_restriction([]) === '');
$iv = vestra_ensure_invoice($order, [['sku' => 'X', 'qty' => 1, 'unit' => 10, 'line' => 10]], null, true);
$t('ensure_invoice: kisitli -> error_code restricted', ($iv['error_code'] ?? '') === 'restricted' && ($iv['no'] ?? 'x') === '');
$t('ensure_invoice: NUMARA YANMADI, dosya yok', (glob(vestra_invoice_dir().'/VES-RTEST__*') ?: []) === []);
$isrc = file_get_contents($root.'/inc/invoice.php');
$t('siparis kesimi hatayi YUKARI tasiyor', str_contains($isrc, "if (!empty(\$iv['error'])) return ['error' => (string)\$iv['error'], 'error_code' => (string)(\$iv['error_code'] ?? '')];"));
$t('kapi pending dalindan SONRA (kabul ani numara yakmaz, bozulmaz)',
   strpos($isrc, "'pending' => true];\n    }\n    if ((\$why = vestra_invoice_buyer_restriction(\$order)) !== '')") !== false);
$osrc = file_get_contents($root.'/inc/offers.php');
$t('birlesik kesim: grup kaydi YAZILMADAN durur',
   strpos($osrc, 'vestra_invoice_buyer_restriction((array)($p[\'meta\']') < strpos($osrc, '$rs[$primary][\'invoice_members\'] = $p[\'refs\'];'));
$asrc = file_get_contents($root.'/admin.php');
$t('admin: kendi bandi var', str_contains($asrc, "\$msg==='invoice_restricted'") && substr_count($asrc, "'restricted'=>'invoice_restricted'") === 2);

echo "\n== 4. panel + ceviri ==\n";
$bsrc = file_get_contents($root.'/buyer.php');
$A = 'Your account is restricted for 30 days (until %s): you cannot send messages and cannot receive payment invoices.';
$B = 'If this happens again, your account will be closed permanently.';
$C = 'Your account is restricted: you cannot send messages until %s.';
$D = 'Your message was not sent.';
$t('alici bandi her sekmede (dash_open sonrasi, sekme dallarindan once)',
   strpos($bsrc, 'auth_restricted_until($AUTH_USER)') > strpos($bsrc, 'dash_open(\'buyer\'') && strpos($bsrc, 'auth_restricted_until($AUTH_USER)') < strpos($bsrc, "if(\$tab==='overview'){"));
$t('bant iki cumleyi de basiyor', str_contains($bsrc, "t('".$A."')") && str_contains($bsrc, "t('".$B."')"));
$t('mesaj hatasi restricted olarak donuyor', str_contains($bsrc, "\$res['error']==='restricted'?'restricted'"));
$t('mesaj panelinde restricted bandi', str_contains($src, "\$msgerr === 'restricted'"));
foreach (['fr','it','de','es','pt','ru','ja','ar'] as $l) {
    $d = include $root.'/inc/lang/'.$l.'.php';
    $t("ceviri $l: 4 cumle", isset($d[$A], $d[$B], $d[$C], $d[$D]) && str_contains($d[$A], '%s') && str_contains($d[$C], '%s'));
}
$t('bant yolunda mektup YOK', !preg_match('/auth_restricted_until\(\$AUTH_USER\).{0,600}(vestra_send|brevo|mail\()/s', $bsrc));

exec('rm -rf '.escapeshellarg($sand));
echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
