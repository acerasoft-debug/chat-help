<?php
/** ChatHelp — dump-vst-xfull (SADECE OKUR) — XSETS FR/ES/IT/GB mevcut sozlesmeleri
 *  (key+ad) + kategori key'leri (fr_/es_/it_/gb_ contrats/contratos...). Eksikleri
 *  gormek + kategori yonlendirme icin. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function keys($src,$prefix,$label){
    echo "\n──────── $label ('$prefix') ────────\n";
    $off=0;$n=0;
    while(($p=strpos($src,$prefix,$off))!==false && $n<40){
        /* key + n:"..." yakala */
        $seg=substr($src,$p,140);
        echo "  ".str_replace("\n","⏎",$seg)."\n";
        $off=$p+strlen($prefix);$n++;
    }
    echo "  (toplam ".substr_count($src,$prefix).")\n";
}
echo "════ [A] XSETS sozlesme anahtarlari (key + ad) ════\n";
keys($idx,'"vtr_es_','ES sozlesmeleri');
keys($idx,'"vtr_fr_','FR sozlesmeleri');
keys($idx,'"vtr_it_','IT sozlesmeleri');
keys($idx,'"vtr_gb_','GB sozlesmeleri');

echo "\n════ [B] ES/FR/IT/GB sozlesme kategori key'leri ════\n";
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n── $label ('$needle') ──\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "  [@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "  (YOK)\n"; echo "  (toplam $tot)\n";
}
raw($idx,'cat:"es_contrat',-40,60,'es_contrat*',6);
raw($idx,'es_contratos',-40,60,'es_contratos',4);
raw($idx,'fr_contrats',-40,60,'fr_contrats',4);
raw($idx,'it_contratti',-40,60,'it_contratti',4);
raw($idx,'gb_contract',-40,60,'gb_contract*',4);
raw($idx,'contratto',-40,50,'contratto',6);
raw($idx,'contrat',-40,50,'contrat (fr)',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
