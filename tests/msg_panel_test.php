<?php
/* MESAJLASMA PANELI — tek cizim yolu, okunur zaman, telefon duzeni
 * (operator, 7 Eyl 2026: "mesajlasma ve genel olarak mobil bolumunu daha
 * konforlu ve estetik hale getir").
 *
 * Tutulan ilkeler:
 *   - Alici ve satici paneli AYNI fonksiyonu cagirir. Iki kopya, ilk farkli
 *     duzenlemede ayrisirdi (bu depoda KURAL 5f ve order_shipped ayni dersi
 *     verdi); o yuzden ikinci kopyanin OLMADIGI da denetleniyor.
 *   - Zaman damgasi ham ISO basmaz ("2026-09-07T09:52" -> "09:52" / "Dun" /
 *     gun adi / gun+ay). Gun ve ay adlari sozlukten gelir.
 *   - Fotograf kapisi urun sayfasiyla ayni: onaysiz hesap ilan fotografini
 *     mesaj listesinden de goremez; satici kendi ilanini her zaman gorur.
 *   - Telefon: konusma kabuk icinde, tek kaydirma alani baloncuklar, yazma
 *     kutusu alt sekme cubugunun ustunde. Bunlar CSS'te olculebilir kurallar.
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/i18n.php';
require_once $root . '/inc/auth.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/messages.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);
$css = $src('inc/style.css');

echo "== 1. Tek cizim yolu (alici + satici ayni fonksiyon) ==\n";
$buyer = $src('buyer.php'); $seller = $src('seller.php');
$t('buyer.php ortak cizicyi cagiriyor',  str_contains($buyer, "vestra_msg_panel_html('buyer'"));
$t('seller.php ortak ciziciyi cagiriyor', str_contains($seller, "vestra_msg_panel_html('seller'"));
/* Ikinci kopya kalmadi: baloncugu/yazma kutusunu artik yalnizca messages.php kuruyor. */
foreach (['buyer.php' => $buyer, 'seller.php' => $seller] as $f => $s) {
    $t("$f baloncuk kurmuyor",     !str_contains($s, 'msgbubblewrap'));
    $t("$f yazma kutusu kurmuyor", !str_contains($s, 'class="msgcompose"'));
    $t("$f konusma satiri kurmuyor", !str_contains($s, 'class="threadrow'));
}
$t('sistem karti tek yerde', substr_count($src('inc/messages.php'), 'function vestra_msg_system_html') === 1);

echo "\n== 2. Zaman etiketleri ==\n";
$now = strtotime('2026-09-07 12:00:00');            // Pazartesi
$t('bugun -> saat',            vestra_msg_when('2026-09-07T09:52:00+00:00', $now) === '09:52');
$t('dun -> Yesterday',         vestra_msg_when('2026-09-06T18:00:00+00:00', $now) === 'Yesterday');
$t('bu hafta -> gun adi',      vestra_msg_when('2026-09-03T09:12:00+00:00', $now) === 'Thu');
$t('eski -> gun + ay',         vestra_msg_when('2026-08-21T10:00:00+00:00', $now) === '21 Aug');
$t('baska yil -> yil da var',  vestra_msg_when('2025-12-24T10:00:00+00:00', $now) === '24 Dec 2025');
$t('gun ayraci bugun icin de yazilir', vestra_msg_day_label('2026-09-07T09:52:00+00:00', $now) === 'Today');
$t('baloncuk saati yalniz saat',       vestra_msg_clock('2026-09-07T09:52:00+00:00') === '09:52');
$t('cozulemeyen tarih bos doner',      vestra_msg_when('', $now) === '' && vestra_msg_clock('bozuk') === '');
/* Saat dilimi kaymasi "yarin" uretmemeli: gelecek bir damga bugun sayilir. */
$t('ileri damga bugun sayilir', vestra_msg_when('2026-09-07T23:30:00+00:00', $now) === '23:30');
/* Gun/ay adlari sozlukte: eksik olsalardi t() sessizce Ingilizce basardi ve
   Japonca panelde "Thu" gorunurdu. */
