<?php
/** ChatHelp — dump-render: kategori render + bayrak/dil/ülke UI'sini TAM çeker (parantez eşlemeli) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

/* fonksiyon gövdesini { } eşleyerek tam çek */
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
/* obje literali { } eşleyerek tam çek (CAT_DOCS= gibi) */
function OBJ($s,$name,$lbl,$cap=4000){
  echo "\n──────── $lbl ────────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kırpıldı)":"")."\n";
}
function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}

echo "###### KATEGORİ RENDER FONKSİYONLARI (TAM) ######\n";
FX($idx,'function showAllCats','showAllCats');
FX($idx,'function showCat(','showCat');
FX($idx,'function showCatDocs','showCatDocs');

echo "\n\n###### CAT_DOCS / CAT_LABELS (TAM) ######\n";
OBJ($idx,'CAT_LABELS=','CAT_LABELS');
OBJ($idx,'CAT_DOCS=','CAT_DOCS', 6000);

echo "\n\n###### WELCOME GRID + 'documents/Dokumente' sayım ######\n";
WIN($idx,'wcat-grid',-60,1500,'.wcat-grid HTML');
WIN($idx,'Dokumente</',-200,260,'\"Dokumente\" sayım yeri 1');
WIN($idx,'documents',-200,160,'\"documents\" sayım yeri');

echo "\n\n###### BAYRAK / DİL / ÜLKE UI (profildeki 2-3 bayrak) ######\n";
WIN($idx,'lang-opt',-300,900,'lang-opt (dil seçenekleri)');
WIN($idx,'sb-country',-80,700,'sb-country (ülke seçici sidebar)');
FX($idx,'function setFlag','setFlag');
WIN($idx,'tb-flag',-200,300,'tb-flag (üst bar bayrak)');
WIN($idx,'k-cc',-200,300,'k-cc (profil ülke)');
WIN($idx,'🇫🇷',-120,160,'🇫🇷 ilk geçiş (bayrak nerede)');

echo "\n=== BİTTİ. SİL: rm dump-render.php ===\n";
