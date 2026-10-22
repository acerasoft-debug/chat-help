<?php
/**
 * ChatHelp — dump-faxpolicy (SADECE OKUR) — "Fax su an KIME ucretsiz?" sorusuna net cevap.
 *  [1] Senden(fax) kapisi — hangi kosulda paywall'a gidiyor (free/paket ayrimi)
 *  [2] chFaxQuota — plan basina kac ucretsiz fax (Basic/Pro/Elite/free)
 *  [3] Isaretler: CH_PAIDDOC (tek-belge free-fax deligi) / CH_NOFREEFAX / CH_PLANFAX
 *  [4] OZET: fax kimin icin ucretsiz
 * KULLANIM: pull2.php?key=...&files=dump-faxpolicy.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-faxpolicy (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] Senden(fax) kapisi (paywall kosulu) ===\n";
$found=false;
foreach(['_up4=P.plan','showPaywall(dk||CD,{})','_isPaidCard'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]:\n    ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-90),260))."...\n";
    $off=$p+strlen($pat); $n++; $found=true;
  }
}
if(!$found) echo "  (Senden kapisi bulunamadi)\n";

echo "\n=== [2] chFaxQuota — plan basina ucretsiz fax adedi ===\n";
$p=strpos($src,'chFaxQuota');
if($p!==false){
  $s=strpos($src,'function',max(0,$p-80)); if($s===false||$p-$s>120)$s=max(0,$p-40);
  $e=strpos($src,'}',$p); $e=strpos($src,'}',$e+1); if($e===false)$e=$p+400;
  echo "  ".str_replace(["\n"],"\n  ",substr($src,$s,($e-$s)+2))."\n";
} else echo "  (chFaxQuota yok)\n";
/* free-fax adet sabitleri */
echo "\n  -- free-fax sabitleri (5/25/100 vb.) --\n";
foreach(['Gratis-Fax','freeFax','free_fax','faxQuota','FAX_QUOTA','faxLeft','faxRemain'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<2){
    echo "    [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-50),150))."...\n";
    $off=$p+strlen($pat); $n++;
  }
}

echo "\n=== [3] Isaretler (marker) ===\n";
foreach(['CH_PAIDDOC','CH_NOFREEFAX','CH_PLANFAX','CH_FREE1IP'] as $m){
  echo "  ".($m).": ".((strpos($src,$m)!==false)?"VAR ✓":"yok")."\n";
}

echo "\n=== [4] OZET (yorum) ===\n";
$hasHole = strpos($src,"!_isPaidCard")!==false;
$hasPaid = strpos($src,'CH_PAIDDOC')!==false;
$hasNoff = strpos($src,'CH_NOFREEFAX')!==false;
echo "  · Senden kapisi 'free' plani paywall'a gonderiyor mu?  ".((strpos($src,"_up4==='free'")!==false)?"EVET (free-plan fax atamaz)":"BELIRSIZ")."\n";
echo "  · Tek-belge (Einzeldokument) free-fax deligi acik mi?   ".($hasHole?"ACIK (tek-belge 1 free-fax alir)":"KAPALI")."\n";
echo "  · CH_NOFREEFAX uygulanmis mi?                           ".($hasNoff?"EVET":"HAYIR")."\n";
echo "\n  SONUC: Fax ucretsiz => yalnizca PAKET sahibi (kotasi kadar).";
echo ($hasHole?"  + Tek-belge alicisi da 1 free-fax aliyor (delik acik).":"  Tek-belge alicisi fax icin ODER.")."\n";
echo "\n== BITTI ==\n";
