<?php
/**
 * ChatHelp — Besucher-Locale für die Website: Land → Sprache + Währung (nie im Edge-Cache)
 * ==========================================================================================
 *   GET /chat/locale-api.php                → {country, lang, currency, symbol, rate, decimals, langs[], currencies[], prices{}, rounding{}, …}
 *   GET /chat/locale-api.php?lang=tr        → Sprache wählen  (setzt Cookie ch_lang, 1 Jahr)
 *   GET /chat/locale-api.php?cur=USD        → Währung wählen  (setzt Cookie ch_cur, 1 Jahr; RUB → EUR mit note)
 *   GET /chat/locale-api.php?cc=TR          → Vorschau als Besucher aus einem Land (setzt nichts)
 * Land: Cloudflare-Header CF-IPCountry; ohne Land: Accept-Language; Auswahl per Cookie hat Vorrang (Details: ch_visitor_locale in i18n.php).
 * Die Startseite ist statisch und bis zu 31 Tage am Edge gecacht – deshalb wird nichts Länderabhängiges ins HTML gerendert,
 * die Seite fragt diesen Endpunkt einmal je Sitzung (sessionStorage) und lokalisiert im Browser.
 * Absichtlich OHNE tokens-lib/db.php: der Endpunkt läuft bei jedem neuen Besucher und braucht keine Datenbank.
 * Kein Konflikt mit dem alten chat/geo.php auf dem Server (anderer Dateiname).
 */
require_once __DIR__.'/plans.php';   // CH_PLANS/CH_RUN_TIERS (Preistabelle) + i18n.php (Sprachen, Währungen, Kurse)

/* Gleiche Kopfzeilen wie th_nocache() (tokens-lib): weder Browser noch Cloudflare/Plattform dürfen die Antwort speichern */
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Cookie, CF-IPCountry, Accept-Language');
header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);   // Plattform-Cache (Host Europe/GoDaddy MWP) überspringt Antworten mit Set-Cookie
header('X-Robots-Tag: noindex'); header('X-Content-Type-Options: nosniff');

$m=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if($m!=='GET' && $m!=='HEAD'){ http_response_code(405); header('Allow: GET, HEAD'); header('Content-Type: application/json; charset=UTF-8'); echo '{"error":"method_not_allowed"}'; exit; }

$loc=ch_visitor_locale($_SERVER,$_COOKIE,$_GET);

/* Ausdrückliche Wahl (?lang= / ?cur=) als Cookie merken – dieselben Cookies setzt die Website beim Umschalten selbst */
$https=(($_SERVER['HTTPS'] ?? '')!=='' && ($_SERVER['HTTPS'] ?? '')!=='off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))==='https' || str_contains((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''),'https');
$ck=fn(string $k,string $v)=>header('Set-Cookie: '.$k.'='.$v.'; Path=/; Max-Age=31536000; SameSite=Lax'.($https ? '; Secure' : ''),false);
if($loc['lang_source']==='param') $ck('ch_lang',$loc['lang']);
if($loc['currency_source']==='param') $ck('ch_cur',(string)($loc['requested'] ?? $loc['currency']));

http_response_code(200); header('Content-Type: application/json; charset=UTF-8');
if($m==='HEAD') exit;
echo json_encode($loc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
