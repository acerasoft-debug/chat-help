<?php
/**
 * ChatHelp — dump-chclean (SADECE OKUR) — body.ch-clean NEDEN takiliyor.
 *  (1) ch-clean1-js TAM govde, (2) NC/_g wrapper baglami (hangi fonksiyonlar
 *  sarmalaniyor + ch-clean ekleyen/kaldiran), (3) ch-clean'in load'da uygulandigi
 *  yer(ler), (4) CH_CLEAN markerlari. Boylece dogru kalici fix yazilir. YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-chclean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-chclean — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* [1] ch-clean1-js tam govde */
echo "[1] <script id=\"ch-clean1-js\"> TAM govde:\n";
$p=strpos($src,'id="ch-clean1-js"');
if($p!==false){
  $s=strpos($src,'>',$p)+1; $e=strpos($src,'</script>',$s);
  echo substr($src,$s,min($e-$s,3500))."\n".(($e-$s)>3500?"…(kirpildi)\n":"");
} else echo "  (ch-clean1-js YOK)\n";

/* [2] NC/_g wrapper baglami (ch-clean ekleyen add + kaldiran remove cevresi) */
echo "\n[2] ch-clean ekleyen/kaldiran wrapper baglami (±700 bayt):\n";
foreach(['classList.add("ch-clean")','classList.remove("ch-clean")'] as $nd){
  $q=strpos($src,$nd);
  if($q!==false){
    $seg=substr($src,max(0,$q-700),1200); $seg=preg_replace('/\s+/',' ',$seg);
    echo "\n  ── '$nd' ──\n  …".$seg."…\n";
  }
}

/* [3] NC ve _g tanimi/atamasi — 'var NC' / 'NC=' / '_g=' */
echo "\n[3] NC / _g tanim baglamlari:\n";
foreach(['NC=','var NC','function NC','_g=','window.NC','=NC;','=NC,'] as $nd){
  $off=0;$n=0;
  while(($q=strpos($src,$nd,$off))!==false && $n<3){
    $seg=substr($src,max(0,$q-80),160); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  '$nd' …".$seg."…\n"; $off=$q+strlen($nd);$n++;
  }
}

/* [4] load'da ch-clean uygulanmasi / gP('konto') veya chat auto-open */
echo "\n[4] CH_CLEAN markerlari + baglam:\n";
$off=0;$n=0;
while(($q=strpos($src,'CH_CLEAN',$off))!==false && $n<8){
  $seg=substr($src,max(0,$q-40),130); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$q+8;$n++;
}
if(!$n) echo "  (CH_CLEAN marker yok)\n";

/* [5] wcat-grid RENDER — kartlar aslinda DOM'a basiliyor mu (render fonksiyonu) */
echo "\n[5] wcat-grid'e kart basan render baglami (innerHTML/appendChild):\n";
$off=0;$n=0;
while(($q=strpos($src,'wcat-grid',$off))!==false && $n<6){
  $seg=substr($src,max(0,$q-60),150); $seg=preg_replace('/\s+/',' ',$seg);
  if(preg_match('/innerHTML|appendChild|getElementById|querySelector|render/i',$seg))
    echo "  …".$seg."…\n";
  $off=$q+9;$n++;
}

echo "\n=== BITTI (salt-okunur) ===\n";
