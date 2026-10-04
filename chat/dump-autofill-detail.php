<?php
/** ChatHelp — dump-autofill-detail (SADECE OKUR)
 *  (1) fall-api.php: gercek Fall/dilekce uretimi (prompt+model+anon).
 *  (2) index.php: soru-formu render'i (autofill icin), profil kaydi/edit (f1-f7),
 *      Bewerbung/Anschreiben akisi + Eintrittstermin, vtr_ (Vertrag) alanlari.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALLW($src,$needle,$before,$len,$label,$max=4){
    echo "──────── $label (ilk $max gecis) ────────\n";
    $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n";
        $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n";
    echo "\n";
}

/* ═══ fall-api.php ═══ */
$fall=@file_get_contents(__DIR__.'/fall-api.php');
echo "############ fall-api.php ############\n";
if($fall===false){ echo "  (fall-api.php YOK/okunamadi — belki baska ada sahip)\n\n"; }
else{
    echo "boyut: ".strlen($fall)." bayt\n";
    foreach(['ch_ds','ch_claude','ch_vision','callClaude','callDeepSeek','§','Rechtsanwalt','profBlock','[VORNAME]','anon','placeholder','draftSys','verifySys','action','solve','generate'] as $k)
        echo "  '$k' -> ".substr_count($fall,$k)." kez\n";
    echo "\n";
    /* action dispatch */
    W($fall,'action',-20,400,"action dispatch civari");
    /* uretim promptu (sys) */
    ALLW($fall,'Du bist',-40,700,"'Du bist' (system prompt) gecisleri",5);
    W($fall,'Rechtsanwalt',-100,600,"Rechtsanwalt promptu (varsa)");
    W($fall,'§-Verweis',-200,400,"§-Verweis talimati (varsa)");
    W($fall,'profBlock',-100,500,"profBlock (profil enjeksiyonu)");
    W($fall,'[VORNAME]',-150,400,"anonimlestirme/placeholder (varsa)");
}

/* ═══ index.php: soru render + profil + Bewerbung ═══ */
$idx=@file_get_contents(__DIR__.'/index.php');
echo "\n############ index.php ############\n";
if($idx===false){ echo "  (okunamadi)\n"; }
else{
    /* soru objesi P('key','label',required) tanimi */
    W($idx,'function P(',0,400,"P('key',...) soru fabrikasi");
    /* soru formunu input'a ceviren render */
    ALLW($idx,'.q.forEach',-40,500,"q.forEach render (varsa)",3);
    ALLW($idx,'q.map(',-40,500,"q.map render (varsa)",3);
    W($idx,'renderQ',0,600,"renderQ (varsa)");
    W($idx,'function askNext',0,700,"askNext (soru sirasi, varsa)");
    W($idx,'data-qkey',-200,400,"input olusturma (data-qkey civari, varsa)");

    /* profil: kaydetme + edit formu (f1..f7) */
    W($idx,"setItem('ch_prof",-120,300,"ch_prof YAZMA (varsa)");
    W($idx,'function getP',0,400,"getP()");
    ALLW($idx,'Vorname',-60,220,"Vorname (profil edit label)",4);
    W($idx,'Telefon',-120,220,"Telefon (profil edit f5)");
    W($idx,'k-name',-200,300,"Konto profil edit alani civari");

    /* Bewerbung / Anschreiben akisi */
    W($idx,'Anschreiben erstellen',-60,300,"'Anschreiben erstellen' buton/handler");
    W($idx,'apply:',-40,200,"apply etiketi civari");
    ALLW($idx,'Eintrittstermin',-200,350,"Eintrittstermin (2 gecis)",3);
    W($idx,'bew_anschreiben',-20,700,"bew_anschreiben docType q[] tanimi");
    W($idx,'function genLetter',0,900,"genLetter/Anschreiben gonderimi (varsa)");
    W($idx,'action=generate',-300,500,"generate cagrisi (frontend) — payload");

    /* Vertrag alanlari */
    W($idx,'vtr_miet',-20,500,"vtr_ ornek docType q[] (parti alanlari)");
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-autofill-detail.php ===\n";
