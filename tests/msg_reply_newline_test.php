<?php
/**
 * msg_reply: govdede '\n' = YENI SATIR (operator, 1 Eki 2026, Ecokemet:
 * "je ne vois pas le detail de la commande ... la quantite par produit et
 * couleur" -- 11 referans, her biri icin adet/birim/renk dokumu).
 *
 * NEDEN: reply_spec TEK SATIR bir girdi. msg_reply govdeyi oldugu gibi
 * aliyordu, yani bir siparis dokumu (referans / adet x birim / renkler) tek
 * paragraf olarak giderdi -- 2.100 karakterlik bir duvar. order_note'un msg=
 * alani bu kurali zaten tasiyordu ('\n' = yeni satir); msg_reply'da yoktu.
 *
 * UC SEY OLCULUYOR (her biri ayri bir kirilma noktasi):
 *   1. workflow'un govde okumasi '\n'i gercek satir sonuna cevirir (kaynaktan
 *      CIKARILIP kosturuluyor -- kaynakta "str_replace gorunuyor" demek olcum degil);
 *   2. vestra_msg_send'in bosluk sadelestirmesi satir sonlarini YEMEZ
 *      (yalniz bosluk/tab) -- yoksa 1. madde bosuna cevirmis olurdu;
 *   3. site mesaji nl2br ile basiyor -- yoksa satir sonlari ekranda gorunmez.
 * Ve ters yon: siparis dokumunun BICIMI (SKU benzeri kodlar, fatura/siparis
 * numaralari, tutarlar) platform-disi-temas suzgecine takilmamali, ama ayni
 * metne gercek bir telefon/e-posta eklenince takilmali.
 */
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/messages.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$sp = (string)file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$a  = strpos($sp, "if (\$letter === 'msg_reply') {");
$b  = $a === false ? false : strpos($sp, "thread'e eklendi", $a);
$branch = ($a !== false && $b !== false) ? substr($sp, $a, $b - $a) : '';
$code = preg_replace('~/\*.*?\*/~s', '', $branch);          // yorumlari ayikla: yorum kod degil
$t('msg_reply dali bulundu', $code !== '');

echo "\n== 1. govde okumasi: '\\n' -> satir sonu ==\n";
$expr = '';
if (preg_match('/\$mrBody\s*=\s*([^;]+);/', $code, $m)) $expr = $m[1];
$t('govde atamasi cikarildi', $expr !== '');
$run = function (string $bodyInput) use ($expr): string {
    $E = fn(string $k) => $k === 'body' ? $bodyInput : '';
    return (string)eval('return '.$expr.';');
};
$got = $expr !== '' ? $run('Bonjour,\n\nLigne 1\nLigne 2') : '';
$t("'\\n' gercek satir sonu oldu (3 adet)",     substr_count($got, "\n") === 3);
$t('metin korunuyor',                           $got === "Bonjour,\n\nLigne 1\nLigne 2");
$t("yaziya '\\n' kalmadi",                      !str_contains($got, '\\n'));
$t("'\\n' yoksa govde AYNEN",                   $expr !== '' && $run('Merhaba dunya') === 'Merhaba dunya');
$t("bastaki/sondaki '\\n' kirpilir",            $expr !== '' && $run('\n\nx\n') === 'x');
$t("baska ters bolu dizisine dokunmaz (\\t)",   $expr !== '' && $run('C:\\temp') === 'C:\\temp');
$t('bos govde bos kalir (kapi bunu reddeder)',  $expr !== '' && $run('') === '' && $run('\n\n') === '');

echo "\n== 2. vestra_msg_send satir sonlarini YEMEZ ==\n";
$ms = (string)file_get_contents($root.'/vestra/inc/messages.php');
$fa = strpos($ms, 'function vestra_msg_send(');
$norm = '';
if ($fa !== false && preg_match('/\$text\s*=\s*trim\(preg_replace\([^;]+;/', substr($ms, $fa, 600), $m2)) $norm = $m2[0];
$t('bosluk sadelestirme satiri cikarildi', $norm !== '');
$text = "a   b\n\nc \t d\n  e";
$out = $norm !== '' ? (function () use ($norm, $text) { eval($norm); return $text; })() : '';
$t('ardisik bosluk/tab tek bosluk',             $out !== '' && str_contains($out, 'a b') && str_contains($out, 'c d'));
$t('satir sonlari duruyor (3 adet)',            substr_count($out, "\n") === 3);

echo "\n== 3. mesaj nl2br ile basiliyor ==\n";
$t('mesaj metni nl2br(htmlspecialchars) ile', (bool)preg_match('/nl2br\(\$h\(\$m\[\'text\'\]/', $ms));

echo "\n== 4. siparis dokumunun BICIMI platform-disi-temas suzgecinden gecer ==\n";
$ref = "LAC-L1212 — Lacoste L1212 Classic Piqué Polo\n10 pièces × 34,00 € = 340,00 €\n"
     . "1 pièce de chaque couleur : Black, White, Beige, Navy, Yellow, Pink, Bordeaux, Green, Blue, Light Blue\n\n"
     . "PH5522 — Lacoste Paris Regular Fit Polo Shirt\n9 pièces × 32,00 € = 288,00 €\n\n"
     . "XH9624 — Lacoste Slim Fit Sweatpants\n5 pièces × 55,00 € = 275,00 €\n\n"
     . "TH6709 TH6710 SH9623 SH9626 SH9608 SH1927 DH1417 PH9863\n"
     . "Total marchandises : 71 pièces = 2 734,60 €\nLivraison : 30,00 €\nTotal à régler : 2 764,60 €\n\n"
     . "Commande VES-3507BF86, facture INV-2026-1022. Offre O34FE5, facture INV-2026-1020.";
$t('SKU kodlari, fatura/siparis no, tutarlar: GECER',   vestra_msg_flag_offplatform($ref) === null);
$t('TERS YON: ayni metne telefon eklenince BLOK',       vestra_msg_flag_offplatform($ref."\nAppelez-moi au +33 6 12 34 56 78") === 'phone');
$t('TERS YON: ayni metne e-posta eklenince BLOK',       vestra_msg_flag_offplatform($ref."\nEcrivez a x.y@example.com") === 'email');

echo "\n== 5. dal kendi sozunu tutuyor ==\n";
$t('thread\'e eklenmeden once vestra_msg_send',   str_contains($code, 'vestra_msg_send($mrBuyerUid, $mrSellerUid, $mrFrom, $mrBody, $mrListing)'));
$t('send=false: yazmadan cikar (kuru kosu)',       (bool)preg_match('/strtolower\(\$E\(\'send\'\)\)\s*!==\s*\'true\'\)\s*\{[^}]*exit\(0\)/s', $code));

echo "\n".($fail ? "KALDI: $fail" : "gecti: $ok")." iddia\n";
exit($fail ? 1 : 0);
