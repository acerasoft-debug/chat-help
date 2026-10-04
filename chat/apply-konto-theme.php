<?php
/**
 * ChatHelp — apply-konto-theme (CH_THEME_UNIFY) — Konto tema: kalicilik bug'i +
 *  cift selector temizligi.
 *  KOK NEDEN: iki katman ayni 'ch_theme' anahtarini farkli sozlukle kullaniyor.
 *   • Katman A (data-chtheme attr): anthracite/navy paletleri
 *   • Katman B (ch-navy class, CH_NAVY_THEME): navy gradyanlar
 *  apply() 't!=="anth"' testiyle ch-navy'yi aciyordu; kullanici ALT selector'den
 *  "anthracite" secince navigasyonda ch-navy geri acilip navy'yi eziyordu
 *  ("bir iki adim sonra lacivert").
 *  DUZELTME:
 *   [1] (preg) ch-navy-js apply()/chSetTheme BIRLESTIRILIR: normalize (anth*→
 *       anthracite, digeri→navy), data-chtheme + ch-navy/ch-anth TUTARLI set,
 *       normalized deger persist. window.chApplyTheme de bu motora baglanir.
 *       => Anthrazit KALICI; navy gradyan korunur; Weiss kaldirilir (navy'ye map).
 *   [2] (str) ALT selector (Anthrazit/Marine/Weiß, chtheme-btn) enjeksiyonu
 *       durdurulur (chFillKontoUser cagirisi korunur). Yalniz UST 2-secenek kalir.
 *  Lint + dogrulama + opcache reset.
 * KULLANIM: pull2.php?key=...&files=apply-konto-theme.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-theme BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_THEME_UNIFY')!==false) exit("Zaten ekli (CH_THEME_UNIFY).\n");

$fail=[];

/* ── [1] apply()/chSetTheme birlestir (preg) ── */
$pat = '~function apply\(t\)\{\s*try\{\s*document\.documentElement\.classList\.toggle\("ch-navy",\s*t!=="anth"\);\s*\}catch\(e\)\{\}\s*try\{\s*document\.querySelectorAll\("#ch-theme-sw button"\)\.forEach\(function\(b\)\{\s*b\.classList\.toggle\("on",\s*b\.getAttribute\("data-t"\)===t\);\s*\}\);\s*\}catch\(e\)\{\}\s*\}\s*window\.chSetTheme=function\(t\)\{\s*try\{\s*localStorage\.setItem\("ch_theme",t\);\s*\}catch\(e\)\{\}\s*apply\(t\);\s*\};~s';
$rep = 'function apply(t){ var v=chThNorm(t); try{ var r=document.documentElement; r.setAttribute("data-chtheme",v); r.classList.toggle("ch-navy",v==="navy"); r.classList.toggle("ch-anth",v==="anthracite"); }catch(e){} try{ localStorage.setItem("ch_theme",v); }catch(e){} try{ document.querySelectorAll("#ch-theme-sw button").forEach(function(b){ b.classList.toggle("on", chThNorm(b.getAttribute("data-t"))===v); }); }catch(e){} }
  function chThNorm(t){ t=(""+(t||"")).toLowerCase(); return (t.indexOf("anth")===0||t.indexOf("antr")===0)?"anthracite":"navy"; }
  window.chSetTheme=function(t){ apply(t); };
  window.chApplyTheme=function(t){ apply(t); };/*CH_THEME_UNIFY*/';
$m1 = preg_match($pat,$src);
if($m1!==1){ echo "  ✗ [1] apply() bloku $m1 kez eslesti (1 beklenir)\n"; $fail[]='1'; }
else { $src = preg_replace($pat,$rep,$src,1); echo "  ✓ [1] tema motoru birlestirildi (data-chtheme + ch-navy/ch-anth tutarli, normalize+persist)\n"; }

/* ── [2] ALT selector enjeksiyonu durdur (str) ── */
$anc = "if(!wrap||document.getElementById('ch-theme-sec'))return;";
$c2 = substr_count($src,$anc);
if($c2!==1){ echo "  ✗ [2] ALT selector anchor $c2 kez (1 beklenir)\n"; $fail[]='2'; }
else { $src = str_replace($anc, $anc." return;/*CH_THEME_UNIFY_ALT: alt selector (Weiß dahil) kaldirildi*/", $src); echo "  ✓ [2] ALT tema selector (Anthrazit/Marine/Weiß) kaldirildi; chFillKontoUser korundu\n"; }

if($fail){ echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'kt').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-konto-theme-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_THEME_UNIFY')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ DOGRULAMA: CH_THEME_UNIFY diskte (".strlen($chk)." B)\n";
echo "\n✓ Anthrazit artik KALICI (navigasyonda navy'ye donmez)\n";
echo "✓ Sadece Lacivert(Marine) + Antrazit — alt selector ve Weiß kaldirildi\n";
echo "✓ Navy premium gradyan korundu; eski beyaz secim otomatik navy'ye tasinir\n";
echo "\nKonto'yu ac, Anthrazit sec, baska sayfaya gecip don — antrasit kalmali.\n";
