<?php
/* dump-plan-src — canli index.php'de plan tespit kaynaklarini gosterir (salt-okunur).
   KULLANIM: pull2.php?key=...&files=dump-plan-src.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "index.php ".strlen($src)." B\n\n";
foreach(['CH_ABO_REFRESH','CH_ADRES_IMZA3','CH_ADRES_IMZA4','window.chPlan','chPlan=','ch_plan','ch_user'] as $m){
  echo str_pad($m,20)." : ".substr_count($src,$m)." kez\n";
}
echo "\n--- window.chPlan tanimi (varsa) ---\n";
if(preg_match_all('/window\.chPlan\s*=[^;\n]{0,200}/',$src,$mm)){
  foreach($mm[0] as $x) echo "  ".trim($x)."\n";
} else echo "  (yok — window.chPlan tanimlanmamis)\n";
echo "\n--- 'function chPlan' / 'chPlan(' tanimlari ---\n";
if(preg_match_all('/function\s+chPlan[^)]*\)|chPlan\s*[:=]\s*function[^)]*\)/',$src,$mm2)){
  foreach($mm2[0] as $x) echo "  ".trim($x)."\n";
} else echo "  (yok)\n";
echo "\n--- IMZA3 plan() satiri ---\n";
if(preg_match('/function plan\(\)\{[^\n]*\}/',$src,$m3)) echo "  ".substr($m3[0],0,400)."\n"; else echo "  (bulunamadi)\n";
