<?php
/* Mesajlasmada OKUNDU onayi + kendi eyleminin rozeti yakmamasi.
 * (operator, 11 Eyl 2026: "mesajlarda mesajin okunup okunmadigi musteriye
 *  gorunsun ayrica, kendi mesaji okursa bildirim kalksin birsey yanmasin")
 *
 * IKI YONU DE tutuyor:
 *   - yanmasi GEREKEN: karsi tarafin mesaji / karsi tarafin dogurdugu sistem karti
 *   - yanmaMASI gereken: kendi yazdigi mesaj / KENDI eyleminin sistem karti
 * Tek yon yazilsaydi test yesil kalir, ya musteri kendi teklifiyle rozet yakmaya
 * devam ederdi ya da gercek bir mesaj sessizce gizlenirdi.
 */
$root = dirname(__DIR__);
define('VESTRA_MESSAGES', sys_get_temp_dir().'/vestra_msgrcpt_'.getmypid().'.json');
require_once $root.'/vestra/inc/i18n.php';
require_once $root.'/vestra/inc/messages.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$B = 'buyer-uid-1';
$S = 'seller-uid-1';
/* Konusmayi ELDE kuruyoruz: vestra_msg_send() off-platform suzgeci, e-posta ve
   push cagiriyor -- olculen sey okuma/rozet mantigi, gonderim yolu degil. */
$mk = function (array $msgs, array $read = []) use ($B, $S) {
    return ['id'=>'t1', 'buyer_uid'=>$B, 'seller_uid'=>$S, 'listing_id'=>'p1',
            'messages'=>$msgs, 'read'=>$read, 'last_at'=>'2026-09-11T10:00:00+00:00'];
};
$txt = fn(string $from) => ['from'=>$from, 'text'=>'hi', 'at'=>'2026-09-11T10:00:00+00:00'];
$sys = function (string $by = '') {
    $e = ['from'=>'system', 'meta'=>['kind'=>'offer','ref'=>'O1'], 'text'=>'', 'at'=>'2026-09-11T10:00:00+00:00'];
    if ($by !== '') $e['by'] = $by;
    return $e;
};

echo "\n== 1. KENDI eylemi KENDI rozetini yakmaz ==\n";
/* Olculen kusur buydu: alici kendi teklifini verince kendi rozeti yaniyordu --
   sistem karti 'from'=>'system' ile yaziliyor, yani "benden degil" sayiliyordu. */
$th = $mk([$sys($B)]);
$t('alici KENDI teklifi -> alicinin rozeti SONUK', vestra_msg_unread($th, $B) === false);
$t('ayni kart -> SATICININ rozeti YANIYOR',        vestra_msg_unread($th, $S) === true);
$th = $mk([$sys($S)]);
$t('satici "gonderildi" karti -> saticinin rozeti SONUK', vestra_msg_unread($th, $S) === false);
$t('ayni kart -> ALICININ rozeti YANIYOR',               vestra_msg_unread($th, $B) === true);

echo "\n== 1b. Ters yön: gizlenmemesi gerekenler ==\n";
$t('karsi tarafin MESAJI yakiyor',        vestra_msg_unread($mk([$txt($S)]), $B) === true);
$t('kendi MESAJI yakmiyor (eskiden beri)', vestra_msg_unread($mk([$txt($B)]), $B) === false);
/* 'by' TASIMAYAN eski kayitlar bugunku davranisi korumali: eksik bir aktor FAZLA
   haber verir, mesaji GIZLEMEZ. Yanlis yon bu olmali. */
$t("aktorsuz eski kart iki tarafi da yakiyor (geriye donuk)",
   vestra_msg_unread($mk([$sys()]), $B) === true && vestra_msg_unread($mk([$sys()]), $S) === true);
