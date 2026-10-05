<?php
/* KALICI HESAP SILME (vestra/inc/account_delete.php) — 3 Eki 2026, operator:
 * "Tyrex Internatioal BV yi saticilardan sil" -> "Devret + sil".
 *
 * Panelin Delete dugmesi ile seller-products.yml -> admin_mode=seller_delete AYNI
 * fonksiyonu cagirir. Test dort seyi olcer, hepsi KUM HAVUZUNDA GERCEKTEN KOSTURULARAK
 * (kaynak taramasi bu isi olcemezdi -- bu depoda `php -l`'den gecen iki cagri-zamani
 * hatasi daha once yasandi):
 *   1. kapi panelin eski satir ici kapisiyla AYNI cevabi veriyor (faturali -> engel,
 *      acik -> engel, alici tarafi da sayiliyor, kontrol saticisinin siparisi girmiyor)
 *   2. STRICT kip hesabin tuttugu her seyi sayiyor ve silmiyor (ilan / konusma / numune /
 *      teklif / kesen-secimi / diskte fatura); kontrol saticisinin kayitlari girmiyor
 *   3. yazma yolu: uc yedek, tam BIR hesap gider, geri okuma; yedek alinamazsa ya da
 *      hesap tekil degilse HICBIR SEY silinmez
 *   4. is akisi adimi (gercek PHP, heredoc'tan cikarilip): varsayilan kuru kosu,
 *      yalniz satici, yalniz TAM id, e-posta/banka cikti disinda, uygulayinca geri okur
 * Ayrica panelin POST'u gercek admin.php uzerinde kosuyor: ayni fonksiyon, strict=false. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__);
$V = 'aaaa1111aaaa1111'; $O = 'cccc3333cccc3333'; $B = 'bbbb2222bbbb2222'; $B2 = 'dddd4444dddd4444';
$VMAIL = 'probe.owner@probe-seller.example';

/* ---------- kum havuzu kurucu ---------- */
$mk = function (array $o = []) use ($V, $O, $B, $B2, $VMAIL): string {
  $s = sys_get_temp_dir().'/vestra_adel_'.bin2hex(random_bytes(4));
  mkdir($s.'/data/invoices', 0777, true);
  $acc = [
    ['id'=>$V,  'type'=>'seller', 'company'=>'Probe Seller BV', 'name'=>'Pat Probe', 'email'=>$VMAIL, 'country'=>'Netherlands',
     'status'=>'active', 'kyb_status'=>'approved', 'stripe_account_id'=>'acct_TEST', 'bank_iban'=>'TESTBANK123'],
    ['id'=>$O,  'type'=>'seller', 'company'=>'Control Seller SL', 'name'=>'Cora Control', 'email'=>'cora@control-seller.example', 'country'=>'Spain', 'status'=>'active'],
    ['id'=>$B,  'type'=>'buyer',  'company'=>'Buyer One Ltd', 'name'=>'Bo One', 'email'=>'buyer.one@buyer1.example', 'country'=>'France', 'status'=>'active'],
    ['id'=>$B2, 'type'=>'buyer',  'company'=>'Buyer Two GmbH', 'name'=>'Bea Two', 'email'=>'buyer.two@buyer2.example', 'country'=>'Germany', 'status'=>'active'],
  ];
  if (!empty($o['dup'])) $acc[] = ['id'=>$V, 'type'=>'seller', 'company'=>'Probe Seller DUPLICATE', 'email'=>'dup@probe.example', 'status'=>'active'];
  file_put_contents($s.'/data/accounts.json', json_encode($acc));
  $L = fn($id, $sku, $st, $uid) => ['id'=>$id, 'sku'=>$sku, 'brand'=>'BrandX', 'name'=>'Name of '.$id, 'status'=>$st, 'seller_uid'=>$uid, 'moq'=>10, 'tiers'=>[['min'=>10,'price'=>10.0]], 'images'=>[]];
  $own = !empty($o['handed_over']) ? $O : $V;     // devir yapilmis: V'nin ilanlari artik baska saticida
  file_put_contents($s.'/data/listings.json', json_encode([
    $L('v1', 'SKU-V1', 'approved', $own), $L('v2', 'SKU-V2', 'pending', $own), $L('o1', 'SKU-O1', 'approved', $O),
  ]));
  $head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_reply_unused','voucher_code','discount','shipping','shipping_label'];
  $ord = fn($ref, $co, $email, $items) => ['2026-09-20T10:00:00+00:00', $ref, $co, '', 'Person Name', $email, 'France', '', $items, '10', '0', '10', '10', 'Payment: Bank transfer.', '', '', '', '', '0', ''];
  $rows = [];
  if (!empty($o['inv']))     { $rows[] = $ord('VES-1', 'Buyer One Ltd', 'buyer.one@buyer1.example', '1x SKU-V1 @10.00');
                               file_put_contents($s.'/data/invoices/VES-1__vestra.json', json_encode(['no'=>'INV-T-1','ref'=>'VES-1','seller_key'=>'vestra','currency'=>'EUR','total'=>10,'issued_at'=>'2026-09-21T09:00:00+00:00'])); }
  if (!empty($o['open']))    $rows[] = $ord('VES-2', 'Buyer One Ltd', 'buyer.one@buyer1.example', '1x SKU-V1 @10.00');
  if (!empty($o['buyer']))   $rows[] = $ord('VES-3', 'Probe Seller BV', $VMAIL, '1x SKU-O1 @10.00');            // V ALICI olarak
  if (!empty($o['control'])) $rows[] = $ord('VES-9', 'Buyer Two GmbH', 'buyer.two@buyer2.example', '1x SKU-O1 @10.00');   // kontrol: girmemeli
  $h = fopen($s.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
  foreach ($rows as $r) fputcsv($h, $r, ',', '"', '\\');
  fclose($h);
  $thr = [];
  if (!empty($o['thread']))  $thr[] = ['id'=>'thr0000000000001', 'buyer_uid'=>$B, 'seller_uid'=>$V, 'listing_id'=>'v1', 'messages'=>[['from'=>$B,'text'=>'hi','at'=>'2026-09-30T09:00:00+00:00']], 'last_at'=>'2026-09-30T09:00:00+00:00'];
  if (!empty($o['control'])) $thr[] = ['id'=>'thr0000000000009', 'buyer_uid'=>$B2, 'seller_uid'=>$O, 'listing_id'=>'o1', 'messages'=>[['from'=>$B2,'text'=>'hello','at'=>'2026-09-30T09:00:00+00:00']], 'last_at'=>'2026-09-30T09:00:00+00:00'];
  file_put_contents($s.'/data/messages.json', json_encode($thr));
  file_put_contents($s.'/data/blocked_messages.json', '[]');
  $smp = [];
  if (!empty($o['sample']))  $smp['SPL-T1'] = ['ref'=>'SPL-T1', 'seller_uid'=>$V, 'buyer_id'=>$B, 'status'=>'pending', 'sku'=>'SKU-V1', 'created'=>'2026-10-02T10:00:00+00:00'];
  if (!empty($o['control'])) $smp['SPL-T9'] = ['ref'=>'SPL-T9', 'seller_uid'=>$O, 'buyer_id'=>$B2, 'status'=>'pending', 'sku'=>'SKU-O1', 'created'=>'2026-10-02T10:00:00+00:00'];
  file_put_contents($s.'/data/samples.json', json_encode($smp));
  $oh = ['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'];
  $h = fopen($s.'/data/offers.csv', 'w'); fputcsv($h, $oh, ',', '"', '\\');
  if (!empty($o['offer']))   fputcsv($h, ['2026-09-25T10:00:00+00:00', 'OFR-V1', 'SKU-V1', 'P', '10', '9', '90', 'Buyer One Ltd', 'x@y.example', ''], ',', '"', '\\');
  if (!empty($o['control'])) fputcsv($h, ['2026-09-25T10:00:00+00:00', 'OFR-O1', 'SKU-O1', 'P', '10', '9', '90', 'Buyer Two GmbH', 'x@y.example', ''], ',', '"', '\\');
  fclose($h);
  $st = []; $rs = [];
  if (!empty($o['pick_order'])) $st['VES-X'] = ['status'=>'pending', 'invoice_seller_uid'=>$V];
  if (!empty($o['pick_offer'])) $rs['OFR-X'] = ['status'=>'accept', 'invoice_seller_uid'=>$V];
  if (!empty($o['control']))    { $st['VES-9'] = ['status'=>'pending', 'invoice_seller_uid'=>$O]; $rs['OFR-O1'] = ['status'=>'accept', 'invoice_seller_uid'=>$O]; }
  file_put_contents($s.'/data/order_statuses.json', json_encode($st));
  file_put_contents($s.'/data/offer_responses.json', json_encode($rs));
  if (!empty($o['disk_inv']))   file_put_contents($s.'/data/invoices/VES-Z__'.$V.'.json', json_encode(['no'=>'INV-T-9','ref'=>'VES-Z','seller_key'=>$V,'currency'=>'EUR','total'=>55,'issued_at'=>'2026-09-22T09:00:00+00:00']));
  if (!empty($o['control']))    file_put_contents($s.'/data/invoices/VES-Y__'.$O.'.json', json_encode(['no'=>'INV-T-8','ref'=>'VES-Y','seller_key'=>$O,'currency'=>'EUR','total'=>55,'issued_at'=>'2026-09-22T09:00:00+00:00']));
  if (!empty($o['ro_deleted'])) file_put_contents($s.'/data/deleted-accounts', 'bu bir DOSYA: dizin acilamasin');
  return $s;
};
$snap = function (string $s): array {         // yedek / geri alma disi ana veri dosyalari
  $o = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($s.'/data', FilesystemIterator::SKIP_DOTS)) as $f) {
    $p = (string)$f; $b = basename($p);
    if ($b === '.htaccess' || str_contains($b, '.bak')) continue;
    $o[substr($p, strlen($s))] = sha1_file($p);
  }
  ksort($o); return $o;
};
$baks = function (string $s): array {
  $o = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($s.'/data', FilesystemIterator::SKIP_DOTS)) as $f) {
    $b = basename((string)$f);
    if (str_contains($b, '.bak') || str_contains((string)$f, '/deleted-accounts/')) $o[] = substr((string)$f, strlen($s));
  }
  sort($o); return $o;
};
$accIds = fn(string $s): array => array_map(fn($a) => (string)($a['id'] ?? ''), json_decode((string)file_get_contents($s.'/data/accounts.json'), true) ?: []);
$lstIds = fn(string $s): array => array_map(fn($a) => (string)($a['id'] ?? ''), json_decode((string)file_get_contents($s.'/data/listings.json'), true) ?: []);

