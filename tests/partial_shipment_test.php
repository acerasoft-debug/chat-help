<?php
/* KISMI GONDERIM (operator, 29 Eyl 2026: iki UPS numarasi, iki siparis --
 * "bu gonderim numaralarini ilgili siparislere ekle, siparislerin bir kisminin
 * ciktigini belirt ve musterilere email gonder").
 *
 * Tutulan olgular, iki yon:
 *   - kismi isaret bir PAKETE bagli: numara degisince (dort ayri yazicidan
 *     herhangi biri) isaret kendiliginden duser, onceki paket kaybolmaz
 *   - durum DEGISMEZ: 'shipped' olsaydi alicinin sayfasinda "teslim aldim"
 *     dugmesi cikar ve yarim bir siparis kapatilabilirdi
 *   - mektup "BIR KISMI yola cikti, kalani ayri pakette" der; hangi kalemlerin
 *     pakette oldugunu SOYLEMEZ (operator paket listesi vermedi -- KURAL 3)
 *   - ayni pakete ikinci mektup gitmez; damga YALNIZ basarili gonderimden sonra
 *   - onizleme herkese acik kutuge musterinin ADINI yazmaz
 * Is akisi adimi IS AKISININ ICINDEKI GERCEK PHP'den cikarilip kum havuzunda
 * KOSTURULUYOR (kaynak taramasi degil). */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };
$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vestra_partial_'.bin2hex(random_bytes(4));
mkdir($sb.'/data', 0777, true);
file_put_contents($sb.'/data/accounts.json', '[]');
define('VESTRA_DATA_DIR', $sb.'/data');
define('VESTRA_ACCOUNTS', $sb.'/data/accounts.json');
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/email_templates.php';
require_once $root.'/vestra/inc/push_texts.php';

$T1 = '1ZRJ70256819041340'; $T2 = '1ZRJ70256833376757'; $T3 = '1ZRJ70256800000009';

echo "== 1. okuyucu: kismi isaret PAKETE bagli ==\n";
$s = vestra_order_shipment(['tracking' => $T1, 'ship_partial_trk' => '1zrj 7025 6819 041340']);
$t('isaretli numara = gecerli numara -> kismi (bosluk/buyuk-kucuk harf fark etmez)', $s['partial'] === true);
$s = vestra_order_shipment(['tracking' => $T2, 'ship_partial_trk' => $T1]);
$t('baska bir numara yazildiysa isaret DUSER (panel/satici formu hatirlamak zorunda degil)', $s['partial'] === false);
$s = vestra_order_shipment(['ship_partial_trk' => $T1]);
$t('numara yoksa kismi DEGIL', $s['partial'] === false);
$s = vestra_order_shipment(['tracking' => $T1]);
$t('isaret hic yoksa kismi DEGIL (eski kayitlar aynen)', $s['partial'] === false && $s['earlier'] === []);
$s = vestra_order_shipment(['tracking' => $T2, 'parcels' => [
    ['tracking' => $T1, 'carrier' => '', 'service' => '', 'at' => '2026-09-29T08:00:00+00:00'],
    ['tracking' => $T2, 'carrier' => '', 'service' => '', 'at' => '2026-10-10T08:00:00+00:00'], 'bozuk']]);
$t('onceki paket listede, gecerli paket listede DEGIL', count($s['earlier']) === 1 && $s['earlier'][0]['tracking'] === $T1);
$t('onceki paketin tasiyicisi numaradan cozuluyor (UPS) ve baglantisi var',
   $s['earlier'][0]['carrier_name'] === 'UPS' && str_contains($s['earlier'][0]['url'], 'tracknum='.$T1));
$t('onceki paketin tarihi korunuyor', $s['earlier'][0]['at'] === '2026-09-29T08:00:00+00:00');

