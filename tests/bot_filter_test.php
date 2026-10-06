<?php
/* ZIYARETCI SAYACININ BOT SUZGECI — her iki yon.
 *
 * Operator, 8 Eyl 2026: *"US · Mountain View, böyle biri sürekli siteye giriyor
 * her gün — bu gerçek bir kişi mi yoksa Google bot mu? araştır ve IP'sine bak."*
 *
 * Canli erisim kutugunden olculdu: Googlebot dogru sekilde atlaniyordu, ama
 * Google'in DIGER ajanlari kimliklerini dizgenin SONUNDAKI parantezde
 * "GoogleOther" / "Google-Read-Aloud" / "Google-Site-Verification" diye yaziyor
 * ve hicbirinde 'bot' ya da 'crawl' gecmiyor. Govdeleri gercek Googlebot'unkiyle
 * birebir ayni (Nexus 5X / Android 10), yani ilk bakista ayirt edilmiyorlar.
 * Sonuc: hepsi ZIYARETCI olarak sayiliyordu — Eylul kutugunde 1.415, Agustos'ta
 * 1.207 istek; 4 Eylul'de gunun 1.394 "benzersiz ziyaretcisinin" 620'si.
 *
 * TERS YON EN AZ O KADAR ONEMLI. Bu depoda kayitli mango/zara dersi: fazla genis
 * bir kalip gercek olani sessizce eler. Burada elenmemesi gerekenler asagida tek
 * tek yaziyor — ozellikle iPhone'da Google uygulamasindan gezen gercek bir kisi
 * ('GSA/'), cunku o bir musteri olabilir ve 'google' kelimesini tasiyor.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/../vestra/inc/security.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

/* Gercek Googlebot'un govdesi — asagidaki ajanlarin cogu bunu aynen tasiyor ve
   yalnizca sondaki parantezle ayriliyor. Testin okunur olmasi icin bir kez. */
$NEXUS = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
       . '(KHTML, like Gecko) Chrome/151.0.7999.173 Mobile Safari/537.36';

echo "== 1. Sayilmamasi gerekenler (bot / otomat) ==\n";
$bots = [
    'Googlebot (masaustu)'      => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Googlebot (akilli telefon)'=> $NEXUS.' (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Googlebot-Image'           => 'Googlebot-Image/1.0',
    /* CANLI KUTUKTE EN COK GORULEN — sayacin yarisini bu uretiyordu. */
    'GoogleOther'               => $NEXUS.' (compatible; GoogleOther)',
    'GoogleOther (yalin)'       => 'GoogleOther',
    'Google-Read-Aloud'         => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) '
                                 . 'Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; '
                                 . '+https://support.google.com/webmasters/answer/1061943)',
    'Google-Site-Verification'  => 'Mozilla/5.0 (compatible; Google-Site-Verification/1.0)',
    'Google-InspectionTool'     => $NEXUS.' (compatible; Google-InspectionTool/1.0)',
    'Google-Extended'           => 'Mozilla/5.0 (compatible; Google-Extended)',
    'Google-Lens'               => 'Google-Lens',
    'Gmail resim vekili'        => 'Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)',
    'Google Favicon'            => 'Mozilla/5.0 (X11; U; Linux x86_64) AppleWebKit/534.24 Google Favicon',
    'Google Web Preview'        => 'Mozilla/5.0 (en-us) AppleWebKit/534.14 (KHTML, like Gecko; Google Web Preview) Version/5.0 Safari/534.14',
    'AppEngine-Google'          => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppEngine-Google; '
                                 . '(+http://code.google.com/appengine; appid: s~virustotalcloud)',
    'Chrome-Lighthouse'         => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/128.0.0.0 Safari/537.36 Chrome-Lighthouse',
    'Feedfetcher-Google'        => 'Feedfetcher-Google; (+http://www.google.com/feedfetcher.html)',
    /* Once de tutuluyordu: gerileme olmasin. */
    'Bingbot'                   => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'AhrefsBot'                 => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
    'curl'                      => 'curl/8.0.1',
    'bos UA'                    => '',
];
foreach ($bots as $label => $ua) $t("{$label} atlaniyor", vestra_is_bot($ua) === true);

echo "\n== 2. SAYILMASI gerekenler (gercek kisi) ==\n";
/* Sessiz eleme, yanlis saymaktan pahali: kimse fark etmiyor. Asagidakilerden
   biri 'bot' sayilirsa gercek bir toptanci ziyaretcisi rakamdan dusuyor. */
$humans = [
    'Chrome (masaustu)'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    'Safari (iPhone)'        => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    /* iPhone'da GOOGLE UYGULAMASINDAN gezen gercek kisi. 'GSA/' tasiyor ve
       'google.com' yazan bir referans URL'i olabiliyor -- genis bir 'google'
       kalibi tam olarak bu musteriyi elerdi. */
    'Google uygulamasi (GSA)'=> 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/319.0.665880616 Mobile/15E148 Safari/604.1',
    'Android Chrome'         => 'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
    'Android WebView'        => 'Mozilla/5.0 (Linux; Android 13; Pixel 7; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/128.0.0.0 Mobile Safari/537.36',
    'Firefox (Windows)'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
    'Edge'                   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0',
    'Samsung Internet'       => 'Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36',
];
foreach ($humans as $label => $ua) $t("{$label} SAYILIYOR", vestra_is_bot($ua) === false);

echo "\n== 3. Kalip genel: yarinki bir 'Google-Xyz' de tutulmali ==\n";
/* Google surekli yeni ajan cikariyor; adlarin hepsini tek tek yazmak, bir
   sonraki adin sessizce sayaca girmesi demek. */
$t('bilinmeyen Google-Xyz atlaniyor', vestra_is_bot('Mozilla/5.0 (compatible; Google-SomethingNew/1.0)') === true);
$t('bilinmeyen Google-Xyz (Nexus govdeli)', vestra_is_bot($NEXUS.' (compatible; Google-FutureAgent)') === true);
/* Ama 'google' kelimesi TEK BASINA yetmemeli. */
$t('adinda google gecen gercek tarayici SAYILIYOR',
   vestra_is_bot('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/128.0.0.0 Mobile Safari/537.36 googleapp-shell') === false);

echo "\n== 4. Sayac gercekten bu suzgecten geciyor ==\n";
/* Suzgeci duzeltip cagiran yeri unutmak, hicbir sey duzeltmemektir. */
$src = (string)@file_get_contents(__DIR__ . '/../vestra/inc/security.php');
$t('vestra_track_visit ilk is bot kontrolu yapiyor',
   (bool)preg_match('/function vestra_track_visit.*?if \(vestra_is_bot\(\$ua\)\) return;/s', $src));
$t('bot kontrolu cografi sorgudan ONCE',
   strpos($src, 'if (vestra_is_bot($ua)) return;') < strpos($src, '$intel = vestra_ip_intel($ip, 1);'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
