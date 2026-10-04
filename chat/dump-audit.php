<?php
/**
 * ChatHelp — dump-audit — komple site HATA taramasi (OKUR, degistirmez):
 *  [A] chat/ altindaki cekirdek *.php -> php -l
 *  [B] index.php: cift tanimli JS fonksiyonlari
 *  [C] debug/console kalintilari + TODO/FIXME
 *  [D] cift DOM id
 *  [E] perf: interval/observer sayilari
 *  [F] enjekte blok sayisi (yama sismesi)
 *  [G] mojibake/bozuk karakter
 * KULLANIM: pull2.php?key=...&files=dump-audit.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
set_time_limit(120);
echo "== dump-audit (PHP ".PHP_VERSION.") ==\n\n";
$dir=__DIR__;

echo "=== [A] cekirdek PHP lint ===\n";
$core=['index.php','api.php','fall-api.php','postversand.php','config.php','dl.php','admin.php','auth.php'];
foreach($core as $b){
  $f="$dir/$b"; if(!is_file($f)){ echo "  ○ $b yok\n"; continue; }
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$lo,$rc);
  echo ($rc===0?"  ✓ ":"  ✗ ").str_pad($b,20)." ".number_format(filesize($f))." B".($rc?"\n     ".implode("\n     ",$lo):"")."\n";
}

$src=@file_get_contents("$dir/index.php"); if($src===false){ echo "\nindex.php YOK\n"; exit; }
echo "\nindex.php: ".number_format(strlen($src))." B\n";

echo "\n=== [B] cift tanimli 'function chX(' ===\n";
if(preg_match_all('/function\s+(ch[A-Za-z0-9_]+)\s*\(/',$src,$m)){
  $cnt=array_count_values($m[1]); $dup=0;
  foreach($cnt as $fn=>$c){ if($c>1){ echo "  ⚠ $fn -> $c kez\n"; $dup++; } }
  echo $dup?"  >>> $dup fonksiyon birden fazla (son kazanir; patch olabilir ama kafa karisikligi)\n":"  temiz\n";
}

echo "\n=== [C] debug kalintilari ===\n";
foreach(['console.log'=>'/console\.log/','alert('=>'/\balert\(/','DBG marker'=>'/CH_[A-Z]*DBG/','debugger'=>'/\bdebugger\b/','TODO'=>'/TODO/','FIXME'=>'/FIXME/','XXX'=>'/\bXXX\b/'] as $lbl=>$re){
  echo "  ".str_pad($lbl,14).": ".preg_match_all($re,$src)."\n";
}

echo "\n=== [D] cift id (ilk 25) ===\n";
if(preg_match_all('/\bid=["\']([a-zA-Z][\w\-]{2,})["\']/',$src,$mm)){
  $c=array_count_values($mm[1]); $d=0;
  foreach($c as $id=>$n){ if($n>1){ echo "  ⚠ #".str_pad($id,22)." x$n\n"; if(++$d>=25) break; } }
  echo $d?"":"  temiz\n";
}

echo "\n=== [E] perf ===\n";
foreach(['setInterval','setTimeout','MutationObserver','addEventListener','querySelectorAll'] as $k)
  echo "  ".str_pad($k,18).": ".substr_count($src,$k)."\n";

echo "\n=== [F] enjekte bloklar ===\n";
echo "  <script: ".substr_count($src,'<script')."  <style: ".substr_count($src,'<style')."\n";
preg_match_all('/CH_[A-Z0-9_]+/',$src,$cm);
echo "  CH_ marker toplam: ".count($cm[0])."  essiz: ".count(array_unique($cm[0]))."\n";

echo "\n=== [G] mojibake ===\n";
$mo=0;
foreach(['Ã©/Ã¤'=>'/Ã[©¤¶¼]/','â€™/â€œ'=>'/â€[™œ]/','replacement U+FFFD'=>'/\xEF\xBF\xBD/'] as $lbl=>$re){
  $n=preg_match_all($re,$src); if($n>0){ echo "  ⚠ ".str_pad($lbl,18).": $n\n"; $mo+=$n; }
}
echo $mo?"":"  temiz\n";

echo "\n== BITTI ==\n";
