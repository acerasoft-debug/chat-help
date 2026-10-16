<?php
/* ChatHelp Studio ist nach /chat/studio.php umgezogen (alte URL bleibt gültig). */
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /chat/studio.php'.($q !== '' ? '?'.$q : ''), true, 302);
exit;
