<?php
/* dump-profil-billing (READ-ONLY) — (A) 'Zahlungsmethode/Abo verwalten' butonu
   (CH_BILLING_PORTAL_UI) — silmek icin; (B) Konto Profil blogu veri kaynagi:
   Name/E-Mail/Adresse nasil doluyor, 'Acerasoft' varsayilan nereden, aktif
   Absender-Profil sistemi.
   KULLANIM: pull2.php?key=...&files=dump-profil-billing.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-profil-billing (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

/* A) billing buton */
echo "=== [A1] CH_BILLING_PORTAL_UI script (buton uretimi) ===\n";
$p=strpos($s,'CH_BILLING_PORTAL_UI');
if($p!==false){ $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  $blk=substr($s,$so,$eo-$so+9);
  echo (strlen($blk)>2600? substr($blk,0,1300)."\n…[".strlen($blk)."]\n".substr($blk,-1000):$blk)."\n"; }
echo "\n=== [A2] 'Zahlungsmethode' gecisleri ===\n";
$off=0;$c=0; while(($p=strpos($s,'Zahlungsmethode',$off))!==false && $c<4){ echo "  [".$p."] …".W($s,$p,120,80)."…\n"; $off=$p+5;$c++; }

/* B) Profil blogu */
echo "\n=== [B1] Konto Profil blogu (Name/E-Mail/Adresse render) ===\n";
foreach(['GELTENDES RECHT','>Adresse<',' profName','profEmail','profAddr','konto-prof','renderProfil','fillProfil','Profil löschen','Bearbeiten'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,40,110)."\n";
}
echo "\n=== [B2] 'Acerasoft' varsayilan kaynagi ===\n";
$off=0;$c=0; while(($p=stripos($s,'Acerasoft',$off))!==false && $c<10){ echo "  [".$p."] …".W($s,$p,50,70)."…\n"; $off=$p+8;$c++; }

echo "\n=== [B3] prof() / profil depolama + aktif Absender-Profil ===\n";
foreach(['function prof(','ch_profile','ch_prof','ch_profiles','activeProfile','ch_active_prof','absender','Absender-Profile','ap_active','profiles'] as $k){
  $n=substr_count($s,$k); if($n>0){ $p=strpos($s,$k); echo "  '".$k."' (".$n.") [".$p."] ".W($s,$p,10,110)."\n"; }
}
echo "\nBITTI.\n";
