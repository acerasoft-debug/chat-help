<?php
/**
 * ChatHelp — apply-premium-polish (CH_PREMIUM_POLISH)
 *  Site geneli premium kalite paketi:
 *   • Sol menu modul ikonlari KOKTEN yeniden: altin cipli (chip) tutarli ikon sistemi
 *   • Kartlar (CV sablonlari, Vertrag kartlari, Modul-Zentrale, ana sayfa kartlari):
 *     derin golge, altin kenar vurgusu, hover kaldirma — komple ust kalite
 *   • Verträge/CV geri oklari: premium yuvarlak altin buton (hover'da dolan)
 *   • Verträge tipografisi: baslik/etiket/alt yazi ust kalite (letter-spacing, hiyerarsi)
 *   • Logo mobil & app'te belirgin sekilde buyur (34px -> 46px + yazi buyur)
 *   • SIRALAMA SABITLEYICI: sol menu ogeleri artik SABIT kanonik sirada tutulur
 *     (Fall eröffnen > Chat > Verlauf > CV > Verträge > Fristen > Rechner > Dokumente > Briefe)
 *     — "altli ustlu yer degistirme" sorununu kokten bitirir.
 * KULLANIM: pull-updates.php?files=apply-premium-polish.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-premium-polish BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_PREMIUM_POLISH') !== false) exit("Zaten ekli (CH_PREMIUM_POLISH).\n");
file_put_contents($file . '.bak-prempolish-' . date('Ymd-His'), $src);

$bundle = <<<'POLHTML'
<style id="ch-premium-polish-css">
/* ══ CH_PREMIUM_POLISH — site geneli premium kalite ══ */

/* ── 1) Sol menu modul ikonlari: altin cipli tutarli sistem ── */
#sb .sb-photo-btn{gap:12px !important;padding:9px 12px !important;border-radius:13px !important;
  transition:background .18s,transform .15s}
#sb .sb-photo-btn:hover{background:rgba(212,168,74,.08) !important;transform:translateX(2px)}
#sb .sb-photo-btn>svg{width:38px !important;height:38px !important;padding:9px;box-sizing:border-box;
  border-radius:11px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.04));
  border:1px solid rgba(212,168,74,.28);
  transition:border-color .18s,background .18s,box-shadow .18s}
#sb .sb-photo-btn:hover>svg{border-color:rgba(212,168,74,.65);
  background:linear-gradient(135deg,rgba(212,168,74,.26),rgba(212,168,74,.09));
  box-shadow:0 4px 14px rgba(212,168,74,.22)}
#sb .sb-photo-btn .sb-photo-t{font-weight:700 !important;font-size:13.5px !important;letter-spacing:.2px}
#sb .sb-photo-btn .sb-photo-s{font-size:11px !important;opacity:.75}

