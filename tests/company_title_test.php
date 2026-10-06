<?php
/* vestra_company_from_title(): sayfa basligindan firma adi.
   Bu ad kampanya mektubunun ilk satirina giriyor ("Hello <firma>,"), yani buradaki
   bir hata dogrudan aliciya gorunuyor. 4 Eyl 2026'da "Αρχική" (Yunanca "Anasayfa")
   bir lead'in firma adi olarak kaydedilmisti. */
$src = file_get_contents(__DIR__.'/../vestra/inc/notify.php');
preg_match('/function vestra_company_from_title.*?\n}/s', $src, $m); eval($m[0]);

$cases = [
  // [baslik, alan adi, beklenen]
  ['Αρχική | FACTORY OUTLET',          'factoryoutlet.gr', 'FACTORY OUTLET'],
  ['Home - Urbanstaroma',              'urbanstaroma.com', 'Urbanstaroma'],
  ['Accueil – Boutique Marie',         'marie.fr',         'Boutique Marie'],
  ['Startseite | Modehaus Weber',      'weber.de',         'Modehaus Weber'],
  ['Главная — Мода Опт',               'moda.ru',          'Мода Опт'],
  ['Anasayfa | Aceras Butik',          'aceras.com.tr',    'Aceras Butik'],
  // genel ad DEGILSE ilk parca aynen kalir
  ['ANTONIA | Luxury Boutique Milano', 'antonia.it',       'ANTONIA'],
  ['Sessùn',                           'dropdayz.be',      'Sessùn'],
  // "Boutique" tek basina genel, ama bir adin PARCASI ise korunur
  ['Boutique | Chez Lucie',            'lucie.fr',         'Chez Lucie'],
  ['Boutique Lucie | Accueil',         'lucie.fr',         'Boutique Lucie'],
  // her parca genelse alan adina dusulur
  ['Home | Shop',                      'someshop.com',     'someshop.com'],
  ['Welcome',                          'someshop.com',     'someshop.com'],
  ['',                                 'someshop.com',     'someshop.com'],
  // bosluklar sadelestirilir
  ["  Home  |   Chic   Store  ",       'chic.com',         'Chic Store'],
];

$bad = 0;
foreach ($cases as [$title, $domain, $want]) {
  $got = vestra_company_from_title($title, $domain);
  if ($got !== $want) { $bad++; echo "  BEKLENEN \"{$want}\" — GELEN \"{$got}\"  (baslik: \"{$title}\")\n"; }
}
// 60 karakter siniri
$long = str_repeat('A', 80);
$got = vestra_company_from_title($long, 'x.com');
if (mb_strlen($got) !== 60) { $bad++; echo "  uzun baslik 60'a kirpilmadi: ".mb_strlen($got)."\n"; }

$n = count($cases) + 1;
if ($bad) { echo "company_title_test: {$bad}/{$n} iddia KALDI\n"; exit(1); }
echo "company_title_test: {$n} iddia gecti\n";
