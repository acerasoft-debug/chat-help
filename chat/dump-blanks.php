<?php
/**
 * ChatHelp — dump-blanks — "eksik alan varsa SORU SOR" mekanizmasi ne durumda?
 *  Sikayet: eskiden IBAN vb. bos alanlar icin soru soruluyordu, simdi belge
 *  bos alanla ciciyor (ör. "IBAN:" bos).
 *  Bu dump: eksik-alan tespiti / tekrar-soru mantigini ve prompt kurallarini arar.
 * KULLANIM: pull2.php?key=...&files=dump-blanks.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$dir=__DIR__;
echo "== dump-blanks ==\n\n";

function scan($label,$path,$keys,$ctxLen=260,$max=4){
  if(!is_file($path)){ echo "-- $label: dosya yok\n\n"; return; }
  $s=(string)@file_get_contents($path);
  echo "-- $label (".strlen($s)." B, ".date('m-d H:i',filemtime($path)).") --\n";
  foreach($keys as $k){
    $c=substr_count($s,$k);
    if(!$c){ continue; }
    echo "  [".str_pad($k,26)."] x$c\n";
    $off=0;$n=0;
    while(($p=strpos($s,$k,$off))!==false && $n<$max){
      echo "     ...".preg_replace('/\s+/',' ',substr($s,max(0,$p-120),$ctxLen))."...\n";
      $off=$p+strlen($k); $n++;
    }
    echo "\n";
  }
  echo "\n";
}

$keys=[
  'IBAN','Iban','iban',
  'fehlende','fehlt','MISSING','missing','eksik',
  'nachfrage','Nachfrage','rueckfrage','Rückfrage',
  'PLATZHALTER','platzhalter','placeholder',
  '[[FRAGE','[[ASK','[[MISSING','[[DOC',
  '____','...........','xxx','XXX',
  'ask_more','needMore','need_more','followup','follow_up',
  'unvollständig','unvollstaendig'
];

scan('api.php',        $dir.'/api.php',        $keys);
scan('intake-api.php', $dir.'/intake-api.php', $keys);
scan('fall-api.php',   $dir.'/fall-api.php',   $keys);

echo "=== index.php — eksik alan / soru mantigi ===\n";
$s=(string)@file_get_contents($dir.'/index.php');
if($s===''){ echo "index.php okunamadi\n"; exit; }
foreach(['IBAN','fehlende','Rückfrage','[[FRAGE','[[ASK','askMissing','missingFields','__ask','chAskMore','CH_ASKFIRST','askfirst'] as $k){
  $c=substr_count($s,$k);
  echo "  ".str_pad($k,18)." x$c\n";
}
echo "\n--- 'IBAN' gecen ilk 4 yer ---\n";
$off=0;$n=0;
while(($p=strpos($s,'IBAN',$off))!==false && $n<4){
  echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($s,max(0,$p-200),420))."...\n\n";
  $off=$p+4;$n++;
}
if($n===0) echo "  index.php'de IBAN yok\n";

echo "\n=== prompt dosyalari (varsa) ===\n";
foreach((array)@glob($dir.'/*prompt*') as $pf){ echo "  ".basename($pf)."  ".filesize($pf)." B\n"; }
foreach((array)@glob($dir.'/prompts/*') as $pf){ echo "  prompts/".basename($pf)."  ".filesize($pf)." B\n"; }

echo "\n== BITTI ==\n";