/* Bos uid, 'by' tasimayan bir kartla ESLESMEMELI ('' === '' tuzagi). */
$t("bos uid, aktorsuz kartla eslesmiyor", vestra_msg_unread($mk([$sys()]), '') === true);
$t('okunmus konusma sonuk',               vestra_msg_unread($mk([$txt($S)], [$B=>1]), $B) === false);
$t('okunduktan SONRA gelen mesaj yakiyor', vestra_msg_unread($mk([$txt($S), $txt($S)], [$B=>1]), $B) === true);
/* Karisik: 1 okunmus satici mesaji + kendi karti -> hala sonuk kalmali. */
$t('okunmus mesaj + kendi karti -> sonuk', vestra_msg_unread($mk([$txt($S), $sys($B)], [$B=>1]), $B) === false);
/* Ama kendi kartinin ARDINDA gercek bir mesaj varsa YANMALI -- 'continue' ile
   'return false' karistirilirsa bu iddia duser. */
$t('kendi karti + ardindan satici mesaji -> YANIYOR',
   vestra_msg_unread($mk([$sys($B), $txt($S)], [$B=>0]), $B) === true);

echo "\n== 2. Okundu onayi: muhatabin gordugu SAYIM ==\n";
$th = $mk([$txt($B), $txt($B), $txt($S)], [$S=>2]);
$t('satici 2 mesaj gormus -> read_upto 2', vestra_msg_read_upto($th, $S) === 2);
$t('0. mesaj OKUNDU',                      vestra_msg_read_upto($th, $S) > 0);
$t('1. mesaj OKUNDU',                      vestra_msg_read_upto($th, $S) > 1);
$t('2. mesaj OKUNMADI',                    !(vestra_msg_read_upto($th, $S) > 2));
$t('hic okumamis muhatap -> 0',            vestra_msg_read_upto($mk([$txt($B)]), $S) === 0);
$t('bos uid -> 0',                         vestra_msg_read_upto($th, '') === 0);
/* Eski tarih tabanli isaret SAYI degil: "okundu" diye YANLIS bir sey yazmaktansa
   hic yazmamak dogru. */
$t('eski tarih isareti -> 0 (okunmamis sayilir)',
   vestra_msg_read_upto($mk([$txt($B)], [$S=>'2026-09-01T00:00:00+00:00']), $S) === 0);
/* vestra_msg_delete() read[]'i yeniden hesaplamiyor, yani silme sonrasi isaret
   dizinin DISINA tasabiliyor. Kirpma dogru cevabi veriyor: karsi taraf silineni
   de gormustu, demek ki kalanlarin hepsini gormus. Kirpma olmasaydi gosterge
   her mesaji "okundu" diye basardi. */
$t('silme sonrasi tasan isaret KIRPILIYOR',
   vestra_msg_read_upto($mk([$txt($B), $txt($B)], [$S=>5]), $S) === 2);
$t('negatif isaret -> 0', vestra_msg_read_upto($mk([$txt($B)], [$S=>-3]), $S) === 0);

echo "\n== 3. Muhatap tek yerde çözülüyor ==\n";
$th = $mk([$txt($B)]);
$t('alicinin muhatabi SATICI', vestra_msg_other_uid($th, $B) === $S);
$t('saticinin muhatabi ALICI', vestra_msg_other_uid($th, $S) === $B);

echo "\n== 4. Yoklama okuma sayacını da taşıyor ==\n";
/* 'last_at' YETMEZ: karsi taraf okudugunda konusmaya hicbir sey eklenmiyor, yani
   ✓ hicbir zaman ✓✓ olmazdi -- tam da bekleyen kisi icin bozuk gorunurdu. */
