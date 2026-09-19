<?php
/* Odeme hatirlatma / otomatik iptal (operator karari, 2 Eyl 2026, order
 * INV-2026-1001 / Daymond Proconect vesilesiyle): "siparislerin odemesi 5 is
 * gunu icerisinde gelmez ise otomatik kapanacagini soyle, eger odeme
 * yaptiysa havale dekontunu... bize gondersin."
 * Uc sey tutulur:
 *   1. vestra_business_days_after() -- hafta sonu atlayan gun sayaci,
 *   2. vestra_order_payment_grace() -- saf asama makinesi: baslamamis /
 *      isliyor / suresi dolmus / DEKONT VAR (saat DURUR -- bakilmadan
 *      otomatik iptal olmaz, KURAL 2f'nin ayni dersi),
 *   3. vestra_tpl_order_payment_due() -- Ingilizce, rakamlar parametreden.
 * Ikisi/ucu de zaman/veri parametresini disaridan alir (saf); testin sonucu
 * gercek "bugun" hangi gune denk gelirse gelsin degismez.
 */
/* vestra_business_days_after() lives in inc/escrow.php, NOT here -- the escrow
 * auto-release sweep (31 Aug 2026) already needed the identical "N business
 * days, Sat/Sun skipped" clock. It is eval'd FIRST, before
 * vestra_order_payment_grace(): that function's own lazy require
 * (`if (!function_exists(...)) require_once __DIR__.'/escrow.php'`) would
 * resolve __DIR__ against THIS file's directory under eval and fail to find
 * it -- harmless here only because function_exists() is already true by then
 * and the require is skipped, exactly as it is on every real page that loads
 * both files before either function is called. */
$esrc = file_get_contents(__DIR__.'/../vestra/inc/escrow.php');
if (!preg_match('/^function vestra_business_days_after\(.*?^}/ms', $esrc, $m)) { echo "HATA: vestra_business_days_after bulunamadi\n"; exit(1); }
eval($m[0]);
$src = file_get_contents(__DIR__.'/../vestra/inc/orders.php');
/* vestra_order_payment_settled() GRACE'DEN ONCE eval edilmeli: grace artik onu
   cagiriyor ("parasi gelmis satisin saati hic islemez"). Zincir sabiti de
   lazim -- olcut elle yazilmis bir durum listesinden degil ZINCIRDEN okunuyor,
   o yuzden testin de gercek zinciri gormesi gerekiyor (kaynaktan alinir,
   burada ikinci kez yazilmaz: iki kopya ilk adim eklendiginde ayrisirdi). */
if (!preg_match('/^const VESTRA_ORDER_STEPS = (\[[^\]]*\]);/m', $src, $m)) { echo "HATA: VESTRA_ORDER_STEPS bulunamadi\n"; exit(1); }
eval('define("VESTRA_ORDER_STEPS", '.$m[1].');');
/* offer_responses.json'i testin kendi tablosundan okutan sahte okuyucu: teklif
   faturasinin "odendi" isaretinin saati DURDURDUGU ancak boyle sinanabilir. */
