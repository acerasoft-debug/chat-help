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

/* Son okunan leads.json'un kopyası ve dosya kimliği (inode|mtime|boyut). vestra_save_leads() dosya bu okumadan beri
 * değiştiyse (başka bir iş yazdıysa) üzerine YAZMAZ, bu işin değişikliklerini taze listeye birleştirir. */
function vestra_leads_snapshot(?array $set = null, bool $clear = false): ?array {
  static $snap = null;
  if ($clear) { $snap = null; return null; }
  if ($set !== null) $snap = $set;
  return $snap;
}
function vestra_leads_file_key(): string {
  $f = vestra_data_dir().'/leads.json'; clearstatcache(true, $f);
  return is_file($f) ? ((int)@fileinode($f)).'|'.((int)@filemtime($f)).'|'.((int)@filesize($f)) : '-';
}
function vestra_leads(): array {
  $key = vestra_leads_file_key();
  $list = vestra_read_json('leads.json');
  vestra_leads_snapshot(['key' => $key, 'list' => $list]);
  return $list;
}

/* leads.json'a AYNI ANDA yazan işler (17 Eki 2026 incelemesi): sunucunun kendi gönderimi (mektup başına damga, 8–11 sn),
 * Brevo partisi (send-outreach, mektup başına damga), saatlik geri dönme taraması, Brevo geri dönüş eşitlemesi, bulucu,
 * yönetim / satıcı paneli. Hepsi "oku → değiştir → yaz" yapıyordu, kilitsiz: biri ötekinin arada yazdığını eski kopyasıyla
 * ezebiliyordu (damga kaybı = aynı dükkâna ikinci mektup; "geri döndü" / "çıktı" kaybı = ölü ya da çıkmış adrese yeniden
 * mektup). Ayrıca yazım dosyayı önce kesip sonra dolduruyordu: o anda okuyan [] görüp boş listeyle çalışabiliyordu.
 * Artık: (1) yazım geçici dosya + rename (okuyan ya eskiyi ya yeniyi görür); (2) sıcak yollar vestra_leads_update() ile
 * data/leads.lock altında okur-değiştirir-yazar; (3) geri kalan her yazıcı için vestra_save_leads(): dosya son okumadan
 * beri değiştiyse ÜÇ YÖNLÜ BİRLEŞTİRME — bu işin okuduğu kopyaya göre yaptığı değişiklikler (alan, eklenen / silinen
 * kayıt) kilit altında taze listeye uygulanır, başkasının arada yazdıkları korunur. */
function vestra_leads_merge(array $base, array $ours, array $fresh): ?array {
  $byId = static function (array $L): ?array { $o = []; foreach ($L as $x) { $id = (string)($x['id'] ?? ''); if ($id === '' || isset($o[$id])) return null; $o[$id] = $x; } return $o; };
  $B = $byId($base); $O = $byId($ours); $F = $byId($fresh);
  if ($B === null || $O === null || $F === null) return null;   // kimliksiz / çift kimlikli kayıt: birleştirilemez
  foreach ($O as $id => $o) {
    if (!isset($B[$id])) { if (!isset($F[$id])) $F[$id] = $o; continue; }        // bu işin eklediği
    if (!isset($F[$id])) continue;                                               // arada başkası sildi
    foreach ($o as $k => $v) if (!array_key_exists($k, $B[$id]) || $B[$id][$k] !== $v) $F[$id][$k] = $v;   // bu işin değiştirdiği alan
    foreach ($B[$id] as $k => $v) if (!array_key_exists($k, $o)) unset($F[$id][$k]);                        // bu işin kaldırdığı alan
  }
  foreach ($B as $id => $_) if (!isset($O[$id])) unset($F[$id]);                 // bu işin sildiği
  return array_values($F);
}
function vestra_save_leads(array $list): void {
  $snap = vestra_leads_snapshot();
  if ($snap !== null && !vestra_leads_lock_held() && $snap['key'] !== vestra_leads_file_key()) {
    $lk = @fopen(vestra_data_dir().'/leads.lock', 'c'); if ($lk) flock($lk, LOCK_EX);
    try {
      $fresh = vestra_read_json('leads.json');
      $m = vestra_leads_merge((array)$snap['list'], array_values($list), $fresh);
      if ($m !== null) $list = $m;
      vestra_leads_write($list);
    } finally { if ($lk) { flock($lk, LOCK_UN); fclose($lk); } }
    return;
  }
  vestra_leads_write($list);
}
function vestra_leads_write(array $list): void {
  $f = vestra_data_dir().'/leads.json';
  $tmp = $f.'.tmp-'.getmypid().'-'.bin2hex(random_bytes(3));
  $json = json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  if ($json === false || @file_put_contents($tmp, $json) === false) { @unlink($tmp); vestra_write_json('leads.json', array_values($list)); }
  else {
    if (is_file($f)) @chmod($tmp, fileperms($f) & 0777);
    if (!@rename($tmp, $f)) { @unlink($tmp); vestra_write_json('leads.json', array_values($list)); }
  }
  vestra_leads_snapshot(['key' => vestra_leads_file_key(), 'list' => array_values($list)]);
}
function vestra_leads_lock_held(?int $delta = null): bool {
  static $depth = 0;
  if ($delta !== null) $depth += $delta;
  return $depth > 0;
}