$st = vestra_msg_poll_state($mk([$txt($B), $txt($B)], [$S=>1]), $B);
$t("yoklama 'read' aniari tasiyor",  array_key_exists('read', $st));
$t("yoklama 'last' aniari duruyor",  ($st['last'] ?? null) === '2026-09-11T10:00:00+00:00');
$t('read = MUHATABIN sayaci (1)',    $st['read'] === 1);
$t('kendi sayacini DONDURMUYOR',     vestra_msg_poll_state($mk([$txt($B)], [$B=>1, $S=>0]), $B)['read'] === 0);
$panels = ['buyer', 'seller'];
foreach ($panels as $p) {
    $src = (string)file_get_contents($root.'/vestra/'.$p.'.php');
    $t($p.'.php yoklamasi vestra_msg_poll_state cagiriyor', str_contains($src, 'vestra_msg_poll_state('));
    /* Ikinci bir kopya yazilmamali: iki panel kendi hesaplasaydi biri otekinden
       ayrisirdi (bu depoda dort mektup govdesi ayni dersi verdi). */
    $t($p.".php artik elle ['last'=>...] kurmuyor", !str_contains($src, "['last' => \$ok ?"));
}

echo "\n== 5. Çizim: onay yalnız KENDI baloncuğunda ==\n";
$src = (string)file_get_contents($root.'/vestra/inc/messages.php');
$t('dongu INDEKS aliyor ($i => $m)',        str_contains($src, 'foreach ($thread[\'messages\'] as $i => $m)'));
$t('onay yalniz $mine baloncukta',          str_contains($src, 'if ($mine && $showReceipt)'));
$t('olcut readUpto > $i',                   str_contains($src, '$seen = $readUpto > $i;'));
$t("etiket t('Read') / t('Sent')",           str_contains($src, "t('Read') : t('Sent')"));
/* VESTRA Support ipliginde HIC cizilmiyor: operator paneli butun konusmalari TEK
   sayfada listeliyor, yani "operator tam bu ipligi okudu" diyebilecegimiz bir an
   yok ve isaret hicbir zaman ilerleyemezdi. Asla ilerlemeyen bir gosterge bozuk
   bir gostergedir -- bu depoda kendine donen "geri" dugmesi ayni sinifti. */
$t('Support ipliginde onay cizilmiyor',     str_contains($src, "\$otherUid !== VESTRA_SUPPORT_UID"));
$t('yoklama betigi read farkina da bakiyor', str_contains($src, 'typeof d.read==="number"&&d.read!==seen'));

echo "\n== 5b. İşaret ÇİZİLİYOR, yazılmıyor ==\n";
/* Duz '✓✓' iki ayri harf: yazi tipine gore arasi aciliyor, bazi tiplerde kalin
   bir emoji olarak cikiyor. Cizim her yerde ayni. Sembol TEK kez tanimlanip
   <use> ile cagriliyor — 30 mesajlik konusmada ayni yol 30 kez gomulmesin. */
$render = function (array $msgs, array $read) use ($B, $S) {
    $th = ['id'=>'t1', 'buyer_uid'=>$B, 'seller_uid'=>$S, 'listing_id'=>'',
           'messages'=>$msgs, 'read'=>$read, 'last_at'=>'2026-09-11T10:00:00+00:00'];
    $html = vestra_msg_panel_html('buyer', $B, 't1', $th, [$th]);
    return substr($html, 0, strpos($html, '<script') ?: strlen($html)); // yoklama betigi haric
};
$vis = $render([$txt($B), $txt($B), $txt($S)], [$S=>1]);
$t('sembol tanimi TEK kez basiliyor',       substr_count($vis, 'class="msgtickdefs"') === 1);
$t('OKUNAN baloncukta cift cengel',         str_contains($vis, 'class="vt2" aria-hidden="true"><use href="#vtick2"'));
$t('GONDERILEN baloncukta tek cengel',      str_contains($vis, 'class="vt1" aria-hidden="true"><use href="#vtick1"'));
$t('duz ✓ karakteri KALMADI',               !str_contains($vis, '✓'));
$t('iki sembol de tanimli',                 str_contains($vis, 'id="vtick1"') && str_contains($vis, 'id="vtick2"'));
/* Renk/kalinlik CSS'te kaliyor: sembol kendi stroke'unu YAZMAMALI, yoksa
   `.seen` rengi ve tema degisimi hicbir sey yapmaz (SVG'de bunlar kalitimli). */
