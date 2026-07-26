<?php
/**
 * VESTRA — daily customer-finding cron job (CLI only, never web-accessible).
 *
 * Finds real small/medium retailers in one city per day (OpenStreetMap) and
 * resolves a real email for each new one (company's own site, or your finder
 * key if you've set one in Admin -> Customers). It does NOT send anything —
 * new customers just land in the list with a real email, ready for a human
 * to review and send from Admin -> Customers. Sending stays a deliberate,
 * reviewed action: a bad day here (wrong city, a network hiccup) should
 * never turn into silent mass-email to real businesses.
 *
 * cPanel setup: Home -> Cron Jobs -> Add New Cron Job
 *   Minute 0, Hour 9, every day
 *   Command: php /home/USER/public_html/cron_find_customers.php >> /home/USER/cron_find_customers.log 2>&1
 * (replace USER with your cPanel username; the log file lets you check each
 * morning's run without logging into the admin panel.)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/leads.php';
require_once __DIR__.'/inc/notify.php';
@set_time_limit(0);

/* One city per day, rotating through a list so the same city isn't hit every
 * time. Edit data/cron_cities.json to use your own list — a JSON array of
 * {"city":"Milano","country":"Italy"} objects — or leave it unset to use the
 * built-in default covering your target regions (EU / UK / US / AU). */
function vestra_cron_cities(): array {
  $custom = vestra_read_json('cron_cities.json');
  if (is_array($custom) && $custom) return $custom;
  return [
    ['city'=>'Berlin','country'=>'Germany'], ['city'=>'München','country'=>'Germany'],
    ['city'=>'Paris','country'=>'France'], ['city'=>'Lyon','country'=>'France'],
    ['city'=>'Milano','country'=>'Italy'], ['city'=>'Roma','country'=>'Italy'],
    ['city'=>'Amsterdam','country'=>'Netherlands'], ['city'=>'Barcelona','country'=>'Spain'],
    ['city'=>'Madrid','country'=>'Spain'], ['city'=>'London','country'=>'United Kingdom'],
    ['city'=>'Manchester','country'=>'United Kingdom'], ['city'=>'New York','country'=>'United States'],
    ['city'=>'Los Angeles','country'=>'United States'], ['city'=>'Sydney','country'=>'Australia'],
    ['city'=>'Melbourne','country'=>'Australia'],
  ];
}

$cities = vestra_cron_cities();
$dayIndex = (int)date('z') % max(1, count($cities));
$pick = $cities[$dayIndex];
$city = trim((string)($pick['city'] ?? '')); $country = trim((string)($pick['country'] ?? ''));

$stamp = date('Y-m-d H:i:s');
echo "[$stamp] VESTRA cron: discovering \"$city\"".($country ? ", $country" : '')."...\n";
if ($city === '') { echo "  no city configured — skipping.\n"; exit(0); }

$rows = vestra_discover_osm($city, $country, 80);
[$addedRows, ] = $rows ? vestra_leads_add($rows) : [[], 0];
echo "  found ".count($rows)." shop(s), added ".count($addedRows)." new.\n";

$toCheckIds = array_column(array_filter($addedRows, fn($r) => $r['email'] === '' && $r['website'] !== ''), 'id');
$found = 0;
if ($toCheckIds) {
  $all = vestra_leads();
  foreach ($all as &$l) {
    if (!in_array($l['id'] ?? '', $toCheckIds, true)) continue;
    $email = vestra_find_email((string)$l['website']);
    if ($email !== '') { $l['email'] = $email; $found++; echo "  \xE2\x9C\x93 {$l['company']} — $email\n"; }
    else { echo "  \xE2\x9C\x97 {$l['company']} — no email found\n"; }
  }
  unset($l);
  vestra_save_leads($all);
}
echo "  emails: $found found / ".count($toCheckIds)." checked.\n";
echo "[$stamp] done — review & send from Admin -> Customers.\n";
