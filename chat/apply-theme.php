<?php
/**
 * ChatHelp — Tema (Antrazit/Lacivert) + Konto isim düzeltmesi
 *  #1 Konto: girişte isim/e-posta/avatar görünür (loadKonto erken return bug'ı)
 *  #2 Tema sistemi: varsayılan ANTRAZIT, Konto'dan LACİVERT'e geçiş (kalıcı)
 *  #3 Konto'ya "🎨 Design" seçici bölümü
 *
 * KULLANIM: chat-help.com/chat/apply-theme.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-theme-' . date('Ymd-His'), $src);
$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* ── #1 loadKonto: token dalında da kullanıcı bilgisini doldur ── */
rep($src, '#1 Konto isim gorunurlugu',
    "renderKontoPlanSelector();}).catch(()=>renderKontoPlanSelector());return;}",
    "renderKontoPlanSelector();chFillKontoUser();}).catch(()=>{renderKontoPlanSelector();chFillKontoUser();});return;}");

/* ── #2 Tema CSS + erken tema seti (head) ── */
if (strpos($src, 'id="ch-theme"') === false) {
    $head = <<<'HTML'
<script>(function(){var t='anthracite';try{t=localStorage.getItem('ch_theme')||'anthracite';}catch(e){}document.documentElement.setAttribute('data-chtheme',t);})();
function chApplyTheme(t){document.documentElement.setAttribute('data-chtheme',t);try{localStorage.setItem('ch_theme',t);}catch(e){}}</script>
<style id="ch-theme">
html[data-chtheme="anthracite"]{--bg:#1b1b1e;--s1:#222226;--s2:#29292e;--s3:#313137;--s4:#3b3b42;--bdr:rgba(255,255,255,.09);--t3:#b4b4c0;--t4:#8e8e9c}
html[data-chtheme="navy"]{--bg:#0a102a;--s1:#0f1634;--s2:#151d42;--s3:#1b2550;--s4:#233060;--bdr:rgba(255,255,255,.10)}
html[data-chtheme] body,html[data-chtheme] #app,html[data-chtheme] .pnl,html[data-chtheme] #msgs,html[data-chtheme] #mi,html[data-chtheme] #pnl-ant,html[data-chtheme] #pnl-konto,html[data-chtheme] #pnl-fall{background:var(--bg)!important}
html[data-chtheme] #sb,html[data-chtheme] #tb,html[data-chtheme] #ia,html[data-chtheme] .pnl-topbar,html[data-chtheme] .pnl-back{background:var(--s1)!important}
body,#sb,#tb,#ia,.pnl{transition:background-color .3s ease}
.chtheme-btn{flex:1;padding:13px;border-radius:12px;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;border:1.5px solid var(--bdr);background:var(--s2);color:#d0d0e0;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s}
html[data-chtheme="anthracite"] .chtheme-btn[data-t="anthracite"],html[data-chtheme="navy"] .chtheme-btn[data-t="navy"]{border-color:var(--gold);color:var(--gold);background:rgba(212,168,74,.10)}
</style>
</head>
HTML;
    $n2 = 0;
    $src = preg_replace('/<\/head>/', $head, $src, 1, $n2);
    $report[] = ['#2 Tema CSS + erken set', $n2];
} else { $report[] = ['#2 Tema (zaten ekli)', 0]; }

/* ── #3 chFillKontoUser + Konto tema seçici (body sonu) ── */
if (strpos($src, 'chFillKontoUser') !== false && strpos($src, 'window.chFillKontoUser') === false) {
    $tail = <<<'HTML'
<script>
window.chFillKontoUser=function(){
  var u=null;try{u=JSON.parse(localStorage.getItem('ch_user')||'null');}catch(e){}
  var info=document.getElementById('konto-user-info');
  if(!u||!u.email){ if(info)info.style.display='none'; return; }
  if(info)info.style.display='flex';
  var av=document.getElementById('konto-avatar'); if(av)av.textContent=((u.name||u.email||'U')[0]||'U').toUpperCase();
  var nm=document.getElementById('konto-name'); if(nm)nm.textContent=u.name||u.email;
  var em=document.getElementById('konto-email'); if(em)em.textContent=u.email||'';
  var pb=document.getElementById('konto-plan-badge'); if(pb)pb.textContent=(((window.P&&P.plan)||u.plan||'KEIN PLAN')+'').toUpperCase();
  var lbl=document.getElementById('tb-auth-label'); if(lbl)lbl.textContent=(u.name||u.email).split(' ')[0];
};
(function(){
  chFillKontoUser();
  var wrap=document.querySelector('#pnl-konto .konto-wrap');
  if(!wrap||document.getElementById('ch-theme-sec'))return;
  var sec=document.createElement('div');sec.className='ksec';sec.id='ch-theme-sec';
  sec.innerHTML='<div class="ksec-hdr"><svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.8" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0 0 20"/></svg><div class="ksec-hdr-t">Design</div></div>'
    +'<div class="ksec-body"><div style="display:flex;gap:8px">'
    +'<button class="chtheme-btn" data-t="anthracite" onclick="chApplyTheme(\'anthracite\')">⬛ Anthrazit</button>'
    +'<button class="chtheme-btn" data-t="navy" onclick="chApplyTheme(\'navy\')">🟦 Marine</button>'
    +'</div><div style="font-size:11px;color:var(--t4);margin-top:8px">Ihre Wahl wird gespeichert.</div></div>';
  wrap.insertBefore(sec, wrap.firstChild);
})();
</script>
HTML;
    rep($src, '#3 chFillKontoUser + tema secici', '</body>', $tail . "\n</body>");
} else { $report[] = ['#3 (zaten ekli veya #1 eslesmedi)', 0]; }

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — Tema + Konto isim raporu\n===================================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: index.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php. SİL: rm apply-theme.php\n";
echo "Test: site antrazit açılır; Konto -> Design -> Marine; girişte isim görünür.\n";
