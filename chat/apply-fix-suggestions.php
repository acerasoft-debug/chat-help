<?php
/**
 * ChatHelp — Sitenin eski örnek önerilerini gizle (bizim Beispiel-Fragen var)
 * + KOMPLE KONTROL: tüm güncellemeler index.php'de mi?
 * KULLANIM: chat-help.com/chat/apply-fix-suggestions.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-fixsug-' . date('Ymd-His'), $src);

echo "ChatHelp — Komple kontrol + öneri gizleme\n=========================================\n\n";

/* ── A) KOMPLE KONTROL ── */
$markers=[
  'CH_NOCACHE'      =>'no-cache (taze HTML)',
  'CH_VTR_INLINE'   =>'Verträge (34 sözleşme)',
  'CH_BEW_INLINE'   =>'Bewerbung',
  'CH_CLEANQ_INLINE'=>'Teknik soru temizliği',
  'ch-home-cats'    =>'Grid kartları (Verträge/Bewerbung)',
  'ch-i18n-layer'   =>'8 dil + çeviri',
  'ch-lang-mobile'  =>'Mobil dil + bilgi kartı',
  'ch-foto-gate'    =>'Foto paywall + Beispiel-Fragen',
  'ch-attach-v2'    =>'Foto/dosya ekleme +',
  'ch-einzelkauf'   =>'Tek ödeme teslim',
  'ch-doc-email'    =>'Dilekçe e-posta',
  'ch-mega-layer'   =>'AI gizle/durdur/ton',
];
echo "[Tüm güncellemeler index.php'de mi?]\n";
$eksik=0;
foreach($markers as $m=>$d){ $ok=strpos($src,$m)!==false; if(!$ok)$eksik++; echo ($ok?"  ✓ ":"  ✗ EKSİK ").$d."\n"; }
echo $eksik? "\n  ⚠️ $eksik güncelleme eksik — söyle, tekrar uygularım.\n" : "\n  ✅ HEPSİ TAM.\n";

/* ── B) Eski örnek önerileri gizle (additive) ── */
if (strpos($src,'ch-hide-sug')!==false){
  echo "\n[Öneri gizleme] Zaten ekli.\n";
} else {
$bundle = <<<'HTML'
<script id="ch-hide-sug">
(function(){
  var SUG=['Mein Vermieter erhöht die Miete','Handyvertrag kündigen','Jobcenter hat Leistungen','Vermieter erhöht die Miete'];
  function hit(t){ for(var i=0;i<SUG.length;i++){ if(t.indexOf(SUG[i])>-1) return true; } return false; }
  function run(){
    var found=[];
    document.querySelectorAll('button,a,li,p,div,span').forEach(function(e){
      if(e.id==='ch-hide-sug'||e.closest('.ch-ex-sec')) return;     // bizim Beispiel-Fragen'a dokunma
      if(e.querySelector('*')) return;                               // sadece yaprak
      var t=(e.textContent||'').trim(); if(t&&hit(t)) found.push(e);
    });
    if(found.length>=2){
      var p=found[0].parentNode;
      if(p && found.filter(function(f){return p.contains(f);}).length>=2){ p.style.display='none'; return; }
    }
    found.forEach(function(f){ f.style.display='none'; });
  }
  function boot(){ run(); setInterval(run, 1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;
  $n=0; $src=preg_replace('/<\/body>/', $bundle."\n</body>", $src, 1, $n);
  echo "\n[Öneri gizleme] ".($n>0?"✓ eklendi (eski öneriler gizlenir, bizim Beispiel-Fragen kalır)":"✗ </body> yok")."\n";
}

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "\n".($changed?"DURUM: güncellendi. ✅":"DURUM: değişiklik yok.")."\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-fix-suggestions.php\n";
