<?php
/**
 * ChatHelp — apply-resumegen (CH_RESUMEGEN) — dilekce cevaplari kaybolmasin + profil
 *  verilir verilmez dilekce cikar:
 *  [1] genDoc basinda: profil eksikse (soru sunucuya GITMEDEN, free tuketilmeden) profil
 *      formu gosterilir; cevaplar window._lastGenAns'te korunur.
 *  [2] showProfileForm KAYDET: showQuestions yerine -> genDoc(dk, _lastGenAns.ans) ile
 *      ayni cevaplarla dilekce ANINDA uretilir (cevaplar geri sorulmaz).
 * KULLANIM: pull2.php?key=...&files=apply-resumegen.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-resumegen BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_RESUMEGEN')!==false) exit("Zaten ekli (CH_RESUMEGEN).\n");

$ok=0;

/* [1] genDoc basi: profil eksikse formu once goster (free tuketmeden) */
$o1='window._lastGenAns={dk:dk,ans:Object.assign({},ans)};';
$n1='window._lastGenAns={dk:dk,ans:Object.assign({},ans)};/*CH_RESUMEGEN*/ if((!P.f1||!P.f2||!P.f3||!P.f7)&&typeof showProfileForm===\'function\'){ showProfileForm(dk); return; }';
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] genDoc profil-once kontrolu (cevaplar korunur)\n"; $ok++; }
else echo "  ✗ [1] anchor ($c1, 1 beklenir) — atlandi\n";

/* [2] showProfileForm kaydet: showQuestions yerine genDoc(resume) */
$o2="const dk=afterDk||CD;if(dk)setTimeout(()=>showQuestions(dk),300);else showAllCats();";
$n2="const dk=afterDk||CD;/*CH_RESUMEGEN*/ if(dk){ if(window._lastGenAns&&window._lastGenAns.dk===dk&&window._lastGenAns.ans&&Object.keys(window._lastGenAns.ans).length){ setTimeout(()=>genDoc(dk,window._lastGenAns.ans),300); } else { setTimeout(()=>showQuestions(dk),300); } } else showAllCats();";
$c2=substr_count($src,$o2);
if($c2===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] profil kaydedilince -> dilekce ANINDA (resume)\n"; $ok++; }
else echo "  ✗ [2] anchor ($c2, 1 beklenir) — atlandi\n";

if($ok===0){ echo "\nHicbir sey degismedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'rg').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-resumegen-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_RESUMEGEN')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  Sonuc: $ok/2 (".strlen($chk)." B)\n";
echo "  Akis: sorulari cevapla -> profil eksikse form (cevaplar durur) -> profil doldur ->\n";
echo "  dilekce ANINDA ayni cevaplarla uretilir. Cevap kaybi YOK.\n";
