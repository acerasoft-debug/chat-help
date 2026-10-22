<?php
/** ChatHelp — dump-launch-prep: Google Play oncesi 11 maddelik is icin gereken TUM yapiyi cikarir.
 *  SADECE OKUR, hicbir sey degistirmez. Cikti duz metin. SIL: rm dump-launch-prep.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$fe =@file_get_contents(__DIR__.'/form-engine.js');
if($idx===false) exit("index.php yok\n");

function R($s,$needle,$b,$l,$lbl){
  echo "\n──── $lbl ────\n";
  if($s===false){ echo "(dosya yok)\n"; return; }
  $p=strpos($s,$needle);
  if($p===false){ echo "(BULUNAMADI: '".substr($needle,0,60)."')\n"; return; }
  echo "[@".$p."]\n".substr($s,max(0,$p-$b),$l)."\n";
}
function RALL($s,$needle,$b,$l,$lbl,$max=6){
  echo "\n──── $lbl (tum gecmeler, ilk $max) ────\n";
  if($s===false){ echo "(dosya yok)\n"; return; }
  $off=0;$n=0;
  while(($p=strpos($s,$needle,$off))!==false && $n<$max){
    echo "\n[@".$p." #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";
    $off=$p+strlen($needle); $n++;
  }
  if($n===0) echo "(BULUNAMADI: '".substr($needle,0,60)."')\n";
}

echo "############ 1) DOGENERATE / BELGE URETIM BUTONU (index.php) ############\n";
foreach(['Dokument generieren','generateDoc','doGenerate','function generate','onclick','wait','bekleyin','Erstelle','loading','spinner'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($idx,'Dokument generieren',-300,500,'\"Dokument generieren\" butonu HTML');
R($idx,'function doGenerate',0,1400,'doGenerate() govdesi (index.php tarafi)');
R($idx,'function generateDoc',0,1400,'generateDoc() (varsa)');

echo "\n\n############ 2) BEKLEME/LOADING MEVCUT MU ############\n";
R($idx,'addMsg',-50,260,'addMsg imzasi (bekleme mesaji icin)');
R($idx,'.typing',-40,220,'.typing / yazma gostergesi (CSS)');

echo "\n\n############ 3) STAATLICHE FORM — ULKE KAPISI (form-engine.js + index.php) ############\n";
foreach(['staatlich','Staatlich','FORMS','formCatalog','Bundesland','openForm','renderForm','country','showForm','FORM_'] as $k){
  $p=($fe!==false?strpos($fe,$k):false); echo "  fe:'$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($fe,'Bundesland',-200,300,'form-engine Bundesland baglami');
R($idx,'staatlich',-200,400,'index.php staatliche giris/menu');
R($idx,'Staatliche',-200,400,'index.php Staatliche (buyuk)');
RALL($idx,'form-engine',-80,200,'form-engine.js nasil yukleniyor',4);

echo "\n\n############ 4) ANTRAGE / KAYDET-SAKLA (index.php) ############\n";
foreach(['Anträge','Antraege','meineAntr','saveAntr','behalten','ch_antr','localStorage','myApplications','savedDocs'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($idx,'Anträge',-150,500,'Antrage menu/bolum');
R($idx,'behalten',-250,400,'behalten (sakla) mantigi');

echo "\n\n############ 5) TARIH ALANI (takvim eklentisi icin) ############\n";
RALL($idx,'type="date"',-120,200,'mevcut date inputlari',5);
RALL($idx,'Tarih',-120,220,'Tarih etiketli alanlar (TR)',4);
R($idx,'SEYAHAT',-150,400,'seyahat/vize formu (ekran goruntusu)');

echo "\n\n############ 6) ONERILER (suggestions) — ceviri icin ############\n";
foreach(['suggest','Vorschlag','öneri','chip','quickReply','options'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($idx,'suggest',-100,400,'suggestions render');

echo "\n\n############ 7) UL / ULKE-DIL SENKRON (buton neden Almanca) ############\n";
R($idx,'ch_uilang',-150,300,'ch_uilang kullanimi');
R($idx,'function setCountry',0,700,'setCountry (dil senkronu var mi)');
R($idx,'ch_lm',-150,300,'ch_lm (dil manuel kilidi)');

echo "\n\n############ 8) API.PHP — URETIM PROMPTU (kalite/karakter/format) ############\n";
R($api,'CH_QUALITY_PROMPT',-600,900,'mevcut kalite kurallari blogu');
R($api,'You are',-50,500,'sistem promptu basi');
R($api,'RULES',-50,600,'RULES blogu');

echo "\n\n############ 9) TEMA / RENK DEGISKENLERI (#11 daha acik premium antrasit) ############\n";
R($idx,':root{',0,900,':root CSS degiskenleri');
RALL($idx,'--s1',-10,60,'--s1 tanimlari',4);
RALL($idx,'--bg',-10,60,'--bg tanimlari',4);
R($idx,'body{',-10,400,'body arka plani');
RALL($idx,'background:#0',-40,90,'sabit koyu arka planlar (ornekler)',8);

echo "\n\n=== BITTI. Bu ciktinin TAMAMINI yapistir. SIL: rm dump-launch-prep.php ===\n";
