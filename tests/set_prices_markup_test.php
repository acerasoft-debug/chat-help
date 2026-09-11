<?php
/* set-prices'in ZAM (markup_pct) ve BÖLME (section) süzgeci.
 *
 * Neden ayri bir test: zam CANLI katalogun fiyatlarini yaziyor ve discount_pct'in
 * aksine TEKRARLANABILIR DEGIL -- indirim tabani olarak 'list'i okuyup onu
 * degistirmiyordu, yani iki kosu ayni sonucu veriyordu. Zamda taban bugunku
 * fiyattir ve zam onu degistirir: ayni is iki kez calisirsa %20 degil %44 olur.
 * Damga (markup_at/markup_pct) tam bunu tutuyor ve asagida iki yonu de olculuyor.
 *
 * Betik GERCEK haliyle kosturuluyor (scripts/set_prices.php) -- aritmetik burada
 * kopyalansaydi kod degisince test yine gecerdi.
 */
$root = dirname(__DIR__);
$listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) {
    fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var. Test kendi katalogunu yaziyor;\n"
                 . "yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n");
    exit(1);
}
@mkdir(dirname($listings), 0777, true);

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

require_once $root.'/vestra/inc/products.php';

$seed = function () use ($listings) {
    file_put_contents($listings, json_encode([
        ['id'=>'uw-1','brand'=>'NBB','cat'=>'Underwear','section'=>'underwear','mode'=>'fixed',
         'moq'=>12,'list'=>4.50,'eur_margin_pct'=>50,
         'tiers'=>[['min'=>12,'price'=>4.50],['min'=>60,'price'=>4.20]]],
        ['id'=>'uw-2','brand'=>'NBB','cat'=>'Bras','section'=>'underwear','mode'=>'sale',
         'moq'=>6,'list'=>12.00,'tiers'=>[['min'=>6,'price'=>9.00]]],
        ['id'=>'uw-3','brand'=>'NBB','cat'=>'Socks & Hosiery','section'=>'underwear','mode'=>'offer',
         'moq'=>10,'list'=>5.99,'sample_price'=>9.90,'tiers'=>[['min'=>10,'price'=>5.99]]],
        ['id'=>'uw-4','brand'=>'NBB','cat'=>'Sleepwear','section'=>'underwear','mode'=>'fixed',
         'moq'=>6,'list'=>7.00],
        ['id'=>'uw-5','brand'=>'NBB','cat'=>'Basics','section'=>'underwear','mode'=>'fixed','moq'=>6],
        ['id'=>'pr-1','brand'=>'Lacoste','cat'=>'Polos','mode'=>'fixed',
         'moq'=>10,'list'=>89.90,'tiers'=>[['min'=>10,'price'=>79.90]]],
        ['id'=>'fw-1','brand'=>'Pili Pérez','cat'=>'Sandals','section'=>'footwear','mode'=>'fixed',
         'moq'=>1,'list'=>14.50,'tiers'=>[['min'=>1,'price'=>14.50]]],
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
};

/* Betik $HOME/public_html bekliyor (sunucu duzeni). */
$home = sys_get_temp_dir().'/vestra_setprices_test_'.getmypid();
@mkdir($home, 0777, true);
@symlink($root.'/vestra', $home.'/public_html');

$run = function (array $in) use ($home, $root): array {
    $blob = array_merge([
        'brand'=>'*','cat'=>'','nameq'=>'','price'=>'','list_price'=>'','discount_pct'=>'',
        'section'=>'','markup_pct'=>'','force'=>'false','show_prices'=>'true',
        'moq'=>'','step'=>'','sizes'=>'',
        'offers'=>'','dry'=>'true',
    ], $in);
    $cmd = 'HOME='.escapeshellarg($home)
         . ' P_JSON='.escapeshellarg(base64_encode(json_encode($blob)))
         . ' php '.escapeshellarg($root.'/scripts/set_prices.php').' 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};
$byId = function () use ($listings): array {
    $m = [];
    foreach (json_decode((string)file_get_contents($listings), true) ?: [] as $p) $m[$p['id']] = $p;
    return $m;
};

echo "\n== 1. Bölme süzgeci: yalnız underwear ==\n";
$seed();
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'20']);
$t('kosu basarili (cikis 0)',                        $rc === 0);
$t('5 underwear urunu eslesti, digerleri degil',     str_contains($out, 'filtreye uyan urun: 5'));
$t('premium urun ciktida yok',                       !str_contains($out, 'pr-1'));
$t('footwear urun ciktida yok',                      !str_contains($out, 'fw-1'));
$t('DRY RUN oldugunu yaziyor',                       str_contains($out, 'DRY RUN'));
$t('tekrar uyarisi basiliyor',                       str_contains($out, 'iki kez calistirilirsa'));
$a = $byId();
$t('DRY: uw-1 fiyati DEGISMEDI',                     abs($a['uw-1']['list'] - 4.50) < 0.001);
$t('DRY: damga da yazilmadi',                        !isset($a['uw-1']['markup_at']));

echo "\n== 2. Aritmetik: her kademe ve 'list' aynı oranda ==\n";
$seed();
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'20','dry'=>'false']);
$t('kosu basarili (cikis 0)',                        $rc === 0);
$t('KAYDEDILDI yaziyor',                             str_contains($out, 'KAYDEDILDI'));
$a = $byId();
$t('uw-1 list 4.50 -> 5.40',                         abs($a['uw-1']['list'] - 5.40) < 0.001);
$t('uw-1 kademe-1 4.50 -> 5.40',                     abs($a['uw-1']['tiers'][0]['price'] - 5.40) < 0.001);
$t('uw-1 kademe-2 4.20 -> 5.04',                     abs($a['uw-1']['tiers'][1]['price'] - 5.04) < 0.001);
$t('uw-1 "from" fiyati tam %20 arttı',               abs(vestra_from_price($a['uw-1']) - 5.04) < 0.001);
$t('uw-1 MOQ degismedi',                             (int)$a['uw-1']['moq'] === 12);
$t('uw-3 5.99 -> 7.19 (yukari yuvarlama)',           abs($a['uw-3']['tiers'][0]['price'] - 7.19) < 0.001);
$t('uw-4 kademesiz urunde list 7.00 -> 8.40',        abs($a['uw-4']['list'] - 8.40) < 0.001);
$t('uw-4 kademe alani uydurulmadi',                  !isset($a['uw-4']['tiers']));
$t('premium urun ELLENMEDI',                         abs($a['pr-1']['list'] - 89.90) < 0.001);
$t('footwear urun ELLENMEDI',                        abs($a['fw-1']['list'] - 14.50) < 0.001);

