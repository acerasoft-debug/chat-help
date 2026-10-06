<?php
/**
 * order_note: sarmalin DILI + HITAPSIZ kip (operator, 1 Eki 2026, LA ISLA DE MIRABEL SL /
 * O7BA9A: Ispanyolca bir metin verip "bu emaili VESTRA'dan email ile gonder, mesaj ile
 * degil" dedi).
 *
 * NEDEN: vestra_tpl_order_note tek dilliydi (en). Ispanyolca bir metin "Dear X," ile
 * "Kind regards," arasina giderdi, kutunun basligi/dugmesi Ingilizce kalirdi ve metin
 * zaten "Hola," ile basladigi icin hitap IKI KEZ yazilirdi ("Dear LA ISLA..., / Hola,").
 * Operatorun yazdigi metin bir sablona yapistirilmis gibi gorunurdu.
 *
 * UC SEY OLCULUYOR (her biri ayri bir kirilma noktasi):
 *   1. ESKI DAVRANIS DEGISMEDI: dil/hitap verilmeden cikti onceki surumle BIREBIR ayni.
 *      Ornekler elle, harfi harfine yazili -- "yeni ozellik eskiyi bozmaz" iddiasi bir
 *      yerde olculmuyorsa soylenmis olmaz. (Ayrica tek seferlik: 480 girdi kombinasyonu
 *      eski fonksiyonla karsilastirildi, fark 0 -- bu dosyanin degil, gelistirme
 *      sirasinin olcusu.)
 *   2. YENI DAVRANIS: es/fr/de sarmali, hitapsiz kip, taninmayan dil -> en, dil ve
 *      kutu/dugme etiketleri, metnin OLDUGU GIBI korunmasi, baska dilden kalinti YOK.
 *   3. IS AKISI KABLOLAMASI: lang/nogreet spec'ten okunuyor ve sablona GIDIYOR, taninmayan
 *      dil mektup kurulmadan DURUR, cift hitap UYARISI var, metin kutuge basilmiyor.
 *      Hitap algilayicinin regex'i kaynaktan cikarilip KOSTURULUYOR (iki yon).
 */
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/email_templates.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. eski davranis (dil/hitap verilmeden) AYNEN duruyor ==\n";
[$s, $b, $o] = vestra_tpl_order_note('Test Sp.', 'VES-X', 'Size L is fine.', '', true, 'Marco Bellini');
$t('en: konu',   $s === 'VESTRA — update on your order VES-X');
$t('en: govde',  $b === "Dear Test Sp.,\n\nSize L is fine.\n\nKind regards,\n\nMarco Bellini\nVESTRA – vestrasales.com");
$t('en: kutu ve dugme', $o === ['badge' => 'Order update',
    'rows' => [['label' => 'Order ref', 'value' => 'VES-X']],
    'button' => ['label' => 'View my order', 'url' => 'https://vestrasales.com/order-confirm?ref=VES-X']]);
[, $b0, $o0] = vestra_tpl_order_note('', 'VES-X', "  a\nb  ");
$t('en: bos ad -> "Dear Customer", sirket imzasi, hesap yok -> dugme yok',
    $b0 === "Dear Customer,\n\na\nb\n\nKind regards,\n\nVESTRA · Acerasoft LLC\nsupport@vestrasales.com · vestrasales.com"
    && !isset($o0['button']));
$base = vestra_tpl_order_note('Test Sp.', 'VES-X', 'm', 'S', true, 'Marco Bellini');
foreach (['en', 'EN', '', 'it', 'xx', 'pt'] as $l) {
    $t("lang='$l' -> en ile BIREBIR ayni (taninmayan dil hata degil)",
       vestra_tpl_order_note('Test Sp.', 'VES-X', 'm', 'S', true, 'Marco Bellini', $l) === $base);
}
$t('hitap bayragi acikken (varsayilan) de ayni', vestra_tpl_order_note('Test Sp.', 'VES-X', 'm', 'S', true, 'Marco Bellini', 'en', true) === $base);

