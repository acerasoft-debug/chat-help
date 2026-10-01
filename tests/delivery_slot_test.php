<?php
/* SONRAKI TESLIMAT YUVASI (operator, 29 Eyl 2026: "trackinglerde ikinci
 * lieferung icin yer ac ayrica").
 *
 * Tutulan olgular, iki yon:
 *   - teslimatlar NUMARALI ve KAYITTAN turer (onceki paketler + gecerli numara);
 *     kismi pakette bir SONRAKI yuva bos acilir, alici "Teslimat 2: henuz
 *     cikmadi" gorur. Tek paketli sipariste eski tek satir AYNEN kalir.
 *   - yeni paket yazilirken onceki paket -- kismi isaretli olmasa bile --
 *     KORUNUR; ayni numara ikinci kez teslimat olamaz; tasiyici/servis miras
 *     alinmaz. Numara duzeltmesi (eski yol) gunluge "onceki paket" BIRAKMAZ.
 *   - siparisi tamamlayan paket durumu 'shipped' yapar ve alici "teslim aldim"
 *     diyebilir; "daha gelecek" paketi durumu degistirmez.
 *   - mektup ALICININ dilinde: kismi / bir kismi daha / KALANI (siparis tamam).
 *     Tek karar noktasi vestra_tpl_order_parcel_letter(); gonderim tek govde
 *     vestra_order_parcel_notify() -- panelin yuvasi, durum formu, satici ve
 *     is akisi AYNI govdeyi cagirir. Damga yalniz basarili gonderimden sonra.
 * Posta basari yolu GERCEK mail() ile olculuyor: alt surec sendmail_path'i bir
 * yakalayiciya yonlendiriyor, yani mektup gercekten kuruluyor ve dosyaya dusuyor. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };
$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vestra_slot_'.bin2hex(random_bytes(4));
mkdir($sb.'/data', 0777, true);
file_put_contents($sb.'/data/accounts.json', '[]');
define('VESTRA_DATA_DIR', $sb.'/data');
define('VESTRA_ACCOUNTS', $sb.'/data/accounts.json');
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/email_templates.php';

$T1 = '1ZX0000A6800000011'; $T2 = '1ZX0000A6800000012'; $T3 = '1ZX0000A6800000013';
$HEAD = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$orderRow = fn(string $ref, string $email = 'buyer.probe@example.org') =>
    ['2026-09-18T10:00:00+00:00', $ref, 'Probe Test SL', '', 'Buyer Probe Name', $email, 'ES', '', '2x ABC @10.00', '20', '0', '20', '20', 'Payment: Bank transfer.', '', '', '', '', '0', ''];
$writeOrders = function (string $dir, array $rows) use ($HEAD) {
    $h = fopen($dir.'/orders.csv', 'w'); fputcsv($h, $HEAD, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($h, $r, ',', '"', '\\');
    fclose($h);
};
$put = fn(array $st) => file_put_contents($sb.'/data/order_statuses.json', json_encode($st));
$get = fn() => json_decode((string)file_get_contents($sb.'/data/order_statuses.json'), true) ?: [];

echo "== 1. okuyucu: numarali teslimatlar + sonraki yuva ==\n";
$s = vestra_order_shipment(['tracking' => $T1]);
$t('tek paket, kismi degil: 1 teslimat, yuva YOK, blok BOS (eski tek satir kalir)',
   count($s['deliveries']) === 1 && $s['next_n'] === 0 && vestra_order_deliveries_html($s) === '');
$s = vestra_order_shipment(['tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1, 'at' => '2026-09-29T07:22:00+00:00']]]);
$t('kismi tek paket: Teslimat 1 gecerli, Teslimat 2 BOS yuva',
   count($s['deliveries']) === 1 && $s['deliveries'][0]['n'] === 1 && $s['deliveries'][0]['current'] === true && $s['next_n'] === 2);
$t('gecerli paketin tarihi gunlukten', $s['deliveries'][0]['at'] === '2026-09-29T07:22:00+00:00');
$s = vestra_order_shipment(['tracking' => $T2, 'ship_partial_trk' => $T2, 'parcels' => [['tracking' => $T1], ['tracking' => $T2]]]);
$t('iki kismi paket: 1 ve 2, sonraki yuva 3', count($s['deliveries']) === 2 && $s['deliveries'][0]['tracking'] === $T1
   && $s['deliveries'][1]['n'] === 2 && $s['deliveries'][1]['current'] && $s['next_n'] === 3);
$s = vestra_order_shipment(['tracking' => $T2, 'parcels' => [['tracking' => $T1], ['tracking' => $T1], 'bozuk'],
                            'history' => [['status' => 'shipped', 'at' => '2026-10-15T09:00:00+00:00']]]);
$t('son paket: 1 (onceki) + 2 (gecerli), yuva YOK, cift kayit tekillesti',
   count($s['deliveries']) === 2 && $s['next_n'] === 0 && $s['deliveries'][0]['tracking'] === $T1);
$t('gunlukte olmayan son paketin tarihi "shipped" tarihcesinden', $s['deliveries'][1]['at'] === '2026-10-15T09:00:00+00:00');
$s = vestra_order_shipment(['status' => 'paid']);
$t('numara yoksa teslimat yok', $s['deliveries'] === [] && $s['next_n'] === 0);

echo "\n== 2. yazici: yeni paket onceki paketi KORUR ==\n";
$put(['VES-A' => ['status' => 'shipped', 'tracking' => $T1, 'ship_carrier' => 'ups', 'ship_service' => 'Express Saver',
                  'history' => [['status' => 'shipped', 'at' => '2026-09-20T10:00:00+00:00', 'by' => 'admin']]],
      'VES-B' => ['status' => 'shipped', 'tracking' => $T1]]);
$r = vestra_order_set_shipment('VES-A', $T2, null, null, false, true);
$g = $get()['VES-A'] ?? [];
$t('yeni paket yazildi', !empty($r['ok']) && ($g['tracking'] ?? '') === $T2);
$t('KISMI OLMAYAN onceki paket gunluge alindi (tasiyici, servis, cikis tarihi ile)',
   count((array)($g['parcels'] ?? [])) === 1 && ($g['parcels'][0]['tracking'] ?? '') === $T1
   && ($g['parcels'][0]['service'] ?? '') === 'Express Saver' && ($g['parcels'][0]['at'] ?? '') === '2026-09-20T10:00:00+00:00');
$t('servis yeni pakete MIRAS kalmadi, tasiyici numaradan cozuldu',
   !isset($g['ship_service']) && $r['shipment']['deliveries'][1]['carrier_name'] === 'UPS' && $r['shipment']['deliveries'][1]['service'] === '');
$t('okuyucu: Teslimat 1 = onceki, Teslimat 2 = yeni', $r['shipment']['deliveries'][0]['tracking'] === $T1 && $r['shipment']['deliveries'][1]['tracking'] === $T2);
$snap = sha1_file($sb.'/data/order_statuses.json');
$r1 = vestra_order_set_shipment('VES-A', ' 1zx0 000a6800000011 ', null, null, false, true);
$r2 = vestra_order_set_shipment('VES-A', $T2, null, null, false, true);
$r3 = vestra_order_set_shipment('VES-A', '', null, null, false, true);
$t('ayni numara ikinci kez teslimat OLAMAZ (onceki ve gecerli), numarasiz yeni paket reddedilir; hicbir sey yazilmadi',
   empty($r1['ok']) && str_contains((string)$r1['error'], '1. teslimat') && empty($r2['ok']) && str_contains((string)$r2['error'], '2. teslimat')
   && empty($r3['ok']) && sha1_file($sb.'/data/order_statuses.json') === $snap);
vestra_order_set_shipment('VES-B', $T3, null, null);
$sh = vestra_order_shipment($get()['VES-B'] ?? null);
$t('KONTROL: eski yol (yeni paket degil) = DUZELTME, "onceki paket" birakmaz', $sh['earlier'] === [] && count($sh['deliveries']) === 1);

echo "\n== 3. vestra_order_add_parcel ==\n";
$writeOrders($sb.'/data', [$orderRow('VES-P'), $orderRow('VES-M'), $orderRow('VES-C'), $orderRow('VES-D'), $orderRow('VES-K'), $orderRow('VES-S')]);
$put(['VES-P' => ['status' => 'to_vestra', 'tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1, 'at' => '2026-09-29T07:22:00+00:00']]],
      'VES-M' => ['status' => 'paid', 'tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1]]],
      'VES-C' => ['status' => 'cancelled', 'tracking' => $T1],
      'VES-D' => ['status' => 'delivered', 'tracking' => $T1],
      'VES-K' => ['status' => 'completed', 'tracking' => $T1],
      'VES-S' => ['status' => 'shipped', 'tracking' => $T1]]);
$r = vestra_order_add_parcel('VES-P', $T2, '', '', false);
$g = $get()['VES-P'] ?? [];
$last = end($g['history']);
$t('son paket: tamam, Teslimat 2', !empty($r['ok']) && $r['n'] === 2 && $r['status_before'] === 'to_vestra');
$t('siparisi tamamlayan paket durumu SHIPPED yapti (alici teslimi onaylayabilir)',
   ($g['status'] ?? '') === 'shipped' && $r['status_after'] === 'shipped' && !empty($g['shipped_at']));
$t('kismi isaret kalkti, Teslimat 1 duruyor, bos yuva yok',
   !isset($g['ship_partial_trk']) && $r['shipment']['partial'] === false && count($r['shipment']['deliveries']) === 2 && $r['shipment']['next_n'] === 0);
$t('tarihce: shipped + "Delivery 2 … (completes the order)"', ($last['status'] ?? '') === 'shipped' && str_contains((string)($last['note'] ?? ''), 'Delivery 2: '.$T2.' (completes'));
$r = vestra_order_add_parcel('VES-M', $T2, 'ups', 'Standard', true);
$g = $get()['VES-M'] ?? [];
$t('"daha gelecek": durum DEGISMEDI (paid), yeni paket kismi, sonraki yuva 3',
   !empty($r['ok']) && ($g['status'] ?? '') === 'paid' && $r['shipment']['partial'] && $r['shipment']['next_n'] === 3
   && ($g['ship_partial_trk'] ?? '') === $T2);
$t('"daha gelecek" tarihcede notlu, durum ayni', str_contains((string)(end($g['history'])['note'] ?? ''), '(more to follow)') && (end($g['history'])['status'] ?? '') === 'paid');
$snap = sha1_file($sb.'/data/order_statuses.json');
$rc = vestra_order_add_parcel('VES-C', $T2); $rd = vestra_order_add_parcel('VES-D', $T2); $rk = vestra_order_add_parcel('VES-K', $T2);
$rs = vestra_order_add_parcel('VES-S', $T2, '', '', true); $rx = vestra_order_add_parcel('VES-YOK', $T2);
$t('iptal / teslim edilmis / tamamlanmis sipariste YAZILMAZ', empty($rc['ok']) && empty($rd['ok']) && empty($rk['ok']));
$t("'shipped' siparise \"daha gelecek\" DENEMEZ (alici su an teslimi onaylayabiliyor)", empty($rs['ok']) && str_contains((string)$rs['error'], 'shipped'));
$t('olmayan siparis reddedilir; reddedilenlerin hicbiri bir sey yazmadi',
   empty($rx['ok']) && str_contains((string)$rx['error'], 'bulunamadi') && sha1_file($sb.'/data/order_statuses.json') === $snap);
$r = vestra_order_add_parcel('VES-S', $T2);
$t("'shipped' siparise EK paket (son) yazilabilir; durum shipped kalir, ilk paket korunur",
   !empty($r['ok']) && $r['status_after'] === 'shipped' && count($r['shipment']['deliveries']) === 2);

echo "\n== 4. mektup: kismi / bir kismi daha / KALANI (5 dil) ==\n";
$final = vestra_order_shipment(['tracking' => $T2, 'parcels' => [['tracking' => $T1, 'carrier' => 'ups']]]);
$part  = vestra_order_shipment(['tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1]]]);
$more  = vestra_order_shipment(['tracking' => $T2, 'ship_partial_trk' => $T2, 'parcels' => [['tracking' => $T1], ['tracking' => $T2]]]);
$single = vestra_order_shipment(['tracking' => $T1]);
[$s1] = vestra_tpl_order_parcel_letter('es', 'Ana', 'VES-X', $part, true);
[$s2] = vestra_tpl_order_parcel_letter('es', 'Ana', 'VES-X', $final, true);
[$s3, $b3] = vestra_tpl_order_parcel_letter('es', 'Ana', 'VES-X', $single, true);
$t('secici: kismi -> "parte de su pedido"', str_starts_with($s1, 'VESTRA — parte de su pedido VES-X'));
$t('secici: onceki paketli son paket -> "el resto de su pedido" (alicinin dili)', str_starts_with($s2, 'VESTRA — el resto de su pedido VES-X'));
$t('secici: tek paket -> eski "gonderildi" mektubu AYNEN (Ingilizce)', $s3 === 'VESTRA — your order VES-X has shipped' && str_contains($b3, 'Good news'));
$lead = ['en' => 'The remaining items of your order VES-X', 'fr' => 'Les articles restants de votre commande VES-X',
         'es' => 'Los artículos restantes de su pedido VES-X', 'it' => 'Gli articoli rimanenti del suo ordine VES-X',
         'de' => 'Die übrigen Artikel Ihrer Bestellung VES-X'];
$restPara = ['en' => 'separate shipment', 'fr' => 'envoi séparé', 'es' => 'envío aparte', 'it' => 'spedizione separata', 'de' => 'separaten Sendung'];
$earlierLbl = ['en' => 'Earlier parcel', 'fr' => 'Colis précédent', 'es' => 'Paquete anterior', 'it' => 'Pacco precedente', 'de' => 'Früheres Paket'];
foreach ($lead as $lg => $w) {
    [$sub, $body, $opts] = vestra_tpl_order_rest_shipped($lg, 'Ana Test', 'VES-X', $final, true);
    $t("{$lg}: KALANI mektubu: acilis + onceki paket numara ve baglantisiyla",
       str_contains($body, $w) && str_contains($body, $earlierLbl[$lg].': UPS '.$T1.' — https://www.ups.com/track?tracknum='.$T1));
    $t("{$lg}: 'kalani ayri pakette gelecek' paragrafi YOK, teslim onayi satiri siparis sayfasiyla VAR",
       !str_contains($body, $restPara[$lg]) && str_contains($body, 'https://vestrasales.com/buyer?tab=orders&view=VES-X'));
    $t("{$lg}: kutuda onceki paket satiri, ham yer tutucu yok",
       end($opts['rows'])['label'] === $earlierLbl[$lg] && !preg_match('/\{[a-z_]+\}/', $sub.$body));
}
[, $body] = vestra_tpl_order_rest_shipped('en', 'Ana', 'VES-X', $final, true);
$t('en: teslim onayi ancak TUM paketler gelince isteniyor', str_contains($body, 'Once all parcels have arrived'));
[, $body, $opts] = vestra_tpl_order_rest_shipped('es', 'Ana', 'VES-X', $final, false);
$t('hesabi yoksa: onay satiri yok, "cevap yazin" var', !str_contains($body, 'confirme') && str_contains($body, 'responda simplemente'));
[$sub, $body] = vestra_tpl_order_parcel_letter('en', 'Ana', 'VES-X', $more, true);
$t('ikinci KISMI paket: "Another part", kalan paragrafi + onceki paket', str_contains($body, 'Another part of your order VES-X')
   && str_contains($body, 'separate shipment') && str_contains($body, 'Earlier parcel: UPS '.$T1));
[, $body] = vestra_tpl_order_parcel_letter('es', 'Ana', 'VES-X', $more, true);
$t('es: "Otra parte de su pedido"', str_contains($body, 'Otra parte de su pedido VES-X'));
[, $body] = vestra_tpl_order_rest_shipped('ro', 'Ana', 'VES-X', $final, true);
$t('bilinmeyen dil Ingilizceye duser', str_contains($body, 'The remaining items of your order'));

echo "\n== 5. gonderim: vestra_order_parcel_notify (GERCEK mail(), yakalayici) ==\n";
/* Kum havuzu: vestra'nin KOPYASI, kendi data/'si, mail acik; sendmail_path bir
   yakalayici. Mektup gercekten kuruluyor ve dosyaya dusuyor. */
