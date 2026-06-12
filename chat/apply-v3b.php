<?php
/**
 * ChatHelp — v3b: kalan 2 düzeltme (esnek regex + teşhis)
 *  #2 Fall solve isteğine profil + tarih ekle
 *  #4 genDoc: tarih boşsa bugünün tarihi
 * Eşleşmezse ilgili bölgenin GERÇEK metnini basar (teşhis).
 * KULLANIM: chat-help.com/chat/apply-v3b.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-v3b-' . date('Ymd-His'), $src);

function ctx($src, $needle, $before = 80, $len = 260) {
    $p = strpos($src, $needle);
    if ($p === false) return "  (bölge bulunamadı: $needle)\n";
    $s = max(0, $p - $before);
    return "  …" . substr($src, $s, $len) . "…\n";
}

echo "ChatHelp — v3b raporu\n=====================\n\n";

/* ── #2 Fall solve -> profil + tarih ── */
if (strpos($src, "datum:new Date().toLocaleDateString") !== false
    && strpos($src, "action:'fall_solve'") !== false
    && preg_match("/action:'fall_solve'[^)]*profile:/", $src)) {
    echo "  ✓ #2 zaten uygulanmış\n";
} else {
    $n2 = 0;
    $src = preg_replace(
        "/action:'fall_solve',\s*title:f\.title,\s*messages:f\.messages/",
        "action:'fall_solve',title:f.title,messages:f.messages,profile:(window.P||{}),datum:new Date().toLocaleDateString('de-DE')",
        $src, 1, $n2
    );
    echo ($n2 > 0 ? "  ✓ #2 Fall solve -> profil+tarih  →  $n2\n"
                  : "  ✗ #2 eşleşmedi — gerçek metin:\n" . ctx($src, "fall_solve"));
}

/* ── #4 genDoc tarih varsayılanı ── */
if (strpos($src, "!ans.datum") !== false) {
    echo "  ✓ #4 zaten uygulanmış\n";
} else {
    $n4 = 0;
    $src = preg_replace(
        "/async function genDoc\(dk,\s*ans\)\s*\{/",
        "async function genDoc(dk,ans){if(ans&&typeof ans==='object'&&!ans.datum){ans.datum=new Date().toLocaleDateString('de-DE');}",
        $src, 1, $n4
    );
    echo ($n4 > 0 ? "  ✓ #4 genDoc tarih varsayılanı  →  $n4\n"
                  : "  ✗ #4 eşleşmedi — gerçek metin:\n" . ctx($src, "function genDoc"));
}

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-v3b.php apply-clean-v3.php\n";
