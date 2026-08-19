<?php
/**
 * ChatHelp — apply-premium-v2 (CH_PREMIUM_V2)
 *  Ikinci premium cila turu:
 *   • Karsilama basligi (.wlc-h1) icin zarif serif yazi tipi + daha buyuk
 *     hiyerarsi; alt yazi (.wlc-p) sakinlestirildi.
 *   • Panel gecisleri (.pnl.on) icin yumusak fade+slide animasyonu.
 *   • Kartlarda (vst/cvs/trv) hover'da "nefes alan" altin kenar parlamasi.
 *   • AI'nin urettigi belge karti (.ch-doccard) kagit dokusu + kose muhur
 *     rozeti ("ChatHelp ✓ Geprüft") ile "gercek belge" hissi.
 *   • Fristen/Tresor bos durumlari (empty state) buyuk ikon + tek eylem
 *     cumlesiyle premium gorunume kavusturuldu.
 *   • Yazan-yaziyor (typing) noktalari icin daha zarif animasyon.
 * KULLANIM: pull-updates.php?files=apply-premium-v2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-premium-v2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_PREMIUM_V2') !== false) exit("Zaten ekli (CH_PREMIUM_V2).\n");
file_put_contents($file . '.bak-premiumv2-' . date('Ymd-His'), $src);

$changes = 0;

/* ── empty-state metinlerini ikonlu premium bloklara cevir (JS ile, string-degil DOM sonrasi) ── */
/* Fristen bos durumu */
$oldF = "}).join(\"\"):'<div class=\"vst-sub\">'+esc(l.fempty)+'</div>')";
$newF = "}).join(\"\"):'<div class=\"ch-empty\"><div class=\"ch-empty-ic\">⏰</div><div class=\"ch-empty-t\">'+esc(l.fempty)+'</div></div>')";
$c=0; $src=str_replace($oldF,$newF,$src,$c); if($c){ $changes++; echo "  ✓ [1] Fristen bos-durum ikonlu ($c)\n"; } else echo "  ✗ [1] Fristen empty anchor yok\n";

/* Tresor bos durumu (iki noktada gecer: yuklenirken hata + gercek bos) */
$oldT = 'if(!docs.length){ el.textContent=l.tempty; return; }';
$newT = 'if(!docs.length){ el.innerHTML=\'<div class="ch-empty"><div class="ch-empty-ic">📁</div><div class="ch-empty-t">\'+esc(l.tempty)+\'</div></div>\'; return; }';
$c=0; $src=str_replace($oldT,$newT,$src,$c); if($c){ $changes++; echo "  ✓ [2] Tresor bos-durum ikonlu ($c)\n"; } else echo "  ✗ [2] Tresor empty anchor yok\n";

if ($changes < 2) { echo "\nHATA: 2 degisiklikten sadece $changes uygulandi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── CSS + kucuk JS (belge kartina muhur rozeti ekler) ── */
$bundle = <<<'PVHTML'
<style id="ch-premium-v2-css">
/* ══ CH_PREMIUM_V2 ══ */

/* ── Karsilama basligi: zarif serif hiyerarsi ── */
.wlc-h1{font-family:Georgia,'Iowan Old Style','Palatino Linotype',ui-serif,serif !important;
  font-weight:700 !important;letter-spacing:.2px !important;font-size:clamp(24px,4vw,34px) !important;
  line-height:1.2 !important}
.wlc-p{opacity:.82;font-size:14px !important;line-height:1.55 !important;max-width:520px}

/* ── Panel gecis animasyonu ── */
.pnl{opacity:0;transform:translateY(6px);transition:opacity .22s ease,transform .22s ease}
.pnl.on{opacity:1;transform:translateY(0)}

/* ── Kart hover: nefes alan altin kenar ── */
@keyframes chBreathe{0%,100%{box-shadow:0 0 0 0 rgba(212,168,74,0)}50%{box-shadow:0 0 0 3px rgba(212,168,74,.12)}}
.vst-card:hover,.cvs-card:hover,.trv-card:hover{animation:chBreathe 1.8s ease-in-out infinite}

/* ── AI belge karti: kagit dokusu + muhur rozeti ── */
.ch-doccard{position:relative;background:
  linear-gradient(135deg,rgba(212,168,74,.06),rgba(212,168,74,.015)),
  repeating-linear-gradient(0deg,rgba(255,255,255,.012) 0px,rgba(255,255,255,.012) 1px,transparent 1px,transparent 3px)}
.ch-doccard:after{content:"✓ ChatHelp";position:absolute;top:-9px;right:14px;
  background:linear-gradient(135deg,#8a1f1f,#b32f2f);color:#fff;font-size:9.5px;font-weight:800;
  letter-spacing:.4px;padding:4px 10px;border-radius:100px;box-shadow:0 3px 10px rgba(0,0,0,.35);
  border:1px solid rgba(255,255,255,.25)}
.ch-doccard-body{font-family:Georgia,'Iowan Old Style',ui-serif,serif;font-size:13px !important}

/* ── Bos durumlar (Fristen/Tresor) ── */
.ch-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:44px 20px;text-align:center;gap:10px;opacity:.85}
.ch-empty-ic{font-size:38px;opacity:.55;filter:grayscale(.15)}
.ch-empty-t{font-size:13px;color:var(--t3,#a8a8d0);max-width:260px;line-height:1.5}

/* ── Yaziyor (typing) noktalari: zarif nabiz ── */
@keyframes chTypeDot{0%,60%,100%{opacity:.25;transform:translateY(0)}30%{opacity:1;transform:translateY(-2px)}}
.typing span,.tpd,.dot-typing span{animation:chTypeDot 1.1s ease-in-out infinite}
.typing span:nth-child(2),.tpd:nth-child(2),.dot-typing span:nth-child(2){animation-delay:.15s}
.typing span:nth-child(3),.tpd:nth-child(3),.dot-typing span:nth-child(3){animation-delay:.3s}
</style>
PVHTML;

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
echo "\n✓ CH_PREMIUM_V2 uygulandi ($changes/2 + CSS).\n";
