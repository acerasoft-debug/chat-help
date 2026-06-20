<?php
/**
 * ChatHelp — index.php'ye no-cache başlığı (tarayıcı eski sürümü göstermesin)
 * Her ziyarette taze HTML -> "eski mod" sorunu biter.
 * KULLANIM: chat-help.com/chat/apply-nocache.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-nocache-' . date('Ymd-His'), $src);

if (strpos($src,'CH_NOCACHE')!==false){ exit("Zaten ekli.\n"); }

$hdrs = "header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); /*CH_NOCACHE*/";

$trim = ltrim($src);
if (substr($trim,0,5) === '<?php') {
    // ilk <?php'den hemen sonra ekle (çıktıdan önce)
    $pos = strpos($src, '<?php');
    $src = substr($src,0,$pos+5) . "\n" . $hdrs . "\n" . substr($src,$pos+5);
    $how = "mevcut <?php bloğuna eklendi";
} else {
    // başa yeni <?php bloğu koy
    $src = "<?php " . $hdrs . " ?>\n" . $src;
    $how = "başa yeni <?php bloğu eklendi";
}

$changed=($src!==$start);
if($changed){
    $tmp=tempnam(sys_get_temp_dir(),'chk').'.php'; file_put_contents($tmp,$src);
    $lint=shell_exec('php -l '.escapeshellarg($tmp).' 2>&1'); @unlink($tmp);
    if($lint!==null && strpos($lint,'No syntax errors')===false){ echo "‼️ SÖZDİZİMİ HATASI — kaydedilmedi:\n$lint\n"; exit; }
    file_put_contents($file,$src);
}
echo "ChatHelp — no-cache raporu\n==========================\n\n";
echo ($changed?"  ✓ no-cache başlığı eklendi ($how)\n":"  ✗ değişiklik yok\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"\n");
echo "Artık tarayıcı eski HTML saklamaz; her güncelleme anında görünür.\n";
echo "SONRA: opcache-reset.php + Safari'de Cmd+Option+E ile bir kez temizle. SİL: rm apply-nocache.php\n";