/** leads.json'u kilit altında oku → $fn(&$leads) → yaz. $fn false dönerse yazılmaz. Dosya dolu ama okunamıyorsa
 *  (bozuk JSON) hiçbir şey yazılmaz — boş listeyle üzerine yazmak bütün müşteri kaydını silerdi. Kilit iç içe
 *  çağrıda yeniden alınmaz. Döner: $fn'in dönüşü. */
function vestra_leads_update(callable $fn) {
  $lk = null;
  if (!vestra_leads_lock_held()) { $lk = @fopen(vestra_data_dir().'/leads.lock', 'c'); if ($lk) flock($lk, LOCK_EX); }
  vestra_leads_lock_held(1);
  try {
    $f = vestra_data_dir().'/leads.json';
    clearstatcache(true, $f);
    $leads = vestra_leads();
    if (!$leads && is_file($f) && filesize($f) > 2) { error_log('[VESTRA leads] leads.json okunamadi -- yazim iptal'); return false; }
    $r = $fn($leads);
    if ($r !== false) vestra_leads_write($leads);
    return $r;
  } finally {
    vestra_leads_lock_held(-1);
    if ($lk) { flock($lk, LOCK_UN); fclose($lk); }
  }
}

/* Sağlayıcının "geçersiz alıcı" dediği adresi işaretle — gönderim listelerinden düşsün, her
 * gönderimde yeniden denenip hak harcamasın (8 Eki 2026: 6 × "email is not valid in to").
 * TAZE listeye yazılır (uzun gönderimler sırasında başka işlerin eklediği kayıt kaybolmasın). */
function vestra_lead_mark_bad_email(string $leadId, string $why = 'provider: invalid recipient'): void {
  if ($leadId === '') return;
  vestra_leads_update(static function (array &$L) use ($leadId, $why) {
    foreach ($L as $i => $l) if (($l['id'] ?? '') === $leadId) { $L[$i]['status'] = 'bounced'; $L[$i]['bounce_reason'] = $why; $L[$i]['bounced_at'] = date('c'); return true; }
    return false;
  });
}

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

/* Shared by the daily cron (cron_find_customers.php) and the admin "Run automation now"
 * button, so both trigger paths use the exact same rotation and both leave the exact same
 * status trail — a manual click and a 9am cron run are indistinguishable to the operator. */
function vestra_cron_countries(): array {
    $custom = vestra_read_json('cron_countries.json');
    return (is_array($custom) && $custom) ? $custom
        : ['Netherlands','France','Italy','Spain','Poland','United Kingdom','United States','Australia'];
}

function vestra_cron_today_country(): string {
    $countries = vestra_cron_countries();
    $dayIndex = (int)date('z') % max(1, count($countries));
    return trim((string)($countries[$dayIndex] ?? ''));
}

/** Status snapshot read by the admin "Automation" card — written after every discovery run,
 *  whether triggered by the daily cron or a manual click, so both leave the same visible trail. */
function vestra_cron_write_status(string $country, int $found, int $added, int $emailsFound, int $emailsChecked, string $trigger, string $note = ''): void {
    vestra_write_json('cron_status.json', [
        'last_run' => date('c'),
        'country' => $country,
        'found' => $found,
        'added' => $added,
        'emails_found' => $emailsFound,
        'emails_checked' => $emailsChecked,
        'trigger' => $trigger,
        'note' => $note,
    ]);
}

