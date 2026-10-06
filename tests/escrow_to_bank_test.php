<?php
/* ODENMEMIS kart/escrow siparisini BANKA HAVALESINE cevirme (2 Eki 2026,
 * VES-8E46FFA2: "fatura yapamiyorum fatura sayfasina dusmuyor").
 *
 * Escrow siparisi faturasini odeme aninda kendisi keser; bu yuzden onay
 * kuyrugundan dislaniyor, dosyada Approve yok, kesim reddediyor. Alici kart
 * sayfasinda odemeyi tamamlamadiysa siparis HICBIR yolda faturalanamiyordu.
 *
 * KUM HAVUZUNDA GERCEKTEN YAZIYOR, Stripe SAHTE bir cagriyla enjekte ediliyor.
 * IKI YON: odenmemis siparis cevrilir; odenmis / islemde / tamamlanmis /
 * okunamayan oturum, 'held' kayit, faturali, iptal ve parasi gelmis siparis
 * HIC DEGISMEZ. Baska siparis hic degismez. Panel kum havuzunda cizdiriliyor
 * ve POST gercekten kosuyor. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_e2b_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';
require_once $root.'/vestra/inc/escrow.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
$t('escrow.json KUM HAVUZUNDA (gercek data/ degil)', escrow_file() === $sand.'/data/escrow.json');

$head = ['timestamp','ref','company','email','country','items','subtotal','commission','payout','total','notes','discount','shipping','shipping_label'];
$ESC = 'Payment: Secure escrow (card). Colours — SKU-X: Navy. Welcome discount WELCOME5 (-5%) = -€99.75 (first order).';
$seed = function () use ($sand, $head, $ESC) {
    $h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
    $r = function (string $ref, string $notes, string $total = '1967.27', string $comm = '138.35', string $pay = '1828.92', string $ship = '') use ($h) {
        fputcsv($h, ['2026-10-01T10:00:00+00:00', $ref, 'Test BV', 'x@example.com', 'Netherlands',
            '10x SKU-X @199.50', '1895.25', $comm, $pay, $total, $notes, '99.75', $ship, ''], ',', '"', '\\');
    };
    foreach (['VES-E1','VES-E2','VES-E3','VES-E4','VES-E5','VES-E6','VES-E7','VES-E8','VES-E9'] as $ref) $r($ref, $ESC);
    $r('VES-BANK', 'Payment: Bank transfer. Colours — SKU-X: Navy.', '1895.25', '0.00', '1895.25');
    $r('VES-OTHER', $ESC);
    fclose($h);
    $rec = fn(string $ref, string $sid, string $st = 'pending') => ['ref' => $ref, 'seller_uid' => 's1', 'acct_id' => 'acct_X',
        'session_id' => $sid, 'payment_intent' => '', 'amount' => 196727, 'fee' => 13835, 'currency' => 'eur',
        'status' => $st, 'created' => '2026-10-01T10:00:00+00:00', 'buyer' => ['email' => 'x@example.com']];
    file_put_contents($sand.'/data/escrow.json', json_encode([
        'VES-E1' => $rec('VES-E1', 'cs_open'), 'VES-E2' => $rec('VES-E2', 'cs_paid'),
        'VES-E3' => $rec('VES-E3', 'cs_complete'), 'VES-E4' => $rec('VES-E4', 'cs_stuck'),
        'VES-E5' => $rec('VES-E5', 'cs_boom'), 'VES-E6' => $rec('VES-E6', 'cs_x', 'held'),
        'VES-E8' => $rec('VES-E8', 'cs_expired'), 'VES-OTHER' => $rec('VES-OTHER', 'cs_other'),
    ], JSON_PRETTY_PRINT));
    file_put_contents($sand.'/data/order_statuses.json', json_encode([
        'VES-E9' => ['status' => 'paid'], 'VES-OTHER' => ['status' => 'pending'],
    ]));
};
$seed();
$get = function (string $ref) { foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $ref) return $r; return null; };

/* SAHTE STRIPE: oturum durumlari + hangi cagrinin yapildigi. */
$calls = [];
$sess = ['cs_open' => ['open', 'unpaid'], 'cs_paid' => ['open', 'paid'], 'cs_complete' => ['complete', 'unpaid'],
         'cs_stuck' => ['open', 'unpaid'], 'cs_expired' => ['expired', 'unpaid'], 'cs_other' => ['open', 'unpaid']];
