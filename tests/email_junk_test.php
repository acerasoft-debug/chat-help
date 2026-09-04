<?php
/* vestra_email_is_junk(): harvested addresses that must never receive a campaign,
   and real ones that must survive. The filter is the last gate before a send --
   a false positive silently drops a paying prospect, a false negative burns a
   daily credit on a software vendor and invites a spam complaint. Both directions
   are asserted here for that reason. */
$src = file_get_contents(__DIR__.'/../vestra/inc/notify.php');
preg_match('/function vestra_email_is_junk.*?\n}/s', $src, $m); eval($m[0]);

/* GECMESI SART: bu havuzda duran ya da gercekten yazisilmis adresler. */
$must_pass = [
  // 4 Eyl 2026 Winter 26/27 secimindeki dogru adaylar
  'info@dropdayz.be', 'info@factoryoutlet.gr', 'hello@sorrythanksiloveyou.com',
  'uomo@antonia.it', 'info@urbanstaroma.com',
  // ayni gun soguk listede kalan tek aday
  'info@elevastor.com',
  // platform adresleri engelli ama o platformda BARINAN kendi alan adi engelli degil
  'info@mijnwinkel.be', 'contact@boutique-lyon.fr',
  // kendi adreslerimiz
  'support@vestrasales.com', 'acerasoft@gmail.com',
  // 'info'/'mail'/'contact' bilerek junk DEGIL -- gercek genel kutular
  'mail@szykszok.pl', 'contact@colony.work',
];

/* ELENMESI SART: her biri canli bir kampanyada yakalandi. */
$must_fail = [
  // 4 Eyl 2026: posta alan adi "www." ile basliyor -- sayfanin host'u, kutu degil
  'info@www.amorinikids.be', 'contact@www.example-shop.fr',
  // magazanin sayfasina gomulu UCUNCU TARAF saticinin destek adresi
  'support@glood.ai',        // Shopify kisisellestirme uygulamasi ("INTRO" leadi)
  'info@virtualminds.com',   // ProSiebenSat.1 reklam teknolojisi ("Mi.na" leadi)
  // daha once yakalanmis siteyi kuran platform adresleri (gerileme korumasi)
  'support@jouwweb.nl', 'blog@wordpress.com', 'domains@loopia.com',
  // iletisim formu yer tutuculari ve cevaplanamayan rol kutulari
  'your-email@example.com', 'nom.prenom@boutique.fr', 'noreply@shop.it',
  // bicimsel olarak adres bile degil
  '', 'no-at-sign.com', '--@shop.com',
];

$bad = 0;
foreach ($must_pass as $e) {
  if (vestra_email_is_junk($e)) { $bad++; echo "  YANLIS ELENDI (junk sayildi): {$e}\n"; }
}
foreach ($must_fail as $e) {
  if (!vestra_email_is_junk($e)) { $bad++; echo "  YANLIS GECTI (junk sayilmadi): ".($e === '' ? '(bos)' : $e)."\n"; }
}
$n = count($must_pass) + count($must_fail);
if ($bad) { echo "email_junk_test: {$bad}/{$n} iddia KALDI\n"; exit(1); }
echo "email_junk_test: {$n} iddia gecti\n";
