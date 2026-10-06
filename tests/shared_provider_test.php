<?php
/* Serbest posta saglayicisi (webmail / tuketici ISS) TEK kaynaktan:
 * vestra_email_is_shared_provider() (inc/notify.php).
 *
 * NEDEN VAR: 27 Eyl 2026 Fransa ayakkabi partisinde iki bagimsiz dukkan
 * (Laffite, Le Havre'daki Yves Saint Clair) "ayni firmaya zaten gitti" diye
 * SESSIZCE atlandi. Sebep: add-and-send'in firma tekillestirmesi (KURAL 1c) el
 * yazmasi bir liste tasiyordu ve wanadoo.fr / hotmail.fr o listede yoktu;
 * o saglayicilarda haftalar once UC ALAKASIZ dukkana mektup gitmisti
 * (leads_status ile olculdu). Depoda ayni sorunun cevabi YEDI ayri listedeydi
 * ve ayrismislardi. Sessiz eleme yanlis gonderimden pahali: kimse fark etmiyor.
 *
 * IKI YON: paylasimli saglayici MUAF olmali, ama firmanin kendi alan adi --
 * adi bir saglayiciya BENZESE bile ("outlookstore.com") -- firma kimligi
 * tasimaya devam etmeli; aksi halde ayni dukkanin ikinci kutusu ikinci soguk
 * mektubu alirdi (KURAL 1c'nin korudugu sey). */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };
$root = dirname(__DIR__);
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/notify.php';

echo "== 1. paylasimli saglayici MUAF ==\n";
foreach ([
  'a@wanadoo.fr'   => 'wanadoo.fr (27 Eyl vakasi)',
  'b@hotmail.fr'   => 'hotmail.fr (27 Eyl vakasi)',
  'c@orange.fr'    => 'orange.fr',
  'd@free.fr'      => 'free.fr',
  'e@laposte.net'  => 'laposte.net',
  'f@bluewin.ch'   => 'bluewin.ch',
  'g@libero.it'    => 'libero.it',
  'h@alice.it'     => 'alice.it',
  'i@telefonica.net' => 'telefonica.net',
  'j@skynet.be'    => 'skynet.be',
  'k@ziggo.nl'     => 'ziggo.nl',
  'l@wp.pl'        => 'wp.pl',
  'm@seznam.cz'    => 'seznam.cz',
  'n@sapo.pt'      => 'sapo.pt',
  'o@web.de'       => 'web.de',
  'p@t-online.de'  => 't-online.de',
  'q@gmail.com'    => 'gmail.com',
  'r@outlook.it'   => 'outlook.<ulke>',
  's@yahoo.co.uk'  => 'yahoo.co.<ulke>',
  't@yahoo.com.br' => 'yahoo.com.<ulke>',
  'u@gmx.at'       => 'gmx.<ulke>',
  'v@live.be'      => 'live.<ulke>',
  'w@icloud.com'   => 'icloud.com',
  'wanadoo.fr'     => 'yalin alan adi da kabul',
  'X@HotMail.FR'   => 'buyuk harf',
] as $in => $why) $t("MUAF: {$why}", vestra_email_is_shared_provider($in));

echo "\n== 2. firma alan adi MUAF DEGIL (benzese bile) ==\n";
foreach ([
  'info@schuhvoegel.at'      => 'siradan dukkan',
  'info@outlookstore.com'    => 'adi outlook ile baslayan firma',
  'info@live-shoes.de'       => 'adi live- ile baslayan firma',
  'info@hotmailshop.fr'      => 'adi hotmail ile baslayan firma',
  'info@mygmail.com'         => 'icinde gmail gecen firma',
  'x@yahoo.fr.example.com'   => 'saglayici adi ALT alan adi olarak',
  'x@orange.fr.shop.example' => 'orange.fr ALT alan adi olarak',
  'x@myorange.fr'            => 'orange.fr ile BITEN baska alan adi',
  'x@me.com.au'              => 'me.com benzeri, liste disi',
  ''                         => 'bos',
  'nodot'                    => 'noktasiz',
] as $in => $why) $t("FIRMA: {$why}", !vestra_email_is_shared_provider($in));

