<?php
/**
 * VESTRA — seller prospecting (admin-only lead CRM + templated outreach).
 *
 * Leads are added by the admin, one at a time or via CSV import, from
 * research they've already done (trade shows, directories, referrals,
 * LinkedIn, the existing "Seller Scout" search links). Nothing here crawls
 * or scrapes the web automatically — VESTRA never harvests contact data on
 * its own. Every outreach email carries a required, working one-click
 * unsubscribe link and sender identification, and a lead that unsubscribes
 * can never be selected for another send.
 *
 * Assumes the caller has already required inc/products.php (for
 * vestra_read_json/vestra_write_json), matching inc/orders.php/inc/invoice.php.
 */

function vestra_leads(): array { return vestra_read_json('leads.json'); }

function vestra_save_leads(array $list): void { vestra_write_json('leads.json', array_values($list)); }

function vestra_lead_by_token(string $token): ?array {
    if ($token === '') return null;
    foreach (vestra_leads() as $l) {
        if (hash_equals($l['unsub_token'] ?? '', $token)) return $l;
    }
    return null;
}

function vestra_lead_sources(): array {
    return ['Trade show', 'LinkedIn', 'Industry directory', 'Referral', 'Existing contact', 'Other'];
}

const VESTRA_LEAD_STATUSES = ['new', 'contacted', 'replied', 'converted', 'declined'];

function vestra_lead_status_label(string $s): string {
    return match ($s) {
        'contacted'    => 'Contacted',
        'replied'      => 'Replied',
        'converted'    => 'Converted',
        'declined'     => 'Declined',
        'unsubscribed' => 'Unsubscribed',
        default        => 'New',
    };
}

function vestra_lead_default_template(): array {
    return [
        'subject' => 'Wholesale offer for {{company}} — verified designer brands',
        'body' =>
            "Hello {{contact_name}},\n\n".
            "We're reaching out to {{company}} with a wholesale offer from VESTRA — a KYC-verified B2B marketplace where retailers source authentic designer and streetwear brands directly from background-checked sellers, on clear invoice terms.\n\n".
            "Available now at wholesale — mixed-size cartons, low minimums:\n".
            "• Lacoste — polos & logo-trim tees\n".
            "• DSQUARED2 — Icon & graphic tees, sweatshirts\n".
            "• Ralph Lauren — Custom Fit tees\n".
            "• Dolce & Gabbana, Amiri and more\n\n".
            "Browse the full catalogue and live wholesale prices:\n".
            "https://vestrasales.com/shop\n\n".
            "Want a tailored quote for specific brands, sizes or volumes? Just reply and we'll put an offer together the same day.\n\n".
            "Best regards,\nThe VESTRA team",
    ];
}

function vestra_lead_template(): array {
    $t = vestra_read_json('lead_template.json');
    $d = vestra_lead_default_template();
    return ['subject' => $t['subject'] ?? $d['subject'], 'body' => $t['body'] ?? $d['body']];
}

function vestra_save_lead_template(array $t): void {
    vestra_write_json('lead_template.json', ['subject' => $t['subject'] ?? '', 'body' => $t['body'] ?? '']);
}

/** Render one lead's outreach email — substitutes placeholders and appends a
 *  mandatory sender-identity + unsubscribe footer (not admin-editable). */
function vestra_lead_render_email(array $lead, array $tpl): array {
    $map = [
        '{{company}}'      => $lead['company'] ?? '',
        '{{contact_name}}' => $lead['contact_name'] ?: 'there',
        '{{country}}'      => $lead['country'] ?? '',
    ];
    $subject = strtr($tpl['subject'] ?? '', $map);
    $body    = strtr($tpl['body'] ?? '', $map);
    $unsubUrl = 'https://vestrasales.com/lead-unsubscribe?token=' . urlencode($lead['unsub_token'] ?? '');
    $company  = $lead['company'] ?? 'your company';
    $body .= "\n\n—\nVESTRA is operated by acerasoft LLC. This is a one-time business message from our ".
             "verified B2B wholesale marketplace — you're receiving it because {$company} was identified as a ".
             "potential trade partner by our team.\nDon't want to hear from us again? Unsubscribe instantly: {$unsubUrl}";
    return [$subject, $body];
}

/** Render a wholesale product-offer ("quote") email to a customer/buyer. $lines is a
 *  list of ['title'=>, 'price'=>, 'moq'=>, 'url'=>] built from the selected listings.
 *  A sender-identity + opt-out footer is always appended — the saved prospect's
 *  one-click unsubscribe link when the offer targets one, else a plain reply-to-opt-out
 *  line — so proactive offers stay as compliant as the invite outreach. */
