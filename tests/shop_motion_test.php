<?php
/**
 * Katalog hareket sahnesi (operatör, 14 Eki 2026: "çok daha etkili bir animasyon kataloğa koy").
 * Kaynağa bakar: sahne yalnız html.mo varken (JS + "hareketi azalt" kapalı) çalışır; başlık kelime kelime, ızgarada
 * kaydırma çizgisi. Kart efektleri (dalga, perde, bulanıklık, 3B eğim) 29 Eki 2026'da operatör isteğiyle kaldırıldı.
 * Görsel doğrulama yerelde headless Chromium ile yapıldı (1440 / 390 px, en/de/ar: yatay kaydırma 0, JS hatası 0).
 */
$ok = 0; $bad = 0;
$t = function (string $name, bool $c) use (&$ok, &$bad) { echo ($c ? '  ok   ' : '  FAIL ').$name."\n"; $c ? $ok++ : $bad++; };
$src = (string)file_get_contents(__DIR__.'/../vestra/shop.php');
$t('html.mo ızgaradan ÖNCE, yalnız "hareketi azalt" kapalı ve IntersectionObserver varken', (bool)preg_match("/<div class=\"wrap wide shopwrap\">\\s*<script>try\\{ if\\(!\\(window\\.matchMedia && matchMedia\\('\\(prefers-reduced-motion: reduce\\)'\\)\\.matches\\) && 'IntersectionObserver' in window\\) document\\.documentElement\\.classList\\.add\\('mo'\\);/", $src)
   && strpos($src, "classList.add('mo')") < strpos($src, 'id="shopgrid"'));
/* 29 Eki 2026 (operator: "catalogtaki urunlerin karti acilip kapanmasini yani animasyonu yada effekti kaldirabilirsin"):
   KART efektleri kalkti -- perde, dalga, bulaniktan netlesme, yazi acilisi, filtrede yeniden dalga, 3B egim + isik.
   Kartlar ilk cizimden son hallerinde; baslik animasyonu ve kaydirma cizgisi duruyor. Bu iddialar geri gelmesini yakalar. */
$t('kart hicbir durumda gizli/bulanik baslamaz (.in/.open/.done kurali yok)', !preg_match('/\.scard(:not\()?\.(in|open|done)\b/', $src) && !str_contains($src, 'filter:blur(9px)'));
$t('perde + isik yansimasi + marka karti sablonu yok', !str_contains($src, 'scurt') && !str_contains($src, 'sglare') && !str_contains($src, 'bcardtpl'));
$t('kart dalgasi, filtrede yeniden dalga ve 3B egim JS\'i yok', !str_contains($src, 'show(vis, true)') && !str_contains($src, 'firstScroll') && !str_contains($src, "classList.add('tilt')") && !str_contains($src, 'rotateX('));
$t('baslik kelime animasyonu ve marka seridi girisi DURUYOR', str_contains($src, '.mo .sphead h1 .hw>span{display:inline-block;animation:cWord') && str_contains($src, '.mo .brail-cell{animation:cCell'));
$t('kaydırma çizgisi pasif dinleyici + rAF; RTL\'de sağdan', str_contains($src, '{ passive: true }') && str_contains($src, '[dir="rtl"] .scprog{transform-origin:100% 50%}'));
$t('hata olursa sahne kendini kapatır (kartlar görünür kalır)', str_contains($src, "}catch(e){ document.documentElement.classList.remove('mo'); } })();"));
$rm = substr($src, (int)strpos($src, '@media(prefers-reduced-motion:reduce){', (int)strpos($src, 'KATALOG HAREKET SAHNESİ')));
$t('CSS de "hareketi azalt"ta baslik animasyonunu ve cizgiyi kapatir', str_contains($rm, '.scprog{display:none}') && str_contains($rm, '.mo .brail-cell{animation:none}'));
$t('PHP sözdizimi', str_contains((string)shell_exec('php -l '.escapeshellarg(__DIR__.'/../vestra/shop.php').' 2>&1'), 'No syntax errors'));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
