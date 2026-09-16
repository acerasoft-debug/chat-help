<?php
/**
 * ADIM BETIKLERI ARGUMAN SINIRINI ASMASIN.
 *
 * 16 Eyl 2026: `send-campaign-preview.yml` -> "Alıcı cevap mektubu" adiminin
 * betigi 132.127 bayta cikti ve adim HIC calismadi:
 *
 *   An error occurred trying to start process '/usr/bin/docker'
 *   with working directory ... Argument list too long
 *
 * Sebep GIRDI DEGILDI. appleboy/ssh-action betigi TEK bir env degiskeni olarak
 * docker'a geciriyor ve Linux'ta bir argumanin tavani MAX_ARG_STRLEN = 128 KiB
 * (131.072 bayt). Yani mektup yolu herkes icin kirilmisti, hata mesaji "cok
 * uzun" demiyordu, ve sebep ancak betik TARTILARAK bulundu -- kaynak okuyarak
 * degil. Kutukte gorunen tek sey docker'in kendi cumlesiydi.
 *
 * Bu test o olcumu her kosuda yapiyor. Yakaladigi sey bir davranis degil bir
 * BUTCE: bir blok eklerken kimse betigi tartmayi hatirlamaz, ve hatirlamaya
 * birakilan kural bu depoda defalarca bozuldu.
 *
 * Esik 128.000: tavanin 3 KiB altinda duruyor, cunku bir sonraki kucuk ekleme
 * tam sinirda patlamasin -- uyari, cenaze degil.
 */

const ARG_MAX_STRLEN = 131072;   // Linux MAX_ARG_STRLEN — asilirsa adim HIC baslamaz
const ARG_BUDGET     = 128000;   // bizim esigimiz: tavanin biraz altinda

$pass = 0; $fail = 0; $warn = [];
$t = function (string $what, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; } else { $fail++; echo "  KIRMIZI: {$what}\n"; }
};

$dir = dirname(__DIR__) . '/.github/workflows';
$t('workflows dizini okunabiliyor', is_dir($dir));

/* YAML ayristirici yok; `script: |` bloklarini elle cikariyoruz. Blok, `script: |`
   satirinin girintisinden DAHA DERIN girintili (ya da bos) satirlar bittiginde
   biter -- YAML'in kendi kurali. */
$files = glob($dir . '/*.yml') ?: [];
$t('en az bir workflow bulundu', count($files) > 0);

$measured = 0; $biggest = ['', '', 0];
foreach ($files as $f) {
    $lines = explode("\n", (string)file_get_contents($f));
    for ($i = 0; $i < count($lines); $i++) {
        if (!preg_match('/^(\s*)script:\s*\|\s*$/', $lines[$i], $m)) continue;
        $ind = strlen($m[1]);
        $body = [];
        for ($j = $i + 1; $j < count($lines); $j++) {
            $l = $lines[$j];
            if (trim($l) === '') { $body[] = ''; continue; }
            if (strlen($l) - strlen(ltrim($l, ' ')) <= $ind) break;
            $body[] = substr($l, $ind + 2);
        }
        while ($body && end($body) === '') array_pop($body);
        $bytes = strlen(implode("\n", $body));
        $measured++;
        if ($bytes > $biggest[2]) $biggest = [basename($f), 'satir '.($i + 1), $bytes];
        $t(sprintf('%s (satir %d) betigi %s bayt, butce %s',
                   basename($f), $i + 1, number_format($bytes), number_format(ARG_BUDGET)),
           $bytes <= ARG_BUDGET);
        if ($bytes > ARG_BUDGET * 0.85 && $bytes <= ARG_BUDGET) {
            $warn[] = sprintf('  UYARI: %s (satir %d) %s bayt — butcenin %%%d\'i',
                              basename($f), $i + 1, number_format($bytes), (int)round($bytes / ARG_BUDGET * 100));
        }
    }
}
$t('olculen betik sayisi makul (>20)', $measured > 20);
/* Olcunun kendisi calisiyor mu? Sifir betik olcup yesil kalan bir test, hic
   dusemeyen bir iddiadir -- bu depoda o hata birden fazla kez cikti. */
$t('en buyuk betik gercekten bulundu (>10 KB)', $biggest[2] > 10000);

echo "  en buyuk: {$biggest[0]} ({$biggest[1]}) " . number_format($biggest[2]) . " bayt"
   . "  |  olculen betik: {$measured}  |  sert tavan: " . number_format(ARG_MAX_STRLEN) . "\n";
foreach ($warn as $w) echo $w . "\n";

echo ($fail === 0)
    ? "workflow_arg_limit_test: {$pass} iddia gecti\n"
    : "workflow_arg_limit_test: {$pass} gecti, {$fail} KIRMIZI\n";
exit($fail === 0 ? 0 : 1);
