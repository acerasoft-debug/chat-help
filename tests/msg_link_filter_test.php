<?php
/**
 * PLATFORM DISI TEMAS SUZGECI — bir BAGLANTI telefon numarasi degildir
 * (operator, 10 Eyl 2026: AlexaShop ↔ TYREX thread'inde bir Amazon urun
 * linki "PHONE" diye bloklandi; satici musteriye urun gosteremiyordu).
 *
 * Iki yon de tutuluyor. Tek yon yazilsaydi -- yalnizca "link gecsin" --
 * suzgeci tumden kaldirmak da testi yesil birakirdi, oysa isin yarisi
 * gercek numaralari BLOKLAMAYA devam etmek.
 */
require_once __DIR__.'/../vestra/inc/messages.php';

$ok = 0; $bad = 0;
$t = function (string $name, bool $cond) use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ok   $name\n"; }
    else       { $bad++; echo "  HATA $name\n"; }
};
$flag = fn(string $s) => vestra_msg_flag_offplatform($s);

echo "== 1. GECMESI gereken baglantilar ==\n";
/* Amazon adresin sonuna Unix zaman damgasi koyuyor (qid=1757497000): 10 hane,
   yani eski suzgec icin "telefon". Vakanin kendisi budur. */
$amazon = 'https://www.amazon.fr/-/en/Lacoste-Mens-Regular-T-Shirt-Green/dp/B01M72ASEG'
        . '/ref=sr_1_1?crid=CROX728IUGMZ&dib=eyJ2IjoiMSJ9&qid=1757497000&sprefix=lacoste%2Caps%2C123&sr=8-1';
$t('Amazon urun linki (qid zaman damgali)', $flag($amazon) === null);
$t('cumle icinde link',                     $flag("Bonjour, voici le modele : {$amazon} — dites-moi.") === null);
$t('uzun sayisal urun id (zalando)',        $flag('https://www.zalando.fr/p/123456789012') === null);
$t('www ile baslayan link',                 $flag('www.farfetch.com/shopping/item-19876543210.aspx') === null);
$t('kendi urun sayfamiz',                   $flag('https://vestrasales.com/product?id=lac-l1212&qid=1757497000') === null);
/* Katalogda gecen sira sayilari zaten gecmeliydi -- eski davranis korunuyor. */
$t('normal metin, kisa rakamlar',           $flag('20 pcs, ref 8045006, prix 110 EUR') === null);

echo "\n== 2. BLOKLANMASI gereken metinler (ters yon) ==\n";
$t('acik telefon (+33 bosluklu)',           $flag('appelez-moi au +33 6 12 34 56 78') === 'phone');
$t('bitisik yerel numara',                  $flag('mon numero 0612345678') === 'phone');
$t('00 ile uluslararasi',                   $flag('0033612345678 arayin') === 'phone');
/* Mesajlasma servisleri MASKELENMIYOR: yasaklanan seyin ta kendisi. */
$t('wa.me numarali link',                   $flag('https://wa.me/33612345678') === 'phone');
$t('api.whatsapp.com?phone=',               $flag('https://api.whatsapp.com/send?phone=33612345678') === 'phone');
$t('t.me numarali link',                    $flag('https://t.me/+33612345678') === 'phone');
$t('tel: baglantisi',                       $flag('tel:+33612345678') === 'phone');
$t('e-posta hala bloklu',                   $flag('ecrivez a moi@exemple.fr') === 'email');
$t('link icindeki e-posta da bloklu',       $flag('https://site.com/contact?mail=vendeur@exemple.fr') === 'email');
$t('IBAN hala bloklu',                      $flag('FR76 3000 4000 0500 0012 3456 789') === 'iban');
/* Ana makine adi maskelenmiyor: numaranin kendisi alan adiysa yine yakalanir. */
$t('alan adi numaranin kendisiyse',         $flag('https://0033612345678.com') === 'phone');

echo "\n== 3. Maskeleyici ne yapiyor ==\n";
$masked = vestra_msg_mask_link_digits($amazon);
$t('yol ve sorgu siliniyor',                !str_contains($masked, 'qid=') && !str_contains($masked, '/dp/'));
$t('ana makine kaliyor',                    str_contains($masked, 'www.amazon.fr'));
$t('wa.me dokunulmadan geciyor',            vestra_msg_mask_link_digits('https://wa.me/33612345678') === 'https://wa.me/33612345678');
$t('link disindaki metin bozulmuyor',
   vestra_msg_mask_link_digits('Prix 110 EUR, voir https://x.fr/a/1?b=2 svp') === 'Prix 110 EUR, voir https://x.fr svp');
/* Suzgec ham metne bakmaya devam eden iki kontrolu MASKELI kopyayla
   degistirmemeli; kablo taramasi bunu kaynaktan dogruluyor. */
$src = file_get_contents(__DIR__.'/../vestra/inc/messages.php');
$t('e-posta kontrolu ham metinde',          str_contains($src, "\$text)) return 'email';"));
$t('IBAN kontrolu ham metinde',             str_contains($src, "\$text)) return 'iban';"));
$t('telefon kontrolu maskeli kopyada',      str_contains($src, '$probe = vestra_msg_mask_link_digits($text);'));

echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
