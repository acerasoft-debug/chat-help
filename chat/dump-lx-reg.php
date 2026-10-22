<?php
/* dump-lx-reg — LetterXpress gonderim varyant testi (TEST modu — gercek gonderim
   yok). Ayni mektubu 3 varyantla setJob'a yollar: normal, Einwurf-Einschreiben,
   Einschreiben (Übergabe). Fiyat/durum karsilastirir. postversand.php'nin GUNCEL
   (registered string destekli) olmasi gerekir.
   KULLANIM: pull2.php?key=...&files=apply-postversand.php,dump-lx-reg.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-lx-reg BASLADI (PHP ".PHP_VERSION.")\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
function post($url,$body){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>60]);
  $r=curl_exec($ch); curl_close($ch); return $r;
}
$letter=[
  'text'=>"Sehr geehrte Damen und Herren,\n\nhiermit widerspreche ich dem Bescheid vom 01.07.2026 fristgerecht.\n\nMit freundlichen Gruessen\nTest Nutzer",
  'recipient'=>"Musterbehoerde\nHauptstrasse 1\n10115 Berlin",
  'sender'=>"Test Nutzer - Alte Str. 5 - 10115 Berlin",
];

$pv=@file_get_contents(__DIR__.'/postversand.php');
echo "postversand.php 'registered' pass-through : ".($pv&&strpos($pv,"\$spec['registered']=\$rv")!==false?'VAR':'YOK — once apply-postversand.php calistir!')."\n";
echo "postversand.php send_fax / Phaxio         : ".($pv&&strpos($pv,'send_fax')!==false?'VAR':'YOK')."\n\n";

$variants=[['normal',''],['Einwurf-Einschreiben','einwurf'],['Einschreiben Übergabe','einschreiben']];
$prices=[];
foreach($variants as $v){
  $body=$letter; if($v[1]!=='') $body['registered']=$v[1];
  $r=post($base.'?action=send_letter',$body);
  $j=json_decode((string)$r,true);
  $pr=$j['result']['letter']['price']??($j['result']['price']??'?');
  $prices[$v[0]]=$pr;
  echo "== ".$v[0]." (registered=".($v[1]?:'-').") ==\n";
  echo "   ".substr((string)$r,0,320)."\n\n";
}
echo "== OZET fiyatlar ==\n";
foreach($prices as $k=>$pr) echo "   ".str_pad($k,26).": $pr\n";
echo "\nBeklenen: Einschreiben varyantlari normalden pahali. Fiyat farki yoksa\n";
echo "LetterXpress v1 registered anahtarini/degerini yok sayiyor -> ciktiyi yapistir,\n";
echo "dogru parametreye (or. 'einschreiben_einwurf') geceriz.\n";
