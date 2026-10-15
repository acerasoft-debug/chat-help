<?php
/* STRIPE ANA SALTERI (15 Eki 2026, operator: "vestrasales.com dan tum Stripe API
   baglantilarini inaktif yap").
   data/stripe_switch.json {"off":true} iken site Stripe'a HICBIR cagri yapmaz:
   stripe_api() calismadan reddeder, stripe_available()/stripe_configured() false,
   Connect/escrow kapali (siparisler havale/fatura yoluna duser), dropship kart
   odemesi, grup kaporasi ve numune odemesi kapali, webhook olaylari islenmeden
   data/stripe_held_events.jsonl'e saklanir (kayip yok, acilinca elle islenir).
   Anahtarlar (.env) SILINMEZ: salter geri alinabilir bir karar, anahtar silmek degil.
   Bagimliligi yok: Stripe'a hic dokunmayan sayfalar da ucuzca sorabilsin. */
function vestra_stripe_switch_file(): string { return dirname(__DIR__).'/data/stripe_switch.json'; }

function vestra_stripe_switch(bool $reload = false): array {
    static $s = null;
    if ($s !== null && !$reload) return $s;
    $f = vestra_stripe_switch_file();
    $j = is_readable($f) ? json_decode((string)@file_get_contents($f), true) : null;
    return $s = is_array($j) ? $j : [];
}

function vestra_stripe_off(): bool {
    if ((getenv('STRIPE_DISABLED') ?: '') === '1') return true;
    return !empty(vestra_stripe_switch()['off']);
}

/** Salteri yazar ve geri okur. Donen: yeni durum (true = kapali). */
function vestra_stripe_set_off(bool $off, string $by = ''): bool {
    $f = vestra_stripe_switch_file();
    $j = vestra_stripe_switch(true);
    $j['off'] = $off;
    $j[$off ? 'off_at' : 'on_at'] = date('c');
    if ($by !== '') $j['by'] = $by;
    @file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return !empty(vestra_stripe_switch(true)['off']);
}

/** Webhook olayini islemeden saklar (salter kapaliyken). */
function vestra_stripe_hold_event(string $payload, string $type): void {
    $f = dirname(__DIR__).'/data/stripe_held_events.jsonl';
    @file_put_contents($f, json_encode(['at' => date('c'), 'type' => $type, 'payload' => $payload], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND | LOCK_EX);
}
