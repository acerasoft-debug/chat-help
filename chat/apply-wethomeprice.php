<?php
/**
 * ChatHelp — apply-wethomeprice (CH_WETPRICE) — 'Original an meine Adresse' butonuna fiyat ekler.
 *   Normal durumda buton fiyatli (plan bazli: 1,50/1,99/2,99 €); islak-imza (wet) durumunda fiyat
 *   atlanmisti -> ucretsiz gibi gorunuyordu. Artik iki durumda da fiyat gorunur.
 *   Ek olarak (SADECE RAPOR): #ai-home tiklama handler'i baglamini basar — tahsilat var mi gorulur.
 *   Idempotent + tek-eslesme + php -l lint + .bak + dogrulama.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-wethomeprice.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-wethomeprice BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_WETPRICE')!==false) exit("Zaten uygulandi (CH_WETPRICE).\n");

$o="(wet?T('wethome'):(T('home')+' — '+priceStr()))";
$n="((wet?T('wethome'):T('home'))+' — '+priceStr())/*CH_WETPRICE*/";
$c=substr_count($src,$o);
if($c===1){
  $src=str_replace($o,$n,$src);
  echo "  ✓ [FIYAT] wet durumunda da fiyat etiketi eklendi (plan bazli 1,50/1,99/2,99 €)\n";
} else {
  echo "  ✗ anchor $c kez (1 beklenir) — DEGISTIRILMEDI.\n";
  exit;
}

/* lint + yaz */
$tmp=tempnam(sys_get_temp_dir(),'wp').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-wetprice-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_WETPRICE')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_WETPRICE diskte (".strlen($chk)." B)\n";

/* ── RAPOR: #ai-home tiklama handler'i (tahsilat var mi?) ── */
echo "\n=== RAPOR: ai-home handler baglamlari (tahsilat kontrolu icin) ===\n";
$off=0;$nn=0;
while(($q=strpos($chk,"ai-home",$off))!==false && $nn<6){
  $ctx=substr($chk,($q-60>0?$q-60:0),520);
  if(strpos($ctx,'onclick')!==false || strpos($ctx,'addEventListener')!==false || strpos($ctx,'getElementById')!==false){
    echo "  [@".$q."]: ...".str_replace(array("\r","\n"),' ',$ctx)."...\n\n";
  }
  $off=$q+7;$nn++;
}
echo "Butonda artik fiyat gorunuyor. Handler ciktisina gore gercek tahsilat kapisi ayrica eklenebilir.\n";
