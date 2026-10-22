<?php
/**
 * ChatHelp — dump-welcome2 — ilk-acilis 'zuerst Ihre Daten' karsilama + Willkommen gate.
 * KULLANIM: pull2.php?key=...&files=dump-welcome2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-welcome2 (".number_format(strlen($src))." B) ==\n\n";

echo "=== [A] karsilama metni ('zuerst Ihre Daten' / 'genau diesen Namen') ===\n";
foreach(['zuerst Ihre Daten','geben Sie zuerst','genau diesen Namen','Willkommen! Bitte'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-180),420))."...\n\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  [$pat]: yok\n";
}

echo "=== [B] Willkommen gate / killWelcome / seed ===\n";
foreach(['Willkommen gate','killWelcome','seedWelcome','_chWelcomed','ch_welcomed','function greet','needData','requireProfile','profileGate'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-70),300))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  [$pat]: yok\n";
}

echo "\n=== [C] baslangic mesaji ekleyen kod (mi appendChild / addMsg ch) ===\n";
$off=0;$n=0;
foreach(["addMsg('ch'","addMsg(\"ch\"","greet(","intro("] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-30),200))."...\n";
    $off=$p+strlen($pat); $n++;
  }
}
echo "\n== BITTI ==\n";
