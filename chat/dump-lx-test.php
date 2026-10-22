<?php
/**
 * ChatHelp — dump-lx-test (UCTAN UCA TEST) — LetterXpress TEST MODUNDA deneme
 *  mektubu: postversand.php action=send_text ile metinden PDF uretilir ve
 *  setJob'a gonderilir. TEST modda gercek mektup GITMEZ, ucret ALINMAZ —
 *  yalnizca boru hatti dogrulanir. Ayrica status/bakiye gosterilir.
 * KULLANIM: pull2.php?key=...&files=dump-lx-test.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
echo "=== dump-lx-test — LetterXpress uctan uca (TEST modu) ===\n\n";
$host=$_SERVER['HTTP_HOST']??'chat-help.com';
$dir=rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x.php'),'/');
$base='https://'.$host.$dir.'/postversand.php';
function req($url,$post=null){
  $c=curl_init($url);
  $o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>90,CURLOPT_USERAGENT=>'ChatHelp-lxtest'];
  if($post!==null){ $o[CURLOPT_POST]=true;$o[CURLOPT_POSTFIELDS]=json_encode($post);$o[CURLOPT_HTTPHEADER]=['Content-Type: application/json']; }
  curl_setopt_array($c,$o);
  $b=curl_exec($c); $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
  return [$code,$b];
}
echo "[1] status:\n";
list($c1,$b1)=req($base.'?action=status');
echo "  HTTP $c1 — ".substr((string)$b1,0,300)."\n\n";
$j=json_decode((string)$b1,true);
if(!is_array($j)||empty($j['configured'])){ exit("SONUC: servis yapilandirilmamis — once kimlik kurulumu gerekli.\n"); }
if(($j['mode']??'')!=='test'){ echo "  ⚠ DIKKAT: mod '".($j['mode']??'?')."' — test degil!\n\n"; }
echo "[2] send_text (deneme mektubu, TEST modu):\n";
$txt="ChatHelp Testbrief\n\nSehr geehrte Damen und Herren,\n\ndies ist ein automatischer Testbrief zur Verifizierung der Postversand-Anbindung. Bitte ignorieren.\n\nMit freundlichen Gruessen\nChatHelp System";
list($c2,$b2)=req($base.'?action=send_text',['text'=>$txt]);
echo "  HTTP $c2 — ".substr((string)$b2,0,500)."\n\n";
$k=json_decode((string)$b2,true);
if(is_array($k)&&!empty($k['ok'])) echo "SONUC: BORU HATTI CALISIYOR ✓ — PDF uretildi, LetterXpress kabul etti (test modu).\n  Canliya gecis: LetterXpress panelinden bakiye yukle + mode=live.\n";
else echo "SONUC: sorun var — ciktinin tamamini gonderin, degerlendirecegim.\n";
