<?php
/**
 * Gutschein-Ablauftest — CLI
 * ==========================
 *   php tools/test-voucher.php
 *
 * Läuft in einem temporären Datenverzeichnis und verschickt keine Post.
 * Die beiden wichtigsten Behauptungen, die hier geprüft werden:
 *   • ein Code wird erst beim ZAHLUNGSEINGANG gezählt und dann genau einmal,
 *   • der Nachlass übersteigt nie unsere Provision, sonst zahlt die Aktion
 *     aus dem Anteil des Verkäufers.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

putenv('VR_NO_MAIL=1');
$tmp = sys_get_temp_dir() . '/vr-vou-' . getmypid();
@mkdir($tmp, 0700, true);
putenv('VR_DATA_DIR=' . $tmp);
$_SERVER['REQUEST_METHOD'] = 'GET';

require __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/vouchers.php';

$n = 0; $bad = 0;
function ok(string $what, bool $cond, string $note = ''): void {
    global $n, $bad; $n++;
    if (!$cond) { $bad++; echo "  [FAIL] {$what} {$note}\n"; }
    else echo "  [ok  ] {$what}" . ($note ? "  {$note}" : '') . "\n";
}

echo "== anlegen ==\n";
[$c1] = vr_voucher_create(['code' => 'sommer25', 'type' => 'percent', 'value' => 1000]);
ok('Prozentcode angelegt', $c1 === true);
ok('Code normalisiert (Grossschreibung)', (vr_voucher_find('SoMmEr25')['code'] ?? '') === 'SOMMER25');
[$c2] = vr_voucher_create(['code' => 'SOMMER25', 'type' => 'percent', 'value' => 500]);
ok('kein doppelter Code', $c2 === false);
[$c3] = vr_voucher_create(['code' => 'ZEHN', 'type' => 'fixed', 'value' => 1000]);
ok('Festbetragscode angelegt', $c3 === true);
[$c4, $e4] = vr_voucher_create(['code' => 'X', 'type' => 'percent', 'value' => 500]);
ok('zu kurzer Code abgelehnt', $c4 === false, (string)$e4);
[$c5] = vr_voucher_create(['code' => 'NULLWERT', 'type' => 'percent', 'value' => 0]);
ok('Wert 0 abgelehnt', $c5 === false);

echo "\n== rechnen ==\n";
[$o1, $d1] = vr_voucher_check('SOMMER25', 20000);
ok('10 % auf 200,00 = 20,00', $o1 && $d1 === 2000, vr_money((int)$d1));
[$o2, $d2] = vr_voucher_check('ZEHN', 20000);
ok('Festbetrag 10,00', $o2 && $d2 === 1000, vr_money((int)$d2));
[$o3, $d3] = vr_voucher_check('ZEHN', 500);
ok('Nachlass nie groesser als der Warenwert', $o3 && $d3 === 500, vr_money((int)$d3));
[$o4, , $e5] = vr_voucher_check('GIBTESNICHT', 20000);
ok('unbekannter Code abgelehnt', $o4 === false && $e5 === 'vou_unknown');

echo "\n== deckel auf die provision ==\n";
// 10 % auf 200,00 = 20,00 Nachlass; Provision im Korb nur 12,00 → ablehnen.
[$o5, , $e6] = vr_voucher_check('SOMMER25', 20000, 1200);
ok('Nachlass ueber Provision wird abgelehnt', $o5 === false && $e6 === 'vou_not_applicable');
[$o6, $d6] = vr_voucher_check('SOMMER25', 20000, 2400);
ok('Nachlass unter Provision geht durch', $o6 === true && $d6 === 2000, vr_money((int)$d6));

echo "\n== bedingungen ==\n";
vr_voucher_create(['code' => 'AB100', 'type' => 'fixed', 'value' => 500, 'min_order_cents' => 10000]);
[$o7, , $e7] = vr_voucher_check('AB100', 9999);
ok('Mindestbestellwert greift', $o7 === false && $e7 === 'vou_min');
[$o8] = vr_voucher_check('AB100', 10000);
ok('genau am Mindestwert gueltig', $o8 === true);

vr_voucher_create(['code' => 'ABGELAUFEN', 'type' => 'fixed', 'value' => 500, 'valid_until' => time() - 60]);
[$o9, , $e9] = vr_voucher_check('ABGELAUFEN', 20000);
ok('abgelaufener Code abgelehnt', $o9 === false && $e9 === 'vou_expired');

vr_voucher_create(['code' => 'SPAETER', 'type' => 'fixed', 'value' => 500, 'valid_from' => time() + 3600]);
[$o10, , $e10] = vr_voucher_check('SPAETER', 20000);
ok('noch nicht gueltiger Code abgelehnt', $o10 === false && $e10 === 'vou_not_yet');

vr_voucher_create(['code' => 'NURICH', 'type' => 'fixed', 'value' => 500, 'email' => 'a@b.de']);
[$o11, , $e11] = vr_voucher_check('NURICH', 20000, PHP_INT_MAX, '');
ok('gebundener Code ohne Anmeldung abgelehnt', $o11 === false && $e11 === 'vou_login');
[$o12, , $e12] = vr_voucher_check('NURICH', 20000, PHP_INT_MAX, 'fremd@b.de');
ok('gebundener Code fuer fremde Adresse abgelehnt', $o12 === false && $e12 === 'vou_unknown');
[$o13] = vr_voucher_check('NURICH', 20000, PHP_INT_MAX, 'A@B.de');
ok('gebundener Code fuer eigene Adresse gueltig', $o13 === true);

echo "\n== erstbestellung ==\n";
vr_voucher_create(['code' => 'ERSTE', 'type' => 'percent', 'value' => 500,
                   'email' => 'neu@b.de', 'first_order_only' => true]);
[$o14] = vr_voucher_check('ERSTE', 20000, PHP_INT_MAX, 'neu@b.de');
ok('ohne bezahlte Bestellung gueltig', $o14 === true);
vr_store_append('retail-orders.json', [
    'id' => 'o-1', 'number' => 'MX-1', 'status' => 'paid', 'created_at' => time(),
    'total_cents' => 5000, 'lines' => [], 'customer' => ['email' => 'neu@b.de'],
]);
[$o15, , $e15] = vr_voucher_check('ERSTE', 20000, PHP_INT_MAX, 'neu@b.de');
ok('nach der ersten Zahlung abgelehnt', $o15 === false && $e15 === 'vou_first_only');

echo "\n== einloesen ==\n";
ok('Zaehler startet bei 0', (int)(vr_voucher_find('SOMMER25')['uses'] ?? -1) === 0);
vr_voucher_redeem('SOMMER25', 'o-100');
ok('eine Einloesung gezaehlt', (int)vr_voucher_find('SOMMER25')['uses'] === 1);
vr_voucher_redeem('SOMMER25', 'o-100');
ok('gleiche Bestellung zaehlt NICHT doppelt', (int)vr_voucher_find('SOMMER25')['uses'] === 1);
vr_voucher_redeem('SOMMER25', 'o-101');
ok('andere Bestellung zaehlt weiter', (int)vr_voucher_find('SOMMER25')['uses'] === 2);

vr_voucher_create(['code' => 'EINMAL', 'type' => 'fixed', 'value' => 500, 'max_uses' => 1]);
vr_voucher_redeem('EINMAL', 'o-200');
[$o16, , $e16] = vr_voucher_check('EINMAL', 20000);
ok('aufgebrauchter Code abgelehnt', $o16 === false && $e16 === 'vou_used_up');

echo "\n== abschalten ==\n";
vr_voucher_set_active('ZEHN', false);
[$o17] = vr_voucher_check('ZEHN', 20000);
ok('abgeschalteter Code abgelehnt', $o17 === false);
vr_voucher_set_active('ZEHN', true);
[$o18] = vr_voucher_check('ZEHN', 20000);
ok('wieder eingeschaltet gueltig', $o18 === true);

echo "\n== willkommensgutschein ==\n";
[$w1, $wc1] = vr_voucher_welcome('kunde@b.de');
ok('Willkommenscode erzeugt', $w1 === true && $wc1 !== '', $wc1);
$wv = vr_voucher_find($wc1);
ok('5 % laut Konfiguration', (int)$wv['value'] === (int)vr_config('welcome_discount_bps'));
ok('an die Adresse gebunden', ($wv['email'] ?? '') === 'kunde@b.de');
ok('nur Erstbestellung', !empty($wv['first_order_only']));
ok('einmal einloesbar', (int)$wv['max_uses'] === 1);
ok('befristet', (int)$wv['valid_until'] > time());
[$w2, $wc2] = vr_voucher_welcome('kunde@b.de');
ok('zweiter Aufruf gibt DENSELBEN Code (kein Sammeln)', $w2 === true && $wc2 === $wc1);
vr_voucher_redeem($wc1, 'o-300');
[$w3, $wc3] = vr_voucher_welcome('kunde@b.de');
ok('nach Einloesung neuer Code moeglich', $w3 === true && $wc3 !== $wc1);
[$w4] = vr_voucher_welcome('keine-adresse');
ok('ungueltige Adresse abgelehnt', $w4 === false);

echo "\n== codeform ==\n";
$gen = vr_voucher_generate();
ok('Praefix und Trenner', (bool)preg_match('/^MX-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $gen), $gen);
ok('keine verwechselbaren Zeichen (0 O 1 I)', !preg_match('/[01OI]/', substr($gen, 3)), $gen);

array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo "\n" . str_repeat('=', 62) . "\n";
printf("%d Tests, %d fehlgeschlagen\n", $n, $bad);
exit($bad ? 1 : 0);