$GLOBALS['__json'] = [];
function vestra_read_json(string $name): array { return (array)($GLOBALS['__json'][$name] ?? []); }
if (!preg_match('/^function vestra_order_payment_settled\(.*?^}/ms', $src, $m)) { echo "HATA: vestra_order_payment_settled bulunamadi\n"; exit(1); }
eval($m[0]);
if (!preg_match('/^function vestra_order_payment_grace\(.*?^}/ms', $src, $m)) { echo "HATA: vestra_order_payment_grace bulunamadi\n"; exit(1); }
eval($m[0]);
if (!preg_match('/const VESTRA_ORDER_PAYMENT_GRACE_DAYS = (\d+);/', $src, $m)) { echo "HATA: VESTRA_ORDER_PAYMENT_GRACE_DAYS bulunamadi\n"; exit(1); }
define('VESTRA_ORDER_PAYMENT_GRACE_DAYS', (int)$m[1]);

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- vestra_business_days_after: hafta ici baslangic -> +5 is gunu = +7 takvim gunu --\n";
foreach (['monday','tuesday','wednesday','thursday','friday'] as $day) {
    $start = strtotime("$day this week 09:00:00");
    $end = vestra_business_days_after($start, 5);
    $t("$day: +5 is gunu = tam +7 takvim gunu sonra, ayni saatte", $end === $start + 7*86400);
    $t("$day: sonuc ayni gun adina denk gelir", gmdate('N', $end) === gmdate('N', $start));
}
echo "-- hafta sonu baslangic (nadir ama ihtimal disi degil) --\n";
$sat = strtotime('saturday this week 09:00:00');
$t('cumartesi baslarsa +5 is gunu = +6 takvim gunu (pazar hic sayilmaz)', vestra_business_days_after($sat,5) === $sat + 6*86400);
$sun = strtotime('sunday this week 09:00:00');
$t('pazar baslarsa +5 is gunu = +5 takvim gunu (bastan hafta sonu yok)', vestra_business_days_after($sun,5) === $sun + 5*86400);
$t('0 gun istenirse degismez', vestra_business_days_after($sat, 0) === $sat);

echo "-- vestra_order_payment_grace: asama makinesi --\n";
$mon = strtotime('monday this week 09:00:00');

$g = vestra_order_payment_grace(['status'=>'pending'], $mon);
$t('saat hic baslamamis -> unstamped', $g['phase'] === 'unstamped');
$t('unstamped -> son tarih yok', $g['deadline'] === null);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon)], $mon + 86400);
$t('saat basladi, 1 gun gecti, mektup henuz stamplanmadi -> running', $g['phase'] === 'running');
$t('  running -> notice_sent false', $g['notice_sent'] === false);
$t('  running -> son tarih = baslangic + 7 takvim gunu', $g['deadline'] === $mon + 7*86400);
$t('  running -> kalan gun > 0', $g['days_left'] > 0);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon)],
                                 $mon + 7*86400 + 60);
$t('son tarih GECTI, ilk mektup gitmisti -> overdue (iptal edilebilir)', $g['phase'] === 'overdue');
$t('  overdue -> notice_sent true', $g['notice_sent'] === true);
$t('  overdue -> kalan gun 0', $g['days_left'] === 0);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon)], $mon + 7*86400 + 60);
$t('son tarih gecti AMA ilk mektup HIC gitmemisti -> yine overdue', $g['phase'] === 'overdue');
$t('  bu durumda notice_sent false kalir -- cron once mektubu dener, IPTAL ETMEZ', $g['notice_sent'] === false);

echo "-- dekont yuklu -> saat DURUR (KURAL 2f'nin ayni dersi: bakilmadan otomatik islem yok) --\n";
$g = vestra_order_payment_grace(['status'=>'pending', 'payment_receipt'=>['file'=>'receipt_x.pdf']], $mon + 30*86400);
$t('dekont var, saat hic baslamamis olsa bile -> has_receipt', $g['phase'] === 'has_receipt');
$g = vestra_order_payment_grace([
    'status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon),
    'payment_receipt'=>['file'=>'receipt_x.pdf'],
], $mon + 30*86400);
$t('dekont var, son tarih COKTAN gecmis olsa bile -> yine has_receipt, IPTAL DEGIL', $g['phase'] === 'has_receipt');

$t('sabit: operator karari 5 is gunu', VESTRA_ORDER_PAYMENT_GRACE_DAYS === 5);

/* ── PARASI GELMIS SATIS: saat HIC islemez (19 Eyl 2026) ───────────────────
   Iki ayri "odendi" kaydi vardi ve biri otekini okumuyordu:
     order_statuses[ref].status        <- Admin > Orders durum secici
     offer_responses[ref].invoice_paid_at <- Invoice approvals "✓ Paid"
   OLCULDU: O39419 order_statuses'te `completed` (24 Agu'da odenmis, 9 Eyl'de
   kargolanmis, 12 Eyl'de ALICININ KENDISI tamamlandi isaretlemis) ama teklif
   kaydinda invoice_paid_at YOK -- yani invoice_paid_at okuyan iki yuzey de
   (alicinin "Payment due" bandi, panelin Paid sutunu) odenmis ve teslim
   edilmis bir satisi "odenmemis" gosteriyordu.
   IKI YON DE tutuluyor: odenmis olan settled OLMALI, odenmemis olan OLMAMALI --
   tek yon yazilsaydi "her seye odendi diyen" bir kusur da yesil kalirdi. */
