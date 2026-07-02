<?php
/**
 * ChatHelp — ÜLKE SEÇİCİYE İNGİLTERE + ABD KUTULARI ve statik CC_DATA kayıtları
 * (apply-turkiye-picker ile aynı kanıtlanmış anchor'lar; EU her iki yerde de en sonda kalır)
 * KULLANIM: pull-updates.php?files=apply-more-countries-picker.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-more-countries-picker BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_GBUS_PICKER")!==false) exit("Zaten ekli (CH_GBUS_PICKER).\n");

$done=0;

/* ---- 1) Seçici ızgarasına GB + US kutuları (EU kutusundan önce) ---- */
$gridAnchor = <<<'A'
<div class="co" data-cc="EU" onclick="setCountry('EU')">
A;
$tiles = <<<'A'
<div class="co" data-cc="GB" onclick="setCountry('GB')"><div class="co-flag">&#x1F1EC;&#x1F1E7;</div><div class="co-n">United Kingdom</div></div><!--CH_GBUS_PICKER-->
          <div class="co" data-cc="US" onclick="setCountry('US')"><div class="co-flag">&#x1F1FA;&#x1F1F8;</div><div class="co-n">United States</div></div>
          <div class="co" data-cc="EU" onclick="setCountry('EU')">
A;
if(strpos($src,$gridAnchor)!==false){
  $src=str_replace($gridAnchor,$tiles,$src);
  echo "1) Secicide GB + US kutulari eklendi (EU'dan once). OK\n"; $done++;
} else {
  echo "1) HATA: secici EU anchor bulunamadi — kutular EKLENMEDI.\n";
}

/* ---- 2) Statik CC_DATA'ya GB + US (EU satirindan once) ---- */
$ccAnchor = <<<'A'
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
A;
$ccNew = <<<'A'
  GB:{n:'United Kingdom',f:'\u{1F1EC}\u{1F1E7}',l:'en',law:'England & Wales'},
  US:{n:'United States',f:'\u{1F1FA}\u{1F1F8}',l:'en',law:'US law (state-specific)'},
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
A;
if(strpos($src,$ccAnchor)!==false){
  $src=str_replace($ccAnchor,$ccNew,$src);
  echo "2) CC_DATA'ya GB + US eklendi. OK\n"; $done++;
} else {
  echo "2) HATA: CC_DATA EU anchor bulunamadi — GB/US kayitlari eklenmedi.\n";
}

if($done===0) exit("\nHicbir degisiklik yapilamadi — index.php dokunulmadi.\n");

file_put_contents($file.".bak-gbus-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "\nindex.php guncellendi ($done/2). Yedek: index.php.bak-gbus-*\n";
echo "SIL: rm apply-more-countries-picker.php\n";
echo "Test: secicide United Kingdom + United States kutulari gorunmeli; tiklaninca Ingilizce katalog.\n";
