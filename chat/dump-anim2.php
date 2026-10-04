<?php
/* dump-anim2 (READ-ONLY) — surekli(infinite) + dekoratif animasyonlarin BAGLI
   OLDUGU SELECTOR'leri cikarir; boylece 'sakin' gecisinde neyi kapatacagimizi
   kesin biliriz (skeleton-loader gibi islevsel olanlari bozmadan).
   KULLANIM: pull2.php?key=...&files=dump-anim2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-anim2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

/* tum <style> bloklarini birlestir (kaba selector cikarimi icin) */
echo "=== INFINITE animasyon kullanan CSS kurallari (selector + decl) ===\n";
if(preg_match_all('/([#.\w\-\[\]="\':\s,>()*+~]{1,120}?)\{([^{}]*animation[^{}]*infinite[^{}]*)\}/i',$s,$m,PREG_SET_ORDER)){
  $seen=[];
  foreach($m as $x){
    $sel=trim(preg_replace('/\s+/',' ',$x[1]));
    $decl=trim(preg_replace('/\s+/',' ',$x[2]));
    $key=$sel.'|'.$decl;
    if(isset($seen[$key])) continue; $seen[$key]=1;
    echo "  SEL: $sel\n    { $decl }\n";
  }
} else echo "  (bulunamadi)\n";

echo "\n=== Dekoratif keyframe adlari + kullanan yerin baglami ===\n";
foreach(['chHeroLaser','chHeroLaser2','scanshine','shimmer','chBreathe','tbpulse','pulse','chAfGlow'] as $kf){
  $n=preg_match_all('/animation[^;{}]*\b'.preg_quote($kf,'/').'\b/i',$s,$mm);
  echo "  $kf: $n kullanim\n";
}

echo "\n=== .wlc-scan / hero / ::before-::after animasyonlu parcalar ===\n";
if(preg_match_all('/([#.\w\-]+(?:::?(?:before|after))?)\s*\{[^{}]*animation\s*:[^{}]*\}/i',$s,$m,PREG_SET_ORDER)){
  $i=0; $seen=[];
  foreach($m as $x){
    if(stripos($x[0],'infinite')===false) continue;
    $sel=trim($x[1]); if(isset($seen[$sel]))continue; $seen[$sel]=1;
    if($i++>=40) break;
    echo "  $sel\n";
  }
}
echo "\n=== prefers-reduced-motion zaten var mi? ===\n";
echo "  ".preg_match_all('/prefers-reduced-motion/i',$s)." adet\n";
echo "\nBITTI.\n";