echo "\n== 2. yazici: vestra_order_set_shipment (kum havuzu) ==\n";
$put = function (array $st) use ($sb) { file_put_contents($sb.'/data/order_statuses.json', json_encode($st)); };
$get = function () use ($sb) { return json_decode((string)file_get_contents($sb.'/data/order_statuses.json'), true) ?: []; };
$put(['VES-S1' => ['status' => 'to_vestra', 'history' => [['status' => 'to_vestra', 'at' => '2026-09-23T10:00:00+00:00', 'by' => 'admin']]],
      'VES-S2' => ['status' => 'paid']]);
$r = vestra_order_set_shipment('VES-S1', ' 1zrj 70256819041340 ', 'ups', null, true);
$g = $get();
$t('yazildi, geri okumada kismi', !empty($r['ok']) && $r['shipment']['partial'] === true);
$t('numara normalize edildi', ($g['VES-S1']['tracking'] ?? '') === $T1 && ($g['VES-S1']['ship_partial_trk'] ?? '') === $T1);
$t('DURUM DEGISMEDI (to_vestra)', ($g['VES-S1']['status'] ?? '') === 'to_vestra' && count((array)($g['VES-S1']['history'] ?? [])) === 1);
$t('paket gunlugunde bir kayit, tarihli', count($g['VES-S1']['parcels'] ?? []) === 1 && ($g['VES-S1']['parcels'][0]['tracking'] ?? '') === $T1
   && strtotime((string)($g['VES-S1']['parcels'][0]['at'] ?? '')) > 0);
$at0 = (string)($g['VES-S1']['parcels'][0]['at'] ?? 'yok');
sleep(1);
vestra_order_set_shipment('VES-S1', $T1, null, 'Express Saver', true);
$g = $get();
$t('ayni paket ikinci kez: gunluk COGALMAZ, cikis tarihi KORUNUR, servis guncellenir',
   count((array)($g['VES-S1']['parcels'] ?? [])) === 1 && ($g['VES-S1']['parcels'][0]['at'] ?? '') === $at0 && ($g['VES-S1']['parcels'][0]['service'] ?? '') === 'Express Saver');
$before = file_get_contents($sb.'/data/order_statuses.json');
$r = vestra_order_set_shipment('VES-S2', null, null, null, true);
$t('numarasiz "kismi" REDDEDILIR ve hicbir sey yazilmaz', empty($r['ok']) && file_get_contents($sb.'/data/order_statuses.json') === $before);
/* Panel/satici formu gibi ESKI dort argumanli bir cagiran yeni numara yaziyor: */
vestra_order_set_shipment('VES-S1', $T2, null, null);
$sh = vestra_order_shipment($get()['VES-S1']);
$t('eski cagiran yeni numara yazinca isaret kendiliginden DUSTU', $sh['partial'] === false && $sh['tracking'] === $T2);
$t('ilk paket KAYBOLMADI (onceki paket olarak duruyor)', count($sh['earlier']) === 1 && $sh['earlier'][0]['tracking'] === $T1);
vestra_order_set_shipment('VES-S1', $T3, null, null, true);
vestra_order_set_shipment('VES-S1', null, null, null, false);
$g = $get();
$t('partial=false isareti kaldirir, gunluk durur', !isset($g['VES-S1']['ship_partial_trk']) && count((array)($g['VES-S1']['parcels'] ?? [])) === 2);

echo "\n== 3. mektup: 'bir kismi yola cikti' (5 dil) ==\n";
$shp = vestra_order_shipment(['tracking' => $T1, 'ship_partial_trk' => $T1]);
$url = $shp['url'];
$words = ['en' => 'Part of your order', 'fr' => 'Une partie de votre commande', 'es' => 'Parte de su pedido',
          'it' => 'Parte del suo ordine', 'de' => 'Ein Teil Ihrer Bestellung'];
