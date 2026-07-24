<?php
/**
 * ChatHelp — dump-micwv — CH_MIC_WVDIRECT blok tamami + alttaki composer mikrofon
 *  butonunun (id + onclick) kablolamasi (OKUR). App'te mikrofonu tek override ile
 *  klavye-dikteye baglamak icin gereken exact anchorlar.
 * KULLANIM: pull2.php?key=...&files=dump-micwv.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-micwv ==\n\n";

/* [1] CH_MIC_WVDIRECT blok tamami */
$p=strpos($src,'CH_MIC_WVDIRECT');
if($p!==false){
  $start=strrpos(substr($src,0,$p),'<script');
  $end=strpos($src,'</script>',$p); if($end!==false)$end+=9;
  echo "=== CH_MIC_WVDIRECT blok (@$start..$end) ===\n";
  echo substr($src,$start,($end-$start))."\n\n";
} else echo "CH_MIC_WVDIRECT yok\n\n";

/* [2] composer mikrofon butonu: id='pb' veya mic svg */
echo "=== composer mic butonu (id=pb / 🎤 / mic) ===\n";
foreach(["id=\"pb\"","id='pb'","onclick=\"startVoice","startVoice()","'pb'","\"mic\"","id=\"mic\""] as $pat){
  $off=0;$n=0;
  while(($q=strpos($src,$pat,$off))!==false && $n<2){
    echo "  ['$pat' @$q]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$q-140),320))."...\n";
    $off=$q+strlen($pat); $n++;
  }
}

/* [3] CH_MIC_APPKB + CH_MIC_UNI kisa baglam */
echo "\n=== CH_MIC_APPKB / CH_MIC_UNI baglam ===\n";
foreach(['CH_MIC_APPKB','CH_MIC_UNI','CH_MIC_TRY'] as $m){
  $q=strpos($src,$m);
  if($q!==false) echo "  [$m @$q]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$q-60),400))."...\n\n";
}
echo "== BITTI ==\n";
