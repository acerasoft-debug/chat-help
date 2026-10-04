<?php
/**
 * ChatHelp — apply-foto-panel2 (CH_FOTO_PANEL2) — 2 duzeltme:
 *  [1] KONUM: Belge & Foto Merkezi karti en ustten alinir, HERO blogunun
 *      ("Korumanız… / GDPR · 300+ Şablon · Yapay Zeka") HEMEN ALTINA konur.
 *  [2] HATA GORUNURLUGU: analiz basarisiz olursa artik GERCEK NEDEN ekranda
 *      gosterilir (sunucu hatasi / ag hatasi / giris gerekli ipucu) —
 *      "analiz edilmiyor" sorununun nedeni aninda gorunur.
 *  3 count-guard'li str_replace (canli CH_FOTO_PANEL+CH_FOTO_BELGE blogu).
 * KULLANIM: pull2.php?key=...&files=apply-foto-panel2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-panel2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTO_PANEL2')!==false) exit("Zaten ekli (CH_FOTO_PANEL2).\n");
if (strpos($src,'CH_FOTO_BELGE')===false) exit("HATA: once CH_FOTO_BELGE gerekli.\n");

$fail=[];
function rep(&$src,$o,$n,$tag,&$fail){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$tag] anchor $c kez (1 olmali)\n"; $fail[]=$tag; return; }
  $src=str_replace($o,$n,$src);
  echo "  ✓ [$tag] uygulandi\n";
}

/* [1] kart hero'nun hemen altina */
rep($src,
  "if(wlc&&wlc.firstChild){ wlc.insertBefore(c,wlc.firstChild); } else if(grid){ host.insertBefore(c,grid); } else { host.insertBefore(c,host.firstChild||null); }",
  "var hero=wlc?wlc.querySelector('.wlc-hero'):null; /*CH_FOTO_PANEL2*/ if(hero&&hero.parentNode){ hero.parentNode.insertBefore(c,hero.nextSibling); } else if(grid){ (grid.parentNode||host).insertBefore(c,grid); } else if(wlc){ wlc.insertBefore(c,wlc.firstChild||null); } else { host.insertBefore(c,host.firstChild||null); }",
  '1-konum',$fail);

/* [2] hata nedeni ekranda */
rep($src,
  "if(!s || (j&&j.error)){ st.textContent=T('err'); return; }",
  "if(!s || (j&&j.error)){ st.textContent=T('err'); try{ var _r=(j&&j.error)?String(j.error):'sunucu bos yanit'; if(!localStorage.getItem('ch_token')) _r+=' — '+({tr:'once giris yapmayi deneyin',de:'bitte zuerst anmelden',en:'try signing in first'}[UIL()]||''); sm.textContent='⚠ '+_r; }catch(e){} return; }",
  '2-neden',$fail);

/* [3] ag hatasi nedeni ekranda */
rep($src,
  ".catch(function(){ st.textContent=T('err'); });",
  ".catch(function(e){ st.textContent=T('err'); try{ sm.textContent='⚠ '+((e&&e.message)||'ag hatasi — baglantiyi kontrol edin'); }catch(e2){} });",
  '3-ag',$fail);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'f2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotopanel2-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTO_PANEL2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_FOTO_PANEL2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Kart artik HERO'nun (GDPR · 300+ Şablon · Yapay Zeka) hemen altinda\n";
echo "✓ [2] Analiz basarisiz olursa GERCEK NEDEN kartin icinde gorunur\n";
echo "   Test: sayfayi yenileyin, karttan 1 foto + 1 PDF yukleyin.\n";
echo "   Ozet gelmezse karttaki ⚠ satirini AYNEN gonderin.\n";