echo "-- vestra_order_payment_settled: odendi mi, TEK karar noktasi --\n";
$t('status pending -> odenmemis',        vestra_order_payment_settled('', ['status'=>'pending'])['settled'] === false);
$t('status paid -> ODENMIS (via status)',
   vestra_order_payment_settled('', ['status'=>'paid'])['settled'] === true
   && vestra_order_payment_settled('', ['status'=>'paid'])['via'] === 'status');
$t('status shipped -> ODENMIS (zincirden turetiliyor, elle liste degil)',
   vestra_order_payment_settled('', ['status'=>'shipped'])['settled'] === true);
$t('status completed -> ODENMIS (O39419 vakasi)',
   vestra_order_payment_settled('', ['status'=>'completed'])['settled'] === true);
$t('araya sonradan giren adim (to_vestra) da kendiliginden odenmis tarafta',
   vestra_order_payment_settled('', ['status'=>'to_vestra'])['settled'] === true);
/* 'cancelled' zincirde BILEREK yok: iptal, ayni yolculugun ileri asamasi degil,
   yolculugun durmasi (VESTRA_ORDER_CANCELLED'in kendi notu). */
$t('status cancelled -> odenmis SAYILMAZ', vestra_order_payment_settled('', ['status'=>'cancelled'])['settled'] === false);

/* TARIH: canli olcum (O39419, 19 Eyl 2026) ilk yazimimin YANLIS tarih bastigini
   gosterdi -- `paid_at` yoktu ve `updated_at`e dusuyordu, o da kargo damgasiydi
   (9 Eyl), oysa para 24 Agustos'ta gelmisti. Rakam dogruydu, ETIKET yalandi.
   Uc yon de tutuluyor, ucuncusu asil olan: bilinmeyen tarih BOS kalmali. */
$t('tarih: acik paid_at alani kullanilir',
   vestra_order_payment_settled('', ['status'=>'shipped', 'paid_at'=>'2026-08-24T12:59:00+00:00'])['at']
   === '2026-08-24T12:59:00+00:00');
$t('tarih: paid_at yoksa GECMISTEKI paid satirindan (O39419 vakasi)',
   vestra_order_payment_settled('', ['status'=>'completed', 'updated_at'=>'2026-09-09T14:03:27+00:00',
     'history'=>[['status'=>'paid','at'=>'2026-08-24T12:59:00+00:00','by'=>'admin'],
                 ['status'=>'shipped','at'=>'2026-09-09T14:03:27+00:00','by'=>'admin']]])['at']
   === '2026-08-24T12:59:00+00:00');
$t('tarih: ikisi de yoksa BOS -- updated_at odeme tarihi DEGILDIR',
   vestra_order_payment_settled('', ['status'=>'shipped', 'updated_at'=>'2026-09-09T14:03:27+00:00'])['at'] === '');

$GLOBALS['__json']['offer_responses.json'] = [
    'OPAID' => ['status'=>'accept', 'invoice_paid_at'=>'2026-09-01T10:00:00+00:00'],
    'OOPEN' => ['status'=>'accept'],
    'OMEMB' => ['status'=>'accept', 'invoice_group_ref'=>'OPAID'],
    'OMEM2' => ['status'=>'accept', 'invoice_group_ref'=>'OOPEN'],
];
$t('siparis pending ama teklif faturasi ODENDI isaretli -> ODENMIS (via invoice)',
   vestra_order_payment_settled('OPAID', ['status'=>'pending'])['settled'] === true
   && vestra_order_payment_settled('OPAID', ['status'=>'pending'])['via'] === 'invoice');