echo "\n== 2. Ispanyolca, hitapsiz (operatorun metni kendi 'Hola,'siyla basliyor) ==\n";
$msg = "Hola, gracias por su nuevo pedido de 80 unidades del polo Lacoste.\n\nLe rogamos que realice el pago de la factura INV-2026-1021 lo antes posible.\n\nQuedamos a su disposición para cualquier consulta.";
[$s, $b, $o] = vestra_tpl_order_note('LA ISLA DE MIRABEL SL', 'O7BA9A', $msg, '', true, 'Marco Bellini', 'es', false);
$t('govde metinle BASLAR (sablon hitap eklemiyor)',   str_starts_with($b, 'Hola, gracias por su nuevo pedido'));
$t('"Estimado" ve "Dear" YOK',                         !str_contains($b, 'Estimado') && !str_contains($b, 'Dear'));
$t('metin OLDUGU GIBI (aksan, bos satirlar, numara)',  str_contains($b, $msg));
$t('kapanis "Un cordial saludo" + persona imzasi',    str_ends_with($b, "\n\nUn cordial saludo,\n\nMarco Bellini\nVESTRA – vestrasales.com"));
$t('Ingilizce kalinti YOK (govde, konu, kutu, dugme)', !preg_match('/Kind regards|Dear |View my order|Order update|Order ref/', $b.$s.json_encode($o)));
$t('kutu basligi, satir etiketi, dugme Ispanyolca',    $o['badge'] === 'Pedido' && $o['rows'][0]['label'] === 'Pedido' && $o['rows'][0]['value'] === 'O7BA9A' && $o['button']['label'] === 'Ver mi pedido');
$t('dugme adresi dilden bagimsiz AYNI',                $o['button']['url'] === 'https://vestrasales.com/order-confirm?ref=O7BA9A');
$t('varsayilan konu Ispanyolca ve siparis no tasir',   str_contains($s, 'O7BA9A') && str_contains($s, 'novedades'));
$t('verilen konu OLDUGU GIBI kullanilir',              vestra_tpl_order_note('X', 'O1', 'm', 'Mi asunto', false, '', 'es', false)[0] === 'Mi asunto');
$t('dil buyuk harfle de calisir',                      vestra_tpl_order_note('X', 'O1', 'm', '', false, '', 'ES')[2]['badge'] === 'Pedido');
$t('hesap yoksa dugme yok',                            !isset(vestra_tpl_order_note('X', 'O1', 'm', '', false, '', 'es', false)[2]['button']));

echo "\n== 2b. Ispanyolca, hitapli (metin hitap tasimiyorsa) ==\n";
[, $bh] = vestra_tpl_order_note('LA ISLA DE MIRABEL SL', 'O7BA9A', 'Gracias.', '', true, '', 'es', true);
$t('"Estimado/a <ad>," ile baslar',     str_starts_with($bh, "Estimado/a LA ISLA DE MIRABEL SL,\n\nGracias."));
$t('persona yoksa sirket imzasi',       str_contains($bh, "VESTRA · Acerasoft LLC\nsupport@vestrasales.com"));
[, $bc] = vestra_tpl_order_note('', 'O1', 'x', '', false, '', 'es');
$t('bos ad -> "Estimado/a cliente,"',   str_starts_with($bc, "Estimado/a cliente,\n\n"));

echo "\n== 2c. fr / de ==\n";
[, $bf, $of] = vestra_tpl_order_note('Mob', 'VES-1', 'Merci.', '', true, 'Elena Romano', 'fr');
$t('fr: "Bonjour <ad>," ... "Cordialement,"', str_starts_with($bf, "Bonjour Mob,\n\nMerci.") && str_contains($bf, "\n\nCordialement,\n\nElena Romano"));
$t('fr: kutu ve dugme',                       $of['badge'] === 'Commande' && $of['button']['label'] === 'Voir ma commande');
[, $bg, $og] = vestra_tpl_order_note('Arelisshop', 'VES-2', 'Danke.', '', true, 'Marco Bellini', 'de');
$t('de: "Guten Tag <ad>," ... "Mit freundlichen Grüßen,"', str_starts_with($bg, "Guten Tag Arelisshop,\n\nDanke.") && str_contains($bg, "\n\nMit freundlichen Grüßen,\n\nMarco Bellini"));
$t('de: kutu ve dugme',                       $og['badge'] === 'Bestellung' && $og['button']['label'] === 'Bestellung ansehen');
[, $bg2] = vestra_tpl_order_note('', 'VES-2', 'Danke.', '', false, '', 'de');
$t('de bos ad: "Sehr geehrte Kundin, sehr geehrter Kunde,"', str_starts_with($bg2, 'Sehr geehrte Kundin, sehr geehrter Kunde,'));
[, $bf2] = vestra_tpl_order_note('', 'VES-1', 'Merci.', '', false, '', 'fr');
$t('fr bos ad: "Bonjour Madame, Monsieur,"',  str_starts_with($bf2, 'Bonjour Madame, Monsieur,'));

echo "\n== 2d. diller birbirine KARISMIYOR ==\n";
$closing = ['en' => 'Kind regards,', 'fr' => 'Cordialement,', 'de' => 'Mit freundlichen Grüßen,', 'es' => 'Un cordial saludo,'];
foreach ($closing as $lang => $mine) {
    [$sx, $bx, $ox] = vestra_tpl_order_note('Firma', 'VES-9', 'metin', '', true, 'Marco Bellini', $lang);
    $all = $sx."\n".$bx."\n".json_encode($ox, JSON_UNESCAPED_UNICODE);
    $others = array_diff_key($closing, [$lang => 1]);
    $leak = array_filter($others, fn($c) => str_contains($all, $c));
    $t("$lang: kendi kapanisi VAR, baska dilin kapanisi YOK", str_contains($bx, $mine) && !$leak);
}

