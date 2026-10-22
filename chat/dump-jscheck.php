<?php
/**
 * ChatHelp — dump-jscheck (SADECE OKUR) — ACIL: "acilinca tum arayuz gizleniyor".
 *  Amac: index.php icindeki inline <script> bloklarindan HANGISI parse hatasi
 *  veriyor bul (bir JS hatasi tum render'i durdurur). node varsa node --check;
 *  yoksa parantez/suslu/koseli DENGE kontrolu (string-farkinda degil, kaba) +
 *  son deploy markerlarinin bulundugu blok(lar)i isaretler. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-jscheck.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-jscheck — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* node binary? */
$node='';
foreach(['node','nodejs','/usr/bin/node','/usr/local/bin/node'] as $c){
  $o=[];$rc=1; @exec(escapeshellarg($c).' --version 2>/dev/null',$o,$rc);
  if($rc===0 && !empty($o)){ $node=$c; break; }
}
echo "[node] ".($node?("VAR: $node ".($o[0]??'')):"YOK (denge kontrolune dusulecek)")."\n\n";

/* inline <script> bloklarini cikar (src'li olanlari atla) */
$blocks=[];
if(preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is',$src,$mm,PREG_OFFSET_CAPTURE)){
  for($i=0;$i<count($mm[0]);$i++){
    $attrs=$mm[1][$i][0]; if(stripos($attrs,'src=')!==false) continue;
    $code=$mm[2][$i][0]; $off=$mm[0][$i][1];
    $id=''; if(preg_match('/id\s*=\s*["\']([^"\']+)["\']/',$attrs,$idm)) $id=$idm[1];
    $blocks[]=['id'=>$id,'code'=>$code,'off'=>$off,'len'=>strlen($code)];
  }
}
echo "[scripts] ".count($blocks)." inline <script> blogu\n\n";

/* son deploy markerlari */
$MK=['CH_DE_MINIJOB','CH_ESFR_VERTRAG','CH_ITGB_VERTRAG','CH_ATCH_VERTRAG2','CH_VIZE_UX','nc_minijob','vtr_es_compraventa','vtr_it_vendita','vtr_at_scheidung','300+'];

function bal($s){ // string-farkinda degil: yine de imbalans buyuk ipucu
  $b=0;$p=0;$k=0;$len=strlen($s);
  for($i=0;$i<$len;$i++){ $c=$s[$i];
    if($c==='{')$b++; elseif($c==='}')$b--;
    elseif($c==='(')$p++; elseif($c===')')$p--;
    elseif($c==='[')$k++; elseif($c===']')$k--;
  }
  return "{}=$b ()=$p []=$k";
}

$bad=[];
foreach($blocks as $n=>$bk){
  $mks=[]; foreach($MK as $m){ if(strpos($bk['code'],$m)!==false) $mks[]=$m; }
  $tag=$mks?(" ← markerlar: ".implode(',',$mks)):"";
  $status='';
  if($node){
    $tmp=tempnam(sys_get_temp_dir(),'js').'.js';
    file_put_contents($tmp,$bk['code']);
    $o=[];$rc=0; @exec(escapeshellarg($node).' --check '.escapeshellarg($tmp).' 2>&1',$o,$rc);
    @unlink($tmp);
    if($rc===0){ $status='OK'; }
    else { $status='HATA: '.implode(' | ',array_slice($o,0,4)); $bad[]=$n; }
  } else {
    $b=bal($bk['code']); $status="denge[$b]";
    if(preg_match('/=-?[1-9]/',str_replace(['=0'],['=OK'],$b))) $bad[]=$n;
  }
  /* sadece markerli VEYA hatali bloklari yazdir (cikti kisa kalsin) */
  if($mks || strpos($status,'HATA')!==false || (isset($b)&&strpos($b,'=0 ()=0 []=0')===false && $mks)){
    echo sprintf("  #%-2d id=%-22s len=%-7d %s%s\n", $n, ($bk['id']?:'(yok)'), $bk['len'], $status, $tag);
  }
}

/* node yoksa: TUM bloklarin denge ozetini de ver (imbalansli olanlar) */
if(!$node){
  echo "\n[denge] SIFIR-olmayan (imbalansli) bloklar:\n";
  foreach($blocks as $n=>$bk){
    $b=bal($bk['code']);
    if(strpos($b,'=0 ()=0 []=0')===false){
      echo sprintf("  #%-2d id=%-22s len=%-7d %s\n", $n, ($bk['id']?:'(yok)'), $bk['len'], $b);
    }
  }
}

echo "\n[ozet] Supheli blok index'leri: ".($bad?implode(',',$bad):'(yok)')."\n";
echo "\n=== BITTI (salt-okunur) ===\n";
