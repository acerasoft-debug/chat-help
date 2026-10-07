<?php
/**
 * Hukuki sayfaların ortak kabuğu
 * ------------------------------
 * Bağlayıcı dil ALMANCA. Metinler dile göre legal/content/<dil>/<belge>.php
 * dosyalarından gelir:
 *
 *   • o dilde çeviri varsa  → çeviri gösterilir, altında kısa bir not:
 *                             "Almanca metin bağlayıcıdır" (legal_de_note)
 *   • çeviri yoksa          → Almanca metin gösterilir, üstünde belirgin not:
 *                             "bu sayfa yalnızca Almanca" (legal_de_only);
 *                             kapsayıcı lang="de" alır ki ekran okuyucu doğru
 *                             dilde okusun
 *
 * Çeviriler okunabilirlik için, hukuki yorum için değil. Yarı çevrilmiş bir
 * AGB'den daha dürüst olan şey, çevirinin ne olduğunu söylemektir.
 *
 * İşletmeci verileri data/retail-settings.json'dan gelir. Eksikse sayfanın
 * başında bariz bir uyarı basılır: uydurulmuş adres/sicil numarası yazmak
 * yanlış olurdu, sessizce boş bırakmak ise Impressum'u kullanılamaz kılardı.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';

/**
 * Bir hukuki belgeyi baştan sona basar.
 *
 * @param string $doc      content/<dil>/ altındaki dosya adı (uzantısız)
 * @param string $titleKey sözlük anahtarı
 * @param string $updated  'YYYY-MM-DD'
 * @param array  $vars     içerik dosyasına açılacak değişkenler
 */
function vr_doc_page(string $doc, string $titleKey, string $updated, array $vars = []): void
{
    $doc  = preg_replace('/[^a-z]/', '', $doc);
    $lang = vr_lang();
    $base = __DIR__ . '/content/';

    $file = $base . $lang . '/' . $doc . '.php';
    $mode = 'translated';
    if ($lang === 'de') {
        $mode = 'native';
    } elseif (!is_file($file)) {
        $mode = 'fallback';
        $file = $base . 'de/' . $doc . '.php';
    }

    vr_doc_start($titleKey, $updated, $mode);
    extract($vars, EXTR_SKIP);
    include $file;
    vr_doc_end();
}

/** Sayfa başlangıcı. $mode: native | translated | fallback (bkz. üst yorum). */
function vr_doc_start(string $titleKey, string $updated = '2026-08-01', string $mode = 'native'): void
{
    vr_layout_start([
        'title'  => t($titleKey),
        'desc'   => t($titleKey) . ' — ' . vr_config('brand'),
        'jsonld' => [vr_jsonld_breadcrumbs([
            (string)vr_config('brand') => vr_url('/'),
            t($titleKey)               => null,
        ])],
    ]);

    // Fallback'te gövde Almanca: lang="de" ekran okuyucuya ve çevirmen
    // eklentilerine doğru dili söyler.
    $langAttr = $mode === 'fallback' ? ' lang="de"' : '';

    echo '<section class="sec sec--tight"><div class="wrap"><div class="doc"' . $langAttr . '>';
    vr_breadcrumbs([t('footer_legal') => null, t($titleKey) => null]);

    echo '<h1>' . te($titleKey) . '</h1>';
    echo '<p class="doc__meta">' . te('legal_last_update', ['date' => $updated]) . ' · '
       . h((string)(vr_config('company')['legal_name'] ?? '')) . '</p>';

    if ($mode === 'fallback') {
        echo '<div class="notice" lang="' . h(vr_locale()) . '">' . te('legal_de_only') . '</div>';
    } elseif ($mode === 'translated') {
        echo '<p class="doc__binding">' . te('legal_de_note') . '</p>';
    }

    /* Eksik işletmeci verisi ARTIK ziyaretçiye bant olarak basılmıyor: dosya
       yolu geçen, operatöre yazılmış bir cümleyi müşterinin görmesi için
       sebep yok. Eksik alan şirket bloğunun içinde yer tutucuyla zaten
       görünür (vr_company_block), selftest de FAIL veriyor. */
}

