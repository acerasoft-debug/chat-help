<?php
/**
 * ChatHelp — apply-waitdedup-off — CH_WAITDEDUP'i TAMAMEN KALDIRIR.
 *
 * NEDEN: CH_WAITDEDUP her DOM degisiminde tum sayfayi tariyordu
 * (querySelectorAll('div,p,span,li,section')). 2.5 MB'lik sayfada cok agir;
 * balon metnini gizleyip avatarlari birakti (bos 'CH' sutunu) ve sayfayi
 * yavaslatarak ausdrucken/PDF akisini bozdu. Geri aliniyor.
 *
 * Ayrica: gizlenmis olabilecek ogeleri de acar (data-ch-waithidden temizligi
 * gerekmiyor — script gidince yeni sayfa yuklemesinde hicbir sey gizlenmez).
 *
 * KORUNAN: CH_ISWV_GLOBAL, CH_APPVIEW3, CH_APPVIEW2C (4), CH_MAILBTN.
 * Lint + .bak + dogrulama + kilit tazeleme.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-waitdedup-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-waitdedup-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$c=substr_count($src,'CH_WAITDEDUP');
if($c<1) exit("Zaten yok (CH_WAITDEDUP bulunamadi) — DEGISIKLIK YOK.\n");

$new=preg_replace('/<script>\/\*CH_WAITDEDUP\*\/.*?<\/script>/s','',$src,-1,$rm);
if($new===null) exit("preg hatasi\n");
if($rm<1) exit("Blok bulunamadi (bicim degismis) — DEGISIKLIK YOK.\n");

/* korunanlar */
$keep=['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN'=>1];
foreach($keep as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret kaybolurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'wo').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-waitdedupoff-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($file);
echo "✓ CH_WAITDEDUP kaldirildi ($rm blok, ".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  kalan CH_WAITDEDUP: ".substr_count($cur,'CH_WAITDEDUP')."\n";
echo "  korunanlar: CH_ISWV_GLOBAL=".substr_count($cur,'CH_ISWV_GLOBAL')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_APPVIEW2C=".substr_count($cur,'CH_APPVIEW2C')
    ."  CH_MAILBTN=".substr_count($cur,'CH_MAILBTN')."\n";

/* kilidi tazele */
@file_put_contents($file.'.GOOD-appprint',$cur);
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'waitdedup geri alindi',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN'=>substr_count($cur,'CH_MAILBTN')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nApp'i TAM kapat-ac. Sohbet ve ausdrucken/PDF eski calisir haline donmeli.\n";
