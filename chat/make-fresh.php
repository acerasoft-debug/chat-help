<?php
/**
 * ChatHelp — Cache kanıtı: index.php'nin TAZE (cache'siz) bir kopyasını üretir.
 * Yeni/rastgele isimli olduğu için Cloudflare onu hiç cache'lememiştir →
 * origin'den taze gelir. Bu kopyada staatliche AÇILIYORSA, sorun %100 cache.
 *
 * KULLANIM: html/chat/ içine yükle -> chat-help.com/chat/make-fresh.php aç
 * İŞ BİTİNCE hem bu dosyayı hem üretilen fresh-*.php'yi SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$src = @file_get_contents(__DIR__ . '/index.php');
if ($src === false) { exit("HATA: index.php okunamadı.\n"); }

// Eski fresh-*.php kopyalarını temizle
foreach (glob(__DIR__ . '/fresh-*.php') as $old) { @unlink($old); }

$name = 'fresh-' . substr(md5(uniqid('', true)), 0, 8) . '.php';
$ok = file_put_contents(__DIR__ . '/' . $name, $src);

if ($ok === false) { exit("HATA: kopya oluşturulamadı (yazma izni?).\n"); }

echo "=== Cache Kanıt Testi ===\n\n";
echo "Taze (cache'siz) kopya oluşturuldu:\n\n";
echo "  https://chat-help.com/chat/$name\n\n";
echo "ŞUNU YAP:\n";
echo "  1) Yukarıdaki adresi GİZLİ pencerede aç.\n";
echo "  2) Staatliche Formulare -> Jobcenter -> Bürgergeld tıkla.\n\n";
echo "SONUÇ:\n";
echo "  • AÇILIYORSA  -> sorun %100 CACHE. Cloudflare 'Purge Everything' yap.\n";
echo "  • AÇILMIYORSA -> cache değil; bana haber ver (başka bir şey var).\n\n";
echo "Bana söyle: bu taze linkte staatliche açıldı mı, açılmadı mı?\n";
echo "\nTest bitince sil:  rm $name make-fresh.php\n";
