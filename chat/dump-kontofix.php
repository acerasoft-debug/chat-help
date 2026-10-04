<?php
/** ChatHelp — dump-kontofix (SADECE OKUR) — Konto Profil kartı (Name kaynagi),
 *  Pläne&Preise butonu, Kategorien anzeigen toggle, ust-bar profil butonu (Serdar),
 *  Absender-Profile aktif + belge gonderen. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] Pläne & Preise butonu + onclick ════\n";
raw($idx,'Pläne',-100,160,'Pläne metni',6);
raw($idx,'Pläne &amp; Preise',-120,160,'Plane Preise entity',4);
raw($idx,'k-plan',-60,160,'k-plan* (konto plan)',10);

echo "\n════ [B] Kategorien anzeigen toggle ════\n";
raw($idx,'anzeigen',-120,120,'anzeigen',8);
raw($idx,'einblenden',-100,120,'einblenden',5);
raw($idx,'toggleCat',-40,180,'toggleCat',5);
raw($idx,'cat-toggle',-60,160,'cat-toggle',6);
raw($idx,'KATEGORIEN',-40,180,'KATEGORIEN baslik',4);

echo "\n════ [C] Konto Profil karti (Name kaynagi) ════\n";
raw($idx,'>Profil<',-60,120,'Profil basligi',4);
raw($idx,'Aktives Profil wird',-80,160,'Absender-Profile aciklamasi',3);
raw($idx,'Absender',-60,140,'Absender',6);
raw($idx,'k-prof-name',-60,160,'k-prof-name',5);
raw($idx,'.name||',-40,120,'.name|| (fallback)',10);

echo "\n════ [D] ust-bar profil butonu (Serdar) + profil switch ════\n";
raw($idx,'tb-auth-label',-100,120,'tb-auth-label',6);
raw($idx,'switchProfile',-40,180,'switchProfile',6);
raw($idx,'setActiveProf',-40,180,'setActiveProf',6);
raw($idx,'ch_prof_active',-50,140,'ch_prof_active',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
