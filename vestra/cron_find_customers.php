<?php
/**
 * VESTRA — daily customer-finding cron job (CLI only, never web-accessible).
 *
 * Finds real small/medium retailers across one whole country per day
 * (OpenStreetMap) and resolves a real email for each new one (company's own
 * site, or your finder key if you've set one in Admin -> Customers). It does
 * NOT send anything — new customers just land in the list with a real
 * email, ready for a human to review and send from Admin -> Customers.
 * Sending stays a deliberate, reviewed action: a bad day here (a network
 * hiccup, a bad batch) should never turn into silent mass-email to real
 * businesses.
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

/* One country per day, rotating through a list so the same one isn't hit
 * every time. Edit data/cron_countries.json to use your own list — a JSON
 * array of country name strings, e.g. ["Germany","France"] — or leave it
 * unset to use the built-in default covering your target regions. */
function vestra_cron_countries(): array {
  $custom = vestra_read_json('cron_countries.json');
  if (is_array($custom) && $custom) return $custom;
  return ['Germany','Netherlands','France','Italy','Spain','United Kingdom','United States','Australia'];
}

$countries = vestra_cron_countries();
$dayIndex = (int)date('z') % max(1, count($countries));
$country = trim((string)($countries[$dayIndex] ?? ''));

$stamp = date('Y-m-d H:i:s');
echo "[$stamp] VESTRA cron: discovering \"$country\" (whole country)...\n";
if ($country === '') { echo "  no country configured — skipping.\n"; exit(0); }

$rows = vestra_discover_osm($country, '', 80);
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
