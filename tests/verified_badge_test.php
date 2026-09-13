<?php
/* "Verified seller" rozeti BEYAZ FOTOĞRAFIN üstünde okunmalı
 * (operatör, 13 Eyl 2026: "daha okunakli, beyaz ustude durdugundan rengi degissin").
 *
 * Rozet ürün fotoğrafının üstünde duruyor ve bu kataloğun fotoğraflarının neredeyse
 * tamamı beyaz fonlu paket çekimi. Eski hâli açık yeşil zemin + #1f7a4c yazıydı:
 * 4.46:1, AA eşiği 4.5'in altında. Asıl kusur ise tik işaretiydi — stroke="#fff",
 * yani açık yeşil üstünde BEYAZ tik (1.06:1, pratikte görünmez).
 *
 * Test RENK SEÇİMİNİ değil OKUNABİLİRLİĞİ ölçüyor: hangi yeşil seçilirse seçilsin
 * beyaz üzerinde 4.5:1'i geçmek zorunda. Bir sonraki "biraz daha açık olsun"
 * isteğinde eşiği geçip geçmediğini tahmin etmek gerekmesin.
 */
$root = dirname(__DIR__);
$css  = (string)file_get_contents($root.'/vestra/inc/style.css');

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
/* WCAG bağıl parlaklık + kontrast oranı. */
$lum = function (array $rgb): float {
    $c = [];
    foreach ($rgb as $v) {
        $v /= 255;
        $c[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126*$c[0] + 0.7152*$c[1] + 0.0722*$c[2];
};
$ratio = function (array $fg, array $bg) use ($lum): float {
    $a = $lum($fg); $b = $lum($bg);
    return (max($a,$b) + 0.05) / (min($a,$b) + 0.05);
};
$hex = fn(string $h): array => [hexdec(substr($h,1,2)), hexdec(substr($h,3,2)), hexdec(substr($h,5,2))];
/* Yarı saydam zemini ALTINDAKİ renkle harmanla — rozetin arkasındaki asıl yüzey
   beyaz paket fotoğrafı, yani harman beyazla yapılır. */
$over = fn(array $c, float $a, array $under): array => [
    (int)round($a*$c[0] + (1-$a)*$under[0]),
    (int)round($a*$c[1] + (1-$a)*$under[1]),
    (int)round($a*$c[2] + (1-$a)*$under[2]),
];

echo "\n== 1. Kural tek ve kendi rengini taşıyor ==\n";
$t('.svbadge ile .gal-vbadge AYNI kuralda', str_contains($css, '.svbadge,.gal-vbadge{'));
/* Ayni rozetin iki ayri tanimi er gec ayrisir; fotografli karo icin olan
   override tam bunu yapiyordu (biri #7ad6a0, digeri #1f7a4c). */
$t('fotoğraflı karo için AYRI renk override YOK', !str_contains($css, '.sthumb.sphoto .svbadge{'));
preg_match('/\.svbadge,\.gal-vbadge\{(.*?)\}/s', $css, $m);
$rule = $m[1] ?? '';
$t('kural bulundu', $rule !== '');
preg_match('/color:(#[0-9a-fA-F]{6})/', $rule, $cm);
preg_match('/background:rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rule, $bm);
$t('yazı rengi tanımlı', !empty($cm[1]));
$t('zemin tanımlı (yarı saydam)', !empty($bm[4]));

echo "\n== 2. Beyaz fotoğraf üstünde AA'yı geçiyor ==\n";
$fg   = $hex($cm[1]);
$bgEff = $over([(int)$bm[1],(int)$bm[2],(int)$bm[3]], (float)$bm[4], [255,255,255]);
$r = $ratio($fg, $bgEff);
printf("  olculen kontrast: %.2f:1  (yazi %s, zemin rgb(%d,%d,%d))\n", $r, $cm[1], ...$bgEff);
$t('kontrast >= 4.5 (AA, küçük yazı)', $r >= 4.5);
$t('kontrast >= 7 (AAA — 10px kalın yazı bunu hak ediyor)', $r >= 7.0);
/* Eski hali DUSSUN diye duruyor: ayni olcut eski renklerle 4.5'i gecmiyordu. */
$oldFg = $hex('#1f7a4c');
$oldBg = $over([28,120,72], 0.10, [251,250,248]);
$oldR  = $ratio($oldFg, $oldBg);
printf("  eski hali       : %.2f:1  (karsilastirma icin)\n", $oldR);
$t('eski renk gerçekten AA altındaydı (ölçüt ayırt ediyor)', $oldR < 4.5);

echo "\n== 3. Zemin fotoğrafa bırakılmıyor ==\n";
$t('opaklık >= .9 (fotoğraf sızmıyor)', (float)$bm[4] >= 0.9);
$t('blur var (kenar yumuşak)', str_contains($rule, 'backdrop-filter:blur'));
$t('webkit ön eki de var', str_contains($rule, '-webkit-backdrop-filter'));
$t('gölge var (beyaz üstünde beyaz hap kaybolmasın)', str_contains($rule, 'box-shadow'));
$t('ince kenarlık var', str_contains($rule, 'border:1px solid'));

echo "\n== 4. Mühür rozetin rengini alıyor, işaretleme TEK kaynakta ==\n";
/* stroke="#fff" acik zeminde gorunmez bir tik demekti; currentColor yaziyla
   ayni rengi alir, yani rozet nerede olursa olsun muhur de okunur.
   Isaretleme 13 Eyl 2026'da bes sayfada bes kopyaydi ve renk duzeltmesi bes
   yerde ayri ayri yapilmak zorunda kalmisti -- artik tek yardimci fonksiyon
   (vestra_verified_badge), sayfalar onu cagiriyor. */
$prod = (string)file_get_contents($root.'/vestra/inc/products.php');
$t('yardımcı fonksiyon var',        str_contains($prod, 'function vestra_verified_badge('));
$t('mühür currentColor kullanıyor', str_contains($prod, 'stroke="currentColor"'));
$t('ikon ekran okuyucudan gizli',   str_contains($prod, 'aria-hidden="true"'));
$pages = ['shop.php'=>1, 'product.php'=>2, 'showroom.php'=>2];
$whites = 0; $calls = 0; $inline = 0;
foreach ($pages as $pg => $want) {
    $src = (string)file_get_contents($root.'/vestra/'.$pg);
    $whites += substr_count($src, 'stroke="#fff"');
    $n = substr_count($src, 'vestra_verified_badge(');
    $calls += $n;
    $t("{$pg}: {$want} çağrı", $n === $want);
    /* Sayfada elle yazilmis bir rozet kalmasi, renk duzeltmesinin o kopyayi
       atlamasi demek -- kusurun ilk halinin sebebi tam buydu. */
    if (preg_match('/class="(sv|gal-v)badge"[^>]*>\s*<svg/', $src)) $inline++;
}
$t('hiçbir sayfada BEYAZ stroke kalmadı', $whites === 0);
$t('elle yazılmış rozet işaretlemesi yok', $inline === 0);
$t('toplam 5 çağrı', $calls === 5);

echo "\n".($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
