<?php
/** ChatHelp — dump-vst-tr (SADECE OKUR) — TR seti tamamlama icin:
 *  TRK merge + ulke filtresi + mevcut TR listesi, modul render/liste + ulke
 *  gating, tr_vertraege duz-kart sozlesmeleri (kaldirilacak). TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] TRK devami: merge + ulke filtresi + tam liste ════";
sl($idx,1392200,3400,'TRK devam (vtr_tr_kira sonrasi + merge + filtre)');

echo "\n════ [B] modul sozlesme listesi render + ulke gating ════\n";
raw($idx,'for(var k in CT)',-40,200,'CT dongusu (render)',8);
raw($idx,'CT_COUNTRY',-40,160,'CT_COUNTRY / ulke haritasi',6);
raw($idx,'vtrCountry',-40,160,'vtrCountry',6);
raw($idx,'.vst-card',-60,180,'vst-card render',6);
raw($idx,'function openVST',-20,240,'openVST',3);
raw($idx,'function renderVST',-20,240,'renderVST',3);
raw($idx,'gP===\'vst\'',-40,160,"gP vst",4);

echo "\n════ [C] tr_vertraege duz-kart sozlesmeleri (KALDIRILACAK) ════\n";
raw($idx,'tr_vertraege',-40,200,'tr_vertraege',10);
raw($idx,'tr_kaufvertrag',-30,120,'tr_kauf',4);
raw($idx,'tr_kira',-30,140,'tr_kira (kart)',6);
raw($idx,'tr_mietvertrag',-30,120,'tr_miet',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
