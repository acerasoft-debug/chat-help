<?php
/** ChatHelp — dump-uistate: logo/ikon yapisini gosterir (sb-brand markup + marker durumu). SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx === false) exit("index.php okunamadi\n");
echo "dump-uistate (idx=".strlen($idx)." bayt)\n\n";

echo "──── markerlar ────\n";
foreach (['CH_NAVY_THEME','ch-navy-theme','ch-navy-js','CH_STAAT_GATE2','CH_LANG_PERSIST','CH_JOBS_MAIL'] as $m)
  echo "  $m: ".(strpos($idx,$m)!==false?'VAR':'YOK')."\n";

function OCC($s,$needle,$lbl,$ctx=400,$max=4){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=stripos($s,$needle,$o))!==false && $n<$max){
    echo "@$p:\n".substr($s,max(0,$p-60),$ctx)."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

OCC($idx,'sb-brand','sb-brand markup (statik HTML)',700,3);
OCC($idx,'class="logo','baska logo elementleri',300,4);
OCC($idx,'sb-nav','sidebar nav yapisi',500,2);
OCC($idx,'nav-item','nav ogeleri (ilk 2)',400,2);
echo "\n=== BITTI ===\n";
