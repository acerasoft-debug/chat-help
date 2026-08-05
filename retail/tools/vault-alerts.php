<?php
/**
 * Vault fiyat alarmlarını gönder — CLI (cron / GitHub Actions)
 * ============================================================
 *   php tools/vault-alerts.php --dry     # kimseye yazmadan ne olacağını göster
 *   php tools/vault-alerts.php           # gerçekten gönder
 *   php tools/vault-alerts.php list      # kurulu alarmlar
 *
 * Saatte bir çalıştırmak yeterli: kademeler saatlerce sürüyor, dakikalık
 * hassasiyet gerekmiyor. Her alarm en fazla BİR posta üretir ve sonra silinir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/alerts.php';

$cmd = (string)($argv[1] ?? '');
$dry = in_array('--dry', $argv, true) || $cmd === '--dry';

if ($cmd === 'list') {
    $rows = vr_alerts_all();
    if (!$rows) { echo "Keine Alarme.\n"; exit(0); }

    printf("%-14s %-11s %-11s %-24s %s\n", 'LOS', 'WUNSCH', 'JETZT', 'ARTIKEL', 'E-MAIL');
    echo str_repeat('-', 92) . "\n";

    foreach ($rows as $r) {
        $lot  = vr_vault_find((string)($r['lot_id'] ?? ''));
        $now  = $lot ? vr_money((int)$lot['state']['cents']) : '—';
        $name = $lot ? substr($lot['product']['brand'] . ' ' . vr_card_name($lot['product']), 0, 24) : '(los weg)';
        printf("%-14s %-11s %-11s %-24s %s\n",
            substr((string)$r['lot_id'], 0, 14),
            vr_money((int)$r['threshold_cents']),
            $now, $name,
            // E-postayı tam basmıyoruz: bu çıktı Actions kayıtlarına düşüyor.
            substr((string)$r['email'], 0, 3) . '***@' . substr(strrchr((string)$r['email'], '@') ?: '@?', 1));
    }
    echo "\n" . count($rows) . " Alarm(e).\n";
    exit(0);
}

echo "MAXSALES — Vault-Preisalarme" . ($dry ? " (Probelauf)" : "") . "\n";
echo str_repeat('=', 62) . "\n";

$res = vr_alerts_run($dry);

foreach ($res['lines'] as $line) echo '  ' . $line . "\n";
if (!$res['lines']) echo "  (nichts zu tun)\n";

echo str_repeat('-', 62) . "\n";
printf("geprüft: %d · gesendet: %d · entfernt: %d%s\n",
    $res['checked'], $res['sent'], $res['dropped'], $dry ? '  [Probelauf: nichts gesendet]' : '');
