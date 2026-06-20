<?php
/**
 * ChatHelp — Akordeonu geri al (eski kategori grid'ine dön)
 * apply-home-accordion'un eklediği CSS + inline kodu kaldırır.
 * Bewerbung/Verträge kategorileri KALIR (CATS'te, eski grid'de görünür).
 * KULLANIM: chat-help.com/chat/apply-home-revert.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-accrevert-' . date('Ymd-His'), $src);
$rep=[];

/* 1) Premium CSS bloğunu kaldır (eski grid'i gizleyen display:none burada) */
$c1=0;
$src=preg_replace('/<style id="ch-acc-css">.*?<\/style>\s*/s', '', $src, 1, $c1);
$rep[]=['#1 akordeon CSS kaldırıldı', $c1];

/* 2) Inline akordeon bloğunu kaldır */
$c2=0;
$src=preg_replace('/\n?\/\* CH_ACC_INLINE.*?\}\)\(\);\}catch\(e\)\{\}/s', '', $src, 1, $c2);
$rep[]=['#2 inline akordeon kaldırıldı', $c2];

$changed=($src!==$start);
if($changed){
    // güvenlik: PHP linti (kapanış bozulmasın)
    $tmp=tempnam(sys_get_temp_dir(),'chk').'.php'; file_put_contents($tmp,$src);
    $lint=shell_exec('php -l '.escapeshellarg($tmp).' 2>&1'); @unlink($tmp);
    if($lint!==null && strpos($lint,'No syntax errors')===false){ echo "‼️ SÖZDİZİMİ HATASI — kaydedilmedi:\n$lint\n"; exit; }
    file_put_contents($file,$src);
}
echo "ChatHelp — Akordeon geri alma raporu\n====================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: eski grid geri geldi. ✅\n":"DURUM: Değişiklik yok (akordeon zaten yoktu).\n");
echo "Bewerbung/Verträge kategorileri grid'de görünür (CATS'te kayıtlı).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-home-revert.php apply-home-accordion.php\n";
