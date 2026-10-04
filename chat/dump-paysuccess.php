<?php
/**
 * ChatHelp — dump-paysuccess — odeme-sonrasi iki isleyicinin TAM govdesi (OKUR):
 *  [1] @~1027466 payment=success -> verify -> plan aktive
 *  [2] @~1162427 verify -> d.paid && mode==='payment' -> tek-belge kilit acma + ch_pending restore
 *  Eksik parcalar (fax hakki + auto-email + blur) icin gereken exact yapi.
 * KULLANIM: pull2.php?key=...&files=dump-paysuccess.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-paysuccess ==\n\n";

echo "=== [1] payment=success handler (@1027466 civari) ===\n";
$p=strpos($src,"_params.get('payment') === 'success'");
if($p!==false){ $st=strrpos(substr($src,0,$p),'const _params'); if($st===false)$st=max(0,$p-120); echo substr($src,$st,2200)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [2] verify mode==='payment' handler (@1162427 civari) TAM ===\n";
$p=strpos($src,"d.paid && d.mode==='payment'");
if($p===false)$p=strpos($src,"d.mode==='payment'");
if($p!==false){ $st=max(0,$p-260); echo substr($src,$st,2000)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [3] ch_pending restore (odeme sonrasi belge geri uretme) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_pending',$off))!==false && $n<8){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-70),220))."...\n";
  $off=$p+10; $n++;
}
echo "\n== BITTI ==\n";
