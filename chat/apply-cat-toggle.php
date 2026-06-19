<?php
/**
 * ChatHelp — Kategori gizle/göster toggle (temiz görünüm, additive)
 * Kategori ızgaralarının (.cgrid / .wcat-grid) başına "Kategorien ausblenden ▲".
 * KULLANIM: chat-help.com/chat/apply-cat-toggle.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-cattoggle-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-cat-toggle') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-cat-toggle-css">
.ch-cat-tg{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:2px 0 10px;padding:9px 13px;border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:12px;background:var(--s2,#26262c)}
.ch-cat-tg .lbl{font-size:12.5px;font-weight:800;color:var(--t3,#cfcfdd);letter-spacing:.3px}
.ch-cat-tg button{background:rgba(212,168,74,.1);border:1px solid rgba(212,168,74,.35);color:var(--gold,#d4a84a);border-radius:9px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
.ch-cat-tg button:hover{background:rgba(212,168,74,.2)}
</style>
<script id="ch-cat-toggle">
(function(){
  'use strict';
  function attach(grid){
    if(!grid || grid._chtg) return; grid._chtg=true;
    var prev=grid.previousElementSibling;
    if(prev&&prev.classList&&prev.classList.contains('ch-cat-tg')) return;
    var bar=document.createElement('div'); bar.className='ch-cat-tg';
    bar.innerHTML='<span class="lbl">📂 Kategorien</span><button>ausblenden ▲</button>';
    var btn=bar.querySelector('button');
    btn.onclick=function(){
      var hidden=(grid.style.display==='none');
      grid.style.display=hidden?'':'none';
      btn.textContent=hidden?'ausblenden ▲':'anzeigen ▼';
    };
    grid.parentNode.insertBefore(bar, grid);
  }
  function tick(){ document.querySelectorAll('.cgrid,.wcat-grid').forEach(attach); }
  function boot(){ tick(); setInterval(tick,800); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — Kategori toggle raporu\n=================================\n\n";
echo ($n>0?"  ✓ Toggle eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-cat-toggle.php\n";
