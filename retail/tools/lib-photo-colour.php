<?php
/**
 * Aday karenin rengi ürünün rengiyle uyuyor mu — ORTAK KİTAPLIK
 * =============================================================
 * check-attach-colour.php (rapor) ve attach-orphan-photos.php (yazan)
 * aynı kararı vermek zorunda. Ayrı kopyalar olsaydı rapor "kabul" deyip
 * yazan başka bir şey yapabilirdi; eşleştirme kuralında tam olarak bu
 * tehlike vardı, onun için o da tek yerde duruyor.
 *
 * KARAR KURALI, ÖLÇÜMLE SEÇİLDİ
 * -----------------------------
 * Bir kare, YALNIZCA ELDE DELİL VARSA reddediliyor:
 *   · ürünün mevcut karesinde giysi gerçekten görünüyorsa (özne ≥ %10), VE
 *   · aday karede de görünüyorsa (özne ≥ %10), VE
 *   · iki yönlü renk payı %12'nin altındaysa.
 *
 * Beyaz giysi beyaz zeminde ölçülemiyor — zemin çıkarılınca giysi de
 * gidiyor. Orada "renk tutmadı" demek yanlış olurdu, kare bırakılıyor.
 *
 * Eşikler tahmin değil: 307 adayın tamamı ölçüldü, en riskli uçlar kolaja
 * basılıp gözle bakıldı. Tek yönlü ölçüm bordo bir tişörte beyaz kareleri
 * %13 payla geçiriyordu; çift yönlü ölçüm onu düşürüyor ve mankenli doğru
 * çekimleri tutuyor.
 */

declare(strict_types=1);

/**
 * Özne piksellerini döndür: [[r,g,b], …] ve özne oranı.
 *
 * ORTALAMA RENK NİYE YETMİYOR
 * ---------------------------
 * İlk sürüm öznenin ortalama rengini alıp iki kareyi karşılaştırıyordu.
 * Ölçüldü: mesafesi en yüksek 14 adayın 13'ü gerçekten yanlış renk
 * varyantıydı — ama 14'üncüsü DOĞRUYDU: kırmızı Lacoste polonun mankenli
 * çekimi. Orada ortalamayı ten rengi ve kot kaydırıyor, giysi hâlâ kırmızı.
 * Ortalamaya bakan bir eşik o kareyi de atardı.
 *
 * Onun için soru değişti: "ortalamalar yakın mı" değil, "ürünün rengi bu
 * karede VAR MI". Mankenli çekimde kırmızı geniş bir alan kaplıyor, cevap
 * evet; turuncu tişörtün yerine gelen beyaz karede cevap hayır.
 */
function cac_pixels(string $abs): ?array
{
    $raw = @file_get_contents($abs);
    if ($raw === false) return null;
    $src = @imagecreatefromstring($raw);
    if (!$src) return null;

    $w = imagesx($src); $h = imagesy($src);
    $n = 64;
    $im = imagecreatetruecolor($n, $n);
    imagecopyresampled($im, $src, 0, 0, 0, 0, $n, $n, $w, $h);
    imagedestroy($src);

    $br = $bg = $bb = $bc = 0;
    for ($i = 0; $i < $n; $i++) {
        foreach ([[$i, 0], [$i, $n - 1], [0, $i], [$n - 1, $i]] as [$x, $y]) {
            $c = imagecolorat($im, $x, $y);
            $br += ($c >> 16) & 255; $bg += ($c >> 8) & 255; $bb += $c & 255; $bc++;
        }
    }
    $br = (int)($br / $bc); $bg = (int)($bg / $bc); $bb = (int)($bb / $bc);

    $px = [];
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            $c = imagecolorat($im, $x, $y);
            $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
            if (abs($r - $br) + abs($g - $bg) + abs($b - $bb) < 40) continue;
            $px[] = [$r, $g, $b];
        }
    }
    imagedestroy($im);
    return [$px, count($px) / ($n * $n)];
}

/** Öznenin BASKIN rengi: 32'lik kutulara böl, en kalabalık kutunun ortalaması. */
function cac_dominant(array $px): ?array
{
    if (!$px) return null;
    $bin = [];
    foreach ($px as [$r, $g, $b]) {
        $k = (($r >> 5) << 6) | (($g >> 5) << 3) | ($b >> 5);
        $bin[$k][0] = ($bin[$k][0] ?? 0) + $r;
        $bin[$k][1] = ($bin[$k][1] ?? 0) + $g;
        $bin[$k][2] = ($bin[$k][2] ?? 0) + $b;
        $bin[$k][3] = ($bin[$k][3] ?? 0) + 1;
    }
    uasort($bin, static fn($a, $b) => $b[3] <=> $a[3]);
    $top = reset($bin);
    return [(int)($top[0] / $top[3]), (int)($top[1] / $top[3]), (int)($top[2] / $top[3])];
}

/** Referans rengin adayda kapladığı pay. */
function cac_presence(array $px, array $ref): float
{
    if (!$px) return 0.0;
    $hit = 0;
    foreach ($px as $p) if (cac_dist($p, $ref) < 90) $hit++;
    return $hit / count($px);
}

/** İki rengin kabaca algısal uzaklığı. */
function cac_dist(array $a, array $b): float
{
    // Yesile agirlik: goz yesildeki farki daha cok goruyor.
    $dr = $a[0] - $b[0]; $dg = $a[1] - $b[1]; $db = $a[2] - $b[2];
    return sqrt(2 * $dr * $dr + 4 * $dg * $dg + 3 * $db * $db);
}


/**
 * Aday kare ürüne uyuyor mu?
 * @return array{0:string,1:float} [karar, renk payı]
 */
function cac_decide(array $refPx, float $refFrac, array $candPx, float $candFrac): array
{
    $refCol = cac_dominant($refPx);
    $dom    = cac_dominant($candPx);
    if (!$refCol || !$dom) return ['kabul', 1.0];

    $pres = min(cac_presence($candPx, $refCol), cac_presence($refPx, $dom));

    // Yazı kartı: özne az yer kaplıyor VE mürekkep gibi koyu.
    $ink = ($dom[0] + $dom[1] + $dom[2]) / 3;
    if ($candFrac < 0.20 && $ink < 110) return ['RED-yazi', $pres];

    if ($refFrac >= 0.10 && $candFrac >= 0.10 && $pres < 0.12) return ['RED-renk', $pres];
    return ['kabul', $pres];
}