/* fonksiyonu AYRI SURECTE, yalniz inc/account_delete.php yuklenerek cagir: ayni zamanda
   KURAL 15'in sinavi (kendi bagimliliklarini yukluyor mu). */
$runner = sys_get_temp_dir().'/vestra_adel_runner_'.bin2hex(random_bytes(4)).'.php';
file_put_contents($runner, <<<'PHP'
<?php
define('VESTRA_DATA_DIR', getenv('SB').'/data');
define('VESTRA_ACCOUNTS', getenv('SB').'/data/accounts.json');
define('VESTRA_MESSAGES', getenv('SB').'/data/messages.json');
define('VESTRA_BLOCKED_MESSAGES', getenv('SB').'/data/blocked_messages.json');
define('VESTRA_SAMPLES', getenv('SB').'/data/samples.json');
ini_set('display_errors', 'stderr'); error_reporting(E_ALL);
require getenv('ROOT').'/vestra/inc/account_delete.php';
echo json_encode(vestra_account_delete(getenv('UID'), getenv('APPLY') === '1', getenv('STRICT') === '1'));
PHP);
$call = function (string $s, string $uid, bool $apply, bool $strict) use ($runner, $root): array {
  $cmd = 'SB='.escapeshellarg($s).' ROOT='.escapeshellarg($root).' UID='.escapeshellarg($uid).' APPLY='.($apply ? '1' : '0').' STRICT='.($strict ? '1' : '0')
       .' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($runner).' 2>&1';
  $raw = (string)shell_exec($cmd);
  $j = json_decode($raw, true);
  return is_array($j) ? $j + ['_raw' => $raw] : ['code' => 'RUNNER_HATASI', '_raw' => $raw];
};

