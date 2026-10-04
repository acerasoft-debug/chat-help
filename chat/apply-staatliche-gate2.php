<?php
/**
 * ChatHelp — apply-staatliche-gate2 (CH_STAAT_GATE2)
 *  Staatliche Formulare'nin DE disinda gorunen SON kalintilarini da gizler:
 *   • "📋 Staatliche Formulare" bolum basligi (.sec-hdr) + altindaki bos grid
 *   • Fiyat dipnotundaki "· Staatliche Formulare ab 1,00€" ibaresi
 *  DE secilince hepsi geri gelir.
 * KULLANIM: pull-updates.php?files=apply-staatliche-gate2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-staatliche-gate2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_STAAT_GATE2') !== false) exit("Zaten ekli (CH_STAAT_GATE2).\n");
file_put_contents($file . '.bak-staatgate2-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<script id="ch-staat-gate2">
/* CH_STAAT_GATE2 — Staatliche basligi + grid + fiyat ibaresi DE disinda tamamen gizli */
try{(function(){
  function isDE(){ try{ return (typeof CC!=="undefined" && CC==="DE"); }catch(e){ return false; } }
  function gate(){
    var de=isDE();
    try{
      document.querySelectorAll(".sec-hdr").forEach(function(h){
        if(h.textContent.indexOf("Staatliche Formulare")===-1) return;
        h.style.display=de?"":"none";
        var g=h.nextElementSibling;
        if(g && g.classList && (g.classList.contains("wcat-grid")||g.querySelector&&g.querySelector(".wcat"))) g.style.display=de?"":"none";
      });
    }catch(e){}
    try{
      document.querySelectorAll("div").forEach(function(d2){
        if(d2.children.length) return;
        var t=d2.textContent||"";
        if(t.indexOf("Staatliche Formulare ab")===-1) return;
        if(!d2.dataset.chOrig) d2.dataset.chOrig=t;
        if(de){ if(d2.textContent!==d2.dataset.chOrig) d2.textContent=d2.dataset.chOrig; }
        else{
          var nt=d2.dataset.chOrig.replace(/\s*[·•]\s*Staatliche Formulare[^—–-]*/," ");
          if(d2.textContent!==nt) d2.textContent=nt;
        }
      });
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ gate(); setInterval(gate,900); });
  else { gate(); setInterval(gate,900); }
})();}catch(e){}
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "✓ CH_STAAT_GATE2 eklendi: baslik + grid + fiyat ibaresi DE disinda gizli.\n";
echo "Test: ulke TR iken 'Staatliche' kelimesi hicbir yerde gorunmemeli; DE'de her sey geri gelmeli.\n";