function vestra_quote_render_email(string $company, string $contact, array $lines, string $note, string $unsubUrl = ''): array {
    $who = $company !== '' ? $company : 'your business';
    $c   = $contact !== '' ? $contact : 'there';
    $subject = 'VESTRA — wholesale offer' . ($company !== '' ? ' for ' . $company : '');
    $b  = "Hello {$c},\n\n";
    $b .= "Here's a wholesale offer from VESTRA — a KYC-verified B2B fashion marketplace. ".
          "Every seller is background-checked, goods are authenticity-verified on delivery, and orders run on clear invoice terms.\n\n";
    $b .= "Selected for {$who}:\n\n";
    foreach ($lines as $ln) {
        $b .= '• ' . ($ln['title'] ?? '') . ' — ' . ($ln['price'] ?? '');
        if (!empty($ln['moq'])) $b .= ' · ' . $ln['moq'];
        $b .= "\n";
        if (!empty($ln['url'])) $b .= '  ' . $ln['url'] . "\n";
    }
    $b .= "\n";
    if ($note !== '') $b .= $note . "\n\n";
    $b .= "Browse the full range or request a tailored quote: https://vestrasales.com/shop\n\n";
    $b .= "Best regards,\nThe VESTRA team";
    $b .= "\n\n—\nVESTRA is operated by acerasoft LLC. You received this wholesale offer because {$who} was identified as a potential trade buyer.\n";
    $b .= $unsubUrl !== ''
        ? "Don't want these offers? Unsubscribe instantly: {$unsubUrl}"
        : "To opt out of future offers, just reply to this email.";
    return [$subject, $b];
}

/** Bulk-import leads from an uploaded CSV (header row required; company mandatory,
 *  everything else — email included — optional). A row without a valid email still
 *  imports so a web-research list (company + website, email to be enriched later)
 *  can be loaded and completed inside the CRM; such leads simply can't be selected
 *  for a send until an email is added. Dedupes by email when present, otherwise by
 *  company name, against existing leads and within the file. Returns [added, skipped].
 *  Caps at 500 rows so a mistaken huge upload can't silently create thousands. */
function vestra_lead_import_csv(string $tmpPath): array {
    $fh = @fopen($tmpPath, 'r');
    if (!$fh) return [0, 0];
    $header = fgetcsv($fh, null, ',', '"', '\\');
    if (!$header) { fclose($fh); return [0, 0]; }
    $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

    $leads = vestra_leads();
    $seenEmail = []; $seenCompany = [];
    foreach ($leads as $l) {
        $e = strtolower($l['email'] ?? ''); if ($e !== '') $seenEmail[$e] = true;
        $seenCompany[strtolower($l['company'] ?? '')] = true;
    }

    $added = 0; $skipped = 0; $max = 500;
    while (($added + $skipped) < $max && ($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
        $n = count($header);
        $r = array_combine($header, array_slice(array_pad($row, $n, ''), 0, $n));
        $email      = strtolower(trim($r['email'] ?? ''));
        $company    = trim($r['company'] ?? '');
        $emailValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        // Company is required; dedupe by email when the row has one, else by company.
        if ($company === '' ||
            ($emailValid && isset($seenEmail[$email])) ||
            (!$emailValid && isset($seenCompany[strtolower($company)]))) {
            $skipped++;
            continue;
        }
        if ($emailValid) $seenEmail[$email] = true;
        $seenCompany[strtolower($company)] = true;
        $leads[] = [
            'id'                => 'LD' . strtoupper(bin2hex(random_bytes(4))),
            'added_at'          => date('c'),
            'company'           => $company,
            'contact_name'      => trim($r['contact_name'] ?? $r['contact'] ?? ''),
            'email'             => $emailValid ? $email : '',
            'country'           => trim($r['country'] ?? ''),
            'website'           => trim($r['website'] ?? ''),
            'source'            => trim($r['source'] ?? '') ?: 'Other',
            'category'          => trim($r['category'] ?? ''),
            'notes'             => trim($r['notes'] ?? ''),
            'status'            => 'new',
            'last_contacted_at' => '',
            'unsub_token'       => bin2hex(random_bytes(16)),
        ];
        $added++;
    }
    fclose($fh);
    vestra_save_leads($leads);
    return [$added, $skipped];
}