echo "== 1. bulunamayan hesap: hicbir sey degismez ==\n";
$s = $mk(['control' => 1]); $before = $snap($s);
foreach (['bilinmeyen' => 'zzzz9999zzzz9999', 'bos' => '', 'on ek' => substr($V, 0, 8), 'firma parcasi' => 'Probe Seller'] as $n => $id) {
  $r = $call($s, $id, true, true);
  $t("{$n}: not_found, ok degil", ($r['code'] ?? '') === 'not_found' && empty($r['ok']));
}
$t('veri ve yedekler: hicbir sey yazilmadi', $snap($s) === $before && $baks($s) === []);

echo "\n== 2. kuru kosu (apply=false): yazmaz, e-posta ve banka sonucta YOK ==\n";
$s = $mk(['control' => 1]); $before = $snap($s);
$r = $call($s, $V, false, false);
$t('temiz hesap: ok, uygulanmadi', !empty($r['ok']) && empty($r['applied']) && ($r['code'] ?? 'x') === '');
$t('kimlik alanlari dolu', ($r['label'] ?? '') === 'Probe Seller BV' && ($r['type'] ?? '') === 'seller' && ($r['status'] ?? '') === 'active' && ($r['country'] ?? '') === 'Netherlands');
$t('Stripe / IBAN yalniz bayrak', ($r['extras']['stripe'] ?? null) === true && ($r['extras']['iban'] ?? null) === true);
$t('sonucta E-POSTA, IBAN degeri, Stripe kimligi YOK',
   !str_contains($r['_raw'], 'probe.owner@') && !str_contains($r['_raw'], 'TESTBANK123') && !str_contains($r['_raw'], 'acct_TEST') && !str_contains($r['_raw'], 'Pat Probe'));
