<?php
/* dump-konto (READ-ONLY) — Konto sayfasi tema selector(ler)i + 'Abo verwalten'
   + tema kalicilik/geri-donus mantigini cikarir. Silme/duzeltme icin haritalama.
   KULLANIM: pull2.php?key=...&files=dump-konto.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-konto (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

function ctx($s,$needle,$before=90,$after=180,$max=8){
  $out=[]; $off=0;
  while(($p=stripos($s,$needle,$off))!==false && count($out)<$max){
    $a=max(0,$p-$before); $seg=substr($s,$a,$before+strlen($needle)+$after);
    $seg=preg_replace('/\s+/',' ',$seg);
    $out[]="  @".$p.": …".trim($seg)."…";
    $off=$p+strlen($needle);
  }
  return $out;
}

echo "=== 'Anthrazit' gecisleri (".substr_count($s,'Anthrazit').") ===\n";
echo implode("\n",ctx($s,'Anthrazit',80,120,10))."\n";
echo "\n=== 'Weiß' / 'Weiss' gecisleri ===\n";
echo implode("\n",array_merge(ctx($s,'Weiß',80,90,6),ctx($s,'>Weiss',60,80,4)))."\n";
echo "\n=== 'Marine' gecisleri (".substr_count($s,'Marine').") ===\n";
echo implode("\n",ctx($s,'Marine',80,90,10))."\n";
echo "\n=== 'verwalten' gecisleri (".substr_count($s,'verwalten').") ===\n";
echo implode("\n",ctx($s,'verwalten',110,60,10))."\n";
echo "\n=== 'Systemeinstellung' (auto-tema notu) ===\n";
echo implode("\n",ctx($s,'Systemeinstellung',120,120,4))."\n";

echo "\n=== tema JS anahtarlari ===\n";
foreach(['ch_theme','data-theme','setTheme','applyTheme','matchMedia','prefers-color-scheme','theme_auto','ch-theme','localStorage.setItem(\'ch_theme'] as $k){
  echo "  '$k': ".substr_count($s,$k)." kez\n";
}

echo "\n=== data-theme / classList.add('anthr / navy / marine yazan yerler ===\n";
foreach(['anthrazit','anthracite','marine','navy','light-theme','theme-anthr'] as $k){
  $n=preg_match_all('/(setAttribute\([\'"]data-theme[\'"]|classList\.(?:add|remove|toggle)|documentElement\.\w+|body\.\w+)[^;]{0,60}'.preg_quote($k,'/').'/i',$s,$mm);
  if($n) echo "  ...$k: $n eslesme\n";
}

echo "\n=== 'Design' basligi olan bloklarin CH_ marker baglamı ===\n";
echo implode("\n",ctx($s,'>Design<',140,40,8))."\n";
echo implode("\n",ctx($s,'DESIGN<',140,40,6))."\n";

echo "\nBITTI.\n";