/* ── 2) Geri oklari (Verträge + CV + Hub): premium yuvarlak altin buton ── */
.vst-back,.cvs-back{width:38px !important;height:38px !important;border-radius:12px !important;
  border:1px solid rgba(212,168,74,.45) !important;
  background:rgba(212,168,74,.09) !important;color:var(--gold,#d4a84a) !important;
  font-size:22px !important;font-weight:700;line-height:1 !important;cursor:pointer;
  display:flex !important;align-items:center;justify-content:center;padding:0 0 3px 0 !important;
  transition:background .18s,color .18s,transform .15s,box-shadow .18s;flex-shrink:0}
.vst-back:hover,.cvs-back:hover{background:linear-gradient(135deg,#d4a84a,#f0c860) !important;
  color:#1a1400 !important;transform:translateX(-2px);box-shadow:0 6px 18px rgba(212,168,74,.35)}

/* ── 3) Verträge/CV tipografi: ust kalite hiyerarsi ── */
.vst-ttl,.cvs-ttl{font-size:17px !important;font-weight:800 !important;letter-spacing:.4px !important}
.vst-sub,.cvs-sub{font-size:13.5px !important;line-height:1.65 !important;max-width:640px}
.vst-badge,.cvs-price{font-size:11px !important;font-weight:800 !important;letter-spacing:.5px;
  background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.04));
  border:1px solid rgba(212,168,74,.4) !important}
.vst-fl{font-size:10px !important;font-weight:800 !important;letter-spacing:1.1px !important;
  text-transform:uppercase;color:var(--gold,#d4a84a) !important;opacity:.85}
.vst-pgttl{font-size:16px !important;font-weight:800 !important;letter-spacing:.3px;
  padding-left:12px;border-left:3px solid var(--gold,#d4a84a)}
.vst-in{transition:border-color .18s,box-shadow .18s}
.vst-in:focus{border-color:rgba(212,168,74,.7) !important;box-shadow:0 0 0 3px rgba(212,168,74,.14) !important}
.vst-n{letter-spacing:.2px}
.vst-note{line-height:1.6 !important}

/* ── 4) Kartlar: komple premium (Vertrag + CV + Modul-Zentrale) ── */
.vst-card,.cvs-card{border-radius:18px !important;
  border:1px solid rgba(212,168,74,.2) !important;
  box-shadow:0 6px 20px rgba(0,0,0,.22);
  transition:transform .16s,box-shadow .22s,border-color .22s !important;
  position:relative;overflow:hidden}
.vst-card:before,.cvs-card:before{content:"";position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,rgba(212,168,74,.55),transparent);
  opacity:0;transition:opacity .22s;pointer-events:none}
.vst-card:hover,.cvs-card:hover{transform:translateY(-3px) !important;
  border-color:rgba(212,168,74,.75) !important;
  box-shadow:0 18px 44px rgba(0,0,0,.4),0 4px 16px rgba(212,168,74,.14) !important}
.vst-card:hover:before,.cvs-card:hover:before{opacity:1}
.vst-ic{background:linear-gradient(135deg,rgba(212,168,74,.24),rgba(212,168,74,.06)) !important;
  border:1px solid rgba(212,168,74,.42) !important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 4px 12px rgba(0,0,0,.18)}
.vst-pr{letter-spacing:.3px}

/* ── 5) Ana sayfa kartlari (Module entdecken + Job finden) ── */
#ch-hub-card,#ch-jobs-card{padding:15px 17px !important;border-radius:16px !important;
  border:1px solid rgba(212,168,74,.4) !important;
  box-shadow:0 6px 20px rgba(0,0,0,.2);
  transition:transform .16s,box-shadow .22s,border-color .22s}
#ch-hub-card:hover,#ch-jobs-card:hover{transform:translateY(-2px);
  border-color:rgba(212,168,74,.8) !important;
  box-shadow:0 14px 36px rgba(0,0,0,.34),0 3px 12px rgba(212,168,74,.16)}

/* ── 6) Butonlar & FAB ── */
.vst-nextb{box-shadow:0 8px 22px rgba(212,168,74,.3);letter-spacing:.3px;
  transition:transform .15s,box-shadow .2s}
.vst-nextb:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(212,168,74,.42)}
.vst-prevb{transition:background .18s,border-color .18s}
.vst-prevb:hover{border-color:rgba(212,168,74,.5);background:rgba(212,168,74,.08)}
#ch-hub-fab{transition:transform .15s,box-shadow .2s}
#ch-hub-fab:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(212,168,74,.5)}

/* ── 7) Kaydirma cubuklari: ince premium ── */
.vst-scroll::-webkit-scrollbar,.cvs-scroll::-webkit-scrollbar,.pnl-scroll::-webkit-scrollbar{width:9px}
.vst-scroll::-webkit-scrollbar-thumb,.cvs-scroll::-webkit-scrollbar-thumb,.pnl-scroll::-webkit-scrollbar-thumb{
  background:rgba(212,168,74,.28);border-radius:100px;border:2px solid transparent;background-clip:padding-box}
.vst-scroll::-webkit-scrollbar-thumb:hover,.cvs-scroll::-webkit-scrollbar-thumb:hover,.pnl-scroll::-webkit-scrollbar-thumb:hover{
  background:rgba(212,168,74,.5);background-clip:padding-box}

/* ── 8) Logo: mobil & app'te buyur ── */
@media(max-width:820px){
  .sb-logo svg#ch-nlogo2{width:46px !important;height:46px !important}
  .ch-lg-name{font-size:22px !important;letter-spacing:.2px}
  .ch-lg-sub{font-size:10.5px !important;letter-spacing:1.4px !important}
  .sb-logo{padding:10px 8px !important}
}
</style>
<script id="ch-premium-polish">
/* CH_PREMIUM_POLISH JS — sol menu SIRALAMA SABITLEYICI:
   ogeler her zaman kanonik sirada tutulur, sadece yer degistirmisse tasinir
   (idempotent — dogru siradayken hicbir DOM islemi yapmaz, titreme biter). */
try{(function(){
  function enforceOrder(){
    try{
      var fall=null;
      document.querySelectorAll(".sb-photo-btn").forEach(function(b){
        if(!fall && !/^nav-/.test(b.id||"") && /Fall\s*er[öo]ffnen/i.test(b.textContent||"")) fall=b;
      });
      if(!fall||!fall.parentNode) return;
      var chat=null;
      document.querySelectorAll(".sb-nav-item").forEach(function(n){
        if(!chat && n.getAttribute("onclick")==="NC()") chat=n;
      });
      var seq=[chat,
        document.getElementById("nav-verlauf"),
        document.getElementById("nav-cv"),
        document.getElementById("nav-vst"),
        document.getElementById("nav-fris"),
        document.getElementById("nav-rech"),
        document.getElementById("nav-tresor"),
        document.getElementById("nav-briefe")];
      var anchor=fall;
      seq.forEach(function(el){
        if(!el) return;
        if(el.parentNode!==anchor.parentNode || el.previousElementSibling!==anchor){
          anchor.parentNode.insertBefore(el, anchor.nextSibling);
        }
        anchor=el;
      });
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ enforceOrder(); setInterval(enforceOrder,900); });
  else { enforceOrder(); setInterval(enforceOrder,900); }
})();}catch(e){}
</script>
POLHTML;

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
echo "✓ CH_PREMIUM_POLISH uygulandi.\n";
echo "Icerik: cipli premium menu ikonlari, ust-kalite kartlar/geri oklari/tipografi, mobilde buyuk logo, menu siralama sabitleyici.\n";
