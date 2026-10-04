<?php
/**
 * ChatHelp — dump-wpcfg — wp-config.php'nin define yapisini MASKELI gosterir
 *  (parola ASLA yazilmaz; deger uzunlugu + ilk/son 2 karakter). DB_NAME/USER/HOST
 *  yaziminin neden parse edilemedigini anlamak icin.
 * KULLANIM: pull2.php?key=...&files=dump-wpcfg.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0);
echo "== dump-wpcfg ==\n\n";
$f=dirname(__DIR__).'/wp-config.php';
$cfg=@file_get_contents($f);
if($cfg===false) exit("wp-config.php okunamadi\n");
echo "boyut: ".strlen($cfg)." B\n\n";
function maskv($k,$v){
  if(stripos($k,'PASS')!==false || stripos($k,'KEY')!==false || stripos($k,'SALT')!==false || stripos($k,'SECRET')!==false)
    return '(gizli, '.strlen($v).' kr)';
  $n=strlen($v); if($n<=4) return $v;
  return substr($v,0,2).str_repeat('*',$n-4).substr($v,-2)." ($n kr)";
}
$lines=preg_split('/\r?\n/',$cfg);
echo "=== define / prefix / require satirlari (maskeli) ===\n";
foreach($lines as $i=>$L){
  $t=trim($L);
  if($t==='') continue;
  if(stripos($t,'define')!==false){
    if(preg_match('/define\s*\(\s*([\'"])([^\'"]+)\1\s*,\s*(.+?)\)\s*;/',$t,$m)){
      $k=$m[2]; $raw=trim($m[3]);
      if(preg_match('/^([\'"])(.*)\1$/s',$raw,$mv)) echo "  ".($i+1).": define('$k', '".maskv($k,$mv[2])."')\n";
      else echo "  ".($i+1).": define('$k', <ifade: ".substr($raw,0,60).">)\n";
    } else {
      echo "  ".($i+1).": (define, parse edilemedi) ".substr($t,0,80)."\n";
    }
  }
  elseif(stripos($t,'table_prefix')!==false) echo "  ".($i+1).": ".substr($t,0,80)."\n";
  elseif(stripos($t,'require')!==false||stripos($t,'include')!==false) echo "  ".($i+1).": ".substr($t,0,90)."\n";
  elseif(stripos($t,'getenv')!==false||stripos($t,'$_ENV')!==false||stripos($t,'$_SERVER')!==false) echo "  ".($i+1).": ".substr($t,0,90)."\n";
}
echo "\n== BITTI ==\n";
