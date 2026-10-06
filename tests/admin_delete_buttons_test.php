<?php
/**
 * SILME DUGMESI, OPERATORUN BAKTIGI EKRANDA (operator, 16 Eyl 2026:
 * *"siparisler ve offer lar silinmesi icin button yap demistim"*).
 *
 * OLCULDU: iki dugme de ZATEN VARDI -- `Admin ▸ Offers`'ta 🗑 Sil,
 * `Admin ▸ Orders`'ta Delete. Eksik olan sey dugme degil, KONUMU:
 * `Admin ▸ Invoice approvals` kuyrugunda hicbir silme yolu yoktu ve operatorun
 * kabul edilmis bir teklifi tam orada goruyor. Bir ekranda gorunmeyen secenek
 * olmayan secenektir -- bu depoda KURAL 2e olarak kayitli ("acacak dugmem yok",
 * operator iki kez soyledi).
 *
 * BU TESTIN TUTTUGU UC OLGU:
 *  1. Dugmeler o kuyrukta GERCEKTEN CIZILIYOR (kaynak taramasi degil: admin.php
 *     kum havuzunda kosturulup HTML'i okunuyor -- bu depoda "kaynakta gormek
 *     olcum degil" defalarca kayitli).
 *  2. IKINCI BIR SILME YOLU YOK: ayni `_action` cagriliyor. Ikinci bir uygulama
 *     yedek almayi, pazarlik kaydinin yedegini ya da faturali kayitta reddi
 *     kaciririrdi ve ayrisma ancak bir kayit kayboldugunda gorunurdu.
 *  3. `back` IZIN LISTESI: deger POST'tan geliyor ve bir Location basligina
 *     giriyor. Serbest birakmak acik yonlendirme olurdu.
 */
$root = dirname(__DIR__);
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "  KIRMIZI: {$n}\n"; }
};

/* ─────────────────────────────────────────────────────────────────────────
   1. `back` IZIN LISTESI — DAVRANISSAL, grep degil.
   Kapanis admin.php'nin icinde ve disaridan cagrilamiyor, o yuzden tanimi
   kaynaktan CIKARIP eval ediyoruz. Bu depoda ayni teknik bir kez daha
   kullanildi (workflow'un "hitap" satiri): duz bir grep, satir baska bir
   yazimla geri geldiginde yesil kalirdi.
   ───────────────────────────────────────────────────────────────────────── */
$src = (string)@file_get_contents($root.'/vestra/admin.php');
$t('admin.php okunabildi', $src !== '');

if (preg_match('/\$backTab\s*=\s*function\s*\(string \$default\): string \{.*?\n  \};/s', $src, $m)) {
    $t('$backTab tanimi kaynaktan cikarildi', true);
    $_POST = [];
    eval($m[0]);
    /* Taninan hedefler aynen geciyor. */
    foreach (['offers','orders','invoices'] as $good) {
        $_POST['back'] = $good;
        $t("back={$good} aynen geciyor", $backTab('offers') === $good);
    }
    /* Taninmayan her sey VARSAYILANA duser -- yani mevcut davranis korunur. */
    $_POST['back'] = '';        $t('bos back -> varsayilan',        $backTab('orders') === 'orders');
    unset($_POST['back']);      $t('back HIC yoksa -> varsayilan',  $backTab('offers') === 'offers');
    /* ACIK YONLENDIRME: disari cikaran hicbir deger gecmemeli. */
    foreach ([
        'https://evil.example', '//evil.example', '/\\evil.example',
        'offers&x=1', 'invoices ', 'INVOICES', '../../etc', "offers\nLocation: x",
    ] as $bad) {
        $_POST['back'] = $bad;
        $t('disari cikaran deger reddedildi: '.json_encode($bad), $backTab('offers') === 'offers');
    }
    $_POST = [];
} else {
    $t('$backTab tanimi kaynaktan cikarildi', false);
}

/* ─────────────────────────────────────────────────────────────────────────
   2. IKINCI BIR SILME YOLU YAZILMADI.
   Invoice approvals kuyrugundaki dugmeler MEVCUT eylemleri cagirmali. Yeni bir
   `_action` adi, yedekleme/fatura kapisi olmayan ikinci bir silme demek olurdu.
   ───────────────────────────────────────────────────────────────────────── */
$t('delete_offer eylemi TEK yerde tanimli',
   substr_count($src, "\$act==='delete_offer'") === 1);
$t('order_delete eylemi TEK yerde tanimli',
   substr_count($src, "\$act==='order_delete'") === 1);
/* Faturali teklifte ret, handler'da duruyor (dugmenin gorunmemesi yetki degil). */
$t('delete_offer faturali teklifi hala reddediyor',
   str_contains($src, 'if(count(vestra_invoices_for_ref($ref))>0){'));
$t('order_delete faturali siparisi ilk tikta hala reddediyor',
   str_contains($src, 'if($inv && empty($_POST[\'force\'])){'));
