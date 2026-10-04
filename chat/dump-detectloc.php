<?php
/** ChatHelp — dump-detectloc (SADECE OKUR) — F2-1 son parca:
 *  detectLoc() -> api.php?action=detect_location TAM govdesi. detectLoc()
 *  donen d.country'yi HICBIR beyaz liste olmadan dogrudan CC'ye yaziyor
 *  (geoMulti'nin aksine). Bu uc nokta LU/RU/desteklenmeyen bir ISO kodu
 *  dondurursen hayalet-ulke riski gercek olur. Tam govdeyi goster. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$api=(string)@file_get_contents("$D/api.php");
if($api==='') exit("api.php okunamadi\n");

/* match/switch icindeki 'detect_location' kolunun govdesini bul */
$p=strpos($api,"'detect_location'");
if($p===false){ echo "detect_location anahtar kelimesi yok\n"; exit; }
echo "──── 'detect_location' cevresi (genis baglam) ────\n";
echo substr($api,max(0,$p-100),700)."\n";

/* muhtemel isimli fonksiyonlari genis ara */
foreach(['function doLoc','function detectLocation','function geoLocation','GeoIP','ipinfo','ip-api'] as $needle){
    $q=strpos($api,$needle);
    echo "\n──── '$needle' ────\n";
    echo $q!==false ? substr($api,max(0,$q-60),500)."\n" : "(YOK)\n";
}
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
