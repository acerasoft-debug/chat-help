<?php
/* Kapak 800x520 ama makale kahramaninda 21:9 kirpiliyor: yalnizca y 88..431
   goruluyor. Disarida kalan cizim sessizce kesilir -- kimse fark etmez, kapak
   sadece "bos" gorunur. Bu test icerigin pencerede kaldigini dogrular.
   Tam zemin dolgusu ve dekoratif izgara HARIC: onlarin tasmasi kasitli. */
$f = __DIR__.'/../vestra/uploads/journal/art-selling-on-instagram-and-facebook-what-actually-moves-stock-cover.svg';
if (!is_readable($f)) { fwrite(STDERR, "kapak yok: $f\n"); exit(1); }
$x = simplexml_load_file($f);
if (!$x) { fwrite(STDERR, "gecersiz SVG\n"); exit(1); }

$lo = INF; $hi = -INF; $n = 0;
$note = function ($a, $b) use (&$lo, &$hi, &$n) { $lo = min($lo, $a); $hi = max($hi, $b); $n++; };

foreach ($x->xpath('//*') as $el) {
    $tag = $el->getName();
    if ($tag === 'rect') {
        $w = (float)$el['width'];
        if ($w >= 800) continue;                       // tam zemin
        $note((float)$el['y'], (float)$el['y'] + (float)$el['height']);
    } elseif ($tag === 'circle') {
        $note((float)$el['cy'] - (float)$el['r'], (float)$el['cy'] + (float)$el['r']);
    } elseif ($tag === 'path') {
        $d = (string)$el['d'];
        if (str_contains($d, 'h800')) continue;        // dekoratif izgara
        /* "M x y" ile baslar, "v dy" o y'den itibaren ilerler. */
        if (preg_match_all('/M\s*(-?[\d.]+)\s+(-?[\d.]+)((?:\s*v\s*-?[\d.]+)*)/', $d, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $y = (float)$m[2]; $a = $y; $b = $y;
                if (preg_match_all('/v\s*(-?[\d.]+)/', $m[3], $vs)) {
                    foreach ($vs[1] as $dv) { $y += (float)$dv; $a = min($a, $y); $b = max($b, $y); }
                }
                $note($a, $b);
            }
        }
    }
}
printf("olculen eleman : %d\n", $n);
printf("icerik y       : %.0f - %.0f\n", $lo, $hi);
printf("21:9 penceresi : 88 - 431\n");
$ok = $n >= 8 && $lo >= 88 && $hi <= 431;
echo $ok ? "SONUC: pencereye sigiyor ✓\n" : "SONUC: TASIYOR ✗\n";
exit($ok ? 0 : 1);
