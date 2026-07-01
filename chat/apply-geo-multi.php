<?php
/**
 * ChatHelp — Genelleştirilmiş IP otomatik ülke/dil tespiti (FR-only sınırını kaldırır)
 * -----------------------------------------------------------------------------------
 *  Mevcut CH_FR_FIX içindeki frGeo() SADECE 'FR' kontrolü yapıyordu — Türkiye (veya
 *  gelecekte eklenecek başka ülkeler) IP'den otomatik algılanmıyordu.
 *  Bu yama geo.php'yi bir kez sorgular, dönen ülke DESTEKLENEN listede ise
 *  (şu an: FR, TR) otomatik setCountry(cc) çağırır -> dil + hukuk + kategori filtresi
 *  otomatik değişir. Desteklenmeyen bir ülke dönerse (henüz modülü olmayan) DOKUNMAZ,
 *  varsayılan Almanca kalır (boş kategori listesi riski önlenir).
 *
 * KULLANIM: chat-help.com/chat/apply-geo-multi.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-geo-multi BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_GEO_MULTI")!==false) exit("Zaten ekli (CH_GEO_MULTI).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-geomulti-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_GEO_MULTI — genelleştirilmiş IP otomatik ülke tespiti (FR-only sınırını kaldırır) */
try{(function(){
  /* Modülü hazır olan ülkeler — yeni ülke eklenince buraya kod eklenecek */
  var SUPPORTED = ["FR","TR"];

  function geoMulti(){
    try{
      if(localStorage.getItem("ch_lm")) return;         // kullanıcı dili elle seçtiyse dokunma
      if(localStorage.getItem("ch_geo_multi")) return;   // sadece bir kez tespit
      fetch("geo.php",{cache:"no-store"}).then(function(r){return r.json();}).then(function(d){
        try{ localStorage.setItem("ch_geo_multi","1"); }catch(e){}
        if(d && d.country && SUPPORTED.indexOf(d.country)>=0
           && (typeof CC==="undefined" || CC!==d.country)
           && typeof setCountry==="function"){
          setCountry(d.country);
        }
      }).catch(function(){});
    }catch(e){}
  }

  setTimeout(geoMulti, 120);
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Genellestirilmis IP tespiti eklendi (CH_GEO_MULTI) -> FR ve TR IP'den otomatik dil/hukuk degisir.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Sadece SUPPORTED listesindeki ulkeler icin otomatik gecis yapar (bos kategori riski onlenir).\n";
echo "Yeni ulke modulu eklendikce SUPPORTED listesine eklenmesi yeterli.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-geo-multi.php\n";
echo "Test: Turk IP/VPN ile gizli pencerede ac -> otomatik Turkce + Turkiye kategorileri gelmeli.\n";
