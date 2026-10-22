<?php
/** ChatHelp — dump-gentest: dilekce uretimini SUNUCUDA gercekten test eder.
 *  Kendi api.php'sine gercek bir generate cagrisi yapar ve ciktiyi denetler:
 *  cevaplar kullanildi mi, gonderen/muhatap dogru mu, placeholder kaldi mi.
 *  SADECE test cagrisi yapar; siteyi degistirmez. Calismasi 30-90 sn surebilir. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(180);
echo "dump-gentest BASLADI (PHP ".PHP_VERSION.") — AI cagrisi 30-90 sn surebilir, bekleyin...\n\n";
@ob_flush(); @flush();

$payload = [
  'docType' => 'kuendigung_vertrag',
  'docName' => 'Kündigung Internetvertrag',
  'answers' => [
    'Kundennummer'   => 'TESTKD-4472019',
    'Vertragsnummer' => 'TESTVT-88231',
    'Kündigungsgrund'=> 'Umzug ins Ausland',
    'datum'          => '04.07.2026',
    'ch_empfaenger'  => "Telekom Deutschland GmbH\nKundenservice\n53171 Bonn",
  ],
  'profile' => ['f1'=>'Max','f2'=>'Testmann','f3'=>'Teststraße 9','f4'=>'10115 Berlin','f7'=>'max.testmann@example.com'],
  'lang' => 'de', 'country' => 'DE', 'tone' => 'standard',
];

$url = 'https://chat-help.com/chat/api.php?action=generate&_nc=' . microtime(true);
$ctx = stream_context_create(['http' => [
  'method' => 'POST',
  'header' => "Content-Type: application/json\r\n",
  'content'=> json_encode($payload),
  'timeout'=> 150,
]]);
$t0 = microtime(true);
$raw = @file_get_contents($url, false, $ctx);
$dt = round(microtime(true) - $t0, 1);
echo "HTTP cagri suresi: {$dt}s\n";
if ($raw === false) exit("HATA: api.php cagrisi basarisiz (HTTP hatasi).\n");

$d = json_decode($raw, true);
if (!is_array($d)) { echo "HAM CIKTI (JSON degil, ilk 1500):\n".substr($raw,0,1500)."\n"; exit; }
if (isset($d['error'])) { echo "API HATASI: ".$d['error']."\n(Not: plan/oturum kisiti olabilir — bu da onemli bir bulgu.)\n"; exit; }

$doc = $d['document'] ?? ($d['text'] ?? ($d['doc'] ?? ''));
if (!$doc) { echo "Belge alani bos. Yanit anahtarlari: ".implode(', ', array_keys($d))."\nIlk 800: ".substr($raw,0,800)."\n"; exit; }

echo "Belge uzunlugu: ".strlen($doc)." karakter\n\n";
echo "################ OTOMATIK DENETIM ################\n";
$checks = [
  'Kundennummer kullanildi (TESTKD-4472019)'   => strpos($doc,'TESTKD-4472019') !== false,
  'Vertragsnummer kullanildi (TESTVT-88231)'   => strpos($doc,'TESTVT-88231') !== false,
  'Gonderen adi gecti (Max Testmann)'          => stripos($doc,'Testmann') !== false,
  'Gonderen adresi gecti (Teststraße 9)'       => strpos($doc,'Teststraße 9') !== false,
  'Muhatap gecti (Telekom)'                    => stripos($doc,'Telekom') !== false,
  'Tarih gecti (04.07.2026)'                   => strpos($doc,'04.07.2026') !== false || strpos($doc,'4.7.2026') !== false,
  'Kanun maddesi var (§)'                      => strpos($doc,'§') !== false,
];
$fail = 0;
foreach ($checks as $k => $ok) { echo ($ok?'  ✓ ':'  ✗ ')."$k\n"; if(!$ok)$fail++; }

/* placeholder kaldi mi */
if (preg_match_all('/\[(VORNAME|NACHNAME|ADRESSE|NAME|ORT|PLZ|STRASSE|EMPFÄNGER|DATUM)[^\]]*\]/u', $doc, $m)) {
  echo "  ✗ PLACEHOLDER KALDI: ".implode(', ', array_unique($m[0]))."\n"; $fail++;
} else { echo "  ✓ Placeholder kalmadi\n"; }

/* rol karismasi: kullanici Antragsgegner olarak mi gecmis? */
if (preg_match('/Antragsgegner[^\n]{0,80}Testmann/u', $doc) || preg_match('/Testmann[^\n]{0,40}Antragsgegner/u', $doc)) {
  echo "  ✗ ROL KARISMASI: kullanici 'Antragsgegner' olarak gecmis!\n"; $fail++;
} else { echo "  ✓ Rol karismasi yok (kullanici Antragsgegner degil)\n"; }

echo "\nSONUC: ".($fail ? "$fail SORUN VAR ✗" : "TUM KONTROLLER GECTI ✓")."\n";
echo "\n################ URETILEN BELGE (ilk 2200) ################\n";
echo substr($doc, 0, 2200) . (strlen($doc)>2200 ? "\n…(kirpildi)" : '') . "\n";
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-gentest.php ===\n";
