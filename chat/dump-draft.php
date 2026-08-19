<?php
/**
 * ChatHelp — dump-draft — CH_GEN_NOHALL 2-adimli uretimin TAM draftPrompt'unu +
 *  placeholder->deger degistirme kodunu DOGRULA (OKUR). IBAN bug'inin yeri burasi.
 * KULLANIM: pull2.php?key=...&files=dump-draft.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{8,}|[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/api.php';
$src=@file_get_contents($f);
if($src===false){ echo "api.php YOK\n"; exit; }
echo "== dump-draft api.php (".strlen($src)." B) ==\n\n";

/* draftPrompt TAM blogu */
echo "=== draftPrompt blogu (SENDER/RECIPIENT/placeholder talimati, ±0..2600) ===\n";
$p=strpos($src,'$draftPrompt');
if($p!==false){ echo mask(substr($src,$p,2600))."\n\n"; }
else echo "  \$draftPrompt bulunamadi\n";

/* placeholder degistirme: str_replace / preg_replace ile [.*] pattern */
echo "=== placeholder->deger degistirme kodu (str_replace/[BRACKET]) ===\n";
$lines=explode("\n",$src);
foreach($lines as $i=>$ln){
  if(preg_match('/\[(ABSENDER|EMPFAENGER|EMPFANGER|IBAN|NAME|ADRESSE|SENDER|RECIPIENT|BANK|KONTO)/i',$ln)
     || (stripos($ln,'str_replace')!==false && preg_match('/\[/',$ln))
     || preg_match('/PLACEH|Platzhalter|placeholder/',$ln)){
    echo "  L".($i+1).": ".mask(trim(substr($ln,0,240)))."\n";
  }
}

/* profil->belge alan eslemesi (profile fields) */
echo "\n=== profil alan eslemesi civari ('IBAN' + str_replace/array) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'IBAN',$off))!==false && $n<6){
  echo "  [$n] ...".mask(substr($src,max(0,$p-200),420))."...\n\n";
  $off=$p+4;$n++;
}
echo "== BITTI ==\n";
