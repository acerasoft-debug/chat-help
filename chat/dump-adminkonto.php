<?php
/** ChatHelp — dump-adminkonto (SADECE OKUR):
 *  A) "Site acilinca Konto acilyor" — index.php'de otomatik/init gP('konto')
 *     cagrisi var mi (acilista paneli acan sey).
 *  B) Admin sayfasi: admin.php canli var mi + icerigi; auth.php'de admin
 *     action/yetki var mi; users/user_plans yonetim altyapisi.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$auth=(string)@file_get_contents("$D/auth.php");

echo "════ [A] acilista gP('konto') / panel acan sey ════\n";
foreach(["gP('konto')",'gP("konto")'] as $nd){
    $off=0;$n=0;$tot=substr_count($idx,$nd);
    echo "\n-- '$nd' (toplam $tot) --\n";
    while(($p=strpos($idx,$nd,$off))!==false && $n<12){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($idx,max(0,$p-90),150))."\n";
        $off=$p+strlen($nd);$n++;
    }
}
echo "\n-- 'konto' iceren init/DOMContentLoaded yakininda otomatik cagri var mi --\n";
$off=0;$n=0;
while(($p=strpos($idx,"gP('konto')",$off))!==false && $n<12){
    $ctx=substr($idx,max(0,$p-260),300);
    if(strpos($ctx,'onclick')===false && strpos($ctx,'addEventListener')===false){
        echo "[OTOMATIK? @$p] ".str_replace("\n"," ⏎ ",$ctx)."\n\n";
    }
    $off=$p+11;$n++;
}
echo "\n-- handleUserBtn otomatik cagriliyor mu --\n";
$off=0;$n=0;$tot=substr_count($idx,'handleUserBtn');
while(($p=strpos($idx,'handleUserBtn',$off))!==false && $n<8){
    echo "[@$p] ".str_replace("\n"," ",substr($idx,max(0,$p-70),120))."\n"; $off=$p+12;$n++;
}
echo "(toplam handleUserBtn: $tot)\n";

echo "\n════ [B] admin.php canli var mi ════\n";
foreach(['admin.php','admin-panel.php','verwaltung.php','panel.php','manage.php'] as $f){
    $p="$D/$f";
    echo "  $f: ".(is_file($p)?"VAR (".filesize($p)." bayt)":"yok")."\n";
}
$admin=(string)@file_get_contents("$D/admin.php");
if($admin!==''){
    echo "\n---- admin.php (ilk 3500 bayt) ----\n".substr($admin,0,3500)."\n";
} else echo "\n(admin.php YOK — sifirdan olusturulacak)\n";

echo "\n════ [C] auth.php admin/yetki + user yonetimi ════\n";
foreach(['admin','is_admin','role','ADMIN','list_users','set_plan','update_plan'] as $nd){
    $c=substr_count($auth,$nd);
    if($c>0){ $p=strpos($auth,$nd); echo "  '$nd': $c kez, ilk@$p: ".str_replace("\n"," ",substr($auth,max(0,$p-40),130))."\n"; }
}
echo "\n-- users tablosu kolonlari (INSERT/SELECT'ten cikarim) --\n";
$p=strpos($auth,'INSERT INTO users');
echo $p!==false ? substr($auth,$p,180)."\n" : "(INSERT yok)\n";

echo "\n════ [D] db() baglanti + config ════\n";
$p=strpos($auth,'function db(');
echo $p!==false ? substr($auth,$p,600)."\n" : "(db() yok)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
