<?php
/* dump-appstore (READ-ONLY) — @1900113 civari 'qs-s' Google Play kartinin
   bulundugu bolum (premium menu?) + kapsayici id/class + CH_QR iPhone durumu.
   KULLANIM: pull2.php?key=...&files=dump-appstore.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-appstore (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php'); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] 2. Google Play (qs-s) bolumu — TAM baglam ===\n";
$p=strpos($s,'qs-s');
if($p!==false){
  /* iceren <script> ya da bolum */
  $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  echo "  [script @".$so."]\n";
  echo substr($s,max(0,$p-900), 1500)."\n";
}
echo "\n=== [2] 'qs-' id/class'lar (bolum yapisi) ===\n";
if(preg_match_all('/(qs-[a-z0-9\-]+|id="[a-z0-9\-]*(qs|appcard|getapp|store)[a-z0-9\-]*")/i',$s,$m)){ echo "  ".implode(', ',array_slice(array_unique($m[0]),0,25))."\n"; }

echo "\n=== [3] bu bolum nerede gosteriliyor? (baslik/tetikleyici) ===\n";
$p=strpos($s,'qs-s');
if($p!==false){ echo "  geri 2500: …".W($s,$p,2500,60)."…\n"; }

echo "\n=== [4] CH_QR iPhone (ch-qr-ios) eklendi mi? ===\n";
echo "  ch-qr-ios-js: ".(strpos($s,'ch-qr-ios-js')!==false?'VAR':'YOK')."\n";
echo "  ch-qr-ios (buton): ".substr_count($s,'ch-qr-ios')."\n";
echo "\nBITTI.\n";