$t('isaret yoksa odenmemis kalir',   vestra_order_payment_settled('OOPEN', ['status'=>'pending'])['settled'] === false);
/* Birlesik belgede isaret BIRINCIL ref'te durur (KURAL 5e); uyeyi kendi basina
   sormak, tek belgeyle odenmis bir satisin yarisini "odenmemis" gosterirdi. */
$t('uye satiri: birincil ref odenmisse uye de ODENMIS',
   vestra_order_payment_settled('OMEMB', ['status'=>'pending'])['settled'] === true);
$t('uye satiri: birincil ref odenmemisse uye de ODENMEMIS',
   vestra_order_payment_settled('OMEM2', ['status'=>'pending'])['settled'] === false);

echo "-- grace: parasi gelmis satis kovalanmaz (otomatik iptalin onundeki tek sey) --\n";
$g = vestra_order_payment_grace(['status'=>'completed'], $mon + 30*86400, 'OOPEN');
$t('siparis completed -> phase paid (unstamped DEGIL)', $g['phase'] === 'paid');
$t('  paid -> son tarih yok', $g['deadline'] === null);
$t('  paid -> kaynagi yaziyor (via status)', ($g['paid_via'] ?? '') === 'status');
/* ASIL KORUMA: satir pending, saat isliyor ve SON TARIH GECMIS -- eski kod
   burada IPTAL ederdi. Teklif faturasinda odendi isareti oldugu icin artik
   phase 'paid'; odenmis bir satis otomatik iptal EDILEMEZ. */
