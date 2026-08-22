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
            [$mSubj,$mBody,$mOpts] = vestra_tpl_message(vestra_user_lang($recAcc), $recAcc['name']?:($recAcc['company']?:'there'),
              $fromLabel, "https://vestrasales.com/{$panel}?tab=messages");
            /* A note from VESTRA Support was written by a person, so it signs off like one.
               Messages between two members stay unsigned — the sender is the other company,
               and putting our signature under their message would misattribute it. */
            if ($fromUid === VESTRA_SUPPORT_UID) $mOpts['signature'] = vestra_support_signature(vestra_user_lang($recAcc));
            vestra_send_mail($recAcc['email'], $mSubj, $mBody, $fromEmail, $fromLabel, null, '', $mOpts);
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
            'shipped'   => '🚚 '.t('Order shipped'),
            'completed' => '✓ '.t('Order completed — payment released'),
            default     => '📦 '.t('Order placed'),
        },
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
    $time = '<div class="msgtime">'.htmlspecialchars(substr($m['at']??'',0,16)).'</div>';
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
    if ($kind === 'order') {
        [$cls, $label] = match($meta['status']??''){
            'shipped'   => ['ctr', '🚚 '.t('Order shipped')],
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

/* Display label for the OTHER party in a thread, from the point of view of $uid. */
function vestra_msg_counterpart_label(array $thread, string $uid): string {
    $otherUid = ($thread['buyer_uid']??'') === $uid ? ($thread['seller_uid']??'') : ($thread['buyer_uid']??'');
    if ($otherUid === VESTRA_SUPPORT_UID) return vestra_msg_label($otherUid);
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