preg_match('~<svg width="0".*?</svg>~s', $vis, $dm);
$t('sembol kendi rengini SABITLEMIYOR',     isset($dm[0]) && !str_contains($dm[0], 'stroke=') && !str_contains($dm[0], 'fill='));
/* Karsi tarafin baloncugu ile sistem karti isaret TASIMAZ: ikisi de bizim degil.
   DIKKAT — sayim kapanis tirnagina KADAR: `class="msgtick` oneki sembol kutusunun
   `msgtickdefs`'ini de yakaliyordu ve ilk yazimda 2 yerine 3 saydi. Bu depoda
   mango -> Mangobay dersinin testin KENDI icindeki hali. */
$t('karsi tarafin baloncugunda isaret yok', preg_match_all('~class="msgtick( seen)?"~', $vis) === 2);
$css = (string)file_get_contents($root.'/vestra/inc/style.css');
$t('.msgtick rengi degiskenden',            str_contains($css, '.msgtick.seen{color:var(--acc)'));
$t('cizim stroke/fill CSS tarafinda',       str_contains($css, '.msgtick svg{') && str_contains($css, 'stroke:currentColor'));
/* Tek cengel cift olunca saat saga yasli oldugu icin satir SICRAR; sabit
   genislik bunu tutuyor. */
$t('isaret sabit genislikte',               str_contains($css, '.msgtick{min-width:'));
$t('defs gorunmez (yer kaplamiyor)',        str_contains($css, '.msgtickdefs{position:absolute'));

echo "\n== 6. Her çağrı yeri AKTÖRÜ geçiriyor ==\n";
/* Eksik bir aktor sessiz bir gerilemedir: kart yine yazilir, yalniz yanlis rozet
   yanar. Onun icin cagri yerleri ELLE degil, kaynagi ayristirarak sayiliyor. */
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/vestra'));
foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname(); }
$sites = 0; $withActor = 0;
foreach ($files as $f) {
    $s = (string)file_get_contents($f);
    $off = 0;
    while (($pos = strpos($s, 'vestra_msg_post_system(', $off)) !== false) {
        $off = $pos + 1;
        if (str_contains(substr($s, max(0, $pos - 40), 40), 'function ')) continue; // tanim
        $i = $pos + strlen('vestra_msg_post_system('); $d = 1; $commas = 0;
        while ($i < strlen($s) && $d > 0) {
            $c = $s[$i];
            if ($c === '(' || $c === '[' || $c === '{') $d++;
            elseif ($c === ')' || $c === ']' || $c === '}') $d--;
            elseif ($c === ',' && $d === 1) $commas++;
            $i++;
        }
        $sites++;
        if ($commas + 1 >= 5) $withActor++;
    }
}
$t('cagri yeri bulundu (>=14)',          $sites >= 14);
$t('HEPSI aktoru geciriyor',             $sites === $withActor);
$r = new ReflectionFunction('vestra_msg_post_system');
$t('5. parametre var ve VARSAYILANI bos', $r->getNumberOfParameters() === 5
   && $r->getParameters()[4]->isDefaultValueAvailable() && $r->getParameters()[4]->getDefaultValue() === '');

echo "\n== 7. 'Read' / 'Sent' 8 sözlükte de var ==\n";
require_once $root.'/vestra/inc/i18n.php';
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $dict = require $root.'/vestra/inc/lang/'.$lg.'.php';
    $has = !empty($dict['Read']) && !empty($dict['Sent']);
    $t("$lg: Read + Sent var",            $has);
    /* Ingilizce kalmis bir cevirim sessizce dusup fark edilmezdi. */
    if ($has) $t("$lg: cevrilmis (EN degil)", $dict['Read'] !== 'Read' && $dict['Sent'] !== 'Sent');
}

@unlink(VESTRA_MESSAGES);
echo "\n";
echo ($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