$t('kuru kosu veriye dokunmadi, yedek de yok', $snap($s) === $before && $baks($s) === []);

echo "\n== 3. PANEL SEMANTIGI (strict=false): hesap ve ILANLARI birlikte gider, UC YEDEK alinir ==\n";
$s = $mk(['control' => 1, 'thread' => 1]);              // panelin kapisi konusmayi GORMEZ -- eski davranis
$r = $call($s, $V, true, false);
$t('ok, uygulandi, kod bos', !empty($r['ok']) && !empty($r['applied']) && ($r['code'] ?? 'x') === '');
$t('hesap gitti, DIGER uc hesap yerinde', !in_array($V, $accIds($s), true) && $accIds($s) === [$O, $B, $B2]);
$t('V\'nin iki ilani gitti, KONTROL saticisinin ilani yerinde', $lstIds($s) === ['o1']);
$bk = $baks($s);
$t('accounts.json.bak.* var ve silinen hesabi iceriyor', ($f = glob($s.'/data/accounts.json.bak.*')) && str_contains((string)file_get_contents($f[0]), $V));
$dj = glob($s.'/data/deleted-accounts/'.$V.'-*.json');
$dd = $dj ? json_decode((string)file_get_contents($dj[0]), true) : null;
$t('deleted-accounts/<uid>-<zaman>.json: hesabin KENDI kaydi + deleted_at', is_array($dd) && ($dd['id'] ?? '') === $V && !empty($dd['deleted_at']) && ($dd['company'] ?? '') === 'Probe Seller BV');
$lb = glob($s.'/data/listings.json.bak-del-*');
$t('listings.json.bak-del-*: silinen ilanlari iceriyor (panel eskiden YEDEKSIZ siliyordu)', $lb && str_contains((string)file_get_contents($lb[0]), '"v1"') && str_contains((string)file_get_contents($lb[0]), '"v2"'));
$t('sonuc: yedek ADLARI (yol degil), geri okuma dogru',
   count($r['backups'] ?? []) === 3 && !str_contains(implode('', $r['backups']), '/') && ($r['readback']['account_gone'] ?? null) === true
   && ($r['readback']['accounts_before'] ?? 0) === 4 && ($r['readback']['accounts_after'] ?? 0) === 3 && ($r['readback']['listings_left'] ?? 1) === 0);
$t('konusma dosyasina DOKUNULMADI (panel eskiden de dokunmuyordu; strict bunun icin var)', str_contains((string)file_get_contents($s.'/data/messages.json'), 'thr0000000000001'));

echo "\n== 4. KAPI, panelin eski kapisiyla AYNI cevap ==\n";
$s = $mk(['inv' => 1, 'control' => 1]); $before = $snap($s);
$r = $call($s, $V, true, false);
$t('faturali siparis -> has_invoice, n=1', ($r['code'] ?? '') === 'has_invoice' && ($r['n'] ?? 0) === 1 && empty($r['ok']) && empty($r['applied']));
$t('REDDEDILINCE veri ve yedek: hicbir sey', $snap($s) === $before && $baks($s) === []);
$s = $mk(['open' => 1, 'control' => 1]); $before = $snap($s);
$r = $call($s, $V, true, false);
$t('faturasiz acik siparis -> has_orders, n=1', ($r['code'] ?? '') === 'has_orders' && ($r['n'] ?? 0) === 1 && $snap($s) === $before && $baks($s) === []);
$s = $mk(['buyer' => 1, 'control' => 1]);
$r = $call($s, $V, false, false);
$t('V ALICI olarak siparis vermis -> has_orders (alici tarafi da sayiliyor)', ($r['code'] ?? '') === 'has_orders' && ($r['counts']['orders'] ?? 0) === 1);
$s = $mk(['inv' => 1, 'open' => 1]);
$r = $call($s, $V, false, false);
$t('ikisi birden: FATURA onceliklidir', ($r['code'] ?? '') === 'has_invoice' && ($r['counts']['invoiced'] ?? 0) === 1 && ($r['counts']['open'] ?? 0) === 1);
$s = $mk(['control' => 1]);
$r = $call($s, $V, false, false);
$t('KONTROL: yalniz BASKA saticinin siparisi var -> V icin GECER', !empty($r['ok']) && ($r['counts']['orders'] ?? 9) === 0);

