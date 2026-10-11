<?php
/*
 * ODENMEMIS FATURA ASKISI -- alicinin kendi cikis yolu (inc/unpaid_hold.php;
 * operator, 11 Eki 2026).
 *   §1 giris: unpaid askisindaki alici GIRER, operator askisi girmez
 *   §2 kural: tek siparisli alicida iptal YOK; iki siparislide biri iptal
 *      edilir, son kalan edilemez; dekontlu / odenmis / escrow iptal edilemez
 *   §3 odeme gelince hesap ACILIR (kyb geri gelir); odenmeden acilmaz
 *   §4 kart: cumleler, dugmeler, "en az birini odemek zorunda" metni
 *   §5 buyer.php/admin.php kablolamasi + 8 dil
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-hold-'.bin2hex(random_bytes(4));
mkdir($sand.'/data/invoices', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/i18n.php';
require_once $root.'/inc/unpaid_hold.php';

$hash = password_hash('secret123', PASSWORD_DEFAULT);
$acc = fn(string $id, string $em, array $x = []) => array_merge(['id' => $id, 'type' => 'buyer', 'status' => 'suspended', 'suspend_reason' => 'operator',
    'kyb_status' => 'suspended', 'kyb_status_before_suspend' => 'approved', 'suspend_note' => 'unpaid invoice: X', 'company' => $id, 'email' => $em, 'hash' => $hash], $x);
file_put_contents(VESTRA_ACCOUNTS, json_encode([
    $acc('one', 'one@x.fr'),                       // 1 siparis
    $acc('two', 'two@x.fr'),                       // 2 siparis
    $acc('rcp', 'rcp@x.fr'),                       // 2 siparis, biri dekontlu
    $acc('opr', 'opr@x.fr', ['suspend_note' => 'spam']),   // operator askisi
    ['id' => 'act', 'type' => 'buyer', 'status' => 'active', 'kyb_status' => 'approved', 'company' => 'act', 'email' => 'act@x.fr', 'hash' => $hash],
]));
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach ([['O-1','one@x.fr'],['T-1','two@x.fr'],['T-2','two@x.fr'],['R-1','rcp@x.fr'],['R-2','rcp@x.fr'],['A-1','act@x.fr'],['A-2','act@x.fr'],['A-3','act@x.fr']] as [$r, $e])
    fputcsv($h, ['2026-10-0'.rand(1,9).'T10:00:00+00:00', $r, 'Co', '', 'n', $e, 'FR', '', '1x A @10', '100', '0', '100', '100', 'Payment: Bank transfer.', 'yes', '', '', '0', '0', ''], ',', '"', '\\');
fclose($h);
foreach (['O-1' => 1001, 'T-1' => 1002, 'T-2' => 1003, 'R-1' => 1004, 'R-2' => 1005] as $r => $no)
    file_put_contents($sand.'/data/invoices/'.$r.'__vestra.json', json_encode(['no' => 'INV-2026-'.$no, 'seller_key' => 'vestra', 'currency' => 'USD', 'total' => 112.5]));
file_put_contents($sand.'/data/order_statuses.json', json_encode([
    'R-1' => ['status' => 'pending', 'payment_receipt' => ['file' => 'r.pdf']],
    'A-1' => ['status' => 'paid'],
]));

echo "\n== 1. giris ==\n";
$t('unpaid askisi GIRER', is_array(auth_login('one@x.fr', 'secret123')));
$t('operator askisi GIRMEZ', auth_login('opr@x.fr', 'secret123') === 'suspended');
$t('suspend_reason=unpaid de taninir', auth_suspended_for_unpaid(['type' => 'buyer', 'status' => 'suspended', 'suspend_reason' => 'unpaid']));
$t('acik hesap askida sayilmaz', !auth_suspended_for_unpaid(auth_find('act@x.fr')));
$t('satici askisi bu kural degil', !auth_suspended_for_unpaid(['type' => 'seller', 'status' => 'suspended', 'suspend_note' => 'unpaid invoice: X']));

echo "\n== 2. iptal kurali ==\n";
$s1 = vestra_unpaid_hold_state(auth_find('one@x.fr'));
$t('tek siparis: listede 1 acik', count($s1['open']) === 1 && $s1['open']['O-1']['invoice'] === 'INV-2026-1001');
$t('tek siparis: iptal YOK (last)', vestra_unpaid_hold_cancel_block($s1, 'O-1') === 'last');
$t('tek siparis: sunucu da reddeder', vestra_unpaid_hold_cancel(auth_find('one@x.fr'), 'O-1')['error'] === 'last');
$s2 = vestra_unpaid_hold_state(auth_find('two@x.fr'));
$t('iki siparis: ikisi de iptal edilebilir gorunur', vestra_unpaid_hold_cancel_block($s2, 'T-1') === '' && vestra_unpaid_hold_cancel_block($s2, 'T-2') === '');
$t('iki siparis: biri iptal edildi', vestra_unpaid_hold_cancel(auth_find('two@x.fr'), 'T-1')['ok'] === true);
$stJ = json_decode(file_get_contents($sand.'/data/order_statuses.json'), true);
$t('iptal gecmise buyer olarak yazildi', ($stJ['T-1']['status'] ?? '') === 'cancelled' && ($stJ['T-1']['cancelled_by'] ?? '') === 'buyer' && (end($stJ['T-1']['history'])['by'] ?? '') === 'buyer');
$s2b = vestra_unpaid_hold_state(auth_find('two@x.fr'));
$t('son kalan iptal EDILEMEZ', vestra_unpaid_hold_cancel_block($s2b, 'T-2') === 'last' && vestra_unpaid_hold_cancel(auth_find('two@x.fr'), 'T-2')['ok'] === false);
$t('baskasinin siparisi iptal edilemez', vestra_unpaid_hold_cancel(auth_find('two@x.fr'), 'O-1')['error'] === 'notfound');
$s3 = vestra_unpaid_hold_state(auth_find('rcp@x.fr'));
$t('dekontlu siparis iptal edilemez', vestra_unpaid_hold_cancel_block($s3, 'R-1') === 'receipt');
$t('dekontsuz kardesi iptal edilebilir', vestra_unpaid_hold_cancel_block($s3, 'R-2') === '');
$sa = vestra_unpaid_hold_state(auth_find('act@x.fr'));
$t('odemis alici: kalan iki aciktan ikisi de iptal edilebilir', vestra_unpaid_hold_cancel_block($sa, 'A-2') === '' && vestra_unpaid_hold_cancel_block($sa, 'A-3') === '');
$t('odenmis siparis acik listede yok', !isset($sa['open']['A-1']) && $sa['paid'] === ['A-1']);

echo "\n== 3. odeme gelince acilir ==\n";
$t('odenmeden ACILMAZ', vestra_unpaid_hold_try_reopen(auth_find('one@x.fr')) === false && auth_find('one@x.fr')['status'] === 'suspended');
$stJ = json_decode(file_get_contents($sand.'/data/order_statuses.json'), true);
$stJ['O-1'] = ['status' => 'paid']; file_put_contents($sand.'/data/order_statuses.json', json_encode($stJ));
$t('operator paid yapinca reopen_for_ref acar', vestra_unpaid_hold_reopen_for_ref('O-1') === true);
$a1 = auth_find('one@x.fr');
$t('hesap ACIK, kyb geri geldi, fiyatlar acik', $a1['status'] === 'active' && $a1['kyb_status'] === 'approved' && auth_prices_unlocked($a1) && str_contains((string)$a1['unsuspended_by'], 'O-1'));
$t('ikinci cagri bir sey yapmaz', vestra_unpaid_hold_try_reopen($a1) === false);
$t('operator askisi odemeyle acilmaz', vestra_unpaid_hold_try_reopen(auth_find('opr@x.fr')) === false);

echo "\n== 4. kart ==\n";
$c2 = vestra_unpaid_hold_card(auth_find('rcp@x.fr'), $s3, true);
$t('cok siparis: "en az birini" cumlesi', str_contains($c2, 'You have 2 unpaid orders. To reopen your account, you must pay at least one of them.'));
$t('cok siparis: iptal adimi var', str_contains($c2, 'Cancel the others'));
$t('dekontlu satirda iptal dugmesi YOK, digerinde VAR', substr_count($c2, 'name="ref" value="R-2"') === 1 && substr_count($c2, 'name="ref" value="R-1"') === 0);
$t('dekont rozeti', str_contains($c2, 'Receipt received'));
$t('fatura no + tutar birimiyle', str_contains($c2, 'INV-2026-1005') && str_contains($c2, 'US$ 112.50'));
$t('fatura linki siparis sayfasina', str_contains($c2, '/buyer?tab=orders&amp;view=R-2'));
$c1 = vestra_unpaid_hold_card(auth_find('two@x.fr'), $s2b, true);
$t('tek kalan: "bu faturayi odemek zorundasiniz"', str_contains($c1, 'To reopen your account, you must pay this invoice.'));
$t('tek kalan: iptal dugmesi HIC yok', !str_contains($c1, 'hold_cancel_order') && !str_contains($c1, 'Cancel the others'));
$t('tek kalan: "Must be paid" etiketi', str_contains($c1, 'Must be paid'));
$t('acik hesapta acik siparis yoksa kart bos', vestra_unpaid_hold_card($a1, vestra_unpaid_hold_state($a1), false) === '');
$t('iptal onayi JS ile (yanlislikla tik yok)', str_contains($c2, 'onsubmit="return confirm('));
$t('flash: last cumlesi', str_contains(vestra_unpaid_hold_flash('last'), 'last unpaid order'));

echo "\n== 5. kablolama + ceviri ==\n";
$b = file_get_contents($root.'/buyer.php');
$t('buyer: kapi oturum acilir acilmaz, POST islemlerinden once', strpos($b, 'auth_suspended_for_unpaid($__me)') < strpos($b, "=== 'profile'") || strpos($b, 'auth_suspended_for_unpaid($__me)') < strpos($b, "==='profile'"));
$t('buyer: izinli sekmeler overview/orders/profile', str_contains($b, "['overview', 'orders', 'profile']"));
$t('buyer: izinli eylemlerde dekont + iptal', str_contains($b, "['upload_receipt', 'hold_cancel_order', 'change_password', 'profile']"));
$t('buyer: once odeme kontrolu (acilis)', strpos($b, 'vestra_unpaid_hold_try_reopen($__me)') < strpos($b, '$__ok = ($_SERVER'));
$t('buyer: kart sekme iceriginden ONCE', strpos($b, 'vestra_unpaid_hold_card($AUTH_USER, $__hs, true)') < strpos($b, "if(\$tab==='overview'){"));
$t('buyer: askida eski bant tekrar basilmaz', str_contains($b, '$unpaidInvs = $__hold ? [] :'));
$a = file_get_contents($root.'/admin.php');
$t('admin: paid durumunda acilis', str_contains($a, "vestra_unpaid_hold_reopen_for_ref((string)\$ref); }\n    header('Location: /admin?tab=orders&msg=status_ok')"));
$t('admin: teklif faturasi odendi isaretinde acilis', str_contains($a, "if(!empty(\$rs[\$ref]['invoice_paid_at'])){ require_once __DIR__.'/inc/unpaid_hold.php'; vestra_unpaid_hold_reopen_for_ref(\$ref); }"));
$t('login: askidaki alici /buyer\'a', str_contains(file_get_contents($root.'/login.php'), "auth_suspended_for_unpaid(\$acc)) \$back"));
$css = file_get_contents($root.'/inc/style.css');
$t('stil: kart + telefon kirilimi', str_contains($css, '.uh-card{') && str_contains($css, '@media (max-width:640px){') && str_contains($css, '.uh-row{grid-template-columns:1fr;'));
$t('kartta <header> yok (sitenin sabit ust cubuk stili binmesin)', !str_contains($c2, '<header') && !str_contains($c2, '<section') && !str_contains($c2, '<article'));
$t('head.php: askidaki aliciya "hesabiniz inceleniyor" bandi yok', str_contains(file_get_contents($root.'/inc/head.php'), '!$APPROVED && !auth_suspended_for_unpaid($AUTH_USER)'));
preg_match_all("/t\\('((?:[^'\\\\]|\\\\.)+)'\\)/", file_get_contents($root.'/inc/unpaid_hold.php'), $mm);
$keys = array_unique(array_map('stripslashes', $mm[1]));
foreach (['fr','it','de','es','pt','ru','ja','ar'] as $l) {
    $d = include $root.'/inc/lang/'.$l.'.php';
    $miss = array_filter($keys, fn($k) => !isset($d[$k]));
    $t("ceviri $l: kartin ".count($keys)." cumlesi", !$miss);
    if ($miss) echo "       eksik: ".implode(' | ', $miss)."\n";
}
exec('rm -rf '.escapeshellarg($sand));
echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
