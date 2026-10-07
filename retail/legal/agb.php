<?php
/**
 * AGB — Allgemeine Geschäftsbedingungen (Kaufverträge)
 * ----------------------------------------------------
 * Pazaryeri modeli burada net kurulmalı: sözleşme kimin arasında kuruluyor,
 * platformun rolü ne, privat satıcıda hangi hükümler düşüyor. Vault (Premium
 * Outlet) kendine ait bir bölüm alıyor çünkü fiyat mekanizması standart bir
 * "sabit fiyatlı teklif" değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$brand = (string)vr_config('brand');
$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$wd    = (int)vr_config('withdrawal_days', 14);
$days  = (int)vr_config('return_days', 30);
$vat   = (int)vr_config('vat_rate_bps', 1900) / 100;
$steps = (int)vr_config('vault_steps', 6);
$hours = (int)vr_config('vault_step_hours', 24);

vr_doc_page('agb', 'legal_terms', '2026-08-01', compact('brand', 'c', 'co', 'email', 'wd', 'days', 'vat', 'steps', 'hours'));
