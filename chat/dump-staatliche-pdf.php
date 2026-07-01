<?php
/** ChatHelp — dump-staatliche-pdf: Staatliche Formulare PDF üretim akışı + zorunlu alan kontrolü (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=6000){
  echo "\n──────── $lbl ────────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  $body=substr($s,$p,min($i-$p,$cap));
  echo $body.(($i-$p)>$cap?"\n…(kırpıldı)":"")."\n";
}
function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) STAATLICHE_FORMS veri yapısı (alanlar zorunlu mu işaretli?) ######\n";
$p=strpos($idx,'STAATLICHE_FORMS');
echo $p===false ? "(BULUNAMADI)\n" : "VAR @$p\n";
WIN($idx,'STAATLICHE_FORMS=',-20,1800,'STAATLICHE_FORMS tanımı başlangıcı (alan yapısı görmek için)');

echo "\n\n###### 2) PDF üretim fonksiyonu / tetikleyici buton ######\n";
foreach(['jsPDF','generatePDF','exportPDF','downloadPDF','erstellePDF','PDF erstellen','pdf-btn','function pdf','.pdf(','doc.save'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
FX($idx,'function generatePDF','generatePDF (varsa)');
FX($idx,'function exportPDF','exportPDF (varsa)');
FX($idx,'function erstellePDF','erstellePDF (varsa)');
FX($idx,'function downloadPDF','downloadPDF (varsa)');
FX($idx,'function pdfDownload','pdfDownload (varsa)');

echo "\n\n###### 3) Staatliche form doldurma ekranı / zaten var olan bir doğrulama var mı ######\n";
foreach(['function showStaatForm','function openStaatForm','function renderStaatForm','function fillForm','requiredFields','r:true','required:true','.required','Pflichtfeld','ausfüllen'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
FX($idx,'function showStaatForm','showStaatForm (varsa)');
FX($idx,'function openStaatForm','openStaatForm (varsa)');
FX($idx,'function renderStaatForm','renderStaatForm (varsa)');
FX($idx,'function fillForm','fillForm (varsa)');

echo "\n\n###### 4) 'Staatliche' ilk geçiş bağlamı (genel yapıyı görmek için) ######\n";
WIN($idx,'Staatliche Formulare',-100,1200,'\"Staatliche Formulare\" bağlamı 1');

echo "\n=== BİTTİ. SİL: rm dump-staatliche-pdf.php ===\n";
