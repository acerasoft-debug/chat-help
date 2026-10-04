<?php
/** ChatHelp — dump-uistate2: sidebar marka + nav GERCEK HTML markup'i. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx === false) exit("index.php okunamadi\n");
echo "dump-uistate2 (idx=".strlen($idx)." bayt)\n";

function WIN($s,$needle,$lbl,$len=1100,$max=6){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=strpos($s,$needle,$o))!==false && $n<$max){
    echo "@$p:\n".substr($s,$p,$len)."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

/* HTML markup — CSS degil, gercek div'ler */
WIN($idx,'<div class="sb-brand','MARKA BLOGU (statik HTML)',900,3);
WIN($idx,'class="sb-nav-item','NAV OGELERI (ilk 4 tam)',600,4);
WIN($idx,'class="sb-mark','sb-mark markup',500,3);
WIN($idx,'id="sb"','#sb acilisi',700,2);
echo "\n=== BITTI ===\n";
