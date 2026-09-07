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
/* IS GUNU (operator, 6 Eyl 2026: "hafta sonlari sayilmasin"). Metin "3 business
   days" demeli; "3 days" tek basina eski takvim kuralinin kalintisidir. */
$t("metin '{$days} business days' diyor", str_contains($all, "{$days} business days"));
/* Yeni metin "Business days, not calendar days" der -- 'calendar day' gecer ama
   kural olarak degil, karsitlik olarak. Yasak olan ESKI acilis: "Calendar days,
   not business days". Ilk yazimim her 'calendar day'i yasaklayip dogru metni
   kirmizi boyadi. */
$t("eski 'Calendar days, not business days' kalmadi", stripos($all, 'Calendar days, not business days') === false);
$t("'Saturdays and Sundays' sayilmadigi yaziyor",    stripos($all, 'Saturdays and Sundays') !== false);
$t("'Friday ... Monday' ornegi kalmadi",   !preg_match('/Friday[^.]*by Monday/i', $all));
$t("'the seller is paid' kalmadi (havale siparisinde zaten yanlisti)", stripos($all, 'the seller is paid') === false);
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
/* Pencere IS GUNU (6 Eyl 2026). Eski takvim kurali Cuma teslimatta Pazartesi
   diyordu; simdi Cmt/Paz sayilmaz -> Carsamba. Ve para, pencere bitmeden
   HICBIR gunde cikmamali: 14 gun boyunca her teslim gunu icin denenir. */
$bad = 0;
for ($d = 0; $d < 14; $d++) {
    $ts  = strtotime('2026-09-07 10:00') + $d * 86400;   // Pazartesi'den 14 gun
    if (escrow_release_deadline($ts) < vestra_claim_deadline($ts)) $bad++;
}
$t('serbest birakma hicbir gunde pencereden once degil', $bad === 0);
$t('Cuma teslimat -> Carsamba (hafta sonu sayilmaz)',
   date('D', vestra_claim_deadline(strtotime('2026-09-04 12:00'))) === 'Wed');
$t('Pazartesi teslimat -> Persembe',
   date('D', vestra_claim_deadline(strtotime('2026-09-07 12:00'))) === 'Thu');
$src = file_get_contents($root . '/inc/escrow.php');
$t('supurucu TEK kaynagi kullaniyor (escrow_release_deadline -> vestra_claim_deadline)',
   str_contains($src, '$deadline = escrow_release_deadline($dts)')
   && (bool)preg_match('/function escrow_release_deadline.*?vestra_claim_deadline\(\$deliveredTs\)/s', $src));
$t('pencere sabiti IS GUNU ile hesaplaniyor',
   (bool)preg_match('/function vestra_claim_deadline.*?vestra_business_days_after\(\$deliveredTs,\s*VESTRA_CLAIM_DAYS\)/s', $src));
$t('kodda takvim gunu carpani kalmadi', !preg_match('/VESTRA_CLAIM_DAYS\s*\*\s*86400/', $src . file_get_contents($root . '/inc/claims.php')));

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

echo "\n== 7. Politika sitenin BUTUN dillerinde ==\n";
/* Ceviri dosyalari inc/faq/{lang}.php. vestra_faq() eksik bir maddeyi SESSIZCE
   Ingilizceye dusurur -- yani bir ceviri yarim kalirsa sayfa yine calisir ve
   kimse fark etmez. Alicinin okudugu tek belge bu oldugu icin, her dilde her
   maddenin gercekten cevrilmis olmasi burada zorunlu tutuluyor.
   Dil basina AYRI SUREC gerekiyor: vlang() ilk cagrida sabitleniyor, tek
   surecte donguye alinirsa dillerin hepsi "Ingilizce" olarak olculur --
   ilk olcumde tam bu oldu ve ceviriler bozukmus gibi gorundu.
   DIL LISTESI ELLE YAZILMAZ: burada sabit bir liste duruyordu ve site
   dokuzuncu dili (ja) aldiginda liste guncellenmedi -- yani yeni dil bu
   kontrolun disinda kaldi ve FAQ'i hic cevrilmemis olsa da test yesil
   kalirdi. vlang_list() tek dogruluk kaynagi; yeni bir dil eklendigi anda
   burasi da onu sorar. */
$enItems = $faq['returns']['items'];
$php = PHP_BINARY ?: 'php';
require_once $root . '/inc/i18n.php';
/* Dil KODU anahtarda duruyor, deger ekranda gorunen etiket ('en' => 'EN').
   Ilk yazimimda degerleri okudum: 'EN' hicbir zaman 'en'e esit olmadigi icin
   Ingilizce listede kaldi ve ?lang=FR gibi buyuk harfli kodlar vlang()
   tarafindan taninmadi -- dokuz dilin dokuzu da "cevrilmemis" cikti. Ceviriler
   dogruydu, olcum yanlisti. */
$langs = array_values(array_diff(array_keys(vlang_list()), ['en']));
$t('dil listesi vlang_list()ten geliyor (' . count($langs) . ' dil)', count($langs) >= 8);
foreach ($langs as $lang) {
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