echo "\n== 3. İndirim yüzdesi korunur, mode değişmez ==\n";
$t('uw-2 list 12.00 -> 14.40',                       abs($a['uw-2']['list'] - 14.40) < 0.001);
$t('uw-2 kademe 9.00 -> 10.80',                      abs($a['uw-2']['tiers'][0]['price'] - 10.80) < 0.001);
$t('uw-2 indirim yuzdesi hala %25',                  vestra_discount($a['uw-2']) === 25);
$t('uw-2 mode hala sale (zam indirim degil)',        ($a['uw-2']['mode'] ?? '') === 'sale');
$t('uw-3 mode hala offer',                           ($a['uw-3']['mode'] ?? '') === 'offer');
$t('uw-1 mode hala fixed',                           ($a['uw-1']['mode'] ?? '') === 'fixed');
$t('uw-3 numune fiyati DOKUNULMADI (9.90)',          abs($a['uw-3']['sample_price'] - 9.90) < 0.001);
$t('numune uyarisi ciktida',                         str_contains($out, 'numune fiyati'));

echo "\n== 3b. Kâr oranı damgası yalan söylemez ==\n";
$t('uw-1 kar orani %50 -> %80 (1.5 x 1.2 = 1.8)', abs((float)$a['uw-1']['eur_margin_pct'] - 80.0) < 0.001);
$t('yeni oran ciktida yaziyor',                   str_contains($out, 'kar orani'));
$t('damgasi olmayan urune oran UYDURULMADI',      !isset($a['uw-2']['eur_margin_pct']));

