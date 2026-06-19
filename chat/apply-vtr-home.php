<?php
/**
 * ChatHelp — Verträge: ana sayfa bölümü + 1,99€ fiyat (additive)
 *  • DOC_TIER[vtr_*]='komplex' -> tek sözleşme 1,99€ (plan sahiplerine bedava, mevcut paywall)
 *  • Ana sayfaya (welcome #wlc en altına, Staatliche'den sonra) başlıklı Verträge bölümü
 *  • Kısa avukat notu bölümün altında
 * GEREKLİ: apply-vertraege(.php/2.php) çalıştırılmış olmalı (CATS.vertraege).
 * KULLANIM: chat-help.com/chat/apply-vtr-home.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-vtrhome-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-vtr-home') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-vtr-home-css">
.ch-vtr-sec{margin:22px 0 8px;text-align:left}
.ch-vtr-head{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin:0 0 4px}
.ch-vtr-head .t{font-size:16px;font-weight:800;color:#fff;display:flex;align-items:center;gap:7px}
.ch-vtr-head .badge{font-size:10px;font-weight:800;color:#1b1b1e;background:linear-gradient(135deg,#d4a84a,#f0c860);padding:2px 8px;border-radius:100px}
.ch-vtr-sub{font-size:11.5px;color:var(--t3,#b4b4c0);margin:0 0 12px}
.ch-vtr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.ch-vtr-card{background:var(--s2,#26262c);border:1px solid var(--bdr,rgba(255,255,255,.09));border-radius:13px;padding:12px 10px;cursor:pointer;transition:all .18s;text-align:left}
.ch-vtr-card:hover{border-color:rgba(212,168,74,.4);background:var(--s3,#313137);transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.3)}
.ch-vtr-card .ic{font-size:20px;line-height:1;margin-bottom:7px}
.ch-vtr-card .nm{font-size:12px;font-weight:700;color:#fff;line-height:1.25;letter-spacing:-.2px}
.ch-vtr-note{margin-top:11px;font-size:11px;color:var(--t4,#8e8e9c);display:flex;align-items:center;gap:6px}
@media(max-width:700px){ .ch-vtr-grid{grid-template-columns:repeat(2,1fr);gap:6px} .ch-vtr-card{padding:11px 9px} }
</style>
<script id="ch-vtr-home">
(function(){
  'use strict';
  /* ── 1) Fiyat: sözleşmeler 1,99€ (komplex). Plan sahibi paywall görmez (mevcut). ── */
  function priceTick(){
    try{
      if(typeof DOC_TIER==='undefined'||typeof DOCS==='undefined') return;
      Object.keys(DOCS).forEach(function(k){ if(k.indexOf('vtr_')===0 && DOC_TIER[k]!=='komplex') DOC_TIER[k]='komplex'; });
    }catch(e){}
  }

  /* ── 2) Ana sayfa Verträge bölümü (welcome en altı) ── */
  function buildSection(){
    var host=document.getElementById('wlc'); if(!host) return;
    if(host.querySelector('.ch-vtr-sec')) return;
    if(typeof CATS==='undefined'||typeof DOCS==='undefined'||!CATS.vertraege) return;
    var keys=(CATS.vertraege.docs||[]).filter(function(k){ return k.indexOf('vtr_')===0 && DOCS[k]; });
    if(!keys.length) return;
    var sec=document.createElement('div'); sec.className='ch-vtr-sec';
    var cards=keys.map(function(k){
      var d=DOCS[k];
      return '<div class="ch-vtr-card" onclick="(window.qS||function(){})(\''+k+'\')">'
        +'<div class="ic">'+(d.ic||'📜')+'</div><div class="nm">'+(d.n||k)+'</div></div>';
    }).join('');
    sec.innerHTML=
      '<div class="ch-vtr-head"><div class="t">📜 Verträge</div><span class="badge">NEU</span></div>'
      +'<div class="ch-vtr-sub">Rechtssichere Verträge per Frage-Antwort · je 1,99 € · mit Paket gratis</div>'
      +'<div class="ch-vtr-grid">'+cards+'</div>'
      +'<div class="ch-vtr-note">⚖️ KI-gestützt erstellt — bei wichtigen Verträgen empfehlen wir eine anwaltliche Prüfung.</div>';
    host.appendChild(sec);   // en alt: Staatliche bölümünden sonra
  }

  function tick(){ priceTick(); buildSection(); }
  function boot(){ tick(); setInterval(tick,900); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — Verträge ana sayfa + fiyat raporu\n============================================\n\n";
echo ($n>0?"  ✓ Verträge ana sayfa katmanı eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "\nGEREKLİ: apply-vertraege.php + apply-vertraege2.php çalıştırılmış olmalı.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-vtr-home.php\n";
echo "Test: ana sayfa en altta '📜 Verträge' bölümü; bir sözleşmeye tıkla; free kullanıcıda paywall 1,99€.\n";
