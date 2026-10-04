<?php
/**
 * ChatHelp — IP OTOMATİK ÜLKE TESPİTİ: TÜM MODÜLLÜ ÜLKELERE AÇ
 * ---------------------------------------------------------------
 *  İki durumu da halleder:
 *   a) CH_GEO_MULTI zaten ekli -> SUPPORTED listesi 13 ülkeye genişletilir
 *   b) CH_GEO_MULTI hiç eklenmemiş -> tam blok, genişletilmiş listeyle enjekte edilir
 *  Not: setCountry yalnız modülü OLAN ülkeler için çağrılır (boş katalog riski yok).
 *
 * KULLANIM: pull-updates.php?files=apply-geo-all.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-geo-all BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_GEO_ALL")!==false) exit("Zaten ekli (CH_GEO_ALL).\n");

$newList = 'var SUPPORTED = ["FR","TR","ES","CH","GB","US","IT","NL","BE","AT","PL","SE","PT"]; /*CH_GEO_ALL*/';

if(strpos($src,"CH_GEO_MULTI")!==false){
  /* a) mevcut listeyi genişlet */
  $oldList = 'var SUPPORTED = ["FR","TR"];';
  if(strpos($src,$oldList)===false){
    exit("CH_GEO_MULTI var ama SUPPORTED anchor farkli — dokunulmadi. Satiri bana gonder.\n");
  }
  file_put_contents($file.".bak-geoall-".date("Ymd-His"),$src);
  $src=str_replace($oldList,$newList,$src);
  file_put_contents($file,$src);
  echo "SUPPORTED listesi genisletildi: FR,TR,ES,CH,GB,US,IT,NL,BE,AT,PL,SE,PT\n";
} else {
  /* b) tam blok enjekte et */
  $anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
  if(strpos($src,$anchor)===false) exit("anchor yok\n");
  file_put_contents($file.".bak-geoall-".date("Ymd-His"),$src);
  $js = "\n\n/* CH_GEO_MULTI — genelleştirilmiş IP otomatik ülke tespiti */\n"
      . "try{(function(){\n"
      . "  ".$newList."\n"
      . "  function geoMulti(){\n"
      . "    try{\n"
      . "      if(localStorage.getItem(\"ch_lm\")) return;\n"
      . "      if(localStorage.getItem(\"ch_geo_multi\")) return;\n"
      . "      fetch(\"geo.php\",{cache:\"no-store\"}).then(function(r){return r.json();}).then(function(d){\n"
      . "        try{ localStorage.setItem(\"ch_geo_multi\",\"1\"); }catch(e){}\n"
      . "        if(d && d.country && SUPPORTED.indexOf(d.country)>=0\n"
      . "           && (typeof CC===\"undefined\" || CC!==d.country)\n"
      . "           && typeof setCountry===\"function\"){\n"
      . "          setCountry(d.country);\n"
      . "        }\n"
      . "      }).catch(function(){});\n"
      . "    }catch(e){}\n"
      . "  }\n"
      . "  setTimeout(geoMulti, 120);\n"
      . "})();}catch(e){}\n";
  $src=str_replace($anchor,$anchor.$js,$src);
  file_put_contents($file,$src);
  echo "CH_GEO_MULTI tam blok eklendi (13 ulke destekli).\n";
}
echo "\nSIL: rm apply-geo-all.php\n";
echo "Test: desteklenen ulke VPN'i ile gizli pencerede ac -> otomatik o ulkenin dili+katalogu.\n";
