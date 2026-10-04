<?php
/**
 * ChatHelp — GECICI TESHIS: doGenerate ne aliyor / ne uretiyor (CH_GENDEBUG)
 * ------------------------------------------------------------------------
 *  Kaydetmeden HEMEN once alinan cevaplari, foto sayisini, olusan taslak/nihai
 *  metni korumali jbdata/gen-debug.json'a yazar. Boylece "cevaplar/fotolar
 *  belgede yok" sikayetinin KOK NEDENI netlesir (cevaplar geliyor mu? prompt'a
 *  giriyor mu? uretim bos mu donuyor?). TESHIS BITINCE GERI ALINMALI (bak dosyasi).
 *
 * KULLANIM: pull-updates.php?files=apply-gen-debug.php&run=1
 *   -> 1 belge uret (alanlari doldur) -> dump-gen-debug.php calistir.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-gen-debug BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_GENDEBUG")!==false) exit("Zaten ekli (CH_GENDEBUG).\n");

/* jbdata klasoru garanti */
$dir=__DIR__."/jbdata"; if(!is_dir($dir)) @mkdir($dir,0700,true);
$ht=$dir."/.htaccess"; if(!is_file($ht)) @file_put_contents($ht,"Require all denied\nDeny from all\n");

$anchor="    // Save document\n    \$docId  = bin2hex(random_bytes(8));";
if(substr_count($src,$anchor)!==1){ echo "✗ save anchor bulunamadi/coklu (".substr_count($src,$anchor).") — DEGISIKLIK YOK.\n"; exit; }

$dbg=<<<'PHP'
    /* CH_GENDEBUG — teshis: ne alindi / ne uretildi */
    @file_put_contents(__DIR__.'/jbdata/gen-debug.json', json_encode([
        'time'         => date('c'),
        'docType'      => $docType ?? null,
        'docName'      => $docName ?? null,
        'country'      => $country ?? null,
        'answers_keys' => is_array($answers ?? null) ? array_keys($answers) : 'ANSWERS_NOT_ARRAY',
        'answers'      => $answers ?? null,
        'answersText'  => $answersText ?? null,
        'photo_count'  => isset($chPhotos) && is_array($chPhotos) ? count($chPhotos) : 0,
        'vision_in_answers' => isset($answersText) && strpos((string)$answersText,'HOCHGELADENEN')!==false,
        'research_len' => isset($research) ? strlen((string)$research) : -1,
        'draft_len'    => isset($draft) ? strlen((string)$draft) : -1,
        'final_len'    => isset($final) ? strlen((string)$final) : -1,
        'draft_head'   => isset($draft) ? mb_substr((string)$draft,0,700) : null,
        'final_head'   => isset($final) ? mb_substr((string)$final,0,900) : null,
        'finalPrompt_head' => isset($finalPrompt) ? mb_substr((string)$finalPrompt,0,900) : null,
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);

    // Save document
    $docId  = bin2hex(random_bytes(8));
PHP;
$src=str_replace($anchor,$dbg,$src);

if($src===$start) exit("degisiklik yok!\n");
$tmp=$file.".tmp-gd"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-gendebug-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "✓ Teshis kaydi eklendi (CH_GENDEBUG).\n";
echo "SIMDI: 1 belge uret (alanlari doldur, mumkunse foto da yukle).\n";
echo "SONRA: pull-updates.php?files=dump-gen-debug.php&run=1 -> ciktiyi bana yapistir.\n";
echo "SIL: rm apply-gen-debug.php\n";
