<?php
/* dump-connect-check — KOMPLE baglanti kontrolu (gonderim YOK, kredi harcamaz):
   postversand surumu, LetterXpress (mektup) durum+bakiye, ClickSend (fax) canli
   auth (v3/account) + bakiye. Her satirda ✓/✗.
   KULLANIM: pull2.php?key=...&files=apply-postversand3.php,dump-connect-check.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "=== ChatHelp Gonderim Baglanti Kontrolu ===\n\n";

function mark($ok){ return $ok?'[✓]':'[✗]'; }
$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
function g($u){ $c=curl_init($u); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]); $r=curl_exec($c); curl_close($c); return $r; }
$cb='&_cb='.str_replace('.','',microtime(true)).rand(100,999);

/* 1) postversand surumu (disk) */
$pv=@file_get_contents(__DIR__.'/postversand.php'); $pvlen=$pv?strlen($pv):0;
$hasCS=$pv&&strpos($pv,'fax_send_clicksend')!==false;
$hasPing=$pv&&strpos($pv,"action==='fax_ping'")!==false;
echo mark($hasCS&&$hasPing)." postversand.php guncel: {$pvlen} B  (clicksend:".($hasCS?'var':'yok').", fax_ping:".($hasPing?'var':'yok').")\n";

/* 2) LetterXpress (mektup + Einschreiben) */
$st=json_decode((string)g($base.'?action=status'.$cb),true);
$lxOK=is_array($st)&&!empty($st['configured']);
$lxBal=is_array($st)&&isset($st['balance']['value'])?($st['balance']['value'].' '.($st['balance']['currency']??'')):'?';
echo mark($lxOK)." LetterXpress (Brief/Einschreiben): configured=".($lxOK?'EVET':'HAYIR').", mode=".($st['mode']??'?').", bakiye=$lxBal\n";

/* 3) fax saglayici konfig */
$fp=is_array($st)?($st['fax_provider']??'(yok)'):'(yok)';
$fc=is_array($st)?($st['fax_configured']??null):null;
echo mark($fp==='clicksend'&&$fc===true)." Fax saglayici: $fp, kimlik yuklu=".($fc===null?'(eski postversand!)':($fc?'EVET':'HAYIR'))."\n";

/* 4) ClickSend CANLI auth ping (gonderimsiz) */
$pg=json_decode((string)g($base.'?action=fax_ping'.$cb),true);
$pingOK=is_array($pg)&&!empty($pg['ok']);
$csBal=is_array($pg)&&isset($pg['balance'])?($pg['balance'].' '.($pg['currency']??'')):'?';
echo mark($pingOK)." ClickSend CANLI auth (v3/account): ".($pingOK?'BAGLANDI':'BASARISIZ')
     .", hesap=".($pg['account']??'?').", bakiye=$csBal, http=".($pg['http']??'?')."\n";
if(!$pingOK && is_array($pg)) echo "     detay: ".json_encode($pg['raw']??$pg['error']??$pg)."\n";

echo "\n--- OZET ---\n";
$all=$hasCS&&$hasPing&&$lxOK&&($fp==='clicksend')&&($fc===true)&&$pingOK;
echo $all
  ? "✓ HER SEY BAGLI: Mektup (LetterXpress) + Fax (ClickSend) canli. Not: ClickSend fax\n  KANALININ hesapta acik oldugu ancak gercek bir fax gonderimiyle kesinlesir (sunset riski).\n"
  : "→ Eksik/hata var (yukaridaki ✗ satirlarina bak). ClickSend bakiye 0 ise kredi yukle;\n  auth basarisizsa user/API key kontrol; fax_ping 'not enabled' derse hesapta fax kapali.\n";
