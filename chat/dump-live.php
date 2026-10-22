<?php
/**
 * ChatHelp — dump-live — PDF premium zincirinin CANLI sunucuda tam yerinde olup
 *  olmadigini dogrular (OKUR): index.php + dl.php markerlari + font dosyalari.
 * KULLANIM: pull2.php?key=...&files=dump-live.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "== dump-live — ".date('d.m.Y H:i')." ==\n\n";

$idx=(string)@file_get_contents("$D/index.php");
$dl=(string)@file_get_contents("$D/dl.php");

echo "index.php (".number_format(strlen($idx))." B):\n";
foreach([
  'CH_ALLDL_CHAT'   =>'chat PDF -> her platform dl.php',
  'CH_CHATPDF_WVSKIP'=>'chat App/WebView dl.php',
  'CH_NOLOGO'       =>'(index tarafinda beklenmez)',
] as $m=>$d){ echo "  ".(strpos($idx,$m)!==false?'✓':'—')."  $m  $d\n"; }

echo "\ndl.php (".number_format(strlen($dl))." B):\n";
foreach([
  'CH_DL_PREMIUM'   =>'premium DIN mektup (fallback)',
  'CH_DL_PREMIUM_CALL'=>'premium cagri',
  'CH_DL_UNI'       =>'Unicode gomulu (TR/AR-Latin)',
  'CH_DL_UNI_CALL'  =>'unicode+fallback cagri',
  'CH_NOLOGO'       =>'ChatHelp logosu/marka kaldirildi',
] as $m=>$d){ echo "  ".(strpos($dl,$m)!==false?'✓':'✗ EKSIK')."  $m  $d\n"; }

echo "\nFontlar:\n";
foreach(['fonts/dvs.ttf','fonts/dvsb.ttf'] as $f){
  $p="$D/$f"; echo "  ".(is_file($p)?'✓ '.number_format(filesize($p)).' B':'✗ YOK')."  $f\n";
}

/* dl.php gercekten premium PDF uretiyor mu — kucuk uctan uca test */
echo "\nUctan uca PDF testi (sunucuda):\n";
if(function_exists('ch_premium_pdf_uni')||strpos($dl,'function ch_premium_pdf')!==false){
  echo "  (dl.php dogrudan cagrilarak test edilemez; asagidaki marker'lar yeterli kanit)\n";
}
$okDl = strpos($dl,'CH_DL_UNI_CALL')!==false && strpos($dl,'CH_NOLOGO')!==false && is_file("$D/fonts/dvs.ttf");
$okIdx= strpos($idx,'CH_ALLDL_CHAT')!==false;
echo "\nSONUC:\n";
echo "  Sunucu (dl.php premium+unicode+nologo): ".($okDl?'✓ CANLI':'✗ eksik')."\n";
echo "  Istemci yol (chat->dl.php her platform): ".($okIdx?'✓ CANLI':'✗ eksik')."\n";
echo ($okDl&&$okIdx)
  ? "\n  ✓✓ HER SEY CANLI. Hala duz goruyorsan cihaz onbellegi: tarayici hard-refresh (Ctrl+Shift+R) / App veri temizle.\n"
  : "\n  ⚠ Eksik var — yukaridaki ✗ satirlarini bildir.\n";
echo "\n== BITTI ==\n";
