<?php
/**
 * KURAL 2 — zorunlu belge istekleri: satir yoksa YUKLEME YOLU da yok.
 *
 * Operator, 10 Eyl 2026: *"seller de sadece bir dokuman indirilebiliyor oysaki
 * Trade Licence · Government ID (Passport / National ID) bu iki belge
 * indirilmesi gerekli"*.
 *
 * Yapisal kusur: satici Verification sayfasi ve panel, tabloyu hesabin KAYITLI
 * istek satirlarindan ciziyor, `auth_required_doc_types()`'tan degil. Yukleme
 * formu istek ID'sine bagli oldugu icin satir yoksa dugme de yok -- sayfa ustte
 * "her satici iki belge verir" derken alttaki tabloda tek satir gosteriyor ve
 * saticinin kimligi verecek HICBIR yolu kalmiyordu.
 *
 * Canli olcum (ayni gun, 109 hesap): 4 hesapta zorunlu bir satir eksikti;
 * Marca Online'da HIC satir yoktu (auth_register() disinda, create_seller ile
 * acilmis). Yani veri kusuru gercek ve tek hesaba ozel degil.
 */
/* Hesap deposunu GECICI bir dosyaya yonlendir -- auth.php sabiti defined()
   ile korumali oldugu icin burada onceden tanimlamak yetiyor. Bu olmadan test
   gercek data/accounts.json'i eziyordu (ilk yazimda tam bunu yapti: putenv
   ile bir env degiskeni kurmustum, oysa yol bir SABIT ve env hic okunmuyor). */
$sandbox = sys_get_temp_dir().'/vestra_doccov_'.bin2hex(random_bytes(4));
@mkdir($sandbox, 0777, true);
define('VESTRA_ACCOUNTS', $sandbox.'/accounts.json');

require_once __DIR__.'/../vestra/inc/products.php';
require_once __DIR__.'/../vestra/inc/auth.php';

$T = 0; $F = 0;
function ok(bool $c, string $m): void { global $T,$F; $T++; if(!$c){ $F++; echo "  HATA: $m\n"; } }

echo "== 1. Satir kurucusu: not metni TEK yerde ==\n";
$row = auth_doc_request_row('trade_licence', 'DE');
ok(($row['type'] ?? '') === 'trade_licence', 'tip yaziliyor');
ok(($row['status'] ?? '') === 'requested', 'yeni satir requested doguyor');
ok(!empty($row['id']), 'id uretiliyor (yukleme formu buna bagli)');
ok(!empty($row['requested_at']), 'istek damgasi var');
/* Ulkeye gore yerel ad: "Gewerbeschein" Almanya'nin belgesi, Irlandali bir
   butik icin o kelime hicbir sey anlatmaz. */
ok(strpos($row['note'], 'Gewerbeschein') !== false, 'DE: yerel belge adi notta');
ok(strpos(auth_doc_request_row('trade_licence', 'IE')['note'], 'Gewerbeschein') === false,
   'IE: Almanya belgesinin adi YAZILMIYOR');
ok(strpos(auth_doc_request_row('trade_licence', '')['note'], '(') === false,
   'ulke bilinmiyorsa notr cumle tek basina');
/* KURAL 2: "belgesiz hesap aktif edilemez" cumlesi HICBIR metinde olmamali --
   kapiyi operator onayi aciyor, belge uyaridir. */
foreach (['trade_licence','id_document'] as $t) {
    $n = strtolower(auth_doc_request_row($t, 'DE')['note']);
    ok(strpos($n, 'cannot be activated') === false, "$t: 'cannot be activated' yok");
}
ok(stripos(auth_doc_request_row('id_document')['note'], 'passport') !== false,
   'id_document notu pasaportu sayiyor');

echo "== 2. Zorunlu liste TEK kaynak ==\n";
ok(auth_required_doc_types('seller') === ['trade_licence','id_document'], 'satici: iki belge');
ok(auth_required_doc_types('buyer')  === ['trade_licence'],               'alici: tek belge');

echo "== 3. Eksik satir ACILIYOR, mevcut satira DOKUNULMUYOR ==\n";
/* Uc gercek vaka, canli olcumden birebir:
   a) hic satiri olmayan satici (create_seller ile acilmis),
   b) yalnizca id_document'i olan satici,
   c) tam satici -- hicbir sey yazilmamali. */
$accs = [
  ['id'=>'aaaa1111','type'=>'seller','company'=>'Hic Satiri Yok','country'=>'Turkey','doc_requests'=>[]],
  ['id'=>'bbbb2222','type'=>'seller','company'=>'Yarim','country'=>'France','doc_requests'=>[
      ['id'=>'keep01','type'=>'id_document','status'=>'approved','note'=>'operator notu',
       'requested_at'=>'2026-08-29T00:00:00+00:00','reviewed_at'=>'2026-08-30T00:00:00+00:00'],
  ]],
  ['id'=>'cccc3333','type'=>'seller','company'=>'Tam','country'=>'Italy','doc_requests'=>[
      ['id'=>'t1','type'=>'trade_licence','status'=>'requested'],
      ['id'=>'t2','type'=>'id_document','status'=>'requested'],
  ]],
  ['id'=>'dddd4444','type'=>'buyer','company'=>'Alici','country'=>'Spain','doc_requests'=>[]],
];
auth_save_accounts($accs);

$a1 = auth_ensure_required_doc_requests('aaaa1111');
ok($a1 === ['trade_licence','id_document'], 'satirsiz saticida IKI satir da aciliyor (gelen: ['.implode(', ',$a1).'])');