$stripe = function (string $m, string $p, string $acct) use (&$calls, &$sess) {
    $calls[] = "$m $p @$acct";
    if (!preg_match('#/v1/checkout/sessions/([^/]+)(/expire)?$#', $p, $mm)) throw new RuntimeException('beklenmeyen yol');
    $sid = $mm[1];
    if ($sid === 'cs_boom') throw new RuntimeException('ag hatasi');
    if (!empty($mm[2]) && $sid !== 'cs_stuck') $sess[$sid][0] = 'expired';
    return (object)['status' => $sess[$sid][0], 'payment_status' => $sess[$sid][1]];
};
$untouched = function (string $ref) use ($get, $ESC) {
    $b = $get($ref);
    return $b && ($b['notes'] ?? '') === $ESC && ($b['total'] ?? '') === '1967.27' && ($b['commission'] ?? '') === '138.35';
};

echo "== 1. rakamlar: kasanin HAVALE formulu (ucret 0) ==\n";
$f = vestra_order_bank_figures($get('VES-E1'));
$t('mal satirlardan 1995.00', abs($f['goods'] - 1995.00) < 0.005);
$t('alt toplam 1995 - 99.75 = 1895.25', abs($f['subtotal'] - 1895.25) < 0.005);
$t('yeni toplam 1895.25 (koruma ucreti yok)', abs($f['total'] - 1895.25) < 0.005);
$t('dusen ucret 72.02', abs($f['fee_removed'] - 72.02) < 0.005);
$t('komisyon havale formuluyle (VESTRA_FEE_* = 0)', abs($f['commission'] - round(1895.25 * VESTRA_FEE_BUYER, 2) - round(1895.25 * VESTRA_FEE_SELLER, 2)) < 0.005);
$t('satici odemesi 1895.25', abs($f['payout'] - 1895.25) < 0.005);

echo "\n== 2. KURU KOSU: Stripe yalniz OKUNUR, hicbir sey yazilmaz ==\n";
$calls = [];
$r = vestra_order_escrow_to_bank('VES-E1', true, $stripe);
$t('kuru kosu ok', !empty($r['ok']) && !empty($r['dry']));
$t('plan: oturum open, KAPATILACAK', ($r['plan']['session'] ?? '') === 'open' && !empty($r['plan']['expire']));
$t('Stripe\'ta yalniz GET (expire YOK)', $calls === ['GET /v1/checkout/sessions/cs_open @acct_X']);
$t('oturum hala acik', $sess['cs_open'][0] === 'open');
$t('siparis DEGISMEDI', $untouched('VES-E1'));
$t('escrow kaydi DURUYOR', escrow_get('VES-E1') !== null);

echo "\n== 3. UYGULA: once Stripe kapatilir, sonra kayit ==\n";
$calls = [];
$r = vestra_order_escrow_to_bank('VES-E1', false, $stripe);
$t('cevrildi', !empty($r['ok']) && empty($r['dry']));
$t('Stripe sirasi: GET, expire, GET (geri okuma)', $calls === ['GET /v1/checkout/sessions/cs_open @acct_X',
    'POST /v1/checkout/sessions/cs_open/expire @acct_X', 'GET /v1/checkout/sessions/cs_open @acct_X']);
$b = $get('VES-E1');
$t('notlar: Payment: Bank transfer.', str_starts_with((string)$b['notes'], 'Payment: Bank transfer. '));
$t('notlarin gerisi AYNEN (renk + indirim notu)', str_contains((string)$b['notes'], 'Colours — SKU-X: Navy.') && str_contains((string)$b['notes'], 'WELCOME5'));
$t('notlarda escrow etiketi YOK', !str_contains((string)$b['notes'], 'Secure escrow'));
$t('toplam 1895.25', ($b['total'] ?? '') === '1895.25');
$t('komisyon 0.00, satici odemesi 1895.25', ($b['commission'] ?? '') === '0.00' && ($b['payout'] ?? '') === '1895.25');
$t('indirim AYNEN 99.75', ($b['discount'] ?? '') === '99.75');
$t('escrow kaydi escrow.json\'da YOK', escrow_get('VES-E1') === null);
$bk = glob($sand.'/data/escrow_backups/VES-E1-*.json');
$t('escrow kaydi YEDEKTE', count($bk) === 1 && isset(json_decode((string)file_get_contents($bk[0]), true)['VES-E1']));
$st = vestra_read_json('order_statuses.json');
$t('iz: pay_method_changed escrow->bank, onceki toplam', ($st['VES-E1']['pay_method_changed']['from'] ?? '') === 'escrow'
   && abs((float)($st['VES-E1']['pay_method_changed']['prev_total'] ?? 0) - 1967.27) < 0.005);
