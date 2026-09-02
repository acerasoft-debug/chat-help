<?php
/* Satici belge suresi (operator karari, 2 Eyl 2026): "ilk urun eklensin,
 * sonrasinda belgeler icin 3 gun; yuklemezse suspend."
 *
 * Karar auth_seller_doc_grace() -- saf fonksiyon. Bu test asamalari ve
 * ozellikle TUZAKLARI tutar:
 *   - saat ilan tarihinden GERIYE DONUK hesaplanmaz (damga yoksa 'unstamped'),
 *   - ilani olmayan saticiya saat islemez,
 *   - 'uploaded' belge VERILMIS sayilir (onay bekliyor, tekrar istenmez),
 *   - alici bu kuraldan etkilenmez,
 *   - askidaki hesap 'suspended' doner, sebebiyle.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/auth.php');
if (!defined('VESTRA_SELLER_DOC_GRACE_DAYS')) define('VESTRA_SELLER_DOC_GRACE_DAYS', 3);
foreach (['auth_required_doc_types','auth_missing_doc_types','auth_doc_grace_exempt_uids','auth_doc_grace_exempt','auth_seller_doc_grace'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn auth.php'de bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$NOW = 1_800_000_000;                  // sabit "simdi": test gunden bagimsiz
$D   = 86400;
$req = fn(string $type, string $st) => ['id'=>substr(md5($type.$st),0,8), 'type'=>$type, 'status'=>$st];
$seller = fn(array $over = []) => array_merge(['id'=>'s1','type'=>'seller','status'=>'active','doc_requests'=>[$req('trade_licence','requested'), $req('id_document','requested')]], $over);
$L = [['id'=>'p1','seller_uid'=>'s1','status'=>'pending','added_at'=>'2026-09-01T10:00:00+00:00']];

echo "-- eksik belge listesi --\n";
$t('satici: ikisi de eksik',                    auth_missing_doc_types($seller()) === ['trade_licence','id_document']);
$t("'uploaded' verilmis sayilir",               auth_missing_doc_types($seller(['doc_requests'=>[$req('trade_licence','uploaded'), $req('id_document','requested')]])) === ['id_document']);
$t("'approved' verilmis sayilir",               auth_missing_doc_types($seller(['doc_requests'=>[$req('trade_licence','approved'), $req('id_document','approved')]])) === []);
$t("'rejected' eksik sayilir",                  auth_missing_doc_types($seller(['doc_requests'=>[$req('trade_licence','rejected'), $req('id_document','approved')]])) === ['trade_licence']);
$t('istek hic acilmamis = eksik',               auth_missing_doc_types($seller(['doc_requests'=>[]])) === ['trade_licence','id_document']);
$t('alici yalnizca ticari kayit borclu',        auth_missing_doc_types(['type'=>'buyer','doc_requests'=>[]]) === ['trade_licence']);

echo "-- asamalar --\n";
$g = auth_seller_doc_grace(['type'=>'buyer','status'=>'active','doc_requests'=>[]], $L, $NOW);
$t('alici -> clear (kural saticiya ozgu)',      $g['phase'] === 'clear' && $g['missing'] === []);
$g = auth_seller_doc_grace($seller(['doc_requests'=>[$req('trade_licence','uploaded'), $req('id_document','approved')]]), $L, $NOW);
$t('belgeler tam -> clear',                     $g['phase'] === 'clear');
$g = auth_seller_doc_grace($seller(), [], $NOW);
$t('ilani yok -> none (saat islemez)',          $g['phase'] === 'none' && $g['has_listing'] === false);
$g = auth_seller_doc_grace($seller(), $L, $NOW);
$t('ilani var, damga yok -> unstamped',         $g['phase'] === 'unstamped' && $g['deadline'] === null);
$t('   ...ilan tarihinden geriye donuk saat YOK', $g['start'] === null);

$stamp = fn(int $ago) => date('c', $NOW - $ago);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(1*$D)]), $L, $NOW);
$t('1 gun once basladi -> running, 2 gun kaldi', $g['phase'] === 'running' && $g['days_left'] === 2);
$t('   son tarih = baslangic + 3 gun',           $g['deadline'] === ($NOW - 1*$D) + 3*$D);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(0)]), $L, $NOW);
$t('az once basladi -> running, 3 gun kaldi',    $g['phase'] === 'running' && $g['days_left'] === 3);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(2*$D + 3600)]), $L, $NOW);
$t('23 saat kaldi -> due_soon, 1 gun',           $g['phase'] === 'due_soon' && $g['days_left'] === 1);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(3*$D)]), $L, $NOW);
$t('tam 3 gun doldu -> expired',                 $g['phase'] === 'expired' && $g['days_left'] === 0);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(10*$D)]), $L, $NOW);
$t('cok gecmis -> expired, days_left negatif degil gosterilir', $g['phase'] === 'expired' && $g['days_left'] <= 0);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>'bozuk-tarih']), $L, $NOW);
$t('bozuk damga -> unstamped (cokme yok)',       $g['phase'] === 'unstamped');

echo "-- aski --\n";
$g = auth_seller_doc_grace($seller(['status'=>'suspended','suspend_reason'=>'docs','doc_grace_start'=>$stamp(5*$D)]), $L, $NOW);
$t('belge askisi -> suspended, reason=docs',     $g['phase'] === 'suspended' && $g['reason'] === 'docs');
$g = auth_seller_doc_grace($seller(['status'=>'suspended','suspend_reason'=>'operator']), $L, $NOW);
$t('operator askisi -> suspended, reason=operator', $g['phase'] === 'suspended' && $g['reason'] === 'operator');
$g = auth_seller_doc_grace($seller(['status'=>'suspended','suspend_reason'=>'docs','doc_requests'=>[$req('trade_licence','uploaded'), $req('id_document','uploaded')]]), $L, $NOW);
$t('askida ama belgeler geldi -> clear (operator acacak)', $g['phase'] === 'clear' && $g['missing'] === []);

echo "-- muafiyet (operator karari: GARAGE LE PARIS) --\n";
$g = auth_seller_doc_grace($seller(['id'=>'7ab30f26afedd840','doc_grace_start'=>$stamp(10*$D)]), $L, $NOW);
$t('GARAGE (kod varsayilani) -> exempt, sure gecmis olsa bile aski yok', $g['phase'] === 'exempt' && $g['exempt'] === true);
$t('   ...eksik belgeler yine listelenir',                              $g['missing'] === ['trade_licence','id_document']);
$g = auth_seller_doc_grace($seller(['id'=>'7ab30f26afedd840','doc_grace_exempt'=>false,'doc_grace_start'=>$stamp(10*$D)]), $L, $NOW);
$t('hesap bayragi false kodun varsayilanini EZER -> expired',            $g['phase'] === 'expired');
$g = auth_seller_doc_grace($seller(['doc_grace_exempt'=>true,'doc_grace_start'=>$stamp(10*$D)]), $L, $NOW);
$t('baska saticida bayrak true -> exempt',                              $g['phase'] === 'exempt');
$g = auth_seller_doc_grace($seller(['doc_grace_exempt'=>'', 'doc_grace_start'=>$stamp(1*$D)]), $L, $NOW);
$t("bos bayrak ('') = karar yok -> kural isler (running)",              $g['phase'] === 'running');
$g = auth_seller_doc_grace($seller(['id'=>'7ab30f26afedd840','doc_requests'=>[$req('trade_licence','uploaded'), $req('id_document','uploaded')]]), $L, $NOW);
$t('muaf ama belgeler tam -> clear (muafiyet gereksiz)',                $g['phase'] === 'clear' && $g['exempt'] === false);
$t('muaf listesi yalnizca GARAGE',                                       auth_doc_grace_exempt_uids() === ['7ab30f26afedd840']);
$t('muaf satici askida degil, bayrak yok, ilan yok -> exempt oncelikli', auth_seller_doc_grace($seller(['id'=>'7ab30f26afedd840']), [], $NOW)['phase'] === 'exempt');

echo "-- ikinci ilan saati yeniden baslatmaz --\n";
$L2 = array_merge($L, [['id'=>'p2','seller_uid'=>'s1','status'=>'approved','added_at'=>date('c', $NOW)]]);
$g = auth_seller_doc_grace($seller(['doc_grace_start'=>$stamp(2*$D + 3600)]), $L2, $NOW);
$t('yeni ilan eklense de due_soon kalir',        $g['phase'] === 'due_soon');

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
