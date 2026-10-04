<?php
/**
 * ChatHelp — TESHIS 2: doGenerate BASINA giris kaydi (CH_GENDEBUG2)
 * ------------------------------------------------------------------------
 *  answersText olusturulduktan HEMEN sonra (AI cagrilarindan ONCE) ham istegi
 *  + cevaplari jbdata/gen-debug-in.json'a yazar. Boylece uretim ortada koparsa
 *  bile ne geldigini goruruz. GECICI.
 * KULLANIM: pull-updates.php?files=apply-gen-debug2.php&run=1 -> 1 belge uret ->
 *   dump-gen-debug.php calistir.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-gen-debug2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_GENDEBUG2")!==false) exit("Zaten ekli (CH_GENDEBUG2).\n");

$dir=__DIR__."/jbdata"; if(!is_dir($dir)) @mkdir($dir,0700,true);

$anchor="    \$answersText = implode(\"\\n\", array_filter(explode(\"\\n\", \$answersText)));";
if(substr_count($src,$anchor)!==1){ echo "✗ answersText anchor bulunamadi/coklu (".substr_count($src,$anchor)."). DEGISIKLIK YOK.\n"; exit; }

$ins=<<<'PHP'

    /* CH_GENDEBUG2 — giris kaydi (AI cagrilarindan once) */
    @file_put_contents(__DIR__.'/jbdata/gen-debug-in.json', json_encode([
        'time'         => date('c'),
        'docType'      => $docType ?? null,
        'docName'      => $docName ?? null,
        'country'      => $country ?? null,
        'body_keys'    => is_array($b ?? null) ? array_keys($b) : 'B_NOT_ARRAY',
        'answers_type' => gettype($answers ?? null),
        'answers_keys' => is_array($answers ?? null) ? array_keys($answers) : 'ANSWERS_NOT_ARRAY',
        'answers'      => $answers ?? null,
        'answersText'  => $answersText ?? null,
        'photos_in_body' => isset($b['photos']) && is_array($b['photos']) ? count($b['photos']) : 0,
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
PHP;
$src=str_replace($anchor, $anchor.$ins, $src);

if($src===$start) exit("degisiklik yok!\n");
$tmp=$file.".tmp-gd2"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-gendebug2-".date("Ymd-His"),$start);
file_put_contents($file,$src);

/* yazilabilirlik testi */
$tf=$dir."/_wtest.txt"; $wok=@file_put_contents($tf,"ok")!==false; @unlink($tf);
echo "✓ Giris kaydi eklendi (CH_GENDEBUG2).\n";
echo "  jbdata yazilabilir mi: ".($wok?"EVET":"HAYIR (izin sorunu!)")."\n";
echo "SIMDI: 1 belge uret -> pull-updates.php?files=dump-gen-debug.php&run=1\n";
echo "SIL: rm apply-gen-debug2.php\n";
