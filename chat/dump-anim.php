<?php
/* dump-anim (READ-ONLY) — canli index.php'de titreme/reflow kaynaklarini teshis:
   @keyframes adlari, animation/transition/transform kullanimi, hover scale/translate,
   will-change, filter:blur gecisleri. Sadece RAPOR — hicbir sey degistirmez.
   KULLANIM: pull2.php?key=...&files=dump-anim.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-anim (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
echo "index.php: ".strlen($s)." B\n\n";

function cnt($s,$re){ return preg_match_all($re,$s,$m); }
echo "=== SAYIMLAR ===\n";
echo "  @keyframes        : ".cnt($s,'/@keyframes\s+[\w-]+/i')."\n";
echo "  animation:        : ".cnt($s,'/[^-]animation\s*:/i')."\n";
echo "  animation-name    : ".cnt($s,'/animation-name\s*:/i')."\n";
echo "  transition:       : ".cnt($s,'/[^-]transition\s*:/i')."\n";
echo "  transform:        : ".cnt($s,'/[^-]transform\s*:/i')."\n";
echo "  will-change       : ".cnt($s,'/will-change\s*:/i')."\n";
echo "  filter:blur       : ".cnt($s,'/filter\s*:\s*blur/i')."\n";
echo "  backdrop-filter   : ".cnt($s,'/backdrop-filter\s*:/i')."\n";
echo "  scale(            : ".cnt($s,'/scale\s*\(/i')."\n";
echo "  translateY/X      : ".cnt($s,'/translate[XY]?\s*\(/i')."\n";
echo "  position:sticky   : ".cnt($s,'/position\s*:\s*sticky/i')."\n";

echo "\n=== @keyframes ADLARI ===\n";
if(preg_match_all('/@keyframes\s+([\w-]+)/i',$s,$m)) foreach(array_unique($m[1]) as $k) echo "  - $k\n";

echo "\n=== hover'da transform/scale (reflow suphelileri) ===\n";
if(preg_match_all('/([.#][\w-]+(?:[:\s>][^\{]{0,40})?):hover\s*\{[^}]*?(transform|scale|translate)[^}]*\}/i',$s,$m,PREG_SET_ORDER)){
  $i=0; foreach($m as $x){ if($i++>=25) break; echo "  ".trim(preg_replace('/\s+/',' ',$x[0]))."\n"; }
} else echo "  (yok)\n";

echo "\n=== 'animation:' bildirimleri (ilk 30) ===\n";
if(preg_match_all('/[^-\w]animation\s*:\s*([^;}{]+)[;}]/i',$s,$m)){
  $u=array_unique(array_map('trim',$m[1])); $i=0;
  foreach($u as $a){ if($i++>=30) break; echo "  animation: ".preg_replace('/\s+/',' ',$a)."\n"; }
}

echo "\n=== CH_ marker'lari (uygulanan yamalar) ===\n";
if(preg_match_all('/CH_[A-Z0-9_]+/',$s,$m)){ $u=array_count_values($m[0]); ksort($u);
  foreach($u as $k=>$c) echo "  $k ($c)\n"; }
echo "\nBITTI.\n";