function vestra_cron_status(): ?array {
    $s = vestra_read_json('cron_status.json');
    return $s ? $s : null;
}

const VESTRA_LEAD_STATUSES = ['new', 'contacted', 'replied', 'converted', 'declined'];

function vestra_lead_status_label(string $s): string {
    return match ($s) {
        'contacted'    => 'Contacted',
        'replied'      => 'Replied',
        'converted'    => 'Converted',
        'declined'     => 'Declined',
        'unsubscribed' => 'Unsubscribed',
        /* Address rejected permanently by the receiving server. Without this arm it fell
         * through to 'New', which reads as "not contacted yet" — the opposite of the truth
         * and an invitation to retry an address that can only hurt sender reputation. */
        'bounced'      => 'Bounced (dead address)',
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
        'img' => '',
    ];
}

function vestra_lead_template(): array {
    $t = vestra_read_json('lead_template.json');
    $d = vestra_lead_default_template();
    return ['subject' => $t['subject'] ?? $d['subject'], 'body' => $t['body'] ?? $d['body'], 'img' => $t['img'] ?? $d['img']];
}

function vestra_save_lead_template(array $t): void {
    vestra_write_json('lead_template.json', ['subject' => $t['subject'] ?? '', 'body' => $t['body'] ?? '', 'img' => $t['img'] ?? '']);
}

/** Render one lead's outreach email — substitutes placeholders and appends a
 *  mandatory sender-identity + unsubscribe footer (not admin-editable). */
function vestra_lead_render_email(array $lead, array $tpl): array {
    $map = [
        '{{company}}'      => $lead['company'] ?? '',
        '{{contact_name}}' => ($lead['contact_name'] ?? '') ?: 'there',
        '{{country}}'      => $lead['country'] ?? '',
    ];
    $subject = strtr($tpl['subject'] ?? '', $map);
    $body    = strtr($tpl['body'] ?? '', $map);
    $unsubUrl = 'https://vestrasales.com/lead-unsubscribe?token=' . urlencode($lead['unsub_token'] ?? '');
    $company  = $lead['company'] ?? 'your company';
    $body .= "\n\n—\nVESTRA is operated by Acerasoft LLC. This is a one-time business message from our ".
             "verified B2B wholesale marketplace — you're receiving it because {$company} was identified as a ".
             "potential trade partner by our team.\nDon't want to hear from us again? Unsubscribe instantly: {$unsubUrl}";
    return [$subject, $body];
}

/** Render a wholesale product-offer ("quote") email to a customer/buyer. $lines is a
 *  list of ['title'=>, 'price'=>, 'moq'=>, 'url'=>] built from the selected listings.
 *  A sender-identity + opt-out footer is always appended — the saved prospect's
 *  one-click unsubscribe link when the offer targets one, else a plain reply-to-opt-out
 *  line — so proactive offers stay as compliant as the invite outreach. */
