<?php
/**
 * Kurulum kontrolü — CLI
 * ======================
 *   php tools/selftest.php            # okuma kontrolleri
 *   php tools/selftest.php --stripe   # Stripe'a GERÇEK bir istek de atar
 *
 * Web'den çağrılamaz: hangi anahtarların yüklü olduğunu, yolları ve dosya
 * izinlerini gösteriyor — bu bilgi dışarıya sızmamalı.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile: php tools/selftest.php\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/../inc/listings.php';

$withStripe = in_array('--stripe', $argv, true);
$fail = 0;
$warn = 0;

function line(string $status, string $label, string $detail = ''): void
{
    global $fail, $warn;
    if ($status === 'FAIL') $fail++;
    if ($status === 'WARN') $warn++;
    printf("  [%-4s] %-34s %s\n", $status, $label, $detail);
}

function head(string $t): void { echo "\n== {$t} ==\n"; }

echo "MAXSALES Retail — Selbsttest\n";
echo str_repeat('=', 62) . "\n";

// ----------------------------------------------------------------- ortam
head('Umgebung');
line(PHP_VERSION_ID >= 80100 ? 'OK' : 'FAIL', 'PHP-Version', PHP_VERSION . ' (>= 8.1 nötig)');
foreach (['curl', 'json', 'mbstring'] as $ext) {
    line(extension_loaded($ext) ? 'OK' : 'FAIL', 'Extension ' . $ext, extension_loaded($ext) ? 'geladen' : 'FEHLT');
}
line(extension_loaded('gd') ? 'OK' : 'WARN', 'Extension gd',
     extension_loaded('gd') ? 'Bilder werden neu kodiert (EXIF entfernt)' : 'fehlt — Uploads werden unverändert gespeichert');

// ------------------------------------------------------------------ yollar
head('Pfade');
line('OK', 'App-Wurzel', realpath(VR_ROOT) ?: VR_ROOT);
line(is_dir(vr_data_dir()) ? 'OK' : 'FAIL', 'Datenverzeichnis', vr_data_dir());
line(is_writable(vr_data_dir()) ? 'OK' : 'FAIL', 'Datenverzeichnis beschreibbar',
     is_writable(vr_data_dir()) ? 'ja' : 'NEIN — chmod 0750 und Eigentümer prüfen');
line('OK', 'Basis-URL', vr_base_url() === '' ? '(Wurzel)' : vr_base_url());
line('OK', 'Origin', vr_origin());
$docRoot = vr_doc_root();
line(is_dir($docRoot) ? 'OK' : 'WARN', 'Document root', $docRoot);
line(is_dir($docRoot . '/uploads') ? 'OK' : 'WARN', 'uploads/ vorhanden',
     is_dir($docRoot . '/uploads') ? 'ja' : 'fehlt — wird beim ersten Upload erstellt');

// ------------------------------------------------------------- yazma testi
head('Schreibtest');
$probe = 'retail-selftest.json';
$ok = vr_store_write($probe, ['at' => time(), 'test' => true]);
line($ok ? 'OK' : 'FAIL', 'Atomisches Schreiben', $ok ? 'erfolgreich' : 'fehlgeschlagen');
if ($ok) {
    $back = vr_store_read($probe, []);
    line(!empty($back['test']) ? 'OK' : 'FAIL', 'Zurücklesen', !empty($back['test']) ? 'Inhalt stimmt' : 'Inhalt falsch');
    @unlink(vr_data_dir() . '/' . $probe);
}
[$lockOk] = vr_store_mutate($probe, fn($d) => ['locked' => true]);
line($lockOk ? 'OK' : 'FAIL', 'Sperren (flock)', $lockOk ? 'funktioniert' : 'fehlgeschlagen');
@unlink(vr_data_dir() . '/' . $probe);
@unlink(vr_data_dir() . '/' . $probe . '.lock');

// -------------------------------------------------------------------- katalog
head('Katalog');
$cat = vr_catalog();
line($cat ? 'OK' : 'WARN', 'Artikel geladen', count($cat) . ' Artikel'
    . (vr_catalog_is_demo() ? ' (DEMO-Daten — data/listings.json nicht gefunden)' : ''));
$live = vr_store_read('listings.json', null);
line(is_array($live) ? 'OK' : 'WARN', 'Live-Katalog listings.json',
     is_array($live) ? count($live) . ' Zeilen (nur lesend)' : 'nicht gefunden');

$withStock = 0;
$withImg   = 0;
foreach ($cat as $p) {
    if ((int)$p['stock'] > 0) $withStock++;
    if (!empty($p['images'])) $withImg++;
}
line($withStock > 0 ? 'OK' : 'WARN', 'Artikel mit Bestand', (string)$withStock);
line('OK', 'Artikel mit echten Bildern', $withImg . ' von ' . count($cat)
    . ($withImg < count($cat) ? ' (Rest: generierte Grafik)' : ''));

$facets = vr_facets();
line('OK', 'Marken / Kategorien', count($facets['brands']) . ' / ' . count($facets['cats']));

// ---------------------------------------------------------------------- Vault
head('Vault (Premium Outlet)');
$lots = vr_vault_lots(['include_sold' => true, 'include_scheduled' => true]);
line('OK', 'Lose insgesamt', (string)count($lots));
foreach (array_slice($lots, 0, 5) as $v) {
    $st = $v['state'];
    line('OK', '  ' . substr($v['lot_id'], 0, 14),
        sprintf('%s | Stufe %d/%d | jetzt %s | Boden %s | %s',
            substr($v['product']['brand'], 0, 12),
            (int)$st['step'] + 1, (int)$st['steps'] + 1,
            vr_money((int)$st['cents']), vr_money((int)$st['floor_cents']),
            $v['sold'] ? 'VERKAUFT' : ($st['phase'] === 'live' ? 'live' : 'geplant')));
}
// Plan tutarlılığı: fiyat asla tabanın altına düşmemeli, asla açılışı geçmemeli.
$bad = 0;
foreach ($lots as $v) {
    $st = $v['state'];
    if ($st['cents'] < $st['floor_cents'] || $st['cents'] > $st['open_cents']) $bad++;
}
line($bad === 0 ? 'OK' : 'FAIL', 'Preise innerhalb des Plans', $bad === 0 ? 'alle korrekt' : "{$bad} Los(e) außerhalb");

// -------------------------------------------------------------------- Satıcı
head('Verkäufer & Angebote');
$sellers = vr_sellers();
$ready = 0;
foreach ($sellers as $s) if (!empty($s['charges_enabled'])) $ready++;
line('OK', 'Verkäuferkonten', count($sellers) . ' (davon ' . $ready . ' auszahlungsbereit)');
$listings = vr_listings_all();
$pending = 0;
foreach ($listings as $l) if ((string)($l['status'] ?? '') === 'review') $pending++;
line('OK', 'Verkäuferangebote', count($listings) . ' (' . $pending . ' in Prüfung)');
if ($pending > 0) line('WARN', '  Prüfung offen', 'php tools/moderate.php list');

// -------------------------------------------------------------------- Stripe
head('Stripe');
$cfg = vr_stripe_settings();
line($cfg['configured'] ? 'OK' : 'FAIL', 'Secret Key',
     $cfg['configured'] ? 'vorhanden (Modus: ' . $cfg['mode'] . ')' : 'FEHLT — keine Zahlung möglich');
line($cfg['publishable_key'] !== '' ? 'OK' : 'WARN', 'Publishable Key',
     $cfg['publishable_key'] !== '' ? 'vorhanden' : 'fehlt (für Checkout-Redirect nicht nötig)');
line($cfg['webhook_ready'] ? 'OK' : 'FAIL', 'Webhook Secret',
     $cfg['webhook_ready'] ? 'vorhanden' : 'FEHLT — Bestellungen werden nie als bezahlt markiert');
line('OK', 'Webhook-URL', vr_abs('api/webhook.php'));
line('OK', 'Provision', sprintf('Händler %s%% · privat %s%% · Vault %s%% · fix %s',
    (int)vr_config('fee_bps_business') / 100, (int)vr_config('fee_bps_private') / 100,
    (int)vr_config('fee_bps_outlet') / 100, vr_money((int)vr_config('fee_fixed_cents'))));

if ($withStripe && $cfg['configured']) {
    $r = vr_stripe_request('GET', 'balance');
    line($r['ok'] ? 'OK' : 'FAIL', 'Live-API-Aufruf (/v1/balance)',
         $r['ok'] ? 'Antwort erhalten, Schlüssel gültig' : $r['error']);

    $acct = vr_stripe_request('GET', 'account');
    if ($acct['ok']) {
        $d = $acct['data'];
        line('OK', '  Plattformkonto', (string)($d['id'] ?? '?') . ' · ' . (string)($d['country'] ?? '?')
            . ' · charges_enabled=' . (!empty($d['charges_enabled']) ? 'true' : 'false'));
        // Connect açık mı? Kapalıysa satıcı onboarding'i çalışmaz.
        $caps = (array)($d['capabilities'] ?? []);
        line(!empty($caps['transfers']) ? 'OK' : 'WARN', '  Connect (transfers)',
             !empty($caps['transfers']) ? (string)$caps['transfers'] : 'nicht aktiv — im Stripe-Dashboard Connect aktivieren');
    }
} elseif ($cfg['configured']) {
    line('WARN', 'Live-API-Aufruf', 'übersprungen — mit --stripe ausführen');
}

// ---------------------------------------------------------------------- Mail
head('E-Mail');
$mail = vr_mail_settings();
line($mail['enabled'] ? 'OK' : 'WARN', 'Versand aktiv',
     $mail['enabled'] ? $mail['provider'] . ' · Absender ' . $mail['from'] : 'kein API-Key — Fallback auf mail()');

// -------------------------------------------------------------------- hukuk
head('Rechtliches');
line(vr_company_complete() ? 'OK' : 'FAIL', 'Impressumsangaben',
     vr_company_complete() ? 'vollständig' : 'UNVOLLSTÄNDIG — data/retail-settings.json ausfüllen');
$c = vr_config('company');
foreach (['street', 'city', 'country', 'reg_authority', 'reg_number', 'represented_by', 'email'] as $k) {
    if (trim((string)($c[$k] ?? '')) === '') line('WARN', '  fehlt: company.' . $k, '');
}

// ------------------------------------------------------------------- diller
head('Sprachen');
$langs = (array)vr_config('languages');
$en = include VR_ROOT . '/inc/lang/en.php';
foreach ($langs as $lg) {
    $f = VR_ROOT . '/inc/lang/' . $lg . '.php';
    if (!is_readable($f)) { line('FAIL', $lg, 'Datei fehlt: inc/lang/' . $lg . '.php'); continue; }
    $d = include $f;
    $missing = count(array_diff_key($en, is_array($d) ? $d : []));
    $pct = (int)round((count($en) - $missing) / max(1, count($en)) * 100);
    line($missing === 0 ? 'OK' : ($pct >= 55 ? 'OK' : 'WARN'), $lg,
         $pct . '% übersetzt' . ($missing ? " ({$missing} Schlüssel fallen auf EN zurück)" : ''));
}

// ----------------------------------------------------------------- sonuç
echo "\n" . str_repeat('=', 62) . "\n";
if ($fail === 0 && $warn === 0) {
    echo "ALLES OK — betriebsbereit.\n";
} elseif ($fail === 0) {
    echo "BEREIT mit {$warn} Hinweis(en).\n";
} else {
    echo "{$fail} FEHLER, {$warn} Hinweis(e) — bitte zuerst die FAIL-Zeilen beheben.\n";
}
exit($fail > 0 ? 1 : 0);
