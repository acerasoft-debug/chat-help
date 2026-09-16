<?php
/**
 * KALICI GIRIS ("remember me") CIHAZ BASINA.
 *
 * Eskiden hesapta TEK bir jeton hash'i duruyordu ve auth_remember_set() her
 * girisTE onu eziyordu: telefondan giren musteri, dizustunde oturumu dustugu
 * anda kalici girisini de kaybediyordu -- ekranda "giris yaptim, yine giris
 * sayfasindayim". Gercek bir alicinin kaydinda iki ulke ve iki tarayici var
 * (16 Eyl 2026), yani coklu cihaz kenar durum degil.
 *
 * IKI YONU DE tutuyor: taninmasi gereken jetonlar ve taninMAMASI gerekenler
 * (suresi dolmus, yabanci, tavanin disina dusmus). Tek yon yazilsaydi "her
 * jetonu kabul et" de yesil kalirdi.
 */
require_once __DIR__.'/../vestra/inc/auth.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   {$n}\n"; } else { $fail++; echo "  HATA {$n}\n"; }
};
$h  = fn(string $tok) => hash('sha256', $tok);
$in = function (array $list, string $tok) use ($h): bool {
    foreach ($list as $e) if (hash_equals((string)$e['hash'], $h($tok))) return true;
    return false;
};
$future = time() + 90 * 86400;

echo "== eski bicim OKUNUYOR (surum atlarken kimse dusmez) ==\n";
$legacy = ['hash' => $h('eski'), 'exp' => $future];
$t('tek kayit liste olarak okunuyor', count(auth_remember_entries($legacy)) === 1);
$t('eski jeton taniniyor',            $in(auth_remember_entries($legacy), 'eski'));
$t('liste bicimi okunuyor',           count(auth_remember_entries([
       ['hash'=>$h('a'),'exp'=>$future], ['hash'=>$h('b'),'exp'=>$future]])) === 2);
$t('bos/bozuk -> bos liste', auth_remember_entries(null) === [] && auth_remember_entries('x') === []
                          && auth_remember_entries(['hash'=>'']) === []);

echo "\n== IKINCI CIHAZ BIRINCIYI DUSURMUYOR (kusurun ta kendisi) ==\n";
$l = auth_remember_append(auth_remember_entries($legacy), 'telefon', $future);
$t('iki kayit var',            count($l) === 2);
$t('eski cihaz HALA gecerli',  $in($l, 'eski'));
$t('yeni cihaz gecerli',       $in($l, 'telefon'));
$l = auth_remember_append($l, 'tablet', $future);
$t('ucuncu cihaz eklendi',     count($l) === 3 && $in($l, 'eski') && $in($l, 'telefon') && $in($l, 'tablet'));

echo "\n== taninMAMASI gerekenler ==\n";
$t('yabanci jeton reddedilir',  !$in($l, 'baskasinin-jetonu'));
/* Suresi dolmus kayit EKLEME sirasinda atiliyor: hesap, aylar once bir kez
   girilmis cihazlarin olu jetonlariyla sismemeli. */
$expired = [['hash'=>$h('olu'), 'exp'=>time() - 10], ['hash'=>$h('canli'), 'exp'=>$future]];
$l2 = auth_remember_append($expired, 'yeni', $future);
$t('suresi dolmus atiliyor',    !$in($l2, 'olu'));
$t('canli olan duruyor',         $in($l2, 'canli'));
$t('yeni eklendi',               $in($l2, 'yeni'));
$t('sayim dogru',               count($l2) === 2);

echo "\n== tavan: en ESKI duser, bugunku cihaz degil ==\n";
$many = [];
for ($i = 1; $i <= VESTRA_REMEMBER_MAX_DEVICES; $i++) $many = auth_remember_append($many, "c{$i}", $future);
$t('tavana kadar hepsi duruyor', count($many) === VESTRA_REMEMBER_MAX_DEVICES && $in($many, 'c1'));
$over = auth_remember_append($many, 'yeni-cihaz', $future);
$t('tavan asilmiyor',            count($over) === VESTRA_REMEMBER_MAX_DEVICES);
$t('en eski (c1) dustu',        !$in($over, 'c1'));
$t('ikinci en eski duruyor',     $in($over, 'c2'));
$t('yeni cihaz iceride',         $in($over, 'yeni-cihaz'));
$t('tavan makul (1 degil)',      VESTRA_REMEMBER_MAX_DEVICES >= 2);

echo "\n== kablolama: yazan ve okuyan AYNI bicimi kullaniyor ==\n";
$src = (string)@file_get_contents(__DIR__.'/../vestra/inc/auth.php');
$setFn = ''; if (preg_match('/function auth_remember_set\(.*?\n}/s', $src, $m)) $setFn = $m[0];
$resFn = ''; if (preg_match('/function auth_remember_restore\(.*?\n}/s', $src, $m)) $resFn = $m[0];
$t('set listeye ekliyor',        str_contains($setFn, 'auth_remember_append('));
$t('set tek kaydi EZMIYOR',     !preg_match("/'remember'\s*=>\s*\['hash'/", $setFn));
$t('restore listeyi geziyor',    str_contains($resFn, 'auth_remember_entries('));
$t('restore hash_equals ile',    str_contains($resFn, 'hash_equals('));
/* Askidaki hesap OTOMATIK geri yuklenmez -- belge askisindaki satici haric
   (o, tam da belgeyi yuklemek icin girebilmeli). */
$t('askili hesap geri yuklenmez', str_contains($resFn, "=== 'suspended'"));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
