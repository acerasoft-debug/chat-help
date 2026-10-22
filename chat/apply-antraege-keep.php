<?php
/**
 * ChatHelp — apply-antraege-keep (CH_ANTRAEGE_KEEP)
 *  "Behalten tiklandiginda HEP kalmali":
 *   cleanOld() TAMAMEN yeniden yazilir — keep=true olan belgeler ASLA silinmez;
 *   digerleri TTL (24h gizlilik sozu) sonunda silinmeye devam eder.
 *   (Mevcut cleanOld govdesi bilinmiyordu; blok-sinirli degisim + eski govde
 *    cikti icin yazdirilir. keep korumasi zaten varsa dosya DEGISTIRILMEZ.)
 *  + TESHIS (sadece okur): doGenerate kayit kuyrugu + doAiChat (chat dilekceleri
 *    kaydediliyor mu?) + fall-api kayit var mi — "yapilan dilekceler listede yok"
 *    sorununun ikinci yarisi icin.
 * KULLANIM: pull-updates.php?files=apply-antraege-keep.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-antraege-keep BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$api = @file_get_contents($file);
if ($api===false) exit("api.php okunamadi\n");

/* ═══ TESHIS ═══ */
echo "###### TESHIS: kayit yollari ######\n";
/* doGenerate kuyrugu (kayit + respond) */
$s = strpos($api,'function doGenerate');
if ($s!==false) {
    $e = strpos($api,"\nfunction ",$s+10);
    $body = substr($api,$s,($e===false?6000:$e-$s));
    echo "── doGenerate SON 1000 (kayit/respond) ──\n".substr($body,-1000)."\n---\n\n";
    echo "doGenerate icinde STORE_DIR yaziyor mu: ".(strpos($body,'STORE_DIR')!==false?"EVET":"HAYIR — sablon belgeler de kaydedilmiyor!")."\n\n";
}
/* doAiChat tam */
$s = strpos($api,'function doAiChat');
if ($s!==false) {
    $e = strpos($api,"\nfunction ",$s+10);
    $body = substr($api,$s,($e===false?4500:min($e-$s,4500)));
    echo "── doAiChat (ilk 4500) ──\n".$body."\n---\n";
    echo "doAiChat icinde STORE_DIR yaziyor mu: ".(strpos($body,'STORE_DIR')!==false?"EVET":"HAYIR — chat dilekceleri kaydedilmiyor!")."\n\n";
}
/* fall-api kayit */
$fall = @file_get_contents(__DIR__.'/fall-api.php');
if ($fall!==false) {
    echo "── fall-api.php ──\n";
    foreach (['STORE_DIR','file_put_contents','history','keep'] as $k)
        echo "  '$k' -> ".substr_count($fall,$k)." kez\n";
    echo "\n";
}
/* TTL degeri */
if (preg_match("/define\('TTL'[^;]*;/",$api,$m)) echo "TTL: ".$m[0]."\n\n";

/* ═══ cleanOld yeniden yaz ═══ */
if (strpos($api,'CH_ANTRAEGE_KEEP')!==false) exit("Zaten ekli (CH_ANTRAEGE_KEEP).\n");
$s = strpos($api,'function cleanOld');
if ($s===false) exit("HATA: cleanOld bulunamadi.\n");
$e = strpos($api,"\nfunction ",$s+10);
if ($e===false || ($e-$s)>2500) exit("HATA: cleanOld blok siniri belirsiz (".($e===false?'yok':($e-$s))." bayt) — dokunulmadi.\n");
$old = substr($api,$s,$e-$s);
echo "###### MEVCUT cleanOld ######\n".$old."\n---\n\n";

if (strpos($old,'keep')!==false) {
    echo "cleanOld ZATEN keep koruyor — degisiklik gerekmedi. Sorun kayit yollarinda (yukaridaki teshise gore devam edilecek).\n";
    exit;
}

$new = "function cleanOld(): void { /*CH_ANTRAEGE_KEEP — behalten=ASLA silme*/\n"
     . "    foreach (glob(STORE_DIR.'*_*.json') as \$f) {\n"
     . "        \$d = json_decode(@file_get_contents(\$f), true);\n"
     . "        if (!\$d) continue;\n"
     . "        if (!empty(\$d['keep'])) continue;\n"
     . "        if (time() - (int)(\$d['created'] ?? 0) > TTL) @unlink(\$f);\n"
     . "    }\n"
     . "}\n";
$api = substr($api,0,$s).$new.substr($api,$e+1);

$tmp = tempnam(sys_get_temp_dir(),'ak').'.php';
file_put_contents($tmp,$api);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "LINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-antkeep-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$api);
echo "✓ CH_ANTRAEGE_KEEP uygulandi: 'Behalten' isaretli belgeler artik ASLA otomatik silinmez; digerleri 24h gizlilik kurali geregi silinmeye devam eder.\n";
