<?php
/**
 * ChatHelp — dump-dophoto — asil doPhoto() + vision cagri fonksiyonunu doker.
 *  'analyze_photo' bos donuyor; sebebi doPhoto icinde. API anahtarlari MASKELENIR.
 *  Ayrica CLAUDE_KEY/DEEPSEEK_KEY UZUNLUGUNU (degeri degil) gosterir -> dolu mu?
 *  SADECE OKUR.
 * KULLANIM: pull2.php?key=...&files=dump-dophoto.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-dophoto ==\n\n";

function mask($s){
  $s=preg_replace('/sk-[A-Za-z0-9_\-]{8,}/','sk-***',$s);
  $s=preg_replace('/[A-Za-z0-9_\-]{32,}/','***LONG***',$s);
  return $s;
}
function grabFunc($src,$name){
  $p=strpos($src,'function '.$name);
  if($p===false) return "!! function $name YOK\n";
  // fonksiyon govdesini kabaca al: ilk { den denk } e kadar (basit sayac)
  $b=strpos($src,'{',$p); if($b===false) return substr($src,$p,1500);
  $depth=0;$i=$b;$n=strlen($src);
  for(;$i<$n;$i++){ $c=$src[$i]; if($c==='{')$depth++; elseif($c==='}'){$depth--; if($depth===0){$i++;break;}} if($i-$p>6000) break; }
  return substr($src,$p,$i-$p);
}

$src=@file_get_contents(__DIR__.'/api.php');
if($src===false){ echo "api.php okunamadi\n"; exit; }

echo "--- doPhoto() (maskeli) ---\n".mask(grabFunc($src,'doPhoto'))."\n\n";

/* vision cagrisini yapan yardimci: callClaude / callVision / callClaudeVision */
foreach(['callClaudeVision','callVision','doVision','callClaude'] as $fn){
  if(strpos($src,'function '.$fn)!==false){
    echo "--- $fn() (maskeli, ilk 3500) ---\n".mask(substr(grabFunc($src,$fn),0,3500))."\n\n";
  }
}

/* anahtar uzunlugu (deger yok) */
echo "== anahtar uzunlugu ==\n";
foreach(['CLAUDE_KEY','DEEPSEEK_KEY','ANTHROPIC_API_KEY','CLAUDE_API_KEY'] as $k){
  if(defined($k)){ $v=(string)constant($k); echo "  $k: TANIMLI, uzunluk=".strlen($v).(strlen($v)>20?" (dolu gorunuyor)":" (KISA/BOS?)")."\n"; }
}
/* config.php'de nasil tanimli (satir, deger maskeli) */
$cfg=@file_get_contents(__DIR__.'/config.php');
if($cfg!==false){
  echo "\n== config.php tanim satirlari (deger maskeli) ==\n";
  foreach(preg_split('/\n/',$cfg) as $ln){
    if(preg_match('/(CLAUDE|DEEPSEEK|ANTHROPIC).{0,20}(KEY|MODEL)/i',$ln)){
      echo "  ".trim(mask($ln))."\n";
    }
  }
}
echo "\n== BITTI ==\n";
