<?php
/**
 * Gutschein-Codes
 * ===============
 * Ein Code zieht einen Betrag vom Warenkorb ab. Zwei Formen: Prozent oder
 * fester Betrag. Codes liegen in data/retail-vouchers.json.
 *
 * WER DEN RABATT TRÄGT
 * --------------------
 * Der Rabatt ist unsere Werbemaßnahme, nicht die des Verkäufers. Deshalb
 * bleibt die Auszahlung an den Verkäufer unverändert und der Nachlass geht
 * vollständig von unserer Provision ab (siehe inc/orders.php). Wäre es anders,
 * würde jede Rabattaktion fremdes Geld ausgeben.
 *
 * Damit die Provision dabei nie negativ wird, ist der Nachlass beim Prüfen auf
 * die Provision gedeckelt. Ein 5-%-Gutschein gegen 12 % Provision passt
 * bequem; ein 50-%-Code auf einen reinen Fremdverkauf würde gekappt und der
 * Kunde bekäme weniger Nachlass als erwartet — deshalb meldet
 * vr_voucher_check() das als Fehler statt still zu kürzen.
 *
 * WAS EIN CODE KANN
 *   type            'percent' (bps) oder 'fixed' (cents)
 *   min_order_cents Mindestbestellwert (Warenwert ohne Versand)
 *   valid_from      Startzeit; 0 = sofort
 *   valid_until     Ablauf; 0 = unbefristet
 *   max_uses        Gesamtzahl Einlösungen; 0 = unbegrenzt
 *   email           an eine Adresse gebunden; leer = für alle
 *   first_order_only nur, wenn diese Adresse noch nie bezahlt hat
 *   active          abschaltbar, ohne die Historie zu verlieren
 *
 * Einlösungen werden beim Zahlungseingang gezählt, nicht beim Eintippen —
 * ein abgebrochener Checkout verbraucht keinen Code.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/orders.php';

const VR_VOUCHER_FILE = 'retail-vouchers.json';

// ------------------------------------------------------------------ Lesen
function vr_vouchers(): array
{
    $d = vr_store_read(VR_VOUCHER_FILE, []);
    return is_array($d) ? $d : [];
}

/** Codes sind unabhängig von Groß-/Kleinschreibung und Leerzeichen. */
function vr_voucher_norm(string $code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($code)) ?? '');
}

function vr_voucher_find(string $code): ?array
{
    $code = vr_voucher_norm($code);
    if ($code === '') return null;
    foreach (vr_vouchers() as $v) {
        if (vr_voucher_norm((string)($v['code'] ?? '')) === $code) return $v;
    }
    return null;
}

/** Hat diese Adresse schon einmal bezahlt? Für first_order_only. */
function vr_has_paid_order(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') return false;
    foreach (vr_orders() as $o) {
        if ((string)($o['status'] ?? '') !== 'paid') continue;
        if (strtolower((string)($o['customer']['email'] ?? '')) === $email) return true;
    }
    return false;
}

// ------------------------------------------------------------------ Prüfen
/**
 * Code gegen einen Warenkorb prüfen.
 *
 * $goodsCents  Warenwert ohne Versand — der Rabatt gilt nie auf den Versand.
 * $feeCents    unsere Provision in diesem Warenkorb; deckelt den Nachlass.
 * $email       bekannte Adresse (angemeldet oder eingetippt), sonst ''.
 *
 * Rückgabe: [ok, rabatt_cents, meldungs_schlüssel]
 */
