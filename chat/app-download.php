<?php
/**
 * ChatHelp Business — App herunterladen / installieren
 * ====================================================
 *   /chat/app-download.php            erkennt iPhone/iPad, Android oder Computer und zeigt den passenden Weg:
 *     iPhone:   App Store bzw. TestFlight (sobald hinterlegt) · sofort: Web-App über Safari → Teilen → „Zum Home-Bildschirm“
 *     Android:  Google Play bzw. APK (sobald hinterlegt) · sofort: Chrome → „App installieren“ (Ein-Tipp-Knopf, wenn der Browser es anbietet)
 *     Computer: QR-Code zum Öffnen auf dem Handy + Installation in Chrome/Edge
 *   ?p=ios|android|desktop            Plattform erzwingen (z. B. für Links aus E-Mails)
 * Store-Links: optionale Datei chat/app-links.json {"play":"…","apk":"…","appstore":"…","testflight":"…"} – leer = „Bald verfügbar“.
 * Keine Anmeldung, keine Kundendaten, keine externen Skripte (QR-Code wird im Browser erzeugt).
 */
require_once __DIR__.'/pagever.php';
ch_page_version(__FILE__);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');

$LINKS = ['play' => '', 'apk' => '', 'appstore' => '', 'testflight' => ''];
$lf = __DIR__.'/app-links.json';
if (is_file($lf)) { $j = json_decode((string)@file_get_contents($lf), true); if (is_array($j)) foreach ($LINKS as $k => $_) if (isset($j[$k]) && is_string($j[$k]) && preg_match('~^https://~', $j[$k])) $LINKS[$k] = $j[$k]; }
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$plat = preg_match('/iPhone|iPad|iPod/i', $ua) ? 'ios' : (preg_match('/Android/i', $ua) ? 'android' : 'desktop');
if (in_array($_GET['p'] ?? '', ['ios', 'android', 'desktop'], true)) $plat = $_GET['p'];
$inApp = (bool)preg_match('/FBAN|FBAV|Instagram|Line\/|LinkedInApp|Snapchat|TikTok|; wv\)|GSA\//i', $ua);
$host = preg_replace('/[^a-z0-9.\-:]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'chat-help.com')) ?: 'chat-help.com';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || $host === 'chat-help.com' ? 'https' : 'http';
$selfUrl = $scheme.'://'.$host.'/chat/app-download.php?ref=qr';
$bv = substr(md5((string)@filemtime(__DIR__.'/app-bridge.js')), 0, 8);
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ChatHelp Business App – für iPhone, Android und Computer</title>
<meta name="description" content="Die ChatHelp Business App: Bestellungen, Termine, Fahrten, Hotel und Rückrufe deiner KI-Agenten live auf dem Handy – mit Benachrichtigungen. Für iPhone, Android und Computer.">
<link rel="manifest" href="/chat/app.webmanifest">
<meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0b0d18" media="(prefers-color-scheme: dark)">
<meta name="color-scheme" content="light dark">
<link rel="apple-touch-icon" href="/chat/icons/apple-touch-icon.png"><link rel="icon" type="image/png" sizes="32x32" href="/chat/icons/icon-32.png">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="ChatHelp">
<meta property="og:title" content="ChatHelp Business App"><meta property="og:description" content="Aufträge deiner KI-Agenten live auf dem Handy – mit Benachrichtigungen."><meta property="og:image" content="https://chat-help.com/chat/icons/icon-512.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap">
<script src="/chat/app-bridge.js?v=<?= $bv ?>" defer></script>
<style>
:root{--bg:#f6f7fb;--sur:#fff;--sur2:#f1f2f7;--line:#e4e6ee;--line2:#d7dae6;--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--gold:#b8862b;--gold2:#8f6519;--goldl:#f6ecd3;--goldg:linear-gradient(135deg,#f3dfa6,#c9972f);--ok:#1a9e5a;--okl:#e4f5ea;--rose:#d9336b;--rosel:#fbe7ee;--blue:#2f6fde;
  --shadow:0 1px 2px rgba(15,18,34,.05),0 10px 30px rgba(15,18,34,.07);--shadow2:0 2px 6px rgba(15,18,34,.08),0 24px 60px rgba(15,18,34,.16);--ph:#0f1222;--scr:#fbfbfd;--scr2:#eef0f5}
@media (prefers-color-scheme:dark){:root{--bg:#0b0d18;--sur:#151829;--sur2:#1c2035;--line:#262b45;--line2:#323856;--ink:#f1f2f7;--ink2:#c3c6d6;--mute:#8d91a6;--gold:#e3c27a;--gold2:#ecd29a;--goldl:#2a2414;--ok:#3ccf85;--okl:#11291d;--rose:#f0668e;--rosel:#2e1420;--shadow:0 1px 2px rgba(0,0,0,.3),0 10px 30px rgba(0,0,0,.3);--shadow2:0 2px 6px rgba(0,0,0,.4),0 24px 60px rgba(0,0,0,.5);--ph:#000;--scr:#1a1d30;--scr2:#232741}}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--ink);font:15.5px/1.55 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{color:inherit}
.wrap{max-width:1040px;margin:0 auto;padding:0 calc(18px + env(safe-area-inset-right)) 0 calc(18px + env(safe-area-inset-left))}
.nav{display:flex;align-items:center;gap:10px;padding:calc(14px + env(safe-area-inset-top)) 0 6px}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;font:600 18px Fraunces,Georgia,serif}.brand span{color:var(--gold)}
.em{width:36px;height:36px;border-radius:11px;background:var(--goldg);display:grid;place-items:center;flex:0 0 auto}
.nav .sp{flex:1}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;padding:13px 20px;border-radius:15px;border:1px solid transparent;font:700 15.5px Inter,system-ui,sans-serif;text-decoration:none;cursor:pointer;background:var(--sur2);color:var(--ink);transition:transform .12s;max-width:100%;text-align:center}
.btn:active{transform:scale(.97)}
.btn.p{background:var(--ink);color:var(--bg)}.btn.g{background:var(--goldg);color:#1a1407;box-shadow:0 10px 26px rgba(184,134,43,.28)}.btn.o{background:transparent;border-color:var(--line2)}
.btn.s{min-height:42px;padding:9px 14px;font-size:14px;border-radius:12px}.btn[aria-disabled="true"]{opacity:.55;pointer-events:none}
.btn svg{width:20px;height:20px;flex:0 0 auto}
.hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:40px;align-items:center;padding:34px 0 18px}
.kick{display:inline-flex;gap:8px;align-items:center;padding:6px 12px;border-radius:999px;background:var(--goldl);color:var(--gold2);font:700 11.5px Inter;letter-spacing:.08em;text-transform:uppercase}
h1{font:600 clamp(32px,6vw,52px)/1.06 Fraunces,Georgia,serif;letter-spacing:-.02em;margin:14px 0 12px}
h1 em{font-style:normal;color:var(--gold)}
.lead{font-size:17px;color:var(--ink2);margin:0 0 22px;max-width:44ch}
.tabs{display:inline-flex;gap:4px;padding:4px;border-radius:15px;background:var(--sur2);border:1px solid var(--line);max-width:100%;overflow-x:auto}
.tabs button{border:0;background:none;min-height:42px;padding:8px 12px;flex:0 0 auto;border-radius:12px;font:700 14px Inter;color:var(--mute);cursor:pointer;white-space:nowrap;display:inline-flex;gap:7px;align-items:center}
.tabs button.on{background:var(--sur);color:var(--ink);box-shadow:0 1px 3px rgba(15,18,34,.12)}
.tabs svg{width:17px;height:17px}
.pane{display:none}.pane.on{display:block;animation:fi .25s ease}@keyframes fi{from{opacity:0;transform:translateY(6px)}}
.ctas{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 10px}
.store{display:inline-flex;align-items:center;gap:10px;min-height:56px;padding:9px 18px 9px 14px;border-radius:15px;background:#0f1222;color:#fff;text-decoration:none;border:1px solid #0f1222}
.store svg{width:26px;height:26px}.store small{display:block;font-size:11px;opacity:.75;line-height:1.1}.store b{display:block;font-size:17px;line-height:1.15}
.store.soon{background:transparent;color:var(--mute);border:1px dashed var(--line2);cursor:default}
@media (prefers-color-scheme:dark){.store{background:#fff;color:#0f1222;border-color:#fff}.store.soon{background:transparent;color:var(--mute);border-color:var(--line2)}}
.note{font-size:13.5px;color:var(--mute);margin:6px 0 0}
.alert{margin:14px 0 0;padding:12px 14px;border-radius:14px;background:var(--rosel);color:var(--ink);font-size:14px}
.alert b{color:var(--rose)}
.shot{position:relative;display:grid;place-items:center}
.glow{position:absolute;inset:10% 6%;background:radial-gradient(closest-side,rgba(201,151,47,.35),transparent);filter:blur(10px)}
.phone{position:relative;width:260px;max-width:100%;aspect-ratio:9/19;border-radius:40px;background:var(--ph);padding:10px;box-shadow:var(--shadow2)}
.phone .scr{position:relative;height:100%;border-radius:31px;overflow:hidden;background:var(--scr);color:#0f1222}
@media (prefers-color-scheme:dark){.phone .scr{color:var(--ink)}}
.phone .isl{position:absolute;top:8px;left:50%;width:78px;height:22px;margin-left:-39px;border-radius:12px;background:#000;z-index:3}
.sec{padding:34px 0 8px}
.sec h2{font:600 clamp(24px,4vw,32px)/1.15 Fraunces,Georgia,serif;margin:0 0 6px;letter-spacing:-.01em}
.sec .sub{color:var(--ink2);margin:0 0 20px}
.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.step{background:var(--sur);border:1px solid var(--line);border-radius:22px;padding:14px 14px 16px;box-shadow:var(--shadow);min-width:0}
.step .ill{height:270px;border-radius:16px;background:var(--scr2);display:grid;place-items:center;overflow:hidden;margin-bottom:12px;position:relative}
.step h3{font:700 15px Inter;margin:0 0 4px;display:flex;gap:9px;align-items:center}
.step h3 i{font-style:normal;width:26px;height:26px;border-radius:50%;background:var(--ink);color:var(--bg);display:grid;place-items:center;font-size:13px;flex:0 0 auto}
.step p{margin:0;font-size:13.5px;color:var(--ink2)}
.mini{width:150px;height:250px;border-radius:24px;background:#0f1222;padding:6px;box-shadow:0 10px 30px rgba(15,18,34,.25);position:relative}
.mini .s{height:100%;border-radius:19px;background:#fff;overflow:hidden;position:relative;color:#0f1222;font:600 9px/1.3 Inter,system-ui,sans-serif}
.hl{box-shadow:0 0 0 2.5px #c9972f,0 0 0 7px rgba(201,151,47,.28);border-radius:8px;animation:hp 1.6s ease-in-out infinite}
@keyframes hp{50%{box-shadow:0 0 0 2.5px #c9972f,0 0 0 11px rgba(201,151,47,.12)}}
.feat{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.fc{background:var(--sur);border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:var(--shadow)}
.fc .ti{width:42px;height:42px;border-radius:14px;background:var(--goldl);color:var(--gold2);display:grid;place-items:center;margin-bottom:10px}
.fc .ti svg{width:22px;height:22px}
.fc b{display:block;font-size:15.5px;margin-bottom:3px}.fc span{font-size:14px;color:var(--ink2)}
.qrbox{display:flex;gap:24px;align-items:center;background:var(--sur);border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:var(--shadow);flex-wrap:wrap}
.qr{width:200px;height:200px;background:#fff;border-radius:18px;padding:10px;flex:0 0 auto;box-shadow:inset 0 0 0 1px #e4e6ee}
.qr svg{width:100%;height:100%;display:block}
.qrbox .tx{flex:1;min-width:220px}
details{background:var(--sur);border:1px solid var(--line);border-radius:16px;padding:0 16px;margin-bottom:10px}
summary{cursor:pointer;font-weight:700;min-height:54px;display:flex;align-items:center;list-style:none}
summary::-webkit-details-marker{display:none}
summary::after{content:"+";margin-left:auto;font-size:20px;color:var(--mute)}
details[open] summary::after{content:"–"}
details p{margin:0 0 14px;color:var(--ink2);font-size:14.5px}
footer{padding:30px 0 calc(30px + env(safe-area-inset-bottom));color:var(--mute);font-size:13px;text-align:center}
footer a{color:var(--ink2)}
@media (max-width:900px){.hero{grid-template-columns:minmax(0,1fr);gap:18px;padding-top:18px}.shot{order:-1}.phone{width:210px}.steps{grid-template-columns:repeat(2,minmax(0,1fr))}.feat{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:560px){.steps{grid-template-columns:1fr}.feat{grid-template-columns:1fr}.step .ill{height:262px}.nav .btn{display:none}.qrbox{padding:18px}.qr{width:180px;height:180px}}
@media (max-width:400px){.tabs{display:flex;width:100%}.tabs button{flex:1 1 auto;padding:8px 6px;font-size:13px;justify-content:center}.tabs svg{display:none}}
@media (prefers-reduced-motion:reduce){*{animation:none!important}}
</style>
</head>
<body data-plat="<?= $h($plat) ?>">
<div class="wrap">
  <nav class="nav">
    <a class="brand" href="/" aria-label="ChatHelp Startseite"><span class="em" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span>ChatHelp <span>Business</span></a>
    <span class="sp"></span>
    <a class="btn o s" href="/chat/app.php">Im Browser öffnen</a>
  </nav>

  <header class="hero">
    <div>
      <span class="kick">App für deinen Betrieb</span>
      <h1>Jeder Auftrag. <em>Sofort</em> auf dem Handy.</h1>
      <p class="lead">Bestellungen, Termine, Taxifahrten, Hotelanfragen und Rückrufe deiner KI-Agenten – live, mit Benachrichtigung und Ton. Ein Tipp, und der Auftrag ist bestätigt.</p>
      <div class="tabs" role="tablist" aria-label="Gerät wählen">
        <button type="button" role="tab" data-p="ios"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg>iPhone & iPad</button>
        <button type="button" role="tab" data-p="android"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.6 9.5l1.6-2.8a.4.4 0 0 0-.7-.4l-1.7 2.9A10 10 0 0 0 12 8.2c-1.7 0-3.3.4-4.8 1L5.5 6.3a.4.4 0 1 0-.7.4l1.6 2.8A9.3 9.3 0 0 0 1.5 17h21a9.3 9.3 0 0 0-4.9-7.5zM7 14.3a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm10 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>Android</button>
        <button type="button" role="tab" data-p="desktop"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M9 20h6M12 16.5V20"/></svg>Computer</button>
      </div>

      <!-- iPhone -->
      <div class="pane" data-pane="ios">
        <div class="ctas">
          <?php if ($LINKS['appstore']): ?><a class="store" id="lnkAppStore" href="<?= $h($LINKS['appstore']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg><span><small>Laden im</small><b>App Store</b></span></a>
          <?php elseif ($LINKS['testflight']): ?><a class="store" id="lnkTestflight" href="<?= $h($LINKS['testflight']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l2.2 6.8H21l-5.5 4 2.1 6.7L12 16.4l-5.6 4.1 2.1-6.7-5.5-4h6.8z"/></svg><span><small>Vorab testen über</small><b>TestFlight</b></span></a>
          <?php else: ?><span class="store soon" id="lnkAppStore" aria-disabled="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg><span><small>Bald im</small><b>App Store</b></span></span><?php endif; ?>
          <a class="btn g" href="#ios-steps" id="iosGuideBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M7 11H5.5v9.5h13V11H17"/></svg>Jetzt zum Home-Bildschirm</a>
        </div>
        <p class="note">Sofort startklar ohne App Store: In <b>Safari</b> öffnen, „Teilen“ → „Zum Home-Bildschirm“. Benachrichtigungen ab iOS 16.4.</p>
        <?php if ($inApp && $plat === 'ios'): ?><div class="alert" id="inAppHint"><b>Bitte in Safari öffnen:</b> Du bist in einem App-Browser (z. B. Instagram oder Facebook). Tippe auf „…“ bzw. das Kompass-Symbol und wähle „In Safari öffnen“.</div><?php endif; ?>
        <div class="alert" id="iosNotSafari" hidden><b>Bitte Safari verwenden:</b> Nur Safari kann ChatHelp auf dem iPhone mit Benachrichtigungen zum Home-Bildschirm hinzufügen. Link kopieren und in Safari öffnen.</div>
      </div>

      <!-- Android -->
      <div class="pane" data-pane="android">
        <div class="ctas">
          <?php if ($LINKS['play']): ?><a class="store" id="lnkPlay" href="<?= $h($LINKS['play']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 2.8v18.4c0 .5.5.8.9.6l10.6-9.2L4.9 2.2c-.4-.2-.9.1-.9.6zM16.6 13.6l2.9 1.7c.7.4.7 1.4 0 1.8L6.7 23l9.9-9.4zM16.6 10.4L6.7 1l12.8 6.9c.7.4.7 1.4 0 1.8l-2.9 1.7z"/></svg><span><small>Jetzt bei</small><b>Google Play</b></span></a>
          <?php else: ?><span class="store soon" id="lnkPlay" aria-disabled="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 2.8v18.4c0 .5.5.8.9.6l10.6-9.2L4.9 2.2c-.4-.2-.9.1-.9.6zM16.6 13.6l2.9 1.7c.7.4.7 1.4 0 1.8L6.7 23l9.9-9.4zM16.6 10.4L6.7 1l12.8 6.9c.7.4.7 1.4 0 1.8l-2.9 1.7z"/></svg><span><small>Bald bei</small><b>Google Play</b></span></span><?php endif; ?>
          <button class="btn g" type="button" id="installBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5"/><path d="M5 19.5h14"/></svg>App installieren</button>
          <?php if ($LINKS['apk']): ?><a class="btn o" id="lnkApk" href="<?= $h($LINKS['apk']) ?>" rel="noopener">APK herunterladen</a><?php endif; ?>
        </div>
        <p class="note" id="installNote">Ein Tipp installiert ChatHelp direkt aus Chrome – mit eigenem Symbol und Benachrichtigungen. Kein Store nötig.</p>
        <?php if ($LINKS['apk']): ?><p class="note">APK: Beim ersten Mal fragt Android, ob dein Browser Apps installieren darf → „Zulassen“, dann „Installieren“.</p><?php endif; ?>
        <?php if ($inApp && $plat === 'android'): ?><div class="alert"><b>Bitte in Chrome öffnen:</b> Tippe oben rechts auf ⋮ und wähle „In Chrome öffnen“ bzw. „Im Browser öffnen“.</div><?php endif; ?>
      </div>

      <!-- Computer -->
      <div class="pane" data-pane="desktop">
        <div class="ctas"><a class="btn p" href="/chat/app.php">App im Browser öffnen</a><button class="btn o" type="button" id="installBtnD">Als App installieren</button></div>
        <p class="note">Am schnellsten aufs Handy: QR-Code unten mit der Kamera scannen.</p>
      </div>
    </div>

    <div class="shot" aria-hidden="true"><div class="glow"></div>
      <div class="phone"><div class="isl"></div><div class="scr">
        <div style="padding:40px 14px 10px;background:linear-gradient(#fff,#fbfbfd)"><div style="font:600 22px Fraunces,Georgia,serif;color:#0f1222">Heute</div><div style="font-size:10.5px;color:#6b7083">Guten Abend · Pizzeria Napoli</div></div>
        <div style="margin:4px 12px 8px;padding:10px;border-radius:14px;background:linear-gradient(135deg,#fffaf0,#f6ecd3);border:1px solid #ecdcb2;font-size:10px;color:#2a2010"><b style="font:600 12px Fraunces,Georgia,serif;display:block">Keinen Auftrag verpassen</b>Neue Bestellungen kommen sofort mit Ton.</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 12px 8px">
          <div style="background:#fff;border:1px solid #e4e6ee;border-radius:12px;padding:8px"><div style="font:600 20px Fraunces,Georgia,serif;color:#8f6519">4</div><div style="font-size:9px;color:#6b7083">offene Bestellungen</div></div>
          <div style="background:#fff;border:1px solid #e4e6ee;border-radius:12px;padding:8px"><div style="font:600 20px Fraunces,Georgia,serif;color:#0f1222">6</div><div style="font-size:9px;color:#6b7083">Termine heute</div></div></div>
        <?php foreach ([['Lieferung · 26,50 €','2× Margherita, 1× Cola','Neu','#f6ecd3','#8f6519'],['Sofort · Hauptbahnhof','→ Flughafen · 1 Pers.','Eingegangen','#e6eefc','#2f6fde'],['14:30 · Färben & Schnitt','Mia Wagner','Heute','#ece9fb','#6d5bd0']] as [$t,$s,$p,$bg,$fg]): ?>
        <div style="margin:0 12px 6px;background:#fff;border:1px solid #e4e6ee;border-radius:12px;padding:8px;display:flex;gap:7px"><span style="width:24px;height:24px;border-radius:8px;background:<?= $bg ?>;flex:0 0 auto"></span><span style="min-width:0"><b style="display:block;font-size:10.5px;color:#0f1222"><?= $h($t) ?></b><span style="font-size:9px;color:#3b4055"><?= $h($s) ?></span><br><span style="display:inline-block;margin-top:3px;padding:1px 6px;border-radius:9px;background:<?= $bg ?>;color:<?= $fg ?>;font-size:8.5px;font-weight:700"><?= $h($p) ?></span></span></div>
        <?php endforeach; ?>
        <div style="position:absolute;left:0;right:0;bottom:0;height:46px;background:rgba(255,255,255,.95);border-top:1px solid #e4e6ee;display:flex;justify-content:space-around;align-items:center;font-size:8.5px;color:#6b7083"><span style="color:#0f1222;font-weight:700">Heute</span><span>Aufträge</span><span>Kunden</span><span>Meldungen</span><span>Mehr</span></div>
        <div style="position:absolute;left:8px;right:8px;top:36px;background:rgba(255,255,255,.97);border-radius:14px;padding:8px 10px;box-shadow:0 8px 24px rgba(15,18,34,.22);display:flex;gap:8px;align-items:center;animation:drop 4s ease-in-out infinite"><span style="width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,#f3dfa6,#c9972f);flex:0 0 auto"></span><span style="font-size:9px;color:#3b4055;min-width:0"><b style="color:#0f1222;display:block;font-size:9.5px">Neue Bestellung A7K2Q9 · 18,50 €</b>Lieferung · 2× Pizza Margherita</span></div>
      </div></div>
    </div>
  </header>
  <style>@keyframes drop{0%,12%{transform:translateY(-130%);opacity:0}20%,80%{transform:none;opacity:1}90%,100%{transform:translateY(-130%);opacity:0}}</style>

  <!-- ===== Anleitung iPhone ===== -->
  <section class="sec pane" data-pane="ios" id="ios-steps">
    <h2>In 20 Sekunden auf dem iPhone</h2>
    <p class="sub">Funktioniert mit <b>Safari</b> ab iOS 16.4 – danach kommen Benachrichtigungen wie bei jeder App.</p>
    <div class="steps">
      <div class="step"><div class="ill"><div class="mini"><div class="s"><div style="padding:22px 10px 6px;font:600 13px Fraunces,Georgia,serif">ChatHelp</div><div style="margin:0 10px;height:60px;border-radius:8px;background:#f1f2f7"></div><div style="margin:6px 10px;height:40px;border-radius:8px;background:#f1f2f7"></div>
        <div style="position:absolute;left:0;right:0;bottom:0;height:52px;background:#f7f7f9;border-top:1px solid #e4e6ee"><div style="margin:6px 8px;height:16px;border-radius:6px;background:#e9e9ee;font-size:7.5px;color:#6b7083;display:grid;place-items:center">chat-help.com</div>
        <div style="display:flex;justify-content:space-around;align-items:center;color:#2f6fde;font-size:11px;padding:0 4px"><span>‹</span><span>›</span><span class="hl" style="display:grid;place-items:center;width:20px;height:18px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#2f6fde" stroke-width="2.4" stroke-linecap="round"><path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M7 11H5.5v9.5h13V11H17"/></svg></span><span>▢</span><span>⧉</span></div></div></div></div></div>
        <h3><i>1</i>„Teilen“ tippen</h3><p>Unten in Safari das Quadrat mit dem Pfeil nach oben antippen.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#e9e9ee"><div style="position:absolute;left:0;right:0;bottom:0;top:46px;background:#f2f2f7;border-radius:12px 12px 0 0;padding:8px">
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px"><span style="width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span><span><b style="display:block;font-size:8.5px">ChatHelp Business</b><span style="font-size:7px;color:#6b7083">chat-help.com</span></span></div>
        <div style="background:#fff;border-radius:8px;overflow:hidden"><div style="padding:6px 8px;border-bottom:1px solid #eee">Kopieren</div><div style="padding:6px 8px;border-bottom:1px solid #eee">Zur Leseliste</div><div style="padding:6px 8px;border-bottom:1px solid #eee">Lesezeichen</div>
        <div class="hl" style="padding:6px 8px;display:flex;justify-content:space-between;align-items:center;gap:4px;background:#fff;white-space:nowrap;font-size:8px">Zum Home-Bildschirm <span style="border:1.4px solid #0f1222;border-radius:3px;width:11px;height:11px;display:grid;place-items:center;font-size:9px;line-height:1">+</span></div></div></div></div></div></div>
        <h3><i>2</i>„Zum Home-Bildschirm“</h3><p>In der Liste etwas nach unten wischen und den Eintrag wählen.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#f2f2f7"><div style="display:flex;justify-content:space-between;align-items:center;gap:4px;padding:24px 7px 8px;font-size:7.5px;white-space:nowrap"><span style="color:#2f6fde">Abbrechen</span><b style="font-size:7px">Home-Bildschirm</b><span class="hl" style="color:#2f6fde;font-weight:800;padding:1px 3px">Hinzufügen</span></div>
        <div style="margin:4px 8px;background:#fff;border-radius:10px;padding:8px;display:flex;gap:8px;align-items:center"><span style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span><span><b style="display:block;font-size:10px">ChatHelp</b><span style="font-size:7.5px;color:#6b7083">chat-help.com</span></span></div>
        <div style="margin:10px 10px;font-size:7.5px;color:#6b7083;font-weight:500">Ein Symbol wird zum Home-Bildschirm hinzugefügt, damit du schnell auf die Website zugreifen kannst.</div></div></div></div>
        <h3><i>3</i>„Hinzufügen“</h3><p>Oben rechts bestätigen – der Name „ChatHelp“ ist schon eingetragen.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:linear-gradient(160deg,#2b2f4a,#0f1222)"><div style="margin:20px 6px 0;background:rgba(255,255,255,.92);border-radius:10px;padding:6px 7px;display:flex;gap:6px;align-items:center;box-shadow:0 6px 16px rgba(0,0,0,.3)"><span style="width:16px;height:16px;border-radius:5px;background:linear-gradient(135deg,#f3dfa6,#c9972f);flex:0 0 auto"></span><span style="font-size:7px;color:#3b4055;min-width:0"><b style="display:block;color:#0f1222;font-size:7.5px">Neue Bestellung · 18,50 €</b>2× Pizza Margherita</span></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:14px 10px">
        <?php for ($i = 0; $i < 11; $i++): ?><span style="aspect-ratio:1;border-radius:7px;background:rgba(255,255,255,<?= $i % 3 ? '.18' : '.28' ?>)"></span><?php endfor; ?>
        <span class="hl" style="aspect-ratio:1;border-radius:7px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span></div></div></div></div>
        <h3><i>4</i>Öffnen & aktivieren</h3><p>ChatHelp über das neue Symbol öffnen, anmelden und „Benachrichtigungen aktivieren“ tippen.</p></div>
    </div>
  </section>

  <!-- ===== Anleitung Android ===== -->
  <section class="sec pane" data-pane="android" id="android-steps">
    <h2>So geht’s auf Android</h2>
    <p class="sub">Mit Chrome (oder Edge, Samsung Internet). Falls oben kein Knopf „App installieren“ erscheint:</p>
    <div class="steps">
      <div class="step"><div class="ill"><div class="mini"><div class="s"><div style="display:flex;align-items:center;gap:5px;padding:20px 6px 6px;background:#f7f7f9;border-bottom:1px solid #e4e6ee"><span style="flex:1;height:16px;border-radius:9px;background:#e9e9ee;font-size:7px;color:#6b7083;display:grid;place-items:center">chat-help.com/chat/app.php</span><span class="hl" style="width:14px;height:18px;display:grid;place-items:center;font-size:12px;line-height:1">⋮</span></div>
        <div style="padding:8px 10px;font:600 13px Fraunces,Georgia,serif">Heute</div><div style="margin:0 10px;height:50px;border-radius:8px;background:#f1f2f7"></div><div style="margin:6px 10px;height:50px;border-radius:8px;background:#f1f2f7"></div></div></div></div>
        <h3><i>1</i>Menü ⋮ öffnen</h3><p>Oben rechts in Chrome auf die drei Punkte tippen.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#f1f2f7"><div style="position:absolute;right:6px;top:22px;width:108px;background:#fff;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.18);padding:4px 0;font-weight:500">
        <div style="padding:5px 8px">Neuer Tab</div><div style="padding:5px 8px">Verlauf</div><div style="padding:5px 8px">Downloads</div><div style="padding:5px 8px">Lesezeichen</div><div class="hl" style="margin:2px 4px;padding:5px 6px;font-weight:700">App installieren</div><div style="padding:5px 8px">Teilen …</div><div style="padding:5px 8px">Einstellungen</div></div></div></div></div>
        <h3><i>2</i>„App installieren“</h3><p>Bei älteren Versionen heißt es „Zum Startbildschirm hinzufügen“.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:rgba(15,18,34,.45)"><div style="position:absolute;left:8px;right:8px;top:70px;background:#fff;border-radius:14px;padding:10px"><b style="display:block;font-size:10px;margin-bottom:6px">App installieren?</b>
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:10px"><span style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span><span><b style="display:block;font-size:8.5px">ChatHelp Business</b><span style="font-size:7px;color:#6b7083">chat-help.com</span></span></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;font-size:8.5px;color:#2f6fde;font-weight:700"><span>Abbrechen</span><span class="hl" style="padding:1px 4px">Installieren</span></div></div></div></div></div>
        <h3><i>3</i>„Installieren“</h3><p>Bestätigen – ChatHelp landet als App auf dem Startbildschirm.</p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:linear-gradient(160deg,#2b2f4a,#0f1222)"><div style="margin:18px 6px 0;background:#fff;border-radius:12px;padding:7px;box-shadow:0 6px 16px rgba(0,0,0,.3)"><div style="display:flex;gap:5px;align-items:center;font-size:7px;color:#6b7083;margin-bottom:3px"><span style="width:10px;height:10px;border-radius:50%;background:#b8862b"></span>ChatHelp · jetzt</div><b style="display:block;font-size:8px">Neue Taxifahrt · sofort</b><span style="font-size:7.5px;color:#3b4055">Hauptbahnhof → Flughafen</span><div style="display:flex;gap:10px;margin-top:5px;font-size:7.5px;color:#2f6fde;font-weight:700"><span class="hl" style="padding:1px 4px">Öffnen</span></div></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:14px 10px"><?php for ($i = 0; $i < 7; $i++): ?><span style="aspect-ratio:1;border-radius:50%;background:rgba(255,255,255,.2)"></span><?php endfor; ?><span style="aspect-ratio:1;border-radius:50%;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span></div></div></div></div>
        <h3><i>4</i>Öffnen & aktivieren</h3><p>App öffnen, anmelden und „Benachrichtigungen aktivieren“ tippen – fertig.</p></div>
    </div>
  </section>

  <!-- ===== Computer: QR ===== -->
  <section class="sec" id="qr-sec">
    <h2 data-only="desktop">Auf dem Handy öffnen</h2><h2 data-only="mobile">Auch auf anderen Geräten</h2>
    <p class="sub">Kamera-App öffnen, QR-Code scannen, Link antippen – die richtige Anleitung erscheint automatisch.</p>
    <div class="qrbox">
      <div class="qr" id="qr" role="img" aria-label="QR-Code zu <?= $h($selfUrl) ?>" data-url="<?= $h($selfUrl) ?>"></div>
      <div class="tx"><b style="font-size:17px">Scannen mit iPhone oder Android</b><p class="note" style="margin:6px 0 12px">Oder Link selbst senden: <a href="<?= $h(strtok($selfUrl, '?')) ?>" id="selfLink"><?= $h(preg_replace('~^https?://~', '', strtok($selfUrl, '?'))) ?></a></p>
        <div class="ctas" style="margin:0"><button class="btn o s" type="button" id="copyLink">Link kopieren</button><a class="btn o s" href="mailto:?subject=ChatHelp%20Business%20App&amp;body=<?= rawurlencode('Hier die ChatHelp Business App: '.strtok($selfUrl, '?')) ?>">Per E-Mail senden</a></div></div>
    </div>
  </section>

  <section class="sec">
    <h2>Was die App kann</h2>
    <p class="sub">Alles, was deine Agenten annehmen – an einem Ort, für unterwegs gebaut.</p>
    <div class="feat">
      <?php foreach ([['bell','Benachrichtigungen mit Ton','Neue Bestellungen, Fahrten und Termine kommen sofort – auch bei geschlossener App. Ruhezeiten inklusive.'],
        ['bolt','Ein Tipp statt Telefonat','Bestellung bestätigen, Fahrer zuweisen, Gästewunsch erledigen – direkt aus der Liste.'],
        ['users','Kunden über alle Kanäle','Telefon, WhatsApp, Website: ein Verlauf pro Kunde, Antwort per WhatsApp aus der App.'],
        ['wifi','Auch bei schlechtem Netz','Letzter Stand bleibt sichtbar, die App startet offline und lädt nach, sobald das Netz zurück ist.'],
        ['moon','Hell & dunkel','Passt sich deinem Handy an – angenehm in der Küche, an der Rezeption und nachts im Taxi.'],
        ['shield','DSGVO & EU-Server','Gleiche Anmeldung wie im Studio, Daten bleiben in der EU, kein Passwort nötig.']] as [$i, $t, $s]): ?>
      <div class="fc"><div class="ti"><?= [
        'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 16v-4.8a6 6 0 1 1 12 0V16l1.6 2.2H4.4z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/></svg>',
        'bolt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M13 3 5 13.5h6L10 21l8-10.5h-6z"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3.4"/><path d="M2.8 20a6.3 6.3 0 0 1 12.4 0M15.5 4.8a3.4 3.4 0 0 1 0 6.4M17.8 14.2A6.3 6.3 0 0 1 21.2 20"/></svg>',
        'wifi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/></svg>',
        'moon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.1 7.5 9.5 4.3-1.4 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg>'][$i] ?></div><b><?= $h($t) ?></b><span><?= $h($s) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sec">
    <h2>Häufige Fragen</h2>
    <details><summary>Was kostet die App?</summary><p>Nichts extra. Die App gehört zu deinem ChatHelp-Konto – du meldest dich mit derselben E-Mail an wie im Studio.</p></details>
    <details><summary>Web-App oder App aus dem Store – was ist der Unterschied?</summary><p>Beide zeigen dieselben Aufträge und schicken Benachrichtigungen. Die Web-App (Home-Bildschirm) ist sofort ohne Store verfügbar und aktualisiert sich selbst. Die Store-App startet etwas schneller und nutzt die Benachrichtigungs-Kanäle von Android bzw. iOS direkt.</p></details>
    <details><summary>Warum kommen auf dem iPhone keine Benachrichtigungen?</summary><p>Apple erlaubt sie nur für Web-Apps auf dem Home-Bildschirm (ab iOS 16.4) und nur, wenn du sie in der App erlaubst. Prüfe außerdem: Einstellungen → Mitteilungen → ChatHelp → „Mitteilungen erlauben“ sowie den Fokus-Modus.</p></details>
    <details><summary>Können mehrere Mitarbeitende die App nutzen?</summary><p>Ja. Jedes Gerät meldet sich einzeln für Benachrichtigungen an; in der App unter „Meldungen“ siehst du alle Geräte. Fahrer und Küchen-Tablets nutzen ihre eigenen Links aus dem Studio.</p></details>
    <details><summary>Was passiert ohne Internet?</summary><p>Die App öffnet sich trotzdem und zeigt den letzten Stand. Deine Agenten arbeiten weiter – sobald das Netz zurück ist, kommen alle neuen Aufträge an.</p></details>
  </section>

  <footer>ChatHelp Business · <a href="/chat/app.php">App öffnen</a> · <a href="/chat/studio.php">Studio</a> · <a href="/impressum.html">Impressum</a> · DSGVO · EU-Server</footer>
</div>

<script>
/* ---------- QR-Code (Byte-Modus, Fehlerkorrektur M, Version 1–10) – eigenständig, ohne externe Bibliothek ---------- */
window.chQR=(function(){
  const ECC=[10,16,26,18,24,16,18,22,22,26], BLK=[1,1,1,2,2,4,4,4,5,5], RAW=[26,44,70,100,134,172,196,242,292,346];
  const ALIGN=[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50]];
  const mul=(x,y)=>{let z=0;for(let i=7;i>=0;i--){z=(z<<1)^((z>>>7)*0x11D);z^=((y>>>i)&1)*x;}return z&255;};
  function divisor(deg){const r=new Array(deg-1).fill(0);r.push(1);let root=1;for(let i=0;i<deg;i++){for(let j=0;j<r.length;j++){r[j]=mul(r[j],root);if(j+1<r.length)r[j]^=r[j+1];}root=mul(root,2);}return r;}
  function remainder(data,div){const r=div.map(()=>0);for(const b of data){const f=b^r.shift();r.push(0);div.forEach((c,i)=>{r[i]^=mul(c,f);});}return r;}
  function encode(text){
    const bytes=Array.from(new TextEncoder().encode(text));let ver=0;
    for(let v=1;v<=10;v++){const cap=RAW[v-1]-ECC[v-1]*BLK[v-1];const need=Math.ceil((4+(v<10?8:16)+bytes.length*8)/8);if(need<=cap){ver=v;break;}}
    if(!ver)throw new Error('Text zu lang für QR');
    const dataCap=RAW[ver-1]-ECC[ver-1]*BLK[ver-1];const bits=[];const push=(val,n)=>{for(let i=n-1;i>=0;i--)bits.push((val>>>i)&1);};
    push(4,4);push(bytes.length,ver<10?8:16);bytes.forEach(b=>push(b,8));push(0,Math.min(4,dataCap*8-bits.length));while(bits.length%8)bits.push(0);
    const cw=[];for(let i=0;i<bits.length;i+=8)cw.push(parseInt(bits.slice(i,i+8).join(''),2));for(let p=0xEC;cw.length<dataCap;p^=0xEC^0x11)cw.push(p);
    /* Blöcke + Fehlerkorrektur, verschränkt */
    const nb=BLK[ver-1],ecl=ECC[ver-1],raw=RAW[ver-1],nShort=nb-raw%nb,shortLen=Math.floor(raw/nb),div=divisor(ecl),blocks=[];
    for(let i=0,k=0;i<nb;i++){const dat=cw.slice(k,k+shortLen-ecl+(i<nShort?0:1));k+=dat.length;const ec=remainder(dat,div);if(i<nShort)dat.push(0);blocks.push(dat.concat(ec));}
    const all=[];for(let i=0;i<blocks[0].length;i++)blocks.forEach((b,j)=>{if(i!==shortLen-ecl||j>=nShort)all.push(b[i]);});
    /* Matrix */
    const size=ver*4+17,M=[...Array(size)].map(()=>Array(size).fill(false)),F=[...Array(size)].map(()=>Array(size).fill(false));
    const set=(x,y,d)=>{M[y][x]=d;F[y][x]=true;};
    for(let i=0;i<size;i++){set(6,i,i%2===0);set(i,6,i%2===0);}
    const finder=(cx,cy)=>{for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const x=cx+dx,y=cy+dy;if(x<0||y<0||x>=size||y>=size)continue;const d=Math.max(Math.abs(dx),Math.abs(dy));set(x,y,d!==2&&d!==4);}};
    finder(3,3);finder(size-4,3);finder(3,size-4);
    const al=ALIGN[ver-1];al.forEach((a,i)=>al.forEach((b,j)=>{if((i===0&&j===0)||(i===0&&j===al.length-1)||(i===al.length-1&&j===0))return;for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)set(a+dx,b+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1);}));
    const format=mask=>{const data=(0<<3)|mask;let rem=data;for(let i=0;i<10;i++)rem=(rem<<1)^((rem>>>9)*0x537);const b=((data<<10)|rem)^0x5412;const g=i=>((b>>>i)&1)===1;
      for(let i=0;i<=5;i++)set(8,i,g(i));set(8,7,g(6));set(8,8,g(7));set(7,8,g(8));for(let i=9;i<15;i++)set(14-i,8,g(i));
      for(let i=0;i<8;i++)set(size-1-i,8,g(i));for(let i=8;i<15;i++)set(8,size-15+i,g(i));set(8,size-8,true);};
    format(0);
    if(ver>=7){let rem=ver;for(let i=0;i<12;i++)rem=(rem<<1)^((rem>>>11)*0x1F25);const b=(ver<<12)|rem;for(let i=0;i<18;i++){const c=((b>>>i)&1)===1,a=size-11+i%3,bb=Math.floor(i/3);set(a,bb,c);set(bb,a,c);}}
    let i=0;for(let right=size-1;right>=1;right-=2){if(right===6)right=5;for(let v=0;v<size;v++)for(let j=0;j<2;j++){const x=right-j,up=((right+1)&2)===0,y=up?size-1-v:v;if(!F[y][x]&&i<all.length*8){M[y][x]=((all[i>>>3]>>>(7-(i&7)))&1)===1;i++;}}}
    const MF=[(x,y)=>(x+y)%2===0,(x,y)=>y%2===0,(x,y)=>x%3===0,(x,y)=>(x+y)%3===0,(x,y)=>(Math.floor(x/3)+Math.floor(y/2))%2===0,(x,y)=>x*y%2+x*y%3===0,(x,y)=>(x*y%2+x*y%3)%2===0,(x,y)=>((x+y)%2+x*y%3)%2===0];
    const apply=m=>{for(let y=0;y<size;y++)for(let x=0;x<size;x++)if(!F[y][x]&&MF[m](x,y))M[y][x]=!M[y][x];};
    const penalty=()=>{let p=0,dark=0;
      for(let y=0;y<size;y++){let run=1;for(let x=1;x<=size;x++){if(x<size&&M[y][x]===M[y][x-1])run++;else{if(run>=5)p+=run-2;run=1;}}}
      for(let x=0;x<size;x++){let run=1;for(let y=1;y<=size;y++){if(y<size&&M[y][x]===M[y-1][x])run++;else{if(run>=5)p+=run-2;run=1;}}}
      for(let y=0;y<size-1;y++)for(let x=0;x<size-1;x++){const c=M[y][x];if(c===M[y][x+1]&&c===M[y+1][x]&&c===M[y+1][x+1])p+=3;}
      const pat=[true,false,true,true,true,false,true];
      for(let y=0;y<size;y++)for(let x=0;x+6<size;x++){let h=true,v=true;for(let k=0;k<7;k++){if(M[y][x+k]!==pat[k])h=false;if(M[x+k][y]!==pat[k])v=false;}
        const lw=(get)=>{for(let k=1;k<=4;k++){const c=get(k);if(c===true)return false;}return true;};
        if(h&&(lw(k=>x-k<0?false:M[y][x-k])||lw(k=>x+6+k>=size?false:M[y][x+6+k])))p+=40;
        if(v&&(lw(k=>x-k<0?false:M[x-k][y])||lw(k=>x+6+k>=size?false:M[x+6+k][y])))p+=40;}
      for(let y=0;y<size;y++)for(let x=0;x<size;x++)if(M[y][x])dark++;
      p+=Math.floor(Math.abs(dark*20-size*size*10)/(size*size))*10;return p;};
    let best=0,bestP=Infinity;for(let m=0;m<8;m++){apply(m);format(m);const pp=penalty();if(pp<bestP){bestP=pp;best=m;}apply(m);}
    apply(best);format(best);
    return {size,modules:M,version:ver,mask:best};
  }
  function svg(text,opt){opt=opt||{};const q=encode(text),b=4,n=q.size+b*2;let d='';
    for(let y=0;y<q.size;y++)for(let x=0;x<q.size;x++)if(q.modules[y][x])d+='M'+(x+b)+' '+(y+b)+'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+n+' '+n+'" shape-rendering="crispEdges"><rect width="'+n+'" height="'+n+'" fill="#fff"/><path d="'+d+'" fill="'+(opt.color||'#0f1222')+'"/></svg>';}
  return {encode,svg};
})();

(function(){
  'use strict';
  const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
  const ua=navigator.userAgent||'';
  const isIPadOS=navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1;
  let plat=document.body.dataset.plat;
  const forced=new URLSearchParams(location.search).get('p');
  if(!forced&&isIPadOS)plat='ios';
  const standalone=(()=>{try{return navigator.standalone===true||matchMedia('(display-mode: standalone)').matches;}catch(e){return false;}})();
  function show(p){plat=p;document.body.dataset.plat=p;$$('.tabs [data-p]').forEach(b=>{const on=b.dataset.p===p;b.classList.toggle('on',on);b.setAttribute('aria-selected',on?'true':'false');});
    $$('[data-pane]').forEach(el=>el.classList.toggle('on',el.dataset.pane===p));
    $$('[data-only]').forEach(el=>el.hidden=(el.dataset.only==='desktop')!==(p==='desktop'));}
  $$('.tabs [data-p]').forEach(b=>b.addEventListener('click',()=>show(b.dataset.p)));
  show(plat);
  /* iPhone: nur Safari kann zum Home-Bildschirm mit Benachrichtigungen hinzufügen */
  if(plat==='ios'&&/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua))$('#iosNotSafari').hidden=false;
  /* Bereits installiert → direkt zur App */
  if(standalone){$$('.ctas').forEach(c=>c.insertAdjacentHTML('afterbegin','<a class="btn p" href="/chat/app.php">App öffnen</a>'));}
  /* QR-Code */
  try{const el=$('#qr');el.innerHTML=window.chQR.svg(el.dataset.url);}catch(e){$('#qr').textContent='QR-Code nicht verfügbar';}
  $('#copyLink').addEventListener('click',async e=>{const u=$('#selfLink').href;try{await navigator.clipboard.writeText(u);e.currentTarget.textContent='Kopiert ✓';}catch(x){prompt('Link kopieren:',u);}});
  /* Installieren (Android/Computer: beforeinstallprompt über app-bridge.js) */
  function paintInstall(){const A=window.ChatHelpApp;const can=!!(A&&A.canInstall);
    ['#installBtn','#installBtnD'].forEach(s=>{const b=$(s);if(!b)return;b.dataset.can=can?'1':'0';});
    const n=$('#installNote');if(n&&!can&&plat==='android')n.innerHTML='Falls der Knopf nichts anbietet: Chrome-Menü <b>⋮</b> → <b>„App installieren“</b> (Anleitung unten). Kein Store nötig.';}
  async function doInstall(){const A=window.ChatHelpApp;if(A&&A.canInstall){const r=await A.install();if(r.ok)return;}
    const t=plat==='desktop'?null:$('#'+plat+'-steps');if(t)t.scrollIntoView({behavior:'smooth',block:'start'});
    else alert('In Chrome oder Edge: rechts in der Adressleiste auf das Installieren-Symbol klicken (Bildschirm mit Pfeil) bzw. Menü ⋮ → „App installieren“.');}
  ['#installBtn','#installBtnD'].forEach(s=>{const b=$(s);if(b)b.addEventListener('click',doInstall);});
  $('#iosGuideBtn')&&$('#iosGuideBtn').addEventListener('click',e=>{e.preventDefault();$('#ios-steps').scrollIntoView({behavior:'smooth',block:'start'});});
  document.addEventListener('DOMContentLoaded',()=>{const A=window.ChatHelpApp;if(A){try{A.init({});}catch(e){}}paintInstall();});
  window.addEventListener('chathelp:installable',paintInstall);window.addEventListener('chathelp:installed',()=>{const b=$('#installBtn');if(b)b.textContent='Installiert ✓';});
})();
</script>
</body>
</html>
