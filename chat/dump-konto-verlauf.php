<?php
/* dump-konto-verlauf (READ-ONLY) — (A) #pnl-konto bolum basliklari/tekrarlar
   (ikileme tespiti); (B) Verlauf (gecmis) sidebar + konusma depolamasi/yukleme.
   KULLANIM: pull2.php?key=...&files=dump-konto-verlauf.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-konto-verlauf (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function ctx($s,$k,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,strpos($s,$k)-$b),$b+$a))); }

/* A) Konto bolum basliklari + olasi tekrarlar */
echo "=== [A] Konto tekrar suphelileri (kac kez) ===\n";
foreach(['Konto & Einstellungen','Profil, Land, Sprache','Pläne & Preise','Land & Rechtssystem','Abo verwalten','Zahlungsmethode','Abmelden','Strategie-Analyse','Fallanalysen','ksec-hdr-t','>Design<','konto-plan-badge','konto-user-info','DESIGN'] as $k){
  echo "  '".$k."': ".substr_count($s,$k)."\n";
}
echo "\n=== [A2] ksec-hdr-t basliklari (Konto bolum baslik metinleri, sirasiyla) ===\n";
if(preg_match_all('/ksec-hdr-t[^>]*>([^<]{1,40})</',$s,$m)){ foreach($m[1] as $i=>$t) echo "  ".($i+1).". ".trim($t)."\n"; }
echo "\n=== [A3] #pnl-konto HTML iskeleti (statik) ilk 900 ===\n";
$p=strpos($s,'id="pnl-konto"');
if($p!==false){ echo trim(preg_replace('/\s+/',' ',substr($s,$p,900)))."\n"; }
echo "\n=== [A4] 'Konto & Einstellungen' baglami (eski blok) ===\n";
$off=0;$c=0; while(($p=strpos($s,'Konto & Einstellungen',$off))!==false && $c<4){ echo "  [".$p."] …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-60),200)))."…\n"; $off=$p+5;$c++; }

/* B) Verlauf + konusma depolamasi */
echo "\n\n=== [B] 'Verlauf' gecisleri (".substr_count($s,'Verlauf').") ===\n";
$off=0;$c=0; while(($p=strpos($s,'Verlauf',$off))!==false && $c<8){ echo "  [".$p."] …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-70),160)))."…\n"; $off=$p+6;$c++; }

echo "\n=== [B2] konusma/chat gecmisi depolamasi (localStorage anahtarlari) ===\n";
foreach(['ch_history','ch_chats','ch_convos','ch_conversations','ch_verlauf','ch_sessions','ch_threads','chat_history','ch_msgs_hist','saveConversation','loadConversation','loadChat','ch_chat_'] as $k){
  $n=substr_count($s,$k); if($n>0) echo "  '".$k."': ".$n."\n";
}
echo "\n=== [B3] gecmis liste render / yukleme fonksiyonu ===\n";
foreach(['renderVerlauf','showHistory','histList','openHistory','loadThread','restoreChat','function loadChat','nav-verlauf','pnl-verlauf'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-20),150)))."\n";
}
echo "\n=== [B4] sidebar Verlauf nav ogesi ===\n";
$p=strpos($s,'>Verlauf<'); if($p===false)$p=strpos($s,'Verlauf</');
if($p!==false) echo "  …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-180),260)))."…\n";
echo "\nBITTI.\n";
