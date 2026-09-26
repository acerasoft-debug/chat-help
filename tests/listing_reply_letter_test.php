<?php
/* listing_reply (26 Eyl 2026, Odzież Premium): sitede ilan uzerinden yazan
 * aliciya, sitede verilen cevabin E-POSTA hali.
 * Tutulanlar (iki yon):
 *   - urun ADI + ident no. + baglanti govdede; MAGAZA ADI HIC gecmez (KURAL 8
 *     -- sablon satici adini parametre olarak BILE almiyor)
 *   - "cevabimiz gelen kutunuzda da duruyor" cumlesi YALNIZ $replied=true iken
 *   - bos msg -> standart "adet ve renk" cumlesi; verilen msg aynen
 *   - is akisi: to=account: SART, ilanda konusma yoksa DURUR, hesap ID'si TAM
 *     esitlikle ONCE aranir, gorsel diskte yoksa seride konmaz.
 */
$root = __DIR__.'/..';
$src = file_get_contents($root.'/vestra/inc/email_templates.php');
if (!preg_match('/^function vestra_tpl_listing_reply\(.*?^}/ms', $src, $m)) { echo "HATA: vestra_tpl_listing_reply bulunamadi\n"; exit(1); }
eval($m[0]);

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$items = [
  ['label'=>'Givenchy Logo Hoodie', 'ident'=>'BM716G3YBM001', 'url'=>'https://vestrasales.com/product?id=gvy-x'],
  ['label'=>'Balmain Logo T-Shirt', 'ident'=>'AH1EG010',      'url'=>'https://vestrasales.com/product?id=blm-y'],
];

echo "-- iki ilan, cevap sitede var --\n";
[$s, $b, $o] = vestra_tpl_listing_reply('Dear Jan Kowalski', $items, '', true, 'Marco Bellini');
$t('hitap ilk satir',                         str_starts_with($b, "Dear Jan Kowalski,\n"));
$t('iki urun adi govdede',                    str_contains($b, 'Givenchy Logo Hoodie') && str_contains($b, 'Balmain Logo T-Shirt'));
$t('iki ident no.',                           str_contains($b, 'ident no. BM716G3YBM001') && str_contains($b, 'ident no. AH1EG010'));
$t('iki baglanti',                            str_contains($b, 'product?id=gvy-x') && str_contains($b, 'product?id=blm-y'));
$t('cogul: "these items"',                    str_contains($b, 'these items') && str_contains($b, 'messages on VESTRA'));
$t('standart cumle: adet + renk',             str_contains($b, 'quantity and colours'));
$t('gelen kutusu cumlesi VAR ($replied)',     str_contains($b, 'where our reply is waiting'));
$t('konu: 2 urun',                            str_contains($s, '2 items'));
$t('imza persona',                            str_contains($b, "Marco Bellini\nVESTRA"));
$t('magaza adi YOK (KURAL 8)',                !preg_match('/garage|marca online|tyrex/i', $s.$b));
$t('dugme mesaj kutusuna',                    ($o['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=messages');
$t('Turkce karakter yok',                     !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $s.$b));

echo "-- tek ilan, sitede cevap YOK, ozel metin --\n";
[$s, $b, $o] = vestra_tpl_listing_reply('', [$items[0]], 'We have 40 pieces in stock.', false, '');
$t('bos hitap -> Sir or Madam',               str_starts_with($b, "Dear Sir or Madam,\n"));
$t('tekil: "this"/"message on"',              str_contains($b, 'message on VESTRA about the following item:'));
$t('konu urun adini tasir',                   str_contains($s, 'Givenchy Logo Hoodie'));
$t('ozel metin aynen',                        str_contains($b, 'We have 40 pieces in stock.'));
$t('ozel metin varken standart cumle YOK',    !str_contains($b, 'quantity and colours'));
$t('gelen kutusu cumlesi YOK (!$replied)',    !str_contains($b, 'reply is waiting'));
$t('sirket imzasi',                           str_contains($b, "VESTRA · vestrasales.com"));

echo "-- is akisi kablolamasi --\n";
$wf = file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$a = strpos($wf, "\$letter === 'listing_reply'");
$z = $a === false ? false : strpos($wf, "} elseif (\$letter === 'login_fixed')", $a);
$br = ($a !== false && $z !== false) ? substr($wf, $a, $z - $a) : '';
$t('dal var',                                 $br !== '');
$t('to=account: SART (hesap yoksa durur)',    (bool)preg_match('/if \(!\$acc\) \{ fwrite\(STDERR, "listing_reply/', $br));
$t('konusma yoksa DURUR',                     (bool)preg_match('/if \(!\$th\) \{ fwrite\(STDERR.*exit\(1\)/', $br));
$t('konusma alici + ilanla eslesir',          str_contains($br, "(\$t['buyer_uid'] ?? '') === \$lrUid && (\$t['listing_id'] ?? '') === \$pid"));
$t('bizden cevap yoksa cumle dusuyor',        str_contains($br, 'if ($fromUs === 0) $lrReplied = false;'));
$t('gorsel diskte degilse seride yok',        str_contains($br, 'is_file($doc.$img)'));
$t('ident KURAL 8 fonksiyonundan',            str_contains($br, 'vestra_msg_seller_ident('));
$t('satici adi mektuba gecmiyor',             !preg_match('/company|seller_name|lcSellerName/', $br));
$acct = substr($wf, (int)strpos($wf, "str_starts_with(strtolower(\$to), 'account:')"), 1400);
$idPos = strpos($acct, "(\$a['id'] ?? '')");
$subPos = strpos($acct, 'str_contains($hay, $needle)');
$t('account: once ID TAM esitlik',            $idPos !== false && $subPos !== false && $idPos < $subPos);
$t('ID bulunursa alt dize aranmaz',           str_contains($acct, 'if (!$hits) foreach (auth_accounts() as $a)'));

echo "\n$ok ok, $fail HATA\n";
exit($fail ? 1 : 0);
