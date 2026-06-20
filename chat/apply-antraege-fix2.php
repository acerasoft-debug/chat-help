<?php
/**
 * ChatHelp — Meine Anträge: çift geri KÖKTEN çözüm + premium (kesin)
 *  • injectBackChip() çağrısını kapat -> #ch-back-chip ARTIK HİÇ oluşturulmaz
 *  • Mevcut chip varsa kaldır (CSS + JS yedek)
 *  • #pnl-ant native geri butonu (düz <button>) premium
 * KULLANIM: chat-help.com/chat/apply-antraege-fix2.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-antfix2-' . date('Ymd-His'), $src);
$rep=[];

/* ── #1 injectBackChip() çağrısını kapat (kaynak) ── */
$n1=substr_count($src,'injectBackChip();');
if($n1>0){ $src=str_replace('injectBackChip();','void(0);/*chip kapalı*/',$src); }
$rep[]=['#1 injectBackChip() çağrısı kapatıldı', $n1];

/* ── #2 CSS + JS yedek (zaten varsa atla) ── */
if (strpos($src, 'ch-antraege-fix2') === false) {
$bundle = <<<'HTML'
<style id="ch-antraege-fix2-css">
#ch-back-chip{display:none!important}
/* #pnl-ant native geri butonu premium */
#pnl-ant .pnl-topbar>button{transition:all .15s!important;border-radius:11px!important;cursor:pointer}
#pnl-ant .pnl-topbar>button:hover{background:rgba(212,168,74,.14)!important;border-color:rgba(212,168,74,.4)!important}
#pnl-ant .pnl-topbar>button:hover svg{stroke:var(--gold,#d4a84a)!important}
#pnl-ant .pnl-topbar>button:active{transform:scale(.93)}
/* premium panel */
#pnl-ant .pnl-topbar{background:linear-gradient(180deg,var(--s1,#1f1f24),transparent)!important}
#pnl-ant .astat,#pnl-ant .drow,#pnl-ant [class*="ant-item"]{border-radius:14px!important;transition:all .16s}
#pnl-ant .drow:hover,#pnl-ant [class*="ant-item"]:hover{border-color:rgba(212,168,74,.32)!important;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.26)}
#pnl-ant .ant-empty-ic svg{stroke:var(--gold,#d4a84a)!important}
</style>
<script id="ch-antraege-fix2">
(function(){
  function kill(){ var c=document.getElementById('ch-back-chip'); if(c) c.remove(); }
  function boot(){ kill(); setInterval(kill,600);
    try{ new MutationObserver(kill).observe(document.body,{childList:true,subtree:true}); }catch(e){}
  }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;
  $n2=0; $src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n2);
  $rep[]=['#2 CSS+JS yedek (premium + chip kill)', $n2];
} else { $rep[]=['#2 (zaten ekli)',0]; }

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Meine Anträge fix2 raporu\n====================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php + tarayıcıda Ctrl+Shift+R. SİL: rm apply-antraege-fix2.php\n";
echo "Test: Meine Anträge -> TEK geri butonu (premium, çalışır); yüzen ikinci buton yok.\n";
