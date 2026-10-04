<?php
/**
 * ChatHelp — dump-doc-backend (SADECE OKUR) — analyze_photo PDF destegi var mi?
 *  api.php doPhoto govdesi + intake-api.php vision cagri yapisi (mime nasil
 *  kullaniliyor). Gizli anahtarlar maskelenir. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-doc-backend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{6})[A-Za-z0-9_\-]+/','$1***',$s); }
echo "=== dump-doc-backend — SADECE OKUR ===\n\n";
$api=(string)@file_get_contents("$D/api.php");
echo "api.php: ".strlen($api)." B\n";
$p=strpos($api,'function doPhoto');
if($p!==false) echo "\n--- doPhoto (2600 kr) ---\n".mask(substr($api,$p,2600))."\n";
else echo "(doPhoto yok)\n";
$in=(string)@file_get_contents("$D/intake-api.php");
echo "\nintake-api.php: ".strlen($in)." B\n";
foreach(['ch_vision','application/pdf','media_type','image/'] as $nd){
  $off=0;$n=0;
  echo "\n['$nd' gecisleri]:\n";
  while(($q=strpos($in,$nd,$off))!==false && $n<3){
    echo "  @".$q." …".preg_replace('/\s+/',' ',mask(substr($in,max(0,$q-160),420)))."…\n";
    $off=$q+strlen($nd);$n++;
  }
  if(!$n) echo "  (yok)\n";
}
echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
