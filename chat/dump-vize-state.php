<?php
/**
 * ChatHelp — dump-vize-state (SADECE OKUR) — Vize Asistani / TR vize yuzeyini teshis.
 *  Hedef: launcher'in nereden acildigini, TR vize kategorisi/menu girisini,
 *  ve mevcut markerlari gormek -> "konforlu/anlasilir" iyilestirmeyi dogru
 *  noktaya yapmak. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-vize-state.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "=== dump-vize-state — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* [1] Markerlar */
$marks = ['CH_VIZE_ASISTAN','chVizeAsistan','CH_TR_VISA','CH_TURKIYE','tr_vize','vz-launch','vz-ov','Vize Asistanı','vize','Schengen'];
echo "[1] Marker varligi:\n";
foreach($marks as $m){ echo "  ".str_pad($m,20)." : ".substr_count($src,$m)." kez\n"; }

/* [2] Menu / sidebar girisleri — 'vize' veya 'Visa' gecen nav/li/buton satirlari */
echo "\n[2] 'vize/visa' gecen menu/nav/buton baglamlari (ilk 20):\n";
$lines = preg_split('/\n/', $src);
$shown=0;
foreach($lines as $i=>$ln){
  if($shown>=20) break;
  if(preg_match('/vize|visa|schengen/i',$ln) && preg_match('/nav|menu|sidebar|sb-|onclick|data-|<li|<button|<a |gP\(|showCat|pill/i',$ln)){
    $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if($shown===0) echo "  (menu baglaminda eslesme yok)\n";

/* [3] chVizeAsistan cagrilan yerler */
echo "\n[3] chVizeAsistan() cagrildigi yerler:\n";
$off=0;$cnt=0;
while(($p=strpos($src,'chVizeAsistan',$off))!==false && $cnt<12){
  $a=max(0,$p-70); $seg=substr($src,$a,150);
  $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+12; $cnt++;
}

/* [4] TR vize kategori anahtari (tr_vize) baglamlari */
echo "\n[4] 'tr_vize' baglamlari (ilk 8):\n";
$off=0;$cnt=0;
while(($p=stripos($src,'tr_vize',$off))!==false && $cnt<8){
  $a=max(0,$p-60); $seg=substr($src,$a,160);
  $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+7; $cnt++;
}
if($cnt===0) echo "  (tr_vize yok)\n";

/* [5] Sidebar menu item uretimi — nasil ekleniyor? (nav-* id'leri) */
echo "\n[5] 'nav-' id'li menu itemleri (ilk 25 essiz):\n";
if(preg_match_all('/id="(nav-[a-z0-9_-]+)"/i',$src,$mm)){
  $u=array_values(array_unique($mm[1]));
  echo "  ".implode(", ", array_slice($u,0,25))."\n  (toplam ".count($u).")\n";
} else echo "  (nav- id bulunamadi)\n";

/* [6] Launcher enjeksiyon paragrafi hala var mi? */
echo "\n[6] Launcher hedef paragrafi ('Aşağıdaki liste genel bir Schengen'):\n";
echo "  ".(strpos($src,'Aşağıdaki liste genel bir Schengen')!==false?"VAR ✓ (launcher gorunuyor olabilir)":"YOK ✗ (launcher HIC gorunmuyor olabilir!)")."\n";

echo "\n=== BITTI (salt-okunur) ===\n";
