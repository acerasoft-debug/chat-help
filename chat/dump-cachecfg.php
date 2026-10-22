<?php
/* dump-cachecfg (READ-ONLY) — teslimat cache yapilandirmasi: index.php header()
   cache direktifleri, chat/.htaccess icerigi, sw.js Cache-Control, Cloudflare izi.
   Elle cache temizlemeye gerek kalmadan sunucu-tarafi no-cache icin.
   KULLANIM: pull2.php?key=...&files=dump-cachecfg.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-cachecfg (READ-ONLY)\n\n";
$D=__DIR__; $s=@file_get_contents("$D/index.php"); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] index.php ilk 60 satir (header cagrilari?) ===\n";
echo implode("\n",array_slice(explode("\n",$s),0,60))."\n";

echo "\n=== [2] index.php icindeki header('Cache/Expires/Pragma') ===\n";
if(preg_match_all('/header\s*\(\s*[\'"](Cache-Control|Expires|Pragma|ETag|Last-Modified)[^)]*\)/i',$s,$m)){ foreach($m[0] as $h) echo "  ".$h."\n"; } else echo "  (yok)\n";
foreach(['CH_NOCACHE','CH_STRONG_NOCACHE','CH_CACHEBUST'] as $k){ $p=strpos($s,$k); if($p!==false) echo "  ".$k." [".$p."]: ".W($s,$p,20,120)."\n"; }

echo "\n=== [3] chat/.htaccess ===\n";
$h=@file_get_contents("$D/.htaccess");
echo ($h!==false)? ("  (".strlen($h)." B)\n".$h."\n") : "  (yok)\n";

echo "\n=== [4] sw.js ilk satirlar + header ===\n";
$sw=@file_get_contents("$D/sw.js"); echo ($sw!==false)? substr($sw,0,200)."\n" : "  (yok)\n";

echo "\n=== [5] Sunucu ortam (Cloudflare/edge izi) ===\n";
foreach(['HTTP_CF_RAY','HTTP_CF_CONNECTING_IP','SERVER_SOFTWARE','HTTP_X_CACHE','HTTP_CDN_LOOP'] as $k){ if(!empty($_SERVER[$k])) echo "  ".$k."=".$_SERVER[$k]."\n"; }
echo "  apache_get_modules headers: ".(function_exists('apache_get_modules')? (in_array('mod_headers',apache_get_modules())?'VAR':'YOK') : '?')."\n";
echo "\nBITTI.\n";
