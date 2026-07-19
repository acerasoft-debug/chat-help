<?php
/**
 * ChatHelp — apply-calm-fix2 (CH_CALM_FIX2) — ACIL "surekli oynama" (flicker):
 *  son eklenen interval'lar her tikta DOM'u sil-tekrar-ekle yapip reflow/flicker
 *  yaratiyordu. Idempotent yapilir:
 *   [1] CH_VERLAUF_RECENT3: 'old.previousSibling===nav' kosulu kaldirilir
 *       (bu kosul whitespace/komsu degisince her tik basarisiz olup kutuyu
 *        sil-tekrar-ekle yapiyordu -> sidebar flicker). Artik yalniz sig degisince.
 *   [2] CH_APPSTORE_IOS2: ayni Google Play kutusuna zaten iPhone butonu varsa
 *       bir daha eklenmez (link yeniden olusursa cift/flicker onlenir).
 * KULLANIM: pull2.php?key=...&files=apply-calm-fix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-calm-fix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CALM_FIX2')!==false) exit("Zaten ekli (CH_CALM_FIX2).\n");

$fail=0;

/* [1] Verlauf previousSibling kosulunu kaldir */
$o1="if(old && old.getAttribute('data-sig')===sig && old.previousSibling===nav) return;";
$n1="if(old && old.getAttribute('data-sig')===sig) return;/*CH_CALM_FIX2*/";
$c1=substr_count($src,$o1);
if($c1>=1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] Verlauf sil-tekrar-ekle flicker giderildi ($c1 yer)\n"; }
else { echo "  ! [1] Verlauf anchor bulunamadi (belki farkli surum)\n"; $fail++; }

/* [2] iPhone butonu cift-ekleme guard */
$o2="if(a.getAttribute('data-ch-iph')) return;";
$n2="if(a.getAttribute('data-ch-iph')) return; try{ if(a.parentNode && a.parentNode.querySelector('.ch-iph-btn')) return; }catch(_e){}/*CH_CALM_FIX2*/";
$c2=substr_count($src,$o2);
if($c2>=1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] iPhone butonu cift-ekleme onlendi ($c2 yer)\n"; }
else { echo "  ! [2] iPhone anchor bulunamadi\n"; }

/* marker garanti */
if(strpos($src,'CH_CALM_FIX2')===false){ echo "\n✗ Hicbir degisiklik uygulanamadi — DEGISTIRILMEDI.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'cf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-calmfix2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  ✓ CH_CALM_FIX2 uygulandi (".strlen((string)@file_get_contents($file))." B)\n";
echo "✓ Sidebar sil-tekrar-ekle flicker durdu + iPhone butonu cift eklenmez.\n";
echo "  Sayfayi yenile — oynama gitmeli. Halen varsa dump ile canli bakariz.\n";
