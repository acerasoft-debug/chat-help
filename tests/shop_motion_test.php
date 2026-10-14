<?php
/**
 * Katalog hareket sahnesi (operatör, 14 Eki 2026: "çok daha etkili bir animasyon kataloğa koy").
 * Kaynağa bakar: sahne yalnız html.mo varken (JS + "hareketi azalt" kapalı) çalışır; başlık kelime kelime, ızgarada
 * dalga + perde + bulanıktan netleşme, filtrede yeniden dalga, fareli masaüstünde 3B eğim, kaydırma çizgisi.
 * Görsel doğrulama yerelde headless Chromium ile yapıldı (1440 / 390 px, en/de/ar: yatay kaydırma 0, JS hatası 0).
 */
$ok = 0; $bad = 0;
$t = function (string $name, bool $c) use (&$ok, &$bad) { echo ($c ? '  ok   ' : '  FAIL ').$name."\n"; $c ? $ok++ : $bad++; };
$src = (string)file_get_contents(__DIR__.'/../vestra/shop.php');
$t('html.mo ızgaradan ÖNCE, yalnız "hareketi azalt" kapalı ve IntersectionObserver varken', (bool)preg_match("/<div class=\"wrap wide shopwrap\">\\s*<script>try\\{ if\\(!\\(window\\.matchMedia && matchMedia\\('\\(prefers-reduced-motion: reduce\\)'\\)\\.matches\\) && 'IntersectionObserver' in window\\) document\\.documentElement\\.classList\\.add\\('mo'\\);/", $src)
   && strpos($src, "classList.add('mo')") < strpos($src, 'id="shopgrid"'));
$t('gizli başlangıç durumu YALNIZ .mo altında (JS\'siz katalog olduğu gibi görünür)', str_contains($src, '.mo .shopgrid .scard:not(.in){opacity:0;transform:translateY(46px) scale(.955)}')
   && !preg_match('/(?<!\.mo )\.shopgrid \.scard:not\(\.in\)\{opacity:0/', $src));
$t('başlık kelime kelime; ekran okuyucu tüm başlığı aria-label\'dan okur, kelimeler aria-hidden', str_contains($src, "h.setAttribute('aria-label', txt);") && str_contains($src, '<span class="hw" aria-hidden="true">'));
$t('perde + bulanıktan netleşme + dalga gecikmesi (12 karta kadar 70 ms)', str_contains($src, ".scurt{position:absolute;inset:0;z-index:7;pointer-events:none") && str_contains($src, 'filter:blur(9px)')
   && str_contains($src, 'var d = Math.min(i, 11) * 0.07;'));
$t('dalga bitince .done: hover geçişleri yeniden normal hızda', str_contains($src, '.mo .shopgrid .scard.in:not(.done){transition:') && str_contains($src, '.mo .shopgrid .scard:not(.done) .sthumbi:not(.sthumbi-reveal){transition:'));
$t('filtre/marka/sıralama: orijinal applyFilters çağrılır, sonra ekrandaki kartlar yeniden dalgalanır', str_contains($src, 'orig.apply(this, arguments);') && str_contains($src, "c.classList.remove('in', 'open', 'done')") && str_contains($src, 'show(vis, true)'));
/* 14 Eki 2026 (2): once marka karti, asagi inince urun. */
$t('perde = marka karti (marka basina tek sablon, JS kopyalar)', str_contains($src, '<template id="bcardtpl">') && str_contains($src, 'vestra_brand_card($_bn)') && str_contains($src, "cu.innerHTML = bmap[bk]"));
$t('iki adim: .in marka karti, .open urun (perde/foto/yazi .open\'a bagli)', str_contains($src, '.mo .shopgrid .scard:not(.open) .scurt{clip-path:inset(0 0 0 0)}') && str_contains($src, '.mo .shopgrid .scard:not(.open) .sthumbi:not(.sthumbi-reveal){transform:scale(1.16);filter:blur(9px)}') && str_contains($src, '.mo .shopgrid .scard:not(.open) .sbody>*{opacity:0;'));
$t('acilis kaydirmaya bagli (%62 cizgisi) + ilk ekran icin ilk kaydirma / 2,4 sn yedegi + en az 1,1 sn marka karti', str_contains($src, "rootMargin: '0px 0px -38% 0px'") && str_contains($src, 'setTimeout(firstScroll, 2400)') && str_contains($src, '+ 1100 - Date.now()'));
$t('3B eğim yalnız fareli masaüstü, dalga bitmeden başlamaz, çıkınca sıfırlanır', str_contains($src, "matchMedia('(hover: hover) and (pointer: fine)')") && str_contains($src, "if (!c.classList.contains('done')) return;")
   && str_contains($src, "c.classList.remove('tilt'); c.style.transform = '';"));
$t('kaydırma çizgisi pasif dinleyici + rAF; RTL\'de sağdan', str_contains($src, '{ passive: true }') && str_contains($src, '[dir="rtl"] .scprog{transform-origin:100% 50%}'));
$t('hata olursa sahne kendini kapatır (kartlar görünür kalır)', str_contains($src, "}catch(e){ document.documentElement.classList.remove('mo'); } })();"));
$rm = substr($src, (int)strpos($src, '@media(prefers-reduced-motion:reduce){', (int)strpos($src, 'KATALOG HAREKET SAHNESİ')));
$t('CSS de "hareketi azalt"ta her şeyi kapatır', str_contains($rm, '.scurt,.sglare,.scprog{display:none}') && str_contains($rm, 'opacity:1;transform:none;filter:none'));
$t('PHP sözdizimi', str_contains((string)shell_exec('php -l '.escapeshellarg(__DIR__.'/../vestra/shop.php').' 2>&1'), 'No syntax errors'));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