echo "\n== 3. is akisi kablolamasi ==\n";
$sp = (string)file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$na = strpos($sp, "\$letter === 'order_note'");
$nz = $na === false ? false : strpos($sp, "} elseif (\$letter === 'listing_reply')", $na);
$code = ($na !== false && $nz !== false) ? preg_replace('~/\*.*?\*/~s', '', substr($sp, $na, $nz - $na)) : '';   // yorum kod degil
$t('order_note dali bulundu', $code !== '');
$t('lang spec\'ten okunuyor, VARSAYILAN en',
    (bool)preg_match('/\$onLang\s*=\s*strtolower\(trim\(\$E\(\'lang\'\)\)\)\s*\?:\s*\'en\'\s*;/', $code));
$t('taninmayan dil mektup kurulmadan DURUR',
    (bool)preg_match('/in_array\(\$onLang,\s*\[\'en\',\s*\'fr\',\s*\'de\',\s*\'es\'\],\s*true\)\)\s*\{\s*fwrite\(STDERR[^\n]*?exit\(1\)/', $code)
    && strpos($code, 'in_array($onLang') < strpos($code, 'vestra_tpl_order_note('));
$t('hitapsiz kip YALNIZ nogreet=1 ile',
    (bool)preg_match('/\$onGreet\s*=\s*trim\(\$E\(\'nogreet\'\)\)\s*!==\s*\'1\'\s*;/', $code));
$t('sablona dil VE hitap bayragi gidiyor',
    (bool)preg_match('/vestra_tpl_order_note\([^;]*\$persona,\s*\$onLang,\s*\$onGreet\)\s*;/', $code));
$t('onizleme dili ve hitap kararini yaziyor',
    str_contains($code, 'dil     : {$onLang}') && str_contains($code, 'hitap   :'));
$t('cift hitapta UYARI (yalniz sablon hitap ekleyecekse)',
    str_contains($code, 'if ($onGreet && $onHasHi) echo "UYARI'));
$t('metin kutuge BASILMAZ (yalniz uzunluk)',
    !preg_match('/echo[^;]*\$onMsg\b(?!\))/', $code) && str_contains($code, 'mb_strlen($onMsg)'));
$t('eski korumalar yerinde: to=order SART, bos metin DURUR, iptal DURUR',
    str_contains($code, 'if (!$orderRow)') && str_contains($code, "if (\$onMsg === '')")
    && str_contains($code, "if (\$onSt === 'cancelled' && trim(\$E('cancelled_ok')) !== '1') { fwrite(STDERR"));
$t('girdi aciklamasi lang ve nogreet\'i anlatiyor',
    str_contains($sp, 'lang=en|fr|de|es (sarmalın dili') && str_contains($sp, 'nogreet=1 (metin kendi hitabıyla başlıyorsa'));

echo "\n== 3b. hitap algilayici (regex kaynaktan cikarilip KOSTURULUYOR) ==\n";
$re = '';
$p0 = strpos($code, '$onHasHi');
if ($p0 !== false) {
    $q0 = strpos($code, "preg_match('", $p0);
    if ($q0 !== false) {
        $q0 += strlen("preg_match('");
        $q1 = strpos($code, "', \$onMsg)", $q0);
        if ($q1 !== false) $re = substr($code, $q0, $q1 - $q0);
    }
}
$t('regex cikarildi', $re !== '' && @preg_match($re, '') !== false);
if ($re !== '' && @preg_match($re, '') !== false) {
    foreach (['Hola, gracias por su pedido', '  Estimados señores,', 'Estimada Ana', 'Buenos días', 'Buenas tardes', 'Bonjour Madame',
              'Bonsoir', 'Hallo Herr Müller', 'Guten Tag', 'Sehr geehrte Damen', 'Dear John', 'hello', 'HI there', "\nHola"] as $pos) {
        $t('hitap SAYILIR: '.str_replace("\n", '\n', $pos), preg_match($re, ltrim($pos, "\n")) === 1);
    }
    foreach (['Gracias por su pedido', 'Holanda es un pais', 'Size L is fine', 'Hindi', 'Hiking boots', 'Dearest', 'Estimation: 80',
              'Your order is ready', 'Le rogamos que realice el pago', 'Hallow'] as $neg) {
        $t('hitap SAYILMAZ: '.$neg, preg_match($re, $neg) === 0);
    }
}

echo "\n".($fail ? "KALDI: $fail" : "gecti: $ok")." iddia\n";
exit($fail ? 1 : 0);
