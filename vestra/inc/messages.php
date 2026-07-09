<?php
/**
 * VESTRA — buyer/seller direct messaging.
 * File-based threads in data/messages.json. One thread per (buyer, seller, listing) triple.
 * Off-platform contact info (email, IBAN) is detected and blocked before a message is stored —
 * all communication and payment must stay on VESTRA so escrow protection still applies.
 */
function vestra_msg_file(): string { return dirname(__DIR__).'/data/messages.json'; }

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

/* Returns 'email', 'iban', or null. Deliberately blunt — false positives just mean the sender
   edits the message and resends, which is a fine trade-off for keeping deals on-platform.
   The IBAN pattern matches directly against the original text (not a globally whitespace-
   stripped copy) so it follows real IBAN formatting — country+checksum glued together, then
   groups of 4 optionally separated by a space/hyphen — instead of merging unrelated adjacent
   words into a false positive, or losing a real IBAN's word boundary. */
function vestra_msg_flag_offplatform(string $text): ?string {
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) return 'email';
    if (preg_match('/\b[A-Z]{2}\d{2}(?:[ -]?[A-Z0-9]{4}){2,7}(?:[ -]?[A-Z0-9]{1,3})?\b/i', $text)) return 'iban';
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
        "A message containing ".($flag==='email'?'an email address':'an IBAN')." was blocked in buyer-seller chat.\n\n".
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
    $found = false;
    foreach ($threads as &$t) {
        if (($t['id']??'') === $id) {
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
                 '<div class="mo-terms">'.$qty.' × '.$unit.' — <b>'.$total.'</b> '.t('total').'</div>';
        if ($viewerRole === 'seller') {
            $body .= '<a class="mo-act" href="/seller?tab=offers">'.t('Respond in Offers tab →').'</a>';
        }
        return '<div class="msgoffer">'.$body.$time.'</div>';
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
    return '';
}

/* Mark a thread as read by $uid (called when they open it). */
function vestra_msg_mark_read(string $id, string $uid): void {
    $threads = vestra_msg_threads();
    foreach ($threads as &$t) {
        if (($t['id']??'') === $id) { $t['read'][$uid] = date('c'); break; }
    }
    unset($t);
    vestra_msg_save_threads($threads);
}
function vestra_msg_unread(array $thread, string $uid): bool {
    $readAt = $thread['read'][$uid] ?? null;
    if ($readAt === null) return !empty($thread['messages']);
    return strtotime($thread['last_at']??'1970-01-01') > strtotime($readAt);
}

/* Display label for the OTHER party in a thread, from the point of view of $uid. */
function vestra_msg_counterpart_label(array $thread, string $uid): string {
    $otherUid = ($thread['buyer_uid']??'') === $uid ? ($thread['seller_uid']??'') : ($thread['buyer_uid']??'');
    foreach (auth_accounts() as $a) {
        if (($a['id']??'') === $otherUid) return $a['company'] ?: ($a['name'] ?: t('Account'));
    }
    return t('Account');
}

