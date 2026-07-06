<?php
/** ChatHelp — dump-profile-bewerbung (SADECE OKUR)
 *  Amac: (1) Google giris -> hangi kullanici bilgileri kaydediliyor (auth.php),
 *        (2) profil alanlari (ad/adres/e-posta) ve formlara autofill (index.php),
 *        (3) Bewerbung/Anschreiben uretim akisi (index.php + api.php).
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);

function occ($src,$needle){ $n=substr_count($src,$needle); return $n; }
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p = strpos($src,$needle);
    if ($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALL($src,$needle,$before,$len,$label,$max=6){
    echo "──────── $label (tum gecisler) ────────\n";
    $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n";
        $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok: '$needle')\n";
    echo "\n";
}

/* ═══ auth.php — Google giris handler ═══ */
$auth = @file_get_contents(__DIR__.'/auth.php');
echo "############ auth.php ############\n";
if($auth===false){ echo "  (auth.php okunamadi)\n\n"; }
else {
    echo "boyut: ".strlen($auth)." bayt\n";
    foreach(['action=google',"'google'",'email','name','given_name','family_name','picture','payload','\$_SESSION','setcookie','id_token','sub'] as $k)
        echo "  '$k' -> ".occ($auth,$k)." kez\n";
    echo "\n";
    W($auth,'google',-40,900,"google action handler civari");
    W($auth,'given_name',-200,400,"given_name kullanimi");
    W($auth,'name',-80,300,"name -> nereye yaziliyor");
}

/* ═══ index.php — profil + autofill + Bewerbung ═══ */
$idx = @file_get_contents(__DIR__.'/index.php');
echo "\n############ index.php ############\n";
if($idx===false){ echo "  (index.php okunamadi)\n\n"; }
else {
    echo "boyut: ".strlen($idx)." bayt\n";
    foreach(['f1','f2','f3','f4','f5','f6','f7','[VORNAME]','[NACHNAME]','[ADRESSE]','[ORT]','ch_prof','profil','profile','Bewerbung','Anschreiben','Eintrittstermin','verfügbar','ab sofort','arbeitgeber','employer','autofill','prefill','ch_photos','givenName','ch_user','user.email'] as $k)
        echo "  '$k' -> ".occ($idx,$k)." kez\n";
    echo "\n";

    /* profil tanimi / kaydi */
    ALL($idx,'VORNAME',-60,160,"[VORNAME] vb. placeholder kullanimi",8);
    W($idx,'ch_prof',-30,700,"ch_prof (profil objesi) civari");
    W($idx,'function saveProf',0,600,"saveProf (varsa)");
    W($idx,'function loadProf',0,600,"loadProf (varsa)");
    W($idx,'localStorage.getItem("ch_prof',-60,300,"profil localStorage okuma");

    /* google login sonrasi frontend ne yapiyor */
    W($idx,'handleGoogleLogin',0,900,"handleGoogleLogin govdesi");
    W($idx,'auth.php?action=google',-40,600,"google fetch + donen veri kullanimi");

    /* Bewerbung / Anschreiben modal + alanlar */
    W($idx,'Bewerbung',-120,700,"Bewerbung ilk gecis (UI/akis)");
    W($idx,'Anschreiben',-120,700,"Anschreiben civari (modal/alanlar)");
    W($idx,'Eintrittstermin',-120,300,"Eintrittstermin/baslangic (varsa)");
    W($idx,'verfügbar',-120,300,"verfügbar/availability (varsa)");
    W($idx,'employerEmail',-120,300,"isveren e-posta alani (varsa)");
}

/* ═══ api.php — Bewerbung/Anschreiben backend ═══ */
$api = @file_get_contents(__DIR__.'/api.php');
echo "\n############ api.php ############\n";
if($api===false){ echo "  (api.php okunamadi)\n\n"; }
else {
    echo "boyut: ".strlen($api)." bayt\n";
    foreach(['Bewerbung','Anschreiben','bew_','doJobLetter','jobletter','Eintrittstermin','profBlock','[VORNAME]','CH_ANON'] as $k)
        echo "  '$k' -> ".occ($api,$k)." kez\n";
    echo "\n";
    W($api,'Anschreiben',-150,700,"Anschreiben uretim promptu");
    W($api,'doJobLetter',0,1200,"doJobLetter() (varsa)");
    W($api,'bew_',-60,300,"bew_ docType ele alma");
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-profile-bewerbung.php ===\n";
