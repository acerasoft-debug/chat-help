<?php
/** ChatHelp — dump-i18nwar (SADECE OKUR)
 *  BULGU ADAYI: DE disi dillerde menu/kart "oynamasi" = ceviri savasi:
 *  taban applyUI metinleri Almancaya geri yaziyor, ceviri katmanlari
 *  (1sn dongulu "geri Almancaya cevirirse tekrar duzelt" katmani, i18n-full,
 *  refreshLabels) tekrar ceviriyor -> DE disinde saniyelik metin ping-pongu.
 *  Bu dump savasin TARAFLARINI bire bir cikarir. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "dump-i18nwar BASLADI (index ".number_format(strlen($src))." B)\n";

function win($src,$needle,$pre,$post,$label,$max=4){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0; $n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@".$p."]\n".preg_replace('/[ \t]+/',' ',substr($src,$s,$pre+$post+strlen($needle)))."\n────\n";
        $off=$p+strlen($needle); $n++;
    }
    if(!$n) echo "(hic yok)\n";
    $tot=substr_count($src,$needle);
    if($tot>$max) echo "(toplam $tot, ilk $max gosterildi)\n";
}

/* [1] 1sn'lik duzeltme dongusu — apply() TAM govdesi (oncesi genis) */
win($src,"applyUI placeholder'i geri Almancaya cevirirse",2600,200,'[1] duzeltici katman (apply govdesi dahil)',2);

/* [2] taban applyUI: tanimi + cagiran yerler */
win($src,'function applyUI',200,1400,'[2] applyUI tanimi',2);
win($src,'applyUI(',120,60,'[3] applyUI cagrilari',10);

/* [4] refreshLabels tanimi */
win($src,'function refreshLabels',150,1300,'[4] refreshLabels tanimi',2);

/* [5] i18n-full cekirdegi: neyi ceviriyor, neyi SKIP ediyor */
win($src,'ch-i18n-full',100,1600,'[5] i18n-full baslangici',1);
win($src,'__chI',80,400,'[5b] i18n-full nobetci bayragi',3);

/* [6] sb-cat-name / akordeon adlarini kim yaziyor */
win($src,'sb-cat-name',350,350,'[6] akordeon adi yazicilari',5);

/* [7] wcat-t basligini periyodik yazan var mi (kart metni) */
win($src,'.wcat-t"',200,200,'[7a] wcat-t secicileri',6);
win($src,"wcat-t'",200,200,'[7b] wcat-t secicileri (tek tirnak)',6);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
