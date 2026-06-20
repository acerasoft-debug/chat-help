<?php
/**
 * ChatHelp — Çeviri motoru (etiketler için) + kalıcı önbellek
 * POST {texts:[...alman etiketler...], lang:'ru'|'tr'|'ar'|'en'|'fr'}
 * -> {translations:{ "deutscher Text": "перевод", ... }}
 * Her etiket dil başına BİR KEZ çevrilir, sonra cache_lang/{lang}.json'dan anında gelir.
 * Almanca (de) çeviri gerektirmez -> aynen döner.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
error_reporting(0); @ini_set('display_errors','0');

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$LANGS = ['tr'=>'Türkisch','ru'=>'Russisch','ar'=>'Arabisch','en'=>'Englisch','fr'=>'Französisch','it'=>'Italienisch','es'=>'Spanisch','pl'=>'Polnisch'];

$b     = json_decode(file_get_contents('php://input'), true) ?: [];
$lang  = strtolower(trim($b['lang'] ?? ''));
$texts = $b['texts'] ?? [];
if (!is_array($texts)) $texts = [];

// Almanca veya bilinmeyen dil -> çeviri yok, aynen dön
if ($lang === 'de' || !isset($LANGS[$lang])) {
    $out = []; foreach ($texts as $t) $out[$t] = $t;
    echo json_encode(['translations' => $out]); exit;
}

// Benzersiz, boş olmayan etiketler
$texts = array_values(array_unique(array_filter(array_map('strval', $texts), function($t){ return trim($t) !== ''; })));

// ── Önbellek ──
$dir = __DIR__ . '/cache_lang';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$cacheFile = $dir . '/' . $lang . '.json';
$cache = [];
if (is_file($cacheFile)) { $cache = json_decode(@file_get_contents($cacheFile), true) ?: []; }

// Eksik olanları bul
$missing = [];
foreach ($texts as $t) { if (!isset($cache[$t])) $missing[] = $t; }

// Eksikleri çevir (DeepSeek), 50'şerli partiler
if ($missing && defined('DEEPSEEK_KEY')) {
    foreach (array_chunk($missing, 50) as $chunk) {
        $tr = ch_translate_batch($chunk, $LANGS[$lang]);
        foreach ($chunk as $src) {
            if (isset($tr[$src]) && trim($tr[$src]) !== '') $cache[$src] = $tr[$src];
        }
    }
    // Kaydet (kilitli)
    $fp = @fopen($cacheFile, 'c+');
    if ($fp) {
        @flock($fp, LOCK_EX);
        $cur = json_decode(stream_get_contents($fp), true) ?: [];
        $merged = array_merge($cur, $cache);
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($merged, JSON_UNESCAPED_UNICODE));
        @flock($fp, LOCK_UN); fclose($fp);
        $cache = $merged;
    }
}

// Sonuç: çeviri varsa o, yoksa orijinal Almanca
$out = [];
foreach ($texts as $t) { $out[$t] = $cache[$t] ?? $t; }
echo json_encode(['translations' => $out], JSON_UNESCAPED_UNICODE);

/* ── DeepSeek toplu çeviri ── */
function ch_translate_batch(array $items, string $langName): array {
    $list = '';
    foreach ($items as $i => $t) { $list .= ($i+1) . '. ' . str_replace("\n", ' ', $t) . "\n"; }
    $sys = "Du bist ein professioneller Übersetzer für juristische/behördliche Formulare. "
         . "Übersetze die folgenden deutschen Formular-Beschriftungen präzise und knapp ins $langName. "
         . "Eigennamen/Abkürzungen sinnvoll belassen. "
         . "Antworte AUSSCHLIESSLICH mit einem JSON-Objekt: Schlüssel = exakt der deutsche Originaltext, "
         . "Wert = die Übersetzung. Keine Nummern, keine Erklärungen, kein Markdown.";
    $usr = "Übersetze ins $langName:\n" . $list;

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','temperature'=>0.1,'max_tokens'=>3000,
            'messages'=>[['role'=>'system','content'=>$sys],['role'=>'user','content'=>$usr]]]),
    ]);
    $res = curl_exec($ch); curl_close($ch);
    if ($res === false) return [];
    $d = json_decode($res, true);
    $txt = $d['choices'][0]['message']['content'] ?? '';
    $txt = preg_replace('/```json?|```/', '', $txt);
    $map = json_decode(trim($txt), true);
    return is_array($map) ? $map : [];
}
