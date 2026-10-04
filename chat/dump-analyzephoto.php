<?php
/**
 * ChatHelp — dump-analyzephoto — foto analizi "sunucu bos yanit" teshisi.
 *  Canli api.php icindeki 'analyze_photo' islenisini + kullanilan vision
 *  model/endpoint'i gosterir (API anahtarlari MASKELENIR). Ayrica config'te
 *  ilgili anahtarlarin VAR/YOK oldugunu (deger gostermeden) bildirir.
 *  SADECE OKUR — hicbir sey degistirmez.
 * KULLANIM: pull2.php?key=...&files=dump-analyzephoto.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-analyzephoto ==\n\n";

function mask($s){
  // uzun anahtar benzeri dizileri maskele
  $s=preg_replace('/sk-[A-Za-z0-9_\-]{10,}/','sk-***MASKED***',$s);
  $s=preg_replace('/(["\']?[A-Za-z0-9_\-]{28,}["\']?)/', '***MASKED***', $s);
  return $s;
}

$api=__DIR__.'/api.php';
$src=@file_get_contents($api);
if($src===false){ echo "api.php OKUNAMADI\n"; }
else{
  echo "api.php boyut: ".strlen($src)." B\n";
  // analyze_photo gecen satirlarin indexini bul, ilk anlamli blogu bas
  $pos=strpos($src,'analyze_photo');
  if($pos===false){ echo "!! 'analyze_photo' api.php'de YOK — action baska yerde olabilir\n"; }
  else{
    $start=max(0,$pos-200);
    $chunk=substr($src,$start,4200);
    echo "\n--- analyze_photo civari (maskeli) ---\n".mask($chunk)."\n--- son ---\n";
  }
  // hangi vision endpoint/model?
  foreach(['api.anthropic.com','claude-','deepseek','api.openai.com','gpt-','vision','messages','model'] as $kw){
    $c=substr_count($src,$kw);
    if($c>0) echo "  api.php icinde \"$kw\": $c kez\n";
  }
}

echo "\n== config.php anahtar VAR/YOK ==\n";
$cfg=@file_get_contents(__DIR__.'/config.php');
if($cfg===false){ echo "config.php okunamadi\n"; }
else{
  foreach(['ANTHROPIC','CLAUDE','DEEPSEEK','OPENAI','API_KEY','VISION','MODEL'] as $k){
    // sadece tanim var mi — degeri gosterme
    if(preg_match('/[A-Z0-9_]*'.$k.'[A-Z0-9_]*/',$cfg,$m)){
      $name=$m[0];
      $has=preg_match('/'.preg_quote($name,'/').'\s*[,=]\s*[\'"][^\'"]{6,}/',$cfg);
      echo "  $name: ".($has?"TANIMLI (dolu)":"tanimli ama bos/kisa?")."\n";
    }
  }
}
echo "\n== BITTI ==\n";
