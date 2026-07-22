<?php
/**
 * ChatHelp — apply-plandbg3-remove (CH_PLANDBG3_REMOVE) — Konto sayfasindaki
 *  kirmizi DEBUG kutusunu (token/ch_user.plan/P.plan) kaldirir. Teshis bitti,
 *  gorunur debug artik gerekmiyor. SADECE CH_PLANDBG3 script blogunu siler —
 *  CH_CURPLANFIX / plan mantigi ETKILENMEZ.
 * KULLANIM: pull2.php?key=...&files=apply-plandbg3-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plandbg3-remove BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PLANDBG3')===false) exit("CH_PLANDBG3 zaten yok (belki daha once kaldirildi).\n");

$start=strpos($src,'<script id="ch-plandbg3-js">');
if($start===false) exit("baslangic etiketi bulunamadi\n");
$endTag='</script>';
$end=strpos($src,$endTag,$start);
if($end===false) exit("bitis etiketi bulunamadi\n");
$end+=strlen($endTag);
/* olasi tek bir sondaki \n'i de al */
if(substr($src,$end,1)==="\n") $end++;

$block=substr($src,$start,$end-$start);
if(strpos($block,'CH_PLANDBG3')===false) exit("blok icinde marker yok, guvenlik icin durduruldu\n");

$src2=substr($src,0,$start).substr($src,$end);

$tmp=tempnam(sys_get_temp_dir(),'pd3r').'.php'; file_put_contents($tmp,$src2);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-plandbg3remove-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src2);
if($w===false||$w<strlen($src2)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PLANDBG3')!==false) exit("\n✗ DOGRULAMA BASARISIZ (hala mevcut).\n");
echo "  ✓ CH_PLANDBG3 kutusu kaldirildi (".strlen($chk)." B)\n";
echo "  Test: Konto sayfasina git -> plan rozetinin altinda kirmizi DEBUG yazisi\n";
echo "  artik OLMAMALI.\n";
