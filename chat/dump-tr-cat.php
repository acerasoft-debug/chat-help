<?php
/** ChatHelp — dump-tr-cat (SADECE OKUR) — TR katalog: tr_sozlesme kategorisi +
 *  uyeleri (kaldirilacak sozlesmeler), tr_konut'taki Kira Sozlesmesi, kategori
 *  render/tiklama (showCat) + veri yapisi adlari. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=10){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] TR katalog: tr_sozlesme kategori + tum uyeleri ════\n";
/* tr_sozlesme kategori tanimi civari + uye sozlesme key'leri */
sl($idx,599000,1600,'tr_sozlesme uyeleri (@599000)');
sl($idx,641000,2200,'tr_sozlesme/tr_konut uyeleri (@641000)');
raw($idx,'cat:"tr_sozlesme"',-40,50,'tr_sozlesme uye baslangici (key)',14);

echo "\n════ [B] tr_konut Kira Sozlesmesi (kaldirilacak tek belge) ════\n";
raw($idx,'Kira Sözleşmesi (Konut)',-60,60,'Kira Sozlesmesi key',3);
raw($idx,'cat:"tr_konut"',-40,60,'tr_konut uyeleri (key+ad)',14);

echo "\n════ [C] TR katalog veri yapisi + render/tiklama ════\n";
raw($idx,'var T={',-40,120,'TR katalog obj (var T)',4);
raw($idx,'var D={',-60,120,'var D (belge obj)',6);
raw($idx,'TR_ORDER',-20,140,'TR_ORDER',4);
raw($idx,'CAT_DOCS',-30,120,'CAT_DOCS',8);
raw($idx,'function showCat',-10,260,'showCat',3);
raw($idx,'showCat(',-30,90,'showCat cagrilari',10);
raw($idx,'CAT_LABELS',-30,120,'CAT_LABELS',6);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
