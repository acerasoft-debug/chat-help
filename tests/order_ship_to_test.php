<?php
/* SİPARİŞİN TESLİMAT ADRESİ + POSTA KODU HER SİPARİŞTE GÖRÜNÜR (operatör, 29 Eyl
 * 2026: "müsterilerin post codu ve teslimat adresi siparislerinde görünsün").
 *
 * Kusur: kasa `Deliver to:` notunu yalnız alıcı fatura adresinden BAŞKA bir adres
 * seçince yazıyor. "Fatura adresiyle aynı" seçilen siparişte adres hiçbir sipariş
 * ekranında yoktu (alıcı/satıcı sayfası, sipariş PDF'i, panel listesi); panel
 * "same as billing — nothing on file" deyip adresi göstermiyordu. Fatura ise hesaba
 * düşüp adresi basıyordu -- aynı siparişin belgesinde duran adres siparişte yoktu.
 *
 * Tek çözücü vestra_order_ship_to(): siparişin notu > hesabın fatura adresi (posta
 * kodu/şehir alanları dahil). Fatura da ondan okur. İki yön: posta kodu olan adres
 * uyarı almaz, olmayan alır; BAE/Katar muaf; telefon numarası posta kodu sayılmaz;
 * ülke iki kez yazılmaz. Sayfalar AYRI PHP sürecinde gerçekten çizdiriliyor. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };
$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vestra_shipto_'.bin2hex(random_bytes(4));
mkdir($sb.'/data', 0777, true);
$ACC = [
    ['id' => 'a-de', 'type' => 'buyer', 'email' => 'de@example.test', 'company' => 'Boutique Berlin', 'country' => 'Germany',
     'address' => 'Hauptstraße 1', 'postcode' => '10115', 'city' => 'Berlin'],
    ['id' => 'a-fr', 'type' => 'buyer', 'email' => 'fr@example.test', 'company' => 'Boutique Paris', 'country' => 'France',
     'address' => 'Rue de Rivoli 5, Paris', 'postcode' => '', 'city' => ''],
    ['id' => 'a-ae', 'type' => 'buyer', 'email' => 'ae@example.test', 'company' => 'Dubai Store', 'country' => 'United Arab Emirates',
     'address' => 'Sheikh Zayed Rd 12, Dubai', 'postcode' => '', 'city' => ''],
    ['id' => 'a-no', 'type' => 'buyer', 'email' => 'no@example.test', 'company' => 'Oslo AS', 'country' => 'Norway',
     'address' => 'Karl Johans gate 1, 0154 Oslo', 'postcode' => '', 'city' => ''],
];
file_put_contents($sb.'/data/accounts.json', json_encode($ACC));
define('VESTRA_DATA_DIR', $sb.'/data');
define('VESTRA_ACCOUNTS', $sb.'/data/accounts.json');
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/auth.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';

$row = fn(string $email, string $country, string $notes = 'Payment: Bank transfer.', string $ref = 'VES-T1') =>
    ['ref' => $ref, 'timestamp' => '2026-09-29T10:00:00+00:00', 'company' => 'Test Co', 'name' => 'Probe', 'email' => $email,
     'country' => $country, 'items' => '2x ABC @10.00', 'total' => '20', 'notes' => $notes];

echo "== 1. çözücü: sıra, posta kodu, ülke ==\n";
$s = vestra_order_ship_to($row('de@example.test', 'Germany'));
$t('not yoksa HESABIN adresi (ayrı PLZ/şehir alanlarıyla), kaynak account',
   $s['source'] === 'account' && $s['address'] === 'Hauptstraße 1, 10115 Berlin');
$t('satır ülkeyi ekler, posta kodu VAR', $s['line'] === 'Hauptstraße 1, 10115 Berlin, Germany' && $s['postcode'] === true);
$seg = vestra_order_delivery_segment('Lager Nord, Industriestr. 7, 20095 Hamburg, Germany');
$s = vestra_order_ship_to($row('de@example.test', 'Germany', 'Payment: Bank transfer. '.$seg));
$t('siparişin KENDİ notu hesaptan ÖNCE gelir (kısaltmalı adres kesilmeden)',
   $s['source'] === 'order' && $s['address'] === 'Lager Nord, Industriestr. 7, 20095 Hamburg, Germany');
$t('adres ülkeyi zaten içeriyorsa İKİNCİ kez eklenmez', substr_count($s['line'], 'Germany') === 1);
$s = vestra_order_ship_to($row('fr@example.test', 'France'));
$t('posta kodsuz Paris: postcode=false, muaf DEĞİL', $s['line'] === 'Rue de Rivoli 5, Paris, France' && !$s['postcode'] && !$s['pc_optional']);
$s = vestra_order_ship_to($row('ae@example.test', 'United Arab Emirates'));
$t('Dubai: posta kodu yok ama MUAF', !$s['postcode'] && $s['pc_optional']);
$s = vestra_order_ship_to($row('x@example.test', 'Germany', 'Payment: Bank transfer. '
     .vestra_order_delivery_segment('Boutique X, Hauptstr 1, Berlin, Germany, Tel +49 30 123456')));
$t('TELEFON numarası posta kodu SAYILMAZ (adres defteri satırı ", Tel …" ile bitiyor)', !$s['postcode']);
$s = vestra_order_ship_to($row('x@example.test', 'Germany', 'Payment: Bank transfer. '
     .vestra_order_delivery_segment('Boutique X, Hauptstr 1, 10115 Berlin, Germany, Tel +49 30 123456')));
$t('KONTROL: aynı satır posta koduyla → postcode=true', $s['postcode']);
$s = vestra_order_ship_to($row('nobody@example.test', 'Spain'));
$t('hesap yok, not yok: adres BOŞ, kaynak boş (uydurulmaz)', $s['line'] === '' && $s['source'] === '' && !$s['postcode']);
$s = vestra_order_ship_to($row('no@example.test', 'Nor'));
$t('kısaltılmış ülke ("Nor") hesabınkiyle düzelir, satırda Norway', $s['country'] === 'Norway' && str_ends_with($s['line'], ', Norway'));
$s = vestra_order_ship_to($row('de@example.test', 'Germany'), $ACC[1], false);
$t('çağıranın verdiği hesap kullanılır (liste eşlemesi)', $s['address'] === 'Rue de Rivoli 5, Paris');
$s = vestra_order_ship_to($row('de@example.test', 'Germany'), null, false);
$t('findAccount=false + hesap yok: arama YAPILMAZ', $s['line'] === '');
$s = vestra_order_ship_to($row('DE@Example.Test ', 'Germany'));
$t('e-posta eşleşmesi harf/boşluk duyarsız (faturanınkiyle aynı)', $s['source'] === 'account');

echo "== 2. fatura AYNI cevabı verir ==\n";
foreach ([$row('de@example.test', 'Germany'), $row('fr@example.test', 'France'), $row('no@example.test', 'Nor'),
          $row('de@example.test', 'Germany', 'Payment: Bank transfer. '.$seg), $row('nobody@example.test', 'Spain')] as $i => $r) {
    $b = vestra_invoice_buyer($r); $s = vestra_order_ship_to($r);
    $t("vaka $i: fatura adresi = çözücünün adresi, ülke aynı", $b['address'] === $s['address'] && $b['country'] === $s['country']);
}
$t('fatura adresi ülkeyi ayrıca taşır, adrese EKLEMEZ', vestra_invoice_buyer($row('de@example.test', 'Germany'))['address'] === 'Hauptstraße 1, 10115 Berlin');

echo "== 3. alıcı / satıcı sipariş sayfası (ayrı PHP süreci, dil başına) ==\n";
$render = function (string $lang, string $role, string $email, string $notes = 'Payment: Bank transfer.') use ($sb, $root): string {
    $f = $sb.'/r_'.bin2hex(random_bytes(3)).'.php';
    file_put_contents($f, '<?php
define("VESTRA_DATA_DIR", '.var_export($sb.'/data', true).');
define("VESTRA_ACCOUNTS", '.var_export($sb.'/data/accounts.json', true).');
$_GET["lang"] = '.var_export($lang, true).';
require '.var_export($root.'/vestra/inc/products.php', true).';
require_once '.var_export($root.'/vestra/inc/auth.php', true).';
require_once '.var_export($root.'/vestra/inc/orders.php', true).';
$row = ["ref"=>"VES-R1","timestamp"=>"2026-09-29T10:00:00+00:00","company"=>"Test","name"=>"P","email"=>'.var_export($email, true).',"country"=>"","items"=>"2x ABC @10.00","total"=>"20","notes"=>'.var_export($notes, true).'];
echo vestra_render_order_detail($row, ["status"=>"pending"], '.var_export($role, true).', "u1", "/x", "/y");');
    return (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($f).' 2>&1');
};
$h = $render('de', 'buyer', 'de@example.test');
$t('alıcı (de): fatura adresine giden siparişte adres + PLZ GÖRÜNÜR', str_contains($h, 'Lieferung an:') && str_contains($h, 'Hauptstraße 1, 10115 Berlin, Germany'));
$t('alıcı (de): kaynak dürüst — "(Wie Rechnungsadresse)"', str_contains($h, '(Wie Rechnungsadresse)'));
$t('alıcı (de): posta kodu varken uyarı YOK', !str_contains($h, 'Keine Postleitzahl'));
$h = $render('de', 'buyer', 'fr@example.test');
$t('alıcı (de): posta kodu yoksa uyarı + adres defterine bağlantı',
   str_contains($h, 'Keine Postleitzahl hinterlegt') && str_contains($h, '/buyer?tab=profile#addresses'));
$h = $render('en', 'seller', 'fr@example.test');
$t('satıcı: adres ve uyarı görünür, ALICININ profil bağlantısı YOK',
   str_contains($h, 'Rue de Rivoli 5, Paris, France') && str_contains($h, 'No postcode on file') && !str_contains($h, 'tab=profile'));
$h = $render('en', 'buyer', 'ae@example.test');
$t('Dubai: posta kodu uyarısı YOK (muaf)', str_contains($h, 'Sheikh Zayed Rd 12') && !str_contains($h, 'No postcode'));
$h = $render('en', 'buyer', 'nobody@example.test');
$t('adres hiç yoksa "No delivery address on file"', str_contains($h, 'No delivery address on file'));
$h = $render('ar', 'buyer', 'de@example.test');
$t('ar: satır kendi dilinde, adres yön-bağımsız (plaintext), PHP uyarısı yok',
   str_contains($h, 'unicode-bidi:plaintext') && !preg_match('/\b(Warning|Notice|Deprecated|Fatal error)\b/', $h));
$h = $render('en', 'buyer', 'de@example.test', 'Payment: Bank transfer. '.$seg);
$t('seçilmiş teslimat adresi: kendi adresi, "same as billing" YAZMAZ',
   str_contains($h, 'Industriestr. 7, 20095 Hamburg') && !str_contains($h, 'Same as billing'));

echo "== 4. sipariş özeti PDF'i adresi basıyor ==\n";
$pdf = vestra_render_order_pdf($row('de@example.test', 'Germany'), vestra_order_lines($row('de@example.test', 'Germany'))['lines'], 'Pending');
$t('PDF sıkıştırılmamış (ham baytta metin aranabilir)', !str_contains($pdf, '/FlateDecode'));
$t('PDF: "Deliver to" + tam adres + (same as billing address)', str_contains($pdf, '(Deliver to)') && str_contains($pdf, '10115 Berlin, Germany')
   && str_contains($pdf, 'same as billing address'));
$pdf = vestra_render_order_pdf($row('fr@example.test', 'France'), [], 'Pending');
$t('PDF: posta kodu yoksa "Postcode missing"', str_contains($pdf, 'Postcode missing'));
$pdf = vestra_render_order_pdf($row('de@example.test', 'Germany'), [], 'Pending');
$t('KONTROL: posta kodu varken "Postcode missing" YOK', !str_contains($pdf, 'Postcode missing'));

echo "== 5. panel (admin.php kum havuzunda gerçekten çiziliyor) ==\n";
$cp = $sb.'/site';
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($cp).' && rm -rf '.escapeshellarg($cp.'/data').' && mkdir '.escapeshellarg($cp.'/data'), $o2, $rc);
$t('kum havuzu kuruldu', $rc === 0);
file_put_contents($cp.'/inc/config.php', "<?php return ['admin_pass'=>'x'];\n");
file_put_contents($cp.'/data/accounts.json', json_encode($ACC));
$HEAD = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$fh = fopen($cp.'/data/orders.csv', 'w'); fputcsv($fh, $HEAD, ',', '"', '\\');
fputcsv($fh, ['2026-09-29T10:00:00+00:00','VES-DE1','Boutique Berlin','','P','de@example.test','Germany','','2x ABC @10.00','20','0','20','20','Payment: Bank transfer.','','','','','0',''], ',', '"', '\\');
fputcsv($fh, ['2026-09-29T11:00:00+00:00','VES-FR1','Boutique Paris','','P','fr@example.test','France','','2x ABC @10.00','20','0','20','20','Payment: Bank transfer.','','','','','0',''], ',', '"', '\\');
fclose($fh);
file_put_contents($cp.'/data/order_statuses.json', '{}');
$adm = function (array $get) use ($cp): string {
    $f = $cp.'/_t_'.bin2hex(random_bytes(3)).'.php';
    file_put_contents($f, "<?php\nchdir(".var_export($cp, true).");\nerror_reporting(E_ALL); ini_set('display_errors','1');\n"
        .'session_start(); $_SESSION["vadmin"]=true; $_GET='.var_export($get, true).'; $_SERVER["REQUEST_METHOD"]="GET";
$_SERVER["REQUEST_URI"]="/admin?tab=orders"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; $_SERVER["HTTP_HOST"]="localhost";
ob_start(); include "admin.php"; echo ob_get_clean();');
    return (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($f).' 2>&1');
};
$h = $adm(['tab' => 'orders']);
$t('liste çizildi', strlen($h) > 5000 && str_contains($h, 'VES-DE1'));
$t('liste: iki siparişte de 📍 adres satırı', substr_count($h, '📍 ') >= 2 && str_contains($h, 'Hauptstraße 1, 10115 Berlin, Germany'));
$t('liste: posta kodsuz sipariş satırında "⚠ no postcode"', str_contains($h, 'Rue de Rivoli 5, Paris, France') && str_contains($h, '⚠ no postcode'));
$t('liste: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated)\b/', $h));
$h = $adm(['tab' => 'orders', 'view' => 'VES-DE1']);
$t('dosya: adres ve "(same as billing — account address)" — eski "nothing on file" YOK',
   str_contains($h, '<b>Hauptstraße 1, 10115 Berlin, Germany</b>') && str_contains($h, 'same as billing — account address')
   && !str_contains($h, 'nothing on file'));
$t('dosya: override formu BOŞ kalır (hesap adresi siparişe yazılmış gibi gösterilmez)',
   (bool)preg_match('/name="_action" value="order_delivery">.*?<input name="address" value=""/s', $h));
$h = $adm(['tab' => 'orders', 'view' => 'VES-FR1']);
$t('dosya: posta kodsuz siparişte "⚠ no postcode"', str_contains($h, '⚠ no postcode'));
$t('dosya: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated)\b/', $h));

echo "== 6. kablolama ==\n";
$src = fn(string $p) => (string)file_get_contents($root.'/vestra/'.$p);
$inv = $src('inc/invoice.php');
$t('fatura adresi çözücüden (ikinci bir sıra yazılmadı)', str_contains($inv, "'address' => \$ship['address']")
   && !str_contains($inv, 'vestra_account_billing_line($acc);'));
$a = $src('admin.php');
$la = strpos($a, '$__accByEmail = [];'); $lf = strpos($a, 'foreach(array_reverse($orders) as $o):');
$t('liste hesap eşlemesini döngüden ÖNCE bir kez kuruyor', $la !== false && $lf !== false && $la < $lf);
foreach (['de','fr','it','es','pt','ru','ar','ja'] as $l) {
    $d = (string)file_get_contents($root.'/vestra/inc/lang/'.$l.'.php');
    $t("sözlük $l: iki yeni anahtar", str_contains($d, "'No delivery address on file' =>") && str_contains($d, "'No postcode on file' =>"));
}

exec('rm -rf '.escapeshellarg($sb));
echo "\n$ok ok, $bad hata\n";
exit($bad ? 1 : 0);
