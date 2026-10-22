<?php
/**
 * ChatHelp — ÜLKE SEÇİCİYE TÜRKİYE KUTUSU (dump-country-picker teşhisine dayalı kesin düzeltme)
 * ----------------------------------------------------------------------------------------------
 *  Teşhis: ülke seçici ızgarası (#cg) CC_DATA'dan üretilmiyor — 12 ülke SABİT HTML
 *  (DE, AT, CH, FR, IT, ES, NL, BE, PL, SE, PT, EU) ve Türkiye hiç yazılmamış.
 *  setCountry('TR') çalışıyor ama tıklanacak kutu yok.
 *
 *  Düzeltme (CH_TR_PICKER):
 *   1) #cg ızgarasına EU kutusundan önce 🇹🇷 Türkiye kutusu eklenir (aynı sabit HTML deseni)
 *   2) Statik CC_DATA tanımına TR satırı eklenir (EU satırından önce) — böylece bayrak,
 *      hukuk satırı ("TR · TBK, İş K., TMK") ve dil anında doğru; CH_TR_MODULE'ün
 *      runtime eklemesi (if(!CC_DATA.TR)) buna saygı duyar, çakışma olmaz.
 *
 * KULLANIM: pull-updates.php?files=apply-turkiye-picker.php&run=1  -> opcache-reset otomatik. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-picker BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_TR_PICKER")!==false) exit("Zaten ekli (CH_TR_PICKER).\n");

$done=0;

/* ---- 1) Ülke seçici ızgarasına TR kutusu (EU kutusundan önce) ---- */
$gridAnchor = <<<'A'
<div class="co" data-cc="EU" onclick="setCountry('EU')">
A;
$trTile = <<<'A'
<div class="co" data-cc="TR" onclick="setCountry('TR')"><div class="co-flag">&#x1F1F9;&#x1F1F7;</div><div class="co-n">T&#252;rkiye</div></div><!--CH_TR_PICKER-->
          <div class="co" data-cc="EU" onclick="setCountry('EU')">
A;
if(strpos($src,$gridAnchor)!==false){
  $src=str_replace($gridAnchor,$trTile,$src);
  echo "1) Ulke secicide TR kutusu eklendi (EU'dan once). OK\n"; $done++;
} else {
  echo "1) HATA: ulke secici EU anchor bulunamadi — TR kutusu EKLENMEDI.\n";
}

/* ---- 2) Statik CC_DATA'ya TR satırı (EU satırından önce) ---- */
$ccAnchor = <<<'A'
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
A;
$ccNew = <<<'A'
  TR:{n:'T\u00fcrkiye',f:'\u{1F1F9}\u{1F1F7}',l:'tr',law:'TBK, \u0130\u015f K., TMK'},
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
A;
if(strpos($src,$ccAnchor)!==false){
  $src=str_replace($ccAnchor,$ccNew,$src);
  echo "2) CC_DATA'ya statik TR kaydi eklendi (bayrak+dil+hukuk aninda dogru). OK\n"; $done++;
} else {
  echo "2) UYARI: CC_DATA EU anchor bulunamadi — statik TR kaydi eklenmedi.\n";
  echo "   (Kritik degil: CH_TR_MODULE runtime'da CC_DATA.TR ekliyor, secici yine calisir.)\n";
}

if($done===0) exit("\nHicbir degisiklik yapilamadi — index.php dokunulmadan birakildi.\n");

file_put_contents($file.".bak-trpicker-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "\nindex.php guncellendi ($done/2 yama). Yedek: index.php.bak-trpicker-*\n";
echo "SIL: rm apply-turkiye-picker.php\n";
echo "Test: Ayarlar/Konto -> ulke secici -> 🇹🇷 Turkiye kutusu gorunmeli (PT ile Europe arasinda),\n";
echo "      tiklayinca arayuz Turkce + kategoriler Turkce + kenar cubugunda 'TR · TBK, Is K., TMK'.\n";
