<?php
/**
 * VESTRA — günlük journal yazısı (operatör, 7 Eyl 2026: *"her gün otomatik
 * journal e paylaşım yap"*). Sunucu crontab'ı, 07:20 UTC.
 *
 * HER GÜN ÇALIŞIR, HER GÜN YAYIMLAMAZ. Yazının tamamı canlı ilan kaydından
 * türüyor (`inc/journal_auto.php`); söyleyecek gerçek bir şey yoksa hiçbir şey
 * yazılmaz. Gerekçe orada uzun uzun yazılı — özeti: her gün üretilen içi boş
 * bir yazı hem alan adının arama değerini düşürür (KURAL 9'un "sahte/ince
 * içerik" yasağı) hem de okuyucuya journal'ı atlamayı öğretir (KURAL 2c).
 *
 * `--dry` ile hiçbir şey yazmaz, ne üreteceğini basar. Deploy bunu kanarya
 * olarak koşuyor: fatal varsa deploy kırmızıya döner ve hatayı canlı cron'un
 * sessiz günlüğünde değil, dağıtımda görürüz.
 */
$dry = in_array('--dry', $argv ?? [], true);

require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/journal.php';
require_once __DIR__.'/inc/journal_auto.php';

$stamp = date('c');
$say = function (string $line) use ($stamp) { echo '['.$stamp.'] journal: '.$line."\n"; };

/* AYNI GÜN İKİNCİ YAZI YOK. Cron iki kez çalışırsa (elle tetikleme, sunucu
   yeniden denemesi) ikinci bir "bugünün raporu" çıkmamalı: aynı içeriğin iki
   kopyası hem okuyucuya hem arama motoruna aynı şeyi iki kez söyler. */
$already = vestra_journal_auto_today();
if ($already && !$dry) {
    $say('bugun zaten yazildi: '.($already['slug'] ?? '?').' -- ikinci yazi YOK');
    exit(0);
}

$art = vestra_journal_auto_build();
if (isset($art['skip'])) {
    $say('YAZI YOK -- '.$art['skip']);
    exit(0);
}

$say('baslik : '.$art['title']);
$say('slug   : '.$art['slug']);
$say('diller : en + '.implode(',', array_keys($art['i18n'])));
$say('kapak  : '.($art['cover'] !== '' ? $art['cover'] : '(uretilen kapak)'));

if ($dry) { $say('KURU KOSU -- hicbir sey yazilmadi'); exit(0); }

$saved = vestra_journal_save($art);
/* GERİ OKU. Yazdığını görmeden "yayimlandi" demek, bu depoda KURAL 5c'nin
   `billing_saved` dersi: kaydedilmemiş bir şeyi kaydedilmiş sanmak. */
$back = vestra_journal_find((string)($saved['slug'] ?? ''));
if (!$back || empty($back['published'])) {
    fwrite(STDERR, '['.$stamp."] journal: YAZILAMADI -- geri okuma tutmadi\n");
    exit(1);
}
$say('yayimlandi: https://vestrasales.com/journal?slug='.rawurlencode((string)$back["slug"]));
exit(0);
