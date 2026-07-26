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
 * The country rotation and the run's result are shared with the admin panel
 * (inc/leads.php: vestra_cron_today_country / vestra_cron_write_status) —
 * the "Automation" card's "Run now" button does exactly the same thing this
 * script does, so a manual click and tonight's 9am run behave identically.
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

$country = vestra_cron_today_country();
$stamp = date('Y-m-d H:i:s');
echo "[$stamp] VESTRA cron: discovering \"$country\" (whole country)...\n";
if ($country === '') {
  echo "  no country configured — skipping.\n";
  vestra_cron_write_status('', 0, 0, 0, 0, 'cron', 'No country configured (data/cron_countries.json empty and no default).');
  exit(0);
}

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
vestra_cron_write_status($country, count($rows), count($addedRows), $found, count($toCheckIds), 'cron');
