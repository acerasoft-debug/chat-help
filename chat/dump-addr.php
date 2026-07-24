<?php
/**
 * ChatHelp — dump-addr — profil/adres depolama + cok-kullanici ayrimi + kalicilik (OKUR):
 *  ch_prof_v3 / ch_profiles / ch_prof_active save/load/switch, sync guard, hydrate,
 *  kullanici-bagli anahtar (kullanicilar arasi karisma riski).
 * KULLANIM: pull2.php?key=...&files=dump-addr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-addr (".number_format(strlen($src))." B) ==\n\n";

/* depolama anahtarlari */
echo "=== [A] profil/adres anahtarlari (kullanim sayisi) ===\n";
foreach(['ch_prof_v3','ch_pref_v3','ch_profiles','ch_prof_active','ch_user','ch_addr','ch_prof_'] as $k){
  echo "  '$k': ".substr_count($src,$k)." kez\n";
}

/* save/load/switch fonksiyonlari + marker'lar */
echo "\n=== [B] save/load/switch/hydrate + CH_ADDR_* markerlari ===\n";
foreach(['CH_ADDR_SYNCGUARD','CH_ADDR_SAVE2','CH_ADDR_HYDRATE','CH_NOCROSS','function saveProf','function loadProf','function hydrate','function setActiveProf','function switchProf','saveProfile','activeProfile'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-40),200))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  [$pat]: yok\n";
}

/* sync interval — profili bos snapshot ile ezme riski */
echo "\n=== [C] profil yazan setInterval / senkron (ezme riski) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_prof',$off))!==false && $n<40){
  $win=substr($src,max(0,$p-260),320);
  if(preg_match('/setItem|JSON\.stringify|setInterval/',$win) && preg_match('/setItem\([\'"]ch_prof/',$win)){
    echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-160),300))."...\n\n";
    $n++;
  }
  $off=$p+7;
}
echo "  (yukarida profil YAZAN yerler; bos snapshot ile ezme kalicilik sorunudur)\n";

/* multi-profile array yonetimi */
echo "\n=== [D] ch_profiles dizi yonetimi (ekle/sec/kaydet) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_profiles',$off))!==false && $n<8){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-90),230))."...\n";
  $off=$p+11; $n++;
}
echo "\n== BITTI ==\n";
