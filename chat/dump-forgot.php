<?php
/** ChatHelp — dump-forgot (SADECE OKUR) — "Passwort vergessen" akisi:
 *  index.php frontend (link+handler+fetch) + auth.php backend (forgot/reset
 *  router, reset_token, sendMail). Hassas degerler MASKELENIR. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/(sk_live_|sk_test_|whsec_)[A-Za-z0-9]+/','$1***MASKED***',$s);
  $s=preg_replace('/(pass(word)?|secret|token|key)\s*[=:]\s*[\'"][^\'"]+[\'"]/i','$1=***MASKED***',$s);
  return $s;
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
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$auth=(string)@file_get_contents(__DIR__.'/auth.php');

echo "════════ [1] index.php — frontend ════════\n";
raw($idx,'vergessen',-80,220,'Passwort vergessen link/metin',8);
raw($idx,'forgot',-60,200,'forgot',10);
raw($idx,'reset',-50,160,'reset (frontend)',12);
raw($idx,'rp-pw',-80,200,'reset parola alani (rp-*)',6);

echo "\n════════ [2] auth.php — backend (".strlen($auth)." bayt) ════════\n";
if($auth===''){ echo "  auth.php OKUNAMADI\n"; }
else {
  raw($auth,'forgot',-60,240,'forgot endpoint',8);
  raw($auth,'reset',-60,240,'reset endpoint',12);
  raw($auth,'reset_token',-60,200,'reset_token',8);
  raw($auth,'reset_expires',-60,200,'reset_expires',6);
  raw($auth,'function sendMail',-20,200,'sendMail',3);
  raw($auth,'case ',-10,60,'router case listesi',40);
  raw($auth,'=>',-30,60,'router => listesi',40);
}
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