echo "\n== 5. STRICT: hesap hicbir seye sahip olmamali, kontrol saticisi girmez ==\n";
$cases = [
  'ilan (2: biri pending)' => [['control' => 1], 'listings', 2],
  'konusma'                => [['control' => 1, 'thread' => 1, 'handed_over' => 1], 'threads', 1],
  'numune'                 => [['control' => 1, 'sample' => 1, 'handed_over' => 1], 'samples', 1],
  'teklif (SKU ile)'       => [['control' => 1, 'offer' => 1], 'offers', 1, ['listings']],   // SKU kumesi hesabin ILANLARINDAN geliyor: ilan da sayilir
  'kesen-secimi (siparis)' => [['control' => 1, 'pick_order' => 1, 'handed_over' => 1], 'picks', 1],
  'kesen-secimi (teklif)'  => [['control' => 1, 'pick_offer' => 1, 'handed_over' => 1], 'picks', 1],
  'diskte kestigi fatura'  => [['control' => 1, 'disk_inv' => 1, 'handed_over' => 1], 'disk_invoices', 1],
];
foreach ($cases as $n => $cs) {
  [$opt, $key, $want] = $cs; $also = $cs[3] ?? [];
  $s = $mk($opt); $before = $snap($s);
  $r = $call($s, $V, true, true);
  $only = true; $sum = 0;
  foreach (($r['links'] ?? []) as $k => $v) { $sum += (int)$v; if ($k !== $key && !in_array($k, $also, true) && (int)$v !== 0) $only = false; }
  $t("{$n}: has_links, {$key}={$want}, baska kalem YOK", ($r['code'] ?? '') === 'has_links' && ($r['links'][$key] ?? -1) === $want && $only && ($r['n'] ?? 0) === $sum);
  $t("  ve HICBIR SEY silinmedi, yedek de yok", $snap($s) === $before && $baks($s) === []);
}
$s = $mk(['control' => 1, 'handed_over' => 1]);          // ilanlar devredilmis, baska bagi yok
$r = $call($s, $V, false, true);
$t('devir tamam + hicbir bag yok: strict kuru kosu GECER', !empty($r['ok']) && array_sum($r['links']) === 0);
$r = $call($s, $V, true, true);
$t('strict uygulama: hesap gitti, ilan SAYISI AYNI (3), devredilen ilanlar yerinde',
   !empty($r['ok']) && !in_array($V, $accIds($s), true) && $lstIds($s) === ['v1', 'v2', 'o1']);
$t('strict kipte ilan silinmedi => listings.json YEDEGI de yok (yedek yalniz ilan silinecekse)',
   glob($s.'/data/listings.json.bak-del-*') === [] && count($r['backups'] ?? []) === 2);

echo "\n== 6. yedek alinamazsa / hesap tekil degilse HICBIR SEY silinmez ==\n";
$s = $mk(['ro_deleted' => 1, 'control' => 1]); $before = $snap($s);
$r = $call($s, $V, true, false);
$t('deleted-accounts yazilamiyor -> code=backup', ($r['code'] ?? '') === 'backup' && empty($r['ok']));
$t('hesap dosyasi ve ilanlar DEGISMEDI', $snap($s) === $before && in_array($V, $accIds($s), true) && $lstIds($s) === ['v1', 'v2', 'o1']);
$s = $mk(['dup' => 1, 'control' => 1]); $before = $snap($s);
$r = $call($s, $V, true, false);
$t('ayni id iki kayitta -> code=integrity', ($r['code'] ?? '') === 'integrity' && empty($r['ok']));
$t('hicbir kayit silinmedi', $snap($s) === $before && count($accIds($s)) === 5);