$de = require $root . '/inc/lang/de.php';
$missing = [];
foreach (['Today','Yesterday','Mon','Tue','Wed','Thu','Fri','Sat','Sun','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','{d} {m}','{d} {m} {y}'] as $k) if (!isset($de[$k])) $missing[] = $k;
$t('gun/ay adlari sozlukte ('.count($missing).' eksik)', $missing === []);
$t('Japonca sirasi kendi kalibindan', ($de['{d} {m}'] ?? '') !== '' && (require $root.'/inc/lang/ja.php')['{d} {m}'] === '{m}{d}日');

echo "\n== 3. Avatar: fotograf kapisi urun sayfasiyla ayni ==\n";
$seed = vestra_find('lac-pique-polo');
$t('numune ilan bulundu ve fotografi var', $seed !== null && vestra_primary_image($seed) !== '');
$th = ['id'=>'x','buyer_uid'=>'B1','seller_uid'=>'S1','listing_id'=>'lac-pique-polo','messages'=>[]];
$avSeller = vestra_msg_avatar_html($th, 'S1');   // satici kendi ilanini gorur
$avBuyer  = vestra_msg_avatar_html($th, 'B1');   // CLI'da oturum yok -> onaysiz
$t('satici kendi ilaninin fotografini gorur', str_contains($avSeller, '<img'));
$t('onaysiz alici fotograf gormez',           !str_contains($avBuyer, '<img'));
$t('fotograf yoksa bas harf basilir',         str_contains($avBuyer, 'tr-ava-i'));
$sup = vestra_msg_avatar_html(['id'=>'y','buyer_uid'=>'B1','seller_uid'=>VESTRA_SUPPORT_UID,'listing_id'=>'','messages'=>[]], 'B1');
$t('VESTRA Support kendi isaretini tasir',    str_contains($sup, 'tr-ava-v') && str_contains($sup, '<svg'));
$t('ilansiz konusmada fotograf aranmaz',      !str_contains($sup, '<img'));

echo "\n== 4. Cizim: kimlik gizli kalir, ham ISO yok ==\n";
$thread = ['id'=>'T1','buyer_uid'=>'B1','seller_uid'=>'S1','listing_id'=>'lac-pique-polo',
  'last_at'=>'2026-09-07T09:52:00+00:00',
  'messages'=>[
    ['from'=>'B1','text'=>"Merhaba\nnavy var mi?",'at'=>'2026-09-07T09:12:00+00:00'],
    ['from'=>'system','meta'=>['kind'=>'order','ref'=>'O7K2Q1','status'=>'shipped','total'=>2460],'at'=>'2026-09-07T09:20:00+00:00'],
  ]];
$html = vestra_msg_panel_html('buyer', 'B1', 'T1', $thread, [$thread]);
$t('kabuk + acik konusma isareti', str_contains($html, 'class="msgshell has-thread"'));
$t('yazma kutusu var',             str_contains($html, 'class="msgcompose"') && str_contains($html, 'name="body"'));
$t('gonderme yolu alicinin paneli', str_contains($html, 'action="/buyer?tab=messages"'));
$t('geri baglantisi listeye doner', str_contains($html, 'class="msback" href="/buyer?tab=messages"'));
$t('gun ayraci basildi',           str_contains($html, 'class="msgday"'));
/* Yalniz GORUNEN kisim: yoklama betigi son damgayi JS degiskeni olarak tasiyor
   (karsilastirma icin), o ekranda yazi degil. */
