<?php
/* dump-lx-reg — LetterXpress Einwurf-Einschreiben E2E testi (TEST modu — gercek
   gonderim yok). Ayni mektubu once normal, sonra registered olarak setJob'a
   yollar; fiyat/durum karsilastirir. postversand.php'nin GUNCEL (registered
   destekli) olmasi gerekir.
   KULLANIM: pull2.php?key=...&files=apply-postversand.php,dump-lx-reg.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-lx-reg BASLADI (PHP ".PHP_VERSION.")\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
function post($url,$body){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>60]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$c,$r];
}
$letter=[
  'text'=>"Sehr geehrte Damen und Herren,\n\nhiermit widerspreche ich dem Bescheid vom 01.07.2026 fristgerecht.\n\nMit freundlichen Gruessen\nTest Nutzer",
  'recipient'=>"Musterbehoerde\nHauptstrasse 1\n10115 Berlin",
  'sender'=>"Test Nutzer - Alte Str. 5 - 10115 Berlin",
];
echo "== registered destegi kontrol ==\n";
$pv=@file_get_contents(__DIR__.'/postversand.php');
echo "  postversand.php 'registered' : ".($pv&&strpos($pv,"registered")!==false?'VAR':'YOK — once apply-postversand.php calistir!')."\n\n";

echo "== [A] NORMAL posta (test) ==\n";
list($c1,$r1)=post($base.'?action=send_letter',$letter);
echo "  HTTP $c1: ".substr((string)$r1,0,400)."\n\n";

echo "== [B] EINSCHREIBEN einwurf (test) ==\n";
$letter['registered']=1;
list($c2,$r2)=post($base.'?action=send_letter',$letter);
echo "  HTTP $c2: ".substr((string)$r2,0,400)."\n\n";

$j1=json_decode((string)$r1,true); $j2=json_decode((string)$r2,true);
$p1=$j1['result']['letter']['price']??($j1['result']['price']??'?');
$p2=$j2['result']['letter']['price']??($j2['result']['price']??'?');
echo "== OZET ==\n  normal fiyat     : $p1\n  einschreiben     : $p2\n";
echo ($p2!=='?'&&$p1!=='?'&&$p2>$p1) ? "  ✓ registered kabul edildi (fiyat farki var)\n"
   : "  ⚠ fiyat farki yok/okunamadi — LetterXpress v1 'registered' anahtarini yok sayiyor olabilir; ciktiyi yapistir, alternatif parametreye geceriz.\n";
