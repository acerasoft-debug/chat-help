<?php
/* GÜNLÜK OTOMATİK JOURNAL YAZISI (operatör, 7 Eyl 2026: *"her gün otomatik
 * journal e paylaşım yap, estetik ve ayrıntılı, bilgi verici ve işe yarayan"*).
 *
 * Tutulan ilkeler:
 *   - HER GÜN ÇALIŞIR, HER GÜN YAYIMLAMAZ. Yeni ilan yoksa yazı yok — her gün
 *     üretilen içi boş bir yazı hem alan adına zarar verir (KURAL 9'un ince
 *     içerik yasağı) hem de okuyucuya journal'ı atlamayı öğretir (KURAL 2c).
 *   - HİÇBİR RAKAM UYDURULMAZ: hepsi canlı ilandan; şişirilmiş bir sayı da
 *     uydurulmuş sayılır (renkler AYRI sayılıyor, toplanmıyor).
 *   - GÖNDERİM YERİ yalnızca hepsi aynı ve doluysa yazılır (KURAL 3).
 *   - İÇ BAĞLANTI yalnızca GERÇEKTEN açılan sayfalara (KURAL 9).
 *   - DOKUZ DİL, `t()` YOK: `vlang()` süreçte sabitleniyor, tek süreçte
 *     dokuz dili `t()` ile gezmek dokuzunu da İngilizce yazardı.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/journal_auto.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);

echo "== 1. Dokuz dil, eksiksiz ve aynı yer tutucularla ==\n";
$en = vestra_journal_auto_strings('en');
$langs = ['de','fr','es','it','pt','ru','ar','ja'];
foreach ($langs as $l) {
    $s = vestra_journal_auto_strings($l);
    $missing = array_diff(array_keys($en), array_keys($s));
    $t("$l: bütün anahtarlar var", $missing === []);
    /* Yer tutucu sayısı tutmazsa sprintf ya patlar ya da rakamı DÜŞÜRÜR —
       sessiz veri kaybı. */
    $bad = [];
    foreach ($en as $k => $v) {
        if (substr_count((string)$v, '%') !== substr_count((string)($s[$k] ?? ''), '%')) $bad[] = $k;
    }
    $t("$l: yer tutucular tutuyor" . ($bad ? ' ('.implode(',', $bad).')' : ''), $bad === []);
}
$t('bilinmeyen dil İngilizceye düşüyor', vestra_journal_auto_strings('xx') === $en);

echo "\n== 2. Malzeme yoksa YAZI YOK ==\n";
/* Gelecekteki bir "şimdi" ile pencere boşalır: hiçbir ilan o pencereye düşmez. */
$far = strtotime('+10 years');
$r = vestra_journal_auto_build($far, 1);
$t('boş pencerede yayım YOK',      isset($r['skip']));
$t('gerekçe yazılı',               isset($r['skip']) && str_contains($r['skip'], 'esik'));
$t('eşik kodda sabit',             VESTRA_JOURNAL_AUTO_MIN >= 1);

echo "\n== 3. Malzeme varsa yazı kuruluyor ==\n";
$art = vestra_journal_auto_build(null, 3650);   // geniş pencere: demo katalog eski
if (isset($art['skip'])) {
    echo "  ATLA: katalogda damgalı ilan yok (".$art['skip'].")\n";
} else {
    $t('başlık var',                trim((string)($art['title'] ?? '')) !== '');
    $t('özet var',                  trim((string)($art['excerpt'] ?? '')) !== '');
    $t('gövde var',                 mb_strlen((string)($art['body'] ?? '')) > 200);
    $t('sekiz çeviri var',          count($art['i18n'] ?? []) === 8);
    foreach ($langs as $l) {
        $has = trim((string)($art['i18n'][$l]['body'] ?? '')) !== '';
        if (!$has) { $t("$l gövdesi dolu", false); break; }
    }
    $t('çeviri gövdeleri dolu',     !array_filter($langs, fn($l) => trim((string)($art['i18n'][$l]['body'] ?? '')) === ''));
    /* Çeviri gerçekten çeviri mi: İngilizce gövdenin aynısı DEĞİL. Aynısı
       olsaydı "9 dilde" demek yanlış olurdu ve kimse fark etmezdi. */
    $t('çeviriler İngilizce KOPYASI değil',
       !array_filter($langs, fn($l) => (string)($art['i18n'][$l]['body'] ?? '') === (string)$art['body']));
    $t('otomatik damgası var',      ($art['source'] ?? '') === VESTRA_JOURNAL_AUTO_FLAG);
    $t('slug tarih taşıyor',        str_contains((string)($art['slug'] ?? ''), date('Y-m-d')));
    $t('kategori tanınan bir kategori', in_array((string)($art['category'] ?? ''), VESTRA_JOURNAL_CATS, true));
    /* Kapak: raporda geçen GERÇEK bir ürünün fotoğrafı ya da boş (üretilen
       kapağa düşer) — dışarıdan bir URL asla. */
    $cov = (string)($art['cover'] ?? '');
    $t('kapak site-içi ya da boş',  $cov === '' || str_starts_with($cov, '/'));
    /* Gövde DÜZ METİN: renderer markdown/HTML tanımıyor, koyarsak okuyucu ham
       etiket görür (SSS cevaplarındaki `<b>` vakasının aynısı). */
    $t('gövdede HTML/markdown yok', !preg_match('/<[a-z]|\*\*|^#/mi', (string)$art['body']));
    /* Bağlantılar yalnızca gerçekten açılan sayfalara. */
    if (preg_match_all('~https://vestrasales\.com(/(?:b2b|wholesale)/[a-z0-9-]+)~', (string)$art['body'], $m)) {
        require_once $root . '/inc/seo.php';
        $dead = array_values(array_filter($m[1], function ($path) {
            $slug = basename($path);
            return !vestra_seo_resolve($slug);
        }));
        $t('iç bağlantıların hepsi açılıyor' . ($dead ? ' ('.implode(',', $dead).')' : ''), $dead === []);
    }
}

