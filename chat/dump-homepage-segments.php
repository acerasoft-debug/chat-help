<?php
/**
 * ChatHelp — dump-homepage-segments (SADECE OKUR) — #100 icin ana sayfada
 *  hangi segmentler/kartlar/CTA'lar duruyor: Bewerbung/is, USP, foto CTA,
 *  vitrin, kategoriler, gizli-mi (display:none) durumlari. Neyin gereksiz/bozuk
 *  oldugunu gormek icin. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-homepage-segments.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-homepage-segments — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* [1] Ana sayfa segment markerlari — id/class isimleri */
$ids=['wlc','wlc-h1','wlc-scan','usp','ch-jobs-card','ch-vst-home','apply','bewerbung','jobs-card','photo','foto','vitrin','showcase','hero','cta','onboard','home-card','sb-photo'];
echo "[1] Segment/kart marker sayimlari:\n";
foreach($ids as $k){ $n=substr_count($src,$k); if($n>0) echo "  ".str_pad($k,16)." : ".$n."\n"; }

/* [2] Bewerbung / is basvurusu ana sayfa baglami */
echo "\n[2] 'Bewerbung' / 'Başvur' / 'jobs' ana sayfa baglamlari (ilk 14):\n";
$lines=preg_split('/\n/',$src); $shown=0;
foreach($lines as $i=>$ln){
  if($shown>=14) break;
  if(preg_match('/bewerbung|başvur|is bul|jobs-card|nav-jobs/i',$ln) && preg_match('/card|home|wlc|onclick|class=|id=|display|hidden/i',$ln)){
    $t=trim($ln); if(strlen($t)>190)$t=substr($t,0,190).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if($shown===0) echo "  (ana sayfa Bewerbung baglami yok — muhtemelen zaten kaldirilmis)\n";

/* [3] display:none ile gizlenen ana sayfa segmentleri (CH_* temizlik izleri) */
echo "\n[3] 'display:none' + segment class'i gecen CSS satirlari (ilk 20):\n";
$shown=0;
foreach($lines as $i=>$ln){
  if($shown>=20) break;
  if(preg_match('/display\s*:\s*none/i',$ln) && preg_match('/wlc|usp|jobs|vst-home|foto|photo|bewerbung|apply|hero|onboard|home-card|vitrin|cta|scan/i',$ln)){
    $t=trim($ln); if(strlen($t)>190)$t=substr($t,0,190).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if($shown===0) echo "  (segment gizleyen kural yok)\n";

/* [4] CH_ temizlik markerlari (bu oturumdakiler dahil) */
echo "\n[4] Ana sayfa ile ilgili CH_ markerlari:\n";
foreach(['CH_CARDS_CLEAN','CH_CARDMOVE_FIX','CH_HERO_CLEAN','CH_HERO_LASER2','CH_COMPOSER_PREMIUM','CH_PLUSICON_REMOVE','CH_IDENTITY_FIX','CH_VORL300','CH_ZEN','CH_STAB_FINAL2'] as $m){
  echo "  ".str_pad($m,20)." : ".(substr_count($src,$m))." kez\n";
}

/* [5] wlc (welcome) blogu — ilk 300 karakter baglami */
echo "\n[5] 'wlc' (karsilama) ilk gorunum baglami:\n";
$p=strpos($src,'wlc');
if($p!==false){ $seg=substr($src,max(0,$p-40),320); $seg=preg_replace('/\s+/',' ',$seg); echo "  …".substr($seg,0,300)."…\n"; }
else echo "  (wlc yok)\n";

echo "\n=== BITTI (salt-okunur) ===\n";
