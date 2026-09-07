<?php
/**
 * VESTRA — buyer/seller direct messaging.
 * File-based threads in data/messages.json. One thread per (buyer, seller, listing) triple.
 * Off-platform contact info (email, IBAN) is detected and blocked before a message is stored —
 * all communication and payment must stay on VESTRA so buyer protection still applies.
 */
function vestra_msg_file(): string { return dirname(__DIR__).'/data/messages.json'; }

/* Synthetic recipient for messages about platform listings that have no assigned
 * seller (demo / catalogue items). These threads route to the operator, who replies
 * from Admin → Messages. */
const VESTRA_SUPPORT_UID = 'vestra-support';
function vestra_msg_label(string $uid): string { return $uid === VESTRA_SUPPORT_UID ? 'VESTRA Support' : ''; }

/**
 * Handle the BUYER sees in place of the seller's shop name.
 * A thread is one (buyer, seller, listing) triple, so the listing's own SKU is the
 * handle the buyer already knows from the product page. Naming the shop instead hands
 * the supplier over in a single line and makes the off-platform filter below pointless:
 * the buyer just searches the name and leaves. Threads with no listing fall back to a
 * stable per-seller code so the label never changes under the same conversation.
 */
function vestra_msg_seller_ident(string $listingId, string $sellerUid): string {
    if ($listingId !== '' && function_exists('vestra_listing_by_id')) {
        $sku = trim((string)((vestra_listing_by_id($listingId) ?? [])['sku'] ?? ''));
        if ($sku !== '') return $sku;
    }
    if ($listingId !== '') return $listingId;
    return $sellerUid === '' ? '—' : 'S-'.strtoupper(substr(sha1('vestra-seller|'.$sellerUid), 0, 6));
}

