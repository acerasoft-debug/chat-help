<?php
/**
 * ChatHelp — Seitenversion für HTML-Seiten (ohne Datenbank).
 * Ohne passendes ?v=<Dateistand> → 302 auf dieselbe URL mit v. Die Plattform cacht Weiterleitungen nicht,
 * daher kommt nach jedem Update garantiert die neue Seite, selbst wenn die alte URL noch im Cache liegt.
 */
/* Sicherheits-Header für PHP-Seiten (die Plattform unterstützt keine Header-Direktiven in .htaccess): HSTS, kein MIME-Sniffing, Referrer,
   Berechtigungen (Mikrofon/Standort nur eigene Seiten), Opener-Isolation. $frame=true zusätzlich X-Frame-Options (Studio, App) – Buchungs-/Kundenseiten bleiben einbettbar. */
function ch_sec_headers(bool $frame=false): void {
    if(headers_sent()) return;
    header('Strict-Transport-Security: max-age=31536000');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(self), geolocation=(self), payment=(), usb=(), browsing-topics=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    if($frame) header('X-Frame-Options: SAMEORIGIN');
}
function ch_page_version(string $file, array $extra=[]): void {
    ch_sec_headers();
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET' || headers_sent()) return;
    $v=substr(md5(implode('|',array_map(fn($f)=>(string)@filemtime($f),array_merge([$file],$extra)))),0,8);
    if(($_GET['v'] ?? '')===$v) return;
    $q=$_GET; $q['v']=$v;
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
    header('Location: '.strtok((string)($_SERVER['REQUEST_URI'] ?? '/'),'?').'?'.http_build_query($q),true,302); exit;
}