$t('BASKA siparis DEGISMEDI', $untouched('VES-OTHER') && escrow_get('VES-OTHER') !== null && $sess['cs_other'][0] === 'open');
$t('artik normal kesim yolunda: kargo karari soruluyor', isset(vestra_order_issue_prereqs('VES-E1')['shipping']));
$r2 = vestra_order_escrow_to_bank('VES-E1', false, $stripe);
$t('ikinci kez: zaten havale', ($r2['error_code'] ?? '') === 'not_escrow');

echo "\n== 4. para hareket etmis ya da belirsiz: HIC DEGISMEZ ==\n";
$r = vestra_order_escrow_to_bank('VES-E2', false, $stripe);
$t('Stripe ODENDI diyor -> RED', !empty($r['error']) && str_contains($r['error'], 'paid'));
$t('  siparis ve kayit duruyor', $untouched('VES-E2') && escrow_get('VES-E2') !== null);
$r = vestra_order_escrow_to_bank('VES-E3', false, $stripe);
$t('oturum complete (islemde) -> RED', !empty($r['error']) && $untouched('VES-E3') && escrow_get('VES-E3') !== null);
$r = vestra_order_escrow_to_bank('VES-E4', false, $stripe);
$t('expire sonrasi hala open -> RED', !empty($r['error']) && str_contains($r['error'], 'kapanmadı'));
$t('  siparis ve kayit duruyor', $untouched('VES-E4') && escrow_get('VES-E4') !== null);
$r = vestra_order_escrow_to_bank('VES-E5', false, $stripe);
$t('Stripe okunamadi -> RED', !empty($r['error']) && $untouched('VES-E5') && escrow_get('VES-E5') !== null);
$r = vestra_order_escrow_to_bank('VES-E6', false, $stripe);
$t('escrow kaydi held -> RED', !empty($r['error']) && str_contains($r['error'], 'held') && $untouched('VES-E6'));
$r = vestra_order_escrow_to_bank('VES-E9', false, $stripe);
$t('siparis durumu paid -> RED', !empty($r['error']) && $untouched('VES-E9'));
$r = vestra_order_escrow_to_bank('VES-BANK', false, $stripe);
$t('havale siparisi -> not_escrow', ($r['error_code'] ?? '') === 'not_escrow');

echo "\n== 5. Stripe sayfasi hic kurulmamis / suresi dolmus ==\n";
$calls = [];
$r = vestra_order_escrow_to_bank('VES-E7', false, $stripe);
$t('kayitsiz siparis cevrildi', !empty($r['ok']) && ($r['plan']['record'] ?? '') === 'none');
$t('  Stripe HIC cagrilmadi', $calls === []);
$t('  toplam 1895.25', ($get('VES-E7')['total'] ?? '') === '1895.25');
$calls = [];
$r = vestra_order_escrow_to_bank('VES-E8', false, $stripe);
$t('suresi dolmus oturum: expire CAGRILMADAN cevrildi', !empty($r['ok']) && count($calls) === 1 && str_starts_with($calls[0], 'GET '));

echo "\n== 6. iptal edilmis siparis ==\n";
$st = vestra_read_json('order_statuses.json'); $st['VES-E2'] = ['status' => 'cancelled']; vestra_write_json('order_statuses.json', $st);
$r = vestra_order_escrow_to_bank('VES-E2', false, $stripe);
$t('iptal -> RED (Stripe sorulmadan)', !empty($r['error']) && str_contains($r['error'], 'iptal'));

echo "\n== 7. kablolama: tek yazici, panel + is akisi ==\n";
$adm = (string)file_get_contents($root.'/vestra/admin.php');
$wf  = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$dg  = (string)file_get_contents($root.'/.github/workflows/diag-live.yml');
$t('panel handler AYNI fonksiyonu cagiriyor', substr_count($adm, '$r=vestra_order_escrow_to_bank($ref);') === 1);
$t('handler TEK', substr_count($adm, "\$act==='order_escrow_to_bank'") === 1);
$t('is akisi AYNI fonksiyon, kuru kosu varsayilan', str_contains($wf, '$r = vestra_order_escrow_to_bank($ref, !$apply);'));
$t('is akisi adimi admin_mode=escrow_to_bank', str_contains($wf, "github.event.inputs.admin_mode == 'escrow_to_bank'"));
$t('diag find_ref escrow.json okuyor', str_contains($dg, "'samples.json','escrow.json'"));
$t('kuyruk dislamasi YERINDE (escrow kendi faturasini keser)', str_contains($adm, "if (str_contains((string)(\$o['notes'] ?? ''), 'Secure escrow')) return false; // card/escrow"));

