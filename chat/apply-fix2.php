<?php
/**
 * ChatHelp — fix2: kesin küçük düzeltmeler
 *  #1 index: "3× DeepSeek + Claude · ..." -> AI adı kaldırıldı
 *  #2 api: tekrarlayan 'strategie' match satırları -> tek satır
 * KULLANIM: chat-help.com/chat/apply-fix2.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$rep = [];

/* ── #1 index.php AI adı temizliği ── */
$if = __DIR__ . '/index.php';
if (file_exists($if)) {
    $idx = file_get_contents($if); $i0 = $idx;
    file_put_contents($if . '.bak-fix2-' . date('Ymd-His'), $idx);

    foreach ([
        '3× DeepSeek + Claude · maßgeschneidertes Dokument' => 'Maßgeschneidertes Dokument · mehrstufige KI-Analyse',
        '3× DeepSeek + Claude' => 'Mehrstufige KI-Analyse',
        'DeepSeek + Claude'    => 'KI-Analyse',
        'DeepSeek'             => 'ChatHelp KI',
        'Claude AI'            => 'ChatHelp KI',
    ] as $o => $n) {
        $c = substr_count($idx, $o);
        if ($c > 0) { $idx = str_replace($o, $n, $idx); $rep[] = ["index: '$o'", $c]; }
    }
    if ($idx !== $i0) { file_put_contents($if, $idx); $rep[] = ['index.php KAYDEDİLDİ', 1]; }
    else $rep[] = ['index.php değişiklik yok', 0];
} else $rep[] = ['index.php YOK', 0];

/* ── #2 api.php strategie tekrarı temizliği ── */
$af = __DIR__ . '/api.php';
if (file_exists($af)) {
    $api = file_get_contents($af); $a0 = $api;
    file_put_contents($af . '.bak-fix2-' . date('Ymd-His'), $api);

    // ardışık tekrarlayan 'strategie' => doStrategie($body), satırlarını tek satıra indir
    $n = 0;
    $api = preg_replace(
        "/(?:[ \t]*'strategie'[ \t]*=>[ \t]*doStrategie\(\\\$body\),[ \t]*\r?\n)+/",
        "        'strategie'       => doStrategie(\$body),\n",
        $api, -1, $n
    );
    $rep[] = ['api: strategie tekrar bloğu sadeleşti', $n];

    // api.php yorum satırındaki sağlayıcı adı (kullanıcıya görünmez ama temiz olsun)
    foreach ([
        '// Step 1: DeepSeek — anonymous research' => '// Step 1: KI — anonyme Recherche',
        'DeepSeek — anonymous' => 'KI — anonyme',
    ] as $o => $nw) {
        $c = substr_count($api, $o);
        if ($c > 0) { $api = str_replace($o, $nw, $api); $rep[] = ["api: '$o'", $c]; }
    }

    if ($api !== $a0) { file_put_contents($af, $api); $rep[] = ['api.php KAYDEDİLDİ', 1]; }
    else $rep[] = ['api.php değişiklik yok', 0];
} else $rep[] = ['api.php YOK', 0];

echo "ChatHelp — fix2 raporu\n======================\n\n";
foreach ($rep as [$l, $n]) echo ($n > 0 ? "  ✓ " : "  ✗ ") . $l . "  →  $n\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-fix2.php\n";
