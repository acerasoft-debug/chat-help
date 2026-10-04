<?php
/** ChatHelp — dump-vertrag (SADECE OKUR) — sozlesme (Vertrag) mimarisi:
 *  Vertrag Studio modul veri yapisi, hukuk-ulkesine (CC) gore mi, hangi
 *  sozlesmeler normal sablondan geciyor, ana sayfa sozlesme kategorileri.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] Vertrag Studio modul ════\n";
raw($idx,'nav-vst',-40,140,'nav-vst',5);
raw($idx,'VST',-30,120,'VST tanimlari',14);
raw($idx,'Vertrag',-40,120,'Vertrag metin',12);
raw($idx,'vertragStudio',-40,160,'vertragStudio fn',6);
raw($idx,'window.chVertrag',-40,180,'chVertrag*',8);

echo "\n════ [B] sozlesme veri yapisi (options/klozlar) ════\n";
raw($idx,'VCATS',-30,200,'VCATS',8);
raw($idx,'vertraege:',-40,300,'vertraege: CATS girisi',4);
raw($idx,'kloz',-40,120,'kloz/klausel',8);
raw($idx,'options',-40,120,'options',10);
raw($idx,'opt:',-30,120,'opt:',10);

echo "\n════ [C] hukuk-ulke (CC) gate — sozlesme ════\n";
raw($idx,'CC_DATA',-30,120,'CC_DATA',6);
raw($idx,'countryOf',-40,180,'countryOf',6);
raw($idx,'vertr',-30,90,'vertr* anahtarlar',20);

echo "\n════ [D] normal sablon (kart -> doc) akisi ════\n";
raw($idx,'function showCat',-20,300,'showCat',3);
raw($idx,'function genDoc',-20,240,'genDoc',3);
raw($idx,'DOCS._default',-60,160,'DOCS._default',5);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
