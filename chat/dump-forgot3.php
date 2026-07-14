<?php
/** ChatHelp — dump-forgot3 (SADECE OKUR) — aktif reset akisi son dogrulama:
 *  auth.php: doResetRequest + sendResetEmail (URL format). index.php: sayfa
 *  yuklenince ?reset= yakalama + reset submit action. Hassas maskeli. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk_live_|sk_test_|whsec_)[A-Za-z0-9]+/','$1***',$s); }
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$auth=(string)@file_get_contents(__DIR__.'/auth.php');
function showfn($src,$name,$len=1100){
  $p=strpos($src,'function '.$name);
  if($p===false){ echo "\n── $name: (YOK)\n"; return; }
  echo "\n──────── $name (@$p) ────────\n".str_replace("\n","⏎",mask(substr($src,$p,$len)))."\n[/end]\n";
}
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",mask(substr($src,max(0,$p-$pre),$len)))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [1] auth.php — doResetRequest + sendResetEmail ════\n";
showfn($auth,'doResetRequest',1200);
showfn($auth,'sendResetEmail',900);
showfn($auth,'doResetConfirm',900);

echo "\n════ [2] index.php — ?reset= yakalama + reset submit action ════\n";
raw($idx,'reset=',-60,120,'reset= (URL yakalama)',8);
raw($idx,'location.search',-40,160,'location.search',6);
raw($idx,'?reset',-40,120,'?reset',6);
raw($idx,"action:'reset",-40,140,'reset submit action',6);
raw($idx,'reset_confirm',-40,140,'reset_confirm cagrisi',4);
raw($idx,'reset_password',-40,140,'reset_password cagrisi',4);
raw($idx,'rp-pw1',-120,100,'rp-pw1 (submit handler civari)',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
