<?php
/**
 * ChatHelp ops — voice-fetch: lädt die Bausteine der Browser-Stimmen (Piper, Open Source) auf den eigenen Server, damit Browser
 * nichts bei Drittanbietern laden: onnxruntime-web 1.20.1 (MIT), piper_phonemize/espeak-ng (GPL-3.0) und die Stimmen von
 * Hugging Face (rhasspy/piper-voices, Prüfsumme MD5 aus voices.json). Ziel: chat/vendor/voice/{lib,models}/ (nicht im Snapshot).
 * args: models = kommagetrennte Modell-Schlüssel (de_DE-kerstin-low,…), lib = 1 (Bibliothek laden), list = 1 (nur Bestand zeigen)
 * Schreibt nur in chat/vendor/voice/, nie in bestehende Dateien anderer Bereiche. Gibt keine Schlüssel oder Personendaten aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(0);
$dst = __DIR__ . '/vendor/voice';
foreach (['', '/lib', '/models'] as $d) if (!is_dir($dst . $d) && !@mkdir($dst . $d, 0755, true)) { echo "FEHLER: kann $d nicht anlegen\n"; exit(1); }
echo "ChatHelp — voice-fetch (" . gmdate('c') . ")\n";

/** Datei laden → temporär → Prüfsumme → umbenennen. $algo sha256|md5 */
function vf_get(string $url, string $to, string $algo, string $sum): bool {
    if (is_file($to) && hash_file($algo, $to) === $sum) { echo '  vorhanden ' . basename($to) . "\n"; return true; }
    $tmp = $to . '.part'; $fh = fopen($tmp, 'wb'); if (!$fh) { echo "  FEHLER: schreiben $to\n"; return false; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 900, CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'ChatHelp-voice-fetch']);
    $ok = curl_exec($ch); $err = curl_error($ch); curl_close($ch); fclose($fh);
    if (!$ok) { @unlink($tmp); echo '  FEHLER: ' . basename($to) . " ($err)\n"; return false; }
    $h = hash_file($algo, $tmp);
    if ($h !== $sum) { @unlink($tmp); echo '  FEHLER: Prüfsumme ' . basename($to) . "\n"; return false; }
    rename($tmp, $to); @chmod($to, 0644);
    echo '  geladen ' . basename($to) . ' (' . round(filesize($to) / 1048576, 1) . " MB)\n";
    return true;
}

if (!empty($_GET['lib'])) {
    echo "\nBibliothek:\n";
    $J = 'https://cdn.jsdelivr.net/npm/';
    $LIB = [
        ['onnxruntime-web@1.20.1/dist/ort.wasm.min.js', 'ort.wasm.min.js', 'e66568724f8848e57cc2a56e4bea3a5b86ce3ff81b2da11eac8ed7ab02b27bd4'],
        ['onnxruntime-web@1.20.1/dist/ort-wasm-simd-threaded.mjs', 'ort-wasm-simd-threaded.mjs', '745eb7c0ce6f18a6aa521971b2877babc7ffb27eecb58ab3bc6e5ef4692672e8'],
        ['onnxruntime-web@1.20.1/dist/ort-wasm-simd-threaded.wasm', 'ort-wasm-simd-threaded.wasm', '207d02be4591c156b0a98f024f3d58005b5b04c92274d759fb390338c63559ea'],
        ['@diffusionstudio/piper-wasm@1.0.0/build/piper_phonemize.js', 'piper_phonemize.js', 'fef0c2fc442d24fdef5c7c7cc37d5da2314407640fe11ab1bfe347c723dff19b'],
        ['@diffusionstudio/piper-wasm@1.0.0/build/piper_phonemize.wasm', 'piper_phonemize.wasm', 'b777cd107a91d2bcc6a1ea46f2c26a662a7407394fe84589198aeaa83dd7a9d6'],
        ['@diffusionstudio/piper-wasm@1.0.0/build/piper_phonemize.data', 'piper_phonemize.data.bin', '29f1025eb23a5b5c192cd14a6efbce4509402ff265405072ee6f7d1a09b78f8c'],
    ];
    foreach ($LIB as [$src, $name, $sum]) vf_get($J . $src, "$dst/lib/$name", 'sha256', $sum);
    file_put_contents("$dst/LICENSES.txt", "ChatHelp Browser-Stimmen – verwendete Open-Source-Bausteine\n\n"
        . "onnxruntime-web 1.20.1 – MIT License – https://github.com/microsoft/onnxruntime\n"
        . "piper_phonemize (WebAssembly-Build @diffusionstudio/piper-wasm 1.0.0) – MIT – https://github.com/rhasspy/piper-phonemize\n"
        . "  enthält espeak-ng – GPL-3.0-or-later – Quelltext: https://github.com/espeak-ng/espeak-ng\n"
        . "Piper – MIT – https://github.com/rhasspy/piper · Stimmen: https://huggingface.co/rhasspy/piper-voices (Lizenz je Stimme in models/*.card.txt)\n");
}

$keys = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['models'] ?? ''))), fn($k) => preg_match('/^[a-z]{2}_[A-Z]{2}-[a-z0-9_]+-(x_low|low|medium|high)$/', $k)));
if ($keys) {
    $HF = 'https://huggingface.co/rhasspy/piper-voices/resolve/main/';
    $vj = @file_get_contents($HF . 'voices.json');
    $voices = $vj ? json_decode($vj, true) : null;
    if (!is_array($voices)) { echo "FEHLER: voices.json nicht lesbar\n"; exit(1); }
    echo "\nStimmen:\n";
    foreach ($keys as $k) {
        $m = $voices[$k] ?? null; if (!$m) { echo "  unbekannt $k\n"; continue; }
        foreach ($m['files'] as $path => $f) {
            if (str_ends_with($path, '.onnx')) vf_get($HF . $path, "$dst/models/$k.bin", 'md5', $f['md5_digest']);
            elseif (str_ends_with($path, '.onnx.json')) vf_get($HF . $path, "$dst/models/$k.json", 'md5', $f['md5_digest']);
            elseif (str_ends_with($path, 'MODEL_CARD')) vf_get($HF . $path, "$dst/models/$k.card.txt", 'md5', $f['md5_digest']);
        }
    }
}

echo "\nBestand chat/vendor/voice:\n";
$tot = 0;
foreach (['lib', 'models'] as $d) foreach (glob("$dst/$d/*") ?: [] as $f) { if (str_ends_with($f, '.part')) { @unlink($f); continue; } $tot += filesize($f); echo "  $d/" . basename($f) . ' ' . round(filesize($f) / 1048576, 1) . " MB\n"; }
echo 'Gesamt: ' . round($tot / 1048576) . " MB\n";
foreach (['lib/ort.wasm.min.js', 'lib/piper_phonemize.data.bin'] as $p) {
    $ch = curl_init('https://chat-help.com/chat/vendor/voice/' . $p); curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    curl_exec($ch); echo "Web $p → HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' · ' . curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . "\n"; curl_close($ch);
}
echo "\nFERTIG\n";