function vr_doc_end(): void
{
    // Hukuki sayfaların altında birbirine geçiş — kullanıcı aradığı metni
    // bulmak için altlığa inmek zorunda kalmasın.
    $links = [
        'legal/impressum.php'        => t('legal_imprint'),
        'legal/agb.php'              => t('legal_terms'),
        'legal/widerruf.php'         => t('legal_withdrawal'),
        'legal/rueckgabe.php'        => t('legal_returns'),
        'legal/versand.php'          => t('legal_shipping'),
        'legal/zahlung.php'          => t('legal_payment'),
        'legal/datenschutz.php'      => t('legal_privacy'),
        'legal/cookies.php'          => t('legal_cookies'),
        'legal/verkaeufer.php'       => t('legal_seller_terms'),
        'legal/streitbeilegung.php'  => t('legal_disputes'),
        'legal/barrierefreiheit.php' => t('legal_accessibility'),
    ];

    echo '<hr class="rule doc__rule">';
    echo '<p class="doc__related">';
    $out = [];
    foreach ($links as $path => $label) {
        $out[] = '<a href="' . h(vr_url($path)) . '">' . h($label) . '</a>';
    }
    echo implode(' · ', $out);
    echo '</p>';

    echo '</div></div></section>';
    vr_layout_end();
}

/**
 * İşletmeci bloğu — Impressum, AGB ve Datenschutz'ta aynı veriden basılır.
 * Etiketler sözlükten gelir; değerler her dilde aynıdır (adres adrestir).
 * Boş alan, operatörün görmesi gereken bir yer tutucuyla işaretlenir.
 */
function vr_company_block(): void
{
    $c = vr_config('company');
    $f = static fn(string $k): string => trim((string)($c[$k] ?? ''));
    $ph = static fn(string $hint): string =>
        '<em class="doc__missing">[' . h($hint) . ']</em>';

    echo '<p>';
    echo '<strong>' . h($f('legal_name')) . '</strong>';
    if ($f('form') !== '') echo '<br>' . h($f('form'));

    echo '<br>' . ($f('street') !== '' ? h($f('street')) : $ph('Straße und Hausnummer'));
    $cityLine = trim($f('zip') . ' ' . $f('city'));
    echo '<br>' . ($cityLine !== '' ? h($cityLine) : $ph('PLZ und Ort'));
    if ($f('state') !== '') echo '<br>' . h($f('state'));
    echo '<br>' . ($f('country') !== '' ? h($f('country')) : $ph('Land'));
    echo '</p>';

    echo '<p>';
    echo te('imp_represented_by') . ': ' . ($f('represented_by') !== '' ? h($f('represented_by')) : $ph('vertretungsberechtigte Person'));
    echo '<br>' . te('imp_email') . ': ' . ($f('email') !== ''
        ? '<a href="mailto:' . h($f('email')) . '">' . h($f('email')) . '</a>'
        : $ph('E-Mail-Adresse'));
    if ($f('phone') !== '') echo '<br>' . te('imp_phone') . ': ' . h($f('phone'));
    echo '<br>' . te('imp_website') . ': <a href="' . h(vr_origin()) . '">' . h(vr_origin()) . '</a>';
    echo '</p>';

    echo '<p>';
    echo te('imp_reg_authority') . ': ' . ($f('reg_authority') !== '' ? h($f('reg_authority')) : $ph('Registerbehörde'));
    echo '<br>' . te('imp_reg_number') . ': ' . ($f('reg_number') !== '' ? h($f('reg_number')) : $ph('Register-/Filing-Nummer'));
    echo '<br>' . te('imp_vat_id') . ': ' . ($f('vat_id') !== '' ? h($f('vat_id')) : $ph('USt-IdNr., falls vorhanden'));
    echo '</p>';

    if ($f('eu_rep') !== '') {
        echo '<p>' . te('imp_eu_rep') . ': ' . h($f('eu_rep')) . '</p>';
    }
}
