<?php
/** ChatHelp — dump-scripttags (SADECE OKUR) — script etiket "dengesizligi" kaniti
 *  checkall '<script' sayisi ile '</script>' sayisinin esit olmadigini soyledi.
 *  Bu dump iyi-bicimli <script ...>...</script> ciftlerini soyup GERIYE KALAN
 *  her '<script' / '</script>' gecisini baglamiyla gosterir. Kalanlar bir JS
 *  string'inin icindeyse sorun YOKTUR (sayac yanilgisi). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

$o=substr_count($idx,'<script');
$c=substr_count($idx,'</script>');
echo "toplam: <script=$o  </script>=$c  fark=".($o-$c)."\n\n";

/* iyi-bicimli ciftleri soyup kalanlara bak */
$stripped=preg_replace('#<script\b[^>]*>.*?</script>#s','',$idx);
$ro=substr_count($stripped,'<script');
$rc=substr_count($stripped,'</script>');
echo "cift-soyma sonrasi kalan: <script=$ro  </script>=$rc\n";
echo "(kalan 0/0 ise tum bloklar saglam demektir; asagidakiler string icindeki gecislerdir)\n\n";

echo "──── kalan '<script' gecisleri (baglamli) ────\n";
$off=0;$n=0;
while(($p=strpos($stripped,'<script',$off))!==false && $n<12){
    echo "[#$n] …".str_replace("\n"," ⏎ ",substr($stripped,max(0,$p-100),240))."…\n\n";
    $off=$p+7;$n++;
}
if(!$n) echo "(yok)\n";

echo "──── kalan '</script>' gecisleri (baglamli) ────\n";
$off=0;$n=0;
while(($p=strpos($stripped,'</script>',$off))!==false && $n<12){
    echo "[#$n] …".str_replace("\n"," ⏎ ",substr($stripped,max(0,$p-100),240))."…\n\n";
    $off=$p+9;$n++;
}
if(!$n) echo "(yok)\n";

/* ayrica: sayfa sonu saglam mi — son 400 bayt */
echo "──── dosyanin SON 300 bayti ────\n".substr($idx,-300)."\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