echo "\n== 7. is akisi adimi: gercek PHP kum havuzunda ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "admin_mode == 'seller_delete'");
$t('adim var', $a !== false);
$blk = $a === false ? '' : substr($wf, $a, strpos($wf, "\n      - name:", $a) - $a);
$t('issue_ref ve move_apply envs ile geciyor (SD_UID, SD_APPLY)', str_contains($blk, 'SD_UID:   ${{ github.event.inputs.issue_ref }}') && str_contains($blk, 'SD_APPLY: ${{ github.event.inputs.move_apply }}') && str_contains($blk, 'envs: SD_UID,SD_APPLY'));
if (!preg_match("~<<'PHPEOF'\n(.*?)\n\s*PHPEOF~s", $blk, $m)) { echo "  FAIL php govdesi bulunamadi\n"; exit(1); }
$php  = preg_replace('/^            /m', '', $m[1]);
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$t('fonksiyonu strict=true ile cagiriyor (kuru + uygulama)', substr_count($code, 'vestra_account_delete($uid, false, true)') === 1 && substr_count($code, 'vestra_account_delete($uid, true, true)') === 1);
$t('yalniz SATICI hesabi: tip kontrolu var', str_contains($code, "\$r['type'] !== 'seller'"));
$t('firma parcasi KABUL EDILMIYOR: aciklama + yalniz tam id (arama dongusu yok)', !str_contains($code, 'stripos(') && str_contains($php, 'firma parcasi KABUL EDILMEZ'));
$t('uygulamadan once kapi + varsayilan KURU KOSU', strpos($code, 'KAPI: GECER') < strpos($code, "vestra_account_delete(\$uid, true, true)") && str_contains($code, "=== 'true'"));
$t('e-posta / banka / Stripe kimligi yazdirilmiyor', !preg_match('/\[[\'"](email|bank_iban|stripe_account_id|hash)[\'"]\]/', $code));
$t('admin_mode ve move_apply aciklamalarinda seller_delete anlatiliyor',
   (bool)preg_match('/admin_mode:\s*\n\s*description:\s*"[^\n]*seller_delete/', $wf) && (bool)preg_match('/move_apply:\s*\n\s*description:\s*"[^\n]*seller_delete/', $wf));

$run = str_replace("\$doc = getenv('HOME').'/public_html';", "\$doc = ".var_export($root.'/vestra', true).';', $php);
$t('belge koku satiri bulundu (kum havuzuna yonlendirilebildi)', $run !== $php);
$prel = fn(string $s) => "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($s.'/data', true).");\n"
      . "define('VESTRA_ACCOUNTS', ".var_export($s.'/data/accounts.json', true).");\n"
      . "define('VESTRA_MESSAGES', ".var_export($s.'/data/messages.json', true).");\n"
      . "define('VESTRA_BLOCKED_MESSAGES', ".var_export($s.'/data/blocked_messages.json', true).");\n"
      . "define('VESTRA_SAMPLES', ".var_export($s.'/data/samples.json', true).");\n";
$step = function (string $s, string $uid, string $apply) use ($run, $prel): array {
  file_put_contents($s.'/step.php', $prel($s).preg_replace('/^<\?php\s*/', '', $run));
  $cmd = 'SD_UID='.escapeshellarg($uid).' SD_APPLY='.escapeshellarg($apply).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($s.'/step.php').' 2>&1';
  exec($cmd, $lines, $rc);
  return [implode("\n", $lines), $rc];
};
$s = $mk(['control' => 1, 'handed_over' => 1]); $before = $snap($s);
[$out, $rc] = $step($s, $V, '');
$t('apply bos (varsayilan): cikis 0, KURU KOSU, KAPI GECER', $rc === 0 && str_contains($out, 'KAPI: GECER') && str_contains($out, 'KURU KOSU'));
$t('kuru kosu veriye dokunmadi', $snap($s) === $before && $baks($s) === []);
$t('cikti: kimlik + sayimlar; e-posta, banka, Stripe kimligi, kisi adi YOK',
   str_contains($out, 'hesap     : '.$V.' | Probe Seller BV | seller / active | Netherlands')
   && str_contains($out, 'Stripe Connect VAR') && !str_contains($out, 'probe.owner@') && !str_contains($out, 'TESTBANK123') && !str_contains($out, 'acct_TEST') && !str_contains($out, 'Pat Probe'));