echo "\n== 4. Fiyatı olmayan ilan: sessizce geçilmez ==\n";
$t('uw-5 ATLANDI diye yaziliyor',                    str_contains($out, 'fiyat alani yok'));
$t('uw-5 list uydurulmadi',                          !isset($a['uw-5']['list']));
$t('uw-5 damgalanmadi',                              !isset($a['uw-5']['markup_at']));

echo "\n== 5. Damga: aynı zam ikinci kez uygulanmaz ==\n";
$t('uw-1 damgasi yazildi (%20)',                     abs((float)($a['uw-1']['markup_pct'] ?? 0) - 20) < 0.001);
$t('uw-1 damga zamani yazildi',                      (int)($a['uw-1']['markup_at'] ?? 0) > 0);
[$rc2, $out2] = $run(['section'=>'underwear','markup_pct'=>'20','dry'=>'false']);
$b = $byId();
$t('ikinci kosu fiyata DOKUNMADI (5.40 kaldi)',      abs($b['uw-1']['list'] - 5.40) < 0.001);
$t('ikinci kosu sebebini yaziyor',                   str_contains($out2, 'ayni zam'));
$t('force ile tekrarlanabilecegini soyluyor',        str_contains($out2, 'force=true'));
[, $out3] = $run(['section'=>'underwear','markup_pct'=>'10','dry'=>'false']);
$c = $byId();
$t('FARKLI bir yuzde damgaya takilmaz (5.40->5.94)', abs($c['uw-1']['list'] - 5.94) < 0.001);
$run(['section'=>'underwear','markup_pct'=>'10','force'=>'true','dry'=>'false']);
$d = $byId();
$t('force=true damgayi asiyor (5.94->6.53)',         abs($d['uw-1']['list'] - 6.53) < 0.001);

echo "\n== 5b. Rakamlar varsayılan olarak günlüğe basılmaz ==\n";
$seed();
[, $q] = $run(['section'=>'underwear','markup_pct'=>'20','show_prices'=>'false']);
$t('varsayilan: gercek fiyat ciktida YOK',        !str_contains($q, '4.50') && !str_contains($q, '5.40'));
$t('varsayilan: numune rakami da YOK',            !str_contains($q, '9.90'));
$t('gizlendigini SOYLUYOR',                       str_contains($q, 'rakamlar gizli'));
$t('ilan kimligi yine gorunuyor',                 str_contains($q, 'uw-1'));
[, $q2] = $run(['section'=>'underwear','markup_pct'=>'20','show_prices'=>'true']);
$t('show_prices=true ile rakamlar basiliyor',     str_contains($q2, '4.50') && str_contains($q2, '5.40'));

echo "\n== 5c. Yedekten FİYAT geri yükleme ==\n";
$seed();
/* Dun geceki hali: yedegi simdi al, sonra zam yap, sonra geri don. */
$bak = dirname($listings).'/listings.json.bak-29991231-235959';
copy($listings, $bak);
$run(['section'=>'underwear','markup_pct'=>'20','dry'=>'false']);
/* Zam DISI bir alan da degistir: geri yukleme buna DOKUNMAMALI. */
$cur = json_decode((string)file_get_contents($listings), true);
foreach ($cur as $k => $pp) { if ($pp['id'] === 'uw-1') { $cur[$k]['moq'] = 99; $cur[$k]['tiers'][0]['min'] = 99; } }
file_put_contents($listings, json_encode($cur));
$a = $byId();
$t('once zam uygulandi (4.50 -> 5.40)',        abs($a['uw-1']['list'] - 5.40) < 0.001);
[$rc, $out] = $run(['restore_from'=>'29991231','dry'=>'true']);
$b = $byId();
$t('DRY: fiyat geri YAZILMADI',                abs($b['uw-1']['list'] - 5.40) < 0.001);
$t('DRY: kac ilan donecegini yaziyor',         str_contains($out, 'GERI YUKLENEN'));
[$rc, $out] = $run(['restore_from'=>'29991231','dry'=>'false']);
$t('kosu basarili',                            $rc === 0);
$c = $byId();
$t('list dun geceki degerine dondu (4.50)',    abs($c['uw-1']['list'] - 4.50) < 0.001);
$t('kademe-1 dondu (4.50)',                    abs($c['uw-1']['tiers'][0]['price'] - 4.50) < 0.001);
$t('kademe-2 dondu (4.20)',                    abs($c['uw-1']['tiers'][1]['price'] - 4.20) < 0.001);
$t('zam damgasi SILINDI',                      !isset($c['uw-1']['markup_pct']) && !isset($c['uw-1']['markup_at']));
$t('kar orani da geri (%50)',                  abs((float)$c['uw-1']['eur_margin_pct'] - 50.0) < 0.001);
/* Asil sinav: fiyat DISI alanlar korunmali. */
$t('moq 99 KORUNDU (fiyat disi alan)',         (int)$c['uw-1']['moq'] === 99);
$t("kademe 'min' 99 KORUNDU",                  (int)$c['uw-1']['tiers'][0]['min'] === 99);
$t('sale urunun modu bozulmadi',               ($c['uw-2']['mode'] ?? '') === 'sale');
$t('premium urun yine ellenmedi',              abs($c['pr-1']['list'] - 89.90) < 0.001);
/* Yedekte olmayan yeni ilana dokunulmaz. */
$cur = json_decode((string)file_get_contents($listings), true);
$cur[] = ['id'=>'uw-new','brand'=>'NBB','cat'=>'Bras','section'=>'underwear','mode'=>'fixed',
          'moq'=>6,'list'=>3.33,'tiers'=>[['min'=>6,'price'=>3.33]]];
