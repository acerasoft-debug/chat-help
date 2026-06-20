<?php
/**
 * ChatHelp — Sunucu temizliği (geçici teşhis/yama dosyalarını siler)
 * SADECE şu kalıpları siler: dump*, apply-*, audit, mail-test*, plan-check,
 * relay-mail, smtp-fix, fix-*, make-fresh, diagnostic, dump-prices, *.bak-*
 * ÜRETİM dosyalarına DOKUNMAZ: index, api, auth, db, config, fall-api,
 * intake-api, send-doc, form-engine, stripe-checkout, stripe-webhook,
 * opcache-reset. En son kendini siler.
 *
 * KULLANIM: chat-help.com/chat/cleanup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
$dir = __DIR__;

/* ASLA silinmeyecek üretim dosyaları */
$protect = [
  'index.php','api.php','auth.php','db.php','config.php',
  'fall-api.php','intake-api.php','send-doc.php','form-engine.js',
  'stripe-checkout.php','stripe-webhook.php','opcache-reset.php','cleanup.php',
];

/* Silinecek kalıplar */
$patterns = [
  'dump*.php','dump-chat*.php','apply-*.php','audit.php',
  'mail-test*.php','plan-check.php','relay-mail.php','smtp-fix.php',
  'fix-*.php','make-fresh.php','diagnostic.php','dump-prices.php',
  '*.bak-*','*.bak',
];

echo "ChatHelp — Temizlik\n===================\n\n";
$deleted=0; $kept=0; $self=null;
$seen=[];
foreach ($patterns as $pat) {
  foreach (glob($dir.'/'.$pat) as $f) {
    $base = basename($f);
    if (isset($seen[$base])) continue; $seen[$base]=1;
    if (in_array($base, $protect, true)) { continue; }
    if ($base === 'cleanup.php') { $self=$f; continue; }
    if (@unlink($f)) { echo "  🗑  silindi: $base\n"; $deleted++; }
    else { echo "  ⚠️  silinemedi: $base\n"; $kept++; }
  }
}

echo "\nToplam silinen: $deleted" . ($kept?"  (silinemeyen: $kept)":"") . "\n";

/* Korunan üretim dosyaları (bilgi) */
echo "\nKorunan üretim dosyaları:\n";
foreach ($protect as $p) if (file_exists($dir.'/'.$p)) echo "  ✓ $p\n";

/* En son kendini sil */
if ($self) { @unlink($self); echo "\ncleanup.php kendini sildi. ✅\n"; }
echo "\nBİTTİ. Temizlik tamam.\n";
