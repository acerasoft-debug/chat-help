<?php
/**
 * ChatHelp — dump-mic-integrity (SADECE OKUR) — ACIL: "mikrofon yine calismiyor,
 *  1 saat once cok iyiydi". Son 1 saatte COK sayida baska EKLEMELI deploy oldu
 *  (vize/konto). Mikrofon bloklarinin BOZULMADIGINI/hala DOGRU SIRADA oldugunu
 *  dogrudan kontrol eder:
 *  [1] Tum mic markerlarinin VARLIGI + KACAR KEZ (cift olmamali)
 *  [2] window.startVoice'in KAC KEZ tanimlandigi (en SON tanimlanan kazanir —
 *      eger beklenmedik bir script SONRADAN startVoice'i BASKA bir seye
 *      esitlediyse mikrofon calismaz)
 *  [3] Mic dugmesinin onclick/id baglamlari (dugme hala startVoice'i cagiriyor mu)
 *  [4] Mic bloklarinin TAM govdesinin bozulmadigini (parantez dengesi) kontrol
 *  [5] En SON eklenen 10 script id'si (deploy sirasi + mikrofon bloklarina
 *      gore konumu) — hangi script'in mikrofonu "gomdugunu" gormek icin
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-mic-integrity.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-mic-integrity — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

echo "[1] Mic marker sayimlari:\n";
foreach(['CH_MICPRO','CH_MICPRO2','CH_MICSMART','CH_MICSMART2','CH_MICKB2','ch-mic-smart2','ch-mic2','window.startVoice','window.chOpenMic','chKeyboardDictate'] as $m){
  echo "  ".str_pad($m,20)." : ".substr_count($src,$m)." kez\n";
}

echo "\n[2] 'startVoice=' TANIMLARI (hepsi, sirayla — SON kazanir):\n";
$off=0;$n=0;
while(($p=strpos($src,'startVoice=',$off))!==false && $n<15){
  $seg=substr($src,max(0,$p-50),140); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  [@$p] …".$seg."…\n"; $off=$p+11;$n++;
}
if($n===0) echo "  (HIC YOK — CIDDI SORUN)\n";

echo "\n[3] Mic dugmesi onclick/id baglamlari ('startVoice(' cagrisi):\n";
$off=0;$n=0;
while(($p=strpos($src,'startVoice(',$off))!==false && $n<10){
  $seg=substr($src,max(0,$p-100),160); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+11;$n++;
}
if($n===0) echo "  (startVoice() HICBIR YERDEN CAGRILMIYOR — dugme baglantisi kopmus olabilir)\n";

echo "\n[4] Mic script bloklarinin parantez dengesi (string-farkinda degil, kaba gösterge):\n";
foreach(['ch-mic-pro-js','ch-mic-pro2-js','ch-mic-smart-js','ch-mic-smart2-js','ch-mickb2-js'] as $id){
  $p=strpos($src,'id="'.$id.'"');
  if($p===false){ echo "  $id: BULUNAMADI\n"; continue; }
  $s=strpos($src,'>',$p)+1; $e=strpos($src,'</script>',$s);
  if($e===false){ echo "  $id: </script> bulunamadi (KRITIK — blok bozuk olabilir)\n"; continue; }
  $code=substr($src,$s,$e-$s);
  $b=0;$pp=0;$k=0;
  for($i=0;$i<strlen($code);$i++){ $c=$code[$i]; if($c==='{')$b++;elseif($c==='}')$b--;elseif($c==='(')$pp++;elseif($c===')')$pp--;elseif($c==='[')$k++;elseif($c===']')$k--; }
  echo "  $id: len=".strlen($code)." denge[{}=$b ()=$pp []=$k]".(($b||$pp||$k)?" ⚠ IMBALANSLI":" ✓")."\n";
}

echo "\n[5] SON 12 <script id=\"...\"> (deploy sirasi, sondan basa):\n";
preg_match_all('/<script\s+id="([a-z0-9\-_]+)"/i',$src,$mm);
$ids=$mm[1];
$last12=array_slice($ids,-12);
foreach(array_reverse($last12) as $i=>$id) echo "  ".(count($last12)-$i).". $id\n";
echo "  (toplam inline id'li script: ".count($ids).")\n";

/* [6] mic butonu id'sinin varligi + onclick tam metni */
echo "\n[6] mic buton onclick tam metni (id gecenlerden):\n";
$off=0;$n=0;
while(($p=stripos($src,'onclick="startVoice',$off))!==false && $n<5){
  $seg=substr($src,max(0,$p-80),160); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+18;$n++;
}
if($n===0){
  $off=0;$n2=0;
  while(($p=stripos($src,"onclick='startVoice",$off))!==false && $n2<5){
    $seg=substr($src,max(0,$p-80),160); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+18;$n2++;
  }
  if($n2===0) echo "  (onclick=\"startVoice ATTRIBUTE OLARAK YOK — addEventListener ile baglanmis olabilir)\n";
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
