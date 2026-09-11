<?php
/* GONDEREN ADRESI — operator, 11 Eyl 2026: *"acerasoft@gmail.com dan hic bir
 * müsteriye email gitmeyecek sadece support@vestrasales.com dan gidecek brevo
 * üzerinden"*.
 *
 * OLCULDU (ayni gun, diag-messages -> mailcfg): canli ayar ZATEN
 * `from=support@vestrasales.com`. Yani bugun hatali bir mektup gitmiyor —
 * duzeltilen sey, ayarin tek bir duzenlemeyle Gmail'e donebilmesiydi:
 * `mail_from` panelden yazilabilen bir ayar ve operatorun Gmail'i Brevo'da
 * HALA aktif bir gonderici (o kosuda ikisi de "aktif=EVET" cikti).
 *
 * Asil bulgu kural degil KOPYAYDI: ayni olgu DORT yerde okunuyordu ve
 * dorduncu (vestra_smtp_send) `smtp_from` bossa **`smtp_user`'a dusuyordu**.
 * API yolu bir gun kotayi doldurup SMTP yedege dustugunde, SMTP kullanicisi
 * ne ise musteri mektubu ondan giderdi. Bu depoda "ayni olgu bir kac yerde
 * yazili" hatasi defalarca kayitli (desc/sizes, faturanin uc katmani, dort
 * mektup govdesi) -- burada bedeli, musterinin kutusunda yanlis gonderici.
 *
 * Test IKI YONU birden tutuyor:
 *   1) ZORLANMALI  — alan adi disindaki her aday ev adresine duser.
 *   2) ZORLANMAMALI — sirket alan adindaki baska bir kutu ve SATICININ KENDI
 *      transportu aynen kalir. Tek yon yazilsaydi test yesil kalir ve
 *      satici-kendi-adresinden ozelligi sessizce olurdu.
 */

require_once __DIR__ . '/../vestra/inc/notify.php';

$fail = 0; $n = 0;
function ok(bool $c, string $m): void {
    global $fail, $n; $n++;
    if (!$c) { $fail++; echo "  KALDI: {$m}\n"; }
}
$src = (string)file_get_contents(__DIR__ . '/../vestra/inc/notify.php');
$HOUSE = 'support@vestrasales.com';

/* ── 1) ZORLANMALI: platform yolunda alan adi disi her sey ev adresine ──── */
ok(vestra_mail_from(null, 'acerasoft@gmail.com') === $HOUSE,
   'operatorun Gmail\'i -> ev adresi (operatorun cumlesinin ta kendisi)');
ok(vestra_mail_from(null, 'ACERASOFT@GMAIL.COM') === $HOUSE,
   'buyuk harfli yazim da yakalaniyor (alan adi kucultuluyor)');
ok(vestra_mail_from(null, '') === $HOUSE,
   'ayar BOS -> ev adresi (eskiden smtp yolunda smtp_user\'a duserdi)');
ok(vestra_mail_from(null, '   ') === $HOUSE, 'yalnizca bosluk -> ev adresi');
ok(vestra_mail_from(null, 'noreply@brevo.com') === $HOUSE,
   'saglayicinin kendi alan adi da platform gondericisi degil');
/* Yakin-komsu tuzagi: alan adi BENZEMEK yetmez, ESIT olmali. Bu depoda
   mango/zara dersi tam olarak bu sinifin bedelini odedi. */
ok(vestra_mail_from(null, 'support@vestrasales.com.tr') === $HOUSE,
   'vestrasales.com.tr BASKA bir alan adi -> ev adresi');
ok(vestra_mail_from(null, 'support@notvestrasales.com') === $HOUSE,
   'notvestrasales.com -> ev adresi (alt dize degil, TAM esitlik)');
ok(vestra_mail_from(null, 'vestrasales.com@gmail.com') === $HOUSE,
   'yerel kismi alan adi gibi yazan adres kandirmiyor');

/* ── 2) ZORLANMAMALI ───────────────────────────────────────────────────── */
ok(vestra_mail_from(null, $HOUSE) === $HOUSE, 'ev adresi aynen gecer');
ok(vestra_mail_from(null, 'sales@vestrasales.com') === 'sales@vestrasales.com',
   'sirket alan adindaki BASKA kutu korunuyor — operator yarin acabilir');
