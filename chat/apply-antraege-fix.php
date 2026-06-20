<?php
/**
 * ChatHelp — Meine Anträge: çift/bozuk Zurück'ü kaldır + premium (additive)
 *  • #ch-back-chip (benim eklediğim ikinci geri butonu) tamamen kaldırılır
 *  • Native .pnl-back-btn'e güvenli çalışan handler + premium görünüm
 *  • Meine Anträge paneli premium
 * KULLANIM: chat-help.com/chat/apply-antraege-fix.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-antfix-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-antraege-fix') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-antraege-fix-css">
/* benim eklediğim ikinci geri butonunu KESİN gizle */
#ch-back-chip{display:none!important}
/* native geri butonu premium */
.pnl-back-btn{transition:background .15s,color .15s,transform .12s!important;border-radius:11px!important;cursor:pointer}
.pnl-back-btn:hover{background:rgba(212,168,74,.12)!important;color:var(--gold,#d4a84a)!important}
.pnl-back-btn:active{transform:scale(.94)}
/* Meine Anträge premium */
#pnl-ant .pnl-topbar{background:linear-gradient(180deg,var(--s1,#1f1f24),var(--bg,#1b1b1e))!important;border-bottom:1px solid rgba(212,168,74,.14)!important}
#pnl-ant .pnl-topbar-title{font-weight:800;letter-spacing:.2px}
#pnl-ant .drow,#pnl-ant .ant-item,#pnl-ant [class*="card"]{border-radius:14px!important;transition:all .16s}
#pnl-ant .drow:hover,#pnl-ant .ant-item:hover{border-color:rgba(212,168,74,.35)!important;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.28)}
#pnl-ant .ant-empty-ic svg{stroke:var(--gold,#d4a84a)!important;opacity:.9}
#pnl-ant button{border-radius:11px!important}
</style>
<script id="ch-antraege-fix">
(function(){
  'use strict';
  function fix(){
    // 1) benim global geri çipimi DOM'dan kaldır
    var chip=document.getElementById('ch-back-chip'); if(chip){ chip.remove(); }
    document.querySelectorAll('[id^="ch-back"]').forEach(function(el){ if(el.id!=='ch-antraege-fix') el.remove(); });

    // 2) Meine Anträge native geri butonuna güvenli, çalışan handler ekle
    var pnl=document.getElementById('pnl-ant'); if(!pnl) return;
    var backs=pnl.querySelectorAll('.pnl-back-btn');
    // birden çok varsa ilki dışındakini gizle
    backs.forEach(function(b,i){
      if(i>0){ b.style.display='none'; return; }
      if(!b._chfix){ b._chfix=1;
        b.addEventListener('click',function(){ try{ if(typeof gP==='function') gP('chat'); }catch(e){} });
      }
    });
  }
  function boot(){ fix(); setInterval(fix,700); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n=0;
$src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Meine Anträge fix raporu\n===================================\n\n";
echo ($n>0?"  ✓ Anträge fix eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-antraege-fix.php\n";
echo "Test: Meine Anträge -> tek premium 'Zurück' (çalışır); panel premium.\n";
