<?php
/**
 * Zahlungsarten
 * Hangi yöntemlerin gerçekten açık olduğu Stripe Dashboard'daki ayara bağlı;
 * bu sayfa yöntemleri ve akışı anlatıyor, hangi anahtar modunun etkin olduğunu
 * da (test/live) dürüstçe gösteriyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$mode = vr_stripe_settings()['mode'];

vr_doc_page('zahlung', 'legal_payment', '2026-08-01', compact('mode'));