$g = vestra_order_payment_grace(
    ['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon)],
    $mon + 30*86400, 'OPAID');
$t('pending + son tarih gecmis AMA fatura odenmis -> paid, IPTAL DEGIL', $g['phase'] === 'paid');
$t('  kaynagi yaziyor (via invoice)', ($g['paid_via'] ?? '') === 'invoice');
/* Ters yon: ayni girdi, isaretsiz ref -> eski davranis aynen. Bu iddia
   olmasaydi "her seye paid diyen" bir kusur yukaridaki ikisini de yesil
   birakirdi. */
$g = vestra_order_payment_grace(
    ['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon)],
    $mon + 30*86400, 'OOPEN');
$t('isaretsiz ref -> yine overdue (iptal yolu kapanmadi)', $g['phase'] === 'overdue');

echo "-- kablolama: uc okuyan da AYNI fonksiyondan soruyor --\n";
$cronSrc = file_get_contents(__DIR__.'/../vestra/cron_order_payment.php');
$t('cron grace cagrisina ref GECIYOR (yoksa fatura isaretini hic goremez)',
   (bool)preg_match('/vestra_order_payment_grace\(\$entry,\s*\$now,\s*\$ref\)/', $cronSrc));
$t("cron 'paid' asamasini isliyor", str_contains($cronSrc, "case 'paid':"));
$buySrc = file_get_contents(__DIR__.'/../vestra/buyer.php');
$t('alicinin "Payment due" bandi settled() soruyor', str_contains($buySrc, 'vestra_order_payment_settled($__r'));
$t('  ve artik invoice_paid_at\'i TEK BASINA okumuyor',
   !preg_match('/if\(!empty\(\$offerResp\[\$__r\]\[.invoice_paid_at.\]\)\)\s*continue;/', $buySrc));
$admSrc = file_get_contents(__DIR__.'/../vestra/admin.php');
$t('panelin Paid sutunu settled() soruyor', str_contains($admSrc, 'vestra_order_payment_settled($rref'));
/* Yazma yolu DURUYOR: dugme hâlâ invoice_paid_at yaziyor, yalnizca OKUMA
   tek noktaya tasindi. Bu iddia olmasaydi isareti koyma yolunu sessizce
   kaldiran bir degisiklik de yesil kalirdi. */
$t('  "✓ Paid" dugmesinin YAZMA yolu yerinde', str_contains($admSrc, "\$rs[\$ref]['invoice_paid_at']=date('c')"));

/* ── CIZIM: panel kartini GERCEKTEN cizdiriyoruz ───────────────────────────
   Kaynak taramasi bu isi olcemez. Bu depo ayni dersi iki kez odedi ve ikisi de
   `php -l`'den GECMISTI (`csrf_field()` yerine `csrfField()`, ve var olmayan
   bir `vestra_order_status_label` cagrisi olsaydi ayni sinif). Tohum O39419'un
   BIREBIR sekli: kabul edilmis teklif + kesilmis fatura + siparis 'completed'
   + invoice_paid_at YOK. Kart "✓ Paid" demeli, "⌛ Unpaid" DEMEMELI. */
echo "-- panel karti: tamamlanmis satis 'Unpaid' gorunmemeli (canli cizim) --\n";
$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vestra_paidcol_'.getmypid();
@mkdir($sb, 0777, true);
$rc = 0; $o = [];
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
$t('kum havuzu kuruldu', $rc === 0);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data'));
@mkdir($sb.'/vestra/data/invoices', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
file_put_contents($sb.'/vestra/data/listings.json', json_encode([[
  'id'=>'blc-t','brand'=>'Balenciaga','name'=>'Balenciaga Print T-Shirt','sku'=>'SKU-T',
  'cat'=>'T-Shirts','status'=>'approved','mode'=>'fixed','moq'=>20,'list'=>109.90,
  'tiers'=>[['min'=>20,'price'=>109.90]],
]], JSON_UNESCAPED_UNICODE));
file_put_contents($sb.'/vestra/data/offers.csv',
  "ref,at,listing,sku,qty,unit,total,company,email\n"
 ."ODONE,2026-08-22 01:09:15,blc-t,SKU-T,20,89.90,1798.00,SK Ventures,b@example.test\n"
 ."OOPEN,2026-09-09 23:07:40,blc-t,SKU-T,10,105.00,1050.00,SK Ventures,b@example.test\n");
file_put_contents($sb.'/vestra/data/offer_responses.json', json_encode([
  'ODONE'=>['status'=>'accept'],   // <- invoice_paid_at YOK, tipki O39419'da oldugu gibi
  'OOPEN'=>['status'=>'accept'],
]));
/* ODONE tamamlanmis, OOPEN hâlâ odeme bekliyor -> kontrol grubu. */
file_put_contents($sb.'/vestra/data/order_statuses.json', json_encode([
  'ODONE'=>['status'=>'completed'], 'OOPEN'=>['status'=>'pending'],
]));
foreach (['ODONE'=>'INV-2026-1009','OOPEN'=>'INV-2026-1012'] as $r=>$no) {
  file_put_contents($sb.'/vestra/data/invoices/'.$r.'__vestra.json',
    json_encode(['no'=>$no,'seller_key'=>'vestra','total'=>1798.00,'currency'=>'EUR','issued_at'=>'2026-08-24']));
}
file_put_contents($sb.'/render.php', <<<'PHP'
<?php
error_reporting(E_ALL); ini_set('display_errors','1');
session_start(); $_SESSION['vadmin']=true;
$_GET=['tab'=>'invoices']; $_SERVER['REQUEST_METHOD']='GET';
$_SERVER['REQUEST_URI']='/admin?tab=invoices'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_HOST']='localhost';
ob_start(); include __DIR__.'/vestra/admin.php'; echo ob_get_clean();
PHP);
$html = (string)shell_exec('cd '.escapeshellarg($sb).' && php render.php 2>/dev/null');
$t('sayfa cizildi (>5 KB)', strlen($html) > 5000);
$t('kesilmis fatura karti ciziliyor', str_contains($html, 'issued offer invoice(s)'));
$t('iki fatura da listede', str_contains($html,'INV-2026-1009') && str_contains($html,'INV-2026-1012'));
/* ASIL IDDIA: tamamlanmis satis "✓ Paid", odeme bekleyen "⌛ Unpaid".
   IDDIA ROZETIN KENDI ISARETLEMESINE bagli, duz metne DEGIL: ilk yazimda
   substr_count($html,'✓ Paid') saydim ve KIRMIZI dondu -- kartin yardim metni
   de ("Ödeme gelince ✓ Paid ile işaretleyin") ayni dizgeyi tasiyor, yani iddia
   rozeti degil YARDIM METNINI olcuyordu. Kod dogruydu, olcu yanlisti; bu
   depoda kayitli "iddia satiri degil navigasyonu olcuyordu" sinifinin aynisi. */
$t('tamamlanmis satis ✓ Paid ROZETI tasiyor (yardim metni degil)',
   substr_count($html, 'cursor:default">✓ Paid</span>') === 1);
$t('odeme bekleyen ⌛ Unpaid DUGMESI tasiyor',
   substr_count($html, '>⌛ Unpaid</button>') === 1);
/* Kaynagi SIPARISIN DURUMU olan satirda toggle dugmesi YOK -- calismayan bir
   dugme gostermek, olmayan bir dugmeden kotu. Sayfada tek toggle kalmali. */
$t('durum kaynakli satirda toggle YOK (sayfada tek toggle)',
   substr_count($html, 'value="offer_invoice_paid_toggle"') === 1);
$t('nereden degistirilecegi yaziyor', str_contains($html, 'sipariş durumu:'));
$t('durum etiketi cozuluyor (Completed)', str_contains($html, 'Completed'));
$t('PHP uyarisi yok', !preg_match('/\b(Warning|Fatal error|Deprecated)\b/', $html));
exec('rm -rf '.escapeshellarg($sb));

echo "-- vestra_receipt_file_path: ref/dosya adi temizleniyor (path traversal yok) --\n";
$rsrc = file_get_contents(__DIR__.'/../vestra/inc/receipts.php');
if (!preg_match('/^function vestra_receipt_file_path\(.*?^}/ms', $rsrc, $m)) { echo "HATA: vestra_receipt_file_path bulunamadi\n"; exit(1); }
define('VESTRA_RECEIPTS_DIR', '/tmp/vestra-test-receipts');
eval($m[0]);
$p1 = vestra_receipt_file_path('../../etc/passwd', 'x.pdf');
$t('ref icindeki ".." temizlenir', !str_contains($p1, '..'));
$p2 = vestra_receipt_file_path('O123', '../../../etc/passwd');
$t('dosya adi basename ile kirpilir (klasor gezintisi yok)', basename($p2) === 'passwd' && !str_contains($p2, '../'));

echo "-- vestra_tpl_order_payment_due sablonu --\n";
$tsrc = file_get_contents(__DIR__.'/../vestra/inc/email_templates.php');
foreach (['vestra_display_name', 'vestra_tpl_order_payment_due'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $tsrc, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}
[$s, $b, $o] = vestra_tpl_order_payment_due('SC Daymond Proconect SRL', 'O12345', 'INV-2026-1001', 3950.00, 'EUR', '9 September 2026', true, 'https://vestrasales.com/buyer?tab=orders&view=O12345');
$t('konu ref + fatura no tasir', str_contains($s, 'O12345') && str_contains($s, 'INV-2026-1001'));
$t('govde tutari basar (parametreden, gomulu degil)', str_contains($b, '€3,950.00'));
$t('govde son tarihi basar', str_contains($b, '9 September 2026'));
$t('"5 business days" cumlesi', str_contains($b, '5 business days'));
$t('"automatically cancelled" cumlesi', str_contains($b, 'automatically cancelled'));
$t('hesap var -> yukleme linki govdede', str_contains($b, 'https://vestrasales.com/buyer?tab=orders&view=O12345'));
$t('hesap var -> dugme ayni linke gider', ($o['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=O12345');
$t('bilgi kutusunda son tarih satiri', ($o['rows'][3]['value'] ?? '') === '9 September 2026');
[$s2, $b2, $o2] = vestra_tpl_order_payment_due('', 'O9', 'INV-9', 100, 'USD', '1 October 2026', false, '');
$t('bos ad -> "Customer"', str_contains($b2, 'Dear Customer,'));
$t('USD -> US$ sembolu', str_contains($b2, 'US$100.00'));
$t('hesap yok -> e-posta yaniti onerilir, dugme yok', str_contains($b2, 'replying to this e-mail') && !isset($o2['button']));
$noTurkish = fn(string $s) => !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $s);
$t('Turkce karakter yok', $noTurkish($s.$b.$s2.$b2));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
