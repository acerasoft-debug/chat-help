<?php
/** ChatHelp — dump-cardchurn (SADECE OKUR)
 *  AMAC: DE-disi hukuklarda kartlarin "oynamaya devam ettigi" iddiasi icin
 *  KESIN kanit. Uc olasilik test edilecek:
 *   [1] YINELENEN KART: ayni data-ccat/univcat/trcat/frcat anahtari
 *       birden fazla enjektor tarafindan tekrar tekrar mi ekleniyor?
 *       (Kod tarafinda TUM "hasCard"/"querySelector varsa atla" korumalarini
 *       listele — hangi enjektorde YOK?)
 *   [2] METIN SAVASI CATI: CH_I18N_TRUCE'un data-noi18n bayragi index
 *       kaynaginda .wcat ile ilgili herhangi bir yerde ONCEDEN taniniyor mu
 *       (skipEl zaten data-noi18n'i atliyordu — kartlarda kim yaziyor?)
 *   [3] cc()/CC degisiminde .wcat-grid'i TEKRAR TEKRAR yeniden olusturan
 *       (innerHTML='' + yeniden doldur) bir fonksiyon var mi (childList'i
 *       TOPTAN sifirlayip yeniden yazan, guard'siz calisan bir yer)?
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "dump-cardchurn BASLADI (index ".number_format(strlen($src))." B)\n";

function win($src,$needle,$pre,$post,$label,$max=6){
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

/* [1] grid.innerHTML='' TOPTAN sifirlama var mi */
win($src,"grid.innerHTML=''",100,150,'[1a] wcat-grid TOPTAN sifirlama',5);
win($src,"grid.innerHTML=",100,150,'[1b] grid.innerHTML yazimlari (genel)',8);

/* [2] hasCard benzeri korumalarin TUMU — hangi enjektor guard'siz */
win($src,'querySelector(\'[data-ccat=',80,180,'[2a] data-ccat guard kontrolleri',10);
win($src,'querySelector("[data-ccat=',80,180,'[2b] data-ccat guard (cift tirnak)',10);

/* [3] i18n-full'un skipEl'i data-noi18n'i nerede kontrol ediyor + CH_I18N_TRUCE yazim noktalari */
win($src,'data-noi18n',150,150,'[3] data-noi18n kullanim noktalari',6);
win($src,'CH_I18N_TRUCE',120,220,'[3b] CH_I18N_TRUCE izleri (TUMU)',6);

/* [4] .wcat elemanlarinin OLUSTURULDUGU TUM createElement noktalari */
win($src,"className=\"wcat\"",60,250,'[4a] wcat createElement (cift tirnak)',10);
win($src,"className='wcat'",60,250,"[4b] wcat createElement (tek tirnak)",10);

/* [5] CC (ulke) degisiminde grid'i tamamen yeniden kuran fonksiyon var mi */
win($src,'function xGrid',100,600,'[5] xGrid TAM govdesi (X_ORDER kartlarini kim/nasil ekliyor)',2);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
