<?php
/** ChatHelp — dump-lawpicker (SADECE OKUR) — hukuk-ulkesi seciciler haritasi
 *  Amac: hukuk sistemi SADECE Konto'dan degistirilebilsin; diger her yerde
 *  yalniz DIL secici kalsin. Bunun icin canli index'te:
 *  [1] setCountry cagiran tum UI noktalari (onclick/handler baglamiyla)
 *  [2] .co / co-grid ulke izgarasi ve picker panel/modal kimlikleri
 *  [3] sidebar/topbar bayrak dugmeleri (hangisi DIL hangisi HUKUK?)
 *  [4] dil seciciler (ch_uilang yazanlar) — bunlara DOKUNULMAYACAK
 *  [5] Konto #k-cc / #k-law bolgesi (secici buraya eklenecek) + CC_DATA listesi
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "[1] setCountry cagiran UI noktalari\n";
occ($idx,'setCountry(',-160,300,'setCountry cagrilari',12);

echo "\n[2] ulke izgarasi / picker\n";
occ($idx,'co-grid',-120,280,'co-grid',6);
occ($idx,"class=\"co\"",-140,280,'class="co" olusturma',4);
occ($idx,"className=\"co",-140,280,'className co',4);
occ($idx,'CC_DATA',-60,400,'CC_DATA tanimi/kullanimi',3);

echo "\n[3] bayrak dugmeleri\n";
occ($idx,'sb-flag',-120,260,'sidebar bayrak',4);
occ($idx,'setFlag',-80,200,'setFlag (dil algilama?)',3);
occ($idx,'id="flag',-120,260,'id=flag*',4);

echo "\n[4] dil seciciler (KORUNACAK)\n";
occ($idx,"setItem('ch_lm'",-140,260,'dil kilidi yazanlar',5);
occ($idx,'ch_uilang',-100,220,'ch_uilang yazanlar/okuyanlar',6);

echo "\n[5] Konto bolgesi\n";
occ($idx,'id="k-cc"',-200,400,'k-cc satiri',2);
occ($idx,'k-law-row',-80,240,'k-law-row (mevcut gosterim)',2);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
