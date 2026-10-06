<?php
/**
 * VESTRA — the app's page-side pieces: boot data for /inc/app.js and the
 * "Notifications" card of the buyer and seller panels.
 *
 * Before this, a signed-in customer could turn notifications on in exactly one
 * place — the app box on the HOMEPAGE — and nowhere could they see whether
 * they were on, turn them off, or check that they arrive. The panel card is
 * the settings screen every app has.
 */
require_once __DIR__.'/push.php';

/**
 * <script> boot + app.js, once per page (tabbar.php calls it, so every page
 * with the app chrome gets it). Strings are the RECIPIENT's = the viewer's
 * language here, so t() is right in this one place.
 */
function vestra_app_boot(): string {
    if (defined('VESTRA_APP_BOOTED')) return '';
    define('VESTRA_APP_BOOTED', 1);
    $tt = function (string $s): string { return function_exists('t') ? t($s) : $s; };
    $u = (isset($GLOBALS['AUTH_USER']) && is_array($GLOBALS['AUTH_USER'])) ? $GLOBALS['AUTH_USER']
       : (function_exists('auth_user') ? auth_user() : null);
    $signed = is_array($u) && !empty($u['id']);
    $unread = 0;
    if ($signed) {
        if (isset($GLOBALS['MSG_UNREAD'])) $unread = (int)$GLOBALS['MSG_UNREAD'];
        else { require_once __DIR__.'/messages.php'; $unread = vestra_msg_unread_count((string)$u['id']); }
    }
    $keys = vestra_vapid_keys();
    $s = [
        'on'          => $tt('On for this device'),
        'off'         => $tt('Off on this device'),
        'denied'      => $tt('Blocked in your browser settings'),
        'denied_hint' => $tt('To allow them again, open this site’s settings in your browser (the icon next to the address) and set Notifications to Allow.'),
        'unsupported' => $tt('This browser cannot show notifications.'),
        'ios_title'   => $tt('iPhone / iPad: add to Home Screen first'),
        'ios'         => $tt('On iPhone and iPad, notifications work once VESTRA is on your Home Screen: tap Share → Add to Home Screen, open VESTRA from there, then turn notifications on.'),
        'signin'      => $tt('Sign in first to receive notifications.'),
        'sent'        => $tt('Test sent — it should arrive within a few seconds.'),
        'wait'        => $tt('Please wait a moment before sending another test.'),
        'error'       => $tt('Something went wrong — please try again.'),
        'on_hint'     => $tt('Done — you will be notified about orders, offers and messages on this device.'),
    ];
    /* The homepage adds its own box texts (index.php's $t) without a second boot. */
    if (!empty($GLOBALS['VESTRA_APP_STRINGS']) && is_array($GLOBALS['VESTRA_APP_STRINGS'])) {
        $s = array_merge($s, array_map('strval', $GLOBALS['VESTRA_APP_STRINGS']));
    }
    $boot = [
        'signedIn' => $signed,
        'stamped'  => !empty($_SESSION['push_dev']),
        'vapid'    => (string)($keys['publicKey'] ?? ''),
        'unread'   => $unread,
        's'        => $s,
    ];
    $v = @filemtime(__DIR__.'/app.js') ?: 1;
    $j = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script>window.VESTRA_BOOT='.$j.';</script>'."\n".'<script src="/inc/app.js?v='.$v.'" defer></script>'."\n";
}

/**
 * The Notifications card. Everything that depends on THIS device (supported?
 * permission? linked?) is painted by app.js; the server only knows the account.
 * Without JavaScript the card says so instead of showing dead buttons.
 */
function vestra_push_card(): string {
    $tt = function (string $s): string { return htmlspecialchars(function_exists('t') ? t($s) : $s); };
    $bell = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
          . '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg>';
    return '<div class="panelcard vpush" id="notifications" data-vpush-card>'
        . '<div class="vpush-hd"><span class="vpush-ic" aria-hidden="true">'.$bell.'</span>'
        . '<div class="vpush-tx"><h3>'.$tt('Notifications').'</h3>'
        . '<p class="hint">'.$tt('Orders, offers and messages the moment they happen — on this phone or computer.').'</p></div>'
        . '<span class="vpush-st" data-vpush-status data-state="loading" role="status" aria-live="polite">…</span></div>'
        . '<p class="vpush-note" data-vpush-note hidden></p>'
        . '<div class="vpush-btns">'
        . '<button type="button" class="btn btn-p btn-sm" data-vpush="on" hidden>'.$tt('Turn on').'</button>'
        . '<button type="button" class="btn btn-o btn-sm" data-vpush="test" hidden>'.$tt('Send a test').'</button>'
        . '<button type="button" class="btn btn-o btn-sm" data-vpush="off" hidden>'.$tt('Turn off').'</button>'
        . '<button type="button" class="btn btn-o btn-sm" data-vapp="install" hidden>'.$tt('Install the app').'</button>'
        . '</div>'
        . '<p class="hint vpush-foot">'.$tt('Each device is set up separately. Signing out turns notifications off on that device.').'</p>'
        . '<noscript><p class="hint">'.$tt('This browser cannot show notifications.').'</p></noscript>'
        . '</div>';
}

/**
 * One line on the panel overview, for a device that was never asked: most people
 * never open "My profile", so the card alone would be found by few. Shown by
 * app.js ONLY when this browser can do push, has not been asked yet and the
 * line was not dismissed — a nudge that also appears after "no" is nagging.
 */
function vestra_push_nudge(): string {
    $tt = function (string $s): string { return htmlspecialchars(function_exists('t') ? t($s) : $s); };
    return '<div class="vpush-nudge" data-vpush-nudge hidden>'
        . '<span class="vpush-nudge-tx">'.$tt('Get order, offer and message alerts on this device.').'</span>'
        . '<button type="button" class="btn btn-p btn-sm" data-vpush="on">'.$tt('Turn on').'</button>'
        . '<button type="button" class="vpush-x" data-vpush-x aria-label="'.$tt('Dismiss').'">×</button>'
        . '</div>';
}

