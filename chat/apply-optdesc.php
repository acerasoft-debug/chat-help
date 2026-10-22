<?php
/**
 * ChatHelp — apply-optdesc (CH_OPTDESC) — kriptik <select> seceneklerine kisa aciklama.
 *   [1] Ana belge-form render'ina 'deger|etiket' destegi ekler (| yoksa eskisi gibi -> TAM GERIYE UYUMLU).
 *   [2] Rechtsgrundlage secenekleri: §29 VwVfG / §25 SGB X / §147 AO -> aciklamali etiket.
 *       AI'a giden DEGER temiz kalir (§25 SGB X), kullanici aciklamayi gorur.
 *   Idempotent + hepsi-ya-hic + php -l lint + .bak + opcache + dogrulama. Hicbir mevcut secenek bozulmaz.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-optdesc.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-optdesc BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_OPTDESC')!==false) exit("Zaten uygulandi (CH_OPTDESC).\n");
$ok=0;

/* ── [1] render: deger|etiket destegi (| yoksa davranis AYNI) ── */
$o1=<<<'O1'
q.opts.map(o=>`<option${_v===o?' selected':''}>${o}</option>`).join('')
O1;
$n1=<<<'N1'
q.opts.map(o=>{var _dp=String(o).split('|');var _dv=_dp[0];var _dl=_dp.length>1?_dp[1]:_dp[0];return `<option value="${_dv}"${_v===_dv?' selected':''}>${_dl}</option>`;}).join('')/*CH_OPTDESC*/
N1;
$c=substr_count($src,$o1);
if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [RENDER] deger|etiket destegi eklendi\n"; $ok++; }
else echo "  ✗ [RENDER] anchor ($c, 1 beklenir) — atlandi\n";

/* ── [2] Rechtsgrundlage secenekleri: aciklama ── */
$o2='"opts": ["§29 VwVfG", "§25 SGB X", "§147 AO", "Sonstiges"]';
$n2='"opts": ["§29 VwVfG|§29 VwVfG — Verwaltungsverfahren (Behörden allgemein)", "§25 SGB X|§25 SGB X — Sozialrecht (Jobcenter, Kranken-/Rentenkasse)", "§147 AO|§147 AO — Steuerverfahren (Finanzamt)", "Sonstiges"]';
$c=substr_count($src,$o2);
if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [OPTS] rechtsgrundlage aciklamalari eklendi\n"; $ok++; }
else echo "  ✗ [OPTS] anchor ($c, 1 beklenir) — atlandi\n";

if($ok<2){ echo "\n⚠ ".(2-$ok)." parca uygulanamadi — DEGISTIRILMEDI (butunluk icin).\n"; exit; }

/* ── lint + yaz ── */
$tmp=tempnam(sys_get_temp_dir(),'od').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-optdesc-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_OPTDESC')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_OPTDESC diskte ($ok/2, ".strlen($chk)." B)\n";
echo "  · §29 VwVfG → Verwaltungsverfahren · §25 SGB X → Sozialrecht (Jobcenter) · §147 AO → Steuerverfahren\n";
echo "  · Secenek DEGERI temiz (§25 SGB X); yalniz gorunen etiket aciklamali. Diger tum secenekler degismedi.\n";