/* Saticinin KENDI transportu: ozellik "gercekten onlardan gitsin" diye var. */
ok(vestra_mail_from(['mail_api_key'=>'k'], 'boutique@example.com') === 'boutique@example.com',
   'saticinin kendi transportu: adresi DEGISMIYOR');
ok(vestra_mail_from(['smtp_host'=>'h'], 'shop@gmail.com') === 'shop@gmail.com',
   'saticinin kendi Gmail transportu bile dokunulmadan geciyor (kapsam disi)');

/* ── 3) Kablolama: UC yolun HICBIRI ayari kendi basina okumuyor ─────────── */
/* Iddia YAZIMI degil OLGUYU olcuyor: "ham ayar okuyup dogrudan $from'a yazan
   satir kalmadi mi". Dosyanin tamami taranıyor, cunku bu hatanın tekrar etme
   sekli tam olarak "dorduncu bir yerde yeniden okumak". */
ok(!preg_match('/\$from\s*=\s*(?:\(string\))?\s*(?:\$g|vestra_cfg)\(/', $src),
   'hicbir yol $from\'u dogrudan ayardan okumuyor (dordu de resolver\'dan)');
ok(substr_count($src, 'vestra_mail_from(') >= 4,
   'resolver tanim + UC cagri yerinde (api, smtp, php-mail)');
/* Arama FONKSIYONUN GOVDESIYLE sinirli. Ilk yazimda `.*?` ile yazmistim ve
   iddia DUSMUYORDU: vestra_smtp_send dosyada vestra_api_send'den ONCE geliyor,
   yani lazy nokta bir sonraki fonksiyonun cagrisina kadar uzayip orada
   esleşiyordu — smtp yolu eski haline dondurulunce bile YESIL kaliyordu.
   Falsifikasyon kosusu bunu gosterdi (3 kirmizi bekleniyordu, 4 olmaliydi).
   Bu depoda "hic dusemeyen bir iddia, iddia degildir" zaten kayitli. */
$inFn = function (string $fn, string $needle) use ($src): bool {
    return (bool)preg_match('/function '.preg_quote($fn,'/').'\b(?:(?!\nfunction )[\s\S])*?'
                            .preg_quote($needle,'/').'/', $src);
};
ok($inFn('vestra_api_send',  '$from=vestra_mail_from($cfg,'),
   'vestra_api_send resolver\'i cagiriyor ve $cfg\'yi GECIYOR');
ok($inFn('vestra_smtp_send', '$from=vestra_mail_from($cfg,'),
   'vestra_smtp_send resolver\'i cagiriyor ve $cfg\'yi GECIYOR');
ok($inFn('vestra_send_mail', '$from=vestra_mail_from(null,'),
   'php-mail yolu resolver\'i cagiriyor (platform, $cfg yok)');
ok(!preg_match('/\$g\(\'smtp_from\',\'\'\)\s*\?:\s*\$user\s*;/', $src),
   'smtp_from bos -> smtp_user dususu artik resolver\'dan gecmeden olmuyor');

/* ── 4) Zorlama SESSIZ degil ────────────────────────────────────────────── */
ok(str_contains($src, 'platform gondericisi'),
   'ayar ezildiginde error_log\'a yaziliyor — sessiz duzeltme yanlis ayari gizlerdi');
ok((bool)preg_match("/platform gondericisi.*preg_replace\('\/\^\(\.\)\.\*\(@\.\*\)\\\$\/'/s", $src),
   'o satirda adresin yerel kismi MASKELI (error_log teshis ciktisina giriyor)');

/* ── 5) Ev adresi ve alan adi TEK sabitte ───────────────────────────────── */
ok(vestra_mail_house_address() === $HOUSE, 'ev adresi sabiti operatorun yazdigi adres');
ok(vestra_mail_platform_domain() === 'vestrasales.com', 'platform alan adi sabiti');
ok(str_ends_with(vestra_mail_house_address(), '@'.vestra_mail_platform_domain()),
   'ev adresi kendi alan adinda — ikisi ayrisirsa her mektup ezilirdi');

echo $fail
    ? "\nmail_sender_test: {$fail}/{$n} KALDI\n"
    : "mail_sender_test: {$n} iddia gecti\n";
exit($fail ? 1 : 0);
