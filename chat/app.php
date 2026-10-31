<?php
/**
 * ChatHelp Business — App (installierte Web-App + Hülle der nativen Android-/iOS-App)
 * ===================================================================================
 *   /chat/app.php               mobile App für Betriebe: Heute (Live-Feed), Aufträge je Modul, Kunden, Meldungen, Mehr
 *   /chat/app.php#bestellungen  Bereiche: heute · bestellungen · termine · taxi · hotel · rueckruf · kunden · benachrichtigungen · mehr
 *   ?tag=order-A7K2Q9&nid=123   Tipp auf eine Meldung (sw.js): Eintrag öffnen und im Postfach als gelesen markieren
 *   ?focus=push                 z. B. aus der Einrichtung: direkt „Benachrichtigungen aktivieren“ zeigen
 * Login wie im Studio (E-Mail → 6-stelliger Code → API-Key in localStorage 'ch_api_key', gemeinsam mit dem Studio).
 * Daten ausschließlich über /chat/tokens-api.php/v1/… (Bearer-Key) – diese Seite selbst enthält keine Kundendaten
 * und darf deshalb vom Service Worker als Offline-Hülle zwischengespeichert werden.
 */
require_once __DIR__.'/pagever.php';
require_once __DIR__.'/app-i18n.php';   // Sprache: ?lang= → Cookie ch_lang → Accept-Language → de; Wörterbuch chat/app-i18n/<lang>.json
[$L, $LSRC] = apl_lang();
ch_page_version(__FILE__, array_merge([__DIR__.'/app-bridge.js'], apl_files($L)));
$T = apl_t($L); $hx = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('X-Robots-Tag: noindex'); header('Referrer-Policy: same-origin'); header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN');
$bv = substr(md5((string)@filemtime(__DIR__.'/app-bridge.js')), 0, 8);
$hasSetup = is_file(__DIR__.'/studio-panels/einrichtung.js') && is_file(__DIR__.'/studio-panels/einrichtung.html');
$appVer = substr(md5((string)@filemtime(__FILE__)), 0, 6);
?><!DOCTYPE html>
<html lang="<?= $L === 'no' ? 'nb' : $L ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ChatHelp Business</title>
<meta name="robots" content="noindex">
<meta name="description" content="<?= $hx($T('a.meta.desc')) ?>">
<link rel="manifest" href="/chat/app.webmanifest">
<meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b0d18" media="(prefers-color-scheme: dark)">
<meta name="color-scheme" content="light dark">
<link rel="apple-touch-icon" href="/chat/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/chat/icons/icon-32.png">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="ChatHelp"><meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<link rel="stylesheet" href="/fonts/fonts.css">
<?= apl_head($L, $LSRC) ?>
<script>window.CH_BRIDGE_SRC="/chat/app-bridge.js?v=<?= $bv ?>";(function(){try{var t=localStorage.getItem('ch_app_theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<script src="/chat/app-bridge.js?v=<?= $bv ?>" defer></script>
<style>
:root{
  --bg:#f6f7fb;--sur:#ffffff;--sur2:#f1f2f7;--sur3:#e9ebf3;--line:#e4e6ee;--line2:#d7dae6;--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;
  --gold:#b8862b;--gold2:#8f6519;--goldl:#f6ecd3;--goldg:linear-gradient(135deg,#f3dfa6,#c9972f);
  --ok:#1a9e5a;--okl:#e4f5ea;--rose:#d9336b;--rosel:#fbe7ee;--blue:#2f6fde;--bluel:#e6eefc;--violet:#6d5bd0;--violetl:#ece9fb;--teal:#0f8a8a;--teall:#def4f3;
  --shadow:0 1px 2px rgba(15,18,34,.05),0 8px 24px rgba(15,18,34,.06);--shadow2:0 2px 6px rgba(15,18,34,.08),0 24px 60px rgba(15,18,34,.18);
  --bar:rgba(255,255,255,.86);--scrim:rgba(15,18,34,.38);
  --sat:var(--safe-area-inset-top,env(safe-area-inset-top,0px));--sab:var(--safe-area-inset-bottom,env(safe-area-inset-bottom,0px));
  --sal:var(--safe-area-inset-left,env(safe-area-inset-left,0px));--sar:var(--safe-area-inset-right,env(safe-area-inset-right,0px));
  --tabh:64px;--r:18px;--ease:cubic-bezier(.2,.8,.2,1);
  color-scheme:light;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --bg:#0b0d18;--sur:#151829;--sur2:#1c2035;--sur3:#242943;--line:#262b45;--line2:#323856;--ink:#f1f2f7;--ink2:#c3c6d6;--mute:#8d91a6;
  --gold:#e3c27a;--gold2:#ecd29a;--goldl:#2a2414;--ok:#3ccf85;--okl:#11291d;--rose:#f0668e;--rosel:#2e1420;--blue:#7aa6ff;--bluel:#16213d;--violet:#a99bff;--violetl:#211c3d;--teal:#4fd1cf;--teall:#0f2a2a;
  --shadow:0 1px 2px rgba(0,0,0,.3),0 8px 24px rgba(0,0,0,.25);--shadow2:0 2px 6px rgba(0,0,0,.4),0 24px 60px rgba(0,0,0,.55);--bar:rgba(16,18,33,.86);--scrim:rgba(0,0,0,.55);color-scheme:dark}}
:root[data-theme="dark"]{
  --bg:#0b0d18;--sur:#151829;--sur2:#1c2035;--sur3:#242943;--line:#262b45;--line2:#323856;--ink:#f1f2f7;--ink2:#c3c6d6;--mute:#8d91a6;
  --gold:#e3c27a;--gold2:#ecd29a;--goldl:#2a2414;--ok:#3ccf85;--okl:#11291d;--rose:#f0668e;--rosel:#2e1420;--blue:#7aa6ff;--bluel:#16213d;--violet:#a99bff;--violetl:#211c3d;--teal:#4fd1cf;--teall:#0f2a2a;
  --shadow:0 1px 2px rgba(0,0,0,.3),0 8px 24px rgba(0,0,0,.25);--shadow2:0 2px 6px rgba(0,0,0,.4),0 24px 60px rgba(0,0,0,.55);--bar:rgba(16,18,33,.86);--scrim:rgba(0,0,0,.55);color-scheme:dark}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{background:var(--bg);overscroll-behavior-y:none;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{margin:0;min-height:100vh;min-height:100dvh;background:var(--bg);color:var(--ink);font:15px/1.5 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased;overflow-x:hidden;overscroll-behavior-y:none}
button,input,textarea,select{font:inherit;color:inherit}
button{cursor:pointer;-webkit-user-select:none;user-select:none;touch-action:manipulation}
a{color:inherit}
[hidden]{display:none!important}
.serif{font-family:Fraunces,Georgia,"Times New Roman",serif;letter-spacing:-.01em}
.mute{color:var(--mute)}.small{font-size:13px}.tiny{font-size:12px}
.sr{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.ico{width:22px;height:22px;flex:0 0 auto;display:inline-block;vertical-align:middle}
.ico.s{width:18px;height:18px}.ico.xs{width:16px;height:16px}.ico.l{width:28px;height:28px}
.ell{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wrap{overflow-wrap:anywhere;word-break:break-word}
@media (prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}

/* ---------- Start / Login ---------- */
#boot{position:fixed;inset:0;display:grid;place-items:center;background:var(--bg);z-index:60;transition:opacity .25s}
#boot.out{opacity:0;pointer-events:none}
.em{width:64px;height:64px;border-radius:20px;background:var(--goldg);display:grid;place-items:center;box-shadow:0 10px 28px rgba(184,134,43,.28)}
.em.big{width:76px;height:76px;border-radius:24px}
.pulse{animation:pl 1.4s var(--ease) infinite}@keyframes pl{50%{transform:scale(.94);opacity:.85}}
#vLogin{min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;justify-content:center;padding:calc(28px + var(--sat)) 20px calc(24px + var(--sab))}
.lcard{width:100%;max-width:420px;margin:0 auto}
.lhero{text-align:center;margin-bottom:22px}
.lhero h1{font:600 30px/1.15 Fraunces,Georgia,serif;margin:18px 0 6px;letter-spacing:-.015em}.lhero h1 span{color:var(--gold)}
.lhero p{margin:0 auto;color:var(--ink2);max-width:32ch}
.panel{background:var(--sur);border:1px solid var(--line);border-radius:24px;padding:22px 18px;box-shadow:var(--shadow)}
.lbl{display:block;font-weight:600;font-size:13px;color:var(--ink2);margin:0 0 6px}
.inp{width:100%;min-height:52px;padding:13px 14px;border-radius:14px;border:1px solid var(--line2);background:var(--sur);color:var(--ink);font-size:16px;outline:none;transition:border-color .15s,box-shadow .15s}
.inp:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.18)}
.inp.code{font:700 26px/1 Inter,system-ui,sans-serif;letter-spacing:.42em;text-align:center;padding-left:.42em}
textarea.inp{min-height:96px;resize:vertical;line-height:1.45}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:50px;padding:12px 18px;border-radius:14px;border:1px solid transparent;font-weight:700;font-size:15px;background:var(--sur2);color:var(--ink);transition:transform .12s var(--ease),opacity .15s,background .15s;text-decoration:none;max-width:100%}
.btn:active{transform:scale(.97)}
.btn.p{background:var(--ink);color:var(--bg)}
:root[data-theme="dark"] .btn.p{background:var(--goldg);color:#1a1407}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .btn.p{background:var(--goldg);color:#1a1407}}
.btn.g{background:var(--goldg);color:#1a1407;box-shadow:0 8px 22px rgba(184,134,43,.28)}
.btn.o{background:transparent;border-color:var(--line2)}
.btn.ok{background:var(--ok);color:#fff}.btn.rose{background:var(--rosel);color:var(--rose)}
.btn.w{width:100%}.btn.s{min-height:42px;padding:9px 14px;font-size:14px;border-radius:12px}.btn.xs{min-height:36px;padding:7px 12px;font-size:13px;border-radius:11px}
.btn[disabled]{opacity:.55;pointer-events:none}
.btn .spin{width:16px;height:16px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:rot .8s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}
.lnk{background:none;border:0;padding:10px 4px;color:var(--ink2);font-weight:600;font-size:14px;text-decoration:underline;text-underline-offset:3px;min-height:44px}
.msg{margin-top:12px;padding:11px 13px;border-radius:12px;font-size:14px;background:var(--sur2);color:var(--ink2)}
.msg.err{background:var(--rosel);color:var(--rose)}.msg.ok{background:var(--okl);color:var(--ok)}
.lfoot{text-align:center;margin-top:18px;font-size:12.5px;color:var(--mute)}
.lfoot a{color:var(--ink2)}

/* ---------- App-Gerüst ---------- */
#vApp{min-height:100vh;min-height:100dvh;padding-bottom:calc(var(--tabh) + var(--sab) + 14px)}
.top{position:sticky;top:0;z-index:20;padding:calc(10px + var(--sat)) calc(16px + var(--sar)) 10px calc(16px + var(--sal));background:var(--bar);-webkit-backdrop-filter:saturate(1.6) blur(16px);backdrop-filter:saturate(1.6) blur(16px);border-bottom:1px solid transparent;transition:border-color .2s}
.top.sc{border-bottom-color:var(--line)}
.toprow{display:flex;align-items:center;gap:10px;min-height:44px;max-width:728px;margin:0 auto}
.toptitle{flex:1;min-width:0}
.toptitle h1{margin:0;font:600 24px/1.15 Fraunces,Georgia,serif;letter-spacing:-.015em}
.toptitle .sub{font-size:12.5px;color:var(--mute);margin-top:1px}
.ib{width:44px;height:44px;border-radius:14px;border:1px solid var(--line);background:var(--sur);display:grid;place-items:center;color:var(--ink2);flex:0 0 auto;position:relative}
.ib:active{transform:scale(.95)}
.live{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--mute)}
.live i{width:8px;height:8px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 3px rgba(26,158,90,.15)}
.live.off i{background:var(--rose);box-shadow:0 0 0 3px rgba(217,51,107,.15)}
.live.busy i{background:var(--gold);animation:pl 1s infinite}
#net{margin:0 16px 8px;padding:10px 12px;border-radius:12px;background:var(--rosel);color:var(--rose);font-size:13.5px;font-weight:600;display:flex;gap:8px;align-items:center}
#ptr{position:fixed;left:50%;top:calc(8px + var(--sat));z-index:30;width:40px;height:40px;margin-left:-20px;border-radius:50%;background:var(--sur);box-shadow:var(--shadow2);display:grid;place-items:center;color:var(--gold);transform:translateY(-70px);opacity:0;pointer-events:none}
#ptr.go .ico{animation:rot .8s linear infinite}
main{padding:4px calc(16px + var(--sar)) 0 calc(16px + var(--sal));max-width:760px;margin:0 auto}
.page{display:none}
.page.on{display:block;animation:pin .22s var(--ease)}
@keyframes pin{from{opacity:0;transform:translate3d(0,8px,0)}to{opacity:1;transform:none}}

/* ---------- Tab-Leiste ---------- */
.tabbar{position:fixed;left:0;right:0;bottom:0;z-index:40;display:flex;padding:6px calc(6px + var(--sar)) calc(6px + var(--sab)) calc(6px + var(--sal));background:var(--bar);-webkit-backdrop-filter:saturate(1.6) blur(18px);backdrop-filter:saturate(1.6) blur(18px);border-top:1px solid var(--line)}
.tab{flex:1 1 0;min-width:0;min-height:54px;border:0;background:none;border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;color:var(--mute);font-size:11px;font-weight:600;position:relative;padding:4px 2px}
.tab .ico{width:24px;height:24px;transition:transform .2s var(--ease)}
.tab span.t{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tab.on{color:var(--ink)}.tab.on .ico{color:var(--gold);transform:translateY(-1px)}
.tab.on::before{content:"";position:absolute;top:2px;left:50%;width:22px;height:3px;margin-left:-11px;border-radius:3px;background:var(--gold)}
.tab:active .ico{transform:scale(.9)}
.bdg{position:absolute;top:4px;left:calc(50% + 6px);min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--rose);color:#fff;font:700 11px/18px Inter,system-ui,sans-serif;text-align:center;box-shadow:0 0 0 2px var(--sur)}
@media (min-width:900px){.tabbar{left:50%;right:auto;width:560px;margin-left:-280px;bottom:12px;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow2);padding-bottom:6px}#vApp{padding-bottom:110px}}

/* ---------- Bausteine ---------- */
.sec{margin:20px 2px 8px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.sec h2{margin:0;font:700 12px/1.2 Inter,system-ui,sans-serif;letter-spacing:.09em;text-transform:uppercase;color:var(--mute)}
.sec .act{font-size:13px;font-weight:600;color:var(--gold2);background:none;border:0;padding:8px 2px;min-height:40px}
.card{background:var(--sur);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);padding:16px}
.card+.card{margin-top:10px}
.list{display:flex;flex-direction:column;gap:10px}
.it{display:flex;align-items:flex-start;gap:12px;width:100%;text-align:left;background:var(--sur);border:1px solid var(--line);border-radius:var(--r);padding:14px;box-shadow:var(--shadow);position:relative;color:var(--ink);transition:transform .12s var(--ease),border-color .2s}
.it:active{transform:scale(.985)}
.it.hot{border-color:rgba(184,134,43,.55);box-shadow:0 0 0 1px rgba(184,134,43,.25),var(--shadow)}
.it.fresh{animation:fresh 1.6s var(--ease)}
@keyframes fresh{0%{background:var(--goldl)}100%{background:var(--sur)}}
.it .bd{flex:1;min-width:0}
.it .tt{font-weight:700;font-size:15px;line-height:1.3;display:flex;gap:8px;align-items:baseline;min-width:0}
.it .tt b{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.it .tt .tm{margin-left:auto;flex:0 0 auto;font-size:12px;font-weight:600;color:var(--mute)}
.it .sb{color:var(--ink2);font-size:13.5px;margin-top:2px;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.it .mt{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:8px}
.ti{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;flex:0 0 auto}
.t-gold{background:var(--goldl);color:var(--gold2)}.t-blue{background:var(--bluel);color:var(--blue)}.t-ok{background:var(--okl);color:var(--ok)}.t-rose{background:var(--rosel);color:var(--rose)}.t-violet{background:var(--violetl);color:var(--violet)}.t-teal{background:var(--teall);color:var(--teal)}.t-mute{background:var(--sur2);color:var(--ink2)}
.pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:var(--sur2);color:var(--ink2);white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
.pill.gold{background:var(--goldl);color:var(--gold2)}.pill.ok{background:var(--okl);color:var(--ok)}.pill.rose{background:var(--rosel);color:var(--rose)}.pill.blue{background:var(--bluel);color:var(--blue)}.pill.violet{background:var(--violetl);color:var(--violet)}
.pill.new::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
.acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.acts .btn{flex:1 1 140px}
@media (min-width:700px){.it .acts .btn{flex:0 0 auto;min-width:200px}}
.kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.kpi{background:var(--sur);border:1px solid var(--line);border-radius:var(--r);padding:14px;text-align:left;box-shadow:var(--shadow);min-height:92px;display:flex;flex-direction:column;justify-content:space-between;color:var(--ink);position:relative;overflow:hidden}
.kpi:active{transform:scale(.98)}
.kpi .n{font:600 30px/1 Fraunces,Georgia,serif;letter-spacing:-.02em}
.kpi .l{font-size:12.5px;color:var(--mute);font-weight:600;margin-top:6px}
.kpi .ico{position:absolute;right:12px;top:12px;opacity:.9}
.kpi.hot .n{color:var(--gold2)}
.chips{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;margin:0 -16px;padding:2px 16px 4px;-webkit-overflow-scrolling:touch}
.chips::-webkit-scrollbar{display:none}
.chip{flex:0 0 auto;min-height:38px;padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:var(--sur);font-size:13.5px;font-weight:600;color:var(--ink2);display:inline-flex;gap:6px;align-items:center}
.chip.on{background:var(--ink);color:var(--bg);border-color:var(--ink)}
.chip .c{font-size:11.5px;opacity:.75}
.seg{display:flex;gap:4px;padding:4px;border-radius:14px;background:var(--sur2);border:1px solid var(--line);overflow-x:auto;scrollbar-width:none}
.seg::-webkit-scrollbar{display:none}
.seg button{flex:1 0 auto;min-height:40px;padding:8px 12px;border:0;border-radius:11px;background:none;font-size:13.5px;font-weight:700;color:var(--mute);white-space:nowrap;position:relative}
.seg button.on{background:var(--sur);color:var(--ink);box-shadow:0 1px 3px rgba(15,18,34,.12)}
.seg button .c{display:inline-block;min-width:18px;margin-left:4px;padding:0 5px;border-radius:9px;background:var(--rose);color:#fff;font-size:11px;line-height:18px}
.empty{text-align:center;padding:30px 18px;border:1px dashed var(--line2);border-radius:var(--r);color:var(--mute);background:var(--sur)}
.empty .ti{margin:0 auto 10px}
.empty b{display:block;color:var(--ink);font-size:15px;margin-bottom:4px}
.skel{height:76px;border-radius:var(--r);background:linear-gradient(90deg,var(--sur2),var(--sur3),var(--sur2));background-size:200% 100%;animation:sk 1.2s linear infinite}
@keyframes sk{to{background-position:-200% 0}}
.hero{border-radius:22px;padding:18px;background:linear-gradient(135deg,#fffaf0 0%,#f6ecd3 100%);border:1px solid #ecdcb2;color:#2a2010;box-shadow:var(--shadow);position:relative;overflow:hidden}
:root[data-theme="dark"] .hero{background:linear-gradient(135deg,#2a2414,#1b1810);border-color:#4a3d1c;color:var(--ink)}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .hero{background:linear-gradient(135deg,#2a2414,#1b1810);border-color:#4a3d1c;color:var(--ink)}}
.hero h3{margin:0 0 4px;font:600 19px/1.25 Fraunces,Georgia,serif}
.hero p{margin:0 0 12px;font-size:14px;opacity:.85}
.hero.warn{background:var(--rosel);border-color:transparent;color:var(--ink)}
.row{display:flex;align-items:center;gap:12px;min-height:56px;padding:10px 14px;background:var(--sur);border:0;border-bottom:1px solid var(--line);width:100%;text-align:left;color:var(--ink);text-decoration:none}
.row:last-child{border-bottom:0}
.row .bd{flex:1;min-width:0}.row .bd b{display:block;font-weight:600;font-size:15px}.row .bd span{display:block;font-size:12.5px;color:var(--mute)}.row .bd span.pill{display:inline-flex;color:var(--gold2)}
.row .chev{color:var(--mute)}
.group{background:var(--sur);border:1px solid var(--line);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow)}
.sw{position:relative;width:51px;height:31px;flex:0 0 auto}
.sw input{position:absolute;inset:0;opacity:0;margin:0;cursor:pointer;z-index:1}
.sw i{position:absolute;inset:0;border-radius:31px;background:var(--line2);transition:background .2s}
.sw i::after{content:"";position:absolute;left:2px;top:2px;width:27px;height:27px;border-radius:50%;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,.2);transition:transform .22s var(--ease)}
.sw input:checked+i{background:var(--ok)}.sw input:checked+i::after{transform:translateX(20px)}
.sw input:focus-visible+i{outline:2px solid var(--gold);outline-offset:2px}
.kv{display:grid;grid-template-columns:auto minmax(0,1fr);gap:8px 14px;font-size:14px}
.kv dt{color:var(--mute)}.kv dd{margin:0;overflow-wrap:anywhere;font-weight:600}
.steps{margin:10px 0 0;padding:0;list-style:none;counter-reset:s}
.steps li{counter-increment:s;position:relative;padding:8px 0 8px 40px;font-size:14px}
.steps li::before{content:counter(s);position:absolute;left:0;top:6px;width:28px;height:28px;border-radius:50%;background:var(--ink);color:var(--bg);font-weight:800;font-size:13px;display:grid;place-items:center}
.bub{max-width:85%;padding:9px 12px;border-radius:16px;font-size:14px;line-height:1.4;overflow-wrap:anywhere;margin:4px 0}
.bub.u{background:var(--sur2);border-bottom-left-radius:6px}
.bub.a{background:var(--goldl);color:var(--ink);margin-left:auto;border-bottom-right-radius:6px}
.bubs{display:flex;flex-direction:column}
.av{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:14px;flex:0 0 auto;background:var(--sur2);color:var(--ink2)}
.search{position:relative}.search .ico{position:absolute;left:14px;top:50%;margin-top:-9px;color:var(--mute)}.search .inp{padding-left:42px}
.stickybar{position:sticky;top:calc(64px + var(--sat));z-index:10;background:var(--bg);padding:6px 0 10px;margin:0 -2px}

/* ---------- Bottom-Sheet ---------- */
#sheet{position:fixed;inset:0;z-index:70}
#sheet .scrim{position:absolute;inset:0;background:var(--scrim);opacity:0;transition:opacity .28s var(--ease)}
#sheet .pan{position:absolute;left:0;right:0;bottom:0;max-height:calc(100% - 28px - var(--sat));background:var(--sur);border-radius:24px 24px 0 0;box-shadow:var(--shadow2);transform:translate3d(0,105%,0);transition:transform .32s var(--ease);display:flex;flex-direction:column;will-change:transform}
#sheet.full .pan{height:calc(100% - 12px - var(--sat))}
#sheet.on .scrim{opacity:1}#sheet.on .pan{transform:none}
#sheet .grab{padding:10px 0 4px;display:grid;place-items:center;touch-action:none;cursor:grab}
#sheet .grab i{width:40px;height:5px;border-radius:3px;background:var(--line2)}
#sheet .sh{display:flex;align-items:center;gap:10px;padding:2px 16px 10px;touch-action:none}
#sheet .sh h3{flex:1;min-width:0;margin:0;font:600 20px/1.25 Fraunces,Georgia,serif}
#sheet .sbody{overflow-y:auto;overscroll-behavior:contain;padding:0 16px calc(20px + var(--sab));-webkit-overflow-scrolling:touch;flex:1}
#sheet .sfoot{padding:10px 16px calc(10px + var(--sab));border-top:1px solid var(--line);background:var(--sur)}
@media (min-width:700px){#sheet .pan{left:50%;right:auto;width:560px;margin-left:-280px;bottom:16px;border-radius:24px;max-height:calc(100% - 48px)}#sheet.full .pan{height:calc(100% - 48px)}}

/* ---------- Hinweis-Toast ---------- */
#toast{position:fixed;left:50%;top:calc(10px + var(--sat));z-index:90;width:min(440px,calc(100vw - 24px));transform:translate3d(-50%,-160%,0);transition:transform .34s cubic-bezier(.2,.9,.3,1.15);background:var(--sur);color:var(--ink);border:1px solid var(--line);border-left:4px solid var(--gold);border-radius:16px;padding:12px 14px;box-shadow:var(--shadow2);font-size:14px;display:flex;gap:10px;align-items:center}
#toast{visibility:hidden;transition:transform .34s cubic-bezier(.2,.9,.3,1.15),visibility 0s linear .34s}
#toast.on{transform:translate3d(-50%,0,0);visibility:visible;transition:transform .34s cubic-bezier(.2,.9,.3,1.15),visibility 0s}
#toast.err{border-left-color:var(--rose)}#toast.ok{border-left-color:var(--ok)}
#toast .bd{flex:1;min-width:0}#toast b{display:block}
</style>
</head>
<body>
<div id="boot" aria-hidden="true"><div class="em big pulse"><svg width="36" height="36" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></div></div>

<!-- ================= Login ================= -->
<section id="vLogin" hidden aria-labelledby="lTitle">
  <div class="lcard">
    <div class="lhero">
      <div class="em big" style="margin:0 auto" aria-hidden="true"><svg width="36" height="36" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></div>
      <h1 id="lTitle">ChatHelp <span>Business</span></h1>
      <p data-i18n="a.l.lead">Bestellungen, Termine, Fahrten und Kunden deiner KI-Agenten – live auf dem Handy.</p>
    </div>
    <div class="panel">
      <form id="stepEmail" novalidate>
        <label class="lbl" for="lEmail" data-i18n="a.l.email">E-Mail-Adresse</label>
        <input class="inp" id="lEmail" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" placeholder="name@betrieb.de" data-i18n-attr="placeholder:a.l.emailPh" required>
        <button class="btn p w" id="lSend" type="submit" style="margin-top:12px" data-i18n="a.l.send">Anmeldecode senden</button>
        <div class="small mute" style="margin-top:10px;text-align:center" data-i18n="a.l.nopw">Kein Passwort nötig – wir schicken dir einen 6-stelligen Code.</div>
      </form>
      <form id="stepCode" hidden novalidate>
        <div class="small mute" id="lTo" style="margin-bottom:10px"></div>
        <label class="lbl" for="lCode" data-i18n="a.l.code">6-stelliger Code aus der E-Mail</label>
        <input class="inp code" id="lCode" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="7" autocomplete="one-time-code" placeholder="••••••" aria-describedby="lCodeHelp">
        <div class="acts" style="margin-top:10px">
          <button class="btn o s" type="button" id="lPaste" data-i18n-t="a.l.paste"><svg class="ico s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="4" width="10" height="4" rx="1.5"/><path d="M7 6H5.5A1.5 1.5 0 0 0 4 7.5v12A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 18.5 6H17"/></svg>Code einfügen</button>
          <button class="btn p s" type="submit" id="lVerify" data-i18n="a.l.login">Anmelden</button>
        </div>
        <div id="lCodeHelp" class="small mute" style="margin-top:10px" data-i18n="a.l.iosTip">Tipp: Auf dem iPhone erscheint der Code oft direkt über der Tastatur.</div>
        <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:4px"><button class="lnk" type="button" id="lBack" data-i18n="a.l.other">Andere E-Mail</button><button class="lnk" type="button" id="lResend" data-i18n="a.l.resend">Code erneut senden</button></div>
      </form>
      <form id="stepKey" hidden novalidate>
        <label class="lbl" for="lKey" data-i18n="a.l.key">API-Schlüssel</label>
        <input class="inp" id="lKey" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="ch_live_…">
        <button class="btn p w" type="submit" style="margin-top:12px" data-i18n="a.l.keyLogin">Mit Schlüssel anmelden</button>
        <button class="lnk" type="button" id="lKeyBack" data-i18n="a.l.keyBack">Zurück zur Anmeldung per E-Mail</button>
      </form>
      <div id="lMsg" aria-live="polite"></div>
    </div>
    <div class="lfoot" data-i18n-t="a.l.noacc"><button class="lnk" type="button" id="lKeyShow" style="font-size:12.5px" data-i18n="a.l.keyShow">Mit API-Schlüssel anmelden</button><br>Noch kein Konto? <a href="/#preise" data-i18n="a.l.agents">Agenten ansehen</a> · <a href="/impressum.html" data-i18n="a.imprint">Impressum</a></div>
  </div>
</section>

<!-- ================= App ================= -->
<div id="vApp" hidden>
  <header class="top" id="top">
    <div class="toprow">
      <div class="toptitle"><h1 id="pgTitle">Heute</h1><div class="sub" id="pgSub"></div></div>
      <span class="live" id="live" title="Live-Status" data-i18n-attr="title:a.liveStatus"><i></i><span id="liveT">Live</span></span>
      <button class="ib" id="btnRefresh" type="button" aria-label="Aktualisieren" data-i18n-attr="aria-label:a.refresh"><svg class="ico s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/></svg></button>
    </div>
  </header>
  <div id="ptr" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/></svg></div>
  <div id="net" hidden role="status"><svg class="ico s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0"/><path d="M3 3l18 18"/></svg><span id="netT">Offline</span></div>
  <main id="pages">
    <section class="page" id="p-heute" aria-label="Heute" data-i18n-attr="aria-label:a.tab.heute"></section>
    <section class="page" id="p-auftraege" aria-label="Aufträge" data-i18n-attr="aria-label:a.tab.auftraege"></section>
    <section class="page" id="p-kunden" aria-label="Kunden" data-i18n-attr="aria-label:a.tab.kunden"></section>
    <section class="page" id="p-benachrichtigungen" aria-label="Benachrichtigungen" data-i18n-attr="aria-label:a.tab.push"></section>
    <section class="page" id="p-mehr" aria-label="Mehr" data-i18n-attr="aria-label:a.tab.mehr"></section>
  </main>
  <nav class="tabbar" id="tabbar" role="tablist" aria-label="Bereiche" data-i18n-attr="aria-label:a.areas"></nav>
</div>

<div id="sheet" hidden role="dialog" aria-modal="true" aria-labelledby="shTitle">
  <div class="scrim" data-close></div>
  <div class="pan"><div class="grab" aria-hidden="true"><i></i></div>
    <div class="sh"><h3 id="shTitle"></h3><button class="ib" type="button" data-close aria-label="Schließen" data-i18n-attr="aria-label:a.close"><svg class="ico s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
    <div class="sbody" id="shBody"></div><div class="sfoot" id="shFoot" hidden></div>
  </div>
</div>
<div id="toast" role="status" aria-live="polite"></div>

<script>
'use strict';
/* =====================================================================================
   ChatHelp Business – App-Logik
   ===================================================================================== */
const API='/chat/tokens-api.php/v1', HAS_SETUP=<?= $hasSetup ? 'true' : 'false' ?>, APP_VER='<?= $appVer ?>';
const $=(s,r)=>(r||document).querySelector(s), $$=(s,r)=>[...(r||document).querySelectorAll(s)];
/* ---------- Sprache (studio-i18n.js-Laufzeit, Wörterbuch app-i18n/<lang>.json inline im Kopf) ---------- */
const I=window.I18N||{lang:'de',tag:'de-DE',t:k=>k,tx:x=>x,te:(e,f)=>(e&&e.data&&e.data.message)||f||'',fx:x=>x,apply:()=>{},num:n=>Number(n||0).toLocaleString('de-DE'),
  eur:v=>Number(v||0).toLocaleString('de-DE',{style:'currency',currency:'EUR'}),date:(d,o)=>new Date(d).toLocaleDateString('de-DE',o),time:d=>new Date(d).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}),set:()=>{},langs:[['de','Deutsch','de-DE']]};
const _T=(k,v)=>I.t(k,v), DE_UI=I.lang==='de';
/** Deutscher Servertext → Oberflächensprache: Abschnitte („ · “) einzeln über src/srx, deutsche Datums-/Betragsformate (fx, „Fr 23.10. 15:00“) umformatiert */
const WD_DE=['So','Mo','Di','Mi','Do','Fr','Sa'];
function X(v){if(v==null||v==='')return v==null?'':v;if(DE_UI)return String(v);const s=String(v);const one=x=>{const y=I.tx(x);return y!==x?y:x;};
  const t=one(s)!==s?one(s):s.split(' · ').map(one).join(' · ');
  return I.fx(t.replace(/\b(So|Mo|Di|Mi|Do|Fr|Sa),? (\d{1,2})\.(\d{1,2})\.(\d{4})?(?: (\d{1,2}):(\d{2}))?/g,(m,w,d,mo,y,hh,mi)=>{const dt=new Date(y?+y:new Date().getFullYear(),+mo-1,+d,hh?+hh:12,mi?+mi:0);
    return I.date(dt,{weekday:'short',day:'2-digit',month:'2-digit'})+(hh?' '+I.time(dt):'');}));}
const fmtD=(iso,o)=>{const m=/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(iso||'');if(!m)return '';return I.date(new Date(+m[1],+m[2]-1,+m[3],m[4]?+m[4]:12,m[5]?+m[5]:0),o);};
const fmtT=hm=>{const m=/^(\d{1,2}):(\d{2})/.exec(hm||'');if(!m)return hm||'';const d=new Date();d.setHours(+m[1],+m[2],0,0);return I.time(d);};
I.apply(document);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const de=n=>I.num(n);
const LS={get(k,d){try{const v=localStorage.getItem(k);return v==null?d:v;}catch(e){return d;}},set(k,v){try{if(v==null)localStorage.removeItem(k);else localStorage.setItem(k,v);}catch(e){}},
  json(k,d){try{const v=JSON.parse(localStorage.getItem(k)||'null');return v==null?d:v;}catch(e){return d;}}};
let KEY=LS.get('ch_api_key',''), ME=null, CATS=null, MODS=[], TABS=[], ROUTE='heute', SUBMOD='', A=null, ONLINE=navigator.onLine!==false, LAST_OK=0, BOOTED=false;
const D={orders:null,orderDrivers:[],orderLinks:null,appts:null,rides:null,rideDrivers:[],rideLinks:null,hotelReq:null,hotelBook:null,hotelLinks:null,callbacks:null,calls:null,contacts:null,contactsQ:'',inbox:null,unread:0,push:null,pushState:'',deviceHash:''};
const LOADED={contacts:0,calls:0};
let FEED_FILTER=LS.get('ch_app_filter','alle'), SEEN=new Set(LS.json('ch_app_seen',[])), FIRST_LOAD=true;

/* ---------- Symbole ---------- */
const P={
  heute:'<path d="M3.5 10.5 12 3.8l8.5 6.7"/><path d="M5.5 9v10.2c0 .4.3.8.8.8H10v-5.5h4V20h3.7c.5 0 .8-.4.8-.8V9"/>',
  bag:'<path d="M5.5 8h13l-1 12.2a1 1 0 0 1-1 .8h-9a1 1 0 0 1-1-.8z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/>',
  cal:'<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/><path d="M8 14h3"/>',
  car:'<path d="M5 15.5 6.6 9.8A2 2 0 0 1 8.5 8.4h7a2 2 0 0 1 1.9 1.4L19 15.5"/><rect x="3.5" y="15" width="17" height="4" rx="1.5"/><path d="M6.5 19v1.5M17.5 19v1.5M9.5 5.5h5"/><circle cx="7.5" cy="17" r=".6" fill="currentColor"/><circle cx="16.5" cy="17" r=".6" fill="currentColor"/>',
  bed:'<path d="M3 19V6M3 15h18v4"/><path d="M21 15v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="11.5" r="1.8"/>',
  cb:'<path d="M6 4h2.6l1.6 4-2 1.3a10 10 0 0 0 4.6 4.6l1.3-2 4 1.6V16a2 2 0 0 1-2 2A14 14 0 0 1 4 6a2 2 0 0 1 2-2z"/><path d="M15 3.5h5.5V9M20.5 3.5 15 9"/>',
  phone:'<path d="M6 4h2.6l1.6 4-2 1.3a10 10 0 0 0 4.6 4.6l1.3-2 4 1.6V16a2 2 0 0 1-2 2A14 14 0 0 1 4 6a2 2 0 0 1 2-2z"/>',
  users:'<circle cx="9" cy="8" r="3.4"/><path d="M2.8 20a6.3 6.3 0 0 1 12.4 0"/><path d="M15.5 4.8a3.4 3.4 0 0 1 0 6.4M17.8 14.2A6.3 6.3 0 0 1 21.2 20"/>',
  bell:'<path d="M6 16v-4.8a6 6 0 1 1 12 0V16l1.6 2.2H4.4z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>',
  more:'<circle cx="5.5" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="18.5" cy="12" r="1.6" fill="currentColor" stroke="none"/>',
  stack:'<path d="M12 3.5 3.5 8 12 12.5 20.5 8z"/><path d="m3.5 12.2 8.5 4.5 8.5-4.5M3.5 16.4l8.5 4.5 8.5-4.5"/>',
  chat:'<path d="M4.5 19.5l1.2-3.6A7.8 7.8 0 1 1 8.6 18.6z"/><path d="M9 10.5h6M9 13.5h4"/>',
  check:'<path d="M5 12.5l4.5 4.5L19 7.5"/>', x:'<path d="M6 6l12 12M18 6 6 18"/>', chev:'<path d="M9.5 6l6 6-6 6"/>',
  pin:'<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
  clock:'<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>', search:'<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
  send:'<path d="M4 12 20 4l-4.5 16-3.5-6.5z"/><path d="M12 13.5 20 4"/>', ext:'<path d="M14 4.5h5.5V10M19.5 4.5 11 13"/><path d="M18 14v4.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1H10"/>',
  grid:'<rect x="4" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"/>',
  spark:'<path d="M12 3.5 13.9 9 19.5 11 13.9 13 12 18.5 10.1 13 4.5 11 10.1 9z"/><path d="M18.5 3.5v3M17 5h3"/>',
  moon:'<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>', out:'<path d="M14.5 4.5H18a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-3.5"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
  dl:'<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5"/><path d="M5 19.5h14"/>', board:'<rect x="3.5" y="4.5" width="17" height="12" rx="2"/><path d="M9 20h6M12 16.5V20"/>',
  vol:'<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>', vib:'<rect x="8" y="3.5" width="8" height="17" rx="2"/><path d="M4.5 8v8M19.5 8v8"/>',
  layers:'<path d="M4 7h16M4 12h16M4 17h10"/>', mail:'<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4 7 8 6 8-6"/>', key:'<circle cx="8" cy="15" r="3.5"/><path d="m10.5 12.5 8-8M16 7l2.5 2.5"/>',
  share:'<path d="M12 3.5v11M8 7.5l4-4 4 4"/><path d="M7 11H5.5v9.5h13V11H17"/>', plus:'<path d="M12 5v14M5 12h14"/>', block:'<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
  info:'<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/>', globe:'<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.4 3.6 5.2 3.6 8.5s-1.2 6.1-3.6 8.5c-2.4-2.4-3.6-5.2-3.6-8.5s1.2-6.1 3.6-8.5z"/>', note:'<path d="M5 4.5h10l4 4V19a.5.5 0 0 1-.5.5h-13A.5.5 0 0 1 5 19z"/><path d="M14.5 4.5v4.5h4.5M8.5 13h7M8.5 16h5"/>'
};
const ic=(n,c)=>'<svg class="ico'+(c?' '+c:'')+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(P[n]||P.info)+'</svg>';

/* ---------- Module (gleiche Zuordnung wie im Studio: nur, was der Betrieb nutzt) ---------- */
const MOD_DEF={
  bestellungen:{label:_T('a.m.bestellungen'),icon:'bag',tone:'gold',plans:['agent-bestellung','gastro-agent']},
  termine:{label:_T('a.m.termine'),icon:'cal',tone:'violet',plans:['agent-termine','agent-beauty','praxis-agent','agent-autohaus','praxis-termine','gastro-agent']},
  taxi:{label:_T('a.m.taxi'),icon:'car',tone:'blue',plans:['agent-taxi']},
  hotel:{label:_T('a.m.hotel'),icon:'bed',tone:'teal',plans:['agent-hotel']},
  rueckruf:{label:_T('a.m.rueckruf'),icon:'cb',tone:'rose',plans:['agent-phone','immo-agent','agent-hausverwaltung','agent-versicherung','agent-kanzlei','agent-steuer','agent-inkasso','agent-bank','agent-behoerde','agent-aesthetic','agent-hair-tr','agent-dental-tr','praxis-agent','agent-autohaus','agent-hotel','agent-sales','privat-anruf']}
};
const MOD_ORDER=['bestellungen','termine','taxi','hotel','rueckruf'];
const ROUTE_ALIAS={home:'heute',phone:'heute',whatsapp:'kunden',assistant:'mehr',tokens:'mehr',agents:'mehr',meldungen:'benachrichtigungen',fahrten:'taxi',rueckrufe:'rueckruf',auftraege:''};
function allOn(){return LS.get('ch_nav_all','0')==='1';}
function planBase(id){const m=/^(buy|betrieb|byok)-(.+)$/.exec(id||'');if(!m)return id;for(const c of ['agent-'+m[2],m[2]+'-agent',m[2]]){if(Object.values(MOD_DEF).some(d=>d.plans.includes(c)))return c;}
  const cp=(CATS||[]).flatMap(c=>c.plans||[]).find(p=>p.id===id);return cp&&cp.base?cp.base:id;}
function focusPlans(){const s=new Set();(ME&&ME.plans||[]).filter(p=>p.active).forEach(p=>s.add(p.base||planBase(p.id)));
  LS.json('ch_focus',[]).filter(x=>typeof x==='string').forEach(id=>s.add(planBase(id)));return [...s].filter(Boolean);}
function hasPhone(){return !!(ME&&((ME.channels||[]).includes('phone')||(ME.configs||[]).some(c=>+c.phone_on)));}
function computeMods(){const F=focusPlans(),all=allOn();MODS=MOD_ORDER.filter(k=>all||F.some(id=>MOD_DEF[k].plans.includes(id)));
  if(!SUBMOD||!MODS.includes(SUBMOD))SUBMOD=MODS[0]||'';}

/* ---------- Bridge (Push, Haptik, native App) ---------- */
function haptic(k){if(LS.get('ch_app_vib','1')!=='1')return;try{if(A&&A.haptic)A.haptic(k);else if(navigator.vibrate)navigator.vibrate(k==='success'?[12,60,18]:8);}catch(e){}}
function cap(name){try{const C=window.Capacitor;return C&&C.isNativePlatform&&C.isNativePlatform()&&C.Plugins?C.Plugins[name]||null:null;}catch(e){return null;}}
const IS_NATIVE=(()=>{try{return !!(window.Capacitor&&window.Capacitor.isNativePlatform&&window.Capacitor.isNativePlatform());}catch(e){return false;}})();
const UA=navigator.userAgent||'', IS_IOS=/iPad|iPhone|iPod/.test(UA)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1), IS_ANDROID=/Android/i.test(UA);
const STANDALONE=(()=>{try{return navigator.standalone===true||matchMedia('(display-mode: standalone)').matches||matchMedia('(display-mode: minimal-ui)').matches;}catch(e){return false;}})();
function devLabel(){if(IS_NATIVE)return 'ChatHelp-App · '+(IS_IOS?'iPhone':'Android');return 'Business-App · '+(IS_IOS?'iPhone':IS_ANDROID?'Android':'Browser');}

/* ---------- API ---------- */
async function api(path,o={}){
  const ctl=typeof AbortController==='function'?new AbortController():null, t=setTimeout(()=>{try{ctl&&ctl.abort();}catch(e){}},o.timeout||15000);
  try{
    const r=await fetch(API+path+(path.includes('?')?'&':'?')+'_='+Date.now(),{method:o.method||'GET',cache:'no-store',signal:ctl?ctl.signal:undefined,
      headers:Object.assign({'Content-Type':'application/json'},KEY?{Authorization:'Bearer '+KEY}:{}),body:o.body!==undefined?JSON.stringify(o.body):undefined});
    const j=await r.json().catch(()=>({}));
    setOnline(true);
    if(!r.ok){const e=new Error(I.te({data:Object.assign({},j,{message:j.message||j.hint||''})},(j.error==='unauthorized'?_T('a.relogin'):'')||_T('a.errN',{n:r.status})));e.status=r.status;e.data=j;throw e;}
    return j;
  }catch(e){if(e&&e.status)throw e;setOnline(false);const x=new Error(_T('a.noNet'));x.offline=true;throw x;}
  finally{clearTimeout(t);}
}
const post=(p,b)=>api(p,{method:'POST',body:b||{}});

/* ---------- Zeit ---------- */
const pad=n=>String(n).padStart(2,'0');
function ymd(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());}
function addDays(d,n){const x=new Date(d);x.setDate(x.getDate()+n);return x;}
function ago(ts){if(!ts)return '';const s=Math.round((Date.now()-ts)/1000);if(s<45)return _T('a.justNow');if(s<3600)return _T('a.minAgo',{n:I.num(Math.max(1,Math.round(s/60)))});
  const d=new Date(ts),n=new Date(),hm=DE_UI?pad(d.getHours())+':'+pad(d.getMinutes()):I.time(d);if(ymd(d)===ymd(n))return _T('a.todayAt',{t:hm});if(ymd(d)===ymd(addDays(n,-1)))return _T('a.yestAt',{t:hm});
  return I.date(d,{day:'2-digit',month:'2-digit'})+' '+hm;}
function hmToday(hm){const m=/^(\d{1,2}):(\d{2})/.exec(hm||'');if(!m)return 0;const d=new Date();d.setHours(+m[1],+m[2],0,0);if(d.getTime()-Date.now()>5*60000)d.setDate(d.getDate()-1);return d.getTime();}
function dayWord(dateStr){const t=ymd(new Date());if(dateStr===t)return _T('a.today');if(dateStr===ymd(addDays(new Date(),1)))return _T('a.tomorrow');
  try{const [y,m,d]=dateStr.split('-').map(Number);return I.date(new Date(y,m-1,d),{weekday:'long',day:'numeric',month:'long'});}catch(e){return dateStr;}}
function greet(){const h=new Date().getHours();return _T(h<5?'a.g.night':h<11?'a.g.morning':h<17?'a.g.day':h<22?'a.g.evening':'a.g.night');}
const iso=s=>{const t=Date.parse(s||'');return isNaN(t)?0:t;};
const tel=p=>'tel:'+String(p||'').replace(/[^\d+]/g,'');
const eur=c=>I.eur(Number(c||0)/100);
const initials=s=>{const w=String(s||'').replace(/[^\p{L}\p{N} ]/gu,'').trim().split(/\s+/).filter(Boolean);return (w.length?(w[0][0]+(w.length>1?w[w.length-1][0]:'')):'#').toUpperCase();};
function relLink(u){try{const x=new URL(u,location.href);if(/(^|\.)chat-help\.com$/.test(x.hostname)&&location.hostname!==x.hostname)return x.pathname+x.search+x.hash;return x.href;}catch(e){return u;}}

function hideBoot(){const b=$('#boot');if(!b||b.classList.contains('out'))return;b.classList.add('out');setTimeout(()=>{b.hidden=true;},300);}
/* ---------- Hinweise ---------- */
let toastT=null;
function toast(title,body,kind,onTap){const el=$('#toast');el.className=kind||'';el.innerHTML='<div class="bd"><b>'+esc(title)+'</b>'+(body?'<span class="small mute">'+esc(body)+'</span>':'')+'</div>';
  el.onclick=()=>{el.classList.remove('on');if(onTap)onTap();};requestAnimationFrame(()=>el.classList.add('on'));clearTimeout(toastT);toastT=setTimeout(()=>el.classList.remove('on'),kind==='err'?6000:4200);}
let audioCtx=null;
function chime(){if(LS.get('ch_app_sound','1')!=='1')return;try{audioCtx=audioCtx||new (window.AudioContext||window.webkitAudioContext)();const t=audioCtx.currentTime;
  [[880,0],[1318.5,.13]].forEach(([f,d])=>{const o=audioCtx.createOscillator(),g=audioCtx.createGain();o.type='sine';o.frequency.value=f;g.gain.setValueAtTime(0,t+d);g.gain.linearRampToValueAtTime(.18,t+d+.02);g.gain.exponentialRampToValueAtTime(.0001,t+d+.5);o.connect(g);g.connect(audioCtx.destination);o.start(t+d);o.stop(t+d+.55);});}catch(e){}}
document.addEventListener('pointerdown',()=>{try{if(audioCtx&&audioCtx.state==='suspended')audioCtx.resume();}catch(e){}},{passive:true});

/* ---------- Online / Offline ---------- */
function setOnline(on){if(on)LAST_OK=Date.now();if(ONLINE===on&&$('#net').hidden===on)return paintLive();ONLINE=on;$('#net').hidden=on;
  if(!on){const t=LS.json('ch_app_snap',null);$('#netT').textContent=t&&t.t?_T('a.net.offAt',{t:I.time(new Date(t.t))}):_T('a.net.off');}
  paintLive();}
function paintLive(busy){const l=$('#live');if(!l)return;l.className='live'+(!ONLINE?' off':busy?' busy':'');$('#liveT').textContent=_T(!ONLINE?'a.live.off':busy?'a.live.busy':'a.live.on');}
window.addEventListener('online',()=>{setOnline(true);refresh();});
window.addEventListener('offline',()=>setOnline(false));
function snapSave(){try{LS.set('ch_app_snap',JSON.stringify({t:Date.now(),email:ME&&ME.email,me:ME,d:{orders:D.orders,orderDrivers:D.orderDrivers,appts:D.appts,rides:D.rides,rideDrivers:D.rideDrivers,hotelReq:D.hotelReq,hotelBook:D.hotelBook,callbacks:D.callbacks,calls:(D.calls||[]).slice(0,15).map(c=>Object.assign({},c,{transcript:(c.transcript||[]).slice(0,12)})),contacts:(D.contacts||[]).slice(0,60),inbox:D.inbox,unread:D.unread}}));}catch(e){}}
function snapLoad(){const s=LS.json('ch_app_snap',null);if(!s||!s.d||(ME&&s.email&&s.email!==ME.email))return false;Object.assign(D,s.d);if(!ME&&s.me)ME=s.me;return true;}

/* =====================================================================================
   Login (wie im Studio: E-Mail → Code → Schlüssel)
   ===================================================================================== */
let lEmail='';
function lmsg(t,k){$('#lMsg').innerHTML=t?'<div class="msg '+(k||'')+'">'+t+'</div>':'';}
function lstep(s){['stepEmail','stepCode','stepKey'].forEach(id=>$('#'+id).hidden=id!==s);$('#lKeyShow').hidden=s==='stepKey';}
const codeOf=t=>{const m=String(t||'').match(/(?:\d[\s\u00a0.-]?){6}/);return m?m[0].replace(/\D/g,'').slice(0,6):String(t||'').replace(/\D/g,'').slice(0,6);};
function busyBtn(b,on,label){if(!b)return;if(on){b.dataset.l=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spin"></span>'+(label||'');}else{b.disabled=false;if(b.dataset.l!=null)b.innerHTML=b.dataset.l;}}
$('#stepEmail').addEventListener('submit',async e=>{e.preventDefault();lEmail=$('#lEmail').value.trim();if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(lEmail)){lmsg(esc(_T('a.l.badEmail')),'err');return;}
  const b=$('#lSend');busyBtn(b,true,esc(_T('a.l.sending')));lmsg('');
  try{const j=await post('/auth/request',{email:lEmail,lang:I.lang});lstep('stepCode');$('#lTo').textContent=_T('a.l.sentTo',{e:lEmail});
    lmsg(esc(_T(j.sent===false?'a.l.sendFail':'a.l.checkMail')),j.sent===false?'err':'ok');setTimeout(()=>$('#lCode').focus(),60);}
  catch(x){lmsg(esc(x.message),'err');}busyBtn(b,false);});
$('#lCode').addEventListener('input',()=>{const el=$('#lCode'),v=el.value.replace(/\D/g,'').slice(0,6);if(el.value!==v)el.value=v;if(v.length===6)verify();});
$('#lCode').addEventListener('paste',e=>{const t=(e.clipboardData||window.clipboardData);const s=t&&t.getData?t.getData('text'):null;if(s==null)return;e.preventDefault();$('#lCode').value=codeOf(s);$('#lCode').dispatchEvent(new Event('input'));});
$('#lPaste').addEventListener('click',async()=>{try{const c=codeOf(await navigator.clipboard.readText());if(c.length!==6){lmsg(esc(_T('a.l.noCode')),'err');return;}$('#lCode').value=c;$('#lCode').dispatchEvent(new Event('input'));}
  catch(e){lmsg(esc(_T('a.l.pasteDenied')),'err');$('#lCode').focus();}});
if(!(navigator.clipboard&&navigator.clipboard.readText))$('#lPaste').hidden=true;
$('#stepCode').addEventListener('submit',e=>{e.preventDefault();verify();});
let verifying=false;
async function verify(){const code=$('#lCode').value.replace(/\D/g,'');if(code.length!==6||verifying)return;verifying=true;const b=$('#lVerify');busyBtn(b,true,esc(_T('a.l.checking')));
  try{const j=await post('/auth/verify',{email:lEmail,code});KEY=j.key;LS.set('ch_api_key',KEY);LS.set('ch_email',j.email);haptic('success');lmsg('');await boot();}
  catch(x){lmsg(esc(x.message),'err');$('#lCode').value='';haptic('error');}busyBtn(b,false);verifying=false;}
$('#lBack').addEventListener('click',()=>{lstep('stepEmail');lmsg('');});
$('#lResend').addEventListener('click',()=>{$('#lEmail').value=lEmail;$('#stepEmail').requestSubmit?$('#stepEmail').requestSubmit():$('#lSend').click();});
$('#lKeyShow').addEventListener('click',()=>{lstep('stepKey');lmsg('');});
$('#lKeyBack').addEventListener('click',()=>{lstep('stepEmail');lmsg('');});
$('#stepKey').addEventListener('submit',async e=>{e.preventDefault();const k=$('#lKey').value.trim();if(!/^ch_(live|test)_[a-f0-9]{48}$/.test(k)){lmsg(esc(_T('a.l.keyFmt')),'err');return;}KEY=k;LS.set('ch_api_key',KEY);await boot();});
/* Ein-Tipp-Übernahme aus der Login-Mail (#code=123456&e=<base64url>) – wie im Studio nur vorausfüllen */
const LINK_LOGIN=(()=>{try{const h=new URLSearchParams(location.hash.slice(1));const c=(h.get('code')||'').replace(/\D/g,'');const e=h.get('e');if(c.length!==6||!e)return null;let b=e.replace(/-/g,'+').replace(/_/g,'/');while(b.length%4)b+='=';
  const em=decodeURIComponent(escape(atob(b)));history.replaceState(null,'',location.pathname+location.search);return /.+@.+\..+/.test(em)?{code:c,email:em}:null;}catch(_){return null;}})();
function showLogin(m,k){$('#vApp').hidden=true;$('#vLogin').hidden=false;hideBoot();if(m)lmsg(m,k);
  if(LINK_LOGIN){lEmail=LINK_LOGIN.email;$('#lEmail').value=lEmail;lstep('stepCode');$('#lTo').textContent=_T('a.l.codeFor',{e:lEmail});$('#lCode').value=LINK_LOGIN.code;lmsg(esc(_T('a.l.fromLink')),'ok');}
  else{const em=LS.get('ch_email','');if(em&&!$('#lEmail').value)$('#lEmail').value=em;}}
function logout(msg){LS.set('ch_api_key',null);LS.set('ch_app_snap',null);LS.set('ch_app_seen',null);KEY='';ME=null;for(const k in D)if(Array.isArray(D[k]))D[k]=[];
  D.orders=D.appts=D.rides=D.hotelReq=D.hotelBook=D.callbacks=D.calls=D.contacts=D.inbox=null;stopPolling();closeSheet(true);lstep('stepEmail');showLogin(msg,msg?'err':'');}

/* =====================================================================================
   Start
   ===================================================================================== */
async function boot(){
  if(LINK_LOGIN&&!boot.done){boot.done=1;if(!KEY||LS.get('ch_email','').toLowerCase()!==LINK_LOGIN.email.toLowerCase()){showLogin();return;}}
  if(!KEY){showLogin();return;}
  let me=null;
  try{[me,CATS]=await Promise.all([api('/me'),api('/categories').then(j=>j.categories||[]).catch(()=>null)]);}
  catch(e){if(e.status===401){logout(esc(_T('a.expired')));return;}
    if(e.offline&&snapLoad()){ME=ME||{email:LS.get('ch_email',''),plans:[],channels:[]};}else if(e.offline){showLogin(esc(_T('a.noConn')),'err');return;}else{showLogin(esc(e.message),'err');return;}}
  if(me){ME=me;LS.set('ch_email',me.email);if(!snapLoad.done){snapLoad.done=1;const s=LS.json('ch_app_snap',null);if(s&&s.email===me.email&&s.d)Object.assign(D,s.d);}}
  computeMods();buildTabs();
  $('#vLogin').hidden=true;$('#vApp').hidden=false;hideBoot();BOOTED=true;
  initBridge();
  const q=new URLSearchParams(location.search);
  let r=(location.hash||'').slice(1);r=ROUTE_ALIAS[r]!==undefined?ROUTE_ALIAS[r]:r;if(q.get('focus')==='push')r='benachrichtigungen';
  go(validRoute(r)?r:'heute',{silent:true});
  if(q.get('tag'))pendingDeep={tag:q.get('tag')||'',nid:0};
  if(+q.get('nid')>0)post('/push/read',{ids:[+q.get('nid')]}).then(x=>setUnread(x.unread)).catch(()=>{});
  if(q.has('tag')||q.has('nid')||q.has('focus')){q.delete('tag');q.delete('nid');q.delete('focus');try{history.replaceState(history.state,'',location.pathname+(q.toString()?'?'+q:'')+location.hash);}catch(e){}}
  await refresh({first:true});
  startPolling();
}
function validRoute(r){return ['heute','kunden','benachrichtigungen','mehr'].includes(r)||MODS.includes(r)||(r&&MOD_DEF[r]&&allOn());}

/* ---------- Tab-Leiste ---------- */
function buildTabs(){
  TABS=[{k:'heute',label:_T('a.tab.heute'),icon:'heute'}];
  if(MODS.length===1)TABS.push({k:MODS[0],label:MOD_DEF[MODS[0]].label,icon:MOD_DEF[MODS[0]].icon,mod:true});
  else if(MODS.length>1)TABS.push({k:'auftraege',label:_T('a.tab.auftraege'),icon:'stack',mod:true});
  TABS.push({k:'kunden',label:_T('a.tab.kunden'),icon:'users'},{k:'benachrichtigungen',label:_T('a.tab.meldungen'),icon:'bell'},{k:'mehr',label:_T('a.tab.mehr'),icon:'more'});
  $('#tabbar').innerHTML=TABS.map(t=>'<button class="tab" role="tab" type="button" data-tab="'+t.k+'" aria-label="'+esc(t.label)+'">'+ic(t.icon)+'<span class="t">'+esc(t.label)+'</span>'+
    (t.k==='benachrichtigungen'?'<span class="bdg" data-ch-unread style="display:none"></span>':t.mod?'<span class="bdg" data-mod-bdg style="display:none"></span>':'')+'</button>').join('');
  $$('#tabbar .tab').forEach(b=>b.addEventListener('click',()=>{const k=b.dataset.tab;haptic('select');if(k==='auftraege')go(SUBMOD||MODS[0]);else if(ROUTE===k||(k==='auftraege'&&MODS.includes(ROUTE)))window.scrollTo({top:0,behavior:'smooth'});else go(k);}));
  paintBadges();
}
function tabFor(r){return MODS.includes(r)||MOD_DEF[r]?(MODS.length===1&&MODS[0]===r?r:'auftraege'):r;}

/* ---------- Navigation ---------- */
const SCROLL={};
function go(r,o={}){
  r=ROUTE_ALIAS[r]!==undefined&&ROUTE_ALIAS[r]!==''?ROUTE_ALIAS[r]:r;
  if(!validRoute(r))r='heute';
  const prevPage=pageOf(ROUTE);SCROLL[prevPage]=window.scrollY;
  ROUTE=r;if(MOD_DEF[r])SUBMOD=r;
  const page=pageOf(r);
  $$('.page').forEach(p=>p.classList.toggle('on',p.id==='p-'+page));
  const t=tabFor(r);$$('#tabbar .tab').forEach(b=>{const on=b.dataset.tab===t;b.classList.toggle('on',on);b.setAttribute('aria-selected',on?'true':'false');});
  try{history.replaceState(history.state,'',location.pathname+location.search+'#'+r);}catch(e){}
  render();
  window.scrollTo(0,o.keepScroll?SCROLL[page]||0:(page===prevPage?window.scrollY:SCROLL[page]||0));
  if(r==='kunden'&&(!D.contacts||Date.now()-LOADED.contacts>60000))loadContacts().then(()=>render()).catch(()=>{});
  if(r==='benachrichtigungen')loadPush().then(()=>render()).catch(()=>{});
}
window.go=go;   // app-bridge.js: gleiche Seite + #bereich → ohne Neuladen wechseln
function pageOf(r){return MOD_DEF[r]?'auftraege':r;}
window.addEventListener('hashchange',()=>{const h=(location.hash||'').slice(1);if(BOOTED&&h&&h!==ROUTE&&!sheetOpen&&Date.now()-SHEET_CLOSED>600)go(h);});
window.addEventListener('scroll',()=>{$('#top').classList.toggle('sc',window.scrollY>4);},{passive:true});

/* =====================================================================================
   Daten laden
   ===================================================================================== */
let refreshing=null;
async function refresh(o={}){
  if(!KEY)return;
  if(refreshing)return refreshing;
  paintLive(true);
  const today=ymd(new Date()), in6=ymd(addDays(new Date(),6)), in14=ymd(addDays(new Date(),14));
  const jobs=[];
  const job=(p,fn)=>jobs.push(p.then(fn).catch(e=>{if(e.status===401)throw e;}));
  if(MODS.includes('bestellungen'))job(api('/order/orders?scope=open'),j=>{D.orders=j.orders||[];D.orderDrivers=j.drivers||[];});
  if(MODS.includes('termine'))job(api('/appt/bookings?from='+today+'&to='+in6),j=>{D.appts=j.bookings||[];});
  if(MODS.includes('taxi'))job(api('/ride/rides?scope=open'),j=>{D.rides=j.rides||[];D.rideDrivers=j.drivers||[];});
  if(MODS.includes('hotel')){job(api('/hotel/requests?scope=open'),j=>{D.hotelReq=j.requests||[];});job(api('/hotel/bookings?from='+today+'&to='+in14),j=>{D.hotelBook=j.bookings||[];});}
  if(MODS.includes('rueckruf'))job(api('/callbacks?scope=open'),j=>{D.callbacks=j.callbacks||[];});
  if(hasPhone()&&(o.first||o.full||Date.now()-LOADED.calls>60000))job(api('/phone/calls'),j=>{D.calls=(j.calls||[]).slice(0,40);LOADED.calls=Date.now();});
  if(o.first||o.full||ROUTE==='kunden'||Date.now()-LOADED.contacts>90000)job(loadContacts(),()=>{});
  job(api('/push/inbox?limit=40'),j=>{D.inbox=j.items||[];setUnread(j.unread);});
  if(o.first||o.full||ROUTE==='benachrichtigungen')job(loadPush(),()=>{});
  refreshing=Promise.all(jobs).then(()=>{
    detectNew();snapSave();render();
    if(pendingDeep)openDeep(pendingDeep);
  }).catch(e=>{if(e&&e.status===401)logout(esc(_T('a.expired')));})
    .finally(()=>{refreshing=null;paintLive(false);if(!ONLINE){render();}});
  return refreshing;
}
async function loadContacts(){const q=D.contactsQ?'&q='+encodeURIComponent(D.contactsQ):'';const j=await api('/contacts/list?limit=150'+q);D.contacts=j.contacts||[];D.contactsMeta={wa:!!j.whatsapp_ready,sms:!!j.sms_ready};LOADED.contacts=Date.now();return j;}
async function loadPush(){try{D.push=await api('/push/config');setUnread(D.push.unread);}catch(e){if(e.status===401)throw e;}
  if(A){try{const r=await Promise.all([A.pushState(),A.deviceHash()]);D.pushState=r[0];D.deviceHash=r[1]||'';}catch(e){}}else D.pushState='unsupported';}
let pollT=null;
function startPolling(){stopPolling();pollT=setInterval(()=>{if(document.visibilityState==='visible'&&!$('#sheet').classList.contains('on'))refresh();},20000);}
function stopPolling(){if(pollT)clearInterval(pollT);pollT=null;}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&BOOTED&&KEY&&Date.now()-LAST_OK>8000)refresh();});

/* ---------- Ungelesen / Badges ---------- */
function setUnread(n){n=Math.max(0,parseInt(n,10)||0);D.unread=n;if(A&&A.setUnread)A.setUnread(n);else $$('[data-ch-unread]').forEach(el=>{el.textContent=n>99?'99+':String(n);el.style.display=n>0?'':'none';});}
function paintBadges(){const n=Object.values(actionCounts()).reduce((a,b)=>a+b,0);$$('[data-mod-bdg]').forEach(el=>{el.textContent=n>99?'99+':String(n);el.style.display=n>0?'':'none';});
  if(A&&A.setUnread)A.setUnread(D.unread);else setUnread(D.unread);}

/* =====================================================================================
   Modelle je Modul (Feed-Einträge, Aktionen)
   ===================================================================================== */
const ORD_LABEL={};['new','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'].forEach(x=>{ORD_LABEL[x]=_T('a.o.'+x);});
const ORD_TONE={new:'gold',confirmed:'blue',preparing:'violet',ready:'ok',out_for_delivery:'blue',delivered:'ok',cancelled:'rose'};
function ordNext(o){const d=o.type==='delivery';return {new:['confirmed',_T('a.oa.confirm')],confirmed:['preparing',_T('a.oa.prepare')],preparing:['ready',_T('a.oa.ready')],ready:d?['out_for_delivery',_T('a.oa.go')]:['delivered',_T('a.oa.picked')],out_for_delivery:['delivered',_T('a.oa.delivered')]}[o.status]||null;}
const RIDE_TONE={new:'gold',assigned:'blue',en_route:'blue',arrived:'violet',onboard:'violet',completed:'ok',cancelled:'rose',no_show:'rose'};
function rideNext(r){return {new:['assigned',_T('a.ra.assign')],assigned:['en_route',_T('a.ra.start')],en_route:['arrived',_T('a.ra.arrived')],arrived:['onboard',_T('a.ra.onboard')],onboard:['completed',_T('a.ra.done')]}[r.status]||null;}
const HB_LABEL={};['requested','confirmed','declined','cancelled','checked_in','checked_out','no_show'].forEach(x=>{HB_LABEL[x]=_T('a.hb.'+x);});
const CB_TONE={open:'gold',scheduled:'blue',calling:'violet',done:'ok',failed:'rose',cancelled:'mute'};
const CH_LABEL={whatsapp:'WhatsApp',phone:_T('a.ch.phone'),widget:_T('a.ch.web'),web:_T('a.ch.web'),chat:'Chat',studio:'Studio',form:_T('a.ch.form'),api:'API',demo:'Demo'};
/** Abholzeit einer Fahrt: Deutsch wie vom Server („Fr, 24.10. 05:30“), sonst aus pickup_at über Intl */
function rideWhen(r){if(DE_UI||!r.pickup_at)return X(r.when||r.pickup_time);return fmtD(r.pickup_at,{weekday:'short',day:'2-digit',month:'2-digit'})+' '+fmtT(String(r.pickup_at).slice(11,16));}
/** Termin lang: Deutsch wie vom Server („Fr, 24.10.2026 um 9:30 Uhr“), sonst Datum + Uhrzeit über Intl */
function apptHuman(b){if(DE_UI)return b.human;return fmtD(b.date,{weekday:'short',day:'numeric',month:'long',year:'numeric'})+' · '+fmtT(b.time);}
function apptTs(b){const [y,m,d]=(b.date||'').split('-').map(Number);const [h,mi]=(b.time||'0:0').split(':').map(Number);return new Date(y,(m||1)-1,d||1,h||0,mi||0).getTime();}
function hbToday(b){const t=ymd(new Date());return (b.status==='requested')||(b.status==='confirmed'&&b.checkin<=t)||(b.status==='checked_in'&&b.checkout<=t);}

function feedItems(){
  const out=[];const now=Date.now();
  (D.orders||[]).forEach(o=>{const nx=ordNext(o);out.push({key:'o:'+o.id,code:o.code,mod:'bestellungen',icon:'bag',tone:'gold',ts:hmToday(o.created)||now,
    title:_T(o.type==='delivery'?'a.delivery':'a.pickup')+' · '+(DE_UI?o.total:eur(o.total_cents)),sub:(o.items_text||'')+(o.name?' · '+o.name:''),
    status:ORD_LABEL[o.status]||o.status,stTone:ORD_TONE[o.status]||'',hot:o.status==='new',ch:o.channel,primary:nx?{label:nx[1],run:b=>orderStatus(o,nx[0],b)}:null,open:()=>orderSheet(o)});});
  (D.appts||[]).filter(b=>b.status==='booked'&&b.date===ymd(new Date())).forEach(b=>{const ts=apptTs(b);out.push({key:'a:'+b.id,code:b.code,mod:'termine',icon:'cal',tone:'violet',ts:iso(b.created_at&&b.created_at.replace(' ','T'))||ts,sortTs:ts,
    title:fmtT(b.time)+' · '+b.service,sub:b.name+(b.phone?' · '+b.phone:''),status:ts<now?_T('a.runPast'):_T('a.today'),stTone:ts<now?'':'violet',hot:false,ch:b.source,open:()=>apptSheet(b),
    primary:ts<now?{label:_T('a.done'),run:x=>apptStatus(b,'done',x)}:null});});
  (D.rides||[]).forEach(r=>{const nx=rideNext(r);out.push({key:'r:'+r.id,code:r.code,mod:'taxi',icon:'car',tone:'blue',ts:iso(r.created_iso)||now,
    title:(r.asap?_T('a.asap'):rideWhen(r))+' · '+(r.pickup||''),sub:(r.destination?'→ '+r.destination+' · ':'')+(r.name||'')+(r.passengers?' · '+_T('a.pers',{n:r.passengers}):''),
    status:X(r.status_label||r.status),stTone:RIDE_TONE[r.status]||'',hot:r.status==='new',ch:r.channel,open:()=>rideSheet(r),
    primary:nx?{label:nx[1],run:b=>nx[0]==='assigned'?driverSheet(r):rideStatus(r,nx[0],null,b)}:null});});
  (D.hotelReq||[]).forEach(q=>out.push({key:'hr:'+q.id,code:String(q.id),mod:'hotel',icon:'bed',tone:'teal',ts:iso(q.created_iso)||now,title:(X(q.kind_label)||_T('a.guestWish'))+(q.room_no?' · '+_T('a.roomN',{n:q.room_no}):''),
    sub:(q.details||'')+(q.guest_name?' · '+q.guest_name:''),status:X(q.status_label||q.status),stTone:q.status==='open'?'gold':'blue',hot:q.status==='open',ch:q.channel,open:()=>hreqSheet(q),
    primary:q.status==='open'?{label:_T('a.take'),run:b=>hreqStatus(q,'in_progress',b)}:q.status==='in_progress'?{label:_T('a.done'),run:b=>hreqStatus(q,'done',b)}:null}));
  (D.hotelBook||[]).filter(hbToday).forEach(b=>{const t=ymd(new Date());const act=b.status==='requested'?['confirm',_T('a.hb.act.confirm')]:b.status==='confirmed'?['checkin',_T('a.checkin')]:b.status==='checked_in'?['checkout',_T('a.checkout')]:null;
    out.push({key:'hb:'+b.id,code:b.code,mod:'hotel',icon:'bed',tone:'teal',ts:iso(b.created_iso)||now,title:_T(b.status==='requested'?'a.hbReq':b.status==='confirmed'?'a.arrToday':'a.depToday')+' · '+b.name,
      sub:b.room+' · '+(DE_UI?b.checkin_short:fmtD(b.checkin,{weekday:'short',day:'2-digit',month:'2-digit'}))+' – '+(DE_UI?b.checkout_short:fmtD(b.checkout,{weekday:'short',day:'2-digit',month:'2-digit'}))+' · '+_T('a.nightsN',{n:b.nights})+' · '+(DE_UI?b.total:eur(b.total_cents)),status:HB_LABEL[b.status]||X(b.status_label),stTone:b.status==='requested'?'gold':'blue',hot:b.status==='requested',ch:b.channel,
      open:()=>hbookSheet(b),primary:act?{label:act[1],run:x=>hbookAction(b,act[0],x)}:null});});
  (D.callbacks||[]).forEach(c=>out.push({key:'c:'+c.id,code:c.code,mod:'rueckruf',icon:'cb',tone:'rose',ts:iso(c.created_at)||now,title:_T('a.cbTitle',{n:c.name}),sub:(c.topic||'')+' · '+X(c.preferred_short||c.preferred||''),
    status:c.urgency==='dringend'?_T('a.urgent'):X(c.status_label||c.status),stTone:c.urgency==='dringend'||c.due?'rose':(CB_TONE[c.status]||''),hot:!!c.due,ch:c.source,open:()=>cbSheet(c),
    primary:{label:_T('a.call'),href:tel(c.phone)}}));
  (D.calls||[]).slice(0,25).forEach(c=>{const ts=iso(c.started_at);if(now-ts>36*3600e3)return;const first=(c.transcript||[]).find(m=>m.role==='user');
    out.push({key:'call:'+c.id,mod:'anrufe',icon:'phone',tone:'mute',ts,title:_T('a.callTitle',{n:c.from_number||_T('a.unknown')}),sub:first?_T('a.quote',{x:first.content}):_T('a.answersN',{n:c.turns||0}),
      status:c.outcome==='callback'?_T('a.cbWanted'):c.outcome==='forwarded'?_T('a.forwarded'):(c.status==='completed'?_T('a.ended'):c.status),stTone:c.outcome==='callback'?'rose':'',ch:'phone',open:()=>callSheet(c)});});
  (D.contacts||[]).filter(c=>c.last_channel==='whatsapp'&&now-iso(c.last_seen)<24*3600e3).forEach(c=>out.push({key:'wa:'+c.phone+':'+c.last_seen,mod:'whatsapp',icon:'chat',tone:'ok',ts:iso(c.last_seen),
    title:'WhatsApp · '+(c.name||c.phone_masked),sub:c.open_count?_T('a.openN',{n:c.open_count}):_T('a.waAgent'),status:c.window_open?_T('a.canReply'):'',stTone:c.window_open?'ok':'',ch:'whatsapp',open:()=>contactSheet(c.phone)}));
  return out.sort((a,b)=>(b.hot-a.hot)||((b.sortTs?0:b.ts)-(a.sortTs?0:a.ts))||(a.sortTs||0)-(b.sortTs||0));
}
function actionCounts(){const t=ymd(new Date());return {
  bestellungen:(D.orders||[]).filter(o=>o.status==='new').length,
  termine:0,
  taxi:(D.rides||[]).filter(r=>r.status==='new').length,
  hotel:(D.hotelReq||[]).filter(q=>q.status==='open').length+(D.hotelBook||[]).filter(b=>b.status==='requested').length,
  rueckruf:(D.callbacks||[]).filter(c=>c.due).length};}
function detectNew(){
  const items=feedItems().filter(i=>i.hot||i.mod==='rueckruf');const fresh=[];
  items.forEach(i=>{if(!SEEN.has(i.key)){if(!FIRST_LOAD)fresh.push(i);SEEN.add(i.key);}});
  FIRST_LOAD=false;LS.set('ch_app_seen',JSON.stringify([...SEEN].slice(-400)));
  if(fresh.length){NEWKEYS=new Set(fresh.map(i=>i.key));const f=fresh[0];haptic('success');chime();
    toast(fresh.length>1?_T('a.newN',{n:fresh.length}):_T(['bestellungen','taxi','hotel','rueckruf','termine'].includes(f.mod)?'a.new.'+f.mod:'a.new.x'),f.title,'',()=>f.open());}
}
let NEWKEYS=new Set();

/* =====================================================================================
   Darstellung
   ===================================================================================== */
function render(){
  const page=pageOf(ROUTE);const T={heute:_T('a.tab.heute'),kunden:_T('a.tab.kunden'),benachrichtigungen:_T('a.tab.meldungen'),mehr:_T('a.tab.mehr')};
  $('#pgTitle').textContent=T[page]||(MODS.length>1?_T('a.tab.auftraege'):MOD_DEF[ROUTE]?MOD_DEF[ROUTE].label:_T('a.tab.auftraege'));
  const company=((ME&&ME.configs||[]).find(c=>c.company)||{}).company||'';
  $('#pgSub').textContent=page==='heute'?greet()+(company?' · '+company:''):page==='auftraege'?I.date(new Date(),{weekday:'long',day:'numeric',month:'long'}):page==='mehr'?(ME&&ME.email||''):'';
  if(page==='heute')renderHeute();else if(page==='auftraege')renderMod();else if(page==='kunden')renderKunden();else if(page==='benachrichtigungen')renderPush();else if(page==='mehr')renderMehr();
  paintBadges();
}
function itemHTML(i){const fresh=NEWKEYS.has(i.key);
  return '<div class="it'+(i.hot?' hot':'')+(fresh?' fresh':'')+'" data-key="'+esc(i.key)+'" role="button" tabindex="0">'+
    '<div class="ti t-'+i.tone+'">'+ic(i.icon)+'</div><div class="bd"><div class="tt"><b>'+esc(i.title)+'</b><span class="tm">'+esc(i.sortTs?'':ago(i.ts))+'</span></div>'+
    '<div class="sb">'+esc(i.sub)+'</div><div class="mt">'+(i.status?'<span class="pill '+(i.stTone||'')+(i.hot?' new':'')+'">'+esc(i.status)+'</span>':'')+(i.ch&&CH_LABEL[i.ch]?'<span class="pill">'+esc(CH_LABEL[i.ch])+'</span>':'')+'</div>'+
    (i.primary?'<div class="acts" style="margin-top:10px">'+(i.primary.href?'<a class="btn s p" href="'+esc(i.primary.href)+'" data-stop>'+ic('phone','s')+esc(i.primary.label)+'</a>':'<button class="btn s p" type="button" data-primary>'+esc(i.primary.label)+'</button>')+'</div>':'')+
    '</div></div>';}
function bindItems(root,items){const map=new Map(items.map(i=>[i.key,i]));
  $$('.it[data-key]',root).forEach(el=>{const i=map.get(el.dataset.key);if(!i)return;
    el.addEventListener('click',e=>{if(e.target.closest('[data-stop]'))return;const pb=e.target.closest('[data-primary]');if(pb){e.stopPropagation();haptic('medium');i.primary.run(pb);return;}haptic('light');i.open();});
    el.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();i.open();}});});}
function skel(n){return '<div class="list">'+Array.from({length:n},()=>'<div class="skel"></div>').join('')+'</div>';}

/* ---------- Heute ---------- */
function pushBanner(){
  const st=D.pushState;if(!A&&!IS_NATIVE)return '';
  const mine=D.push&&D.deviceHash?(D.push.devices||[]).find(d=>d.h===D.deviceHash):null;
  if(st==='subscribed'&&(mine||!D.push))return '';
  if(st==='denied')return '<div class="hero warn" style="margin-top:4px"><h3>'+esc(_T('a.pb.denied.t'))+'</h3><p>'+esc(_T('a.pb.denied.p'))+'</p><button class="btn s o" type="button" data-go="benachrichtigungen">'+esc(_T('a.pb.denied.b'))+'</button></div>';
  if(IS_IOS&&!STANDALONE&&!IS_NATIVE)return '<div class="hero" style="margin-top:4px"><h3>'+esc(_T('a.pb.ios.t'))+'</h3><p>'+esc(_T('a.pb.ios.p'))+'</p><button class="btn s p" type="button" data-iosguide>'+ic('share','s')+esc(_T('a.pb.ios.b'))+'</button></div>';
  if(st==='unsupported')return '';
  return '<div class="hero" style="margin-top:4px"><h3>'+esc(_T('a.pb.t'))+'</h3><p>'+esc(_T('a.pb.p'))+'</p><button class="btn g" type="button" data-enablepush>'+ic('bell','s')+esc(_T('a.enablePush'))+'</button></div>';
}
function renderHeute(){
  const el=$('#p-heute');const loading=MODS.length&&MODS.some(k=>({bestellungen:D.orders,termine:D.appts,taxi:D.rides,hotel:D.hotelReq,rueckruf:D.callbacks})[k]==null);
  const items=feedItems();const ac=actionCounts();const t=ymd(new Date());
  const kp=[];
  if(MODS.includes('bestellungen'))kp.push({k:'bestellungen',n:(D.orders||[]).length,l:_T('a.k.orders'),hot:ac.bestellungen>0,icon:'bag',tone:'gold'});
  if(MODS.includes('termine'))kp.push({k:'termine',n:(D.appts||[]).filter(b=>b.status==='booked'&&b.date===t).length,l:_T('a.k.appts'),icon:'cal',tone:'violet'});
  if(MODS.includes('taxi'))kp.push({k:'taxi',n:(D.rides||[]).length,l:_T('a.k.rides'),hot:ac.taxi>0,icon:'car',tone:'blue'});
  if(MODS.includes('hotel'))kp.push({k:'hotel',n:ac.hotel,l:_T('a.k.hotel'),hot:ac.hotel>0,icon:'bed',tone:'teal'});
  if(MODS.includes('rueckruf'))kp.push({k:'rueckruf',n:(D.callbacks||[]).length,l:_T('a.k.cb'),hot:ac.rueckruf>0,icon:'cb',tone:'rose'});
  if(kp.length<2&&hasPhone())kp.push({k:'heute',n:(D.calls||[]).filter(c=>ymd(new Date(iso(c.started_at)))===t).length,l:_T('a.k.calls'),icon:'phone',tone:'mute',filter:'anrufe'});
  if(kp.length<2)kp.push({k:'benachrichtigungen',n:D.unread,l:_T('a.k.unread'),icon:'bell',tone:'mute'});
  const types=['bestellungen','termine','taxi','hotel','rueckruf','anrufe','whatsapp'].filter(k=>items.some(i=>i.mod===k));const FL={alle:_T('a.f.alle'),bestellungen:_T('a.m.bestellungen'),termine:_T('a.m.termine'),taxi:_T('a.m.taxi'),hotel:_T('a.m.hotel'),rueckruf:_T('a.m.rueckruf'),anrufe:_T('a.f.anrufe'),whatsapp:'WhatsApp'};
  if(FEED_FILTER!=='alle'&&!types.includes(FEED_FILTER))FEED_FILTER='alle';
  const shown=FEED_FILTER==='alle'?items:items.filter(i=>i.mod===FEED_FILTER);
  let h=pushBanner();
  if(!MODS.length&&!hasPhone()){
    h+='<div class="card" style="margin-top:10px"><div class="ti t-gold" style="margin-bottom:10px">'+ic('spark')+'</div><h3 class="serif" style="margin:0 0 4px;font-size:20px">'+esc(_T('a.none.t'))+'</h3><p class="mute" style="margin:0 0 12px">'+esc(_T('a.none.p'))+'</p>'+
      '<div class="acts">'+(HAS_SETUP?'<a class="btn p s" href="/chat/studio.php#einrichtung">'+esc(_T('a.none.setup'))+'</a>':'')+'<a class="btn '+(HAS_SETUP?'o':'p')+' s" href="/chat/studio.php#agents">'+esc(_T('a.none.studio'))+'</a><button class="btn o s" type="button" data-allmods>'+esc(_T('a.none.all'))+'</button></div></div>';
  }
  if(kp.length)h+='<div class="kpis" style="margin-top:12px">'+kp.slice(0,4).map(x=>'<button class="kpi'+(x.hot?' hot':'')+'" type="button" data-kpi="'+x.k+'"'+(x.filter?' data-filter="'+x.filter+'"':'')+'><span class="ti t-'+x.tone+'" style="width:34px;height:34px;border-radius:11px;position:absolute;right:12px;top:12px">'+ic(x.icon,'s')+'</span><span class="n">'+de(x.n)+'</span><span class="l">'+esc(x.l)+'</span></button>').join('')+'</div>';
  h+='<div class="sec"><h2>'+esc(_T('a.feed'))+'</h2><span class="tiny mute">'+(LAST_OK?esc(_T('a.updated',{t:I.time(new Date(LAST_OK))})):'')+'</span></div>';
  if(types.length>1)h+='<div class="chips" role="tablist" aria-label="'+esc(_T('a.filter'))+'">'+['alle',...types].map(k=>'<button class="chip'+(FEED_FILTER===k?' on':'')+'" type="button" data-filter="'+k+'">'+esc(FL[k]||k)+(k!=='alle'?'<span class="c">'+items.filter(i=>i.mod===k).length+'</span>':'')+'</button>').join('')+'</div><div style="height:10px"></div>';
  if(loading&&!items.length)h+=skel(3);
  else if(!shown.length)h+='<div class="empty"><div class="ti t-ok">'+ic('check')+'</div><b>'+esc(_T('a.allDone'))+'</b>'+esc(_T(D.pushState==='subscribed'?'a.allDone.push':'a.allDone.p'))+'</div>';
  else h+='<div class="list" id="feedList">'+shown.slice(0,60).map(itemHTML).join('')+'</div>';
  el.innerHTML=h;bindItems(el,shown);bindCommon(el);
  $$('[data-filter]',el).forEach(b=>b.addEventListener('click',()=>{if(b.classList.contains('kpi')){FEED_FILTER=b.dataset.filter;}else{FEED_FILTER=b.dataset.filter;haptic('select');}LS.set('ch_app_filter',FEED_FILTER);renderHeute();}));
  $$('[data-kpi]',el).forEach(b=>{if(b.dataset.filter)return;b.addEventListener('click',()=>{haptic('light');go(b.dataset.kpi);});});
}
function bindCommon(root){
  $$('[data-go]',root).forEach(b=>b.addEventListener('click',()=>go(b.dataset.go)));
  $$('[data-enablepush]',root).forEach(b=>b.addEventListener('click',()=>enablePush(b)));
  $$('[data-iosguide]',root).forEach(b=>b.addEventListener('click',iosGuide));
  $$('[data-allmods]',root).forEach(b=>b.addEventListener('click',()=>{LS.set('ch_nav_all','1');computeMods();buildTabs();refresh({full:true});go('heute');}));
}

/* ---------- Aufträge (Module) ---------- */
function renderMod(){
  const el=$('#p-auftraege');const m=MOD_DEF[ROUTE]?ROUTE:(SUBMOD||MODS[0]);const ac=actionCounts();
  let h='';
  if(MODS.length>1)h+='<div class="stickybar"><div class="seg" role="tablist">'+MODS.map(k=>'<button type="button" role="tab" data-mod="'+k+'" class="'+(k===m?'on':'')+'">'+esc(MOD_DEF[k].label)+(ac[k]?'<span class="c">'+ac[k]+'</span>':'')+'</button>').join('')+'</div></div>';
  h+='<div id="modBody"></div>';el.innerHTML=h;
  $$('[data-mod]',el).forEach(b=>b.addEventListener('click',()=>{haptic('select');go(b.dataset.mod);}));
  const body=$('#modBody',el);
  ({bestellungen:renderOrders,termine:renderAppts,taxi:renderRides,hotel:renderHotel,rueckruf:renderCallbacks}[m]||(()=>{}))(body);
}
function listOrEmpty(root,items,emptyTitle,emptyText,icon,tone){
  if(items==null){root.insertAdjacentHTML('beforeend',skel(3));return;}
  if(!items.length){root.insertAdjacentHTML('beforeend','<div class="empty"><div class="ti t-'+(tone||'ok')+'">'+ic(icon||'check')+'</div><b>'+esc(emptyTitle)+'</b>'+esc(emptyText||'')+'</div>');return;}
  const wrap=document.createElement('div');wrap.className='list';wrap.innerHTML=items.map(itemHTML).join('');root.appendChild(wrap);bindItems(wrap,items);}
function linkRow(href,label,sub,icon){return '<a class="row" href="'+esc(relLink(href))+'" target="_blank" rel="noopener">'+'<span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic(icon||'ext','s')+'</span><span class="bd"><b>'+esc(label)+'</b>'+(sub?'<span>'+esc(sub)+'</span>':'')+'</span>'+ic('ext','s chev')+'</a>';}
async function ensureLinks(mod){try{
  if(mod==='bestellungen'&&!D.orderLinks){const j=await api('/order/settings');D.orderLinks=j.links||{};}
  if(mod==='taxi'&&!D.rideLinks){const j=await api('/ride/settings');D.rideLinks=j.links||{};}
  if(mod==='hotel'&&!D.hotelLinks){const j=await api('/hotel/settings');D.hotelLinks=j.links||{};}
}catch(e){}}
function renderOrders(root){
  const items=feedItems().filter(i=>i.mod==='bestellungen');const f=LS.get('ch_app_ofilter','offen');
  const groups={offen:items,neu:items.filter(i=>/^o:/.test(i.key)&&(D.orders.find(o=>'o:'+o.id===i.key)||{}).status==='new'),arbeit:items.filter(i=>{const o=(D.orders||[]).find(o=>'o:'+o.id===i.key)||{};return ['confirmed','preparing'].includes(o.status);}),fertig:items.filter(i=>{const o=(D.orders||[]).find(o=>'o:'+o.id===i.key)||{};return ['ready','out_for_delivery'].includes(o.status);})};
  if(D.orders)root.innerHTML='<div class="chips" style="margin-bottom:10px">'+[['offen',_T('a.of.offen')],['neu',_T('a.of.neu')],['arbeit',_T('a.of.arbeit')],['fertig',_T('a.of.fertig')]].map(([k,l])=>'<button class="chip'+(f===k?' on':'')+'" type="button" data-of="'+k+'">'+esc(l)+'<span class="c">'+groups[k].length+'</span></button>').join('')+'</div>';
  listOrEmpty(root,D.orders==null?null:groups[f]||items,_T('a.e.orders.t'),_T('a.e.orders.p'),'bag','gold');
  $$('[data-of]',root).forEach(b=>b.addEventListener('click',()=>{LS.set('ch_app_ofilter',b.dataset.of);haptic('select');renderMod();}));
  ensureLinks('bestellungen').then(()=>{if(ROUTE!=='bestellungen'||!D.orderLinks||!D.orderLinks.board)return;if($('#ordLinks'))return;
    root.insertAdjacentHTML('beforeend','<div class="sec" id="ordLinks"><h2>'+esc(_T('a.kd'))+'</h2></div><div class="group">'+linkRow(D.orderLinks.board,_T('a.kd.board'),_T('a.kd.boardSub'),'board')+(D.orderLinks.drivers||[]).map(d=>linkRow(d.url,_T('a.kd.drv',{n:d.name}),_T('a.sendDriver'),'car')).join('')+'</div>');});
}
function renderAppts(root){
  const all=(D.appts||[]).filter(b=>b.status==='booked');
  if(D.appts==null){listOrEmpty(root,null);return;}
  if(!all.length){listOrEmpty(root,[],_T('a.e.appts.t'),_T('a.e.appts.p'),'cal','violet');return;}
  const byDay={};all.forEach(b=>{(byDay[b.date]=byDay[b.date]||[]).push(b);});
  Object.keys(byDay).sort().forEach(d=>{root.insertAdjacentHTML('beforeend','<div class="sec"><h2>'+esc(dayWord(d))+'</h2><span class="tiny mute">'+esc(_T(byDay[d].length>1?'a.apptN':'a.apptN1',{n:byDay[d].length}))+'</span></div>');
    const items=byDay[d].sort((a,b)=>a.time.localeCompare(b.time)).map(b=>{const ts=apptTs(b);return {key:'a:'+b.id,mod:'termine',icon:'cal',tone:'violet',ts,sortTs:ts,title:fmtT(b.time)+'–'+fmtT(b.end||'')+' · '+b.service,sub:b.name+(b.phone?' · '+b.phone:'')+(b.note?' · '+b.note:''),
      status:ts<Date.now()?_T('a.past'):_T('a.booked'),stTone:ts<Date.now()?'':'violet',ch:b.source,open:()=>apptSheet(b),primary:ts<Date.now()?{label:_T('a.done'),run:x=>apptStatus(b,'done',x)}:null};});
    const w=document.createElement('div');w.className='list';w.innerHTML=items.map(itemHTML).join('');root.appendChild(w);bindItems(w,items);});
}
function renderRides(root){
  listOrEmpty(root,D.rides==null?null:feedItems().filter(i=>i.mod==='taxi'),_T('a.e.rides.t'),_T('a.e.rides.p'),'car','blue');
  ensureLinks('taxi').then(()=>{if(ROUTE!=='taxi'||!D.rideLinks||!D.rideLinks.board||$('#rideLinks'))return;
    root.insertAdjacentHTML('beforeend','<div class="sec" id="rideLinks"><h2>'+esc(_T('a.zd'))+'</h2></div><div class="group">'+linkRow(D.rideLinks.board,_T('a.zd.board'),_T('a.zd.boardSub'),'board')+(D.rideLinks.drivers||[]).map(d=>linkRow(d.url,_T('a.zd.drv',{n:d.name}),_T('a.sendDriver'),'car')).join('')+'</div>');});
}
function renderHotel(root){
  const it=feedItems().filter(i=>i.mod==='hotel');const req=it.filter(i=>/^hr:/.test(i.key)),bk=it.filter(i=>/^hb:/.test(i.key));
  root.insertAdjacentHTML('beforeend','<div class="sec" style="margin-top:6px"><h2>'+esc(_T('a.guestWishes'))+'</h2></div>');
  listOrEmpty(root,D.hotelReq==null?null:req,_T('a.e.gw'),'','bed','teal');
  root.insertAdjacentHTML('beforeend','<div class="sec"><h2>'+esc(_T('a.hbSec'))+'</h2></div>');
  listOrEmpty(root,D.hotelBook==null?null:bk,_T('a.e.hb.t'),_T('a.e.hb.p'),'bed','teal');
  ensureLinks('hotel').then(()=>{if(ROUTE!=='hotel'||!D.hotelLinks||!D.hotelLinks.board||$('#hotelLinks'))return;
    root.insertAdjacentHTML('beforeend','<div class="sec" id="hotelLinks"><h2>'+esc(_T('a.rz'))+'</h2></div><div class="group">'+linkRow(D.hotelLinks.board,_T('a.rz.board'),_T('a.rz.boardSub'),'board')+'</div>');});
}
function renderCallbacks(root){listOrEmpty(root,D.callbacks==null?null:feedItems().filter(i=>i.mod==='rueckruf'),_T('a.e.cb.t'),_T('a.e.cb.p'),'cb','rose');}

/* ---------- Aktionen ---------- */
async function act(btn,fn,okMsg){if(!ONLINE){toast(_T('a.offline.t'),_T('a.offline.p'),'err');return false;}if(btn)busyBtn(btn,true);
  try{const r=await fn();haptic('success');if(okMsg)toast(okMsg,'','ok');return r||true;}catch(e){haptic('error');toast(_T('a.failed'),e.message,'err');return false;}finally{if(btn&&document.body.contains(btn))busyBtn(btn,false);}}
async function orderStatus(o,st,btn){const r=await act(btn,()=>post('/order/status',{id:o.id,status:st}),_T('a.ok.order',{c:o.code,s:ORD_LABEL[st]||st}));if(r){if(r.order){const i=D.orders.findIndex(x=>x.id===o.id);if(['delivered','cancelled'].includes(st))D.orders.splice(i,1);else if(i>=0)D.orders[i]=r.order;}closeSheet();render();refresh();}}
async function apptStatus(b,st,btn){const r=await act(btn,()=>post('/appt/status',{id:b.id,status:st}),_T({done:'a.ok.apptDone',noshow:'a.ok.noshow',cancelled:'a.ok.apptCancel'}[st]));if(r){const x=(D.appts||[]).find(y=>y.id===b.id);if(x)x.status=st;closeSheet();render();refresh();}}
async function rideStatus(r,st,drv,btn){const res=await act(btn,()=>post('/ride/status',Object.assign({id:r.id,status:st,from:r.status},drv?{driver_id:drv}:{})),_T('a.ok.ride',{c:r.code}));if(res){closeSheet();await refresh();render();}}
async function hreqStatus(q,st,btn){const r=await act(btn,()=>post('/hotel/request',{id:q.id,status:st}),_T(st==='done'?'a.ok.gwDone':'a.ok.taken'));if(r){closeSheet();await refresh();render();}}
async function hbookAction(b,a,btn){const r=await act(btn,()=>post('/hotel/booking',{id:b.id,action:a}),_T({confirm:'a.ok.hbConfirm',decline:'a.ok.hbDecline',checkin:'a.ok.checkin',checkout:'a.ok.checkout',cancel:'a.ok.cancelled',noshow:'a.ok.noarr'}[a]));if(r){closeSheet();await refresh();render();}}
async function cbStatus(c,st,btn){const r=await act(btn,()=>post('/callbacks/update',{id:c.id,status:st}),_T(st==='done'?'a.ok.cbDone':'a.ok.updated'));if(r){D.callbacks=(D.callbacks||[]).filter(x=>x.id!==c.id||st==='open');closeSheet();render();refresh();}}
function confirmAct(title,text,label,fn){openSheet(title,'<p class="mute" style="margin-top:0">'+esc(text)+'</p><div class="acts"><button class="btn o" type="button" data-close>'+esc(_T('a.cancel'))+'</button><button class="btn rose" type="button" id="cfGo">'+esc(label)+'</button></div>');$('#cfGo').addEventListener('click',e=>fn(e.currentTarget));}

/* ---------- Detail-Ansichten ---------- */
function kv(rows){return '<dl class="kv">'+rows.filter(r=>r&&r[1]!=null&&r[1]!=='').map(r=>'<dt>'+esc(r[0])+'</dt><dd>'+(r[2]?r[1]:esc(r[1]))+'</dd>').join('')+'</dl>';}
function callBtns(phone,extra){return '<div class="acts">'+(phone?'<a class="btn o s" href="'+esc(tel(phone))+'">'+ic('phone','s')+esc(_T('a.call'))+'</a><button class="btn o s" type="button" data-contact="'+esc(phone)+'">'+ic('users','s')+esc(_T('a.custHist'))+'</button>':'')+(extra||'')+'</div>';}
function bindSheet(){$$('#shBody [data-contact]').forEach(b=>b.addEventListener('click',()=>contactSheet(b.dataset.contact)));}
function orderSheet(o){const nx=ordNext(o);
  openSheet(_T('a.orderN',{c:o.code}),'<div class="mt" style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap"><span class="pill '+(ORD_TONE[o.status]||'')+'">'+esc(ORD_LABEL[o.status]||o.status)+'</span><span class="pill">'+esc(_T(o.type==='delivery'?'a.delivery':'a.pickup'))+'</span>'+(CH_LABEL[o.channel]?'<span class="pill">'+esc(CH_LABEL[o.channel])+'</span>':'')+'</div>'+
    '<div class="card" style="box-shadow:none">'+(o.items||[]).map(x=>'<div style="display:flex;gap:10px;padding:4px 0"><b style="min-width:28px;color:var(--gold2)">'+esc(x.qty)+'×</b><div style="min-width:0" class="wrap">'+esc(x.name)+((x.options||[]).length?'<div class="small mute">'+esc(x.options.join(', '))+'</div>':'')+'</div></div>').join('')+
    '<div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);margin-top:8px;padding-top:8px;font-weight:800"><span>'+esc(_T('a.total'))+'</span><span>'+esc(DE_UI?o.total:eur(o.total_cents))+'</span></div></div>'+
    '<div style="margin-top:14px">'+kv([[_T('a.f.customer'),o.name],[_T('a.f.phone'),o.phone],[_T('a.f.address'),o.address],[_T('a.f.payment'),X(o.payment)],[_T('a.f.received'),_T('a.clock',{t:fmtT(o.created)})],[_T('a.f.readyAt'),o.eta?_T('a.clock',{t:fmtT(o.eta)}):''],[_T('a.f.driver'),o.driver&&o.driver.name],[_T('a.f.note'),o.note]])+'</div>'+
    callBtns(o.phone,o.address&&o.maps?'<a class="btn o s" href="'+esc(o.maps)+'" target="_blank" rel="noopener">'+ic('pin','s')+esc(_T('a.route'))+'</a>':'')+
    (o.type==='delivery'&&D.orderDrivers.length&&['confirmed','preparing','ready','out_for_delivery','new'].includes(o.status)?'<div class="sec"><h2>'+esc(_T('a.f.driver'))+'</h2></div><select class="inp" id="odDrv" aria-label="'+esc(_T('a.f.driver'))+'"><option value="">'+esc(_T('a.noDriver'))+'</option>'+D.orderDrivers.map(d=>'<option value="'+d.id+'"'+(o.driver&&o.driver.id===d.id?' selected':'')+'>'+esc(d.name)+'</option>').join('')+'</select>':'')+
    '<div class="acts" style="margin-top:16px">'+(o.status!=='cancelled'&&o.status!=='delivered'?'<button class="btn rose s" type="button" id="odCancel">'+esc(_T('a.cancelOrder'))+'</button>':'')+'</div>',
    nx?'<button class="btn p w" type="button" id="odNext">'+esc(nx[1])+'</button>':'');
  bindSheet();
  if($('#odNext'))$('#odNext').addEventListener('click',e=>orderStatus(o,nx[0],e.currentTarget));
  if($('#odCancel'))$('#odCancel').addEventListener('click',()=>confirmAct(_T('a.cf.order.t'),_T('a.cf.order.p',{c:o.code}),_T('a.cancelOrder'),b=>orderStatus(o,'cancelled',b)));
  if($('#odDrv'))$('#odDrv').addEventListener('change',e=>{const v=e.target.value;if(!v)return;act(null,()=>post('/order/status',{id:o.id,status:o.status,driver_id:+v}),_T('a.ok.drv')).then(r=>{if(r)refresh();});});
}
function apptSheet(b){
  openSheet(b.service,'<div class="mt" style="margin:0 0 12px;display:flex;gap:6px"><span class="pill violet">'+esc(dayWord(b.date))+' · '+esc(fmtT(b.time))+'–'+esc(fmtT(b.end||''))+'</span></div>'+kv([[_T('a.f.customer'),b.name],[_T('a.f.phone'),b.phone],[_T('a.f.email'),b.email],[_T('a.f.note'),b.note],[_T('a.f.appt'),apptHuman(b)],[_T('a.f.code'),b.code],[_T('a.f.source'),CH_LABEL[b.source]||b.source]])+callBtns(b.phone)+
    '<div class="acts" style="margin-top:16px"><button class="btn o s" type="button" id="apNo">'+esc(_T('a.noshowBtn'))+'</button><button class="btn rose s" type="button" id="apCancel">'+esc(_T('a.cancelAppt'))+'</button></div>',
    '<button class="btn p w" type="button" id="apDone">'+esc(_T('a.markDone'))+'</button>');
  bindSheet();$('#apDone').addEventListener('click',e=>apptStatus(b,'done',e.currentTarget));$('#apNo').addEventListener('click',e=>apptStatus(b,'noshow',e.currentTarget));
  $('#apCancel').addEventListener('click',()=>confirmAct(_T('a.cf.appt.t'),_T('a.cf.appt.p',{n:b.name,d:apptHuman(b)}),_T('a.cancelAppt'),x=>apptStatus(b,'cancelled',x)));
}
function rideSheet(r){const nx=rideNext(r);
  openSheet(_T('a.rideN',{c:r.code}),'<div class="mt" style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap"><span class="pill '+(RIDE_TONE[r.status]||'')+'">'+esc(X(r.status_label||r.status))+'</span><span class="pill">'+esc(r.asap?_T('a.asap'):DE_UI?(r.when_long||r.when||''):rideWhen(r))+'</span></div>'+
    kv([[_T('a.f.pickup'),r.pickup],[_T('a.f.dest'),r.destination],[_T('a.f.pax'),r.name],[_T('a.f.phone'),r.phone],[_T('a.f.persons'),r.passengers],[_T('a.f.vehicle'),X(r.vehicle_name)],[_T('a.f.price'),X(r.price_text)],[_T('a.f.payment'),X(r.payment_label)],[_T('a.f.driver'),r.driver&&(r.driver.name||r.driver)],[_T('a.f.note'),r.note]])+
    callBtns(r.phone,r.maps_pickup?'<a class="btn o s" href="'+esc(r.maps_pickup)+'" target="_blank" rel="noopener">'+ic('pin','s')+esc(_T('a.pickupPlace'))+'</a>':'')+
    (r.can_cancel!==false&&!['completed','cancelled','no_show'].includes(r.status)?'<div class="acts" style="margin-top:16px">'+(r.status!=='new'?'<button class="btn o s" type="button" id="rdDrv">'+esc(_T('a.changeDriver'))+'</button>':'')+'<button class="btn rose s" type="button" id="rdCancel">'+esc(_T('a.cancelOrder'))+'</button></div>':''),
    nx?'<button class="btn p w" type="button" id="rdNext">'+esc(nx[1])+'</button>':'');
  bindSheet();
  if($('#rdNext'))$('#rdNext').addEventListener('click',e=>nx[0]==='assigned'?driverSheet(r):rideStatus(r,nx[0],null,e.currentTarget));
  if($('#rdDrv'))$('#rdDrv').addEventListener('click',()=>driverSheet(r));
  if($('#rdCancel'))$('#rdCancel').addEventListener('click',()=>confirmAct(_T('a.cf.ride.t'),_T('a.cf.ride.p',{c:r.code}),_T('a.cancelOrder'),b=>rideStatus(r,'cancelled',null,b)));
}
function driverSheet(r){const ds=(D.rideDrivers||[]).slice().sort((a,b)=>(b.id===r.suggest_driver_id)-(a.id===r.suggest_driver_id));
  openSheet(_T('a.drvFor',{c:r.code}),ds.length?'<div class="group">'+ds.map(d=>'<button class="row" type="button" data-drv="'+d.id+'"><span class="av">'+esc(initials(d.name))+'</span><span class="bd"><b>'+esc(d.name)+(d.id===r.suggest_driver_id?' · '+esc(_T('a.suggest')):'')+'</b><span>'+esc([X(d.status),d.vehicle,d.plate].filter(Boolean).join(' · '))+'</span></span>'+ic('chev','s chev')+'</button>').join('')+'</div>':'<div class="empty"><b>'+esc(_T('a.noDrivers.t'))+'</b>'+esc(_T('a.noDrivers.p'))+'</div>');
  $$('#shBody [data-drv]').forEach(b=>b.addEventListener('click',()=>rideStatus(r,r.status==='new'?'assigned':r.status,+b.dataset.drv,b)));}
function hreqSheet(q){
  openSheet((X(q.kind_label)||_T('a.guestWish'))+(q.room_no?' · '+_T('a.roomN',{n:q.room_no}):''),kv([[_T('a.f.wish'),q.details],[_T('a.f.guest'),q.guest_name],[_T('a.f.room'),q.room_no],[_T('a.f.due'),DE_UI||!q.due_iso?q.due_human:fmtD(q.due_iso,{weekday:'short',day:'2-digit',month:'2-digit'})+' '+I.time(new Date(q.due_iso))],[_T('a.f.status'),X(q.status_label)],[_T('a.f.received'),DE_UI||!q.created_iso?q.created_day+' '+q.created:ago(iso(q.created_iso))],[_T('a.f.contact'),q.contact]])+(q.ride_url?'<div class="acts"><a class="btn o s" href="'+esc(relLink(q.ride_url))+'" target="_blank" rel="noopener">'+esc(_T('a.taxiStatus'))+'</a></div>':'')+
    '<div class="acts" style="margin-top:16px"><button class="btn rose s" type="button" id="hqCancel">'+esc(_T('a.cancelOrder'))+'</button></div>',
    q.status==='open'?'<button class="btn p w" type="button" id="hqGo">'+esc(_T('a.take'))+'</button>':q.status==='in_progress'?'<button class="btn p w" type="button" id="hqGo">'+esc(_T('a.done'))+'</button>':'');
  if($('#hqGo'))$('#hqGo').addEventListener('click',e=>hreqStatus(q,q.status==='open'?'in_progress':'done',e.currentTarget));
  $('#hqCancel').addEventListener('click',e=>hreqStatus(q,'cancelled',e.currentTarget));
}
function hbookSheet(b){const a=b.status==='requested'?[['confirm',_T('a.hb.act.confirm'),'p'],['decline',_T('a.decline'),'rose']]:b.status==='confirmed'?[['checkin',_T('a.checkin'),'p'],['noshow',_T('a.hb.no_show'),'o'],['cancel',_T('a.cancelOrder'),'rose']]:b.status==='checked_in'?[['checkout',_T('a.checkout'),'p']]:[];
  openSheet(_T('a.bookingN',{c:b.code}),'<div class="mt" style="margin:0 0 12px;display:flex;gap:6px"><span class="pill '+(b.status==='requested'?'gold':'blue')+'">'+esc(X(b.status_label||b.status))+'</span></div>'+kv([[_T('a.f.guest'),b.name],[_T('a.f.phone'),b.phone],[_T('a.f.email'),b.email],[_T('a.f.room'),b.room+(b.room_no?' · '+_T('a.roomNo',{n:b.room_no}):'')],[_T('a.f.arrival'),_T('a.fromT',{d:DE_UI?b.checkin_long:fmtD(b.checkin,{weekday:'long',day:'numeric',month:'long',year:'numeric'}),t:fmtT(b.checkin_from)})],[_T('a.f.departure'),_T('a.untilT',{d:DE_UI?b.checkout_long:fmtD(b.checkout,{weekday:'long',day:'numeric',month:'long',year:'numeric'}),t:fmtT(b.checkout_until)})],[_T('a.f.guests'),b.guests],[_T('a.f.breakfast'),X(b.breakfast_text)],[_T('a.f.price'),DE_UI?b.total:eur(b.total_cents)],[_T('a.f.note'),b.note]])+callBtns(b.phone),
    a.length?'<div class="acts" style="margin-top:0">'+a.map(x=>'<button class="btn '+x[2]+'" type="button" data-hb="'+x[0]+'">'+x[1]+'</button>').join('')+'</div>':'');
  bindSheet();$$('#shFoot [data-hb]').forEach(x=>x.addEventListener('click',()=>hbookAction(b,x.dataset.hb,x)));}
function cbSheet(c){
  openSheet(_T('a.cbTitle',{n:c.name}),'<div class="mt" style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap"><span class="pill '+(CB_TONE[c.status]||'')+'">'+esc(X(c.status_label||c.status))+'</span>'+(c.urgency==='dringend'?'<span class="pill rose">'+esc(_T('a.urgent'))+'</span>':'')+'</div>'+
    kv([[_T('a.f.topic'),c.topic],[_T('a.f.phone'),c.phone_display||c.phone],[_T('a.f.wishTime'),X(c.preferred)],[_T('a.f.source'),X(c.source_label)],[_T('a.f.handling'),X(c.mode_label)],[_T('a.f.attempts'),c.attempts?c.attempts+' / '+c.max_attempts:''],[_T('a.f.received'),DE_UI||!c.created_at?c.created:ago(iso(c.created_at))],[_T('a.f.result'),X(c.result)]])+
    callBtns(c.phone,'')+'<div class="acts" style="margin-top:16px"><button class="btn o s" type="button" id="cbCancel">'+esc(_T('a.cancelOrder'))+'</button><button class="btn ok s" type="button" id="cbDone">'+ic('check','s')+esc(_T('a.done'))+'</button></div>',
    '<a class="btn p w" href="'+esc(tel(c.phone))+'">'+ic('phone','s')+esc(_T('a.callNow'))+'</a>');
  bindSheet();$('#cbDone').addEventListener('click',e=>cbStatus(c,'done',e.currentTarget));$('#cbCancel').addEventListener('click',e=>cbStatus(c,'cancelled',e.currentTarget));}
function callSheet(c){const tr=(c.transcript||[]).filter(m=>m&&m.content);
  openSheet(_T('a.callTitle',{n:c.from_number||_T('a.unknown')}),kv([[_T('a.f.time'),ago(iso(c.started_at))],[_T('a.f.answers'),c.turns],[_T('a.f.result'),{callback:_T('a.cbWanted'),forwarded:_T('a.forwarded')}[c.outcome]||c.outcome||'–']])+callBtns(c.from_number)+
    '<div class="sec"><h2>'+esc(_T('a.conv'))+'</h2></div><div class="bubs">'+(tr.length?tr.map(m=>'<div class="bub '+(m.role==='user'?'u':'a')+'">'+esc(m.content)+'</div>').join(''):'<div class="mute small">'+esc(_T('a.noTranscript'))+'</div>')+'</div>');bindSheet();}

/* ---------- Kunden ---------- */
let kq='',kT=null;
function renderKunden(){
  const el=$('#p-kunden');
  if(!$('#kSearch',el)){el.innerHTML='<div class="stickybar"><div class="search">'+ic('search','s')+'<input class="inp" id="kSearch" type="search" placeholder="'+esc(_T('a.k.search'))+'" autocomplete="off" enterkeyhint="search" aria-label="'+esc(_T('a.k.searchAria'))+'"></div></div><div id="kList"></div>';
    $('#kSearch').addEventListener('input',e=>{kq=e.target.value.trim();clearTimeout(kT);kT=setTimeout(async()=>{D.contactsQ=kq;try{await loadContacts();}catch(x){}paintKunden();},280);});}
  paintKunden();
}
function paintKunden(){const box=$('#kList');if(!box)return;const L=D.contacts;
  if(L==null){box.innerHTML=skel(5);return;}
  if(!L.length){box.innerHTML='<div class="empty"><div class="ti t-mute">'+ic('users')+'</div><b>'+esc(_T(kq?'a.k.none':'a.k.empty'))+'</b>'+esc(_T(kq?'a.k.none.p':'a.k.empty.p'))+'</div>';return;}
  box.innerHTML='<div class="group">'+L.map(c=>'<button class="row" type="button" data-ph="'+esc(c.phone)+'"><span class="av">'+esc(initials(c.name||c.phone))+'</span><span class="bd"><b class="ell">'+esc(c.name||c.phone_masked)+'</b><span class="ell">'+esc((CH_LABEL[c.last_channel]||X(c.last_channel_label)||'')+' · '+ago(iso(c.last_seen)))+(c.open_count?' · <b style="display:inline;color:var(--gold2);font-size:12.5px">'+esc(_T('a.k.openN',{n:c.open_count}))+'</b>':'')+'</span></span>'+(c.window_open?'<span class="pill ok">'+ic('chat','xs')+'</span>':'')+ic('chev','s chev')+'</button>').join('')+'</div>';
  // Kanal-Label ist bereits escaped, „offen“-Hinweis bewusst als HTML
  $$('[data-ph]',box).forEach(b=>b.addEventListener('click',()=>{haptic('light');contactSheet(b.dataset.ph);}));}
async function contactSheet(phone){
  openSheet(_T('a.customer'),'<div style="margin-top:6px">'+skel(4)+'</div>','',{full:true});
  let d;try{d=await api('/contacts/get?phone='+encodeURIComponent(phone));}catch(e){$('#shBody').innerHTML='<div class="empty"><b>'+esc(_T('a.histNA'))+'</b>'+esc(e.message)+'</div>';return;}
  const c=d.contact||{};const wa=d.whatsapp||{};const canWa=wa.ready&&wa.window_open, canSms=!!d.sms_ready;
  $('#shTitle').textContent=c.name||c.phone_display||_T('a.customer');
  const items=(d.items||[]);
  $('#shBody').innerHTML='<div style="display:flex;gap:12px;align-items:center;margin:0 0 12px"><span class="av" style="width:52px;height:52px;font-size:17px">'+esc(initials(c.name||c.phone))+'</span><div style="min-width:0"><b class="wrap" style="font-size:16px">'+esc(c.phone_display||c.phone)+'</b><div class="small mute">'+esc((c.channels||[]).map(x=>CH_LABEL[x]||x).join(' · '))+(c.first_seen?' · '+_T('a.since',{d:I.date(new Date(iso(c.first_seen)))}):'')+'</div></div></div>'+
    '<div class="acts" style="margin-top:0"><a class="btn o s" href="'+esc(tel(c.phone))+'">'+ic('phone','s')+esc(_T('a.call'))+'</a>'+(d.callbacks_on?'<button class="btn o s" type="button" id="ctCb">'+ic('cb','s')+esc(_T('a.planCb'))+'</button>':'')+'</div>'+
    (d.open&&d.open.length?'<div class="sec"><h2>'+esc(_T('a.open'))+'</h2></div><div class="list">'+d.open.map(x=>'<div class="card" style="padding:12px 14px"><div style="display:flex;gap:8px;align-items:center"><b class="ell" style="flex:1;min-width:0">'+esc(X(x.title))+'</b><span class="pill '+esc(x.tone||'')+'">'+esc(X(x.status_label||''))+'</span></div><div class="small mute wrap" style="margin-top:2px">'+esc(X(x.sub||''))+'</div></div>').join('')+'</div>':'')+
    '<div class="sec"><h2>'+esc(_T('a.note'))+'</h2></div><textarea class="inp" id="ctNote" placeholder="'+esc(_T('a.notePh'))+'" aria-label="'+esc(_T('a.note'))+'" maxlength="4000">'+esc(c.notes||'')+'</textarea><div class="acts" style="margin-top:8px"><button class="btn o s" type="button" id="ctNoteSave">'+esc(_T('a.noteSave'))+'</button></div>'+
    '<div class="sec"><h2>'+esc(_T('a.history'))+'</h2><span class="tiny mute">'+esc(_T('a.entriesN',{n:items.length}))+'</span></div><div class="list">'+(items.length?items.map(x=>'<div class="card" style="padding:12px 14px"><div style="display:flex;gap:8px;align-items:baseline"><b class="ell" style="flex:1;min-width:0">'+esc(X(x.title))+'</b><span class="tiny mute">'+esc(DE_UI&&x.when?x.when:ago(iso(x.at)))+'</span></div>'+(x.sub?'<div class="small mute wrap" style="margin-top:2px">'+esc(X(x.sub))+'</div>':'')+
      (x.status_label?'<div style="margin-top:6px"><span class="pill '+esc(x.tone||'')+'">'+esc(X(x.status_label))+'</span>'+(x.channel&&CH_LABEL[x.channel]?' <span class="pill">'+esc(CH_LABEL[x.channel])+'</span>':'')+'</div>':'')+
      (x.messages&&x.messages.length?'<div class="bubs" style="margin-top:8px">'+x.messages.slice(-12).map(m=>'<div class="bub '+(m.role==='user'?'u':'a')+'">'+esc(m.content)+'</div>').join('')+'</div>':'')+'</div>').join(''):'<div class="mute small">'+esc(_T('a.noHist'))+'</div>')+'</div>';
  $('#shFoot').hidden=false;
  $('#shFoot').innerHTML=(canWa||canSms)?'<form id="ctReply" style="display:flex;gap:8px;align-items:flex-end"><textarea class="inp" id="ctText" rows="1" style="min-height:48px;max-height:140px" placeholder="'+esc(_T(canWa?'a.waReply':'a.smsReply'))+'" maxlength="1000" aria-label="'+esc(_T('a.reply'))+'"></textarea><button class="btn p" type="submit" aria-label="'+esc(_T('a.send'))+'" style="min-width:52px;padding:12px">'+ic('send','s')+'</button></form><div class="tiny mute" style="margin-top:6px">'+esc(canWa?(wa.expires_at?_T('a.waOpenUntil',{t:I.time(new Date(iso(wa.expires_at)))}):_T('a.waOpen')):_T('a.viaSms'))+'</div>'
    :'<div class="small mute">'+esc(_T(wa.window_open&&!wa.ready?'a.waNotConn':'a.wa24'))+'</div>';
  $('#ctNoteSave').addEventListener('click',e=>act(e.currentTarget,()=>post('/contacts/note',{phone:c.phone,notes:$('#ctNote').value}),_T('a.ok.note')));
  if($('#ctCb'))$('#ctCb').addEventListener('click',e=>act(e.currentTarget,()=>post('/contacts/reply',{phone:c.phone,action:'callback',topic:_T('a.cbFromApp')}),_T('a.ok.cbPlanned')).then(r=>{if(r)refresh();}));
  if($('#ctReply')){const ta=$('#ctText');ta.addEventListener('input',()=>{ta.style.height='auto';ta.style.height=Math.min(140,ta.scrollHeight)+'px';});
    $('#ctReply').addEventListener('submit',async e=>{e.preventDefault();const t=ta.value.trim();if(!t)return;const b=e.submitter||$('#ctReply button');
      const r=await act(b,()=>post('/contacts/reply',{phone:c.phone,text:t}),_T('a.ok.sent'));if(r){ta.value='';contactSheet(phone);}});}
}

/* ---------- Benachrichtigungen ---------- */
const EV_MOD={order_new:'bestellungen',order_cancel:'bestellungen',booking_new:'termine',booking_cancel:'termine',ride_new:'taxi',ride_cancel:'taxi',hotel_booking:'hotel',hotel_request:'hotel',callback_new:'rueckruf',lead_new:'rueckruf',call_done:'phone'};
function statusModel(){
  const st=D.pushState, P=D.push, mine=P&&D.deviceHash?(P.devices||[]).find(d=>d.h===D.deviceHash):null, mail=P&&P.email_fallback?(P.email?_T('a.p.mailTo',{e:P.email}):_T('a.p.mail')):'';
  if(!A)return {tone:'rose',icon:'block',tag:_T('a.p.nl.tag'),title:_T('a.p.nl.t'),text:_T('a.p.nl.p')+mail,recheck:true};
  if(st==='subscribed'&&(mine||!P))return {tone:'ok',icon:'check',tag:_T('a.p.on.tag'),title:_T('a.p.on.t'),text:_T('a.p.on.p')+(mine&&mine.last_ok?_T('a.p.on.last',{t:ago(iso(mine.last_ok))}):_T('a.p.on.test')),test:true,disable:true};
  if(IS_IOS&&!STANDALONE&&!IS_NATIVE){const v=/OS (\d+)[_.](\d+)/.exec(UA);const old=v&&(+v[1]<16||(+v[1]===16&&+v[2]<4));
    if(old)return {tone:'mute',icon:'block',tag:_T('a.p.old.tag'),title:_T('a.p.old.t'),text:_T('a.p.old.p')+mail};
    return {tone:'gold',icon:'share',tag:'iPhone & iPad',title:_T('a.p.ios.t'),text:_T('a.p.ios.p'),ios:true};}
  if(st==='subscribed'||st==='granted')return {tone:'gold',icon:'bell',tag:_T('a.p.re.tag'),title:_T('a.p.re.t'),text:_T('a.p.re.p'),enable:_T('a.p.re.b')};
  if(st==='default')return {tone:'gold',icon:'bell',tag:_T('a.p.off.tag'),title:_T('a.enablePush'),text:_T('a.p.off.p')+(IS_ANDROID&&!STANDALONE&&!IS_NATIVE?_T('a.p.off.tip'):''),enable:_T('a.enablePush')};
  if(st==='denied')return {tone:'rose',icon:'block',tag:_T('a.p.den.tag'),title:_T('a.p.den.t'),text:I.tx(A.deniedHint())+mail,recheck:true,denied:true};
  return {tone:'mute',icon:'block',tag:_T('a.p.ns.tag'),title:_T('a.p.ns.t'),text:_T('a.p.ns.p')+mail,download:true};
}
const IOS_STEPS=[_T('a.ios1'),_T('a.ios2'),_T('a.ios3')];   // Wörterbuch-Texte mit <b> (kein Nutzer-Inhalt)
function iosGuide(){openSheet(_T('a.ios.title'),'<p class="mute" style="margin-top:0">'+esc(_T('a.ios.p'))+'</p><ol class="steps">'+IOS_STEPS.map(s=>'<li>'+s+'</li>').join('')+'</ol><div class="acts"><a class="btn o s" href="/chat/app-download.php">'+esc(_T('a.ios.more'))+'</a></div>');}
/** Ereignis-Texte (Server: Deutsch) – je Schlüssel übersetzt (a.ev.<key> / a.evh.<key>) */
const evLabel=e=>I.has&&I.has('a.ev.'+e.key)?_T('a.ev.'+e.key):X(e.label), evHint=e=>I.has&&I.has('a.evh.'+e.key)?_T('a.evh.'+e.key):X(e.hint);
function renderPush(){
  const el=$('#p-benachrichtigungen');const m=statusModel();const P=D.push;
  const tc={ok:['var(--okl)','var(--ok)'],gold:['var(--goldl)','var(--gold2)'],rose:['var(--rosel)','var(--rose)'],mute:['var(--sur2)','var(--mute)']}[m.tone];
  let h='<div class="card" id="pnStatus" data-state="'+esc(D.pushState||'')+'" style="margin-top:6px"><div style="display:flex;gap:12px;align-items:flex-start"><div class="ti" style="background:'+tc[0]+';color:'+tc[1]+';width:48px;height:48px">'+ic(m.icon)+'</div><div style="min-width:0;flex:1">'+
    '<span class="pill '+(m.tone==='ok'?'ok':m.tone==='rose'?'rose':m.tone==='gold'?'gold':'')+'" id="pnTag">'+esc(m.tag)+'</span><h3 class="serif" style="margin:6px 0 4px;font-size:19px;line-height:1.25" id="pnTitle">'+esc(m.title)+'</h3><div class="small" style="color:var(--ink2)" id="pnText">'+esc(m.text)+'</div><div class="tiny mute" style="margin-top:6px">'+esc(devLabel())+'</div></div></div>'+
    (m.ios?'<ol class="steps">'+IOS_STEPS.map(s=>'<li>'+s+'</li>').join('')+'</ol>':'')+
    '<div class="acts">'+(m.enable?'<button class="btn g w" type="button" id="pnEnable" data-enablepush>'+ic('bell','s')+esc(m.enable)+'</button>':'')+(m.test?'<button class="btn p" type="button" id="pnTest">'+ic('send','s')+esc(_T('a.testSend'))+'</button>':'')+
    (m.recheck?'<button class="btn o" type="button" id="pnRecheck">'+esc(_T('a.recheck'))+'</button>':'')+(A&&A.canInstall?'<button class="btn o" type="button" id="pnInstall">'+ic('dl','s')+esc(_T('a.install'))+'</button>':'')+(m.ios||m.download||m.denied?'<a class="btn o" href="/chat/app-download.php">'+ic('dl','s')+esc(_T('a.appGuide'))+'</a>':'')+'</div>'+
    '<div id="pnMsg" aria-live="polite"></div>'+(m.disable?'<button class="lnk" type="button" id="pnOff" style="margin-top:4px">'+esc(_T('a.offHere'))+'</button>':'')+'</div>';
  if(P){const relevant=e=>!EV_MOD[e.key]||allOn()||(EV_MOD[e.key]==='phone'?hasPhone():MODS.includes(EV_MOD[e.key]));
    const evs=(P.events||[]).filter(relevant);const groups={};evs.forEach(e=>{const g=I.has&&I.has('a.pg.'+e.group)?_T('a.pg.'+e.group):X(e.group_label);(groups[g]=groups[g]||[]).push(e);});
    h+='<div class="sec"><h2>'+esc(_T('a.devices'))+'</h2><span class="tiny mute">'+esc(_T('a.devN',{n:(P.devices||[]).length})+(P.drivers?' · '+_T('a.drvN',{n:P.drivers}):'')+(P.boards?' · '+_T('a.boardsN',{n:P.boards}):''))+'</span></div><div class="group" id="pnDevices">'+((P.devices||[]).length?(P.devices||[]).map(d=>'<div class="row"><span class="ti '+(d.h===D.deviceHash?'t-ok':'t-mute')+'" style="width:36px;height:36px;border-radius:12px">'+ic(d.type==='fcm'?'grid':'bell','s')+'</span><span class="bd"><b class="ell">'+esc(d.label||_T('a.device'))+(d.h===D.deviceHash?' · '+esc(_T('a.thisDev')):'')+'</b><span class="ell" data-devres="'+esc(d.id)+'">'+esc(d.last_ok?_T('a.lastOk',{t:ago(iso(d.last_ok))}):_T('a.nothingYet'))+'</span></span></div>').join(''):'<div class="row"><span class="bd"><span>'+esc(_T('a.noDev'))+'</span></span></div>')+'</div>';
    Object.keys(groups).forEach(g=>{h+='<div class="sec"><h2>'+esc(g)+'</h2></div><div class="group">'+groups[g].map(e=>'<label class="row" style="cursor:pointer"><span class="bd"><b>'+esc(evLabel(e))+(e.urgent?' <span class="pill gold" style="font-size:10.5px;vertical-align:1px">'+esc(_T('a.urgentTag'))+'</span>':'')+'</b><span>'+esc(evHint(e))+'</span></span><span class="sw"><input type="checkbox" data-ev="'+esc(e.key)+'"'+(e.on?' checked':'')+' aria-label="'+esc(evLabel(e))+'"><i></i></span></label>').join('')+'</div>';});
    const q=P.quiet||{};
    h+='<div class="sec"><h2>'+esc(_T('a.quiet.sec'))+'</h2></div><div class="group"><div class="row" style="flex-wrap:wrap"><span class="bd" style="flex-basis:100%"><b>'+esc(_T('a.quiet'))+'</b><span>'+esc(_T('a.quiet.p')+(P.in_quiet?' · '+_T('a.quiet.now'):''))+'</span></span><div style="display:flex;gap:8px;align-items:center;width:100%"><input class="inp" type="time" id="pnQFrom" value="'+esc(q.from||'')+'" aria-label="'+esc(_T('a.quiet.from'))+'" style="min-height:44px;min-width:0"><span class="mute">'+esc(_T('a.until'))+'</span><input class="inp" type="time" id="pnQTo" value="'+esc(q.to||'')+'" aria-label="'+esc(_T('a.quiet.to'))+'" style="min-height:44px;min-width:0"></div></div>'+
      '<label class="row" style="cursor:pointer"><span class="bd"><b>'+esc(_T('a.quiet.urgent'))+'</b><span>'+esc(_T('a.quiet.urgent.p'))+'</span></span><span class="sw"><input type="checkbox" id="pnQUrgent"'+(q.urgent!==false?' checked':'')+' aria-label="'+esc(_T('a.quiet.urgent'))+'"><i></i></span></label>'+
      '<label class="row" style="cursor:pointer"><span class="bd"><b>'+esc(_T('a.mailFb'))+'</b><span>'+esc(_T('a.mailFb.p'))+(P.email?' ('+esc(P.email)+')':'')+'</span></span><span class="sw"><input type="checkbox" id="pnMail"'+(P.email_fallback?' checked':'')+' aria-label="'+esc(_T('a.mailFb'))+'"><i></i></span></label></div>';
  } else h+='<div style="margin-top:14px">'+skel(2)+'</div>';
  const inbox=D.inbox||[];
  h+='<div class="sec"><h2>'+esc(_T('a.inbox'))+'</h2>'+(D.unread?'<button class="act" type="button" id="pnReadAll">'+esc(_T('a.readAll',{n:D.unread}))+'</button>':'<span class="tiny mute">'+esc(_T('a.allRead'))+'</span>')+'</div>';
  h+=inbox.length?'<div class="group" id="pnInbox">'+inbox.map(n=>'<button class="row" type="button" data-nid="'+n.id+'"><span class="ti '+(n.read?'t-mute':'t-gold')+'" style="width:36px;height:36px;border-radius:12px">'+ic(({auftraege:'bag',kommunikation:'chat',konto:'info'})[n.group]||'bell','s')+'</span><span class="bd"><b class="ell" style="font-weight:'+(n.read?500:700)+'">'+esc(X(n.title))+'</b><span class="ell">'+esc(X(n.body))+'</span><span>'+esc(ago(iso(n.created)))+'</span></span>'+(n.read?'':'<span style="width:9px;height:9px;border-radius:50%;background:var(--rose);flex:0 0 auto"></span>')+'</button>').join('')+'</div>'
    :'<div class="empty"><b>'+esc(_T('a.noInbox.t'))+'</b>'+esc(_T('a.noInbox.p'))+'</div>';
  el.innerHTML=h;bindCommon(el);
  if($('#pnTest'))$('#pnTest').addEventListener('click',e=>testPush(e.currentTarget));
  if($('#pnRecheck'))$('#pnRecheck').addEventListener('click',async()=>{await loadPush();renderPush();});
  if($('#pnInstall'))$('#pnInstall').addEventListener('click',async()=>{const r=await A.install();toast(_T(r.ok?'a.installing':'a.installation'),I.tx(r.message),r.ok?'ok':'');});
  if($('#pnOff'))$('#pnOff').addEventListener('click',async()=>{const r=await A.disablePush();toast(_T(r.ok?'a.switchedOff':'a.failed'),I.tx(r.message),r.ok?'':'err');await loadPush();renderPush();});
  $$('[data-ev]',el).forEach(x=>x.addEventListener('change',async()=>{haptic('select');const k=x.dataset.ev,v=x.checked;try{const r=await post('/push/prefs',{prefs:{[k]:v}});if(P){P.events.forEach(e=>{if(e.key===k)e.on=!!(r.prefs||{})[k];});}}catch(e){x.checked=!v;toast(_T('a.notSaved'),e.message,'err');}}));
  const saveQ=async()=>{try{const r=await post('/push/prefs',{quiet:{from:$('#pnQFrom').value,to:$('#pnQTo').value,urgent:$('#pnQUrgent').checked,tz:Intl.DateTimeFormat().resolvedOptions().timeZone||'Europe/Berlin'}});if(P){P.quiet=r.quiet;P.in_quiet=r.in_quiet;}toast(_T('a.ok.quiet'),r.quiet&&r.quiet.from?_T('a.range',{a:fmtT(r.quiet.from),b:fmtT(r.quiet.to)}):_T('a.noQuiet'),'ok');}catch(e){toast(_T('a.notSaved'),e.message,'err');}};
  ['pnQFrom','pnQTo','pnQUrgent'].forEach(id=>{const x=$('#'+id);if(x)x.addEventListener('change',saveQ);});
  if($('#pnMail'))$('#pnMail').addEventListener('change',async e=>{const v=e.target.checked;try{const r=await post('/push/prefs',{email_fallback:v});if(P)P.email_fallback=r.email_fallback;}catch(x){e.target.checked=!v;toast(_T('a.notSaved'),x.message,'err');}});
  if($('#pnReadAll'))$('#pnReadAll').addEventListener('click',async()=>{try{const r=await post('/push/read',{all:true});(D.inbox||[]).forEach(n=>n.read=true);setUnread(r.unread);renderPush();haptic('success');}catch(e){toast(_T('a.failed'),e.message,'err');}});
  $$('[data-nid]',el).forEach(b=>b.addEventListener('click',()=>{const n=(D.inbox||[]).find(x=>x.id===+b.dataset.nid);if(n){haptic('light');handleTap({url:n.url,tag:n.tag,id:n.id,event:n.event});}}));
}
async function enablePush(btn){
  if(!A){toast(_T('a.na'),_T('a.na.p'),'err');return;}
  if(IS_IOS&&!STANDALONE&&!IS_NATIVE){iosGuide();return;}
  const p=A.enablePush({label:devLabel()});   // sofort im Klick: der Berechtigungsdialog braucht die Nutzergeste
  busyBtn(btn,true,esc(_T('a.activating')));
  const r=await p;busyBtn(btn,false);
  if(r.ok){haptic('success');toast(_T('a.pushOn.t'),_T('a.pushOn.p'),'ok');}
  else{haptic('error');toast(_T(r.reason==='denied'?'a.blocked':r.reason==='ios_install'?'a.installFirst':'a.notYet'),I.tx(r.message),'err');}
  await loadPush();render();
  if(r.ok&&ROUTE==='benachrichtigungen'){const m=$('#pnMsg');if(m)m.innerHTML='<div class="msg ok">'+esc((r.message?I.tx(r.message):_T('a.active'))+' '+_T('a.tryTest'))+'</div>';}
}
async function testPush(btn){busyBtn(btn,true,esc(_T('a.sending')));
  try{const r=await post('/push/test',{});const m=$('#pnMsg');if(m)m.innerHTML='<div class="msg '+(r.ok?'ok':'err')+'">'+esc(X(r.message||''))+'</div>';
    (r.results||[]).forEach(x=>{const s=$('[data-devres="'+x.id+'"]');if(s){s.textContent=x.ok?_T('a.testOk'):'✗ '+(x.removed?_T('a.gone'):(X(x.error)||_T('a.notDelivered')));s.style.color=x.ok?'var(--ok)':'var(--rose)';}});
    if(r.unread!=null)setUnread(r.unread);haptic(r.ok?'success':'warning');}
  catch(e){toast(_T('a.testFail'),e.message,'err');}busyBtn(btn,false);}

/* ---------- Mehr ---------- */
function renderMehr(){
  const el=$('#p-mehr');const theme=LS.get('ch_app_theme','system');
  const plans=(ME&&ME.plans||[]).filter(p=>p.active);
  let h='<div class="card" style="margin-top:6px"><div style="display:flex;gap:12px;align-items:center"><span class="av" style="background:var(--goldl);color:var(--gold2)">'+esc(initials(ME&&ME.email||'?'))+'</span><div style="min-width:0;flex:1"><b class="ell" style="display:block">'+esc(ME&&ME.email||'')+'</b><span class="small mute">'+esc(_T('a.balance',{n:de(ME&&ME.balance)}))+'</span></div></div>'+
    (plans.length?'<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">'+plans.map(p=>'<span class="pill gold">'+esc((!DE_UI&&I.has&&I.has('pl.'+p.id+'.n')?_T('pl.'+p.id+'.n'):p.name).replace(/\s*\(.*\)$/,''))+'</span>').join('')+'</div>':'')+'</div>';
  h+='<div class="sec"><h2>Studio</h2></div><div class="group">'+
    '<a class="row" href="/chat/studio.php#home"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('grid','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.studio'))+'</b><span>'+esc(_T('a.mx.studio.p'))+'</span></span>'+ic('chev','s chev')+'</a>'+
    '<a class="row" href="/chat/studio.php#assistant"><span class="ti t-gold" style="width:36px;height:36px;border-radius:12px">'+ic('spark','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.asst'))+'</b><span>'+esc(_T('a.mx.asst.p'))+'</span></span>'+ic('chev','s chev')+'</a>'+
    (HAS_SETUP?'<a class="row" href="/chat/studio.php#einrichtung"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('check','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.setup'))+'</b><span>'+esc(_T('a.mx.setup.p'))+'</span></span>'+ic('chev','s chev')+'</a>':'')+
    '<a class="row" href="/chat/studio.php#tokens"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('key','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.tokens'))+'</b><span>'+esc(_T('a.mx.tokens.p'))+'</span></span>'+ic('chev','s chev')+'</a></div>';
  h+='<div class="sec"><h2>App</h2></div><div class="group">'+
    (A&&A.canInstall?'<button class="row" type="button" id="mxInstall"><span class="ti t-ok" style="width:36px;height:36px;border-radius:12px">'+ic('dl','s')+'</span><span class="bd"><b>'+esc(_T('a.install'))+'</b><span>'+esc(_T('a.mx.install.p'))+'</span></span>'+ic('chev','s chev')+'</button>':'')+
    (IS_NATIVE?'':'<a class="row" href="/chat/app-download.php"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('dl','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.dl'))+'</b><span>'+esc(_T('a.mx.dl.p'))+'</span></span>'+ic('chev','s chev')+'</a>')+
    '<div class="row" style="flex-wrap:wrap"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('moon','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.theme'))+'</b></span><div class="seg" style="width:100%" id="mxTheme">'+[['system',_T('a.th.system')],['light',_T('a.th.light')],['dark',_T('a.th.dark')]].map(([k,l])=>'<button type="button" data-th="'+k+'" class="'+(theme===k?'on':'')+'">'+esc(l)+'</button>').join('')+'</div></div>'+
    langRow()+
    tog('ch_app_sound','vol',_T('a.mx.sound'),_T('a.mx.sound.p'),'1')+tog('ch_app_vib','vib',_T('a.mx.vib'),_T('a.mx.vib.p'),'1')+
    tog('ch_nav_all','layers',_T('a.mx.all'),_T('a.mx.all.p'),'0')+'</div>';
  h+='<div class="sec"><h2>'+esc(_T('a.mx.help'))+'</h2></div><div class="group"><a class="row" href="mailto:support@chat-help.com?subject=ChatHelp%20Business-App"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('mail','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.support'))+'</b><span>support@chat-help.com</span></span>'+ic('chev','s chev')+'</a>'+
    '<button class="row" type="button" id="mxOut"><span class="ti t-rose" style="width:36px;height:36px;border-radius:12px">'+ic('out','s')+'</span><span class="bd"><b style="color:var(--rose)">'+esc(_T('a.mx.out'))+'</b><span>'+esc(_T('a.mx.out.p'))+'</span></span></button></div>'+
    '<div class="tiny mute" style="text-align:center;margin:18px 0 8px">ChatHelp Business · '+esc(_T('a.mx.ver',{v:APP_VER}))+(IS_NATIVE?' · App':STANDALONE?' · '+esc(_T('a.mx.installed')):' · Browser')+'<br><a href="/impressum.html" style="color:inherit">'+esc(_T('a.imprint'))+'</a> · '+esc(_T('a.gdpr'))+'</div>';
  el.innerHTML=h;
  $$('#mxTheme [data-th]').forEach(b=>b.addEventListener('click',()=>{haptic('select');setTheme(b.dataset.th);renderMehr();}));
  $$('[data-tog]',el).forEach(x=>x.addEventListener('change',()=>{haptic('select');LS.set(x.dataset.tog,x.checked?'1':'0');if(x.dataset.tog==='ch_nav_all'){computeMods();buildTabs();refresh({full:true});go('mehr');}if(x.dataset.tog==='ch_app_sound'&&x.checked)chime();}));
  if($('#mxInstall'))$('#mxInstall').addEventListener('click',async()=>{const r=await A.install();toast(_T(r.ok?'a.installing':'a.installation'),I.tx(r.message),r.ok?'ok':'');renderMehr();});
  $('#mxOut').addEventListener('click',()=>confirmAct(_T('a.cf.out.t'),_T('a.cf.out.p'),_T('a.mx.out'),()=>logout('')));
  const ls=$('#mxLang');if(ls)ls.addEventListener('change',()=>{haptic('select');I.set(ls.value);});
}
/** Sprachwahl unter „Mehr“: Cookie ch_lang (+ localStorage) und Neuladen – dieselbe Wahl wie im Studio und auf der Website */
function langRow(){return '<label class="row" style="cursor:pointer"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic('globe','s')+'</span><span class="bd"><b>'+esc(_T('a.mx.lang'))+'</b><span>'+esc(_T('a.mx.lang.p'))+'</span></span>'+
  '<select class="inp" id="mxLang" aria-label="'+esc(_T('a.mx.lang'))+'" style="width:auto;max-width:46%;min-height:44px;padding:8px 10px;font-size:15px">'+(I.langs||[]).map(x=>'<option value="'+esc(x[0])+'" lang="'+esc(x[2])+'"'+(x[0]===I.lang?' selected':'')+'>'+esc(x[1])+'</option>').join('')+'</select></label>';}
function tog(k,icon,label,sub,def){return '<label class="row" style="cursor:pointer"><span class="ti t-mute" style="width:36px;height:36px;border-radius:12px">'+ic(icon,'s')+'</span><span class="bd"><b>'+esc(label)+'</b><span>'+esc(sub)+'</span></span><span class="sw"><input type="checkbox" data-tog="'+k+'"'+(LS.get(k,def)==='1'?' checked':'')+' aria-label="'+esc(label)+'"><i></i></span></label>';}
function setTheme(t){LS.set('ch_app_theme',t==='system'?null:t);if(t==='system')document.documentElement.removeAttribute('data-theme');else document.documentElement.setAttribute('data-theme',t);paintSystemBars();}
function isDark(){const t=document.documentElement.getAttribute('data-theme');return t?t==='dark':!!(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);}
function paintSystemBars(){const dark=isDark();
  $$('meta[name="theme-color"]').forEach(m=>m.setAttribute('content',dark?'#0b0d18':'#f6f7fb'));
  const SB=cap('StatusBar');if(SB){try{SB.setStyle({style:dark?'DARK':'LIGHT'});if(IS_ANDROID&&SB.setBackgroundColor)SB.setBackgroundColor({color:dark?'#0b0d18':'#f6f7fb'});}catch(e){}}
  const SY=cap('SystemBars');if(SY&&SY.setStyle){try{SY.setStyle({style:dark?'DARK':'LIGHT'});}catch(e){}}}
try{matchMedia('(prefers-color-scheme: dark)').addEventListener('change',paintSystemBars);}catch(e){}

/* =====================================================================================
   Bottom-Sheet (Wischen zum Schließen, Zurück-Taste schließt)
   ===================================================================================== */
let sheetOpen=false,SHEET_CLOSED=0;
function openSheet(title,body,foot,o={}){
  const s=$('#sheet');$('#shTitle').textContent=title||'';$('#shBody').innerHTML=body||'';const f=$('#shFoot');f.hidden=!foot;f.innerHTML=foot||'';
  s.classList.toggle('full',!!o.full);$('#shBody').scrollTop=0;
  if(!sheetOpen){s.hidden=false;s.offsetHeight;s.classList.add('on');sheetOpen=true;try{history.pushState({chSheet:1},'',location.href);}catch(e){}document.body.style.overflow='hidden';}
  $$('#sheet [data-close]').forEach(b=>b.onclick=()=>closeSheet());
}
function closeSheet(fromPop){if(!sheetOpen)return;sheetOpen=false;SHEET_CLOSED=Date.now();const s=$('#sheet'),pan=$('.pan',s);pan.style.transform='';s.classList.remove('on');document.body.style.overflow='';
  setTimeout(()=>{if(!sheetOpen){s.hidden=true;$('#shBody').innerHTML='';}},320);
  if(fromPop!==true&&history.state&&history.state.chSheet){try{history.back();}catch(e){}}}
window.addEventListener('popstate',()=>{if(sheetOpen)closeSheet(true);});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&sheetOpen)closeSheet();});
(function(){let y0=null,dy=0,t0=0;const pan=$('#sheet .pan');
  const start=e=>{if(!sheetOpen)return;y0=(e.touches?e.touches[0]:e).clientY;dy=0;t0=Date.now();pan.style.transition='none';};
  const move=e=>{if(y0==null)return;dy=Math.max(0,(e.touches?e.touches[0]:e).clientY-y0);pan.style.transform='translate3d(0,'+dy+'px,0)';if(e.cancelable)e.preventDefault();};
  const end=()=>{if(y0==null)return;pan.style.transition='';const v=dy/Math.max(1,Date.now()-t0);if(dy>120||v>.6)closeSheet();else pan.style.transform='';y0=null;};
  ['.grab','.sh'].forEach(sel=>{const h=$('#sheet '+sel);h.addEventListener('touchstart',start,{passive:true});h.addEventListener('touchmove',move,{passive:false});h.addEventListener('touchend',end);h.addEventListener('touchcancel',end);});
})();

/* ---------- Ziehen zum Aktualisieren ---------- */
(function(){let y0=null,dy=0,pulling=false;const ptr=$('#ptr');
  window.addEventListener('touchstart',e=>{if(sheetOpen||!BOOTED||window.scrollY>0||e.touches.length!==1){y0=null;return;}y0=e.touches[0].clientY;dy=0;pulling=false;},{passive:true});
  window.addEventListener('touchmove',e=>{if(y0==null)return;const d=e.touches[0].clientY-y0;if(d<=0){if(pulling){pulling=false;ptr.style.transform='translateY(-70px)';ptr.style.opacity='0';}return;}
    if(window.scrollY>0){y0=null;return;}pulling=true;dy=Math.min(120,d*.5);ptr.style.transition='none';ptr.style.opacity=String(Math.min(1,dy/60));ptr.style.transform='translateY('+(dy-20)+'px) rotate('+(dy*3)+'deg)';if(e.cancelable)e.preventDefault();},{passive:false});
  window.addEventListener('touchend',async()=>{if(y0==null||!pulling){y0=null;return;}y0=null;ptr.style.transition='transform .25s var(--ease),opacity .25s';
    if(dy>64){haptic('medium');ptr.classList.add('go');ptr.style.transform='translateY(44px)';await refresh({full:true});ptr.classList.remove('go');}
    ptr.style.transform='translateY(-70px)';ptr.style.opacity='0';pulling=false;});
})();
$('#btnRefresh').addEventListener('click',async e=>{haptic('light');const b=e.currentTarget;b.style.transform='rotate(360deg)';b.style.transition='transform .6s var(--ease)';await refresh({full:true});setTimeout(()=>{b.style.transition='none';b.style.transform='';},650);});

/* =====================================================================================
   Benachrichtigungen antippen → passender Bereich / Eintrag
   ===================================================================================== */
let pendingDeep=null;
const APP_ROUTES={home:'heute',heute:'heute',phone:'heute',bestellungen:'bestellungen',termine:'termine',taxi:'taxi',hotel:'hotel',rueckruf:'rueckruf',kunden:'kunden',benachrichtigungen:'benachrichtigungen',whatsapp:'kunden'};
function handleTap(data){
  data=data||{};let route='',tag=data.tag||'',nid=+data.id||0,ext=null;
  try{const u=new URL(data.url||'',location.href);
    if(u.origin===location.origin&&(u.pathname==='/chat/studio.php'||u.pathname===location.pathname)){const h=(u.hash||'').slice(1);route=APP_ROUTES[h]||(u.pathname===location.pathname?h:'');
      if(!route&&h)ext=u.href;if(u.searchParams.get('tag'))tag=u.searchParams.get('tag');if(+u.searchParams.get('nid'))nid=+u.searchParams.get('nid');}
    else if(data.url)ext=u.href;}catch(e){}
  if(ext&&!route){location.assign(ext);return true;}
  if(nid>0){post('/push/read',{ids:[nid]}).then(r=>{const n=(D.inbox||[]).find(x=>x.id===nid);if(n)n.read=true;setUnread(r.unread);if(ROUTE==='benachrichtigungen')renderPush();}).catch(()=>{});}
  if(route&&route!=='benachrichtigungen'&&!validRoute(route)&&MOD_DEF[route]){LS.set('ch_nav_all','1');computeMods();buildTabs();}
  if(route)go(route);
  if(tag){pendingDeep={tag,nid:0};if(!openDeep(pendingDeep))refresh();}
  return true;
}
function openDeep(p){const tag=String(p.tag||'');const m=/^([a-z]+)-(.+)$/.exec(tag);if(!m){pendingDeep=null;return false;}const [,k,v]=m;let it=null;
  if(k==='order'){const o=(D.orders||[]).find(x=>x.code===v);if(o){orderSheet(o);it=1;}}
  else if(k==='ride'){const r=(D.rides||[]).find(x=>x.code===v);if(r){rideSheet(r);it=1;}}
  else if(k==='appt'){const b=(D.appts||[]).find(x=>x.code===v);if(b){apptSheet(b);it=1;}}
  else if(k==='hotelreq'){const q=(D.hotelReq||[]).find(x=>String(x.id)===v);if(q){hreqSheet(q);it=1;}}
  else if(k==='hotel'){const b=(D.hotelBook||[]).find(x=>x.code===v);if(b){hbookSheet(b);it=1;}}
  else if(k==='cb'){const c=(D.callbacks||[]).find(x=>x.code===v);if(c){cbSheet(c);it=1;}}
  else if(k==='call'){const c=(D.calls||[]).find(x=>x.call_sid===v);if(c){callSheet(c);it=1;}}
  if(it||!refreshing)pendingDeep=null;return !!it;}

function initBridge(){
  A=window.ChatHelpApp||null;
  if(!A){D.pushState='unsupported';return;}
  try{A.init({getKey:()=>KEY,label:devLabel()});}catch(e){}
  A.onNotificationTap(d=>handleTap(d));
  if(IS_NATIVE||STANDALONE||IS_IOS||IS_ANDROID||/^(pwa|native|push)/.test(new URLSearchParams(location.search).get('src')||''))try{A.setShell&&A.setShell('app');}catch(e){}
  window.addEventListener('chathelp:push',e=>{const p=e.detail||{};if(typeof p.badge==='number')setUnread(p.badge);refresh();});
  window.addEventListener('chathelp:installable',()=>{if(ROUTE==='mehr'||ROUTE==='benachrichtigungen')render();});
  A.pushState().then(s=>{D.pushState=s;if(ROUTE==='heute')renderHeute();}).catch(()=>{});
  paintSystemBars();
  if(IS_NATIVE){const App=cap('App');if(App&&App.addListener){
    App.addListener('backButton',()=>{if(sheetOpen)closeSheet();else if(ROUTE!=='heute')go('heute');else if(App.minimizeApp)App.minimizeApp();else if(App.exitApp)App.exitApp();});
    App.addListener('appStateChange',s=>{if(s&&s.isActive&&KEY)refresh();});}
    const SS=cap('SplashScreen');if(SS&&SS.hide)try{SS.hide();}catch(e){}}
}

/* ---------- Los geht's ---------- */
document.addEventListener('DOMContentLoaded',()=>{boot().catch(e=>showLogin(esc(e.message||_T('a.startErr')),'err'));});
</script>
</body>
</html>
