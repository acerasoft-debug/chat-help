<?php
/**
 * ChatHelp — dump-uxfix — 5 UX duzeltmesi icin canli kod (OKUR):
 *  [1] makePDF'i gonderim modaline yonlendiren wrapper (PDF butonu modal ACMASIN, direkt PDF)
 *  [2] doFree/fillGuard (Kostenlos selbst ausdrucken imza istiyor mu)
 *  [3] gonderim butonu fiyat etiketi (1,50 € kaldirilacak) + _priceFor
 *  [4] Lücken uyarisi + bosluk doldurma + IBAN'a adres yazan enjeksiyon (IBAN bug)
 * KULLANIM: pull2.php?key=...&files=dump-uxfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-uxfix (".strlen($src)." B) ==\n\n";

echo "=== [1] makePDF wrapper / modal-acan kod ===\n";
foreach(['makePDF=function','var _mp=makePDF','_origMakePDF','makePDF.__','function makePDF'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-120),420)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}

echo "=== [2] doFree + fillGuard ===\n";
foreach(['function doFree','function fillGuard'] as $pat){
  $p=strpos($src,$pat);
  if($p!==false){ echo "  [$pat @$p]\n".substr($src,$p,700)."\n\n"; }
  else echo "  $pat bulunamadi\n";
}

echo "=== [3] gonderim butonlari + fiyat ===\n";
foreach(['function _priceFor','an Empfänger senden','Original nach Hause','id="ai-send"',"id='ai-send'"] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-150),400)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}

echo "=== [4] Lücken / bosluk doldurma / IBAN enjeksiyonu ===\n";
foreach(['Offene Lücken','Lücken im Dokument','IBAN'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<4){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-200),450)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}
echo "  '____' (4 alt cizgi) sayisi: ".substr_count($src,'____')."\n";
$off=0;$n=0;
while(($p=strpos($src,'________',$off))!==false && $n<5){
  echo "  [____x2 #$n @$p] ...".substr($src,max(0,$p-180),380)."...\n\n"; $off=$p+8;$n++;
}

echo "\n== BITTI ==\n";