$visible = substr($html, 0, strpos($html, '<script') ?: strlen($html));
$t('ham ISO ekranda yok',          !str_contains($visible, '2026-09-07T09'));
$t('okunur saat ekranda',          str_contains($visible, '>09:12<'));
$t('satici adi degil ident',       str_contains($html, 'lac-pique-polo') && !str_contains($html, 'Atelier'));
$t('cok satirli metin <br> ile',   str_contains($html, 'Merhaba<br'));
$t('sistem karti cizildi',         str_contains($html, 'msgoffer'));
$sellerHtml = vestra_msg_panel_html('seller', 'S1', 'T1', $thread, [$thread]);
$t('satici panelinde satici yolu', str_contains($sellerHtml, 'action="/seller?tab=messages"'));
$empty = vestra_msg_panel_html('buyer', 'B1', '', null, []);
$t('konusma yoksa has-thread yok', !str_contains($empty, 'has-thread'));
$t('bos liste metni alici icin',   str_contains($empty, 'Start a conversation'));
$t('bos listede yoklama betigi yok', !str_contains($empty, 'setInterval'));

echo "\n== 5. Taslak korunur (yoklama sayfayi yeniliyor) ==\n";
/* 15 sn'lik yoklama yeni mesaj gorunce location.reload() yapiyor. Yazilmakta
   olan metin saklanmazsa yenileme onu siler -- kullanici yazarken mesajini
   kaybeder. */
$t('yenilemeden once taslak saklanir', str_contains($html, 'sessionStorage.setItem(key,ta.value)'));
$t('sayfa acilinca taslak geri konur', str_contains($html, 'sessionStorage.getItem(key)'));
$t('gonderince taslak silinir',        str_contains($html, 'removeItem(key)'));
$t('taslak anahtari konusmaya ozel',   str_contains($html, '"vmsgdraft:"+"T1"'));

echo "\n== 6. Telefon duzeni (CSS'te olculebilir kurallar) ==\n";
$t('kabuk telefonda tek sutun',        str_contains($css, '.msgshell{grid-template-columns:1fr;--msh:auto}'));
/* Satir olcusu ACIKCA yaziliyor: ortulu `auto` satir, kabuk sabit boylu olsa
   bile icerige gore buyur ve yazma kutusu ekranin disina duser. */
$t('acik konusma satiri kabuga kilitli', str_contains($css, '.msgshell.has-thread{grid-template-rows:minmax(0,1fr)}'));
$t('konusma sutunu kucultulebilir',      str_contains($css, '.msgshell.has-thread .msmain{display:flex;height:100%;min-height:0}'));
$t('baloncuklar tek kaydirma alani',     str_contains($css, '.msgthread{flex:1;min-height:0'));
$t('kabuk alt cubugun ustunde biter',    str_contains($css, 'height:calc(100dvh - 130px - env(safe-area-inset-bottom,0px))'));
$t('geri dugmesi yalniz telefonda',      str_contains($css, '.msback{display:none') && str_contains($css, '.msback{display:grid}'));
$t('yazma alani 16px (iOS yakinlastirmasin)', str_contains($css, '.msgcompose textarea,.msgshell .mssearch input{font-size:16px}'));
$t('telefonda tum form alanlari 16px',   preg_match('~@media\(max-width:820px\)\{[^}]*?input:not\(\[type=checkbox\]\)~s', $css) === 1);
$t('gonder dugmesi 44px dokunma hedefi', str_contains($css, '.mssend{flex:none;height:44px'));
$t('panel sekme seridi acik sekmeyi ortaliyor', str_contains($src('inc/dash.php'), 'a.offsetLeft-(s.clientWidth-a.offsetWidth)/2'));
/* Ellipsis bir flex kabinde CALISMAZ: ad kirpilmadan zaman damgasinin altina
   giriyordu. */
$t('konusma adi blok (ellipsis calissin)', str_contains($css, '.tr-name{font-weight:600;font-size:14.5px;display:block'));
$t('RTL: geri oku aynalanir',              str_contains($css, '[dir="rtl"] .msback svg,[dir="rtl"] .mssend svg{transform:scaleX(-1)}'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
