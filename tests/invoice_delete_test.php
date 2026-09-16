<?php
/* Kesilmis TEK faturanin kaldirilmasi (operator, 16 Eyl 2026: "faturalari
 * siparisleri silmek icin button koy").
 *
 * Tutulanlar:
 *  - SILMIYOR, ARSIVLIYOR: numarali belge diskten yok edilmiyor, deleted/
 *    altina tasiniyor. Alicinin elinde zaten bir kopya var.
 *  - YALNIZ O DILIM: bir siparisin birden fazla saticisi olabiliyor (KURAL 5b);
 *    yanlis kesilen genelde bir tanesi ve digerine DOKUNULMAMALI. Iddianin bu
 *    yonu olmazsa "hepsini tasiyan" bir hata yesil kalirdi.
 *  - Birlesik faturanin bagi kopariliyor (KURAL 5e): bag kalsaydi alici artik
 *    var olmayan bir belgenin satirini gormeye devam ederdi.
 *  - Geri okuma: dosya gercekten gitmediyse ok=false.
 */

/* Kum havuzu: gercek data/invoices'a DOKUNMUYOR. vestra_invoice_dir() normalde
   dirname(__DIR__).'/data/invoices' donuyor ve eval icinde __DIR__ bu test
   dosyasinin dizini olurdu -- yani depoya yazardi. Kendi surumumuzu once
   tanimlayip eval dongusunde onu atliyoruz. */