function vestra_msg_threads(): array {
    $f = vestra_msg_file();
    if (!is_readable($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function vestra_msg_save_threads(array $t): void {
    file_put_contents(vestra_msg_file(), json_encode(array_values($t), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function vestra_msg_thread_id(string $buyerUid, string $sellerUid, string $listingId=''): string {
    return substr(md5($buyerUid.'|'.$sellerUid.'|'.$listingId), 0, 16);
}
function vestra_msg_find_thread(string $id): ?array {
    foreach (vestra_msg_threads() as $t) if (($t['id']??'') === $id) return $t;
    return null;
}
/* Threads a given account (buyer or seller side) participates in, most recent activity first. */
function vestra_msg_my_threads(string $uid): array {
    $mine = array_values(array_filter(vestra_msg_threads(), fn($t) => ($t['buyer_uid']??'')===$uid || ($t['seller_uid']??'')===$uid));
    usort($mine, fn($a,$b) => strtotime($b['last_at']??'1970-01-01') <=> strtotime($a['last_at']??'1970-01-01'));
    return $mine;
}
function vestra_msg_thread_owner(string $id, string $uid): bool {
    $t = vestra_msg_find_thread($id);
    if ($t === null || $uid === '') return false;
    return ($t['buyer_uid']??'') === $uid || ($t['seller_uid']??'') === $uid;
}

/* Returns 'email', 'iban', 'phone', or null. Deliberately blunt — false positives just mean
   the sender edits the message and resends, which is a fine trade-off for keeping deals and
   contact details on-platform. The IBAN pattern matches directly against the original text
   (not a globally whitespace-stripped copy) so it follows real IBAN formatting — country+
   checksum glued together, then groups of 4 optionally separated by a space/hyphen — instead
   of merging unrelated adjacent words into a false positive, or losing a real IBAN's word
   boundary. The phone check only fires on a digit run long enough to actually BE a phone
   number (9–15 digits once separators are stripped) so ordinary quantities/prices/SKUs
   (routinely 2–7 digits in this catalog) pass through untouched. */
function vestra_msg_flag_offplatform(string $text): ?string {
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) return 'email';
    if (preg_match('/\b[A-Z]{2}\d{2}(?:[ -]?[A-Z0-9]{4}){2,7}(?:[ -]?[A-Z0-9]{1,3})?\b/i', $text)) return 'iban';
    if (preg_match('/(?:\+|\b00)\s?\d[\d \-.()]{5,15}\d\b/', $text)) return 'phone';
    if (preg_match('/(?<![\d.,])(\d[\d \-.\/]{6,20}\d)(?![\d.,])/', $text, $m)) {
        $digits = preg_replace('/\D/', '', $m[1]);
        if (strlen($digits) >= 9 && strlen($digits) <= 15) return 'phone';
    }
    return null;
}

/**
 * Send a message in the (buyer, seller, listing) thread, creating it if needed.
 * $fromUid must be one of $buyerUid/$sellerUid — the caller enforces that.
 * Returns ['ok'=>true,'thread_id'=>...] or ['ok'=>false,'error'=>'empty'|'flagged','flag'=>...].
 */
/* Moderation trail: every blocked off-platform attempt is kept (server-side only, data/ is
   web-blocked) so the admin can spot repeat circumvention and act on the account. */
function vestra_msg_log_blocked(string $fromUid, string $buyerUid, string $sellerUid, string $listingId, string $flag, string $text): void {
    $f = dirname(__DIR__).'/data/blocked_messages.json';
    $log = [];
    if (is_readable($f)) { $d = json_decode((string)file_get_contents($f), true); if (is_array($d)) $log = $d; }
    $log[] = ['at'=>date('c'), 'from'=>$fromUid, 'buyer_uid'=>$buyerUid, 'seller_uid'=>$sellerUid,
              'listing_id'=>$listingId, 'flag'=>$flag, 'text'=>mb_substr($text, 0, 500)];
    file_put_contents($f, json_encode($log, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);

    require_once __DIR__.'/notify.php';
    $who = $fromUid; $whoEmail = '';
    foreach (auth_accounts() as $a) {
        if (($a['id']??'') === $fromUid) { $who = $a['company'] ?: ($a['name'] ?: $fromUid); $whoEmail = $a['email'] ?? ''; break; }
    }
    vestra_notify(
        "⚠️ Off-platform contact attempt blocked ({$flag}) — {$who}",
        "A message containing ".match($flag){'email'=>'an email address','phone'=>'a phone number',default=>'an IBAN'}." was blocked in buyer-seller chat.\n\n".
        "Sender:  {$who}".($whoEmail?" <{$whoEmail}>":'')." (uid {$fromUid})\n".
        "Thread:  buyer {$buyerUid} ↔ seller {$sellerUid}".($listingId?" · listing {$listingId}":'')."\n\n".
        "Attempted text:\n".mb_substr($text, 0, 500)."\n\n".
        "Review: https://vestrasales.com/admin?tab=messages"
    );
}

function vestra_msg_blocked_log(): array {
    $f = dirname(__DIR__).'/data/blocked_messages.json';
    if (!is_readable($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function vestra_msg_send(string $buyerUid, string $sellerUid, string $fromUid, string $text, string $listingId=''): array {
    $text = trim(preg_replace('/[ \t]+/', ' ', (string)$text));
    if ($text === '' || $buyerUid === '' || $sellerUid === '') return ['ok'=>false, 'error'=>'empty'];
    if ($flag = vestra_msg_flag_offplatform($text)) {
        vestra_msg_log_blocked($fromUid, $buyerUid, $sellerUid, $listingId, $flag, $text);
        return ['ok'=>false, 'error'=>'flagged', 'flag'=>$flag];
    }

    $threads = vestra_msg_threads();
    $id = vestra_msg_thread_id($buyerUid, $sellerUid, $listingId);
    $recipient = $fromUid === $buyerUid ? $sellerUid : $buyerUid;
    $hadUnread = false; // was the recipient already behind before this message?
    $found = false;
    foreach ($threads as &$t) {
        if (($t['id']??'') === $id) {
            $hadUnread = vestra_msg_unread($t, $recipient);
            $t['messages'][] = ['from'=>$fromUid, 'text'=>$text, 'at'=>date('c')];
            $t['last_at'] = date('c');
            $found = true;
            break;
        }
    }
    unset($t);
    if (!$found) {
        $threads[] = [
            'id'         => $id,
            'buyer_uid'  => $buyerUid,
            'seller_uid' => $sellerUid,
            'listing_id' => $listingId,
            'created_at' => date('c'),
            'last_at'    => date('c'),
            'messages'   => [['from'=>$fromUid, 'text'=>$text, 'at'=>date('c')]],
        ];
    }
    vestra_msg_save_threads($threads);
    require_once __DIR__.'/notify.php';
    $fromLabel = vestra_msg_label($fromUid); $fromEmail = '';
    if ($fromLabel === '') {
        foreach (auth_accounts() as $a) { if (($a['id']??'') === $fromUid) { $fromLabel = $a['company'] ?: ($a['name'] ?: 'A VESTRA user'); $fromEmail = $a['email'] ?? ''; break; } }
    }
    if ($fromLabel === '') $fromLabel = 'A VESTRA user';
    // Email ping to the recipient — only on the FIRST unread message since they last read
    // the thread (no per-message spam). Content stays out of the mail: conversations live
    // on VESTRA, the mail is just the doorbell.
    if (!$hadUnread && $recipient !== '' && $recipient !== VESTRA_SUPPORT_UID) {
        $recAcc = null;
        foreach (auth_accounts() as $a) { if (($a['id']??'') === $recipient) { $recAcc = $a; break; } }
        if ($recAcc && !empty($recAcc['email'])) {
            $panel = ($recAcc['type']??'') === 'seller' ? 'seller' : 'buyer';
            /* The buyer's doorbell carries the same ident the panel shows. The shop name
               in a subject line — or its address as Reply-To — would give away in one mail
               exactly what the thread withholds. English on purpose: this label is built
               outside the recipient's language context, and a half-translated one is worse
               than a consistent one. */
            $toBuyer   = ($fromUid === $sellerUid && $recipient === $buyerUid);
            $mailLabel = $toBuyer ? 'Seller '.vestra_msg_seller_ident($listingId, $sellerUid) : $fromLabel;
            if ($toBuyer) $fromEmail = '';
            [$mSubj,$mBody,$mOpts] = vestra_tpl_message(vestra_user_lang($recAcc), $recAcc['name']?:($recAcc['company']?:'there'),
              $mailLabel, "https://vestrasales.com/{$panel}?tab=messages");
            /* A note from VESTRA Support was written by a person, so it signs off like one.
               Messages between two members stay unsigned — the sender is the other company,
               and putting our signature under their message would misattribute it. */
            if ($fromUid === VESTRA_SUPPORT_UID) $mOpts['signature'] = vestra_support_signature(vestra_user_lang($recAcc));
            vestra_send_mail($recAcc['email'], $mSubj, $mBody, $fromEmail, $mailLabel, null, '', $mOpts);
        } else {
            // No usable email on file — logging in is the ONLY way this recipient would ever
            // find out. Tell the operator so it's a visible follow-up, not a silent miss.
            vestra_notify("⚠️ VESTRA message not emailed — no address on file",
              "{$fromLabel} sent a message, but the recipient (".
              ($recAcc['company'] ?? ($recAcc['name'] ?? $recipient)).") has no email on file, so no notification could be sent.\n\n".
              "Add their email: https://vestrasales.com/admin?tab=users\n".
              "Thread: https://vestrasales.com/admin?tab=messages");
        }
    }
    // Admin visibility — every message, so the operator has proof the messaging system is
    // actually delivering, not just posting silently into a thread nobody happens to check.
    vestra_notify("💬 VESTRA message — {$fromLabel}",
      "{$fromLabel} sent a message on VESTRA".($listingId !== '' ? " (listing {$listingId})" : '').":\n\n".
      mb_substr($text, 0, 400)."\n\n".
      "Thread: https://vestrasales.com/admin?tab=messages");
    // Push ping to the recipient's installed devices (fire-and-forget).
    if ($recipient !== '') {
        require_once __DIR__.'/push.php';
        $recPanel = ($recipient === $sellerUid) ? 'seller' : 'buyer';
        vestra_push_send($recipient, 'VESTRA — new message',
            mb_substr(preg_replace('/\s+/', ' ', $text), 0, 90),
            '/'.$recPanel.'?tab=messages');
    }
    return ['ok'=>true, 'thread_id'=>$id];
}

/**
 * Post a SYSTEM message (offer / offer response) into the buyer↔seller thread.
 * System messages carry structured meta and no free text, so they bypass the
 * off-platform filter and render as a prominent card in the viewer's language.
 */
function vestra_msg_post_system(string $buyerUid, string $sellerUid, string $listingId, array $meta): string {
    if ($buyerUid === '' || $sellerUid === '') return '';
    $threads = vestra_msg_threads();
    $id = vestra_msg_thread_id($buyerUid, $sellerUid, $listingId);
    $entry = ['from'=>'system', 'meta'=>$meta, 'text'=>'', 'at'=>date('c')];
    $found = false;
    foreach ($threads as &$t) {
        if (($t['id']??'') === $id) { $t['messages'][] = $entry; $t['last_at'] = date('c'); $found = true; break; }
    }
    unset($t);
    if (!$found) {
        $threads[] = [
            'id'=>$id, 'buyer_uid'=>$buyerUid, 'seller_uid'=>$sellerUid, 'listing_id'=>$listingId,
            'created_at'=>date('c'), 'last_at'=>date('c'), 'messages'=>[$entry],
        ];
    }
    vestra_msg_save_threads($threads);
    // Admin visibility for every order/offer status card — same reasoning as vestra_msg_send:
    // the operator should see proof each step actually fired, not just trust it happened.
    require_once __DIR__.'/notify.php';
    $accLabel = ['', '']; // [buyer label, seller label]
    foreach ([$buyerUid, $sellerUid] as $i => $partyUid) {
        $accLabel[$i] = vestra_msg_label($partyUid) ?: $partyUid;
        if ($accLabel[$i] === $partyUid) {
            foreach (auth_accounts() as $a) { if (($a['id']??'') === $partyUid) { $accLabel[$i] = $a['company'] ?: ($a['name'] ?: $partyUid); break; } }
        }
    }
    vestra_notify("📋 VESTRA — ".vestra_msg_snippet($entry),
      "Buyer: {$accLabel[0]}\nSeller: {$accLabel[1]}".($listingId !== '' ? "\nListing: {$listingId}" : '')."\n\n".
      "Thread: https://vestrasales.com/admin?tab=messages");
    return $id;
}

/* Total number of threads with unread activity for $uid — powers the sidebar badge. */
function vestra_msg_unread_count(string $uid): int {
    if ($uid === '') return 0;
    $n = 0;
    foreach (vestra_msg_my_threads($uid) as $t) if (vestra_msg_unread($t, $uid)) $n++;
    return $n;
}

/* Inbox snippet for the latest message (system messages show their card label). */
function vestra_msg_snippet(array $m): string {
    if (($m['from']??'') !== 'system') return mb_substr($m['text']??'', 0, 80);
    $meta = $m['meta'] ?? [];
    return match($meta['kind']??'') {
        'offer'          => '💰 '.t('New offer').' — '.($meta['product']??''),
        'offer_response' => match($meta['status']??''){
            'accept'  => '✓ '.t('Offer accepted'),
            'decline' => '✗ '.t('Offer declined'),
            default   => '↩ '.t('Counter offer'),
        },
        'order' => match($meta['status']??''){
            /* 'paid' ve 'delivered' BURADA YOKTU: satici odemeyi/teslimati
               isaretleyince sohbette "Order placed" karti cikiyordu -- ayni
               eksigin ucuncu yeri (etiket + zincir + burasi). */
            'paid'      => '✓ '.t('Payment received'),
            'shipped'   => '🚚 '.t('Order shipped'),
            'delivered' => '📦 '.t('Order delivered'),
            'completed' => '✓ '.t('Order completed — payment released'),
            default     => '📦 '.t('Order placed'),
        },
        'claim' => (($meta['status']??'') === 'resolved' ? '✓ '.t('Claim resolved') : '⚠️ '.t('Claim opened')).' — '.($meta['claim_ref']??''),
        'request_offer' => match($meta['status']??''){
            'accept' => '✓ '.t('Sourcing offer accepted'),
            default  => '📋 '.t('New sourcing offer').' — '.($meta['product']??''),
        },
        default => '',
    };
}

/* Render a system message as a highlighted card (offer → gold, response → green/red/gold). */
function vestra_msg_system_html(array $m, string $viewerRole): string {
    $meta = $m['meta'] ?? [];
    $kind = $meta['kind'] ?? '';
    $time = '<div class="msgtime">'.htmlspecialchars(vestra_msg_clock((string)($m['at']??''))).'</div>';
    if ($kind === 'offer') {
        $qty   = (int)($meta['qty']??0);
        $unit  = eur($meta['unit_price']??0);
        $total = eur($meta['total']??0);
        $body  = '<div class="mo-head">💰 '.t('New offer').' <span class="atag" style="margin-left:6px">'.htmlspecialchars($meta['ref']??'').'</span></div>'.
                 '<div class="mo-prod">'.htmlspecialchars($meta['product']??'').'</div>'.
                 '<div class="mo-terms">'.$qty.' × '.$unit.' — <b>'.$total.'</b> '.t('total').'</div>'.
                 (!empty($meta['colors'])?'<div class="mo-terms">'.t('Colours').': '.htmlspecialchars(implode(', ', array_map(fn($c)=>t($c),(array)$meta['colors']))).'</div>':'');
        if ($viewerRole === 'seller') {
            $body .= '<a class="mo-act" href="/seller?tab=offers">'.t('Respond in Offers tab →').'</a>';
        }
        return '<div class="msgoffer">'.$body.$time.'</div>';
    }
    if ($kind === 'request_offer') {
        $accepted = ($meta['status']??'') === 'accept';
        $qty  = htmlspecialchars((string)($meta['qty']??''));
        $unit = eur($meta['unit_price']??0);
        $body = '<div class="mo-head">'.($accepted?'✓ '.t('Sourcing offer accepted'):'📋 '.t('New sourcing offer')).
                ' <span class="atag" style="margin-left:6px">'.htmlspecialchars($meta['ref']??'').'</span></div>'.
                '<div class="mo-prod">'.htmlspecialchars($meta['product']??'').'</div>'.
                '<div class="mo-terms">'.$unit.' / pc'.($qty?' · '.$qty:'').'</div>';
        if (!$accepted && $viewerRole === 'buyer') {
            $body .= '<a class="mo-act" href="/buyer?tab=requests">'.t('Review in My requests →').'</a>';
        }
        if ($accepted && $viewerRole === 'seller' && function_exists('vestra_invoices_for_ref')) {
            foreach (vestra_invoices_for_ref($meta['ref'] ?? '') as $iv) {
                $body .= '<a class="mo-act" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener">📄 '.t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a>';
            }
        }
        return '<div class="msgoffer'.($accepted?' ok':'').'">'.$body.$time.'</div>';
    }
    if ($kind === 'offer_response') {
        [$cls, $label] = match($meta['status']??''){
            'accept'  => ['ok',  '✓ '.t('Offer accepted')],
            'decline' => ['bad', '✗ '.t('Offer declined')],
            default   => ['ctr', '↩ '.t('Counter offer').': '.eur($meta['counter_price']??0).' / '.t('unit')],
        };
        return '<div class="msgoffer '.$cls.'"><div class="mo-head">'.$label.
               ' <span class="atag" style="margin-left:6px">'.htmlspecialchars($meta['ref']??'').'</span></div>'.
               '<div class="mo-prod">'.htmlspecialchars($meta['product']??'').'</div>'.$time.'</div>';
    }
    if ($kind === 'claim') {
        /* Talep karti (6 Eyl 2026). SSS returns/9: "sonuc size siparis ipliginde
           bildirilir, dosya tek yerde kalir". Iki tarafa da ayni kart; satici
           neyin sikayet edildigini gormek zorunda (aciklama istenecek). */
        $resolved = ($meta['status']??'') === 'resolved';
        $body = '<div class="mo-head">'.($resolved ? '✓ '.t('Claim resolved') : '⚠️ '.t('Claim opened'))
              . ' <span class="atag" style="margin-left:6px">'.htmlspecialchars($meta['claim_ref']??'').'</span>'
              . ' <span class="atag">'.htmlspecialchars($meta['ref']??'').'</span></div>'
              . '<div class="mo-prod">'.htmlspecialchars(t((string)($meta['reason']??''))).'</div>';
        if ($resolved && !empty($meta['outcome'])) $body .= '<div class="mo-terms"><b>'.t('Outcome').':</b> '.htmlspecialchars((string)$meta['outcome']).'</div>';
        $panel = $viewerRole === 'seller' ? '/seller?tab=orders' : '/buyer?tab=orders&view='.rawurlencode((string)($meta['ref']??''));
        $body .= '<a class="mo-act" href="'.htmlspecialchars($panel).'">'.t('View order →').'</a>';
        return '<div class="msgoffer '.($resolved ? 'ok' : 'ctr').'">'.$body.$time.'</div>';
    }
    if ($kind === 'order') {
        [$cls, $label] = match($meta['status']??''){
            'paid'      => ['ok',  '✓ '.t('Payment received')],
            'shipped'   => ['ctr', '🚚 '.t('Order shipped')],
            'delivered' => ['ctr', '📦 '.t('Order delivered')],
            'completed' => ['ok',  '✓ '.t('Order completed — payment released')],
            default     => ['',    '📦 '.t('Order placed')],
        };
        $body = '<div class="mo-head">'.$label.
                ' <span class="atag" style="margin-left:6px">'.htmlspecialchars($meta['ref']??'').'</span></div>';
        if (!empty($meta['items']))    $body .= '<div class="mo-prod">'.htmlspecialchars($meta['items']).'</div>';
        if (!empty($meta['total']))    $body .= '<div class="mo-terms"><b>'.eur($meta['total']).'</b> '.t('total').'</div>';
        if (!empty($meta['tracking'])) $body .= '<div class="mo-prod">'.t('Tracking').': '.htmlspecialchars($meta['tracking']).'</div>';
        $panel = $viewerRole === 'seller' ? '/seller?tab=orders' : '/buyer?tab=orders';
        $body .= '<a class="mo-act" href="'.$panel.'">'.t('View order →').'</a>';
        return '<div class="msgoffer '.$cls.'">'.$body.$time.'</div>';
    }
    return '';
}

/* Mark a thread as read by $uid — store the message COUNT seen (immune to same-second
   timestamp collisions that a date-based marker suffers from). */
function vestra_msg_mark_read(string $id, string $uid): void {
    $threads = vestra_msg_threads();
    foreach ($threads as &$t) {
        if (($t['id']??'') === $id) { $t['read'][$uid] = count($t['messages'] ?? []); break; }
    }
    unset($t);
    vestra_msg_save_threads($threads);
}
/* Unread ⇔ there is at least one message beyond what $uid has seen that they didn't send. */
function vestra_msg_unread(array $thread, string $uid): bool {
    $msgs = $thread['messages'] ?? [];
    $seen = $thread['read'][$uid] ?? 0;
    if (!is_int($seen)) $seen = 0; // legacy date-based markers → treat as unseen baseline
    for ($i = max(0, $seen); $i < count($msgs); $i++) {
        if (($msgs[$i]['from'] ?? '') !== $uid) return true;
    }
    return false;
}

/* Display label for the OTHER party in a thread, from the point of view of $uid.
   The seller side is named by product ident, never by shop; the buyer side keeps its
   company name — a supplier is entitled to know which house it is selling to. */
function vestra_msg_counterpart_label(array $thread, string $uid): string {
    $otherUid = ($thread['buyer_uid']??'') === $uid ? ($thread['seller_uid']??'') : ($thread['buyer_uid']??'');
    if ($otherUid === VESTRA_SUPPORT_UID) return vestra_msg_label($otherUid);
    if ($otherUid !== '' && $otherUid === ($thread['seller_uid'] ?? '')) {
        return t('Seller').' '.vestra_msg_seller_ident((string)($thread['listing_id'] ?? ''), $otherUid);
    }
    foreach (auth_accounts() as $a) {
        if (($a['id']??'') === $otherUid) return $a['company'] ?: ($a['name'] ?: t('Account'));
    }
    return t('Account');
}

/**
 * Admin starts (or continues) a direct thread with one buyer or seller account —
 * covers accounts an admin needs to reach on-platform (e.g. no usable email on file
 * yet). Admin sits in the VESTRA_SUPPORT_UID slot on whichever side isn't $targetUid,
 * so the target sees it in their normal Messages tab, labelled "VESTRA Support".
 */
function vestra_msg_admin_start(string $targetUid, string $targetType, string $body): array {
    if ($targetUid === '' || !in_array($targetType, ['buyer','seller'], true)) return ['ok'=>false, 'error'=>'empty'];
    $buyerUid  = $targetType === 'buyer'  ? $targetUid : VESTRA_SUPPORT_UID;
    $sellerUid = $targetType === 'seller' ? $targetUid : VESTRA_SUPPORT_UID;
    return vestra_msg_send($buyerUid, $sellerUid, VESTRA_SUPPORT_UID, $body, '');
}


/* ── Zaman etiketleri ───────────────────────────────────────────────────────
   Listede ve baloncukta 7 Eyl 2026'ya kadar ham ISO duruyordu ("2026-09-07T09:52").
   Bugun: yalniz saat; dun: "Dun"; son alti gun: gun adi; daha eski: gun + ay
   (baska yilsa yil da). Gun/ay adlari sozlukten ('Mon'..'Sun', 'Jan'..'Dec'),
   siralama da sozlukten ('{d} {m}' — Japonca '{m}{d}日' yazar). */
function vestra_msg_clock(string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('H:i', $ts) : '';
}
/* [zaman damgasi, bugunden kac gun once] — cozulemeyen tarihte null. */
function vestra_msg_day_diff(string $iso, ?int $now = null): ?array {
    $ts = strtotime($iso);
    if (!$ts) return null;
    $now ??= time();
    $diff = (int)round((strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', $ts))) / 86400);
    return [$ts, max(0, $diff)];   // saat kaymasindan dogan "yarin" bugun sayilir
}
function vestra_msg_day_label(string $iso, ?int $now = null): string {
    $p = vestra_msg_day_diff($iso, $now);
    if ($p === null) return '';
    [$ts, $diff] = $p;
    if ($diff === 0) return t('Today');
    if ($diff === 1) return t('Yesterday');
    if ($diff < 7)   return t(date('D', $ts));
    $now ??= time();
    $pattern = date('Y', $ts) === date('Y', $now) ? t('{d} {m}') : t('{d} {m} {y}');
    return str_replace(['{d}', '{m}', '{y}'], [date('j', $ts), t(date('M', $ts)), date('Y', $ts)], $pattern);
}
/* Liste satiri: bugunkuler saatle, digerleri gun etiketiyle. */
function vestra_msg_when(string $iso, ?int $now = null): string {
    $p = vestra_msg_day_diff($iso, $now);
    if ($p === null) return '';
    return $p[1] === 0 ? date('H:i', $p[0]) : vestra_msg_day_label($iso, $now);
}

/* Konusma avatari: ilanin fotografi (ilan biliniyorsa ve bakan gorebiliyorsa),
   yoksa marka bas harfi; destek konusmasinda VESTRA isareti. Fotograf kapisi
   urun sayfasiyla AYNI (auth_user_approved): kapali hesabin vitrinde goremedigi
   fotograf mesaj listesinden sizmaz. Satici kendi ilaninin fotografini her
   zaman gorur. */
function vestra_msg_avatar_html(array $thread, string $uid): string {
    $otherUid = ($thread['buyer_uid']??'') === $uid ? ($thread['seller_uid']??'') : ($thread['buyer_uid']??'');
    if ($otherUid === VESTRA_SUPPORT_UID) {
        return '<span class="tr-ava tr-ava-v" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none">'
             . '<rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="currentColor" stroke-width="1.6"/>'
             . '<path d="M9 10l7 13 7-13" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
    }
    $lid = (string)($thread['listing_id'] ?? '');
    $initial = '';
    if ($lid !== '') {
        $p = function_exists('vestra_find') ? vestra_find($lid) : null;
        if (!$p && function_exists('vestra_listing_by_id')) $p = vestra_listing_by_id($lid);
        if ($p) {
            $img = function_exists('vestra_primary_image') ? vestra_primary_image($p) : (string)($p['image'] ?? '');
            $isSeller = ($thread['seller_uid'] ?? '') === $uid;
            $canSee = $isSeller || (function_exists('auth_user_approved') && function_exists('auth_user') && auth_user_approved(auth_user()));
            if ($img !== '' && $canSee) {
                return '<span class="tr-ava"><img src="'.htmlspecialchars($img).'" alt="" loading="lazy" decoding="async"></span>';
            }
            $initial = trim((string)($p['brand'] ?? ''));
        }
    }
    if ($initial === '') $initial = vestra_msg_counterpart_label($thread, $uid);
    $initial = mb_strtoupper(mb_substr(trim($initial), 0, 1));
    return '<span class="tr-ava tr-ava-i" aria-hidden="true">'.htmlspecialchars($initial !== '' ? $initial : '·').'</span>';
}

/**
 * Mesaj sekmesinin tamami: konusma listesi + acik konusma + betikler.
 *
 * Alici ve satici paneli AYNI fonksiyonu cagirir. 7 Eyl 2026'ya kadar buyer.php
 * ve seller.php birer kopya tasiyordu; mobil duzeltmesi ikisine ayri ayri
 * yazilacak ve ilk farkli duzenlemede ayrisacaklardi. $role yalnizca panel
 * yolunu (/buyer, /seller), bos-liste metnini ve sistem kartlarinin bakis
 * acisini secer. YETKI burada degil: $thread'in bu hesaba ait oldugu cagiranda
 * dogrulanir; burasi yalnizca cizer.
 *
 * Telefonda konusma acikken kabuk ekrani doldurur (CSS: .msgshell.has-thread);
 * baloncuklar tek kaydirma alanidir, yazma kutusu alt sekme cubugunun hemen
 * ustunde durur. Taslak korunur: 15 sn'lik yoklama yeni mesaj gorunce sayfayi
 * yeniler, yazilmakta olan metin sessionStorage'a alinip geri konur — eskiden
 * yenileme yarim mesaji siliyordu.
 */
function vestra_msg_panel_html(string $role, string $uid, string $tid, ?array $thread, array $myThreads, string $msgerr = ''): string {
    $panel = $role === 'seller' ? '/seller' : '/buyer';
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $base = $panel.'?tab=messages';

    /* Liste */
    $list = '<div class="mssearch"><input id="mfilter" type="search" autocomplete="off" placeholder="'.$h(t('Search conversations…')).'" oninput="mFilterThreads(this.value)"></div>';
    if (!$myThreads) {
        $list .= '<p class="hint msnone">'.($role === 'seller' ? t('No messages yet. Buyers can message you from a product page.') : t('No messages yet. Start a conversation from any product page.')).'</p>';
    } else {
        $list .= '<div class="threadlist" id="mThreadList">';
        foreach ($myThreads as $th) {
            $last   = end($th['messages']);
            $unread = vestra_msg_unread($th, $uid);
            $name   = vestra_msg_counterpart_label($th, $uid);
            $list .= '<a class="threadrow'.($unread ? ' unread' : '').(($th['id'] ?? '') === $tid ? ' active' : '').'" data-name="'.$h(mb_strtolower($name)).'" href="'.$base.'&thread='.urlencode((string)$th['id']).'">'
                   . vestra_msg_avatar_html($th, $uid)
                   . '<span class="tr-body"><span class="tr-top"><span class="tr-name">'.$h($name).($unread ? ' <span class="tr-dot" aria-label="'.$h(t('Unread')).'"></span>' : '').'</span>'
                   . '<span class="tr-time">'.$h(vestra_msg_when((string)($th['last_at'] ?? ''))).'</span></span>'
                   . '<span class="tr-snippet">'.$h(vestra_msg_snippet($last ?: [])).'</span></span></a>';
        }
        $list .= '</div>';
    }

    /* Acik konusma */
    if ($thread) {
        $ctp  = vestra_msg_counterpart_label($thread, $uid);
        $main = '<div class="msghead"><a class="msback" href="'.$base.'" aria-label="'.$h(t('Back to conversations')).'">'
              . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></a>'
              . '<div class="msghead-t"><div class="msghead-n">'.$h($ctp).'</div>';
        $lid = (string)($thread['listing_id'] ?? '');
        $tl  = null;
        if ($lid !== '') { $tl = function_exists('vestra_find') ? vestra_find($lid) : null; if (!$tl) $tl = vestra_listing_by_id($lid); }
        if ($tl) {
            $main .= '<a class="msghead-s" href="/product?id='.urlencode($lid).'">'.$h(trim(($tl['brand'] ?? '').' — '.($tl['name'] ?? ''), ' —')).'</a>';
        }
        $main .= '</div></div>';
        if (in_array($msgerr, ['email', 'iban', 'phone'], true)) {
            $main .= '<div class="banner msgerr">⚠ '.t('For your safety, sharing email addresses, phone numbers, or bank/IBAN details is not allowed here — all communication and payment must stay on VESTRA so buyer protection still applies. Your message was not sent.').'</div>';
        }
        $main .= '<div class="msgthread" id="mThread">';
        $day = '';
        foreach ($thread['messages'] as $m) {
            $at = (string)($m['at'] ?? '');
            $d  = $at !== '' ? date('Y-m-d', strtotime($at) ?: 0) : '';
            if ($d !== '' && $d !== $day) { $day = $d; $main .= '<div class="msgday"><span>'.$h(vestra_msg_day_label($at)).'</span></div>'; }
            if (($m['from'] ?? '') === 'system') { $main .= vestra_msg_system_html($m, $role); continue; }
            $mine = ($m['from'] ?? '') === $uid;
            $main .= '<div class="msgbubblewrap'.($mine ? ' mine' : '').'"><div class="msgbubble'.($mine ? ' mine' : '').'">'
                   . nl2br($h($m['text'] ?? ''))
                   . '<div class="msgtime">'.$h(vestra_msg_clock($at)).'</div></div></div>';
        }
        $main .= '</div>';
        $main .= '<form method="post" action="'.$base.'" class="msgcompose" id="mCompose">'
               . '<input type="hidden" name="_action" value="send_message">'
               . '<input type="hidden" name="thread_id" value="'.$h($tid).'">'
               . '<textarea name="body" rows="1" placeholder="'.$h(t('Write a message…')).'" required enterkeyhint="enter" autocapitalize="sentences"></textarea>'
               . '<button class="btn btn-p mssend" type="submit" aria-label="'.$h(t('Send')).'"><span class="mssend-t">'.t('Send').'</span>'
               . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15"/><path d="M13 6l6 6-6 6"/></svg></button>'
               . '</form>'
               . '<p class="hint mshint"><span class="mskey">'.t('Enter to send · Shift+Enter for a new line').' · </span>'.t('Do not share email addresses, phone numbers, or bank details — keep all communication and payment on VESTRA.').'</p>';
    } else {
        $main = '<div class="msempty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5z"/></svg>'
              . '<span>'.t('Select a conversation to start messaging.').'</span></div>';
    }

    $out = '<div class="panelcard msgpanel"><div class="msgshell'.($thread ? ' has-thread' : '').'">'
         . '<div class="mslist">'.$list.'</div><div class="msmain">'.$main.'</div></div></div>';
    $out .= '<script>function mFilterThreads(q){q=q.toLowerCase();document.querySelectorAll("#mThreadList .threadrow").forEach(function(r){r.style.display=r.dataset.name.indexOf(q)>-1?"":"none";});}</script>';
    if ($thread) {
        $pollUrl = $base.'&thread='.urlencode($tid).'&poll=1';
        $out .= '<script>(function(){'
              . 'var mt=document.getElementById("mThread"),f=document.getElementById("mCompose"),ta=f?f.querySelector("textarea"):null;'
              . 'if(mt)mt.scrollTop=mt.scrollHeight;'
              . 'var key="vmsgdraft:"+'.json_encode($tid).';'
              . 'try{var d=sessionStorage.getItem(key);if(d&&ta&&!ta.value){ta.value=d;ta.focus();ta.setSelectionRange(ta.value.length,ta.value.length);}sessionStorage.removeItem(key);}catch(e){}'
              . 'function grow(){if(!ta)return;ta.style.height="auto";ta.style.height=Math.min(ta.scrollHeight,160)+"px";}'
              . 'if(ta){ta.addEventListener("input",grow);grow();'
              . 'var fine=window.matchMedia&&matchMedia("(hover:hover) and (pointer:fine)").matches;'
              . 'if(fine)ta.addEventListener("keydown",function(e){if(e.key==="Enter"&&!e.shiftKey&&!e.isComposing){e.preventDefault();if(ta.value.trim()){if(f.requestSubmit)f.requestSubmit();else f.submit();}}});'
              . 'f.addEventListener("submit",function(){try{sessionStorage.removeItem(key);}catch(e){}});}'
              . 'var last='.json_encode((string)($thread['last_at'] ?? '')).';'
              . 'setInterval(function(){fetch('.json_encode($pollUrl).',{cache:"no-store"}).then(function(r){return r.json()}).then(function(d){'
              . 'if(d.last&&d.last!==last){try{if(ta&&ta.value.trim())sessionStorage.setItem(key,ta.value);}catch(e){}location.reload();}'
              . '}).catch(function(){})},15000);'
              . '})();</script>';
    }
    return $out;
}