function vr_voucher_check(string $code, int $goodsCents, int $feeCents = PHP_INT_MAX, string $email = ''): array
{
    $v = vr_voucher_find($code);
    if ($v === null)                      return [false, 0, 'vou_unknown'];
    if (empty($v['active']))              return [false, 0, 'vou_unknown'];

    $now = time();
    if ((int)($v['valid_from'] ?? 0) > $now)                          return [false, 0, 'vou_not_yet'];
    if ((int)($v['valid_until'] ?? 0) > 0 && (int)$v['valid_until'] < $now) return [false, 0, 'vou_expired'];

    $max = (int)($v['max_uses'] ?? 0);
    if ($max > 0 && (int)($v['uses'] ?? 0) >= $max)                   return [false, 0, 'vou_used_up'];

    $min = (int)($v['min_order_cents'] ?? 0);
    if ($min > 0 && $goodsCents < $min)   return [false, 0, 'vou_min'];

    // Persönlicher Code: nur für die hinterlegte Adresse.
    $bound = strtolower(trim((string)($v['email'] ?? '')));
    if ($bound !== '') {
        if ($email === '')                              return [false, 0, 'vou_login'];
        if (strtolower(trim($email)) !== $bound)        return [false, 0, 'vou_unknown'];
    }

    if (!empty($v['first_order_only'])) {
        if ($email === '')                 return [false, 0, 'vou_login'];
        if (vr_has_paid_order($email))     return [false, 0, 'vou_first_only'];
    }

    $discount = (string)($v['type'] ?? 'percent') === 'fixed'
        ? (int)($v['value'] ?? 0)
        : (int)floor($goodsCents * (int)($v['value'] ?? 0) / 10000);

    $discount = max(0, min($discount, $goodsCents));
    if ($discount <= 0) return [false, 0, 'vou_unknown'];

    // Der Nachlass geht von unserer Provision ab; mehr können wir nicht geben,
    // ohne fremdes Geld auszugeben. Lieber ablehnen als still kürzen.
    if ($feeCents !== PHP_INT_MAX && $discount > $feeCents) {
        return [false, 0, 'vou_not_applicable'];
    }

    return [true, $discount, ''];
}

// ------------------------------------------------------------- Warenkorb
/** Code in der Sitzung merken. */
function vr_voucher_set(string $code): void
{
    vr_session_start();
    $code = vr_voucher_norm($code);
    if ($code === '') unset($_SESSION['vr_voucher']);
    else $_SESSION['vr_voucher'] = $code;
}

function vr_voucher_current(): string
{
    vr_session_start();
    return (string)($_SESSION['vr_voucher'] ?? '');
}

function vr_voucher_clear(): void
{
    vr_session_start();
    unset($_SESSION['vr_voucher']);
}

// ------------------------------------------------------------- Einlösen
/**
 * Einlösung zählen. Wird beim Zahlungseingang gerufen und ist idempotent:
 * dieselbe Bestellnummer erhöht den Zähler genau einmal, auch wenn Stripe
 * denselben Webhook mehrfach schickt.
 */
function vr_voucher_redeem(string $code, string $orderId, string $email = ''): bool
{
    $code = vr_voucher_norm($code);
    if ($code === '' || $orderId === '') return false;

    $done = false;
    vr_store_mutate(VR_VOUCHER_FILE, static function ($rows) use ($code, $orderId, $email, &$done) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $v) {
            if (vr_voucher_norm((string)($v['code'] ?? '')) !== $code) continue;

            $seen = (array)($v['orders'] ?? []);
            if (in_array($orderId, $seen, true)) { $done = true; return null; }  // schon gezählt

            $seen[] = $orderId;
            $rows[$i]['orders']     = array_slice($seen, -500);
            $rows[$i]['uses']       = (int)($v['uses'] ?? 0) + 1;
            $rows[$i]['last_used']  = time();
            if ($email !== '') $rows[$i]['last_email'] = strtolower(trim($email));
            $done = true;
            return $rows;
        }
        return null;
    });
    return $done;
}

// ------------------------------------------------------------- Anlegen
/**
 * Code anlegen. $in: code, type, value, min_order_cents, valid_until,
 * max_uses, email, first_order_only, note.
 * Rückgabe: [ok, code|fehlertext]
 */
