<?php
/**
 * ChatHelp — apply-theme-auto (CH_THEME_AUTO) — sistem temasina gore otomatik
 *  acik/koyu + eksik "Weiss" dugmesi.
 *  Mevcut durum (dogrulandi): data-chtheme sistemi 'anthracite'/'navy'/'white'
 *  icin TAM CSS renklerine sahip (--bg/--s1/--s2...), ama:
 *   (a) ilk-ziyaret varsayilani hep 'anthracite' (kullanici tercihi olmasa bile)
 *   (b) secici sadece 2 dugme gosteriyor (Anthrazit, Marine) — 'white' CSS'i
 *       hazir ama secilemez durumda.
 *  Duzeltme (2 kesin, count-guard'li str_replace, mevcut kod DEGISMEZ):
 *   [1] Manuel secim YOKSA: telefon/tarayici acik temadaysa (prefers-color-
 *       scheme: light) -> otomatik 'white'; degilse -> 'navy' (asil varsayilan).
 *       Otomatik secim KAYDEDILMEZ (localStorage'a yazilmaz) -> her ziyarette
 *       sistem tercihini takip etmeye devam eder, kullanici elle secince
 *       (chApplyTheme) kalici olarak override eder.
 *   [2] Seciciye 3. dugme eklenir: ⚪ Weiss (data-t="white").
 *  Antrasit + Konto'daki AYRI tema sistemi (ch-navy-js/#ch-theme-sw) HIC
 *  degistirilmez (kendi haliyle calismaya devam eder).
 * KULLANIM: pull2.php?key=...&files=apply-theme-auto.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-theme-auto BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEME_AUTO')!==false) exit("Zaten ekli (CH_THEME_AUTO).\n");

$fail=[];

/* ── [1] ilk-boyama-oncesi tema-init: sistem tercihine gore otomatik ── */
$o1 = <<<'ANC'
<script>(function(){var t='anthracite';try{t=localStorage.getItem('ch_theme')||'anthracite';}catch(e){}document.documentElement.setAttribute('data-chtheme',t);})();
function chApplyTheme(t){document.documentElement.setAttribute('data-chtheme',t);try{localStorage.setItem('ch_theme',t);}catch(e){}}</script>
ANC;
$n1 = <<<'ANC'
<script>(function(){
  /*CH_THEME_AUTO*/
  var t=null; try{ t=localStorage.getItem('ch_theme'); }catch(e){}
  if(!t){ try{ t=(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)?'white':'navy'; }catch(e){ t='navy'; } }
  document.documentElement.setAttribute('data-chtheme',t);
})();
function chApplyTheme(t){document.documentElement.setAttribute('data-chtheme',t);try{localStorage.setItem('ch_theme',t);}catch(e){}}</script>
ANC;
$c=substr_count($src,$o1);
if($c!==1){ echo "  ✗ [1] tema-init anchor ($c)\n"; $fail[]='1'; }
else { $src=str_replace($o1,$n1,$src); echo "  ✓ [1] Ilk ziyarette sistem temasi (acik->beyaz, koyu->lacivert) otomatik uygulanir\n"; }

/* ── [2] seciciye Weiss dugmesi ── */
$o2 = <<<'ANC'
    +'<button class="chtheme-btn" data-t="anthracite" onclick="chApplyTheme(\'anthracite\')">⬛ Anthrazit</button>'
    +'<button class="chtheme-btn" data-t="navy" onclick="chApplyTheme(\'navy\')">🟦 Marine</button>'
    +'</div><div style="font-size:11px;color:var(--t4);margin-top:8px">Ihre Wahl wird gespeichert.</div></div>';
ANC;
$n2 = <<<'ANC'
    +'<button class="chtheme-btn" data-t="anthracite" onclick="chApplyTheme(\'anthracite\')">⬛ Anthrazit</button>'
    +'<button class="chtheme-btn" data-t="navy" onclick="chApplyTheme(\'navy\')">🟦 Marine</button>'
    +'<button class="chtheme-btn" data-t="white" onclick="chApplyTheme(\'white\')">⚪ Weiß</button>'
    +'</div><div style="font-size:11px;color:var(--t4);margin-top:8px">Ihre Wahl wird gespeichert. Ohne Auswahl folgt ChatHelp automatisch der Systemeinstellung Ihres Geräts.</div></div>';
ANC;
$c=substr_count($src,$o2);
if($c!==1){ echo "  ✗ [2] secici dugme anchor ($c)\n"; $fail[]='2'; }
else { $src=str_replace($o2,$n2,$src); echo "  ✓ [2] Seciciye '⚪ Weiß' dugmesi eklendi (3 secenek: Anthrazit/Marine/Weiß)\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'ta').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-themeauto-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEME_AUTO')===false || strpos($chk,'⚪ Weiß')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_THEME_AUTO + Weiß dugmesi diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Tema: manuel secim yoksa telefon acik temadaysa BEYAZ, degilse LACIVERT (varsayilan).\n";
echo "   Antrasit + Beyaz + Lacivert hepsi manuel secilebilir (Design bolumu).\n";
