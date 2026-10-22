<?php
/** ChatHelp — dump-de-vertrag (SADECE OKUR) — DE duz-kart sozlesmeleri:
 *  cat:'vertraege' uyeleri (key+ad), vertraege kategori tanimi, nasil render
 *  ediliyor (wcat/kategori), diger DE sozlesme-tipi belgeler. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function raw($src,$needle,$pre,$len,$label,$max=14){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] cat:'vertraege' / \"vertraege\" uyeleri (DE sozlesme kartlari) ════\n";
raw($idx,"cat:'vertraege'",-90,60,"cat='vertraege' tekli",20);
raw($idx,'cat:"vertraege"',-90,60,'cat=vertraege cift',20);

echo "\n════ [B] vertraege kategori tanimi + label ════\n";
raw($idx,'vertraege:{',-20,140,'vertraege kategori obj',8);
raw($idx,'Verträge',-60,80,'Vertrage metin',10);
raw($idx,"'vertraege'",-40,80,'vertraege string gecis',12);

echo "\n════ [C] diger DE sozlesme-tipi belgeler ════\n";
raw($idx,'Kaufvertrag',-60,70,'Kaufvertrag',6);
raw($idx,'Mietvertrag',-60,70,'Mietvertrag',6);
raw($idx,'Arbeitsvertrag',-60,70,'Arbeitsvertrag',5);
raw($idx,'Darlehensvertrag',-60,70,'Darlehen',4);
raw($idx,'Untermietvertrag',-60,70,'Untermiet',3);

echo "\n════ [D] DE kategori render/tiklama (showCat vs modul) ════\n";
raw($idx,'CATS[',-30,90,'CATS[ erisim',8);
raw($idx,'NEWCATS',-30,120,'NEWCATS',6);
raw($idx,'data-univcat="vertraege"',-80,60,'vertraege wcat',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