function vr_voucher_create(array $in): array
{
    $code = vr_voucher_norm((string)($in['code'] ?? ''));
    if ($code === '') $code = vr_voucher_generate();
    if (strlen($code) < 4)              return [false, 'Code zu kurz'];
    if (vr_voucher_find($code) !== null) return [false, 'Code existiert bereits'];

    $type  = (string)($in['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $value = (int)($in['value'] ?? 0);
    if ($value <= 0)                                    return [false, 'Wert fehlt'];
    if ($type === 'percent' && $value > 10000)          return [false, 'Prozentwert zu hoch'];

    $v = [
        'code'             => $code,
        'type'             => $type,
        'value'            => $value,                       // bps bzw. cents
        'min_order_cents'  => max(0, (int)($in['min_order_cents'] ?? 0)),
        'valid_from'       => (int)($in['valid_from'] ?? 0),
        'valid_until'      => (int)($in['valid_until'] ?? 0),
        'max_uses'         => max(0, (int)($in['max_uses'] ?? 0)),
        'uses'             => 0,
        'orders'           => [],
        'email'            => strtolower(trim((string)($in['email'] ?? ''))),
        'first_order_only' => !empty($in['first_order_only']),
        'active'           => true,
        'note'             => mb_substr(trim((string)($in['note'] ?? '')), 0, 200),
        'created_at'       => time(),
    ];

    $ok = false;
    vr_store_mutate(VR_VOUCHER_FILE, static function ($rows) use ($v, &$ok) {
        if (!is_array($rows)) $rows = [];
        foreach ($rows as $r) {
            if (vr_voucher_norm((string)($r['code'] ?? '')) === $v['code']) return null;
        }
        $rows[] = $v;
        $ok = true;
        return $rows;
    });

    return $ok ? [true, $code] : [false, 'Code existiert bereits'];
}

/** An/aus schalten, ohne die Historie zu verlieren. */
function vr_voucher_set_active(string $code, bool $active): bool
{
    $code = vr_voucher_norm($code);
    $ok = false;
    vr_store_mutate(VR_VOUCHER_FILE, static function ($rows) use ($code, $active, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $v) {
            if (vr_voucher_norm((string)($v['code'] ?? '')) !== $code) continue;
            $rows[$i]['active'] = $active;
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok;
}

/**
 * Lesbarer Zufallscode. Ohne 0/O und 1/I — die werden beim Abtippen aus
 * einer Mail regelmäßig verwechselt.
 */
function vr_voucher_generate(string $prefix = 'MX'): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 8; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $prefix . '-' . substr($out, 0, 4) . '-' . substr($out, 4, 4);
}

/**
 * Persönlicher Willkommensgutschein: Prozent, an die Adresse gebunden, nur
 * für die erste bezahlte Bestellung, einmal einlösbar.
 *
 * Gibt es für die Adresse schon einen unbenutzten Willkommenscode, wird
 * DIESER zurückgegeben statt eines zweiten — sonst sammelt jemand durch
 * mehrfaches Bestätigen beliebig viele Codes an.
 *
 * Rückgabe: [ok, code]
 */
function vr_voucher_welcome(string $email, ?int $bps = null): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, ''];

    foreach (vr_vouchers() as $v) {
        if (strtolower((string)($v['email'] ?? '')) !== $email) continue;
        if (($v['kind'] ?? '') !== 'welcome') continue;
        if ((int)($v['uses'] ?? 0) > 0) continue;
        if (empty($v['active'])) continue;
        return [true, (string)$v['code']];              // schon vorhanden
    }

    $bps = $bps ?? (int)vr_config('welcome_discount_bps', 500);
    $days = (int)vr_config('welcome_valid_days', 90);

    [$ok, $res] = vr_voucher_create([
        'code'             => vr_voucher_generate('WELCOME'),
        'type'             => 'percent',
        'value'            => $bps,
        'valid_until'      => time() + $days * 86400,
        'max_uses'         => 1,
        'email'            => $email,
        'first_order_only' => true,
        'note'             => 'Willkommensgutschein',
    ]);
    if (!$ok) return [false, ''];

    // kind nachtragen, damit der Code oben wiedererkannt wird.
    vr_store_mutate(VR_VOUCHER_FILE, static function ($rows) use ($res) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $v) {
            if ((string)($v['code'] ?? '') !== $res) continue;
            $rows[$i]['kind'] = 'welcome';
            return $rows;
        }
        return null;
    });

    return [true, $res];
}

/** Anzeigetext eines Codes: "5 %" bzw. "10,00 €". */
function vr_voucher_label(array $v): string
{
    return (string)($v['type'] ?? 'percent') === 'fixed'
        ? vr_money((int)($v['value'] ?? 0))
        : rtrim(rtrim(number_format((int)($v['value'] ?? 0) / 100, 2, ',', '.'), '0'), ',') . "\u{202F}%";
}
