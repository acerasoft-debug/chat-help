<?php
/**
 * ChatHelp — dump-dswait — iki soru:
 *  [A] DeepSeek hangi model? (api.php / intake-api.php config)
 *  [B] 'Bitte warten, Ihr Dokument wird erstellt' neden ALT ALTA tekrarliyor?
 *      (index.php'de mesaji ekleyen yer + yoklama dongusu)
 * KULLANIM: pull2.php?key=...&files=dump-dswait.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$dir=__DIR__;
echo "== dump-dswait ==\n\n";

echo "=== [A] Model yapilandirmasi ===\n";
foreach(['api.php','intake-api.php','config.php','fall-api.php'] as $f){
  $p=$dir.'/'.$f;
  if(!is_file($p)){ echo "-- $f : yok\n"; continue; }
  $s=(string)@file_get_contents($p);
  echo "-- $f (".strlen($s)." B, ".date('m-d H:i',filemtime($p)).") --\n";
  $off=0;$n=0;
  while(preg_match('/[\'"]model[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]|[\'"]model[\'"]\s*:\s*[\'"]([^\'"]+)[\'"]|\$MODEL\s*=\s*[\'"]([^\'"]+)[\'"]/i',$s,$m,PREG_OFFSET_CAPTURE,$off)){
    $val=$m[1][0]?:($m[2][0]?:$m[3][0]);
    echo "   model = ".$val."   @".$m[0][1]."\n";
    $off=$m[0][1]+strlen($m[0][0]); if(++$n>12)break;
  }
  foreach(['deepseek-chat','deepseek-reasoner','deepseek-v3','deepseek-r1','claude-','api.deepseek.com','DEEPSEEK_KEY','DS_MODEL'] as $k){
    $c=substr_count($s,$k); if($c) echo "   ".str_pad($k,22)." x$c\n";
  }
  /* timeout ayarlari */
  foreach(['CURLOPT_TIMEOUT','max_tokens','set_time_limit'] as $k){
    $o=0;$i=0;
    while(($q=strpos($s,$k,$o))!==false && $i<4){
      echo "   ".preg_replace('/\s+/',' ',substr($s,$q,60))."\n"; $o=$q+strlen($k); $i++;
    }
  }
  echo "\n";
}

echo "=== [B] 'wird erstellt' mesaji — index.php ===\n";
$s=(string)@file_get_contents($dir.'/index.php');
if($s===''){ echo "index.php okunamadi\n"; exit; }
$off=0;$n=0;
while(($p=stripos($s,'wird erstellt',$off))!==false && $n<12){
  echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($s,max(0,$p-420),820))."...\n\n";
  $off=$p+13;$n++;
}
if($n===0) echo "  YOK (mesaj baska dosyadan geliyor olabilir)\n";

echo "=== [C] Yoklama (polling) dongusu ipuclari ===\n";
foreach(['setInterval','poll(','__genPoll','gen_status','action=genstatus','retry'] as $k){
  echo "  ".str_pad($k,16)." x".substr_count($s,$k)."\n";
}
echo "\n== BITTI ==\n";
