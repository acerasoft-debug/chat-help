<?php
/**
 * ChatHelp — Foto backend (doGenerate Vision)
 * -------------------------------------------
 * Yüklenen fotoğrafları/belgeleri Vision ile okur, çıkan gerçekleri
 * vaka metnine ekler; sonra normal akış (taslak+final) devam eder.
 * Model id, api.php'nin ZATEN kullandığı değerden otomatik alınır.
 *
 * KULLANIM: chat-help.com/chat/apply-photo-backend.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-photobk-' . date('Ymd-His'), $src);
$rep = [];

/* ── Model id'yi tespit et (sitenin kullandığı) ── */
if (preg_match('/claude-[a-z0-9.\-]{3,40}/', $src, $mm)) {
    $model = $mm[0];
} else {
    $model = 'claude-3-5-sonnet-20241022';
}
$rep[] = ["Model tespit: $model", 1];

/* ── #1 callClaudeVision fonksiyonunu ekle (yoksa) ── */
if (strpos($src, 'function callClaudeVision') === false) {
    $vis = "function callClaudeVision(string \$system, string \$prompt, array \$photos, int \$maxTok = 1200): string {\n"
         . "    \$content = [['type'=>'text','text'=>\$prompt]];\n"
         . "    foreach (\$photos as \$ph) {\n"
         . "        \$mt = \$ph['type'] ?? 'image/jpeg';\n"
         . "        if (!in_array(\$mt, ['image/jpeg','image/png','image/gif','image/webp'])) continue;\n"
         . "        if (empty(\$ph['data'])) continue;\n"
         . "        \$content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>\$mt,'data'=>\$ph['data']]];\n"
         . "    }\n"
         . "    if (count(\$content) < 2) return '';\n"
         . "    \$payload = ['model'=>'$model','max_tokens'=>\$maxTok,'system'=>\$system,'messages'=>[['role'=>'user','content'=>\$content]]];\n"
         . "    \$ch = curl_init('https://api.anthropic.com/v1/messages');\n"
         . "    curl_setopt_array(\$ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,\n"
         . "        CURLOPT_POSTFIELDS=>json_encode(\$payload),\n"
         . "        CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json'],\n"
         . "        CURLOPT_TIMEOUT=>60]);\n"
         . "    \$res = curl_exec(\$ch); curl_close(\$ch);\n"
         . "    \$d = json_decode(\$res, true);\n"
         . "    return \$d['content'][0]['text'] ?? '';\n"
         . "}\n\n";
    $n1 = 0;
    $src = preg_replace('/function callClaude\b/', $vis . 'function callClaude', $src, 1, $n1);
    $rep[] = ['#1 callClaudeVision eklendi', $n1];
} else { $rep[] = ['#1 callClaudeVision (zaten var)', 0]; }

/* ── #2 doGenerate içine foto okuma bloğu ── */
if (strpos($src, 'AUS HOCHGELADENEN DOKUMENTEN') === false) {
    $anchor = '$answersText = implode("\n", array_filter(explode("\n", $answersText)));';
    $block  = $anchor . "\n\n"
        . "    // ChatHelp: yüklenen fotoğrafları/belgeleri Vision ile oku, vakaya ekle\n"
        . "    \$chPhotos = array_values(array_filter(\$b['photos'] ?? [], fn(\$p)=>isset(\$p['data']) && strlen(\$p['data'])>100 && strlen(\$p['data'])<5000000));\n"
        . "    if (\$chPhotos) {\n"
        . "        \$chVis = callClaudeVision(\n"
        . "            \"Du wertest hochgeladene Dokumente/Belege für einen Rechtsfall aus. Extrahiere alle relevanten Fakten (Daten, Fristen, Aktenzeichen, Beträge, Behörde/Absender, Kernaussagen) als knappe deutsche Stichpunkte. Keine Einleitung, keine Erklärungen.\",\n"
        . "            \"Analysiere die beigefügten Bilder für das Dokument: {\$docName}.\",\n"
        . "            \$chPhotos, 1200\n"
        . "        );\n"
        . "        if (\$chVis) \$answersText .= \"\\n\\nAUS HOCHGELADENEN DOKUMENTEN:\\n\".\$chVis;\n"
        . "    }";
    $n2 = 0;
    $src = str_replace($anchor, $block, $src, $n2);
    $rep[] = ['#2 doGenerate foto bloğu', $n2];
} else { $rep[] = ['#2 doGenerate foto bloğu (zaten var)', 0]; }

$changed = ($src !== $start);
if ($changed) {
    // güvenlik: PHP sözdizimi bozulmasın diye lint
    $tmp = tempnam(sys_get_temp_dir(), 'chk') . '.php';
    file_put_contents($tmp, $src);
    $lint = shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1');
    @unlink($tmp);
    if ($lint !== null && strpos($lint, 'No syntax errors') === false) {
        echo "‼️ SÖZDİZİMİ HATASI — değişiklik KAYDEDİLMEDİ:\n$lint\n";
        echo "Yedek: " . basename($file) . ".bak-photobk-*\n";
        exit;
    }
    file_put_contents($file, $src);
}

echo "ChatHelp — Foto backend raporu\n==============================\n\n";
foreach ($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n" . ($changed ? "DURUM: api.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php. SİL: rm apply-photo-backend.php\n";
echo "Test: dilekçe sorusuna bir belge fotoğrafı ekle -> oluştur -> belgede o bilgiler kullanılmalı.\n";
