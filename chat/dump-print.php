<?php
/* ChatHelp - dump-print (READ-ONLY) - 'ausdrucken' (yazdir) + fax + tum gonderim yollarinin handler'lari
 *  Amac: App WebView'de neden calismadigini gormek. window.print / window.open / blob / Capacitor tespiti.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-print.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-print (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] 'ausdrucken' / 'selbst ausdrucken' butonu + handler ===\n";
foreach(array('selbst ausdrucken','ausdrucken','printDoc','doPrint','window.print','print(') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-160>0?$q-160:0),520))."...\n\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [2] window.open / blob / data: URL kullanimi (WebView'de sorunlu) ===\n";
foreach(array('window.open','URL.createObjectURL','new Blob','data:application/pdf','data:text/html','iframe') as $pat){
  $c=substr_count($src,$pat);
  echo "  '".$pat."': $c kez";
  if($c){ $q=strpos($src,$pat); echo "  ilk: ...".str_replace(array("\r","\n"),' ',substr($src,($q-80>0?$q-80:0),240))."..."; }
  echo "\n";
}

echo "\n=== [3] Capacitor / App ortami tespiti ===\n";
foreach(array('Capacitor','isNativePlatform','window.Capacitor','isApp','ch_app','__CHAPP','platform','cordova') as $pat){
  $c=substr_count($src,$pat);
  if($c){ $q=strpos($src,$pat); echo "  '".$pat."': $c kez  ilk: ...".str_replace(array("\r","\n"),' ',substr($src,($q-60>0?$q-60:0),200))."...\n"; }
  else echo "  '".$pat."': yok\n";
}

echo "\n=== [4] Fax gonderimi (Senden / Gratis-Fax) handler ===\n";
foreach(array('Gratis-Fax','send_fax','action=fax','faxSend','doFax','Senden') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-100>0?$q-100:0),360))."...\n\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [5] PDF butonu (Als PDF speichern) handler ===\n";
foreach(array('Als PDF speichern','savePdf','downloadPdf','send-doc.php','pdf.php','.pdf') as $pat){
  $q=strpos($src,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-90>0?$q-90:0),320))."...\n"; }
  else echo "  (".$pat.": yok)\n";
}
echo "\n== BITTI ==\n";
