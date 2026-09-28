<?php
/* SIPARIS DENETIMI (seller-products.yml → admin_mode=order_audit) ve PDF'li fatura
 * mektubunun odeme-saati muhafazalari — 28 Eyl 2026, operator: "tüm siparişlerin
 * faturası email ile gittiğinden emin ol ödemeyenlere hatırlatma yap".
 *
 * Denetim adimi IS AKISININ ICINDEKI GERCEK PHP'den cikarilip kum havuzunda
 * KOSTURULUYOR (kaynak taramasi degil). Olcülenler, iki yon:
 *   - her durum dogru siniflaniyor (odendi / iptal+otomatik / dekontlu / acik / faturasiz / escrow)
 *   - acik siparisler "ISLEM GEREKEN"de, odenmis/iptal olanlar DEGIL
 *   - adres MASKELI basiliyor, kisi adi (name) HIC basilmiyor
 *   - SALT OKUNUR: veri dizini kosudan once ve sonra bayt bayt ayni
 * Mektup tarafi: son tarih cron'un saatinden (vestra_order_payment_grace), sablona
 * gomulu degil; dekontlu ve suresi dolmus sipariste mektup kurulmadan DURUR. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__);

echo "== 1. denetim adimi is akisinda, salt okunur ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "admin_mode == 'order_audit'");
$t('adim var', $a !== false);
$blk = $a === false ? '' : substr($wf, $a, strpos($wf, "\n      - name:", $a) - $a);
$t('issue_ref envs ile sunucuya geciyor (V_ONLY)', str_contains($blk, 'V_ONLY: ${{ github.event.inputs.issue_ref }}') && str_contains($blk, 'envs: V_ONLY'));
if (!preg_match("~<<'PHPEOF'\n(.*?)\n\s*PHPEOF~s", $blk, $m)) { echo "  FAIL php govdesi bulunamadi\n"; exit(1); }
$php = preg_replace('/^            /m', '', $m[1]);
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$t('hicbir yazici cagirmiyor (write_json / write_csv / send_mail / file_put)',
   !preg_match('/vestra_write_json|vestra_write_csv|vestra_send_mail|file_put_contents|vestra_order_payment_reminder_send|fopen\([^)]*[\'"]w/', $code));
$t('odeme karari TEK kaynaktan (settled + grace)', str_contains($code, 'vestra_order_payment_settled(') && str_contains($code, 'vestra_order_payment_grace('));
$t('kisi adi (name alani) basilmiyor', !preg_match("/\\\$row\['name'\]/", $code));

echo "\n== 2. kum havuzunda kostur ==\n";
$sand = sys_get_temp_dir().'/vestra_audit_'.bin2hex(random_bytes(4));
mkdir($sand.'/data/invoices', 0777, true);
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$rows = [
  ['2026-09-10T10:00:00+00:00','VES-PAID1','Paid Co','','Anna Person','anna.person@paid.example','France','','1x A @10.00','10','0','10','10','Payment: Bank transfer.','','','','','0',''],
  ['2026-09-12T10:00:00+00:00','VES-CANC1','Cancel SL','','Bob Person','bob.person@canc.example','Spain','','1x A @10.00','10','0','10','10','Payment: Bank transfer.','','','','','0',''],
  ['2026-09-20T10:00:00+00:00','VES-OPEN1','Open GmbH','','Carl Person','carl.person@open.example','Germany','','2x A @10.00','20','0','20','40','Payment: Bank transfer.','','','','','20',''],
  ['2026-09-26T10:00:00+00:00','VES-RCPT1','Receipt BV','','Dee Person','dee.person@rcpt.example','Netherlands','','1x A @10.00','10','0','10','10','Payment: Bank transfer.','','','','','0',''],
  ['2026-09-27T10:00:00+00:00','VES-NOINV','Review Srl','','Eve Person','eve.person@noinv.example','Italy','','1x A @10.00','10','0','10','10','Payment: Bank transfer.','','','','','0',''],
  ['2026-09-27T11:00:00+00:00','VES-ESC1','Escrow Ltd','','Fay Person','fay.person@esc.example','Ireland','','1x A @10.00','10','0','10','10','Payment: Secure escrow (card).','','','','','0',''],
];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach ($rows as $r) fputcsv($h, $r, ',', '"', '\\');
fclose($h);
foreach (['VES-PAID1'=>'INV-T-1','VES-CANC1'=>'INV-T-2','VES-OPEN1'=>'INV-T-3','VES-RCPT1'=>'INV-T-4','VES-ESC1'=>'INV-T-5'] as $ref => $no) {
  file_put_contents($sand."/data/invoices/{$ref}__vestra.json", json_encode(['no'=>$no,'seller_key'=>'vestra','currency'=>'EUR','total'=>40,'issued_at'=>'2026-09-21T09:00:00+00:00']));
  file_put_contents($sand."/data/invoices/{$ref}__vestra.pdf", "%PDF-1.4 test");
}
$start = gmdate('c', time() - 86400);   // saat DUN basladi -> isliyor
file_put_contents($sand.'/data/order_statuses.json', json_encode([
  'VES-PAID1' => ['status'=>'paid', 'history'=>[['status'=>'paid','at'=>'2026-09-15T10:00:00+00:00','by'=>'admin']]],
  'VES-CANC1' => ['status'=>'cancelled', 'payment_grace_start'=>'2026-09-14T14:00:00+00:00', 'payment_reminder_sent_at'=>'2026-09-14T14:00:00+00:00',
                  'history'=>[['status'=>'cancelled','at'=>'2026-09-21T14:00:00+00:00','by'=>'system','note'=>'Auto-cancelled: no payment within 5 business days']]],
  'VES-OPEN1' => ['status'=>'pending', 'payment_grace_start'=>$start, 'payment_reminder_sent_at'=>$start],
  'VES-RCPT1' => ['status'=>'pending', 'payment_receipt'=>['file'=>'x.pdf','uploaded_at'=>'2026-09-27T08:00:00+00:00','uploaded_by'=>'buyer']],
]));
file_put_contents($sand.'/data/accounts.json', '[]');
/* .htaccess HARIC: vestra_invoice_dir() fatura dizinine ilk erisimde koruma
   dosyasini (Deny from all) yaziyor -- sunucuda zaten var, is verisi degil.
   Olculen sey siparis/fatura/durum kayitlari; onlar bayt bayt ayni kalmali. */
