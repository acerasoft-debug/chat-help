<?php
/**
 * ChatHelp — dump-sblogo-full (SADECE OKUR) — .sb-logo elementinin TAM
 *  (kirpilmamis) HTML'i + <head> ekleme noktasi icin <meta charset kisminin
 *  TAM metni. Favicon eklemek + sb-logo'ya CH ikonu koymak icin gerekli
 *  KESIN anchor'lari almak. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-sblogo-full.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-sblogo-full — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

echo "[1] '.sb-logo' onclick=\"NC()\" TAM blok (900 karakter, ilk esleşme):\n";
$p = strpos($src, '<div class="sb-logo" onclick="NC()">');
if ($p!==false) {
  echo substr($src,$p,900)."\n";
} else {
  $p = strpos($src, 'class="sb-logo"');
  echo "(tam anchor yok, alternatif) ".($p!==false ? substr($src,max(0,$p-60),900) : "HIC BULUNAMADI")."\n";
}

echo "\n[2] Kac kere geciyor 'class=\"sb-logo\"':\n";
echo "  count: ".substr_count($src,'class="sb-logo"')."\n";

echo "\n[3] <meta charset TAM satiri (favicon icin ekleme noktasi):\n";
$p=stripos($src,'<meta charset="UTF-8">');
if($p!==false){ echo "  bulundu, count=".substr_count($src,'<meta charset="UTF-8">')."\n"; echo "  baglam: …".substr($src,max(0,$p-40),120)."…\n"; }
else echo "  (tam bu sekliyle yok, alternatif ariyorum)\n";

echo "\n[4] '<link rel=' gecen ilk 5 yer (mevcut link etiketleri):\n";
$off=0;$n=0;
while(($p=stripos($src,'<link rel=',$off))!==false && $n<5){
  $seg=substr($src,$p,140); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+10;$n++;
}
if($n===0) echo "  (yok)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
