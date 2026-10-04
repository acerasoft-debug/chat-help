<?php
/** ChatHelp — dump-ui (SADECE OKUR):
 *  [1] sonuc chip bloku (@990600-991600): Behalten/Neues Dokument/Meine Anträge/
 *      Überarbeiten chip'lerinin tam kodu (Meine Anträge kaldirilacak)
 *  [2] ust bar: tb-auth butonu (Serdar) + AYRI Konto ikon butonu + handleUserBtn
 *  [3] tb-auth-label metin mantigi (ad / Einloggen)
 *  [4] result-card E-Mail (em) butonu tam
 *  [5] auth.php mail gonderimi (email+PDF icin altyapi var mi)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$auth=(string)@file_get_contents("$D/auth.php");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] sonuc chip bloku ════\n";
slice($idx,990600,1050,'chip blok @990600');

echo "\n════ [2] ust bar butonlari ════\n";
raw($idx,'tb-auth',-180,300,'tb-auth buton+label',5);
raw($idx,'handleUserBtn',-140,240,'handleUserBtn cagri/tanim',6);
raw($idx,'id="tb-konto"',-120,180,'tb-konto ikon',3);
raw($idx,'nav-konto',-100,140,'nav-konto',4);
raw($idx,'onclick="gP(\'konto\')"',-160,60,'gP konto inline onclick',6);

echo "\n════ [3] tb-auth-label metin ════\n";
raw($idx,'tb-auth-label',-70,170,'tb-auth-label',8);

echo "\n════ [4] result-card E-Mail butonu ════\n";
slice($idx,986820,320,'em butonu @986820');

echo "\n════ [5] auth.php mail altyapisi ════\n";
foreach(['mail(','PHPMailer','sendmail','->send(','SMTP','wp_mail'] as $k){ echo "  '$k': auth.php=".substr_count($auth,$k)."\n"; }
raw($auth,'mail(',-60,140,'mail() (auth)',4);
raw($auth,'function sendMail',0,200,'sendMail fn',2);
raw($auth,'function sendVerify',0,200,'sendVerify fn',2);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
