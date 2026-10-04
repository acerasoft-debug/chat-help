<?php
/* ChatHelp — apply-hide-billing (CH_HIDE_BILLING) — Konto'daki fazla
   'Zahlungsmethode / Abo verwalten' butonunu (#ch-billing-btn) gizler.
   KULLANIM: pull2.php?key=...&files=apply-hide-billing.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hide-billing BASLADI OK\n\n";
$file=__DIR__.'/index.php'; $src=@file_get_contents($file);
if($src===false) exit("okunamadi\n");
if(strpos($src,'CH_HIDE_BILLING')!==false) exit("Zaten ekli.\n");
$block='<style id="ch-hide-billing">/* CH_HIDE_BILLING */#ch-billing-btn{display:none!important}</style>';
$pos=strripos($src,'</body>'); if($pos===false) exit("</body> yok\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'hb').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI\n"; exit; }
@file_put_contents($file.'.bak-hidebill-'.date('Ymd-His'),(string)@file_get_contents($file));
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
echo (strpos((string)@file_get_contents($file),'CH_HIDE_BILLING')!==false)
  ? "✓ 'Zahlungsmethode / Abo verwalten' butonu gizlendi.\n" : "✗ dogrulama basarisiz\n";
