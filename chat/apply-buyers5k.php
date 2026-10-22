<?php
/**
 * ChatHelp — apply-buyers5k (CH_BUYERS5K) — hero istatistigi '500+ Verifizierte Käufer'
 *  -> '5.000+'. SADECE yakininda Käufer/buyers/acheteurs/alici benzeri kelime olan
 *  '500+' degistirilir; diger 500'lere dokunulmaz. Tum dil kopyalari kapsanir.
 * KULLANIM: pull2.php?key=...&files=apply-buyers5k.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-buyers5k BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* yakin-kelime kontrolu: Käufer + ceviri karsiliklari */
$NEAR='/K(ä|Ã¤|ae?)ufer|buyer|acheteur|al(ı|i)c(ı|i)|comprador|acquirent|kunden|Nutzer/iu';

$hits=[]; $off=0;
while(($p=strpos($src,'500+',$off))!==false){ $hits[]=$p; $off=$p+4; }
if(!$hits) exit("  ✗ '500+' hic bulunamadi — zaten degistirilmis olabilir.\n");
echo "  '500+' toplam ".count($hits)." yerde bulundu; baglam kontrolu:\n\n";

$done=0;
for($i=count($hits)-1;$i>=0;$i--){       /* sondan basa (offset kaymasin) */
  $p=$hits[$i];
  $win=substr($src,max(0,$p-220),470);
  $ctx=str_replace(["\n","\r"],' ',substr($src,max(0,$p-70),160));
  if(preg_match($NEAR,$win)){
    $src=substr($src,0,$p).'5.000+'.substr($src,$p+4);
    echo "  ✓ DEGISTI @$p: ...".$ctx."...\n\n";
    $done++;
  } else {
    echo "  ○ atlandi (Käufer yakininda degil) @$p: ...".$ctx."...\n\n";
  }
}
if($done===0) exit("  ✗ Baglami uyan '500+' yok — HICBIR sey degistirilmedi.\n");
$src.="\n<!--CH_BUYERS5K-->";

$tmp=tempnam(sys_get_temp_dir(),'b5').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-buyers5k-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'5.000+')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ $done yerde '500+' -> '5.000+' (".strlen($chk)." B)\n";
echo "  Hero: '5.000+ Verifizierte Käufer' gorunur.\n";
