<?php
/* IADE POLITIKASI — operator karari (4 Eyl 2026): B2B urunleri iadeye kapali;
   yalnizca YANLIS / EKSIK / HATALI malda iade; alici 3 GUN icinde bildirmek
   zorunda. Bu test o kuralin sitede TEK bir sayi olarak durmasini korur.

   Neden gerekiyor: kural dort ayri yerde yaziliydi ve UCU birbiriyle celisiyordu
   (uyusmazlik icin "5 is gunu", nakliye hasari icin "48 saat", escrow icin
   "2 is gunu"). Bir alici hangi sayiya bakacagini bilemez, bir uyusmazlikta da
   en uzun sureyi gosterir. Sayi tek yerden gelir: VESTRA_CLAIM_DAYS. */

$root = __DIR__ . '/../vestra';
require_once $root . '/inc/escrow.php';
require_once $root . '/inc/faq.php';
require_once $root . '/inc/legal.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   {$n}\n"; }
    else    { $fail++; echo "  KALDI {$n}\n"; }
};

echo "== 1. Sabit ve politika metni ayni sayiyi soyluyor ==\n";
$t('VESTRA_CLAIM_DAYS tanimli', defined('VESTRA_CLAIM_DAYS'));
$days = VESTRA_CLAIM_DAYS;
$t('gun sayisi 3', $days === 3);

$faq = vestra_faq_en();
$t("'returns' kategorisi var", isset($faq['returns']));
$items = $faq['returns']['items'] ?? [];
$t('politika detayli (>=15 madde)', count($items) >= 15);

$all = '';
foreach ($items as $i) $all .= ' ' . $i['q'] . ' ' . $i['a'];
$t("metin '{$days} days' diyor", str_contains($all, "{$days} days"));
$t('iadeye kapali oldugu yaziyor', stripos($all, 'closed to returns') !== false);
foreach (['wrong', 'missing', 'faulty'] as $w) {
    $t("gerekce sayiliyor: {$w}", stripos($all, $w) !== false);
}
$t('yetkisiz geri gonderim yasak', stripos($all, 'without written authorisation') !== false);

echo "\n== 2. Rakip sure kalmadi ==\n";
/* Bu uc ifade daha once ayni soruya UC farkli cevap veriyordu. */
$faqAll = '';
foreach ($faq as $c) foreach ($c['items'] as $i) $faqAll .= ' ' . $i['a'];
$t('SSS\'de "5 business days" yok', !str_contains($faqAll, '5 business days'));
$t('SSS\'de "48 hours" yok',        !str_contains($faqAll, '48 hours'));
$legal = vestra_legal_en();
$legalAll = '';
foreach ($legal as $d) $legalAll .= ' ' . $d['html'];
$t('Sozlesme\'de sabit escrow suresi yok', !str_contains($legalAll, '2 business days after the'));

echo "\n== 3. Para, sikayet hakki bitmeden cikmiyor ==\n";
/* Eski kural yalnizca 2 IS GUNU idi ve takvimde 3 gunden KISA olabiliyordu:
   Pazartesi teslimatta Carsamba serbest, oysa alicinin hakki Persembe aksamina
   kadar suruyordu -- yani odeme pencerenin ORTASINDA yapiliyordu. */
$bad = 0; $shown = 0;
for ($d = 0; $d < 14; $d++) {
    $ts  = strtotime('2026-09-07 10:00') + $d * 86400;   // Pazartesi'den 14 gun
    $biz = vestra_business_days_after($ts, 2);
    $win = $ts + $days * 86400;
    $new = max($biz, $win);
    if ($new < $win) $bad++;
    if ($biz < $win && $shown < 2) { $shown++; }
}
$t('yeni kural hicbir gunde pencereden once odemiyor', $bad === 0);
$t('eski kural en az bir gunde erken odardi (duzeltmenin sebebi)', $shown > 0);
$src = file_get_contents($root . '/inc/escrow.php');
$t('supurucu sabiti gercekten kullaniyor',
   (bool)preg_match('/\$deadline\s*=\s*max\(\s*vestra_business_days_after\([^)]*\),\s*\$dts\s*\+\s*VESTRA_CLAIM_DAYS/s', $src));

