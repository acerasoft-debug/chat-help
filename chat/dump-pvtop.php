<?php
/**
 * ChatHelp — dump-pvtop — postversand.php giris/dispatch mantigini gosterir (OKUR, maskeli):
 *  include edilince neden JSON basip cikiyor -> guard/kosul ne? Sinch fonksiyonlarini
 *  handler'i tetiklemeden cagirabilmek icin.
 * KULLANIM: pull2.php?key=...&files=dump-pvtop.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/postversand.php');
if($src===false){ echo "postversand.php YOK\n"; exit; }
function mm($s){ $s=preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',(string)$s);
  $s=preg_replace('/([A-Za-z0-9_\-]{3})[A-Za-z0-9_\-]{20,}([A-Za-z0-9_\-]{3})/','$1***$2',$s); return $s; }
echo "== dump-pvtop (".strlen($src)." B) ==\n\n";

echo "=== ILK 90 satir ===\n";
$lines=explode("\n",$src);
for($i=0;$i<min(90,count($lines));$i++){ echo ($i+1).": ".mm($lines[$i])."\n"; }

echo "\n=== dispatch anahtarlari (action/REQUEST_METHOD/exit/die/echo json) ===\n";
foreach(['$_GET','$_POST','REQUEST_METHOD','action','function fax_send','function fax_configured','function sinch_token','function fax_provider','json_encode','exit;','die('] as $pat){
  $p=strpos($src,$pat);
  echo "  [".str_pad($pat,22)."] ".($p===false?'YOK':("@$p .. ".mm(str_replace("\n"," ",substr($src,$p,90))))) ."\n";
}
echo "\n== BITTI ==\n";
