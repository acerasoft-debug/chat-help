<?php
/**
 * ChatHelp ops — price-check: lädt die offizielle DeepSeek-Preisseite vom Server aus und zeigt die Preiszeilen (nur lesen, keine Schlüssel).
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(60);
echo "ChatHelp — price-check (".gmdate('c').")\n\n";
foreach (['https://api-docs.deepseek.com/quick_start/pricing', 'https://api-docs.deepseek.com/quick_start/pricing/'] as $u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (ChatHelp price-check)']);
    $html = (string)curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $eff = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); $err = curl_error($ch); curl_close($ch);
    echo "== $u → HTTP $code ($eff) $err\n";
    if ($code !== 200 || $html === '') continue;
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
    $html = preg_replace('#</(tr|p|li|h\d|div|br)>#i', "\n", $html);
    $html = preg_replace('#</t[dh]>#i', ' | ', $html);
    $txt = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_values(array_filter(array_map(fn($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), explode("\n", $txt)), fn($l) => $l !== ''));
    $start = 0; foreach ($lines as $i => $l) { if (stripos($l, 'pricing') !== false || stripos($l, 'MODEL') !== false) { $start = max(0, $i - 2); break; } }
    echo implode("\n", array_slice($lines, $start, 90)), "\n";
    break;
}