echo "\n== 4. Baglanti her yerde, kural tek yerde ==\n";
$link = '/faq?cat=returns';
foreach ([
    'altbilgi (her sayfa)' => '/inc/foot.php',
    'urun sayfasi'         => '/product.php',
    'sepet'                => '/cart.php',
] as $lbl => $f) {
    $t("{$lbl} politikaya baglaniyor", str_contains(file_get_contents($root . $f), $link));
}
$t('Sozlesme politikayi kapsiyor', str_contains($legal['terms']['html'], $link));
$t('Odeme belgesi politikaya baglaniyor', str_contains($legal['payments']['html'], $link));
/* Kural metni SADECE SSS'de: baglanti veren sayfalar sureyi TEKRAR YAZMAMALI,
   yoksa iki kopya er gec birbirinden ayrilir. */
foreach (['/inc/foot.php', '/product.php', '/cart.php'] as $f) {
    $t("kural {$f} icinde tekrar edilmiyor",
       !preg_match('/\b' . $days . '\s*(days|gun)\b/i', file_get_contents($root . $f)));
}

echo "\n== 5. SSS cevaplari duz metin ==\n";
/* faq.php cevaplari nl2br(htmlspecialchars(...)) ile basiyor: bir cevaba HTML
   koyulursa kullaniciya HAM ETIKET gorunur. Canlida tam bu olmustu. */
$htmlN = 0;
foreach ($faq as $c) foreach ($c['items'] as $i) if (preg_match('/<[a-z][a-z0-9]*\b[^>]*>/i', $i['a'])) $htmlN++;
$t('hicbir cevapta HTML etiketi yok', $htmlN === 0);

echo "\n== 6. Olu dosya geri gelmedi ==\n";
/* inc/legal/en.php hicbir zaman yuklenmiyordu (vestra_legal() Ingilizce icin
   once donuyor) ama icinde doldurulmamis yer tutucular ve canli metinle celisen
   bir escrow suresi vardi -- dispatcher bir gun "duzeltilse" site onlari basardi. */
$t('inc/legal/en.php yok', !file_exists($root . '/inc/legal/en.php'));

echo "\n== 7. Politika 8 dilde ==\n";
/* Ceviri dosyalari inc/faq/{lang}.php. vestra_faq() eksik bir maddeyi SESSIZCE
   Ingilizceye dusurur -- yani bir ceviri yarim kalirsa sayfa yine calisir ve
   kimse fark etmez. Alicinin okudugu tek belge bu oldugu icin, her dilde her
   maddenin gercekten cevrilmis olmasi burada zorunlu tutuluyor.
   Dil basina AYRI SUREC gerekiyor: vlang() ilk cagrida sabitleniyor, tek
   surecte donguye alinirsa sekiz dilin sekizi de "Ingilizce" olarak olculur --
   ilk olcumde tam bu oldu ve ceviriler bozukmus gibi gorundu. */
$enItems = $faq['returns']['items'];
$php = PHP_BINARY ?: 'php';
foreach (['de','fr','it','es','pt','ru','ar'] as $lang) {
    $code = '$_GET=["lang"=>' . var_export($lang, true) . '];'
          . 'require ' . var_export($root . '/inc/faq.php', true) . ';'
          . '$r=vestra_faq()["returns"] ?? null;'
          . 'echo json_encode([$r["title"] ?? "", array_column($r["items"] ?? [], "q")]);';
    $out = shell_exec(escapeshellarg($php) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null');
    $got = json_decode((string)$out, true);
    if (!is_array($got)) { $t("{$lang}: bolum okunabildi", false); continue; }
    [$title, $qs] = $got;
    $t("{$lang}: baslik cevrilmis", $title !== '' && $title !== $faq['returns']['title']);
    $t("{$lang}: 18 madde", count($qs) === count($enItems));
    $same = 0;
    foreach ($qs as $i => $q) if (isset($enItems[$i]) && $q === $enItems[$i]['q']) $same++;
    $t("{$lang}: Ingilizce kalan madde yok", $same === 0);
}

$n = $ok + $fail;
echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