/* force=1 (numarasi yanmis belgeyi tasiyarak silme) YALNIZ bir yerde: iki
   ekrandan birden ulasilabilir olsaydi KURAL 5g'nin korudugu sey gevserdi. */
$t('force=1 yolu tek yerde',
   substr_count($src, '<input type="hidden" name="force" value="1">') === 1);

/* ─────────────────────────────────────────────────────────────────────────
   3. CIZIM — admin.php kum havuzunda GERCEKTEN kosuyor.
   ───────────────────────────────────────────────────────────────────────── */
$sb = sys_get_temp_dir().'/vestra_delbtn_'.getmypid();
@mkdir($sb, 0777, true);
$rc = 0; $o = [];
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
$t('kum havuzu kuruldu', $rc === 0);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data'));
@mkdir($sb.'/vestra/data', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");

/* Tohum: bir ilan, KABUL EDILMIS + FATURASIZ teklif, FATURASIZ bekleyen siparis.
   Operatorun ekran goruntusundeki satirin aynisi (O2E880 / Casablanca). */
file_put_contents($sb.'/vestra/data/listings.json', json_encode([[
  'id'=>'cbl-larch','brand'=>'Casablanca','name'=>"Casablanca L'Arch T-Shirt — Black",
  'sku'=>'LARCH-BLACK','cat'=>'T-Shirts','status'=>'approved','mode'=>'fixed',
  'moq'=>20,'list'=>62.50,'tiers'=>[['min'=>20,'price'=>62.50]],'seller_uid'=>'tyrexuid00000001',
]], JSON_UNESCAPED_UNICODE));
file_put_contents($sb.'/vestra/data/offers.csv',
  "ref,at,listing,sku,qty,unit,total,company,email\n"
 ."O2E880,2026-09-16 10:00:00,cbl-larch,LARCH-BLACK,20,62.50,1250.00,Easyauto24,buyer@example.test\n");
file_put_contents($sb.'/vestra/data/offer_responses.json', json_encode([
  'O2E880'=>['status'=>'accept','accepted_by'=>'operator','responded_at'=>'2026-09-16 11:00:00','counters'=>[]],
]));
file_put_contents($sb.'/vestra/data/orders.csv',
  "ref,at,email,company,total,shipping,status,notes\n"
 ."VES-TEST01,2026-09-16 09:00:00,buyer@example.test,Easyauto24,1250.00,0,pending,Payment: transfer.\n");
file_put_contents($sb.'/vestra/data/order_statuses.json', '{}');

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
$t('giris formu DEGIL, panel cizildi', !str_contains($html, 'name="pass"'));
$t('bekleyen teklif bolumu var',  str_contains($html, 'accepted offer(s) awaiting an invoice'));
$t('bekleyen siparis bolumu var', str_contains($html, 'order(s) awaiting your approval'));

/* ASIL IDDIA: iki silme formu da BU kuyrukta basiliyor. */
$t('TEKLIF silme formu bu kuyrukta ciziliyor',
   substr_count($html, 'name="_action" value="delete_offer"') === 1);
$t('SIPARIS silme formu bu kuyrukta ciziliyor',
   substr_count($html, 'name="_action" value="order_delete"') === 1);
/* Ikisi de silinecek kaydin ref'ini tasiyor. */
$t('teklif formu dogru ref tasiyor',  str_contains($html, 'value="O2E880"'));
$t('siparis formu dogru ref tasiyor', str_contains($html, 'value="VES-TEST01"'));
/* back=invoices: silen operator bu kuyruga geri donup satirin gittigini gormeli. */
$t('iki form da back=invoices tasiyor',
   substr_count($html, 'name="back" value="invoices"') === 2);
/* CSRF: bu depoda bir kez unutuldu ve dugme GORUNUR ama HIC calismaz oldu. */
$t('CSRF alani sayfada var', str_contains($html, '_csrf'));
$t('PHP uyarisi yok', !preg_match('/\b(Warning|Fatal error|Deprecated)\b/', $html));

/* KONTROL GRUBU — tek yon yazilsaydi test yesil kalirdi:
   silme mesajlari sekme dagitimindan ONCE basiliyor mu? Basilmasaydi operator
   silme sonrasi bu kuyruga doner ve HICBIR SEY gormezdi, yani dugmenin
   calismadigini dusunurdu (bu dosyanin `billing_saved` icin kayitli dersi). */
$posMsg = strpos($src, "\$msg==='ord_deleted'");
$posTab = strpos($src, "if(\$tab==='overview')");
$t('silme mesajlari sekme dagitimindan ONCE (her sekmede gorunur)',
   $posMsg !== false && $posTab !== false && $posMsg < $posTab);
$t('offer_deleted mesaji haritada var', str_contains($src, "'offer_deleted'=>"));

exec('rm -rf '.escapeshellarg($sb));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
