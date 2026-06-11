<?php
/**
 * ChatHelp — Dilekçe soruları iyileştirme (tarih takvimi + seçim kutusu)
 * --------------------------------------------------------------------
 * showQuestions tüm soruları düz metin kutusu yapıyordu. Bu script:
 *   • Tarih içeren sorularda  -> takvim (input type=date)
 *   • Seçenekli sorularda (opts) -> şık açılır menü (select)
 *   • Diğerleri -> metin / textarea
 * olacak şekilde alan üretimini değiştirir + select için stil ekler.
 *
 * KULLANIM:
 *   Tarayıcı: html/chat/ içine yükle -> chat-help.com/chat/apply-questions.php aç
 *   SSH:      html/chat/ klasöründe  -> php apply-questions.php
 *
 * !!! İŞ BİTİNCE SUNUCUDAN SİL !!!
 */

header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) {
    exit("HATA: index.php bulunamadı. Bu scripti html/chat/ klasörüne koyun.\n");
}

$src   = file_get_contents($file);
$start = $src;

// Yedek
$bak = $file . '.bak-questions-' . date('Ymd-His');
file_put_contents($bak, $src);

$report = [];

/* ── 1) Alan üretim satırını akıllı hale getir ── */
$old = <<<'OLD'
    let inp=q.t==='textarea'?`<textarea data-k="${q.k}" rows="${q.r?3:2}"${q.r?' required':''}>${_v}</textarea>`:q.t==='date'?`<input type="date" data-k="${q.k}" value="${_v}"${q.r?' required':''}>`:`<input type="text" data-k="${q.k}" value="${_v}"${q.r?' required':''}">`;
OLD;

$new = <<<'NEW'
    const _ctx=((q.l||'')+' '+(q.k||'')).toLowerCase();
    const _isDate=/datum|geboren|frist|geburts/.test(_ctx)||/_von$|_bis$|_seit$|geburt/.test((q.k||'').toLowerCase());
    let inp;
    if(Array.isArray(q.opts)&&q.opts.length){
      inp=`<select data-k="${q.k}"${q.r?' required':''}><option value="">— bitte wählen —</option>`+q.opts.map(o=>`<option${_v===o?' selected':''}>${o}</option>`).join('')+`</select>`;
    } else if(_isDate){
      inp=`<input type="date" data-k="${q.k}" value="${_v}"${q.r?' required':''}>`;
    } else if(q.t==='textarea'){
      inp=`<textarea data-k="${q.k}" rows="${q.r?3:2}"${q.r?' required':''}>${_v}</textarea>`;
    } else {
      inp=`<input type="text" data-k="${q.k}" value="${_v}"${q.r?' required':''}>`;
    }
NEW;

$c1 = substr_count($src, $old);
if ($c1 > 0) { $src = str_replace($old, $new, $src); }
$report[] = ['Akilli alanlar (tarih takvimi + secim kutusu)', $c1];

/* ── 2) Select için estetik stil ekle ── */
$style = '<style id="ch-q-style">'
    . '.fi select{background:var(--bg);border:1px solid rgba(255,255,255,.09);border-radius:var(--r);'
    . 'padding:10px 34px 10px 12px;font-size:14px;color:#f8f8ff;outline:none;font-family:inherit;width:100%;'
    . '-webkit-appearance:none;appearance:none;cursor:pointer;'
    . "background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23d4a84a' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");"
    . 'background-repeat:no-repeat;background-position:right 12px center}'
    . '.fi select:focus{border-color:rgba(212,168,74,.4);box-shadow:0 0 0 3px rgba(212,168,74,.06)}'
    . '.fi select option{background:#0d0d1c;color:#fff}'
    . '@media(max-width:700px){.fi select{font-size:16px;padding:12px 34px 12px 14px}}'
    . "</style>\n</head>";

$c2 = substr_count($src, '<style id="ch-q-style">');
if ($c2 === 0) {
    $n2 = 0;
    $src = preg_replace('/<\/head>/', $style, $src, 1, $n2);
    $report[] = ['Select stili (estetik)', $n2];
} else {
    $report[] = ['Select stili (zaten ekli)', 0];
}

/* ── Yaz ── */
$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — Dilekçe soruları iyileştirme raporu\n";
echo "==============================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
foreach ($report as [$label, $n]) {
    echo ($n > 0 ? "  ✓ " : "  ✗ ") . $label . "  →  " . $n . "\n";
}
echo "\n" . ($changed ? "DURUM: Güncellendi. ✅\n" : "DURUM: Değişiklik yok (zaten uygulanmış olabilir).\n");
echo "\nÖNEMLİ:\n";
echo "  1) Test: bir dilekçe seç → tarih sorularında takvim, seçenekli sorularda menü çıkmalı.\n";
echo "  2) Sorun olursa yedek:  cp " . basename($bak) . " index.php\n";
echo "  3) Bu scripti SİL:  rm apply-questions.php\n";
echo "\nNot: '✗ 0' görürsen kalıp bulunamadı (kod değişmiş) — bana haber ver.\n";
