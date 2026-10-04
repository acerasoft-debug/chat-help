<?php
/* dump-kuendigen (READ-ONLY) — 'Verträge kündigen' (cat:vertraege) kategorisinin
   GERCEK icerigi: kuendigung_* (dogru fesih sablonlari) vs vtr_/vst_ (sizan tam
   sozlesmeler). Filtrenin nerede kacirdigi + kategori etiketi eslesmesi.
   KULLANIM: pull2.php?key=...&files=dump-kuendigen.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-kuendigen (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

/* 1) kategori etiketi + cat key */
echo "=== 'kündigen' etiketi baglami ===\n";
$off=0;$c=0;
while(($p=stripos($s,'kündigen',$off))!==false && $c<6){
  echo "  [".$p."] …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-70),160)))."…\n"; $off=$p+6;$c++; }

/* 2) doc key prefiksleri say + ornek isimler */
function keys($s,$pre){
  $out=[]; if(preg_match_all('/\bk:\s*[\'"]('.preg_quote($pre,'/').'[a-z0-9_]+)[\'"][^}]{0,80}?n:\s*[\'"]([^\'"]{0,50})/i',$s,$m,PREG_SET_ORDER)){
    foreach($m as $x){ $out[$x[1]]=$x[2]; } }
  /* n: k'den once de olabilir -> ikinci desen */
  if(preg_match_all('/n:\s*[\'"]([^\'"]{0,50})[\'"][^}]{0,80}?\bk:\s*[\'"]('.preg_quote($pre,'/').'[a-z0-9_]+)[\'"]/i',$s,$m2,PREG_SET_ORDER)){
    foreach($m2 as $x){ if(!isset($out[$x[2]])) $out[$x[2]]=$x[1]; } }
  return $out;
}
foreach(['kuendigung_','vtr_','vst_'] as $pre){
  $k=keys($s,$pre);
  echo "\n=== k:'".$pre."*' girdileri (".count($k).") — ham say: ".preg_match_all('/\bk:\s*[\'"]'.preg_quote($pre,'/').'/i',$s)." ===\n";
  $i=0; foreach($k as $key=>$nm){ if($i++>=25) break; echo "  ".$key." → ".$nm."\n"; }
}

/* 3) CAT_DOCS['vertraege'] insasi + push/merge */
echo "\n=== CAT_DOCS vertraege insasi / merge ===\n";
foreach(["CAT_DOCS['vertraege']","CAT_DOCS[\"vertraege\"]","CAT_DOCS.vertraege","vertraege:"] as $k){
  $off=0;$c=0; while(($p=strpos($s,$k,$off))!==false && $c<5){
    echo "  [".$p."] …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-30),150)))."…\n"; $off=$p+strlen($k);$c++; }
}

/* 4) CH_CARDS_KAT_FIX filtresi ne ayikliyor? */
echo "\n=== CH_CARDS_KAT_FIX filtre satiri ===\n";
$p=strpos($s,'CH_CARDS_KAT_FIX');
if($p!==false){ $q=strpos($s,"indexOf('vtr_",$p); if($q===false)$q=strpos($s,'indexOf("vtr_',$p);
  if($q!==false) echo "  …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$q-80),160)))."…\n";
  else echo "  (vtr_ filtresi bulunamadi — belki baska prefix)\n";
}
/* vst_ da cat:vertraege mi? */
echo "\n=== vst_/vtr_ hangi cat'e etiketli? ===\n";
foreach(['vst_','vtr_'] as $pre){
  if(preg_match('/\bk:\s*[\'"]'.$pre.'[a-z0-9_]+[\'"][^}]{0,160}?cat:\s*[\'"]([a-z_]+)/i',$s,$m)) echo "  ".$pre."* → cat:'".$m[1]."'\n";
  else echo "  ".$pre."* → cat bulunamadi (ayri yapida)\n";
}
echo "\nBITTI.\n";