$a2 = auth_ensure_required_doc_requests('bbbb2222');
ok($a2 === ['trade_licence'], 'yarim saticida yalniz EKSIK olan aciliyor (gelen: ['.implode(', ',$a2).'])');

$a3 = auth_ensure_required_doc_requests('cccc3333');
ok($a3 === [], 'tam saticida hicbir sey acilmiyor');

$a4 = auth_ensure_required_doc_requests('dddd4444');
ok($a4 === ['trade_licence'], 'alicida yalniz ticari kayit aciliyor');
ok(auth_ensure_required_doc_requests('dddd4444') === [], 'alici: ikinci cagri no-op (idempotent)');

/* Mevcut satirin DURUMU, NOTU ve DAMGALARI korunmali: ensure bir tamamlama,
   bir sifirlama degil. Onaylanmis bir belgeyi "requested"a dondurmek,
   saticiya verdigi belgeyi tekrar sordurmak olurdu (KURAL 2b'nin dersi). */
$byId = [];
foreach (auth_accounts() as $x) $byId[$x['id']] = $x;
$keep = null;
foreach ($byId['bbbb2222']['doc_requests'] as $r) if (($r['id'] ?? '') === 'keep01') $keep = $r;
ok($keep !== null, 'mevcut satir duruyor');
ok(($keep['status'] ?? '') === 'approved', 'mevcut satirin DURUMU korunuyor');
ok(($keep['note'] ?? '') === 'operator notu', 'mevcut satirin NOTU korunuyor');
ok(($keep['reviewed_at'] ?? '') === '2026-08-30T00:00:00+00:00', 'inceleme damgasi korunuyor');
/* Ve yeni satirin kendi ID'si var -- yukleme formu ona baglanacak. */
$newIds = [];
foreach ($byId['bbbb2222']['doc_requests'] as $r) $newIds[$r['type']] = $r['id'] ?? '';
ok(!empty($newIds['trade_licence']) && $newIds['trade_licence'] !== 'keep01', 'yeni satirin ayri ID si var');
/* Ulke hesaptan okunuyor: Fransiz saticiya Fransa'nin belge adi. */
foreach ($byId['bbbb2222']['doc_requests'] as $r) {
    if (($r['type'] ?? '') === 'trade_licence')
        ok(strpos((string)($r['note'] ?? ''), 'Kbis') !== false, 'FR satici: notta extrait Kbis');
}

echo "== 4. apply=false hicbir sey YAZMAZ (kuru kosu) ==\n";
/* deploy kanaryasi cron_seller_docs.php'yi --dry ile kosuyor; kuru kosunun
   hicbir sey yazmamasi bu deponun kurali. */
$accs2 = [['id'=>'eeee5555','type'=>'seller','company'=>'Kuru','country'=>'Germany','doc_requests'=>[]]];
auth_save_accounts($accs2);
$dry = auth_ensure_required_doc_requests('eeee5555', false);
ok($dry === ['trade_licence','id_document'], 'kuru kosu NE ACILACAGINI soyluyor');
$back = auth_accounts();
ok(($back[0]['doc_requests'] ?? []) === [], 'kuru kosu diske YAZMADI');
ok(auth_ensure_required_doc_requests('eeee5555', true) === ['trade_licence','id_document'], 'apply=true gercekten yaziyor');
$back2 = auth_accounts();
ok(count($back2[0]['doc_requests']) === 2, 'yazildiktan sonra iki satir var');

echo "== 5. Bilinmeyen hesap: sessizce hicbir sey ==\n";
ok(auth_ensure_required_doc_requests('yok-boyle-bir-id') === [], 'olmayan hesapta bos donuyor');
ok(auth_ensure_required_doc_requests('') === [], 'bos uid guvenli');

echo "== 6. Kablolama: uc okuma yolu da tamamlamayi cagiriyor ==\n";
/* Kutuyu cizen her yer ayni fonksiyondan gecmeli; biri atlanirsa o ekranda
   satir yine eksik gorunur ve kusur "duzeltildi" sanilir. */
$sel  = file_get_contents(__DIR__.'/../vestra/seller.php');
$adm  = file_get_contents(__DIR__.'/../vestra/admin.php');
$cron = file_get_contents(__DIR__.'/../vestra/cron_seller_docs.php');
ok(strpos($sel,  'auth_ensure_required_doc_requests(') !== false, 'satici Verification sayfasi cagiriyor');
ok(strpos($adm,  'auth_ensure_required_doc_requests(') !== false, 'panel Documents listesi cagiriyor');
ok(strpos($cron, 'auth_ensure_required_doc_requests(') !== false, 'gunluk cron cagiriyor');
ok(strpos($cron, 'auth_ensure_required_doc_requests($uid, !$DRY)') !== false, 'cron kuru kosuda YAZMIYOR');
/* Kayit tarafi listeyi IKINCI kez elle yazmamali. */
$auth = file_get_contents(__DIR__.'/../vestra/inc/auth.php');
ok(strpos($auth, "foreach (auth_required_doc_types(\$type) as \$__t)") !== false,
   'auth_register zorunlu listeden kuruyor');
ok(substr_count($auth, "'type'=>'id_document', 'note'=>'Please upload a government-issued ID") === 0,
   'kayit tarafinda id_document notunun ikinci kopyasi YOK');

/* Temizlik */
@unlink(VESTRA_ACCOUNTS);
foreach (glob($sandbox.'/*') as $f) @unlink($f);
@rmdir($sandbox);

printf("\n%d iddia, %d hata\n", $T, $F);
exit($F ? 1 : 0);
