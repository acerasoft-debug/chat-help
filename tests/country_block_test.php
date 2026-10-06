<?php
/* Ulke engeli — asil risk KENDINI DISARIDA BIRAKMAK.
 *
 * Operator Turkiye'den baglaniyor ve "TR'yi engelle" dedi. Tek tek IP
 * engellemede kod zaten kendi IP'sini reddediyor (admin.php: sec_self), ama
 * ULKE kurali bunu kendiliginden bilmez: "TR" yazmak, operatorun kendi
 * baglantisini de kapsar.
 *
 * Bu yuzden UC kacis yolu var ve ucu de burada ayri ayri tutuluyor:
 *   1. /admin yolu muaf  — kapi oturumdan ONCE kosuyor, yol tek olcut
 *   2. izin listesi      — operatorun kendi IP'si ulkeden bagimsiz gecer
 *   3. ulke cozulemezse  — cografi API dustugunde TUM DUNYAYA 403 basmamak icin
 *
 * Uc numara en sinsisi: bir gun ip-api ve ipwho.is birlikte duserse, "bos cc"yi
 * engel saymak siteyi herkese kapatirdi ve sebebi log'da gorunmezdi.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/security.php');
/* _vsec_read/_vsec_write BILEREK alinmiyor: asagida bellek ici sahteleri var,
   test diske dokunmasin. */
foreach (['vestra_ip_matches',
          'vestra_country_blocks','vestra_save_country_blocks','vestra_country_blocked'] as $fn) {
    if (preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) eval($m[0]);
}

/* vestra_ip_intel() gercekte HTTP'ye cikiyor; testte sahtesi konuyor.
   $GLOBALS['T_CC'] ile hangi ulkenin donecegini, '' ile "cozulemedi"yi kuruyoruz. */
function vestra_ip_intel(string $ip, int $timeout = 3): array {
    return ['cc' => (string)($GLOBALS['T_CC'] ?? ''), 'country'=>'', 'city'=>'', 'region'=>'',
            'isp'=>'', 'proxy'=>false, 'hosting'=>false];
}
/* Dosya yerine bellek: test diske yazmasin. */
$GLOBALS['T_STORE'] = [];
function _vsec_read(string $n): array { return $GLOBALS['T_STORE'][$n] ?? []; }
function _vsec_write(string $n, array $d): void { $GLOBALS['T_STORE'][$n] = $d; }

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$TR = '88.230.10.5';   // operatorun tipik TR adresi (ornek)
$DE = '91.10.20.30';

echo "\n== 1. Liste BOSKEN hicbir sey engellenmez ==\n";
$GLOBALS['T_CC'] = 'TR';
$t('bos liste -> gecer', !vestra_country_blocked($TR, '/product'));

echo "\n== 2. TR engellenince TR ziyaretci kesilir ==\n";
vestra_save_country_blocks(['TR'], []);
$GLOBALS['T_CC'] = 'TR';
$t('TR ziyaretci -> ENGELLI', vestra_country_blocked($TR, '/product'));
$GLOBALS['T_CC'] = 'DE';
$t('DE ziyaretci -> gecer',   !vestra_country_blocked($DE, '/product'));

echo "\n== 3. KACIS 1 — /admin her zaman acik ==\n";
/* Kapi oturum baslamadan once kosuyor, yani "bu admin mi" sorusunun cevabi
   yok. Yol muafiyeti olmasaydi operator kendi panelini kapatirdi. */
$GLOBALS['T_CC'] = 'TR';
$t('/admin           -> gecer', !vestra_country_blocked($TR, '/admin'));
$t('/admin.php       -> gecer', !vestra_country_blocked($TR, '/admin.php'));
$t('/admin?tab=users -> gecer', !vestra_country_blocked($TR, '/admin?tab=users'));
$t('/adminx (baska sayfa) -> ENGELLI olmali degil mi: yol oneki oldugu icin gecer',
   !vestra_country_blocked($TR, '/adminx'));   // bilinen ve kabul edilen genislik
$t('/buyer           -> ENGELLI', vestra_country_blocked($TR, '/buyer'));

echo "\n== 4. KACIS 2 — izin listesi ulkeden bagimsiz gecer ==\n";
vestra_save_country_blocks(['TR'], [$TR]);
$GLOBALS['T_CC'] = 'TR';
$t('tam IP izinli    -> gecer', !vestra_country_blocked($TR, '/product'));
$t('ayni ulke baska IP -> ENGELLI', vestra_country_blocked('88.230.99.99', '/product'));
vestra_save_country_blocks(['TR'], ['88.230.']);
$t('onek izinli      -> gecer', !vestra_country_blocked($TR, '/product'));
vestra_save_country_blocks(['TR'], ['88.230.0.0/16']);
$t('CIDR izinli      -> gecer', !vestra_country_blocked($TR, '/product'));

echo "\n== 5. KACIS 3 — ulke cozulemezse ENGELLEME YOK ==\n";
/* En sinsi vaka: iki cografi saglayici da duserse cc bos doner. Bos cc'yi
   engel saymak, saglayici bayildiginda siteyi TUM DUNYAYA kapatirdi. */
vestra_save_country_blocks(['TR'], []);
$GLOBALS['T_CC'] = '';
$t('cc bos (API dustu) -> gecer', !vestra_country_blocked($TR, '/product'));
$t('cc bos, DE de gecer', !vestra_country_blocked($DE, '/product'));

echo "\n== 6. Kayit temizligi: gecersiz kod yazilmaz ==\n";
vestra_save_country_blocks(['tr', 'XX1', '', 'de', 'D', 'FR'], []);
$saved = array_keys(vestra_country_blocks()['countries']);
sort($saved);
$t('kucuk harf buyur, gecersiz atilir: '.implode(',', $saved), $saved === ['DE','FR','TR']);
$GLOBALS['T_CC'] = 'TR';
$t('kucuk harfle yazilan TR yine engelli', vestra_country_blocked($TR, '/product'));

echo "\n== 7. Engel KALDIRILABILIR ==\n";
vestra_save_country_blocks([], []);
$GLOBALS['T_CC'] = 'TR';
$t('liste bosaltildi -> gecer', !vestra_country_blocked($TR, '/product'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
