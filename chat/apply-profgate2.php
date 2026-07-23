<?php
/**
 * ChatHelp — apply-profgate2 — profgate'in atlanan [2] bolumu: engellenen kullanici
 *  Konto'ya atilmak yerine DOGRUDAN profil formunu gorsun; doldurunca dilekce akisi
 *  devam etsin. Bu surum canli block() metnini TOLERANSLI (bosluk-bagimsiz regex)
 *  bulur; yine eslesmezse canli metni EKRANA BASAR (bir sonraki tur icin).
 * KULLANIM: pull2.php?key=...&files=apply-profgate2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-profgate2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PROFGATE_FORM')!==false) exit("Zaten ekli (CH_PROFGATE_FORM).\n");

$new=<<<'ANC'
function block(){/*CH_PROFGATE_FORM*/
    try{ if(typeof addMsg==="function") addMsg("ai", MSG[UIL()]||MSG.en); }catch(e){}
    try{ if(typeof showProfileForm==="function"){ try{ if(typeof gP==="function") gP("chat"); }catch(eg){} showProfileForm((typeof CD!=="undefined"&&CD)||null); return; } }catch(e){}
    try{ if(typeof gP==="function") gP("konto"); }catch(e){}
  }
ANC;

$pat='/function block\(\)\{\s*try\{\s*if\(typeof addMsg==="function"\)\s*addMsg\("ai",\s*MSG\[UIL\(\)\]\|\|MSG\.en\);\s*\}catch\(e\)\{\}\s*try\{\s*if\(typeof gP==="function"\)\s*gP\("konto"\);\s*\}catch\(e\)\{\}\s*\}/s';
$cnt=0;
$out=preg_replace($pat,$new,$src,1,$cnt);
if($cnt===1 && is_string($out)){
  $src=$out;
  echo "  ✓ block() toleransli eslesme ile degistirildi\n";
} else {
  echo "  ✗ regex eslesmedi ($cnt). Canli block() metni:\n";
  $p=strpos($src,'CH_PROFILE_REQ');
  if($p!==false){
    $q=strpos($src,'function block',$p);
    if($q!==false){ echo "---8<---\n".substr($src,$q,420)."\n--->8---\n"; }
    else echo "  (function block bulunamadi)\n";
  }
  exit("\nHICBIR sey degistirilmedi.\n");
}

$tmp=tempnam(sys_get_temp_dir(),'pg2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-profgate2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PROFGATE_FORM')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PROFGATE_FORM diskte (".strlen($chk)." B)\n";
echo "  Test: profili BOS kullanici -> belge dene -> uyari + DOGRUDAN profil formu ->\n";
echo "  doldur -> sorulara otomatik devam.\n";
