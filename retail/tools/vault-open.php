<?php
/**
 * Vault losu aç / kapat / listele — CLI
 * ====================================
 *   php tools/vault-open.php list
 *   php tools/vault-open.php open <urun-id> <acilis-EUR> <taban-EUR> [kademe] [saat] [UVP-EUR]
 *   php tools/vault-open.php close <lot-id>
 *
 * Plan bir kez yazılır ve DEĞİŞTİRİLEMEZ (bkz. inc/vault.php). Sonradan tabanı
 * yükseltebilen bir arayüz kasten yapılmadı: sahte indirim tam olarak böyle
 * doğar. Yanlış açılan bir los kapatılır, yenisi açılır — geçmiş kayıtta kalır.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';

$cmd = (string)($argv[1] ?? 'list');

/** EUR metnini kuruşa çevir ("249", "249,90", "249.90"). */
function eur_to_cents(string $s): int
{
    $s = str_replace([' ', '€'], '', $s);
    $s = str_replace(',', '.', $s);
    return (int)round(((float)$s) * 100);
}

switch ($cmd) {

    // ------------------------------------------------------------------ list
    case 'list':
        $lots = vr_vault_lots(['include_sold' => true, 'include_scheduled' => true]);
        if (!$lots) { echo "Keine Lose vorhanden.\n"; exit(0); }

        printf("%-14s %-13s %-26s %-11s %-11s %-9s %s\n",
               'LOT', 'MARKE', 'ARTIKEL', 'JETZT', 'BODEN', 'STUFE', 'STATUS');
        echo str_repeat('-', 108) . "\n";

        foreach ($lots as $v) {
            $st = $v['state'];
            printf("%-14s %-13s %-26s %-11s %-11s %-9s %s\n",
                substr($v['lot_id'], 0, 14),
                substr($v['product']['brand'], 0, 13),
                substr(vr_card_name($v['product']), 0, 26),
                vr_money((int)$st['cents']),
                vr_money((int)$st['floor_cents']),
                ($st['step'] + 1) . '/' . ($st['steps'] + 1),
                $v['sold'] ? 'VERKAUFT'
                    : ($st['phase'] === 'scheduled' ? 'geplant (' . vr_until((int)$st['next_at']) . ')'
                    : ($v['reserved'] ? 'im Warenkorb' : 'live')));
        }
        echo "\nNächste Senkung: ";
        $n = vr_vault_next_drop();
        echo $n ? vr_date($n, true) . ' (' . vr_until($n) . ")\n" : "keine\n";
        break;

    // ------------------------------------------------------------------ open
    case 'open':
        $pid   = (string)($argv[2] ?? '');
        $open  = eur_to_cents((string)($argv[3] ?? '0'));
        $floor = eur_to_cents((string)($argv[4] ?? '0'));
        $steps = (int)($argv[5] ?? vr_config('vault_steps', 6));
        $hours = (float)($argv[6] ?? vr_config('vault_step_hours', 24));
        $rrp   = isset($argv[7]) ? eur_to_cents((string)$argv[7]) : 0;

        if ($pid === '' || $open <= 0 || $floor <= 0) {
            echo "Aufruf: php tools/vault-open.php open <produkt-id> <eröffnung-EUR> <boden-EUR> [stufen] [stunden] [UVP-EUR]\n";
            exit(1);
        }

        $p = vr_product($pid);
        if ($p === null) {
            echo "FEHLER: Artikel '{$pid}' nicht im Katalog.\n";
            echo "Tipp: php tools/vault-open.php find <suchwort>\n";
            exit(1);
        }
        if ((int)$p['stock'] < 1) {
            echo "FEHLER: '{$pid}' hat keinen Bestand.\n";
            exit(1);
        }

        [$ok, $res] = vr_vault_open($pid, $open, $floor, [
            'steps' => $steps, 'step_hours' => $hours, 'rrp_cents' => $rrp ?: null,
        ]);

        if (!$ok) { echo "FEHLER: {$res}\n"; exit(1); }

        echo "Los geöffnet: {$res}\n";
        echo "  Artikel   : {$p['brand']} " . vr_card_name($p) . "\n";
        echo "  Eröffnung : " . vr_money($open) . "\n";
        echo "  Boden     : " . vr_money($floor) . "\n";
        echo "  Plan      : {$steps} Stufen à {$hours} h\n";

        // Planın tamamını bas: sonradan "hangi fiyat ne zaman" sorusu kalmasın.
        echo "\n  Stufe  Preis        ab\n";
        for ($i = 0; $i <= $steps; $i++) {
            $price = $i === 0 ? $open : ($i >= $steps ? $floor : (int)round($open - ($open - $floor) * ($i / $steps)));
            printf("  %-6s %-12s %s\n", $i + 1, vr_money($price), vr_date(time() + (int)($i * $hours * 3600), true));
        }
        echo "\n  URL: " . vr_abs('outlet.php', ['lot' => $res]) . "\n";
        break;

    // ----------------------------------------------------------------- close
    case 'close':
        $lotId = (string)($argv[2] ?? '');
        if ($lotId === '') { echo "Aufruf: php tools/vault-open.php close <lot-id>\n"; exit(1); }

        $done = false;
        vr_store_mutate(VR_VAULT_FILE, static function ($d) use ($lotId, &$done) {
            if (!is_array($d['lots'] ?? null) || !$d['lots']) $d['lots'] = vr_vault_all();
            foreach ($d['lots'] as $i => $lot) {
                if ((string)($lot['lot_id'] ?? '') !== $lotId) continue;
                $d['lots'][$i]['status']    = 'closed';
                $d['lots'][$i]['closed_at'] = time();
                $done = true;
                return $d;
            }
            return null;
        }, ['lots' => []]);

        echo $done ? "Los {$lotId} geschlossen.\n" : "FEHLER: Los nicht gefunden.\n";
        exit($done ? 0 : 1);

    // ------------------------------------------------------------------ find
    case 'find':
        $q = strtolower((string)($argv[2] ?? ''));
        if ($q === '') { echo "Aufruf: php tools/vault-open.php find <suchwort>\n"; exit(1); }

        $hits = vr_query(['q' => $q, 'per_page' => 40])['rows'];
        if (!$hits) { echo "Nichts gefunden.\n"; exit(0); }

        printf("%-26s %-14s %-30s %-11s %s\n", 'ID', 'MARKE', 'ARTIKEL', 'PREIS', 'BESTAND');
        echo str_repeat('-', 96) . "\n";
        foreach ($hits as $p) {
            printf("%-26s %-14s %-30s %-11s %d\n",
                substr($p['id'], 0, 26), substr($p['brand'], 0, 14),
                substr(vr_card_name($p), 0, 30), vr_money((int)$p['price_cents']), (int)$p['stock']);
        }
        break;

    default:
        echo "Befehle: list | open | close | find\n";
        exit(1);
}