file_put_contents($listings, json_encode($cur));
[, $out] = $run(['restore_from'=>'29991231','dry'=>'false']);
$d = $byId();
$t('yedekte olmayan ilan DOKUNULMADI',         abs($d['uw-new']['list'] - 3.33) < 0.001);
$t('yedekte olmayanlar sayiliyor',             str_contains($out, 'yedekte yok'));
[$rc, $out] = $run(['restore_from'=>'yokboyle','dry'=>'false']);
$t('olmayan yedek reddedildi',                 $rc !== 0 && str_contains($out, 'yedek bulunamadi'));
[$rc, $out] = $run(['restore_from'=>'29991231','markup_pct'=>'20']);
$t('restore + markup birlikte reddedildi',     $rc !== 0 && str_contains($out, 'birlikte kullanilamaz'));
@unlink($bak);

echo "\n== 6. Reddedilenler ==\n";
$seed();
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'20','price'=>'9.90']);
$t('markup_pct + price reddedildi',                  $rc !== 0 && str_contains($out, 'birlikte kullanilamaz'));
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'20','discount_pct'=>'15']);
$t('markup_pct + discount_pct reddedildi',           $rc !== 0);
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'150']);
$t('%150 zam reddedildi (tavan %100)',               $rc !== 0 && str_contains($out, 'gecersiz markup_pct'));
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'0']);
$t('%0 zam reddedildi',                              $rc !== 0);
[$rc, $out] = $run(['section'=>'underwaer','markup_pct'=>'20']);
$t('yazim hatali bolme reddedildi (0 urun demedi)',  $rc !== 0 && str_contains($out, 'gecersiz bolme'));
$t('hata mesaji gecerli bolmeleri sayiyor',          str_contains($out, 'underwear'));
$a = $byId();
$t('reddedilen kosularda hicbir fiyat degismedi',    abs($a['uw-1']['list'] - 4.50) < 0.001);

echo "\n== 7. Virgüllü yüzde ve bölmesiz ürünün yeri ==\n";
$seed();
[$rc, $out] = $run(['section'=>'underwear','markup_pct'=>'12,5','dry'=>'false']);
$a = $byId();
$t('"12,5" gecerli sayildi (4.50 -> 5.06)',          $rc === 0 && abs($a['uw-1']['list'] - 5.06) < 0.001);
$seed();
[$rc, $out] = $run(['section'=>'premium','markup_pct'=>'20']);
$t("section alani HIC olmayan urun 'premium' sayiliyor", str_contains($out, 'filtreye uyan urun: 1') && str_contains($out, 'pr-1'));

echo "\n";
@unlink($listings);
@unlink($home.'/public_html');
@rmdir($home);
echo ($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
