<?php
/**
 * ChatHelp — dump-gen-prompt — belge URETIM promptunu DOGRULA (OKUR, degistirmez).
 *  IBAN bug'i: AI, alici adresini "IBAN: ..." satirina yaziyor. Kok nedeni gormek
 *  icin api.php'deki uretim system-prompt'unu + IBAN/Empfänger gecen yerleri gosterir.
 *  Anahtarlar (sk-..., API key) MASKELENIR.
 * KULLANIM: pull2.php?key=...&files=dump-gen-prompt.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{8,}|[A-Za-z0-9_\-]{32,})/','***MASKED***',$s); }
$f=__DIR__.'/api.php';
$src=@file_get_contents($f);
if($src===false){ echo "api.php YOK/okunamadi\n"; exit; }
echo "== dump-gen-prompt api.php (".strlen($src)." B) ==\n\n";

/* uretim action'lari */
echo "=== action/case adlari (generate/doc/brief) ===\n";
if(preg_match_all("/(case\s*['\"]([a-z_]+)['\"]|action['\"]?\s*[=:]+\s*['\"]([a-z_]+)['\"])/i",$src,$ma)){
  $acts=array_filter(array_merge($ma[2],$ma[3]));
  echo "  ".implode(', ',array_values(array_unique($acts)))."\n";
}

/* system prompt / talimat blogu — IBAN, Empfänger, Adresse gecen tum satirlar baglami */
echo "\n=== 'IBAN' gecen her yer (±160 char, maskeli) ===\n";
$off=0; $n=0;
while(($p=stripos($src,'IBAN',$off))!==false && $n<12){
  echo "  [".$n."] ...".mask(substr($src,max(0,$p-160),320))."...\n\n";
  $off=$p+4; $n++;
}
if($n===0) echo "  'IBAN' bulunamadi\n";

/* Empfänger/absender talimatlari */
echo "\n=== 'Empfänger' / 'Absender' gecen yerler (±120, maskeli) ===\n";
foreach(['Empfänger','Empfaenger','Absender','Adresse des'] as $kw){
  $p=stripos($src,$kw);
  if($p!==false){ echo "  [$kw] ...".mask(substr($src,max(0,$p-120),260))."...\n\n"; }
}

/* system prompt anahtar kelimeleri — belge uretim talimati nerede? */
echo "\n=== belge-uretim system prompt bloklari (Schreibe|Erstelle|Verfasse|Du bist) ===\n";
foreach(['Du bist','Schreibe','Erstelle ein','Verfasse','professionelles Schreiben','offizielles Schreiben','Briefkopf'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
$p=stripos($src,'Du bist'); if($p===false) $p=stripos($src,'Briefkopf');
if($p!==false){ echo "\n--- ilk prompt blogu civari (±700, maskeli) ---\n".mask(substr($src,max(0,$p-200),1400))."\n"; }

echo "\n== BITTI ==\n";
