<?php
/**
 * ChatHelp — v3: Temiz PDF + Fall profil/tarih + dilekçe tarih varsayılanı
 * -----------------------------------------------------------------------
 *  #1 makePDF: ChatHelp logosu/marka/footer KALDIRILDI -> temiz, resmi belge
 *  #2 Fall "lösen" -> gerçek profil + bugünün tarihi backend'e gönderiliyor
 *  #3 Fall "lösen" öncesi profil boşsa doldurma teklifi
 *  #4 Dilekçe: tarih boşsa otomatik bugünün tarihi (boşa PDF gitmesin)
 *
 * KULLANIM: chat-help.com/chat/apply-clean-v3.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-v3-' . date('Ymd-His'), $src);
$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* ── #1 makePDF: temiz, markasız belge ── */
$newPDF = <<<'JS'
function makePDF(doc,name,cc){
  doc=(typeof chSanitizePDF==='function')?chSanitizePDF(doc):doc;
  const {jsPDF}=window.jspdf;
  const pdf=new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
  pdf.setTextColor(20,20,28);pdf.setFont('times','normal');pdf.setFontSize(11);
  const lines=pdf.splitTextToSize(doc,170);let y=26;
  lines.forEach(function(l){if(y>278){pdf.addPage();y=22;}pdf.text(l,20,y);y+=6;});
  const pc=pdf.internal.getNumberOfPages();
  for(let pg=1;pg<=pc;pg++){pdf.setPage(pg);pdf.setFontSize(8);pdf.setTextColor(165,165,175);pdf.text(pg+' / '+pc,196,290,{align:'right'});}
  pdf.save((name||'Dokument').replace(/[\s\/\\:*?"<>|]/g,'-')+'.pdf');
}
JS;
$n1 = 0;
$src = preg_replace_callback(
    '/function makePDF\(doc,name,cc\)\{.*?pdf\.save\([^;]*\);\s*\}/s',
    function($m) use ($newPDF) { return $newPDF; },
    $src, 1, $n1
);
$report[] = ['#1 makePDF temizlendi (markasiz)', $n1];

/* ── #2 Fall solve: profil + tarih gönder ── */
rep($src, '#2 Fall solve -> profil+tarih',
    "body:JSON.stringify({action:'fall_solve',title:f.title,messages:f.messages})",
    "body:JSON.stringify({action:'fall_solve',title:f.title,messages:f.messages,profile:(window.P||{}),datum:new Date().toLocaleDateString('de-DE')})");

/* ── #3 Fall solve öncesi profil teklifi ── */
rep($src, '#3 Fall solve profil teklifi',
    "if(!(f.messages||[]).length){ alert('Bitte beschreiben Sie zuerst kurz Ihre Situation.'); return; }",
    "if(!(f.messages||[]).length){ alert('Bitte beschreiben Sie zuerst kurz Ihre Situation.'); return; }\n    if(!(window.P&&P.f1)){ if(confirm('Für ein vollständiges Dokument mit Ihren Daten: Profil jetzt ausfüllen?')){ gP('chat'); if(typeof showProfileForm==='function')showProfileForm(null); return; } }");

/* ── #4 Dilekçe: tarih boşsa bugünün tarihi ── */
rep($src, '#4 genDoc tarih varsayilani',
    'async function genDoc(dk,ans){window._lastGenAns={dk:dk,ans:Object.assign({},ans)};',
    "async function genDoc(dk,ans){if(ans&&typeof ans==='object'&&!ans.datum){ans.datum=new Date().toLocaleDateString('de-DE');}window._lastGenAns={dk:dk,ans:Object.assign({},ans)};");

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — v3 raporu\n====================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: index.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nÖNEMLİ: Yeni fall-api.php'yi de yükle (profil/tarih orada).\nSONRA: opcache-reset.php. SİL: rm apply-clean-v3.php\n";
echo "Not: '✗ 0' varsa kalıp bulunamadı — haber ver.\n";