$snap = function () use ($sand) {
  $o = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sand.'/data', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (basename((string)$f) === '.htaccess') continue;
    $o[substr((string)$f, strlen($sand))] = sha1_file((string)$f);
  }
  ksort($o); return $o;
};
$before = $snap();
$run = str_replace("\$doc = getenv('HOME').'/public_html';", "\$doc = ".var_export($root.'/vestra', true).';', $php);
$t('belge koku satiri bulundu (kum havuzuna yonlendirilebildi)', $run !== $php);
$run = "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($sand.'/data', true).");\ndefine('VESTRA_ACCOUNTS', ".var_export($sand.'/data/accounts.json', true).");\n"
     . preg_replace('/^<\?php\s*/', '', $run);
file_put_contents($sand.'/run.php', $run);
$out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($sand.'/run.php').' 2>&1');
$sec = function (string $ref) use ($out): string {
  $p = strpos($out, "\n".$ref.'  '); if ($p === false) return '';
  $q = strpos($out, "\nVES-", $p + 5); $r = strpos($out, "\nOZET:", $p);
  $e = min($q === false ? PHP_INT_MAX : $q, $r === false ? PHP_INT_MAX : $r);
  return substr($out, $p, $e - $p);
};
$t('kostu, ozet satiri basildi', str_contains($out, 'OZET: siparis 6'));
$t('PHP uyarisi / hatasi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $out));
$t('ODENDI siniflandi', str_contains($sec('VES-PAID1'), 'ODENDI (status, 2026-09-15)'));
$t('otomatik IPTAL tarihiyle', str_contains($sec('VES-CANC1'), 'IPTAL (OTOMATIK 2026-09-21)'));
$t('DEKONT durumu', str_contains($sec('VES-RCPT1'), 'dekont VAR (2026-09-27)') && str_contains($sec('VES-RCPT1'), 'saat=has_receipt'));
$t('ACIK sipariste saat ve son tarih', str_contains($sec('VES-OPEN1'), 'saat=running') && str_contains($sec('VES-OPEN1'), ' son '));
$t('faturasiz siparis ayri yazildi', str_contains($sec('VES-NOINV'), 'fatura: YOK (inceleme'));
$t('escrow ayri yazildi', str_contains($sec('VES-ESC1'), 'escrow -- havale saati uygulanmaz'));
$todo = substr($out, (int)strpos($out, 'ISLEM GEREKEN:'));
$t('ISLEM GEREKEN: acik + dekontlu + faturasiz VAR', str_contains($todo, 'VES-OPEN1') && str_contains($todo, 'VES-RCPT1') && str_contains($todo, 'VES-NOINV'));
$t('ISLEM GEREKEN: odenmis, iptal ve escrow YOK', !str_contains($todo, 'VES-PAID1') && !str_contains($todo, 'VES-CANC1') && !str_contains($todo, 'VES-ESC1'));
$t('adresler MASKELI (tam adres hic basilmadi)', !str_contains($out, 'anna.person@') && !str_contains($out, 'carl.person@') && str_contains($out, 'c***@open.example'));
$t('kisi adi hic basilmadi', !preg_match('/(Anna|Bob|Carl|Dee|Eve|Fay) Person/', $out));
$after = $snap();
foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $k)
  if (($before[$k] ?? null) !== ($after[$k] ?? null)) echo "  DEGISEN: {$k}\n";
