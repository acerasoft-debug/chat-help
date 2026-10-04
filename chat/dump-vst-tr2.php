<?php
/** ChatHelp — dump-vst-tr2 (SADECE OKUR) — TR apply icin kesin parcalar:
 *  TRK tam liste sonu + syncTR merge/delete anchor'lari, renderCat, kaldirilacak
 *  duz-kart TR sozlesme key'leri. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] TRK liste sonu + syncTR (merge/delete anchor) ════";
sl($idx,1394800,3400,'TRK son + syncTR fonksiyonu');

echo "\n════ [B] syncTR anchor uniqueligi ════\n";
raw($idx,'for(var t in TRK){ CT[t]=TRK[t]; }',-30,80,'TRK merge satiri',4);
raw($idx,'for(var t2 in TRK){ delete CT[t2]; }',-30,80,'TRK delete satiri',4);
raw($idx,'function syncTR',-30,200,'syncTR',3);
raw($idx,'renderCat',-30,160,'renderCat cagrilari',10);

echo "\n════ [C] kaldirilacak duz-kart TR SOZLESMELERI ════\n";
raw($idx,'Kira Sözleşmesi (Konut)',-90,90,'Kira Sozlesmesi Konut karti',3);
raw($idx,'Ev Arkadaşlığı',-90,90,'Ev Arkadasligi',3);
raw($idx,'tr_sozlesme:',-30,200,'tr_sozlesme kategori',3);
raw($idx,'cat:"tr_sozlesme"',-70,80,'tr_sozlesme uyeleri',12);
raw($idx,'Sözleşmesi",cat:"tr_',-80,60,'TR sozlesme kartlari',15);

echo "\n════ [D] modul acilis / ana sayfa sozlesme girisi ════\n";
raw($idx,"gP('vst')",-60,120,"gP vst link",8);
raw($idx,'nav-vst',-20,80,'nav-vst',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
