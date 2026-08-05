<?php
/**
 * Angebotsprüfung (moderasyon) — CLI
 * ==================================
 *   php tools/moderate.php list [review|approved|rejected|paused|all]
 *   php tools/moderate.php show <listing-id>
 *   php tools/moderate.php approve <listing-id>
 *   php tools/moderate.php reject  <listing-id> "Grund für den Verkäufer"
 *   php tools/moderate.php sellers
 *
 * Neden CLI: onay kararı bir insan kararıdır ve web'de tutulan bir yönetici
 * paneli, korunması gereken yeni bir saldırı yüzeyi demektir. SSH erişimi olan
 * kişi zaten sunucunun sahibi.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/listings.php';

$cmd = (string)($argv[1] ?? 'list');

switch ($cmd) {

    case 'list':
        $filter = strtolower((string)($argv[2] ?? 'review'));
        $rows   = vr_listings_all();
        $shown  = 0;

        printf("%-18s %-13s %-28s %-11s %-8s %s\n", 'ID', 'MARKE', 'ARTIKEL', 'PREIS', 'STATUS', 'VERKÄUFER');
        echo str_repeat('-', 108) . "\n";

        foreach ($rows as $r) {
            $status = (string)($r['status'] ?? 'review');
            if ($filter !== 'all' && $status !== $filter) continue;

            $s = vr_seller((string)($r['seller_uid'] ?? ''));
            printf("%-18s %-13s %-28s %-11s %-8s %s\n",
                substr((string)$r['id'], 0, 18),
                substr((string)$r['brand'], 0, 13),
                substr((string)$r['name'], 0, 28),
                vr_money((int)round((float)$r['retail_price'] * 100)),
                $status,
                vr_seller_display($s) . ' (' . ($s['seller_type'] ?? '?') . ')');
            $shown++;
        }
        echo "\n{$shown} Angebot(e) mit Status '{$filter}'.\n";
        if ($filter === 'review' && $shown > 0) {
            echo "Details: php tools/moderate.php show <id>\n";
        }
        break;

    case 'show':
        $id = (string)($argv[2] ?? '');
        $r  = $id !== '' ? vr_listing_get($id) : null;
        if ($r === null) { echo "Angebot nicht gefunden.\n"; exit(1); }

        $s = vr_seller((string)($r['seller_uid'] ?? ''));
        $sizes = (array)($r['size_stock'] ?? []);

        echo "ID          : {$r['id']}\n";
        echo "Status      : " . (string)($r['status'] ?? '') . "\n";
        echo "Marke       : {$r['brand']}\n";
        echo "Artikel     : {$r['name']}\n";
        echo "Kategorie   : {$r['cat']}\n";
        echo "Modellcode  : " . (string)($r['sku'] ?? '—') . "\n";
        echo "Preis       : " . vr_money((int)round((float)$r['retail_price'] * 100)) . "\n";
        echo "Größen      : " . implode(', ', array_map(fn($k, $v) => "{$k}×{$v}", array_keys($sizes), $sizes)) . "\n";
        echo "Zustand     : " . (string)($r['condition'] ?? 'new') . "\n";
        echo "Bilder      :\n";
        foreach ((array)($r['images'] ?? []) as $img) {
            $exists = vr_upload_exists((string)$img);
            echo "  " . ($exists ? '[ok]  ' : '[FEHLT]') . " {$img}\n";
        }
        echo "Beschreibung:\n  " . str_replace("\n", "\n  ", (string)($r['desc'] ?? '')) . "\n";
        echo "\nVerkäufer   : " . vr_seller_display($s) . "\n";
        echo "  Typ       : " . (string)($s['seller_type'] ?? '?') . "\n";
        echo "  E-Mail    : " . (string)($s['email'] ?? '?') . "\n";
        echo "  Land      : " . (string)($s['country'] ?? '?') . "\n";
        echo "  Stripe    : " . ((string)($s['stripe_account_id'] ?? '') !== ''
            ? (string)$s['stripe_account_id'] . (!empty($s['charges_enabled']) ? ' (bereit)' : ' (NICHT bereit)')
            : 'kein Konto') . "\n";
        // Aynı satıcının geçmişi kararı kolaylaştırır.
        $st = vr_seller_stats((string)($r['seller_uid'] ?? ''));
        echo "  Angebote  : {$st['total']} insgesamt, {$st['live']} live\n";
        echo "\nPrüfen: Bilder echt? Modellcode plausibel? Preis realistisch? Herkunft belegt?\n";
        echo "  php tools/moderate.php approve {$r['id']}\n";
        echo "  php tools/moderate.php reject  {$r['id']} \"Grund\"\n";
        break;

    case 'approve':
        $id = (string)($argv[2] ?? '');
        if ($id === '') { echo "Aufruf: approve <listing-id>\n"; exit(1); }

        $r = vr_listing_get($id);
        if ($r === null) { echo "Angebot nicht gefunden.\n"; exit(1); }

        // Satıcı Stripe'ta hazır değilse onay verilse bile satış yapılamaz —
        // sessizce onaylamak yerine bunu açıkça söylüyoruz.
        $s = vr_seller((string)($r['seller_uid'] ?? ''));
        if ($s !== null && empty($s['charges_enabled'])) {
            echo "HINWEIS: Verkäufer ist bei Stripe noch nicht verifiziert.\n";
            echo "Das Angebot wird sichtbar, aber Auszahlungen bleiben bis zur Verifizierung auf dem Plattformkonto.\n";
        }
        foreach ((array)($r['images'] ?? []) as $img) {
            if (!vr_upload_exists((string)$img)) echo "WARNUNG: Bilddatei fehlt auf dem Server: {$img}\n";
        }

        echo vr_listing_moderate($id, true) ? "Freigegeben: {$id}\n" : "Fehler beim Speichern.\n";
        break;

    case 'reject':
        $id   = (string)($argv[2] ?? '');
        $note = (string)($argv[3] ?? '');
        if ($id === '' || $note === '') {
            echo "Aufruf: reject <listing-id> \"Grund für den Verkäufer\"\n";
            exit(1);
        }
        echo vr_listing_moderate($id, false, $note) ? "Abgelehnt: {$id}\n" : "Angebot nicht gefunden.\n";
        break;

    case 'sellers':
        $rows = vr_sellers();
        printf("%-18s %-26s %-9s %-4s %-9s %s\n", 'ID', 'NAME', 'TYP', 'LAND', 'STRIPE', 'E-MAIL');
        echo str_repeat('-', 104) . "\n";
        foreach ($rows as $s) {
            printf("%-18s %-26s %-9s %-4s %-9s %s\n",
                substr((string)$s['id'], 0, 18),
                substr(vr_seller_display($s), 0, 26),
                (string)($s['seller_type'] ?? '?'),
                (string)($s['country'] ?? '?'),
                !empty($s['charges_enabled']) ? 'bereit' : ((string)($s['stripe_account_id'] ?? '') !== '' ? 'offen' : '—'),
                (string)($s['email'] ?? ''));
        }
        echo "\n" . count($rows) . " Verkäuferkonto/-konten.\n";
        break;

    default:
        echo "Befehle: list | show | approve | reject | sellers\n";
        exit(1);
}
