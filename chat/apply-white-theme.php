<?php
/**
 * ChatHelp — Beyaz/Açık tema ekleme (3. tema seçeneği)
 * -----------------------------------------------------
 * Mevcut antrazit + lacivert sistemine beyaz tema ekler.
 * apply-theme.php çalıştırılmış olsa da olmasa da çalışır.
 * KULLANIM: chat-help.com/chat/apply-white-theme.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-white-' . date('Ymd-His'), $src);
$report = [];

function rep2(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* ── #1 Beyaz tema CSS vars ekle ── */
$whiteCss = '
html[data-chtheme="white"]{--bg:#f4f4f8;--s1:#ffffff;--s2:#ebebf2;--s3:#e0e0ea;--s4:#d4d4e0;--bdr:rgba(0,0,0,.11);--gold:#b07d10;--t3:#3a3a52;--t4:#5e5e7a}
html[data-chtheme="white"] body,html[data-chtheme="white"] #app,html[data-chtheme="white"] .pnl,html[data-chtheme="white"] #msgs,html[data-chtheme="white"] #mi,html[data-chtheme="white"] #pnl-ant,html[data-chtheme="white"] #pnl-konto,html[data-chtheme="white"] #pnl-fall{background:var(--bg)!important;color:#111124!important}
html[data-chtheme="white"] #sb,html[data-chtheme="white"] #tb,html[data-chtheme="white"] #ia,html[data-chtheme="white"] .pnl-topbar,html[data-chtheme="white"] .pnl-back{background:var(--s1)!important;border-bottom-color:rgba(0,0,0,.10)!important}
html[data-chtheme="white"] .msg-ai,html[data-chtheme="white"] .msg-user,html[data-chtheme="white"] p,html[data-chtheme="white"] span,html[data-chtheme="white"] div,html[data-chtheme="white"] h1,html[data-chtheme="white"] h2,html[data-chtheme="white"] h3,html[data-chtheme="white"] label{color:#111124}
html[data-chtheme="white"] input,html[data-chtheme="white"] textarea,html[data-chtheme="white"] select{background:var(--s2)!important;color:#111124!important;border-color:rgba(0,0,0,.13)!important}
html[data-chtheme="white"] .chtheme-btn[data-t="white"]{border-color:var(--gold);color:var(--gold);background:rgba(176,125,16,.10)}';

// ch-theme style varsa ona ekle
if (strpos($src, 'id="ch-theme"') !== false) {
    // Mevcut style bloğunun sonuna beyaz tema ekle
    $n = 0;
    $src = preg_replace('/(id="ch-theme">)([\s\S]*?)(<\/style>)/', '$1$2' . $whiteCss . '$3', $src, 1, $n);
    $report[] = ['#1 Beyaz tema CSS (ch-theme içine)', $n];
} else {
    // ch-theme yok: </head> den önce yeni style ekle
    $newStyle = '<style id="ch-white-theme">' . $whiteCss . '</style>' . "\n</head>";
    $n = 0;
    $src = preg_replace('/<\/head>/', $newStyle, $src, 1, $n);
    $report[] = ['#1 Beyaz tema CSS (yeni style)', $n];
}

/* ── #2 Varsayılan tema ayarı: script'te white'ı da tanı ── */
// chApplyTheme zaten dinamik çalışıyor, extra işlem gerekmez

/* ── #3 Design bölümüne Weiß butonu ekle ── */
// 2 buton var (anthracite + navy) → 3 butona çevir
rep2($src, '#3 Weiß butonu Design bölümüne',
    "'<button class=\"chtheme-btn\" data-t=\"anthracite\" onclick=\"chApplyTheme(\\'anthracite\\')\">⬛ Anthrazit</button>'"
    . "+'<button class=\"chtheme-btn\" data-t=\"navy\" onclick=\"chApplyTheme(\\'navy\\')\">🟦 Marine</button>'",
    "'<button class=\"chtheme-btn\" data-t=\"anthracite\" onclick=\"chApplyTheme(\\'anthracite\\')\">⬛ Anthrazit</button>'"
    . "+'<button class=\"chtheme-btn\" data-t=\"navy\" onclick=\"chApplyTheme(\\'navy\\')\">🟦 Marine</button>'"
    . "+'<button class=\"chtheme-btn\" data-t=\"white\" onclick=\"chApplyTheme(\\'white\\')\">☀️ Hell</button>'"
);

// Alternatif string formatı (tek tırnak yerine çift tırnak içinde olabilir)
rep2($src, '#3b Weiß butonu (alternatif format)',
    '<button class="chtheme-btn" data-t="anthracite" onclick="chApplyTheme(\'anthracite\')">⬛ Anthrazit</button>'
    . '<button class="chtheme-btn" data-t="navy" onclick="chApplyTheme(\'navy\')">🟦 Marine</button>',
    '<button class="chtheme-btn" data-t="anthracite" onclick="chApplyTheme(\'anthracite\')">⬛ Anthrazit</button>'
    . '<button class="chtheme-btn" data-t="navy" onclick="chApplyTheme(\'navy\')">🟦 Marine</button>'
    . '<button class="chtheme-btn" data-t="white" onclick="chApplyTheme(\'white\')">☀️ Hell</button>'
);

/* ── #4 Aktif buton highlight için CSS'e white ekle ── */
rep2($src, '#4 Aktif highlight CSS',
    'html[data-chtheme="anthracite"] .chtheme-btn[data-t="anthracite"],html[data-chtheme="navy"] .chtheme-btn[data-t="navy"]',
    'html[data-chtheme="anthracite"] .chtheme-btn[data-t="anthracite"],html[data-chtheme="navy"] .chtheme-btn[data-t="navy"],html[data-chtheme="white"] .chtheme-btn[data-t="white"]'
);

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Beyaz Tema Raporu\n============================\n\n";
foreach ($report as [$l,$n]) echo ($n > 0 ? "  ✓ " : "  ✗ ") . $l . "  →  $n\n";
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nTest: Konto -> Design -> ☀️ Hell butonuna tıkla, site beyaza dönsün.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-white-theme.php\n";
