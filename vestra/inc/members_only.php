<?php
/** Members-only gate for file endpoints (line-sheets, CSV, PDF).
 *
 *  The operator's instruction is that nothing downloadable is available to a visitor who has
 *  not registered. These endpoints stream a file rather than render a page, so they cannot
 *  rely on the blur treatment used on /shop and /product — a blurred page still means the
 *  bytes left the server. They have to refuse outright, before a single byte is written.
 *
 *  Call this FIRST in any endpoint that emits a file. It starts the session itself, because
 *  these endpoints never include head.php and so $MEMBER is not set for them.
 */
function vestra_require_member(string $backPath): void {
    /* auth.php owns the session bootstrap (store + 90-day cookie + remember-me).
       A bare session_start() here read the shared /tmp store instead, so a member
       who was signed in looked signed out and got told to register. */
    require_once __DIR__.'/auth.php';

    /* The same three signals head.php uses to build $MEMBER, read straight from the session
       so this file needs nothing from the page-rendering code:
           $_SESSION['vadmin'] -> $IS_ADMIN
           $_SESSION['uid']    -> auth_user() !== null
           $_SESSION['member']
       Any registered account passes; full KYB approval is not required to download, only
       registration -- matching what /shop and /product gate on. */
    if (!empty($_SESSION['vadmin']) || !empty($_SESSION['uid']) || !empty($_SESSION['member'])) return;

    /* Nothing has been echoed yet, so headers are still ours to set. Send the visitor to the
       login page and bring them back to whatever they were trying to download. */
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: /login?back='.urlencode($backPath), true, 302);
    }
    echo 'Registration required.';
    exit;
}
