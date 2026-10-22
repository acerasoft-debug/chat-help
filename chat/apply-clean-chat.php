<?php
/**
 * ChatHelp — apply-clean-chat (CH_CLEAN_CHAT)
 *  Sol menuden "Chat / Neues Gespräch" acilinca ekran zaten temiz-moda
 *  (body.ch-clean) geciyordu ama bu mod yalnizca kategori/ornek kisimlarini
 *  gizliyordu. Bu yama temiz modu genisletir: Verträge vitrini, tüm modul
 *  kartlari (Module/Job/Türkiye-Vize), hizli-pill'ler ve eski sozlesme
 *  bolumu de gizlenir. Boylece Chat tamamen bos, normal bir yapay zeka
 *  sohbet alani olur (sadece selamlama + mesajlar + giris kutusu).
 *  Ana acilis (landing) ekranini etkilemez — kartlar/vitrin orada durur;
 *  yalnizca Chat'e girildiginde (ch-clean) gizlenir.
 * KULLANIM: pull-updates.php?files=apply-clean-chat.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-clean-chat BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_CLEAN_CHAT') !== false) exit("Zaten ekli (CH_CLEAN_CHAT).\n");
file_put_contents($file . '.bak-cleanchat-' . date('Ymd-His'), $src);

$bundle = <<<'CCHTML'
<style id="ch-clean-chat-css">
/* CH_CLEAN_CHAT — Chat (ch-clean) modunda tum ana-sayfa kart/vitrin/modul gizli */
body.ch-clean #ch-vst-home,
body.ch-clean #ch-hub-card,
body.ch-clean #ch-jobs-card,
body.ch-clean #ch-trvisa-card,
body.ch-clean .wlc-pill,
body.ch-clean .wlc-diff,
body.ch-clean .ch-vtr-sec,
body.ch-clean .wlc-quick-label{display:none !important}
</style>
CCHTML;

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
echo "✓ CH_CLEAN_CHAT uygulandi. Chat'e girince vitrin/kartlar/moduller gizli — tamamen bos AI sohbet.\n";
