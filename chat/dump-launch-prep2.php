<?php
/** ChatHelp — dump-launch-prep2: generate fonksiyonu, buton, STAATLICHE giris, oneriler, Antrage.
 *  SADECE OKUR. SIL: rm dump-launch-prep2.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function W($s,$from,$len,$lbl){ echo "\n──── $lbl [@$from +$len] ────\n".substr($s,$from,$len)."\n"; }
function R($s,$needle,$b,$l,$lbl){
  echo "\n──── $lbl ────\n"; $p=strpos($s,$needle);
  if($p===false){ echo "(BULUNAMADI: '".substr($needle,0,50)."')\n"; return; }
  echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";
}
function RALL($s,$needle,$b,$l,$lbl,$max=6){
  echo "\n──── $lbl (ilk $max) ────\n"; $off=0;$n=0;
  while(($p=strpos($s,$needle,$off))!==false && $n<$max){
    echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n"; $off=$p+strlen($needle); $n++;
  }
  if($n===0) echo "(BULUNAMADI)\n";
}

echo "############ A) FORM RENDER + GENERATE BUTONU + HANDLER (#2,#3,#5,#9) ############\n";
echo "  (offset ~719000-722600 penceresi: soru render + buton + async uretim)\n";
W($idx,718900,3900,'form render + generate handler ham pencere');

echo "\n\n############ B) 'Dokument generieren' butonu tam markup ############\n";
R($idx,'Dokument generieren',-260,340,'buton etiketi baglami');
R($idx,'q.type',-200,600,'soru tipi dallanmasi (date/textarea/text) — #9 icin');
RALL($idx,'type="date"',-80,160,'mevcut date inputlari',4);

echo "\n\n############ C) STAATLICHE FORMS — KATALOG + GIRIS NOKTASI (#1) ############\n";
R($idx,'STAATLICHE_FORMS',-40,700,'STAATLICHE_FORMS tanimi');
R($idx,'function showStaatKat',0,700,'showStaatKat()');
RALL($idx,'staatlich',-120,180,'staatliche menu/kart girisleri',8);
R($idx,'showStaatKat(',-260,340,'showStaatKat CAGRISI (menu/kart onclick)');

echo "\n\n############ D) ONERILER / CHIP / OPTIONS (#10) ############\n";
R($idx,'chip',-160,320,'chip render');
W($idx,892380,700,'options @892583 baglami');
R($idx,'Vorschlag',-160,320,'Vorschlag baglami');

echo "\n\n############ E) MEINE ANTRAGE — LISTE + KAYDET/BEHALTEN (#8) ############\n";
RALL($idx,'ant-item',-200,260,'ant-item render',5);
R($idx,'astat',-200,300,'astat (Antrage istatistik/kart)');
RALL($idx,'Antr',-60,140,'Antr* fonksiyon/degisken adlari',12);
R($idx,'loadRecentDocs',0,700,'loadRecentDocs() — uretilen belge listesi');
R($idx,'ch_recent',-120,300,'ch_recent / kayitli belge anahtari');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-launch-prep2.php ===\n";
