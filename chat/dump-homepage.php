<?php
/**
 * ChatHelp — dump-homepage — ana sayfa TITREME + yeni-kullanici hata taramasi (OKUR):
 *  [A] DOM ekleyip/silen setInterval'ler (titremenin ana kaynagi) — aralik + baglam
 *  [B] MutationObserver kurulumlari
 *  [C] ana welcome/hero render fonksiyonu
 *  [D] yeni kullanici riskleri: profil/login varsayimi (null erisim)
 *  [E] periyodik 'sweep/reflow/prime/inject' fonksiyonlari
 * KULLANIM: pull2.php?key=...&files=dump-homepage.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-homepage (".number_format(strlen($src))." B) ==\n\n";

/* [A] setInterval + kisa aralikli + DOM mutasyonu iceren */
echo "=== [A] setInterval cagrilari (aralik + baglam) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'setInterval',$off))!==false && $n<60){
  $ctx=substr($src,$p,150);
  /* aralik degerini yakala */
  $ms='?'; if(preg_match('/setInterval\([^,]{1,40},\s*(\d+)/',substr($src,$p,220),$m)) $ms=$m[1];
  $before=substr($src,max(0,$p-70),70);
  $mut=(preg_match('/removeChild|appendChild|innerHTML|insertBefore|\.remove\(|style\.display/',substr($src,max(0,$p-400),620))?' [DOM!]':'');
  echo "  @$p ${ms}ms$mut: ...".str_replace(["\n","\r"],' ',$before.'>>'.substr($ctx,0,90))."...\n";
  $off=$p+11; $n++;
}
echo "  toplam setInterval: ".substr_count($src,'setInterval')."\n";

/* [B] MutationObserver */
echo "\n=== [B] MutationObserver kurulumlari ===\n";
$off=0;$n=0;
while(($p=strpos($src,'new MutationObserver',$off))!==false && $n<30){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-50),160))."...\n";
  $off=$p+15; $n++;
}
echo "  toplam: ".substr_count($src,'MutationObserver')."\n";

/* [C] hero/welcome render */
echo "\n=== [C] hero/welcome bloklari ===\n";
foreach(['wlc-','renderHome','function renderWelcome','buildHome','.hero','ch-cards'] as $pat){
  $p=strpos($src,$pat);
  echo "  '$pat': ".($p===false?'yok':"@$p")."\n";
}

/* [D] yeni kullanici riski */
echo "\n=== [D] yeni kullanici (profil/login) riskli erisimler ===\n";
foreach(['P.f1','P.plan','prof.f','ch_user','.plan','JSON.parse(localStorage'] as $pat){
  echo "  '$pat': ".substr_count($src,$pat)." kez\n";
}

/* [E] periyodik fonksiyon adlari */
echo "\n=== [E] sweep/reflow/prime/inject/tick fonksiyonlari ===\n";
foreach(['function sweep','function reflow','function prime','function inject','function tick','function stabil','function fixCards','function guard'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){ echo "  $pat @$p\n"; $off=$p+strlen($pat); $n++; }
}
echo "\n== BITTI ==\n";
