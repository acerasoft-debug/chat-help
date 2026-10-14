<?php
/**
 * Ana sayfa hareket katmanı (operatör, 14 Eki 2026: "VESTRA'nın ana sayfasına premium bir animasyon koy").
 * Kaynağa bakar: açılış sırası, altın çizgi, vurgu ışığı (italik kuyruğu kesilmesin), imleç spotu, sayan rakamlar,
 * kart ışığı — ve her birinin "hareketi azalt"ta kapalı olduğu, rakamların sayfada GERÇEK değerle basıldığı.
 * Görsel doğrulama yerelde headless Chromium ile yapıldı (masaüstü 1440 / telefon 390; ar, de, fr, ja, ru: yatay kaydırma 0).
 */
$ok = 0; $bad = 0;
$t = function (string $name, bool $c) use (&$ok, &$bad) { echo ($c ? '  ok   ' : '  FAIL ').$name."\n"; $c ? $ok++ : $bad++; };
$src = (string)file_get_contents(__DIR__.'/../vestra/index.php');
$css = substr($src, 0, (int)strpos($src, '</style>'));   // ilk stil bloğu

$t('açılış: etiket → manşet → çizgi → metin → teklif → düğmeler → güven satırı (gecikmeler artan)',
   (bool)preg_match('/\.hero>\.wrap>\.pill\{animation:vDrop[^}]*\.1s both\}/', $css) && str_contains($css, '.hero h1{animation:vRiseH 1.25s')
   && str_contains($css, 'animation:vRule 1.2s') && str_contains($css, '.hero>.wrap>p{animation:vRise 1s cubic-bezier(.2,.7,.1,1) .62s both}')
   && str_contains($css, '.hero>.wrap>.btns{animation:vRise 1s cubic-bezier(.2,.7,.1,1) .92s both}') && str_contains($css, '.hero>.wrap>.trustline{animation:vRise 1s cubic-bezier(.2,.7,.1,1) 1.08s both}'));
preg_match_all('/@keyframes (v[A-Z]\w*)\{((?:[^{}]*\{[^}]*\})+)\}/', $css, $kf);
$kfBad = array_filter($kf[2], fn($b) => (bool)preg_match('/(?:height|width|margin|padding|top|left)\s*:/', $b));
$t('6 keyframe, hepsi yalnız transform/opacity/filter/background-position (sayfa kaymaz)', count($kf[1]) === 6 && !$kfBad);
$t('vurgu ışığı: background-clip desteğine bağlı, italik kuyruğu için kutu genişletilmiş (negatif kenar ile)',
   str_contains($css, '@supports ((-webkit-background-clip:text) or (background-clip:text))') && str_contains($css, 'padding:0 .14em .06em .06em;margin:0 -.14em -.06em -.06em'));
$t('altın çizgi manşetin hemen altında, ekran okuyucudan gizli', str_contains($src, "<h1><?= \$t['h1'] ?></h1>\n    <span class=\"hero-rule\" aria-hidden=\"true\"></span>"));
$t('imleç spotu: kahramanın içinde, tıklamayı engellemez, yalnız fareli cihazda', str_contains($src, '<div class="hero-glow" aria-hidden="true"></div>')
   && str_contains($css, '.hero-glow{position:absolute;inset:0;z-index:1;pointer-events:none') && str_contains($src, "matchMedia('(hover: hover) and (pointer: fine)')"));
$t('rakamlar: sayfada gerçek değer basılı + data-n; JS saydıktan sonra aynı metne döner', substr_count($src, '<b data-n="') === 3
   && str_contains($src, "b.textContent = k < 1 ? fmt(Math.round(to * e)) : final;"));
$t('kart ışığı yalnız hover cihazında', str_contains($css, '@media(hover:hover){ .ed-card:hover .ed-ph::before{animation:vCard'));
$rm = substr($css, (int)strrpos($css, '@media(prefers-reduced-motion:reduce)'));
$t('"hareketi azalt": açılış, ışık, kart ışığı kapalı; spot gizli', str_contains($rm, '.hero h1 .acc,.ed-card:hover .ed-ph::before{animation:none}') && str_contains($rm, '.hero-glow{display:none}'));
$t('JS de "hareketi azalt"ı dinliyor ve hata tüm sayfayı düşürmesin diye try içinde',
   (bool)preg_match("/try\\{\\s*var rmM = window\\.matchMedia && matchMedia\\('\\(prefers-reduced-motion: reduce\\)'\\)\\.matches;/", $src));
$t('PHP sözdizimi', str_contains((string)shell_exec('php -l '.escapeshellarg(__DIR__.'/../vestra/index.php').' 2>&1'), 'No syntax errors'));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
