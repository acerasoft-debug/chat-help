<?php
/**
 * ChatHelp — Ana sayfa sarmalayıcı: site/index.html'i dinamik (önbelleksiz) sunar.
 * Host Europe / GoDaddy Managed WordPress: Cache-Control tek başına yetmez; Set-Cookie içeren yanıtlar
 * platform- ve kenar önbelleğine (Cloudflare) alınmaz → her yanıtta çerez yenilenir.
 */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Cookie');
header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
readfile(__DIR__.'/index.html');