$t('SALT OKUNUR: veri dizini bayt bayt ayni (.htaccess koruma dosyasi haric)', $after === $before);

echo "\n== 3. PDF'li fatura mektubu: son tarih ve muhafazalar ==\n";
require_once $root.'/vestra/inc/email_templates.php';
[$s1, $b1, $o1] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-9', 60.0, 'EUR', false, true, 'Marco Bellini', '30 September 2026');
$t('son tarih verilince govdede', str_contains($b1, 'Payment is due by 30 September 2026.'));
$t('son tarih verilince satirda', in_array(['label'=>'Payment due by', 'value'=>'30 September 2026'], $o1['rows'], true));
[, $b2, $o2] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-9', 60.0, 'EUR', false, true, 'Marco Bellini');
$t('tarih verilmezse cumle YOK', !str_contains($b2, 'Payment is due'));
$t('tarih verilmezse satir YOK', !in_array('Payment due by', array_column($o2['rows'], 'label'), true));
$tpl = (string)file_get_contents($root.'/vestra/inc/email_templates.php');
$ta = strpos($tpl, 'function vestra_tpl_order_invoice_pdf'); $tb = strpos($tpl, "\n}\n", $ta);
$t('sablonda gomulu tarih yok', !preg_match('/\b20\d\d\b|September|October/', substr($tpl, $ta, $tb - $ta)));

$sp = (string)file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$sa = strpos($sp, "\$letter === 'order_invoice_pdf'");
$sb = $sa === false ? '' : substr($sp, $sa, strpos($sp, "} elseif (\$letter === 'order_note')", $sa) - $sa);
$sc = preg_replace('~/\*.*?\*/~s', '', $sb);
$pTpl  = strpos($sc, 'vestra_tpl_order_invoice_pdf(');
$pRcpt = strpos($sc, "if (\$og['phase'] === 'has_receipt')");
$pOver = strpos($sc, "if (\$og['phase'] === 'overdue')");
$t('dekontlu sipariste mektup kurulmadan DURUR', $pRcpt !== false && $pTpl !== false && $pRcpt < $pTpl && str_contains(substr($sc, $pRcpt, 220), 'exit(1)'));
$t('suresi dolmus sipariste mektup kurulmadan DURUR', $pOver !== false && $pOver < $pTpl && str_contains(substr($sc, $pOver, 260), 'exit(1)'));
$t('saat cron\'un fonksiyonundan (vestra_order_payment_grace)', str_contains($sc, '$og = vestra_order_payment_grace($ost, time(), $oref);'));
$t('tarih YALNIZ saat isliyorsa', str_contains($sc, "\$odue = \$og['phase'] === 'running' ? gmdate('j F Y', (int)\$og['deadline']) : '';"));
$t('tarih sablona iletiliyor', str_contains($sc, '$persona, $odue);'));

echo "\n== 4. saat asamalari (mektubun okudugu ayni fonksiyon) ==\n";
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/orders.php';
$now = time();
$g1 = vestra_order_payment_grace(['status'=>'pending', 'payment_receipt'=>['file'=>'r.pdf']], $now);
$t('dekont -> has_receipt', $g1['phase'] === 'has_receipt');
$g2 = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c', $now - 20*86400), 'payment_reminder_sent_at'=>gmdate('c', $now - 20*86400)], $now);
$t('20 gun once baslamis -> overdue', $g2['phase'] === 'overdue');
$g3 = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c', $now - 3600)], $now);
$t('1 saat once baslamis -> running, son tarih gelecekte', $g3['phase'] === 'running' && (int)$g3['deadline'] > $now);
$g4 = vestra_order_payment_grace(['status'=>'pending'], $now);
$t('damgasiz -> unstamped (mektup tarih YAZMAZ)', $g4['phase'] === 'unstamped');

$rm = function (string $d) use (&$rm) { foreach (glob($d.'/*') ?: [] as $f) is_dir($f) ? $rm($f) : unlink($f); @rmdir($d); };
$rm($sand);
echo "\n".($bad ? "FAIL: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
