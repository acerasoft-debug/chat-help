<?php
/** ChatHelp — dump-legalinfo: Datenschutz sayfasi icin GERCEK yasal bilgileri toplar. SADECE OKUR.
 *  (1) Impressum blogu (isim/adres/temsilci)  (2) hosting kaniti  (3) e-posta adresleri  */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-legalinfo BASLADI (PHP ".PHP_VERSION.")\n\n";

/* ── 1) Impressum icerigi: /chat/ + ust dizindeki dosyalarda ── */
echo "###### 1) IMPRESSUM ICERIGI ######\n";
$dirs = [__DIR__, dirname(__DIR__)];
foreach (['legal','recht','pages'] as $d) { $p = dirname(__DIR__).'/'.$d; if (is_dir($p)) $dirs[] = $p; }
$found = 0;
foreach ($dirs as $dir) {
    foreach ((array)@scandir($dir) as $f) {
        if (!preg_match('/\.(php|html?|htm)$/i', $f)) continue;
        $path = "$dir/$f";
        if (filesize($path) > 3_000_000) continue;
        $s = @file_get_contents($path); if ($s === false) continue;
        $ip = stripos($s, 'impressum');
        if ($ip === false) continue;
        // Impressum kelimesinin gectigi her nokta cevresinden pencere al (max 3 pencere/dosya)
        $shown = 0; $off = 0;
        while (($ip = stripos($s, 'impressum', $off)) !== false && $shown < 3) {
            $win = substr($s, max(0, $ip - 200), 2400);
            // etiketleri temizle, oku kolaylastir
            $txt = trim(preg_replace('/\s+/', ' ', strip_tags(str_ireplace(['<br>','<br/>','<br />','</div>','</p>','</h1>','</h2>','</h3>','</li>'], "\n", $win))));
            if (preg_match('/(inhaber|betreiber|vertret|anschrift|adresse|stra|weg\s?\d|\d{5}\s+[A-ZÄÖÜ]|LLC|GmbH|Bilgic)/iu', $txt)) {
                echo "\n--- $path (offset $ip) ---\n$txt\n";
                $found++; $shown++;
            }
            $off = $ip + 500;
        }
    }
}
if (!$found) echo "  (adres iceren Impressum blogu bulunamadi)\n";

/* Bilgic / Sontheim dogrudan aramasi (Impressum disinda da olabilir) */
echo "\n###### 1b) 'Bilgic' / 'Sontheim' / PLZ gecen yerler ######\n";
$hits = 0;
foreach ($dirs as $dir) {
    foreach ((array)@scandir($dir) as $f) {
        if (!preg_match('/\.(php|html?|htm)$/i', $f)) continue;
        $path = "$dir/$f";
        if (filesize($path) > 3_000_000) continue;
        $s = @file_get_contents($path); if ($s === false) continue;
        foreach (['Bilgic','Bilgiç','Sontheim'] as $needle) {
            $p = stripos($s, $needle);
            if ($p !== false) {
                $txt = trim(preg_replace('/\s+/', ' ', strip_tags(substr($s, max(0,$p-300), 700))));
                echo "\n--- $path [$needle] ---\n$txt\n";
                $hits++;
                break;
            }
        }
    }
}
if (!$hits) echo "  (bulunamadi)\n";

/* ── 2) Hosting kaniti ── */
echo "\n###### 2) HOSTING KANITI ######\n";
foreach (['SERVER_SOFTWARE','SERVER_NAME','SERVER_ADDR','DOCUMENT_ROOT','HOSTNAME','SERVER_ADMIN'] as $k) {
    echo "  $k = " . ($_SERVER[$k] ?? '(yok)') . "\n";
}
echo "  php_uname = " . php_uname() . "\n";
echo "  gethostname() = " . (function_exists('gethostname') ? gethostname() : '(yok)') . "\n";
$ip = $_SERVER['SERVER_ADDR'] ?? '';
if ($ip && function_exists('gethostbyaddr')) {
    echo "  reverse-DNS($ip) = " . @gethostbyaddr($ip) . "\n";
}
// MX kaydi: mail nereye gidiyor (chat-help.de)
foreach (['chat-help.de','chat-help.com'] as $dom) {
    $mx = [];
    if (function_exists('getmxrr') && @getmxrr($dom, $mx)) {
        echo "  MX($dom) = " . implode(', ', array_slice($mx, 0, 3)) . "\n";
    } else {
        echo "  MX($dom) = (okunamadi)\n";
    }
}

/* ── 3) Sitede kullanilan e-posta adresleri ── */
echo "\n###### 3) SITEDE GECEN E-POSTALAR ######\n";
$mails = [];
foreach ($dirs as $dir) {
    foreach ((array)@scandir($dir) as $f) {
        if (!preg_match('/\.(php|html?|htm)$/i', $f)) continue;
        $path = "$dir/$f";
        if (filesize($path) > 3_000_000) continue;
        $s = @file_get_contents($path); if ($s === false) continue;
        if (preg_match_all('/[a-z0-9._%+-]+@chat-help\.(de|com)/i', $s, $m)) {
            foreach ($m[0] as $em) { $em = strtolower($em); $mails[$em][] = basename($path); }
        }
    }
}
foreach ($mails as $em => $files) {
    echo "  $em  <- " . implode(', ', array_slice(array_unique($files), 0, 5)) . "\n";
}
if (!$mails) echo "  (bulunamadi)\n";

echo "\n=== BITTI. Ciktinin TAMAMINI bana gonder. SIL: rm dump-legalinfo.php ===\n";
