<?php
/**
 * ChatHelp — dump-konto-step3 (SADECE OKUR) — Konto ADIM 3 icin son teyit:
 *  (1) showAuthError TAM govde (resend butonu icin dogru DOM yeri),
 *  (2) login basari/hata TAM baglam ("// Login success" cevresi genis),
 *  (3) ch_profiles yazan closure TAM govde (LSK/LSA) — save_profile hook noktasi.
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-konto-step3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-konto-step3 — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

function bodyOf($s,$decl,$cap=2200){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)\n";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,180)."\n";
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)\n":"\n");
}

echo "════ [1] showAuthError TAM govde ════\n";
echo bodyOf($src,'function showAuthError',1800);

echo "\n════ [2] '// Login success' TAM baglam (±900) ════\n";
$p=strpos($src,'// Login success');
if($p!==false){ $seg=substr($src,max(0,$p-500),1600); echo $seg."\n"; } else echo "(YOK)\n";

echo "\n════ [3] LSK=\"ch_profiles\" closure — TAM govde (ilk olusum) ════\n";
$p=strpos($src,'LSK="ch_profiles"');
if($p!==false){
  /* en yakin fonksiyon baslangicini bul: geriye dogru 'function(' ara */
  $fstart=strrpos(substr($src,0,$p),'(function(');
  if($fstart===false) $fstart=max(0,$p-200);
  $seg=substr($src,$fstart,2400);
  echo $seg."\n";
} else echo "(YOK)\n";

echo "\n════ [4] auth.php base URL degiskeni (AUTH_API tanimi) ════\n";
$p=strpos($src,'AUTH_API');
if($p!==false){ $seg=substr($src,max(0,$p-30),160); echo preg_replace('/\s+/',' ',$seg)."\n"; } else echo "(YOK)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
