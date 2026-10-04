<?php
/** ChatHelp — dump-formengine (SADECE OKUR)
 *  (1) index.php: V[docType].q sorularinin nasil SORULUP CEVAPLANDIGI (Q&A motoru)
 *      — autofill'i buraya baglayacagiz. + opts (secenek) render'i + generate payload.
 *  (2) api.php: tam bew_/CV (Lebenslauf) sysLaw promptu (estetik icin).
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
    echo "──────── $label (ilk $max) ────────\n";
    $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n";
        $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}

$idx=@file_get_contents(__DIR__.'/index.php');
echo "############ index.php — Q&A motoru ############\n";
if($idx===false){ echo "(okunamadi)\n"; } else {
    /* docType secilince akisi baslatan fonksiyon */
    W($idx,'function startDoc',0,900,"startDoc (varsa)");
    W($idx,'function openDoc',0,900,"openDoc (varsa)");
    W($idx,'function pickDoc',0,700,"pickDoc (varsa)");
    W($idx,'function askQ',0,900,"askQ (varsa)");
    W($idx,'function nextQ',0,900,"nextQ (varsa)");
    /* .q dizisini tuketen yer (soru sirasi) */
    ALLW($idx,'.q[',-80,260,"'.q[' indeksleme (soru akisi)",6);
    ALLW($idx,'cur.q',-60,220,"cur.q kullanimi",4);
    ALLW($idx,'.opts',-80,240,"'.opts' secenek render (dropdown)",5);
    /* cevaplarin biriktigi yapi */
    ALLW($idx,'answers',-60,200,"answers (cevap biriktirme)",6);
    /* generate cagrisi — payload (profil gonderiliyor mu?) */
    ALLW($idx,"action:'generate'",-60,420,"action:'generate' payload",3);
    ALLW($idx,'"action":"generate"',-60,420,"\"action\":\"generate\" payload",3);
    W($idx,"'generate'",-200,500,"'generate' (fetch civari)");
    /* profil edit formu (f1..f7) — kaydetme */
    W($idx,'ch_prof_v3',-200,500,"ch_prof_v3 (profil kaydi)");
    W($idx,'function saveP',0,500,"saveP (profil kaydetme, varsa)");
}

$api=@file_get_contents(__DIR__.'/api.php');
echo "\n############ api.php — CV/Bewerbung sysLaw ############\n";
if($api===false){ echo "(okunamadi)\n"; } else {
    W($api,'birinci sınıf',-200,900,"CV/bew_ sysLaw (Turkce, tam)");
    W($api,'Lebenslauf',-120,500,"Lebenslauf ozel talimat (varsa)");
    W($api,'bew_lebenslauf',-40,400,"bew_lebenslauf ele alma (varsa)");
    W($api,'function doGenerate',0,600,"doGenerate basi (payload alanlari)");
    ALLW($api,'$body[',-10,80,"\$body[...] okunan alanlar",20);
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-formengine.php ===\n";
