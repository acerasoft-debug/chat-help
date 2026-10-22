<?php
/** ChatHelp — dump-visa (SADECE OKUR) — vize modulu komple elden gecirme.
 *  Mevcut yapiyi anlamam gerekiyor: TR/Schengen/ABD vize akisi nasil kurulu,
 *  sorular nasil soruluyor (sablon neden serbest metni kabul etmiyor), ulke/
 *  tarih secimi var mi, destekleyici evrak (checklist) yapisi var mi.
 *  [1] 'vize/visa/Visum/schengen' gecen yerler (harita)
 *  [2] TR vize kategorileri / katalog kayitlari (STAATLICHE_FORMS / DOCS icinde)
 *  [3] vize belge uretim akisi — soru soran yapi (fields/questions)
 *  [4] checklist / 'benotigt'/'gereken belgeler'/'unterlagen' yapisi
 *  [5] ulke listesi + tarih secici (date input) mevcut mu
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function raw($src,$needle,$pre,$len,$label,$max=8,$ci=false){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=($ci?substr_count(strtolower($src),strtolower($needle)):substr_count($src,$needle));
    $hay=$ci?strtolower($src):$src; $nd=$ci?strtolower($needle):$needle;
    while(($p=strpos($hay,$nd,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($nd);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] vize/visa/schengen haritasi ════\n";
raw($idx,'schengen',-20,90,'schengen',10,true);
raw($idx,'visa',-20,80,'visa',8,true);
raw($idx,'Visum',-20,80,'Visum',6);
raw($idx,'vize',-20,80,'vize',8,true);

echo "\n════ [2] TR vize kategori/katalog kayitlari ════\n";
raw($idx,'tr_vize',-10,140,'tr_vize anahtarlari',12);
raw($idx,'tr_visa',-10,140,'tr_visa anahtarlari',8);
raw($idx,'konsolosluk',-20,120,'konsolosluk',6,true);

echo "\n════ [3] vize soru/alan yapisi (fields/questions) ════\n";
raw($idx,'reiseziel',-30,120,'reiseziel (nereyi)',5,true);
raw($idx,'reisedatum',-30,120,'reisedatum',5,true);
raw($idx,'aufenthalt',-30,110,'aufenthalt',5,true);
raw($idx,'"land"',-20,90,'land alani',6);

echo "\n════ [4] checklist / gereken belgeler ════\n";
raw($idx,'checklist',-20,120,'checklist',8,true);
raw($idx,'unterlagen',-20,120,'unterlagen',6,true);
raw($idx,'benötigt',-30,120,'benotigt',5,true);
raw($idx,'nachweis',-20,110,'nachweis',6,true);
raw($idx,'versicherung',-20,110,'versicherung',5,true);

echo "\n════ [5] ulke listesi + tarih secici ════\n";
raw($idx,'type="date"',-60,100,'date input',6);
raw($idx,'SCHENGEN',-10,120,'SCHENGEN sabiti',5);
raw($idx,'COUNTRIES',-10,110,'COUNTRIES listesi',5);
raw($idx,'laenderListe',-10,110,'laenderListe',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
