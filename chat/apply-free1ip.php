<?php
/**
 * ChatHelp — apply-free1ip (CH_FREE1IP) — genDoc'ta 1 gratis Dokument pro IP.
 *  Kapi 1 (free plan): once freequota.php?action=claim -> free varsa uret, yoksa paywall.
 *  Kapi 2 (aylik kota): __freeOK ise atlanir. Tek-atislik (__freeOK kullanildi -> sifirla).
 *  Onkosul: apply-freequota.php calistirilmis (freequota.php mevcut). Endpoint yoksa
 *  fetch hatasi -> paywall (guvenli, regresyon yok).
 * KULLANIM: pull2.php?key=...&files=apply-free1ip.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-free1ip BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FREE1IP')!==false) exit("Zaten ekli (CH_FREE1IP).\n");

/* ── Kapi 1: free plan -> IP claim ── */
$o1="  if(!_gTk||_gPlan==='free'){\n    window._pendingDoc={dk,ans};\n    showPaywall(dk,ans);\n    return;\n  }";
$n1="  if((!_gTk||_gPlan==='free') && !window.__freeOK){\n    /* CH_FREE1IP — 1 gratis Dokument pro IP (server, E-Mail-unabhängig) */\n    window._pendingDoc={dk,ans};\n    (async function(){\n      try{\n        const _fr=await fetch('freequota.php?action=claim',{cache:'no-store'});\n        const _fj=await _fr.json();\n        if(_fj&&_fj.free){ window.__freeOK=true; genDoc(dk,ans); return; }\n      }catch(e){}\n      showPaywall(dk,ans);\n    })();\n    return;\n  }";
$c1=substr_count($src,$o1);
if($c1!==1){ echo "  ✗ Kapi 1 anchor ($c1, 1 beklenir) — DEGISTIRILMEDI.\n"; exit; }
$src=str_replace($o1,$n1,$src);
echo "  ✓ Kapi 1: free plan -> freequota claim\n";

/* ── Kapi 2: aylik kota -> __freeOK atla + tek-atislik sifirla ── */
$o2="  const _us2=parseInt(localStorage.getItem(_mk2)||'0');\n  if(_us2>=(_qm2[_gPlan]||0)){showPaywall(dk,ans);return;}\n  localStorage.setItem(_mk2,(_us2+1).toString());";
$n2="  const _us2=parseInt(localStorage.getItem(_mk2)||'0');\n  if(!window.__freeOK && _us2>=(_qm2[_gPlan]||0)){showPaywall(dk,ans);return;}\n  if(!window.__freeOK){ localStorage.setItem(_mk2,(_us2+1).toString()); } else { try{ window.__freeOK=false; }catch(_fe){} }/*CH_FREE1IP*/";
$c2=substr_count($src,$o2);
if($c2!==1){ echo "  ✗ Kapi 2 anchor ($c2, 1 beklenir) — DEGISTIRILMEDI.\n"; exit; }
$src=str_replace($o2,$n2,$src);
echo "  ✓ Kapi 2: aylik kota __freeOK ile atlanir\n";

$tmp=tempnam(sys_get_temp_dir(),'f1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-free1ip-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FREE1IP')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_FREE1IP diskte (".strlen($chk)." B)\n";
echo "  Free kullanici: IP'de free varsa 1 dilekce UCRETSIZ uretilir+indirilir (fax yok).\n";
echo "  Ayni IP ikinci kez (email degisse de) -> paywall.\n";
