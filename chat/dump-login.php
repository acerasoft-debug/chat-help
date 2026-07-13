<?php
/** ChatHelp — dump-login (SADECE OKUR) — zorunlu Anmeldung gate + sifre-goster icin:
 *  [1] login.php TAM icerigi (form yapisi, password input(lar)i, mevcut
 *      show/hide/goz butonu var mi, hangi action POST ediliyor)
 *  [2] index.php: ilk-giris gate — token yoksa login.php'ye yonlendiren
 *      mevcut mantik (apply-profile-required / onboarding) var mi
 *  [3] register.php / signup ayri dosya mi (Anmeldung sayfasi)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$login=(string)@file_get_contents("$D/login.php");
$idx=(string)@file_get_contents("$D/index.php");

echo "════ [1] login.php ════\n";
if($login===''){ echo "(login.php YOK)\n"; }
else {
    echo "login.php: ".strlen($login)." bayt\n";
    echo "  type=\"password\" gecisi: ".substr_count($login,'type="password"')."\n";
    echo "  type='password' gecisi: ".substr_count($login,"type='password'")."\n";
    echo "  'toggle'/'eye'/'goz'/'show' gecisi: ".(substr_count($login,'toggle')+substr_count($login,'eye')+substr_count($login,'showPass')+substr_count($login,'👁'))."\n";
    if(strlen($login)<=13000){ echo "\n---- TAM ICERIK ----\n".$login."\n"; }
    else {
        echo "\n---- password input cevreleri ----\n";
        $off=0;$n=0;
        foreach(['type="password"',"type='password'"] as $pt){
            while(($p=strpos($login,$pt,$off))!==false && $n<6){ echo "[@$p] ".substr($login,max(0,$p-260),520)."\n[/end]\n"; $off=$p+strlen($pt);$n++; }
            $off=0;
        }
        echo "\n---- form + action + submit handler ----\n";
        foreach(['<form','action=','doLogin','doRegister','fetch(','submit'] as $nd){
            $p=strpos($login,$nd);
            if($p!==false) echo "[$nd @$p] ".str_replace("\n"," ",substr($login,max(0,$p-20),200))."\n";
        }
    }
}

echo "\n════ [2] index.php: ilk-giris gate / login.php yonlendirme ════\n";
foreach(['login.php','CH_PROFILE_REQ','CH_ONBOARD','profile-required','requireLogin','gateLogin','ch_ldi','erststart','firstVisit'] as $nd){
    $c=substr_count($idx,$nd);
    if($c>0){ $p=strpos($idx,$nd); echo "  '$nd': $c kez, ilk@$p: ".str_replace("\n"," ",substr($idx,max(0,$p-60),160))."\n"; }
}

echo "\n════ [3] register/Anmeldung ayri dosya mi ════\n";
foreach(['register.php','signup.php','anmeldung.php','registrieren.php'] as $f){
    echo "  $f: ".(is_file("$D/$f")?"VAR (".filesize("$D/$f")." bayt)":"yok")."\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