$cp = $sb.'/site';
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($cp).' && rm -rf '.escapeshellarg($cp.'/data').' && mkdir '.escapeshellarg($cp.'/data'), $o2, $rcCp);
$t('kum havuzu kuruldu', $rcCp === 0);
file_put_contents($cp.'/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>true,'mail_from'=>'support@vestrasales.com'];\n");
$writeOrders($cp.'/data', [$orderRow('VES-N'), $orderRow('VES-NOM', '')]);
file_put_contents($cp.'/data/accounts.json', json_encode([['id' => 'acc-probe', 'email' => 'buyer.probe@example.org', 'type' => 'buyer',
    'lang' => 'es', 'status' => 'active', 'kyb_status' => 'approved', 'name' => 'Buyer Probe Name', 'company' => 'Probe Test SL']]));
file_put_contents($cp.'/data/order_statuses.json', json_encode([
    'VES-N'   => ['status' => 'shipped', 'tracking' => $T2, 'parcels' => [['tracking' => $T1, 'carrier' => 'ups']]],
    'VES-NOM' => ['status' => 'shipped', 'tracking' => $T2]]));
$mailLog = $sb.'/mail.log';
$php = function (string $code) use ($cp, $mailLog): string {
    $f = $cp.'/_t_'.bin2hex(random_bytes(3)).'.php';
    file_put_contents($f, "<?php\nchdir(".var_export($cp, true).");\n".$code);
    $cmd = escapeshellarg(PHP_BINARY).' -d '.escapeshellarg('sendmail_path=cat >> '.$mailLog).' '.escapeshellarg($f).' 2>&1';
    return (string)shell_exec($cmd);
};
$notify = fn(string $ref, bool $force = false) => $php('require "inc/products.php"; require_once "inc/auth.php"; require_once "inc/orders.php";
echo json_encode(vestra_order_parcel_notify('.var_export($ref, true).', null, "", '.($force ? 'true' : 'false').'));');
$out = $notify('VES-N');
$res = json_decode(trim((string)substr($out, strrpos($out, '{'))), true) ?: [];
$log = (string)@file_get_contents($mailLog);
$subj = preg_match('/Subject: =\?UTF-8\?B\?([^?]+)\?=/', $log, $mm) ? base64_decode($mm[1]) : '';
$t('GONDERILDI (mail() yakalayiciya dustu), alicinin dilinde (es)', !empty($res['sent']) && ($res['lang'] ?? '') === 'es' && str_contains($log, 'To: buyer.probe@example.org'));
$t('konu gercek mektupta: "el resto de su pedido"', str_starts_with($subj, 'VESTRA — el resto de su pedido VES-N'));
$st = json_decode((string)file_get_contents($cp.'/data/order_statuses.json'), true);
$t('damga basarili gonderimden sonra yazildi ve geri okundu', !empty($res['stamped']) && !empty($st['VES-N']['ship_notified'][$T2]));
$out = $notify('VES-N');
$res2 = json_decode(trim((string)substr($out, strrpos($out, '{'))), true) ?: [];
$t('ayni pakete ikinci mektup GITMEZ (damgali)', !empty($res2['skipped']) && empty($res2['sent']) && substr_count((string)file_get_contents($mailLog), 'Subject:') === 1);
$out = $notify('VES-N', true);
$t('force=true (cagiran kendi kosulunu verdi) yine gonderir', substr_count((string)file_get_contents($mailLog), 'Subject:') === 2);
$out = $notify('VES-NOM');
$res3 = json_decode(trim((string)substr($out, strrpos($out, '{'))), true) ?: [];
$t('e-postasi olmayan siparis: gonderilmez, sebep yazili', empty($res3['sent']) && str_contains((string)($res3['error'] ?? ''), 'e-postasi yok'));
$t('PHP uyarisi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error)/', $out));

echo "\n== 6. alici / satici sayfasi (ayri PHP sureci, dil basina) ==\n";
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
$stPart  = ['status' => 'paid', 'tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1]]];
$stFinal = ['status' => 'shipped', 'tracking' => $T2, 'parcels' => [['tracking' => $T1]]];
$h = $render('de', 'buyer', $stPart);
$t('alici (de): kismi satiri + "Lieferung 1" baglantili + "Lieferung 2: Noch nicht versandt"',
   str_contains($h, 'Teillieferung') && str_contains($h, 'Lieferung 1:') && str_contains($h, 'tracknum='.$T1)
   && str_contains($h, 'Lieferung 2:') && str_contains($h, 'Noch nicht versandt'));
$t('alici (de): kismi sipariste "teslim aldim" dugmesi YOK', !str_contains($h, 'confirm_receipt'));
$h = $render('en', 'buyer', $stFinal);
$t('alici (en): iki teslimat, IKI baglanti, bos yuva YOK', str_contains($h, 'Delivery 1:') && str_contains($h, 'Delivery 2:')
   && str_contains($h, 'tracknum='.$T1) && str_contains($h, 'tracknum='.$T2) && !str_contains($h, 'Not shipped yet'));
$t('alici (en): siparis tamamlaninca "teslim aldim" dugmesi VAR (yapi degisikligi onu kaybetmedi)', str_contains($h, 'confirm_receipt'));
$h = $render('en', 'buyer', ['status' => 'shipped', 'tracking' => $T1]);
$t('KONTROL: tek paketli sipariste eski "Tracking number" satiri, numarali blok YOK, dugme VAR',
   str_contains($h, 'Tracking number') && !str_contains($h, 'Delivery 1') && str_contains($h, 'confirm_receipt'));
$h = $render('ar', 'buyer', $stPart);
$t('alici (ar): numarali satir kendi dilinde, PHP uyarisi yok', str_contains($h, 'الشحنة 1') && !preg_match('/(Warning|Notice|Deprecated|Fatal error)/', $h));
$h = $render('en', 'seller', $stPart);
$t('satici: form + bekleyen "Delivery 2" yuvasi', str_contains($h, 'update_order_note') && str_contains($h, 'Delivery 2:') && str_contains($h, 'Not shipped yet'));
$c = vestra_order_deliveries_html(vestra_order_shipment($stPart), true);
$t('alici listesi (kisa): "Delivery 1: <baglanti> · Delivery 2: Not shipped yet"', str_contains($c, 'Delivery 1: <a') && str_contains($c, ' · Delivery 2: Not shipped yet'));

echo "\n== 7. panel: yuva cizimi ve POST (admin.php kum havuzunda) ==\n";
$writeOrders($cp.'/data', [$orderRow('VES-SLOT'), $orderRow('VES-FULL'), $orderRow('VES-CANC'), $orderRow('VES-ST')]);
file_put_contents($cp.'/data/order_statuses.json', json_encode([
    'VES-SLOT' => ['status' => 'to_vestra', 'tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1, 'at' => '2026-09-29T07:22:00+00:00']],
                   'ship_notified' => [$T1 => '2026-09-29T07:22:27+00:00']],
    'VES-FULL' => ['status' => 'shipped', 'tracking' => $T1],
    'VES-CANC' => ['status' => 'cancelled', 'tracking' => $T1],
    'VES-ST'   => ['status' => 'paid', 'tracking' => $T1, 'ship_partial_trk' => $T1, 'parcels' => [['tracking' => $T1]]]]));
$view = fn(string $ref) => $php('error_reporting(E_ALL); ini_set("display_errors","1");
session_start(); $_SESSION["vadmin"]=true;
$_GET=["tab"=>"orders","view"=>'.var_export($ref, true).']; $_SERVER["REQUEST_METHOD"]="GET";
$_SERVER["REQUEST_URI"]="/admin?tab=orders"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; $_SERVER["HTTP_HOST"]="localhost";
ob_start(); include "admin.php"; echo ob_get_clean();');
$post = fn(array $f) => $php('session_start(); $_SESSION["vadmin"]=true; $_SESSION["vadmin_csrf"]="tok";
$_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["REQUEST_URI"]="/admin"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; $_SERVER["HTTP_HOST"]="localhost";
$_POST='.var_export($f + ['_csrf' => 'tok'], true).';
ob_start(); include "admin.php"; ob_end_clean();');
$h = $view('VES-SLOT');
$t('panel cizildi (giris formu degil)', strlen($h) > 5000 && !str_contains($h, 'name="pass"'));
$t('kismi sipariste "Delivery 2" yuvasi ACIK (details icinde degil)', str_contains($h, 'name="_action" value="order_parcel"')
   && str_contains($h, 'Save delivery 2') && !str_contains($h, 'Add another parcel'));
$t('Teslimat 1 listede, mektup damgasiyla', str_contains($h, '<b>Delivery 1</b>') && str_contains($h, 'letter sent 2026-09-29'));
$t('yuvada "daha gelecek" ve "alici e-postasi (isaretli)" kutulari', str_contains($h, 'name="more" value="1"') && str_contains($h, 'name="notify" value="1" checked'));
$t('damgali pakette "Send letter" dugmesi YOK', !str_contains($h, 'value="order_parcel_mail"'));
$t('panel: PHP uyarisi yok', !preg_match('/\b(Warning|Fatal error|Deprecated)\b/', $h));
$h = $view('VES-FULL');
$t('tek paketli shipped sipariste "+ Add another parcel (delivery 2)" katlanmis, "daha gelecek" kutusu YOK',
   str_contains($h, 'Add another parcel (delivery 2)') && str_contains($h, 'value="order_parcel"') && !str_contains($h, 'name="more"'));
$h = $view('VES-CANC');
$t('iptal edilmis sipariste yuva YOK', !str_contains($h, 'value="order_parcel"'));
@unlink($mailLog);
$post(['_action' => 'order_parcel', 'ref' => 'VES-SLOT', 'tracking' => strtolower($T2), 'ship_carrier' => '', 'ship_service' => '', 'notify' => '1']);
$st = json_decode((string)file_get_contents($cp.'/data/order_statuses.json'), true);
$t('POST: Teslimat 2 yazildi, durum shipped, Teslimat 1 duruyor',
   ($st['VES-SLOT']['tracking'] ?? '') === $T2 && ($st['VES-SLOT']['status'] ?? '') === 'shipped'
   && ($st['VES-SLOT']['parcels'][0]['tracking'] ?? '') === $T1 && !isset($st['VES-SLOT']['ship_partial_trk']));
$log = (string)@file_get_contents($mailLog);
$subj = preg_match('/Subject: =\?UTF-8\?B\?([^?]+)\?=/', $log, $mm) ? base64_decode($mm[1]) : '';
$t('POST: alici "kalani yola cikti" mektubunu KENDI dilinde aldi, paket damgalandi',
   str_starts_with($subj, 'VESTRA — el resto de su pedido VES-SLOT') && !empty($st['VES-SLOT']['ship_notified'][$T2]));
$snap = sha1_file($cp.'/data/order_statuses.json');
$post(['_action' => 'order_parcel', 'ref' => 'VES-SLOT', 'tracking' => $T1, 'notify' => '1']);
$t('POST: ayni numara ikinci kez YAZILMAZ, kimseye bir sey gitmez', sha1_file($cp.'/data/order_statuses.json') === $snap
   && substr_count((string)@file_get_contents($mailLog), 'Subject:') === 1);
$post(['_action' => 'order_status', 'ref' => 'VES-ST', 'status' => 'shipped', 'tracking' => $T1]);
$st = json_decode((string)file_get_contents($cp.'/data/order_statuses.json'), true);
$t("durum formu 'shipped' = siparisin tamami yolda: kismi isaret KALKTI", ($st['VES-ST']['status'] ?? '') === 'shipped' && !isset($st['VES-ST']['ship_partial_trk']));

echo "\n== 8. is akisi adimi: next=1 (gercek PHP, kum havuzu) ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "admin_mode == 'ship' }}");
$blk = $a === false ? '' : substr($wf, $a, strpos($wf, "\n      - name:", $a) - $a);
if (!preg_match("~<<'PHPEOF'\n(.*?)\n\s*PHPEOF~s", $blk, $m)) { echo "  HATA php govdesi bulunamadi\n"; exit(1); }
$wphp = preg_replace('/^            /m', '', $m[1]);
$run = str_replace('$doc = getenv("HOME")."/public_html";', '$doc = '.var_export($root.'/vestra', true).';', $wphp);
$t('belge koku satiri bulundu', $run !== $wphp);
$run = "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($sb.'/wf', true).");\ndefine('VESTRA_ACCOUNTS', ".var_export($sb.'/wf/accounts.json', true).");\n"
     . preg_replace('/^<\?php\s*/', '', $run);
@mkdir($sb.'/wf', 0777, true);
file_put_contents($sb.'/wf_run.php', $run);
$writeOrders($sb.'/wf', [$orderRow('VES-WF1')]);
file_put_contents($sb.'/wf/accounts.json', json_encode([['id' => 'acc-probe', 'email' => 'buyer.probe@example.org', 'type' => 'buyer', 'lang' => 'es', 'status' => 'active', 'name' => 'Buyer Probe Name', 'company' => 'Probe Test SL']]));
file_put_contents($sb.'/wf/order_statuses.json', json_encode(['VES-WF1' => ['status' => 'shipped', 'tracking' => $T1, 'ship_service' => 'Express Saver',
    'history' => [['status' => 'shipped', 'at' => '2026-09-20T10:00:00+00:00', 'by' => 'admin']]]]));
$wfRun = function (string $spec, bool $go) use ($sb): array {
    $env = ['SH_REF' => 'VES-WF1', 'SH_SPEC' => $spec, 'SH_GO' => $go ? 'true' : 'false', 'PATH' => getenv('PATH'), 'HOME' => $sb];
    $p = proc_open([PHP_BINARY, $sb.'/wf_run.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $o = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $o];
};
$snap = sha1_file($sb.'/wf/order_statuses.json');
[$rc, $o] = $wfRun('tracking='.$T2.'|next=1|status=shipped', false);
$t('kuru kosu: teslimatlar numarali ("teslimat 1" onceki, "teslimat 2" bu kosu)', $rc === 0 && str_contains($o, 'teslimat 1: UPS '.$T1)
   && str_contains($o, 'teslimat 2: UPS '.$T2.' (bu kosu)'));
$t('kuru kosu: "kalani yola cikti" alicinin dilinde, onceki paket anilmis, ad maskeli',
   str_contains($o, 'dil es -- hesap') && str_contains($o, 'Los artículos restantes de su pedido VES-WF1') && str_contains($o, 'Paquete anterior: UPS '.$T1)
   && !str_contains($o, 'Buyer Probe Name') && str_contains($o, 'Hola B***:'));
$t('kuru kosu: HICBIR SEY YAZILMADI', sha1_file($sb.'/wf/order_statuses.json') === $snap && str_contains($o, 'HICBIR SEY YAZILMADI'));
[$rc, $o] = $wfRun('tracking='.$T2.'|next=1', false);
$t('next=1 ne partial ne shipped: REDDEDILIR', $rc !== 0 && str_contains($o, 'next=1 ya partial=1'));
[$rc, $o] = $wfRun('tracking='.$T1.'|next=1|status=shipped', false);
$t('next=1 ayni numara: REDDEDILIR', $rc !== 0 && str_contains($o, 'zaten 1. teslimat'));
[$rc, $o] = $wfRun('tracking='.$T2.'|next=1|status=shipped', true);
$w = json_decode((string)file_get_contents($sb.'/wf/order_statuses.json'), true)['VES-WF1'] ?? [];
$t('uygula: KISMI OLMAYAN ilk paket korundu, yeni numara gecerli, servis miras kalmadi',
   ($w['tracking'] ?? '') === $T2 && ($w['parcels'][0]['tracking'] ?? '') === $T1 && ($w['parcels'][0]['service'] ?? '') === 'Express Saver' && !isset($w['ship_service']));
$t('uygula: kum havuzunda posta kapali -> REDDETTI, damga YOK, cikis 1', $rc !== 0 && str_contains($o, 'SAGLAYICI REDDETTI') && empty($w['ship_notified']));

echo "\n== 9. kablolama: tek gonderim govdesi, tek secici ==\n";
$strip = fn(string $s) => preg_replace('~/\*.*?\*/~s', '', $s);
$adm = $strip((string)file_get_contents($root.'/vestra/admin.php'));
$sel = $strip((string)file_get_contents($root.'/vestra/seller.php'));
$wcode = $strip($wphp);
$t('panel durum formu: mektup ortak govdeden, shipped kismi isareti kaldiriyor',
   str_contains($adm, "vestra_order_parcel_notify(\$ref, vestra_order_shipment(\$all[\$ref]??null), '', true)") && str_contains($adm, "if(\$st==='shipped') unset(\$all[\$ref]['ship_partial_trk']);"));
$t('satici "gonderildi": mektup ortak govdeden, kismi isaret kalkiyor',
   str_contains($sel, "vestra_order_parcel_notify(\$ref, \$shpNow, '', true)") && str_contains($sel, "unset(\$st[\$ref]['ship_partial_trk']);"));
$t('panel ve satici sablonu DOGRUDAN cagirmiyor (tek secici)', !str_contains($adm, 'vestra_tpl_order_shipped(') && !str_contains($sel, 'vestra_tpl_order_shipped('));
$t('is akisi: onizleme tek seciciden, gonderim ortak govdeden',
   substr_count($wcode, 'vestra_tpl_order_parcel_letter(') === 2 && str_contains($wcode, 'vestra_order_parcel_notify($ref, $after, $lang, true)')
   && !str_contains($wcode, 'vestra_tpl_order_part_shipped(') && !str_contains($wcode, 'vestra_tpl_order_shipped('));
$t('alici listesi numarali kisa blogu kullaniyor', str_contains((string)file_get_contents($root.'/vestra/buyer.php'), 'vestra_order_deliveries_html(vestra_order_shipment($orderSt[$ref] ?? null), true)'));
foreach (['de', 'fr', 'es', 'it', 'pt', 'ru', 'ar', 'ja'] as $lg) {
    $d = include $root.'/vestra/inc/lang/'.$lg.'.php';
    $t("{$lg}: 'Delivery %d' cevrildi ve yer tutucuyu tasiyor", str_contains((string)($d['Delivery %d'] ?? ''), '%d') && ($d['Delivery %d'] ?? '') !== 'Delivery %d');
}

exec('rm -rf '.escapeshellarg($sb));
echo "\n".($bad === 0 ? "delivery_slot_test: {$ok} iddia gecti\n" : "delivery_slot_test: {$bad} HATA / {$ok} gecti\n");
exit($bad === 0 ? 0 : 1);