$t('PHP uyarisi / hatasi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $out));
foreach (['yes', 'false', 'TRUE '] as $v) {
  $sx = $mk(['control' => 1, 'handed_over' => 1]); $bx = $snap($sx);
  [$ox, $rx] = $step($sx, $V, $v);
  $isApply = strtolower(trim($v)) === 'true';
  $t("move_apply='{$v}' -> ".($isApply ? 'UYGULAR' : 'kuru kosu (yalniz true uygular)'),
     $isApply ? (!in_array($V, $accIds($sx), true) && $rx === 0) : ($snap($sx) === $bx && $rx === 0 && str_contains($ox, 'KURU KOSU')));
}
$s = $mk(['control' => 1, 'thread' => 1, 'sample' => 1]); $before = $snap($s);        // ilan + konusma + numune duruyor
[$out, $rc] = $step($s, $V, 'true');
$t('strict engel: cikis 1, KAPI ENGELLER, ne kaldigi yazili', $rc === 1 && str_contains($out, 'KAPI: ENGELLER') && str_contains($out, 'ilan 2 | konusma 1 | numune 1'));
$t('apply=true olsa bile ENGELLEYEN hesap silinmedi, veri ayni, yedek yok', $snap($s) === $before && $baks($s) === []);
$s = $mk(['control' => 1]); $before = $snap($s);
[$out, $rc] = $step($s, $B, 'true');
$t('ALICI hesabi: cikis 1, "yalniz SATICI"; silinmedi', $rc === 1 && str_contains($out, 'yalniz SATICI') && $snap($s) === $before);
foreach (['bilinmeyen' => 'zzzz9999zzzz9999', 'firma parcasi' => 'Probe Seller', 'bos' => ''] as $n => $id) {
  [$out, $rc] = $step($s, $id, 'true');
  $t("{$n}: cikis 1, silinmedi", $rc === 1 && $snap($s) === $before);
}
$s = $mk(['control' => 1, 'handed_over' => 1]);
[$out, $rc] = $step($s, $V, 'true');
$t('uygulama: cikis 0, SILINDI, BAGIMSIZ OKUMA hesap yok', $rc === 0 && str_contains($out, 'SILINDI.') && str_contains($out, 'BAGIMSIZ OKUMA: hesap kayitta yok'));
$t('geri okuma satiri: hesap sayisi 4 -> 3, yedekler adlariyla', str_contains($out, 'hesap sayisi 4 -> 3') && str_contains($out, 'yedekler  : accounts.json.bak.') && str_contains($out, 'deleted-accounts') === false && str_contains($out, $V.'-'));
$t('uygulamada hesap gitti, ilanlar (devredilmis) yerinde', !in_array($V, $accIds($s), true) && $lstIds($s) === ['v1', 'v2', 'o1']);

echo "\n== 8. PANELIN POST'U gercek admin.php uzerinde: ayni fonksiyon, strict=false ==\n";
$adm = (string)file_get_contents($root.'/vestra/admin.php');
$ai = strpos($adm, "if(\$act==='delete_account'){");
$hd = $ai === false ? '' : substr($adm, $ai, strpos($adm, "\n  }\n", $ai) - $ai);
$t('handler fonksiyonu strict=false ile cagiriyor', str_contains($hd, "vestra_account_delete((string)(\$_POST['uid']??''),true,false)"));
$t('handlerda ESKI satir ici kapi ve ilan silme KALMADI (ikinci kopya yok)', !str_contains($hd, "vestra_read_csv('orders.csv')") && !str_contains($hd, 'vestra_save_listings') && !str_contains($hd, 'auth_save_accounts'));
$t('admin.php fonksiyonun dosyasini kendisi yukluyor', str_contains($adm, "require_once __DIR__.'/inc/account_delete.php';"));
$t('eski mesaj kodlari yerinde + yeni iki kod haritada ve handlerda',
   str_contains($hd, "msg=acct_notfound") && str_contains($hd, "msg=acct_has_invoice&n=") && str_contains($hd, "msg=acct_has_orders") && str_contains($hd, "msg=acct_deleted")
   && str_contains($hd, 'msg=acct_backup_failed') && str_contains($hd, 'msg=acct_delete_unverified')
   && str_contains($adm, "'acct_backup_failed'=>") && str_contains($adm, "'acct_delete_unverified'=>"));

$post = sys_get_temp_dir().'/vestra_adel_post_'.bin2hex(random_bytes(4)).'.php';
file_put_contents($post, <<<'PHP'
<?php
define('VESTRA_DATA_DIR', getenv('SB').'/data');
define('VESTRA_ACCOUNTS', getenv('SB').'/data/accounts.json');
define('VESTRA_MESSAGES', getenv('SB').'/data/messages.json');
define('VESTRA_BLOCKED_MESSAGES', getenv('SB').'/data/blocked_messages.json');
define('VESTRA_SAMPLES', getenv('SB').'/data/samples.json');
$_SESSION = [];
session_start();
$_SESSION['vadmin'] = true; $_SESSION['vadmin_csrf'] = 'tok';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin';
$_POST = ['_action' => 'delete_account', 'uid' => getenv('UID'), '_csrf' => 'tok'];
ob_start(); include getenv('ADMIN'); ob_end_clean();
PHP);
$panel = function (string $s, string $uid) use ($post, $root): string {
  return (string)shell_exec('SB='.escapeshellarg($s).' UID='.escapeshellarg($uid).' ADMIN='.escapeshellarg($root.'/vestra/admin.php').' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($post).' 2>&1');
};
$s = $mk(['thread' => 1, 'control' => 1]);
$po = $panel($s, $V);
$t('panel POST: hesap silindi (kapi gecti), kontrol hesaplari yerinde', !in_array($V, $accIds($s), true) && $accIds($s) === [$O, $B, $B2]);
$t('panel POST: ilanlar HESAPLA BIRLIKTE gitti (strict=false; konusma olsa da), kontrol ilani yerinde', $lstIds($s) === ['o1']);
$t('panel POST: uc yedek diskte', glob($s.'/data/accounts.json.bak.*') && glob($s.'/data/deleted-accounts/'.$V.'-*.json') && glob($s.'/data/listings.json.bak-del-*'));
$t('panel POST: PHP uyarisi / fatal yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $po));
$s = $mk(['inv' => 1, 'control' => 1]); $before = $snap($s);
$po = $panel($s, $V);
$t('panel POST: faturali hesap SILINMEDI (kapi panelden de calisiyor), veri ayni, yedek yok', $snap($s) === $before && $baks($s) === [] && !preg_match('/(Warning|Notice|Fatal error|Uncaught)/', $po));
$s = $mk(['ro_deleted' => 1, 'control' => 1]); $before = $snap($s);
$po = $panel($s, $V);
$t('panel POST: yedek yazilamazsa SILINMEDI (eskiden sessizce yedeksiz silerdi)', $snap($s) === $before && in_array($V, $accIds($s), true));
$s = $mk(['control' => 1]); $before = $snap($s);
$po = $panel($s, 'zzzz9999zzzz9999');
$t('panel POST: olmayan id -> hicbir sey degismedi', $snap($s) === $before);

echo "\n== 9. footprint ile kapi esliği (ayni olcut, ikinci kopya ayrismasin) ==\n";
$src = (string)file_get_contents($root.'/vestra/inc/account_delete.php');
$fcode = preg_replace('~/\*.*?\*/~s', '', (string)file_get_contents($root.'/.github/workflows/seller-products.yml'));
$t('kapali durum listesi: fonksiyon ile footprint probe AYNI',
   str_contains($src, "['completed','cancelled','refunded']") && str_contains($fcode, "['completed', 'cancelled', 'refunded']"));
$t('faturali siparis saymasi AYNI mantik', str_contains($src, 'count(vestra_invoices_for_ref($ref))>0') && str_contains($fcode, 'count($invs) > 0'));
$t('durumu orders.csv satirindan okuma AYNI (bilinen kusur bilerek ayni: panelin cevabi degismesin)', str_contains($src, "(\$o['status']??'')") && str_contains($fcode, "(\$o['status'] ?? '')"));
$t('fonksiyon kendi bagimliliklarini yukluyor (KURAL 15)',
   str_contains($src, "require_once __DIR__.'/products.php';") && str_contains($src, "require_once __DIR__.'/auth.php';")
   && str_contains($src, "require_once __DIR__.'/orders.php';") && str_contains($src, "require_once __DIR__.'/invoice.php';")
   && str_contains($src, "require_once __DIR__.'/messages.php';") && str_contains($src, "require_once __DIR__.'/samples.php';"));

@unlink($runner); @unlink($post);
echo "\n".($bad ? "BASARISIZ: {$ok} iddia gecti, {$bad} dustu" : "hepsi yesil, {$ok} ok")."\n";
exit($bad ? 1 : 0);