echo "\n== 3b. Aynı ilanlar İKİNCİ kez duyurulmuyor ==\n";
/* Sabit kayan pencere, 3 Eylül'de giren ilanları 4-10 Eylül arasındaki HER
   raporda yeniden yazardı: aynı içeriğin yedi kopyası. Pencere son otomatik
   raporda başlıyor. */
$jf = vestra_data_dir() . '/journal.json';
$jb = file_get_contents($jf);
try {
    $all = json_decode($jb, true) ?: [];
    $all[] = ['id' => 'jr_autotest', 'slug' => 'autotest', 'title' => 'autotest',
              'source' => VESTRA_JOURNAL_AUTO_FLAG, 'created' => date('c'), 'published' => 1];
    file_put_contents($jf, json_encode($all));
    $again = vestra_journal_auto_build(null, 3650);
    $t('son rapordan sonra yeni ilan yoksa YAZI YOK', isset($again['skip']));
    $t('pencere son raporda başlıyor', vestra_journal_auto_last_ts() !== null);
} finally {
    file_put_contents($jf, $jb);
}

echo "\n== 4. Rakamlar uydurulmuyor / şişirilmiyor ==\n";
$fn = $src('vestra/inc/journal_auto.php');
$t('damgasız ilan "yeni" sayılmıyor', str_contains($fn, 'if (!$t || $t < $from'));
/* Renkler AYRI sayılıyor: tek renkli 18 ilan "18 renk" diye çıkıyordu. */
$t('renkler AYRI sayılıyor',      str_contains($fn, '$colorSet') && !str_contains($fn, "\$colors += count"));
$t('fiyat en düşük kademeden',    str_contains($fn, 'vestra_from_price'));
/* KURAL 3: gönderim yeri yalnızca hepsi aynı ve doluysa. */
$t('gönderim yeri tek değilse YAZILMIYOR', str_contains($fn, 'if (count($ships) === 1)'));
$t('rakamlar metne gömülü değil', !preg_match('/=> \'[^\']*\b\d{2,}\b[^\']*\'/', $fn));
$t('pencere son rapordan başlıyor', str_contains($fn, 'if ($since !== null && $since > $from) $from = $since;'));
/* Metindeki "son %d gün" gerçek pencereyi söylemeli: son rapor dün çıktıysa
   "son 7 gün" demek bir haftalık liste vaat edip bir günlük liste vermektir. */
$t('"son %d gün" gerçek pencere',   str_contains($fn, '$days    = max(1, (int)ceil(($now - $winFrom) / 86400));'));

echo "\n== 5. Cron: aynı gün ikinci yazı yok, yazma geri okunuyor ==\n";
$cr = $src('vestra/cron_journal.php');
$t('aynı gün kontrolü var',       str_contains($cr, 'vestra_journal_auto_today()'));
$t('kuru koşu hiçbir şey yazmıyor', str_contains($cr, 'if ($dry) {') && strpos($cr, 'if ($dry) {') < strpos($cr, 'vestra_journal_save('));
$t('yazma geri okunuyor',         str_contains($cr, 'vestra_journal_find((string)($saved[\'slug\'] ?? \'\'))'));
$t('geri okuma tutmazsa KIRMIZI', str_contains($cr, 'exit(1)'));
$dep = $src('.github/workflows/deploy-vestra.yml');
$t('crontab satırı kuruluyor',    str_contains($dep, 'cron_journal.php') && str_contains($dep, 'VESTRA-SWEEP journal'));
$t('deploy kanaryası kuru koşuyor', str_contains($dep, 'php cron_journal.php --dry'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
