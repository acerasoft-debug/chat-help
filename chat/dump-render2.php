<?php
/**
 * ChatHelp - dump-render2 (READ-ONLY, sir maskeli) - opts render + makePDF + email(PDF) mekanizmasi.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-render2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|secret|api_?key|smtp_pass|token)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  return $s;
}
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-render2 (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] opts -> <select> render (deger=etiket mi?) ===\n";
foreach(array("createElement('option')",'<option','.opts','o.opts','opts.map','opts.forEach','opts||','\"opts\"') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),array('',' '),substr($src,($q-80>0?$q-80:0),240))."...\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [2] makePDF tam govde (998498 +1300) ===\n";
echo "  ".str_replace("\n","\n  ",substr($src,998498,1300))."\n\n";

echo "=== [3] chEmailDoc + sendMail tam govde ===\n";
$q=strpos($src,'chEmailDoc=function'); if($q===false)$q=strpos($src,'window.chEmailDoc');
if($q!==false){ echo "  chEmailDoc @".$q.":\n  ".str_replace("\n","\n  ",substr($src,$q,700))."\n\n"; } else echo "  (chEmailDoc tanimi yok)\n";
$q=strpos($src,'async function sendMail'); if($q===false)$q=strpos($src,'function sendMail');
if($q!==false){ echo "  sendMail @".$q.":\n  ".str_replace("\n","\n  ",substr($src,$q,900))."\n\n"; }

echo "=== [4] server: send-doc.php + auth.php email_doc (attachment destegi?) ===\n";
foreach(array('send-doc.php','auth.php') as $f){
  $p=__DIR__.'/'.$f; $s=(string)@file_get_contents($p);
  echo "  --- $f (".strlen($s)." B) ---\n";
  if($f==='send-doc.php'){
    echo "  ".mask(str_replace("\n","\n  ",substr($s,0,1200)))."\n";
  } else {
    $q=stripos($s,'email_doc');
    if($q!==false){ echo "  [email_doc @".$q."]:\n  ".mask(str_replace("\n","\n  ",substr($s,($q-60>0?$q-60:0),700)))."\n"; }
  }
  echo "  attachment/Content-Type/multipart ipuclari: "
      .(stripos($s,'attachment')!==false?'attachment ':'')
      .(stripos($s,'multipart')!==false?'multipart ':'')
      .(stripos($s,'boundary')!==false?'boundary ':'')
      .(stripos($s,'base64')!==false?'base64 ':'')
      .(stripos($s,'PHPMailer')!==false?'PHPMailer ':'')
      .(stripos($s,'addAttachment')!==false?'addAttachment ':'')."\n\n";
}
echo "== BITTI ==\n";
