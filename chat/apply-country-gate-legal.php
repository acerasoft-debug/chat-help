<?php
/**
 * ChatHelp — apply-country-gate-legal (CH_CC_GATE_LEGAL)
 *  Bazi moduller belirli bir ulkenin hukukuna gore yazildi ve baska
 *  ulkelerde gecersiz/yaniltici (orn. Vertrag Studio'nun 7 sozlesmesi
 *  BGB'ye, Rechner'in Kündigungsfrist/Kaution hesaplari §622/§551 BGB'ye
 *  dayanir — Turkiye'de bir arac satis sozlesmesi Alman hukukuyla
 *  gecerli olmaz). Bu yama, CC (secili hukuk ulkesi) Almanya disindaysa:
 *   • Sol menude Verträge (nav-vst) ve Rechner (nav-rech) gizlenir
 *   • Ana sayfadaki "Verträge" hizli-erisim pili gizlenir
 *   • Konto'daki 8'li Modul-Zentrale kartlarinda Verträge/Rechner
 *     kartlari hic gorunmez (kullaniciya uygunsuz bir secenek sunulmaz)
 *  CC yeniden Almanya'ya donerse hepsi otomatik geri gelir (kullanicinin
 *  kendi ac/kapa tercihine dokunulmaz — sadece Almanya-disi gizleme katmani).
 * KULLANIM: pull-updates.php?files=apply-country-gate-legal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-country-gate-legal BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_CC_GATE_LEGAL') !== false) exit("Zaten ekli (CH_CC_GATE_LEGAL).\n");
file_put_contents($file . '.bak-ccgatelegal-' . date('Ymd-His'), $src);

$bundle = <<<'CGHTML'
<style id="ch-cc-gate-legal-css">
/* CH_CC_GATE_LEGAL — Almanya-disinda hukuka-ozgu moduller gizlenir */
body.ch-cc-nonde #nav-vst,
body.ch-cc-nonde #nav-rech,
body.ch-cc-nonde #ch-pill-vst{display:none !important}
body.ch-cc-nonde .vst-card:has([data-tg="vst"]),
body.ch-cc-nonde .vst-card:has([data-tg="rech"]){display:none !important}
body.ch-cc-nonde label:has(input[data-mod="vst"]){display:none !important}
</style>
<script id="ch-cc-gate-legal">
/* CH_CC_GATE_LEGAL JS — body.ch-cc-nonde sinifini CC'ye gore ayarlar + eski tarayici yedegi */
try{(function(){
  var HIDE_NAV=["nav-vst","nav-rech","ch-pill-vst"];
  var HIDE_HUB_KEYS=["vst","rech"];
  var prevNonDE=false;
  function setDisp(disp){
    HIDE_NAV.forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display=disp; });
    HIDE_HUB_KEYS.forEach(function(k){
      document.querySelectorAll('[data-tg="'+k+'"]').forEach(function(btn){
        var card=btn.closest?btn.closest(".vst-card"):null;
        if(card) card.style.display=disp;
      });
      document.querySelectorAll('input[data-mod="'+k+'"]').forEach(function(cb){
        var lab=cb.closest?cb.closest("label"):(cb.parentNode&&cb.parentNode.tagName==="LABEL"?cb.parentNode:null);
        if(lab) lab.style.display=disp;
      });
    });
  }
  function tick(){
    try{
      var cc="DE"; try{ cc=(typeof CC!=="undefined"&&CC)?CC:"DE"; }catch(e){}
      var nonDE=(cc!=="DE");
      document.body.classList.toggle("ch-cc-nonde", nonDE);
      if(nonDE){
        setDisp("none"); /* Almanya disindayken her tick'te yeniden dayat (baska scriptlerle yaris kazanilir) */
      } else if(prevNonDE){
        setDisp(""); /* Almanya'ya YENI donuldu — zorla gizlemeyi bir kerelik geri al, sonrasi modOn()'a birakilir */
      }
      prevNonDE=nonDE;
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,800); });
  else { tick(); setInterval(tick,800); }
})();}catch(e){}
</script>
CGHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_CC_GATE_LEGAL uygulandi. Almanya disi hukuk secenklerinde Verträge/Rechner artik gorunmuyor.\n";
echo "Test: Konto'da ulkeyi Türkiye yap -> Verträge/Rechner sol menuden ve Modul-Zentrale'den kaybolmali; Almanya'ya donunce geri gelmeli.\n";
