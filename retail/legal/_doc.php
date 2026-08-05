<?php
/**
 * Hukuki sayfaların ortak kabuğu
 * ------------------------------
 * Bağlayıcı dil ALMANCA. Diğer dillerde sayfa yine Almanca metni gösterir ve
 * üstünde bunu söyleyen bir not çıkar — yarı çevrilmiş bir AGB'den daha dürüst
 * ve hukuken daha güvenli.
 *
 * İşletmeci verileri data/retail-settings.json'dan gelir. Eksikse sayfanın
 * başında bariz bir uyarı basılır: uydurulmuş adres/sicil numarası yazmak
 * yanlış olurdu, sessizce boş bırakmak ise Impressum'u kullanılamaz kılardı.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';

/** Sayfa başlangıcı. $titleKey sözlük anahtarı, $updated 'YYYY-MM-DD'. */
function vr_doc_start(string $titleKey, string $updated = '2026-08-01'): void
{
    vr_layout_start([
        'title'  => t($titleKey),
        'desc'   => t($titleKey) . ' — ' . vr_config('brand'),
        'jsonld' => [vr_jsonld_breadcrumbs([
            (string)vr_config('brand') => vr_url('/'),
            t($titleKey)               => null,
        ])],
    ]);

    echo '<section class="sec sec--tight"><div class="wrap"><div class="doc">';
    vr_breadcrumbs([t('footer_legal') => null, t($titleKey) => null]);

    echo '<h1>' . te($titleKey) . '</h1>';
    echo '<p class="doc__meta">' . te('legal_last_update', ['date' => $updated]) . ' · '
       . h((string)(vr_config('company')['legal_name'] ?? '')) . '</p>';

    if (vr_lang() !== 'de') {
        echo '<div class="notice"><strong>Rechtlich verbindlich ist die deutsche Fassung.</strong> '
           . 'This page is kept in German because German consumer law governs these terms.</div>';
    }

    if (!vr_company_complete()) {
        echo '<div class="notice" style="border-left-color:var(--ember)"><strong>'
           . te('legal_incomplete') . '</strong></div>';
    }
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

    echo '<hr class="rule" style="margin:40px 0 22px">';
    echo '<p style="font-size:12.5px;color:var(--muted);line-height:2">';
    $out = [];
    foreach ($links as $path => $label) {
        $out[] = '<a href="' . h(vr_url($path)) . '">' . h($label) . '</a>';
    }
    echo implode(' · ', $out);
    echo '</p>';

    echo '</div></div></section>';
    vr_layout_end();
}

/** İşletmeci bloğu — Impressum ve AGB'de aynı veriden basılır. */
function vr_company_block(): void
{
    $c = vr_config('company');
    $f = static fn(string $k): string => trim((string)($c[$k] ?? ''));
    $ph = static fn(string $hint): string =>
        '<em style="color:var(--ember)">[' . h($hint) . ']</em>';

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
    echo 'Vertreten durch: ' . ($f('represented_by') !== '' ? h($f('represented_by')) : $ph('vertretungsberechtigte Person'));
    echo '<br>E-Mail: ' . ($f('email') !== ''
        ? '<a href="mailto:' . h($f('email')) . '">' . h($f('email')) . '</a>'
        : $ph('E-Mail-Adresse'));
    if ($f('phone') !== '') echo '<br>Telefon: ' . h($f('phone'));
    echo '<br>Website: <a href="' . h(vr_origin()) . '">' . h(vr_origin()) . '</a>';
    echo '</p>';

    echo '<p>';
    echo 'Registerbehörde: ' . ($f('reg_authority') !== '' ? h($f('reg_authority')) : $ph('Registerbehörde'));
    echo '<br>Registernummer: ' . ($f('reg_number') !== '' ? h($f('reg_number')) : $ph('Register-/Filing-Nummer'));
    if ($f('vat_id') !== '') {
        echo '<br>Umsatzsteuer-Identifikationsnummer: ' . h($f('vat_id'));
    } else {
        echo '<br>Umsatzsteuer-Identifikationsnummer: ' . $ph('USt-IdNr., falls vorhanden');
    }
    echo '</p>';

    if ($f('eu_rep') !== '') {
        echo '<p>Vertreter in der EU (Art. 27 DSGVO): ' . h($f('eu_rep')) . '</p>';
    }
}
