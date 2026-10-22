<?php
/**
 * ChatHelp — dump-iban — ANA hukuki dilekce uretim promptunu DOGRULA (OKUR).
 *  IBAN halusinasyonu: model, kullanici IBAN vermeyince alici adresini "IBAN:"
 *  satirina uyduruyor. Ana sysLaw prompt blogunu + tum 'sysLaw' atamalarini +
 *  banka/Kontodaten/Bankverbindung + halusinasyon-yasagi gecen yerleri gosterir.
 * KULLANIM: pull2.php?key=...&files=dump-iban.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{8,}|[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/api.php';
$src=@file_get_contents($f);
if($src===false){ echo "api.php YOK\n"; exit; }
echo "== dump-iban api.php (".strlen($src)." B) ==\n\n";

echo "=== tum \$sysLaw atama noktalari (satir + ilk 220 char) ===\n";
$lines=explode("\n",$src);
foreach($lines as $i=>$ln){
  if(preg_match('/\$sysLaw\s*(\.?=)/',$ln)){
    echo "  L".($i+1).": ".mask(trim(substr($ln,0,240)))."\n";
  }
}

echo "\n=== 'Du bist' bloklari (her biri ilk 540 char, maskeli) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'Du bist',$off))!==false && $n<4){
  echo "  --- [$n] (offset $p) ---\n  ".mask(substr($src,$p,540))."\n\n";
  $off=$p+7;$n++;
}

echo "=== banka/IBAN/halusinasyon talimati sayaci ===\n";
foreach(['Bankverbindung','Kontodaten','Rückzahlung','Rueckzahlung','überweis','IBAN','erfinde','erfinden','Platzhalter','nicht erfinden','keine erfundenen','nur die vom Nutzer','nicht bekannt'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== halusinasyon-yasagi talimatlari baglami (±150, maskeli) ===\n";
foreach(['erfinde','erfinden','Platzhalter','nicht bekannt','keine erfundenen','nur die vom Nutzer','Absender'] as $kw){
  $p=stripos($src,$kw);
  if($p!==false){ echo "  [$kw] ...".mask(substr($src,max(0,$p-150),320))."...\n\n"; }
}
echo "== BITTI ==\n";
