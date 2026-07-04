<?php
/** ChatHelp — dump-keycheck: config.php'de DeepSeek/Claude anahtarlarinin VARLIGINI dogrular.
 *  GUVENLIK: gercek anahtar DEGERLERI asla yazdirilmaz — sadece sabit adi + son 4 karakter maskeli. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-keycheck BASLADI (PHP ".PHP_VERSION.")\n\n";

if (file_exists(__DIR__.'/config.php')) require_once __DIR__.'/config.php';

function maskConst($name) {
    if (!defined($name)) { echo "  $name: TANIMLI DEGIL\n"; return; }
    $v = constant($name);
    $len = strlen($v);
    $tail = $len > 4 ? substr($v, -4) : '****';
    echo "  $name: VAR (uzunluk=$len, biter: ...$tail)\n";
}

echo "###### Bilinen/olasi sabitler ######\n";
foreach (['CLAUDE_KEY','ANTHROPIC_API_KEY','ANTHROPIC_KEY','DEEPSEEK_KEY','DEEPSEEK_API_KEY','DEEP_SEEK_KEY','DS_KEY','DS_API_KEY'] as $c) {
    maskConst($c);
}

echo "\n###### config.php icindeki TUM sabit define() satirlari (isim + uzunluk, DEGER YOK) ######\n";
$cfg = @file_get_contents(__DIR__.'/config.php');
if ($cfg === false) { echo "  config.php okunamadi\n"; }
else {
    if (preg_match_all('/define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/i', $cfg, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $name = $mm[1]; $val = $mm[2]; $len = strlen($val);
            $tail = $len > 4 ? substr($val, -4) : '****';
            echo "  $name (uzunluk=$len, biter: ...$tail)\n";
        }
    } else {
        echo "  (define() satiri bulunamadi veya farkli sozdizimi kullaniliyor)\n";
    }
}

echo "\n###### api.php icinde DeepSeek/Claude cagri fonksiyonlari ######\n";
$api = @file_get_contents(__DIR__.'/api.php');
if ($api !== false) {
    foreach (['callClaude','callDeepSeek','callDeepseek','deepseek.com','api.deepseek','function call'] as $needle) {
        $p = stripos($api, $needle);
        echo "  '$needle': " . ($p !== false ? "VAR @$p" : "yok") . "\n";
    }
    $p = stripos($api, 'function callClaude');
    if ($p !== false) {
        $b = strpos($api, '{', $p);
        $d = 0; $i = $b; $L = strlen($api);
        for (; $i < $L; $i++) { $c = $api[$i]; if ($c === '{') $d++; elseif ($c === '}') { $d--; if (!$d) { $i++; break; } } }
        echo "\n  ──── callClaude() imzasi + ilk 800 karakter ────\n" . substr($api, $p, min($i - $p, 800)) . "\n";
    }
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder (deger yok, guvenlidir). SIL: rm dump-keycheck.php ===\n";
