<?php
/**
 * ChatHelp — dump-genflow (SADECE OKUR) — dilekce uretimi hangi AI akisini
 *  kullaniyor? api.php + intake-api.php + fall-api.php icinde DeepSeek ve
 *  Claude cagrilarini, intake_solve/generate/fall_solve akislarini gosterir.
 *  Anahtarlar maskelenir. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-genflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
function mask($s){ $s=preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',$s); return preg_replace('/(Bearer\s+)[A-Za-z0-9._\-]+/','$1***',$s); }
echo "=== dump-genflow — SADECE OKUR ===\n\n";
foreach(['api.php','intake-api.php','fall-api.php'] as $f){
  $s=(string)@file_get_contents("$D/$f");
  echo "════════ $f (".strlen($s)." B) ════════\n";
  echo "  deepseek gecen: ".substr_count(strtolower($s),'deepseek')." | anthropic/claude: ".(substr_count(strtolower($s),'anthropic')+substr_count(strtolower($s),'claude'))."\n";
  echo "  api.deepseek.com: ".substr_count($s,'api.deepseek.com')." | api.anthropic.com: ".substr_count($s,'api.anthropic.com')."\n\n";
  foreach(['intake_solve','fall_solve','function doGenerate','function doIntake','DeepSeek','deepseek','function callDeep','function callClaude','Entwurf','denetle','pruef','audit','ueberprue'] as $nd){
    $p=stripos($s,$nd);
    if($p!==false){
      echo "  ['$nd' @".$p."]:\n    ".preg_replace('/\s+/',' ',mask(substr($s,max(0,$p-40),300)))."\n";
    }
  }
  echo "\n";
}
/* generate akisinin govdesi: iki asama var mi? */
$api=(string)@file_get_contents("$D/api.php");
$p=stripos($api,'function doGenerate');
if($p===false) $p=stripos($api,'intake_solve');
if($p!==false){ echo "──── uretim akisi govde (2200 kr) ────\n".mask(substr($api,$p,2200))."\n"; }
echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