$rest  = ['en' => 'separate shipment', 'fr' => 'envoi séparé', 'es' => 'envío aparte', 'it' => 'spedizione separata', 'de' => 'separaten Sendung'];
foreach ($words as $lg => $w) {
    [$sub, $body, $opts] = vestra_tpl_order_part_shipped($lg, 'Ana Test', 'VES-A11C0C97', $shp, true);
    $t("{$lg}: konu ref tasiyor ve 'kismi' diyor", str_contains($sub, 'VES-A11C0C97') && preg_match('/part|parte|partie|Teil/i', $sub) === 1);
    $t("{$lg}: govde KISMI diye aciliyor", str_contains($body, $w.' VES-A11C0C97'));
    $t("{$lg}: numara + takip baglantisi govdede", str_contains($body, $T1) && str_contains($body, $url));
    $t("{$lg}: kalanin AYRI pakette gelecegini soyluyor", str_contains($body, $rest[$lg]));
    $t("{$lg}: ham yer tutucu yok, Turkce harf yok", !preg_match('/\{[a-z_]+\}/', $sub.$body) && !preg_match('/[şğıİŞĞ]/u', $sub.$body));
    $t("{$lg}: ana dugme TAKIP, ikincil siparis sayfasi", ($opts['button']['url'] ?? '') === $url
       && ($opts['button_alt']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=VES-A11C0C97');
}
[$sub, $body] = vestra_tpl_order_part_shipped('en', 'Ana Test', 'VES-X', $shp, true);
/* Konu "part of your order VES-X has shipped" -- tam gonderim konusunun alt dizesini
   DOGAL OLARAK iceriyor; olculecek sey konunun "part of" ile BASLAMASI. */
$t('tam gonderim mektubunun cumleleri YOK (teslim onayi / "Good news"), konu "part of" ile basliyor',
   !str_contains($body, 'confirm receipt') && !str_contains($body, 'Good news') && str_starts_with($sub, 'VESTRA — part of your order VES-X'));
[$sub] = vestra_tpl_order_part_shipped('ro', 'Ana Test', 'VES-X', $shp, true);
$t('bilinmeyen dil Ingilizceye duser', str_contains($sub, 'part of your order'));
[, $body, $opts] = vestra_tpl_order_part_shipped('es', 'Ana Test', 'VES-X', $shp, false);
$t('hesabi yoksa: hesap satiri yok, "cevap yazin" var, dugme takip', !str_contains($body, 'buyer?tab=orders') && str_contains($body, 'responda simplemente')
   && ($opts['button']['url'] ?? '') === $url && !isset($opts['button_alt']));
[, $body, $opts] = vestra_tpl_order_part_shipped('de', 'Ana Test', 'VES-X', vestra_order_shipment(['tracking' => '1234567890']), true);
$t('tasiyici cozulmemisse baglanti yazilmaz, dugme siparis sayfasi', !str_contains($body, 'Sendung verfolgen:') && str_contains($body, '1234567890')
   && ($opts['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=VES-X');
[, $body] = vestra_tpl_order_part_shipped('fr', '', 'VES-X', $shp, true);
$t('ad yoksa notr hitap (bosluklu virgul yok)', str_starts_with($body, "Bonjour,\n") && !str_contains($body, 'Bonjour ,'));
$tpl = (string)file_get_contents($root.'/vestra/inc/email_templates.php');
$fa = strpos($tpl, 'function vestra_tpl_order_part_shipped('); $fb = strpos($tpl, "\n}\n", $fa);
$fsrc = substr($tpl, $fa, $fb - $fa);
$t('sablon KALEM almiyor (hangi kalemin pakette oldugu tahmin edilmez)', preg_match('/function vestra_tpl_order_part_shipped\(string \$lang, string \$buyerName, string \$ref, array \$shipment, bool \$hasAccount = false\)/', $fsrc) === 1);

echo "\n== 4. son paket: 'gonderildi' mektubu onceki paketi aniyor ==\n";
[, $b1] = vestra_tpl_order_shipped('Ana Test', 'VES-X', $T2, true, vestra_order_shipment(['tracking' => $T2, 'parcels' => [['tracking' => $T1]]]));
$t('onceki kismi paket varsa "siparisi tamamliyor" + onceki numara', str_contains($b1, 'This shipment completes your order') && str_contains($b1, $T1));
[, $b2] = vestra_tpl_order_shipped('Ana Test', 'VES-X', $T2, true, vestra_order_shipment(['tracking' => $T2]));
$t('onceki paket yoksa o cumle YOK', !str_contains($b2, 'completes your order'));

echo "\n== 5. uygulama bildirimi (9 dil) ==\n";
foreach (['en', 'de', 'fr', 'it', 'es', 'pt', 'ru', 'ar', 'ja'] as $lg) {
    $n = vestra_push_compose('order_part_shipped', ['ref' => 'VES-1', 'tracking' => 'UPS '.$T1], $lg);
    $t("{$lg}: baslikta ref, govdede numara", $n && str_contains($n['title'], 'VES-1') && str_contains($n['body'], $T1));
}
$n = vestra_push_compose('order_part_shipped', ['ref' => 'VES-1'], 'en');
$t('siparise baglaniyor, siparis etiketiyle', $n['url'] === '/buyer?tab=orders&view=VES-1' && $n['tag'] === 'order-VES-1');
$t('tam gonderim bildirimi DEGIL', !str_contains(vestra_push_compose('order_part_shipped', [], 'en')['body'], 'Tracking')
   && str_contains(vestra_push_compose('order_part_shipped', [], 'en')['body'], 'rest will follow'));

echo "\n== 6. siparis sayfasi (ayri PHP sureci, dil basina) ==\n";
$render = function (string $lang, string $role, array $st) use ($sb, $root): string {
    $f = $sb.'/render_'.bin2hex(random_bytes(3)).'.php';
    file_put_contents($f, '<?php
define("VESTRA_DATA_DIR", '.var_export($sb.'/data', true).');
define("VESTRA_ACCOUNTS", '.var_export($sb.'/data/accounts.json', true).');
$_GET["lang"] = '.var_export($lang, true).';
require '.var_export($root.'/vestra/inc/products.php', true).';
require_once '.var_export($root.'/vestra/inc/orders.php', true).';
require_once '.var_export($root.'/vestra/inc/invoice.php', true).';
$row = ["ref"=>"VES-R1","timestamp"=>"2026-09-20T10:00:00+00:00","company"=>"Test SL","name"=>"Ana Test","email"=>"ana@test.example","country"=>"Spain","items"=>"2x ABC @10.00","total"=>"20","notes"=>"Payment: Bank transfer."];
echo vestra_render_order_detail($row, '.var_export($st, true).', '.var_export($role, true).', "u1", "/x", "/y");');
    return (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($f).' 2>&1');
};
$stP = ['status' => 'paid', 'tracking' => $T2, 'ship_partial_trk' => $T2, 'parcels' => [['tracking' => $T1, 'at' => '2026-09-29T08:00:00+00:00'], ['tracking' => $T2]]];
$h = $render('de', 'buyer', $stP);
$t('alici (de): kismi satiri kendi dilinde', str_contains($h, 'Teillieferung — die übrigen Artikel folgen'));
$t('alici (de): onceki paket baglantisiyla', str_contains($h, 'Früheres Paket') && str_contains($h, 'tracknum='.$T1));
$t('alici: "teslim aldim" dugmesi YOK (durum paid)', !str_contains($h, 'confirm_receipt'));
$t('alici: PHP uyarisi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error)/', $h));
$h = $render('en', 'seller', $stP);
$t('satici da kismi oldugunu goruyor', str_contains($h, 'Partial shipment — the remaining items will follow'));
$h = $render('en', 'buyer', ['status' => 'paid', 'tracking' => $T1]);
$t('KONTROL: isaretsiz sipariste kismi satiri YOK', !str_contains($h, 'Partial shipment') && str_contains($h, $T1));

echo "\n== 7. sozluk: iki yeni anahtar 8 dilde (KURAL 10) ==\n";
foreach (['de', 'fr', 'es', 'it', 'pt', 'ru', 'ar', 'ja'] as $lg) {
    $d = include $root.'/vestra/inc/lang/'.$lg.'.php';
    $t("{$lg}: iki anahtar dolu", trim((string)($d['Partial shipment — the remaining items will follow in a separate parcel.'] ?? '')) !== ''
                                && trim((string)($d['Earlier parcel'] ?? '')) !== '');
}

echo "\n== 8. is akisi adimi (gercek PHP, kum havuzu) ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "admin_mode == 'ship' }}");
$blk = $a === false ? '' : substr($wf, $a, strpos($wf, "\n      - name:", $a) - $a);
if (!preg_match("~<<'PHPEOF'\n(.*?)\n\s*PHPEOF~s", $blk, $m)) { echo "  HATA php govdesi bulunamadi\n"; exit(1); }
$php = preg_replace('/^            /m', '', $m[1]);
$run = str_replace('$doc = getenv("HOME")."/public_html";', '$doc = '.var_export($root.'/vestra', true).';', $php);
$t('belge koku satiri bulundu (kum havuzuna yonlendirildi)', $run !== $php);
$run = "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($sb.'/wf', true).");\ndefine('VESTRA_ACCOUNTS', ".var_export($sb.'/wf/accounts.json', true).");\n"
     . preg_replace('/^<\?php\s*/', '', $run);
@mkdir($sb.'/wf', 0777, true);
file_put_contents($sb.'/wf_run.php', $run);
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$seed = function (array $st) use ($sb, $head) {
    $h = fopen($sb.'/wf/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
    fputcsv($h, ['2026-09-18T10:00:00+00:00','VES-WF1','Isla Test SL','','Bianca Probe Name','isla.probe@example.org','ES','','2x ABC @10.00','20','0','20','20','Payment: Bank transfer.','','','','','0',''], ',', '"', '\\');
    fclose($h);
    file_put_contents($sb.'/wf/accounts.json', json_encode([['id' => 'acc-isla', 'email' => 'isla.probe@example.org', 'type' => 'buyer', 'lang' => 'es', 'status' => 'active', 'name' => 'Bianca Probe Name', 'company' => 'Isla Test SL']]));
    file_put_contents($sb.'/wf/order_statuses.json', json_encode($st));
};
$wfRun = function (string $spec, bool $go) use ($sb): array {
    $env = ['SH_REF' => 'VES-WF1', 'SH_SPEC' => $spec, 'SH_GO' => $go ? 'true' : 'false', 'PATH' => getenv('PATH'), 'HOME' => $sb];
    $p = proc_open([PHP_BINARY, $sb.'/wf_run.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $o = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $o];
};
$wfState = fn() => json_decode((string)file_get_contents($sb.'/wf/order_statuses.json'), true) ?: [];

$seed(['VES-WF1' => ['status' => 'to_vestra']]);
$snap = sha1_file($sb.'/wf/order_statuses.json');
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1', false);
$t('kuru kosu: basarili, kismi EVET, durum degismiyor', $rc === 0 && str_contains($o, 'kismi     : EVET') && str_contains($o, 'degismiyor (to_vestra)'));
$t('kuru kosu: mektup alicinin HESAP dilinde (es)', str_contains($o, 'dil es -- hesap') && str_contains($o, 'parte de su pedido VES-WF1'));
$t('kuru kosu: UPS baglantisi onizlemede', str_contains($o, 'tracknum='.$T1));
$t('kuru kosu: alicinin ADI kutuge yazilmadi (maskeli)', !str_contains($o, 'Bianca Probe Name') && str_contains($o, 'Hola B***:'));
$t('kuru kosu: adres maskeli', !str_contains($o, 'isla.probe@'));
$t('kuru kosu: HICBIR SEY YAZILMADI', str_contains($o, 'HICBIR SEY YAZILMADI') && sha1_file($sb.'/wf/order_statuses.json') === $snap);
$t('kuru kosu: PHP uyarisi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error)/', $o));
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1|lang=de', false);
$t('lang= hesabin dilini ezer', $rc === 0 && str_contains($o, 'dil de -- spec') && str_contains($o, 'Ein Teil Ihrer Bestellung VES-WF1'));
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1|lang=ro', false);
$t('gecersiz lang REDDEDILIR', $rc !== 0 && str_contains($o, 'gecersiz lang'));
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1|status=shipped', false);
$t('partial=1 + status=shipped CELISKI olarak reddedilir', $rc !== 0 && str_contains($o, 'CELISIYOR'));
[$rc, $o] = $wfRun('partial=1', false);
$t('numarasiz partial=1 reddedilir', $rc !== 0 && str_contains($o, 'takip numarasi ister'));

[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1', true);
$st = $wfState()['VES-WF1'] ?? [];
$t('uygula: numara + kismi isaret kayitta, durum to_vestra KALDI', ($st['tracking'] ?? '') === $T1 && ($st['ship_partial_trk'] ?? '') === $T1 && ($st['status'] ?? '') === 'to_vestra');
$t('uygula: KAYDEDILDI satiri kismi=EVET diyor', str_contains($o, 'kismi=EVET'));
$t('uygula: kum havuzunda posta yok -> REDDETTI, cikis 1', $rc !== 0 && str_contains($o, 'SAGLAYICI REDDETTI'));
$t('damga YALNIZ basarili gonderimden sonra: reddedilen mektup damgalanmadi', empty($st['ship_notified']));
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1', false);
$t('damgasiz paket: yeniden kosu mektubu YINE gonderir (yarim kalan kosu kurtarilir)', str_contains($o, 'mektup    : GIDECEK'));
$all = $wfState(); $all['VES-WF1']['ship_notified'][$T1] = '2026-09-29T09:00:00+00:00';
file_put_contents($sb.'/wf/order_statuses.json', json_encode($all));
[$rc, $o] = $wfRun('tracking='.$T1.'|partial=1', false);
$t('damgali paket: ayni pakete ikinci mektup GITMEZ', $rc === 0 && str_contains($o, 'gitmeyecek (bu paket icin 2026-09-29T09:00:00+00:00 tarihinde gonderildi)'));
[$rc, $o] = $wfRun('tracking='.$T3.'|status=shipped', false);
$t('son paket (status=shipped): "tamamliyor" + onceki numara onizlemede', $rc === 0 && str_contains($o, 'This shipment completes your order') && str_contains($o, $T1));
$t('son paket onizlemesi de ad MASKELI', !str_contains($o, 'Bianca Probe Name') && str_contains($o, 'Hello B***,'));
$t('son paket: kismi DEGIL', str_contains($o, 'kismi     : hayir'));

echo "\n== 9. kablolama ==\n";
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$t('yazici tek fonksiyon; kismi -> true, son paket -> false, diger -> null',
   str_contains($code, "\$isPartial ? true : (\$wantStatus === 'shipped' ? false : null)"));
$t('kismi gonderimde uygulama bildirimi ayri tur', str_contains($code, "\$isPartial ? 'order_part_shipped' : 'order_shipped'"));
$adm = (string)file_get_contents($root.'/vestra/admin.php');
$t('panel siparis sayfasi kismi paketi ve onceki paketi basiyor', str_contains($adm, "!empty(\$vshp['partial'])") && str_contains($adm, "(\$vshp['earlier']??[])"));

exec('rm -rf '.escapeshellarg($sb));
echo "\n".($bad === 0 ? "partial_shipment_test: {$ok} iddia gecti\n" : "partial_shipment_test: {$bad} HATA / {$ok} gecti\n");
exit($bad === 0 ? 0 : 1);