$SAND = sys_get_temp_dir().'/vestra_invdel_'.getmypid();
@mkdir($SAND.'/invoices', 0777, true);
function vestra_invoice_dir(): string { return $GLOBALS['SAND'].'/invoices'; }
function vestra_data_dir(): string    { return $GLOBALS['SAND']; }
function vestra_read_json(string $n): array {
    $f = vestra_data_dir().'/'.$n;
    return is_readable($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}
function vestra_write_json(string $n, array $d): void {
    file_put_contents(vestra_data_dir().'/'.$n, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

$src   = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);
preg_match_all('/^function (\w+)\(.*?^}/ms', $src, $fns, PREG_SET_ORDER);
foreach ($fns as $f) {
    if (in_array($f[1], ['vestra_invoice_dir'], true)) continue;   // kum havuzu surumu kazanir
    if (function_exists($f[1])) continue;
    eval($strip($f[0]));
}

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

/* --- kurulum: AYNI ref'te IKI satici dilimi + baska bir ref + birlesik grup --- */
$dir = vestra_invoice_dir();
$mk = function(string $ref, string $sk, string $no) use ($dir) {
    $slug = vestra_invoice_slug($ref, $sk);
    file_put_contents($dir.'/'.$slug.'.json', json_encode(['no'=>$no,'seller_key'=>$sk,'total'=>100.0,'currency'=>'EUR']));
    file_put_contents($dir.'/'.$slug.'.pdf', '%PDF-1.4 test');
};
$mk('ORD1','sellerA','INV-2026-9001');
$mk('ORD1','sellerB','INV-2026-9002');
$mk('ORD2','sellerA','INV-2026-9003');
vestra_write_json('offer_responses.json', [
    'ORD1' => ['status'=>'accept','invoice_members'=>['ORD1','OFF2']],
    'OFF2' => ['status'=>'accept','invoice_group_ref'=>'ORD1'],
    'OFF9' => ['status'=>'accept','invoice_group_ref'=>'BASKAREF'],
]);

echo "\n== 1. Tek dilim kaldiriliyor ==\n";
$r = vestra_invoice_delete('ORD1','sellerA');
$t('ok dondu',                        !empty($r['ok']));
$t('numara KAYDEDILDI (INV-2026-9001)', ($r['no'] ?? '') === 'INV-2026-9001');
$t('iki dosya tasindi (json+pdf)',    (int)($r['moved'] ?? 0) === 2);
$t('meta dosyasi gitti',              !is_file($dir.'/ORD1__sellerA.json'));
$t('pdf gitti',                       !is_file($dir.'/ORD1__sellerA.pdf'));

/* SILINMEDI, TASINDI: numarali belge diskte durmali. */
$arch = glob($dir.'/deleted/*ORD1__sellerA.*') ?: [];
$t('arsivde iki dosya var',           count($arch) === 2);
$t('arsivlenen pdf OKUNABILIR',       (bool)array_filter($arch, fn($f) => str_ends_with($f,'.pdf') && (string)file_get_contents($f) === '%PDF-1.4 test'));

echo "\n== 2. TERS YON: dokunulmamasi gerekenler ==\n";
/* Ayni ref'in DIGER saticisi ve baska bir ref yerinde kalmali. Tek yon
   yazilsaydi butun dilimleri tasiyan bir hata yesil kalirdi. */
$t('ayni ref, DIGER satici duruyor',  is_file($dir.'/ORD1__sellerB.json') && is_file($dir.'/ORD1__sellerB.pdf'));
$t('baska ref duruyor',               is_file($dir.'/ORD2__sellerA.json'));

echo "\n== 3. Birlesik faturanin bagi ==\n";
$rs = vestra_read_json('offer_responses.json');
$t('uyenin invoice_group_ref bagi koptu', !isset($rs['OFF2']['invoice_group_ref']));
$t('kopan bag SAYILDI',                   (int)($r['unlinked'] ?? 0) === 1);
$t('birincilin uye listesi silindi',      !isset($rs['ORD1']['invoice_members']));
/* BASKA bir gruba bagli teklif etkilenmemeli. */
$t('ilgisiz grup bagi KORUNDU',           ($rs['OFF9']['invoice_group_ref'] ?? '') === 'BASKAREF');
$t('teklif kaydinin kendisi duruyor',     ($rs['OFF2']['status'] ?? '') === 'accept');

echo "\n== 4. Redler -- ve hicbir sey tasinmiyor ==\n";
$before = count(glob($dir.'/*.json') ?: []);
$r2 = vestra_invoice_delete('', 'sellerB');
$t('ref bos -> ok=false',             empty($r2['ok']) && ($r2['error'] ?? '') !== '');
$r3 = vestra_invoice_delete('ORD1','');
$t('satici bos -> ok=false',          empty($r3['ok']) && ($r3['error'] ?? '') !== '');
$r4 = vestra_invoice_delete('ORD1','sellerA');          // zaten kaldirildi
$t('olmayan dilim -> ok=false',       empty($r4['ok']));
$r5 = vestra_invoice_delete('YOKREF','sellerA');
$t('olmayan ref -> ok=false',         empty($r5['ok']));
$t('redlerde HICBIR dosya tasinmadi', count(glob($dir.'/*.json') ?: []) === $before);

echo "\n== 5. Panel kablolamasi ==\n";
$ad = file_get_contents(__DIR__.'/../vestra/admin.php');
$t('invoice_delete eylemi var',       str_contains($ad, "\$act==='invoice_delete'"));
$t('tek karar noktasini cagiriyor',   str_contains($ad, 'vestra_invoice_delete($ref,$sk)'));
/* Dugme DILIM basina olmali: yalniz ref gonderen bir form, operatorun
   dokunmak istemedigi saticinin faturasini da hedefler. */
$t('form satici anahtarini tasiyor',  str_contains($ad, 'name="seller" value="<?= htmlspecialchars((string)$iv[\'seller_key\']) ?>"'));
/* Onay metni geri alinamaz olani SOYLEMELI. */
$t('onay: alicida zaten kopya var',   str_contains($ad, 'buyer already has a copy'));
$t('onay: yeniden kesim YENI numara', str_contains($ad, 'burns a NEW number'));
$t('onay: silinmiyor, tasiniyor',     str_contains($ad, 'MOVED to data/invoices/deleted/'));
/* Siparis silme DOSYA gorunumunde de olmali (listede zaten vardi). */
$t('dosya gorunumunde Delete order',  str_contains($ad, 'Delete order'));
$t('dosya gorunumu order_delete yolluyor', preg_match('/Danger zone.*?name="_action" value="order_delete"/s', $ad) === 1);
$t('iki mesaj da yazili',             str_contains($ad, "invoice_deleted") && str_contains($ad, "invoice_del_bad"));

exec('rm -rf '.escapeshellarg($SAND));
printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