echo "\n== 8. PANEL: kum havuzunda cizim + gercek POST ==\n";
$sb = $sand.'/site';
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($sb).' 2>&1', $o, $rc);
exec('rm -rf '.escapeshellarg($sb.'/data')); @mkdir($sb.'/data', 0777, true);
file_put_contents($sb.'/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
$h = fopen($sb.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
fputcsv($h, ['2026-10-01T10:00:00+00:00', 'VES-PNL1', 'Panel BV', 'p@example.com', 'Netherlands', '10x SKU-X @199.50',
    '1895.25', '138.35', '1828.92', '1967.27', $ESC, '99.75', '', ''], ',', '"', '\\');
fputcsv($h, ['2026-10-01T11:00:00+00:00', 'VES-PNLB', 'Bank BV', 'b@example.com', 'Netherlands', '1x SKU-X @100.00',
    '100.00', '0.00', '100.00', '100.00', 'Payment: Bank transfer.', '', '', ''], ',', '"', '\\');
fclose($h);
file_put_contents($sb.'/data/order_statuses.json', '{}');
file_put_contents($sb.'/render.php', <<<'PHP'
<?php
error_reporting(E_ALL); ini_set('display_errors','1');
session_start(); $_SESSION['vadmin']=true; $_SESSION['vadmin_csrf']='tok';
$q = json_decode(getenv('R_GET') ?: '{}', true);
$_GET=$q; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_HOST']='localhost';
$p = getenv('R_POST');
if ($p) { $_SERVER['REQUEST_METHOD']='POST'; $_POST=json_decode($p,true); $_POST['_csrf']='tok'; }
else { $_SERVER['REQUEST_METHOD']='GET'; }
$_SERVER['REQUEST_URI']='/admin';
ob_start(); include __DIR__.'/admin.php'; echo ob_get_clean();
PHP);
$run = function (array $get, ?array $post = null) use ($sb): string {
    $pfx = 'R_GET='.escapeshellarg(json_encode($get)).' '.($post ? 'R_POST='.escapeshellarg(json_encode($post)).' ' : '');
    return (string)shell_exec('cd '.escapeshellarg($sb).' && '.$pfx.'php render.php 2>&1');
};
$html = $run(['tab' => 'invoices']);
$t('fatura sekmesi cizildi', strlen($html) > 5000 && !str_contains($html, 'name="pass"'));
$t('escrow bekleyen karti var ve siparisi listeliyor', str_contains($html, 'id="escrow-wait"') && str_contains($html, 'data-ref="VES-PNL1"'));
$t('karttaki dugme dogru eylem + ref + from=invoices', preg_match('#value="order_escrow_to_bank">\s*<input type="hidden" name="ref" value="VES-PNL1">\s*<input type="hidden" name="from" value="invoices">#', $html) === 1);
$t('karttaki rakam: havale toplami 1.895,25', str_contains($html, '1.895,25') || str_contains($html, '1,895.25'));
$t('havale siparisi kartta DEGIL', !str_contains($html, 'data-ref="VES-PNLB"'));
$t('PHP uyarisi yok (sekme)', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $html));
$dos = $run(['tab' => 'orders', 'view' => 'VES-PNL1']);
$t('dosyada Approve YOK (escrow)', !str_contains($dos, '✓ Approve &amp; issue invoice'));
$t('dosyada Switch dugmesi VAR', str_contains($dos, 'class="vescrow-switch"') && substr_count($dos, 'value="order_escrow_to_bank"') === 1);
$t('PHP uyarisi yok (dosya)', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $dos));
$run([], ['_action' => 'order_escrow_to_bank', 'ref' => 'VES-PNL1', 'from' => 'invoices']);
$back = null; $hh = fopen($sb.'/data/orders.csv', 'r'); $hd = fgetcsv($hh, null, ',', '"', '\\');
while (($rr = fgetcsv($hh, null, ',', '"', '\\')) !== false) { $a = array_combine($hd, array_pad($rr, count($hd), '')); if ($a['ref'] === 'VES-PNL1') $back = $a; }
fclose($hh);
$t('POST: siparis havaleye CEVRILDI (kayittan okundu)', $back && str_starts_with($back['notes'], 'Payment: Bank transfer.') && $back['total'] === '1895.25');
$html2 = $run(['tab' => 'invoices']);
$t('cevrilen siparis artik ONAY kuyrugunda', str_contains($html2, 'href="/admin?tab=orders&view=VES-PNL1"') && !str_contains($html2, 'data-ref="VES-PNL1"'));
$dos2 = $run(['tab' => 'orders', 'view' => 'VES-PNL1']);
$t('dosyada artik Approve VAR, Switch YOK', str_contains($dos2, 'Approve &amp; issue invoice') && !str_contains($dos2, 'class="vescrow-switch"'));

exec('rm -rf '.escapeshellarg($sand));
printf("\n%d ok, %d FAIL\n", $ok, $bad);
exit($bad ? 1 : 0);
