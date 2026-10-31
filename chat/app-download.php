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
require_once __DIR__.'/app-i18n.php';   // Sprache wie in der App: ?lang= → Cookie ch_lang → Accept-Language → de
[$L, $LSRC] = apl_lang();
ch_page_version(__FILE__, apl_files($L));
$T = apl_t($L);
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
<html lang="<?= $L === 'no' ? 'nb' : $L ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $h($T('dl.title')) ?></title>
<meta name="description" content="<?= $h($T('dl.desc')) ?>">
<link rel="manifest" href="/chat/app.webmanifest">
<meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0b0d18" media="(prefers-color-scheme: dark)">
<meta name="color-scheme" content="light dark">
<link rel="apple-touch-icon" href="/chat/icons/apple-touch-icon.png"><link rel="icon" type="image/png" sizes="32x32" href="/chat/icons/icon-32.png">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="ChatHelp">
<meta property="og:title" content="ChatHelp Business App"><meta property="og:description" content="<?= $h($T('dl.ogdesc')) ?>"><meta property="og:image" content="https://chat-help.com/chat/icons/icon-512.png">
<link rel="stylesheet" href="/fonts/fonts.css">
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
.phone .scr{position:relative;height:100%;border-radius:31px;overflow:hidden;background:#f6f7fb;color:#0f1222}
.phone .isl{position:absolute;top:8px;left:50%;width:78px;height:22px;margin-left:-39px;border-radius:12px;background:#000;z-index:3}
/* Handy-Mockup: Inhalt endet weich über der Leiste, nichts liegt übereinander */
.pm{position:absolute;inset:0 0 48px;overflow:hidden;background:linear-gradient(#fff 0,#f6f7fb 150px);color:#0f1222;line-height:1.3;-webkit-mask-image:linear-gradient(#000 calc(100% - 26px),transparent);mask-image:linear-gradient(#000 calc(100% - 26px),transparent)}
.pm b{font-weight:700}
.pn{display:flex;gap:8px;align-items:center;margin:38px 8px 0;padding:8px 10px;border-radius:14px;background:rgba(255,255,255,.98);box-shadow:0 0 0 1px rgba(15,18,34,.06),0 8px 22px rgba(15,18,34,.16);animation:pnIn .7s .3s cubic-bezier(.2,.8,.2,1) both}
.pn-i{width:24px;height:24px;border-radius:7px;flex:0 0 auto;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#e7c873,#b8862b);animation:pnRing 3.2s 1.2s ease-out infinite}
.pn-i svg{width:13px;height:13px}
.pn-t{min-width:0;font-size:9px;color:#3b4055}
.pn-t b,.pn-t span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pn-t b{font-size:9.5px;color:#0f1222}
@keyframes pnIn{from{transform:translateY(-14px) scale(.96);opacity:0}to{transform:none;opacity:1}}
@keyframes pnRing{0%,60%,100%{box-shadow:0 0 0 0 rgba(201,151,47,0)}75%{box-shadow:0 0 0 5px rgba(201,151,47,.28)}}
.ph-hd{padding:10px 14px 8px}
.ph-h1{font:600 22px/1.1 Fraunces,Georgia,serif;letter-spacing:-.01em}
.ph-gr{font-size:10px;color:#6b7083;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ph-miss{margin:0 12px 8px;padding:9px 10px;border-radius:14px;background:linear-gradient(135deg,#fffaf0,#f6ecd3);border:1px solid #ecdcb2;font-size:9.5px;color:#2a2010}
.ph-miss b{display:block;font:600 11.5px/1.2 Fraunces,Georgia,serif;margin-bottom:2px}
.ph-kpi{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 12px 8px}
.ph-kpi>div{background:#fff;border:1px solid #e4e6ee;border-radius:12px;padding:7px 8px}
.ph-kpi b{display:block;font:600 19px/1.1 Fraunces,Georgia,serif}
.ph-kpi span{display:block;font-size:8.5px;color:#6b7083;margin-top:1px}
.ph-it{margin:0 12px 6px;background:#fff;border:1px solid #e4e6ee;border-radius:12px;padding:7px 8px;display:flex;gap:7px;align-items:center}
.ph-ic{width:24px;height:24px;border-radius:8px;flex:0 0 auto}
.ph-tx{min-width:0;flex:1}
.ph-tx>b{display:block;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ph-rw{display:flex;gap:5px;align-items:center;margin-top:2px}
.ph-sb{flex:1;min-width:0;font-size:8.5px;color:#3b4055;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ph-pl{flex:0 0 auto;padding:1px 6px;border-radius:9px;font-size:8px;font-weight:700;white-space:nowrap}
.pnav{position:absolute;left:0;right:0;bottom:0;height:48px;padding:0 8px 6px;background:#fff;border-top:1px solid #e9ebf2;display:flex;justify-content:space-between;align-items:center;color:#8d91a6}
.pnav span{position:relative;display:flex;align-items:center;justify-content:center;gap:4px;min-width:0;height:26px;padding:0 6px}
.pnav svg{width:15px;height:15px;flex:0 0 auto}
.pnav .on{padding:0 9px 0 7px;border-radius:13px;background:#0f1222;color:#fff;max-width:46%}
.pnav .on svg{color:#e7c873}
.pnav em{font:normal 700 8.5px Inter,system-ui,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pnav .dot::after{content:"";position:absolute;top:3px;right:5px;width:6px;height:6px;border-radius:50%;background:#e2455f;box-shadow:0 0 0 1.5px #fff}
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
    <a class="brand" href="/" aria-label="<?= $h($T('dl.home')) ?>"><span class="em" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span>ChatHelp <span>Business</span></a>
    <span class="sp"></span>
    <a class="btn o s" href="/chat/app.php"><?= $h($T('dl.inBrowser')) ?></a>
  </nav>

  <header class="hero">
    <div>
      <span class="kick"><?= $h($T('dl.kick')) ?></span>
      <h1><?= $T('dl.h1') ?></h1>
      <p class="lead"><?= $h($T('dl.lead')) ?></p>
      <div class="tabs" role="tablist" aria-label="<?= $h($T('dl.choose')) ?>">
        <button type="button" role="tab" data-p="ios"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg>iPhone & iPad</button>
        <button type="button" role="tab" data-p="android"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.6 9.5l1.6-2.8a.4.4 0 0 0-.7-.4l-1.7 2.9A10 10 0 0 0 12 8.2c-1.7 0-3.3.4-4.8 1L5.5 6.3a.4.4 0 1 0-.7.4l1.6 2.8A9.3 9.3 0 0 0 1.5 17h21a9.3 9.3 0 0 0-4.9-7.5zM7 14.3a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm10 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>Android</button>
        <button type="button" role="tab" data-p="desktop"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M9 20h6M12 16.5V20"/></svg><?= $h($T('dl.computer')) ?></button>
      </div>

      <!-- iPhone -->
      <div class="pane" data-pane="ios">
        <div class="ctas">
          <?php if ($LINKS['appstore']): ?><a class="store" id="lnkAppStore" href="<?= $h($LINKS['appstore']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg><span><small><?= $h($T('dl.loadIn')) ?></small><b>App Store</b></span></a>
          <?php elseif ($LINKS['testflight']): ?><a class="store" id="lnkTestflight" href="<?= $h($LINKS['testflight']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l2.2 6.8H21l-5.5 4 2.1 6.7L12 16.4l-5.6 4.1 2.1-6.7-5.5-4h6.8z"/></svg><span><small><?= $h($T('dl.testVia')) ?></small><b>TestFlight</b></span></a>
          <?php else: ?><span class="store soon" id="lnkAppStore" aria-disabled="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.3.9-1.3 1.3-2.6 1.3-2.6s-2.5-1-2.5-3.7zM14.2 5.8c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1 .1 2.1-.5 2.7-1.3z"/></svg><span><small><?= $h($T('dl.soonIn')) ?></small><b>App Store</b></span></span><?php endif; ?>
          <a class="btn g" href="#ios-steps" id="iosGuideBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M7 11H5.5v9.5h13V11H17"/></svg><?= $h($T('dl.toHome')) ?></a>
        </div>
        <p class="note"><?= $T('dl.iosNote') ?></p>
        <?php if ($inApp && $plat === 'ios'): ?><div class="alert" id="inAppHint"><?= $T('dl.inAppIos') ?></div><?php endif; ?>
        <div class="alert" id="iosNotSafari" hidden><?= $T('dl.notSafari') ?></div>
      </div>

      <!-- Android -->
      <div class="pane" data-pane="android">
        <div class="ctas">
          <?php if ($LINKS['play']): ?><a class="store" id="lnkPlay" href="<?= $h($LINKS['play']) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 2.8v18.4c0 .5.5.8.9.6l10.6-9.2L4.9 2.2c-.4-.2-.9.1-.9.6zM16.6 13.6l2.9 1.7c.7.4.7 1.4 0 1.8L6.7 23l9.9-9.4zM16.6 10.4L6.7 1l12.8 6.9c.7.4.7 1.4 0 1.8l-2.9 1.7z"/></svg><span><small><?= $h($T('dl.nowAt')) ?></small><b>Google Play</b></span></a>
          <?php else: ?><span class="store soon" id="lnkPlay" aria-disabled="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 2.8v18.4c0 .5.5.8.9.6l10.6-9.2L4.9 2.2c-.4-.2-.9.1-.9.6zM16.6 13.6l2.9 1.7c.7.4.7 1.4 0 1.8L6.7 23l9.9-9.4zM16.6 10.4L6.7 1l12.8 6.9c.7.4.7 1.4 0 1.8l-2.9 1.7z"/></svg><span><small><?= $h($T('dl.soonAt')) ?></small><b>Google Play</b></span></span><?php endif; ?>
          <button class="btn g" type="button" id="installBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5"/><path d="M5 19.5h14"/></svg><?= $h($T('dl.install')) ?></button>
          <?php if ($LINKS['apk']): ?><a class="btn o" id="lnkApk" href="<?= $h($LINKS['apk']) ?>" rel="noopener"><?= $h($T('dl.apk')) ?></a><?php endif; ?>
        </div>
        <p class="note" id="installNote"><?= $h($T('dl.installNote')) ?></p>
        <?php if ($LINKS['apk']): ?><p class="note"><?= $h($T('dl.apkNote')) ?></p><?php endif; ?>
        <?php if ($inApp && $plat === 'android'): ?><div class="alert"><?= $T('dl.inAppAndroid') ?></div><?php endif; ?>
      </div>

      <!-- Computer -->
      <div class="pane" data-pane="desktop">
        <div class="ctas"><a class="btn p" href="/chat/app.php"><?= $h($T('dl.openInBrowser')) ?></a><button class="btn o" type="button" id="installBtnD"><?= $h($T('dl.installAsApp')) ?></button></div>
        <p class="note"><?= $h($T('dl.qrFast')) ?></p>
      </div>
    </div>

    <div class="shot" aria-hidden="true"><div class="glow"></div>
      <div class="phone"><div class="isl"></div><div class="scr">
        <div class="pm">
          <div class="pn"><span class="pn-i"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span><span class="pn-t"><b><?= $h($T('dl.ph.notif')) ?></b><span><?= $h($T('dl.ph.notifP')) ?></span></span></div>
          <div class="ph-hd"><div class="ph-h1"><?= $h($T('dl.ph.today')) ?></div><div class="ph-gr"><?= $h($T('dl.ph.greet')) ?></div></div>
          <div class="ph-miss"><b><?= $h($T('dl.ph.miss')) ?></b><span><?= $h($T('dl.ph.missP')) ?></span></div>
          <div class="ph-kpi"><div><b style="color:#8f6519">4</b><span><?= $h($T('dl.ph.orders')) ?></span></div><div><b>6</b><span><?= $h($T('dl.ph.appts')) ?></span></div></div>
          <?php foreach ([[$T('dl.ph.i1'),$T('dl.ph.i1s'),$T('dl.ph.i1p'),'#f6ecd3','#8f6519'],[$T('dl.ph.i2'),$T('dl.ph.i2s'),$T('dl.ph.i2p'),'#e6eefc','#2f6fde'],[$T('dl.ph.i3'),'Mia Wagner',$T('dl.ph.today'),'#ece9fb','#6d5bd0']] as [$t,$s,$p,$bg,$fg]): ?>
          <div class="ph-it"><span class="ph-ic" style="background:<?= $bg ?>"></span><span class="ph-tx"><b><?= $h($t) ?></b><span class="ph-rw"><span class="ph-sb"><?= $h($s) ?></span><span class="ph-pl" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= $h($p) ?></span></span></span></div>
          <?php endforeach; ?>
        </div>
        <div class="pnav"><span class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg><em><?= $h($T('dl.ph.today')) ?></em></span><span title="<?= $h($T('dl.ph.jobs')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></span><span title="<?= $h($T('dl.ph.cust')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5A6.5 6.5 0 0 1 21.5 20"/></svg></span><span title="<?= $h($T('dl.ph.alerts')) ?>" class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span><span title="<?= $h($T('dl.ph.more')) ?>"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></span></div>
      </div></div>
    </div>
  </header>

  <!-- ===== Anleitung iPhone ===== -->
  <section class="sec pane" data-pane="ios" id="ios-steps">
    <h2><?= $h($T('dl.ios.h')) ?></h2>
    <p class="sub"><?= $T('dl.ios.sub') ?></p>
    <div class="steps">
      <div class="step"><div class="ill"><div class="mini"><div class="s"><div style="padding:22px 10px 6px;font:600 13px Fraunces,Georgia,serif">ChatHelp</div><div style="margin:0 10px;height:60px;border-radius:8px;background:#f1f2f7"></div><div style="margin:6px 10px;height:40px;border-radius:8px;background:#f1f2f7"></div>
        <div style="position:absolute;left:0;right:0;bottom:0;height:52px;background:#f7f7f9;border-top:1px solid #e4e6ee"><div style="margin:6px 8px;height:16px;border-radius:6px;background:#e9e9ee;font-size:7.5px;color:#6b7083;display:grid;place-items:center">chat-help.com</div>
        <div style="display:flex;justify-content:space-around;align-items:center;color:#2f6fde;font-size:11px;padding:0 4px"><span>‹</span><span>›</span><span class="hl" style="display:grid;place-items:center;width:20px;height:18px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#2f6fde" stroke-width="2.4" stroke-linecap="round"><path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M7 11H5.5v9.5h13V11H17"/></svg></span><span>▢</span><span>⧉</span></div></div></div></div></div>
        <h3><i>1</i><?= $h($T('dl.ios.s1')) ?></h3><p><?= $h($T('dl.ios.s1p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#e9e9ee"><div style="position:absolute;left:0;right:0;bottom:0;top:46px;background:#f2f2f7;border-radius:12px 12px 0 0;padding:8px">
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px"><span style="width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span><span><b style="display:block;font-size:8.5px">ChatHelp Business</b><span style="font-size:7px;color:#6b7083">chat-help.com</span></span></div>
        <div style="background:#fff;border-radius:8px;overflow:hidden"><div style="padding:6px 8px;border-bottom:1px solid #eee"><?= $h($T('dl.m.copy')) ?></div><div style="padding:6px 8px;border-bottom:1px solid #eee"><?= $h($T('dl.m.reading')) ?></div><div style="padding:6px 8px;border-bottom:1px solid #eee"><?= $h($T('dl.m.bookmarks')) ?></div>
        <div class="hl" style="padding:6px 8px;display:flex;justify-content:space-between;align-items:center;gap:4px;background:#fff;white-space:nowrap;font-size:8px"><?= $h($T('dl.m.toHome')) ?> <span style="border:1.4px solid #0f1222;border-radius:3px;width:11px;height:11px;display:grid;place-items:center;font-size:9px;line-height:1">+</span></div></div></div></div></div></div>
        <h3><i>2</i><?= $h($T('dl.ios.s2')) ?></h3><p><?= $h($T('dl.ios.s2p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#f2f2f7"><div style="display:flex;justify-content:space-between;align-items:center;gap:4px;padding:24px 7px 8px;font-size:7.5px;white-space:nowrap"><span style="color:#2f6fde"><?= $h($T('dl.m.cancel')) ?></span><b style="font-size:7px"><?= $h($T('dl.m.homeScreen')) ?></b><span class="hl" style="color:#2f6fde;font-weight:800;padding:1px 3px"><?= $h($T('dl.m.add')) ?></span></div>
        <div style="margin:4px 8px;background:#fff;border-radius:10px;padding:8px;display:flex;gap:8px;align-items:center"><span style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span><span><b style="display:block;font-size:10px">ChatHelp</b><span style="font-size:7.5px;color:#6b7083">chat-help.com</span></span></div>
        <div style="margin:10px 10px;font-size:7.5px;color:#6b7083;font-weight:500"><?= $h($T('dl.m.addNote')) ?></div></div></div></div>
        <h3><i>3</i><?= $h($T('dl.ios.s3')) ?></h3><p><?= $h($T('dl.ios.s3p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:linear-gradient(160deg,#2b2f4a,#0f1222)"><div style="margin:20px 6px 0;background:rgba(255,255,255,.92);border-radius:10px;padding:6px 7px;display:flex;gap:6px;align-items:center;box-shadow:0 6px 16px rgba(0,0,0,.3)"><span style="width:16px;height:16px;border-radius:5px;background:linear-gradient(135deg,#f3dfa6,#c9972f);flex:0 0 auto"></span><span style="font-size:7px;color:#3b4055;min-width:0"><b style="display:block;color:#0f1222;font-size:7.5px"><?= $h($T('dl.m.notif')) ?></b>2× Pizza Margherita</span></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:14px 10px">
        <?php for ($i = 0; $i < 11; $i++): ?><span style="aspect-ratio:1;border-radius:7px;background:rgba(255,255,255,<?= $i % 3 ? '.18' : '.28' ?>)"></span><?php endfor; ?>
        <span class="hl" style="aspect-ratio:1;border-radius:7px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span></div></div></div></div>
        <h3><i>4</i><?= $h($T('dl.s4')) ?></h3><p><?= $h($T('dl.ios.s4p')) ?></p></div>
    </div>
  </section>

  <!-- ===== Anleitung Android ===== -->
  <section class="sec pane" data-pane="android" id="android-steps">
    <h2><?= $h($T('dl.and.h')) ?></h2>
    <p class="sub"><?= $h($T('dl.and.sub')) ?></p>
    <div class="steps">
      <div class="step"><div class="ill"><div class="mini"><div class="s"><div style="display:flex;align-items:center;gap:5px;padding:20px 6px 6px;background:#f7f7f9;border-bottom:1px solid #e4e6ee"><span style="flex:1;height:16px;border-radius:9px;background:#e9e9ee;font-size:7px;color:#6b7083;display:grid;place-items:center">chat-help.com/chat/app.php</span><span class="hl" style="width:14px;height:18px;display:grid;place-items:center;font-size:12px;line-height:1">⋮</span></div>
        <div style="padding:8px 10px;font:600 13px Fraunces,Georgia,serif"><?= $h($T('dl.ph.today')) ?></div><div style="margin:0 10px;height:50px;border-radius:8px;background:#f1f2f7"></div><div style="margin:6px 10px;height:50px;border-radius:8px;background:#f1f2f7"></div></div></div></div>
        <h3><i>1</i><?= $h($T('dl.and.s1')) ?></h3><p><?= $h($T('dl.and.s1p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:#f1f2f7"><div style="position:absolute;right:6px;top:22px;width:108px;background:#fff;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.18);padding:4px 0;font-weight:500">
        <div style="padding:5px 8px"><?= $h($T('dl.m.newTab')) ?></div><div style="padding:5px 8px"><?= $h($T('dl.m.history')) ?></div><div style="padding:5px 8px">Downloads</div><div style="padding:5px 8px"><?= $h($T('dl.m.bookmarks')) ?></div><div class="hl" style="margin:2px 4px;padding:5px 6px;font-weight:700"><?= $h($T('dl.install')) ?></div><div style="padding:5px 8px"><?= $h($T('dl.m.share')) ?></div><div style="padding:5px 8px"><?= $h($T('dl.m.settings')) ?></div></div></div></div></div>
        <h3><i>2</i><?= $h($T('dl.and.s2')) ?></h3><p><?= $h($T('dl.and.s2p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:rgba(15,18,34,.45)"><div style="position:absolute;left:8px;right:8px;top:70px;background:#fff;border-radius:14px;padding:10px"><b style="display:block;font-size:10px;margin-bottom:6px"><?= $h($T('dl.m.installQ')) ?></b>
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:10px"><span style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span><span><b style="display:block;font-size:8.5px">ChatHelp Business</b><span style="font-size:7px;color:#6b7083">chat-help.com</span></span></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;font-size:8.5px;color:#2f6fde;font-weight:700"><span><?= $h($T('dl.m.cancel')) ?></span><span class="hl" style="padding:1px 4px"><?= $h($T('dl.m.install')) ?></span></div></div></div></div></div>
        <h3><i>3</i><?= $h($T('dl.and.s3')) ?></h3><p><?= $h($T('dl.and.s3p')) ?></p></div>
      <div class="step"><div class="ill"><div class="mini"><div class="s" style="background:linear-gradient(160deg,#2b2f4a,#0f1222)"><div style="margin:18px 6px 0;background:#fff;border-radius:12px;padding:7px;box-shadow:0 6px 16px rgba(0,0,0,.3)"><div style="display:flex;gap:5px;align-items:center;font-size:7px;color:#6b7083;margin-bottom:3px"><span style="width:10px;height:10px;border-radius:50%;background:#b8862b"></span><?= $h($T('dl.m.now')) ?></div><b style="display:block;font-size:8px"><?= $h($T('dl.m.ride')) ?></b><span style="font-size:7.5px;color:#3b4055"><?= $h($T('dl.m.rideP')) ?></span><div style="display:flex;gap:10px;margin-top:5px;font-size:7.5px;color:#2f6fde;font-weight:700"><span class="hl" style="padding:1px 4px"><?= $h($T('dl.m.open')) ?></span></div></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:14px 10px"><?php for ($i = 0; $i < 7; $i++): ?><span style="aspect-ratio:1;border-radius:50%;background:rgba(255,255,255,.2)"></span><?php endfor; ?><span style="aspect-ratio:1;border-radius:50%;background:linear-gradient(135deg,#f3dfa6,#c9972f)"></span></div></div></div></div>
        <h3><i>4</i><?= $h($T('dl.s4')) ?></h3><p><?= $h($T('dl.and.s4p')) ?></p></div>
    </div>
  </section>

  <!-- ===== Computer: QR ===== -->
  <section class="sec" id="qr-sec">
    <h2 data-only="desktop"><?= $h($T('dl.qr.h')) ?></h2><h2 data-only="mobile"><?= $h($T('dl.qr.h2')) ?></h2>
    <p class="sub"><?= $h($T('dl.qr.sub')) ?></p>
    <div class="qrbox">
      <div class="qr" id="qr" role="img" aria-label="<?= $h($T('dl.qr.aria', ['u' => $selfUrl])) ?>" data-url="<?= $h($selfUrl) ?>"></div>
      <div class="tx"><b style="font-size:17px"><?= $h($T('dl.qr.scan')) ?></b><p class="note" style="margin:6px 0 12px"><?= $h($T('dl.qr.self')) ?> <a href="<?= $h(strtok($selfUrl, '?')) ?>" id="selfLink"><?= $h(preg_replace('~^https?://~', '', strtok($selfUrl, '?'))) ?></a></p>
        <div class="ctas" style="margin:0"><button class="btn o s" type="button" id="copyLink"><?= $h($T('dl.copy')) ?></button><a class="btn o s" href="mailto:?subject=ChatHelp%20Business%20App&amp;body=<?= rawurlencode($T('dl.mailBody', ['u' => strtok($selfUrl, '?')])) ?>"><?= $h($T('dl.mail')) ?></a></div></div>
    </div>
  </section>

  <section class="sec">
    <h2><?= $h($T('dl.f.h')) ?></h2>
    <p class="sub"><?= $h($T('dl.f.sub')) ?></p>
    <div class="feat">
      <?php foreach ([['bell',$T('dl.f.bell'),$T('dl.f.bell.p')],
        ['bolt',$T('dl.f.bolt'),$T('dl.f.bolt.p')],
        ['users',$T('dl.f.users'),$T('dl.f.users.p')],
        ['wifi',$T('dl.f.wifi'),$T('dl.f.wifi.p')],
        ['moon',$T('dl.f.moon'),$T('dl.f.moon.p')],
        ['shield',$T('dl.f.shield'),$T('dl.f.shield.p')]] as [$i, $t, $s]): ?>
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
    <h2><?= $h($T('dl.faq.h')) ?></h2>
    <details><summary><?= $h($T('dl.q.cost')) ?></summary><p><?= $h($T('dl.q.cost.a')) ?></p></details>
    <details><summary><?= $h($T('dl.q.diff')) ?></summary><p><?= $h($T('dl.q.diff.a')) ?></p></details>
    <details><summary><?= $h($T('dl.q.ios')) ?></summary><p><?= $h($T('dl.q.ios.a')) ?></p></details>
    <details><summary><?= $h($T('dl.q.team')) ?></summary><p><?= $h($T('dl.q.team.a')) ?></p></details>
    <details><summary><?= $h($T('dl.q.offline')) ?></summary><p><?= $h($T('dl.q.offline.a')) ?></p></details>
  </section>

  <footer>ChatHelp Business · <a href="/chat/app.php"><?= $h($T('dl.openApp')) ?></a> · <a href="/chat/studio.php">Studio</a> · <a href="/impressum.html"><?= $h($T('a.imprint')) ?></a> · <?= $h($T('a.gdpr')) ?></footer>
</div>

<script>window.DL_T=<?= json_encode(['open' => $T('dl.openApp'), 'qrNA' => $T('dl.js.qrNA'), 'copied' => $T('dl.js.copied'), 'copyPrompt' => $T('dl.js.copyPrompt'), 'note' => $T('dl.js.note'), 'alert' => $T('dl.js.alert'), 'installed' => $T('dl.js.installed')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
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
  if(standalone){$$('.ctas').forEach(c=>{c.insertAdjacentHTML('afterbegin','<a class="btn p" href="/chat/app.php"></a>');c.firstElementChild.textContent=DL_T.open;});}
  /* QR-Code */
  try{const el=$('#qr');el.innerHTML=window.chQR.svg(el.dataset.url);}catch(e){$('#qr').textContent=DL_T.qrNA;}
  $('#copyLink').addEventListener('click',async e=>{const u=$('#selfLink').href;try{await navigator.clipboard.writeText(u);e.currentTarget.textContent=DL_T.copied;}catch(x){prompt(DL_T.copyPrompt,u);}});
  /* Installieren (Android/Computer: beforeinstallprompt über app-bridge.js) */
  function paintInstall(){const A=window.ChatHelpApp;const can=!!(A&&A.canInstall);
    ['#installBtn','#installBtnD'].forEach(s=>{const b=$(s);if(!b)return;b.dataset.can=can?'1':'0';});
    const n=$('#installNote');if(n&&!can&&plat==='android')n.innerHTML=DL_T.note;}
  async function doInstall(){const A=window.ChatHelpApp;if(A&&A.canInstall){const r=await A.install();if(r.ok)return;}
    const t=plat==='desktop'?null:$('#'+plat+'-steps');if(t)t.scrollIntoView({behavior:'smooth',block:'start'});
    else alert(DL_T.alert);}
  ['#installBtn','#installBtnD'].forEach(s=>{const b=$(s);if(b)b.addEventListener('click',doInstall);});
  $('#iosGuideBtn')&&$('#iosGuideBtn').addEventListener('click',e=>{e.preventDefault();$('#ios-steps').scrollIntoView({behavior:'smooth',block:'start'});});
  document.addEventListener('DOMContentLoaded',()=>{const A=window.ChatHelpApp;if(A){try{A.init({});}catch(e){}}paintInstall();});
  window.addEventListener('chathelp:installable',paintInstall);window.addEventListener('chathelp:installed',()=>{const b=$('#installBtn');if(b)b.textContent=DL_T.installed;});
})();
</script>
</body>
</html>