function vestra_quote_render_email(string $company, string $contact, array $lines, string $note, string $unsubUrl = '', string $senderName = ''): array {
    $who  = $company !== '' ? $company : 'your business';
    $c    = $contact !== '' ? $contact : 'there';
    $from = $senderName !== '' ? $senderName : 'VESTRA';
    $subject = ($senderName !== '' ? $senderName : 'VESTRA') . ' — wholesale offer' . ($company !== '' ? ' for ' . $company : '');
    $b  = "Hello {$c},\n\n";
    $b .= "Here's a wholesale offer from {$from} on VESTRA — a KYC-verified B2B fashion marketplace. ".
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
    $b .= "Best regards,\n".($senderName !== '' ? $senderName : 'The VESTRA team');
    $b .= "\n\n—\n".($senderName !== '' ? $senderName.' via VESTRA (operated by Acerasoft LLC)' : 'VESTRA is operated by Acerasoft LLC').". You received this wholesale offer because {$who} was identified as a potential trade buyer.\n";
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
/* Leads owned by one seller (owner_uid). Admin/global leads have owner_uid ''. */
function vestra_leads_by_owner(string $uid): array {
  return array_values(array_filter(vestra_leads(), fn($l)=>(string)($l['owner_uid']??'')===$uid));
}
/* Add discovered/researched rows as leads. Company required; dedupe by email when present,
 * else by company (against existing leads and within the batch); sets owner_uid. Rows may
 * carry company, contact_name, email, country, website, source, category, address, phone.
 * Returns [addedRows, skipped] — addedRows is the actual new lead records (with generated
 * id/unsub_token), so a caller can chain straight into per-row work (e.g. live email lookup)
 * without guessing IDs. Caps at 500 per call. */
function vestra_leads_add(array $rows, string $owner=''): array {
  $addedRows=[]; $skipped=0;
  /* 17 Eki 2026: kilit altında taze listeye (vestra_leads_update) — gönderim damgaları aynı anda yazılıyor. */
  vestra_leads_update(function (array &$leads) use ($rows, $owner, &$addedRows, &$skipped) {
  $seenEmail=[]; $seenCompany=[];
  foreach($leads as $l){ $e=strtolower($l['email']??''); if($e!=='') $seenEmail[$e]=true; $seenCompany[strtolower($l['company']??'')]=true; }
  $max=500;
  foreach($rows as $r){
    if((count($addedRows)+$skipped)>=$max) break;
    $company=trim((string)($r['company']??'')); if($company===''){ $skipped++; continue; }
    $email=function_exists('vestra_email_clean') ? vestra_email_clean((string)($r['email']??'')) : strtolower(trim((string)($r['email']??''))); $ev=$email!=='' && filter_var($email,FILTER_VALIDATE_EMAIL);
    if(($ev && isset($seenEmail[$email])) || (!$ev && isset($seenCompany[strtolower($company)]))){ $skipped++; continue; }
    if($ev) $seenEmail[$email]=true; $seenCompany[strtolower($company)]=true;
    $notes=trim((string)($r['address']??''));
    if(($r['phone']??'')!=='') $notes=trim($notes.($notes!==''?' · ':'').'☎ '.$r['phone']);
    $new=[
      'id'=>'LD'.strtoupper(bin2hex(random_bytes(4))),'added_at'=>date('c'),'owner_uid'=>$owner,
      'company'=>$company,'contact_name'=>trim((string)($r['contact_name']??'')),
      'email'=>$ev?$email:'','country'=>trim((string)($r['country']??'')),
      /* Kesif her satira sehri yaziyor (vestra_discover_osm), ama bu sabit alan
         listesi onu almiyordu: sehir kayit aninda sessizce dusuyordu. Sonuc,
         "hangi sehirler tarandi" sorusunun sistemde cevabinin OLMAMASI -- oysa
         veri elimize gelmisti. Sehir olmadan tuketilmis bir sehre yeniden kesif
         atmayi ancak kesfi KOSTURUP gorebiliyoruz (bugun Milano/Roma/Floransa/
         Madrid/Barcelona/Valencia'ya 12 dakika boyle harcandi, alti sehir de sifir
         yeni kayit verdi). Mevcut kayitlarda bu alan yok; adres zaten notes icinde
         duruyor, geriye donuk doldurma ayri bir is. */
      'city'=>trim((string)($r['city']??'')),
      'website'=>trim((string)($r['website']??'')),'source'=>trim((string)($r['source']??''))?:'Discovery',
      'category'=>trim((string)($r['category']??'')),'notes'=>$notes,
      'status'=>'new','last_contacted_at'=>'','unsub_token'=>bin2hex(random_bytes(16)),
    ];
    $leads[]=$new; $addedRows[]=$new;
  }
  return (bool)$addedRows;
  });
  return [$addedRows,$skipped];
}
function vestra_lead_import_csv(string $tmpPath, string $owner=''): array {
    $fh = @fopen($tmpPath, 'r');
    if (!$fh) return [0, 0];
    $header = fgetcsv($fh, null, ',', '"', '\\');
    if (!$header) { fclose($fh); return [0, 0]; }
    $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

    $added = 0; $skipped = 0;
    /* 17 Eki 2026: kilit altında taze listeye (vestra_leads_update). */
    vestra_leads_update(function (array &$leads) use ($fh, $header, $owner, &$added, &$skipped) {
    $seenEmail = []; $seenCompany = [];
    foreach ($leads as $l) {
        $e = strtolower($l['email'] ?? ''); if ($e !== '') $seenEmail[$e] = true;
        $seenCompany[strtolower($l['company'] ?? '')] = true;
    }

    $max = 500;
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
            'owner_uid'         => $owner,
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
    return $added > 0;
    });
    fclose($fh);
    return [$added, $skipped];
}