echo "\n== 3. is akislari TEK kaynaga bagli ==\n";
$aas = (string)file_get_contents($root.'/.github/workflows/add-and-send.yml');
$so  = (string)file_get_contents($root.'/.github/workflows/send-outreach.yml');
/* El yazmasi liste geri gelmesin: iki dosyada da bir saglayici alan adi
   DIZGE olarak durmamali (yorum disi). Tarama yorumlari da okuyor, o yuzden
   yorumlarda saglayici adi tirnak icinde yazilmadi. */
foreach (["'wanadoo.fr'", "'hotmail.com'", "'libero.it'", "'orange.fr'"] as $lit) {
  $t("add-and-send el yazmasi listeyi TASIMIYOR ({$lit})", !str_contains($aas, $lit));
  $t("send-outreach el yazmasi listeyi TASIMIYOR ({$lit})", !str_contains($so, $lit));
}
$t('add-and-send: firma haritasi (gecmis) fonksiyonu soruyor',
   str_contains($aas, "if (\$d2 === '' || vestra_email_is_shared_provider(\$d2) || isset(\$firmDone[\$d2])) continue;"));
$t('add-and-send: gonderim kapisi fonksiyonu soruyor',
   str_contains($aas, "if (\$dom !== '' && !vestra_email_is_shared_provider(\$dom) && isset(\$firmDone[\$dom])"));
$t('add-and-send: gonderim SONRASI harita guncellemesi fonksiyonu soruyor',
   str_contains($aas, "if (\$dsent !== '' && !vestra_email_is_shared_provider(\$dsent) && !isset(\$firmDone[\$dsent]))"));
$t('add-and-send: serbest posta adresinden site uretilmiyor',
   str_contains($aas, '$isFree  = vestra_email_is_shared_provider($domain);'));
$t('add-and-send: bilinen alan adi haritasi fonksiyonu soruyor',
   substr_count($aas, '!vestra_email_is_shared_provider($kw)') === 1 && substr_count($aas, '!vestra_email_is_shared_provider($kd)') === 1);
$t('send-outreach: soguk dal gecmis haritasi', str_contains($so, "if (\$d0 !== '' && !vestra_email_is_shared_provider(\$d0)) \$coldSeenDom[\$d0] = true;"));
$t('send-outreach: yeni koleksiyon kapisi',     str_contains($so, "if (\$ncDom !== '' && !vestra_email_is_shared_provider(\$ncDom)) {"));
$t('send-outreach: soguk kapi',                str_contains($so, "if (\$cdDom !== '' && !vestra_email_is_shared_provider(\$cdDom)) {"));
$t('send-outreach: satici alan adi kapisi',    str_contains($so, '!vestra_email_is_shared_provider($slDom)'));
/* Ayni soru uc is akisinda daha el yazmasiyla cevaplaniyordu. */
foreach (['add-lead.yml' => 'vestra_email_is_shared_provider($domain)',
          'purge-leads.yml' => "if (\$d === '' || vestra_email_is_shared_provider(\$d)) continue;",
          'check-registrations.yml' => '$isFree = vestra_email_is_shared_provider($dom);'] as $wf => $call) {
  $src = (string)file_get_contents($root.'/.github/workflows/'.$wf);
  $t("{$wf}: el yazmasi liste YOK", !str_contains($src, "'wanadoo.fr'") && !str_contains($src, "'gmail.com','googlemail.com'"));
  $t("{$wf}: fonksiyonu soruyor", str_contains($src, $call));
}
/* Tek kaynak gercekten tek: depoda baska bir saglayici listesi kalmadi. */
$others = [];
foreach (glob($root.'/.github/workflows/*.yml') as $f) if (str_contains((string)file_get_contents($f), "'wanadoo.fr'")) $others[] = basename($f);
$t('hicbir is akisi wanadoo.fr listesi tasimiyor', $others === []);

echo "\n".($bad ? "KIRMIZI: {$bad}\n" : '')."shared_provider_test: {$ok} iddia gecti".($bad ? ", {$bad} DUSTU" : '')."\n";
exit($bad ? 1 : 0);
