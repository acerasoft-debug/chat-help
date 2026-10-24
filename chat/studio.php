<?php require_once __DIR__.'/pagever.php'; $CH_PANELS=[]; foreach(['einrichtung'=>'Einrichtung','assistant'=>'Assistent','training'=>'Training','rueckruf'=>'Rückrufe','taxi'=>'Taxi','hotel'=>'Hotel','benachrichtigungen'=>'Benachrichtigungen','kunden'=>'Kunden','voices'=>'Stimmen','website-builder'=>'Website erstellen'] as $k=>$l){ $h=__DIR__."/studio-panels/$k.html"; $j=__DIR__."/studio-panels/$k.js"; if(is_file($h) && is_file($j)) $CH_PANELS[$k]=['label'=>$l,'v'=>substr(md5(filemtime($h).'|'.filemtime($j)),0,8)]; } ch_page_version(__FILE__, array_merge(...array_map(fn($k)=>[__DIR__."/studio-panels/$k.html",__DIR__."/studio-panels/$k.js"],array_keys($CH_PANELS)))); require_once __DIR__.'/studio-i18n.php'; [$STI_L,$STI_S]=sti_lang(); header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false); header('X-Robots-Tag: noindex'); ?><!DOCTYPE html>
<html lang="<?= $STI_L==='no' ? 'nb' : $STI_L ?>" dir="ltr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title data-i18n="st.chathelp_studio_agenten_tele">ChatHelp Studio – Agenten, Telefon, Widget & Tokens</title><meta name="robots" content="noindex">
<?= sti_head($STI_L,$STI_S) ?>
<link rel="manifest" href="/chat/app.webmanifest"><meta name="theme-color" content="#ffffff"><link rel="apple-touch-icon" href="/chat/icons/apple-touch-icon.png"><link rel="icon" type="image/png" sizes="32x32" href="/chat/icons/icon-32.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="ChatHelp"><script>window.CH_BRIDGE_SRC="/chat/app-bridge.js?v=<?= substr(md5((string)@filemtime(__DIR__.'/app-bridge.js')),0,8) ?>";</script><script src="/chat/app-bridge.js?v=<?= substr(md5((string)@filemtime(__DIR__.'/app-bridge.js')),0,8) ?>" defer></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap">
<style>
:root{--bg:#f6f7fb;--sur:#fff;--sur2:#f1f2f7;--line:#e4e6ee;--line2:#d6d9e4;--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--mute2:#9094a6;--gold:#b8862b;--gold2:#d4a84a;--goldl:#f6ecd3;--indigo:#4f46e5;--indigol:#eceafe;--teal:#0f9f9c;--teall:#e2f6f4;--rose:#d9336b;--rosel:#fde7ee;--ok:#1a9e5a;--okl:#e4f5ea;
--sans:'Inter',system-ui,sans-serif;--serif:'Fraunces',Georgia,serif;--sh1:0 1px 2px rgba(15,18,34,.05),0 8px 26px rgba(15,18,34,.06);--sh2:0 2px 6px rgba(15,18,34,.06),0 24px 60px rgba(15,18,34,.12)}
*{box-sizing:border-box}html,body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 var(--sans);-webkit-font-smoothing:antialiased}
a{color:var(--gold);text-decoration:none}button,input,select,textarea{font:inherit}h1,h2,h3{font-family:var(--serif);font-weight:600;letter-spacing:-.01em;margin:0}
.top{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 22px;background:rgba(255,255,255,.85);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
#acct{flex-wrap:nowrap;min-width:0}#acct .pill,#acct .btn{white-space:nowrap}#acct #who{overflow:hidden;text-overflow:ellipsis;max-width:220px}
@media(max-width:720px){.top{padding:10px 14px;gap:8px}.top .brand{font-size:19px}.top .brand .mark{width:30px;height:30px}#acct{gap:6px}#acct #who{display:none}#acct .pill{padding:6px 9px;font-size:12px}#acct .btn{padding:7px 10px;font-size:12px}}
.tab.tab-all{border:1px dashed var(--line2);color:var(--mute);background:transparent}.tab.tab-all:hover{color:var(--ink)}
@media(max-width:420px){#acct .btn.gold{display:none}.top{padding:9px 12px}.top .logo{font-size:0;gap:0}.top .logo .em{margin:0}#acct .pill{padding:6px 8px;font-size:11.5px}#acct #logout{padding:7px 9px;font-size:11.5px}}
.logo{display:flex;align-items:center;gap:10px;font:600 19px var(--serif);color:var(--ink)}.logo .em{width:34px;height:34px;border-radius:11px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center}.logo span.s{color:var(--gold)}
.nav2{position:sticky;top:61px;z-index:4;background:rgba(246,247,251,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}.nav2 .tabs{max-width:1240px;margin:0 auto;padding:8px 22px;overflow-x:auto;scrollbar-width:none}.nav2 .tabs::-webkit-scrollbar{display:none}
.tabs{display:flex;gap:4px}.tab{white-space:nowrap}.tab .dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--ok);margin-left:6px;vertical-align:middle}.tabsep{width:1px;background:var(--line);margin:6px 6px;flex:0 0 auto}.tab{padding:8px 14px;border-radius:999px;border:1px solid transparent;background:transparent;color:var(--ink2);font-weight:500;cursor:pointer}.tab:hover{background:var(--sur2)}.tab.on{background:var(--ink);color:#fff}
.pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid var(--line);border-radius:999px;color:var(--ink2);font-size:13px;background:#fff}.pill b{color:var(--ink)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:12px;border:1px solid var(--line2);background:#fff;color:var(--ink);cursor:pointer;font-weight:600;transition:.15s;white-space:nowrap}@media(max-width:600px){.btn{max-width:100%;white-space:normal;text-align:center}}.btn:hover{border-color:#c3c7d4;box-shadow:var(--sh1)}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.p:hover{background:#1c2140}.btn.gold{background:linear-gradient(135deg,#ecd08a,#c9972f);color:#1a1407;border-color:transparent}.btn.s{padding:7px 11px;font-size:13px}.btn:disabled{opacity:.5;cursor:not-allowed}.btn.ghost{background:transparent;border-color:transparent;color:var(--gold)}
.wrap{max-width:1240px;margin:0 auto;padding:26px 22px 70px}
.card{background:var(--sur);border:1px solid var(--line);border-radius:22px;padding:22px;box-shadow:var(--sh1)}
.grid{display:grid;gap:16px}.g3{grid-template-columns:repeat(3,1fr)}.g2{grid-template-columns:1fr 1fr}.g4{grid-template-columns:repeat(4,1fr)}@media(max-width:1000px){.g3,.g4{grid-template-columns:1fr 1fr}}@media(max-width:680px){.g3,.g2,.g4{grid-template-columns:1fr}}
.tag{display:inline-flex;align-items:center;gap:6px;font:600 11px var(--sans);letter-spacing:.08em;text-transform:uppercase;padding:6px 10px;border-radius:999px;background:var(--sur2);color:var(--ink2);border:1px solid var(--line)}
.tag.ok{background:var(--okl);color:var(--ok);border-color:#bfe8cf}.tag.gold{background:var(--goldl);color:var(--gold);border-color:#eadbb0}.tag.indigo{background:var(--indigol);color:var(--indigo);border-color:#d6d2fb}.tag.rose{background:var(--rosel);color:var(--rose);border-color:#f7c6d5}.tag.teal{background:var(--teall);color:var(--teal);border-color:#bfe9e5}
label{display:block;font:600 12.5px var(--sans);color:var(--mute);margin:12px 0 6px}
input[type=text],input[type=email],input[type=tel],select,textarea{width:100%;padding:11px 13px;border-radius:12px;border:1px solid var(--line2);background:#fff;color:var(--ink);outline:0}input:focus,select:focus,textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.12)}
textarea{min-height:160px;resize:vertical;line-height:1.5}
.mute{color:var(--mute)}.small{font-size:12.5px}.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.num{font:800 34px/1.1 var(--sans)}
.hidden{display:none !important}
/* login */
.login{min-height:calc(100vh - 62px);display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;max-width:1100px;margin:0 auto;padding:40px 22px}@media(max-width:900px){.login{grid-template-columns:1fr;padding:26px 18px}}
.login h1{font-size:clamp(34px,4.5vw,52px);line-height:1.08}.login .sub{color:var(--ink2);font-size:17px;margin-top:14px;max-width:56ch}
.perk{display:flex;gap:12px;padding:12px 0;border-top:1px solid var(--line)}.perk i{width:30px;height:30px;border-radius:10px;background:var(--goldl);color:var(--gold);display:grid;place-items:center;font-style:normal;font-weight:800;flex:0 0 auto}.perk b{display:block;font-size:14.5px}.perk span{color:var(--ink2);font-size:13.5px}
.lbox{padding:30px;border-radius:26px;box-shadow:var(--sh2)}.lbox h2{font-size:26px}.code{letter-spacing:.3em;font:700 26px ui-monospace,Menlo,Consolas,monospace;text-align:center}
.msg{padding:10px 12px;border-radius:12px;font-size:13.5px;margin-top:12px}.msg.ok{background:var(--okl);color:#15703f}.msg.err{background:var(--rosel);color:#9f2448}.msg.info{background:var(--indigol);color:#3730a3}
/* agents */
.ag{display:flex;flex-direction:column;cursor:pointer;transition:.18s}.ag:hover{transform:translateY(-2px);box-shadow:var(--sh2)}.ag h3{font-size:18px;margin:12px 0 4px}.ag p{color:var(--ink2);font-size:13.5px;margin:0}.ag .pr{margin-top:auto;padding-top:12px;color:var(--mute);font-size:13px}
.ag .ic{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font:700 19px var(--sans);background:var(--indigol);color:var(--indigo)}
/* workspace */
.ws{display:grid;grid-template-columns:400px 1fr;gap:18px;align-items:start}@media(max-width:1000px){.ws{grid-template-columns:1fr}.ws[style*="1fr 1fr"]{grid-template-columns:1fr!important}}.ws>*,.grid>*{min-width:0}
.chat{display:flex;flex-direction:column;height:calc(100vh - 150px);min-height:560px}.chat .ms{flex:1;overflow:auto;display:flex;flex-direction:column;gap:10px;padding:4px 2px}
.m{max-width:85%;padding:11px 14px;border-radius:16px;white-space:pre-wrap;line-height:1.5;font-size:14.5px}.m.u{align-self:flex-end;background:var(--ink);color:#fff;border-top-right-radius:6px}.m.a{align-self:flex-start;background:var(--sur2);border-top-left-radius:6px}.m.sys{align-self:center;color:var(--mute);font-size:12.5px;background:transparent}
.m .meta{display:block;font-size:11px;color:var(--mute2);margin-top:6px}
.chat form{display:flex;gap:8px;margin-top:10px}.chat textarea{min-height:48px;max-height:160px;flex:1}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}.chip{border:1px solid var(--line2);background:#fff;border-radius:999px;padding:6px 10px;font-size:12.5px;color:var(--ink2);cursor:pointer}.chip:hover{border-color:var(--gold)}
.sw{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--sur2);margin-top:10px}.sw input{width:18px;height:18px;accent-color:var(--gold)}
.snip{font:12.5px ui-monospace,Menlo,Consolas,monospace;background:#0f1222;color:#e6e8f5;border-radius:12px;padding:12px;word-break:break-all;white-space:pre-wrap;margin-top:8px}
.sec{border-top:1px solid var(--line);margin-top:18px;padding-top:16px}.sec h3{font-size:17px}
.call{border:1px solid var(--line);border-radius:14px;padding:12px 14px;margin-top:10px;background:#fff}.call .hd{display:flex;justify-content:space-between;gap:10px;font-size:13px;color:var(--ink2);cursor:pointer}.call .tr{display:none;margin-top:10px;display:none;flex-direction:column;gap:6px}.call.open .tr{display:flex}.call .tr div{font-size:13px;padding:7px 10px;border-radius:10px;max-width:92%}.call .tr .a{background:var(--sur2);align-self:flex-start}.call .tr .u{background:var(--ink);color:#fff;align-self:flex-end}
.mic{width:46px;border-radius:12px;border:1px solid var(--line2);background:#fff;font-size:18px}.mic.rec{background:var(--rosel);border-color:#f7c6d5;animation:pl 1.4s infinite}@keyframes pl{70%{box-shadow:0 0 0 9px rgba(217,51,107,0)}100%{box-shadow:0 0 0 0 rgba(217,51,107,0)}}
table{width:100%;border-collapse:collapse;font-size:13.5px}td,th{padding:9px 8px;border-bottom:1px solid var(--line);text-align:left}th{color:var(--mute);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
.guide{margin-top:12px;border:1px solid var(--line);border-radius:14px;background:var(--sur2)}.guide summary{cursor:pointer;padding:12px 14px;font-weight:700;font-size:14px;list-style:none}.guide summary::-webkit-details-marker{display:none}.guide summary::after{content:"＋";float:right;color:var(--gold)}.guide[open] summary::after{content:"－"}
.guide ol{margin:0;padding:0 16px 14px 34px;font-size:13.5px;color:var(--ink2);line-height:1.6}.guide ol li{margin:6px 0}.guide code{font:12px ui-monospace,Menlo,Consolas,monospace;background:#fff;border:1px solid var(--line);border-radius:6px;padding:1px 5px}.guide .tip{padding:0 16px 14px;font-size:12.5px;color:var(--mute)}
.banner{display:flex;gap:12px;align-items:center;padding:12px 16px;border-radius:14px;background:var(--goldl);border:1px solid #eadbb0;color:#5c4411;margin-bottom:16px}
/* login · premium */
.login-wrap{position:relative;overflow:hidden;background:radial-gradient(640px 460px at 82% 26%,rgba(212,168,74,.20),rgba(212,168,74,0) 70%),radial-gradient(520px 420px at 8% 92%,rgba(79,70,229,.06),rgba(79,70,229,0) 70%),var(--bg)}
.login .kick{display:inline-flex;align-items:center;gap:8px;font:600 11.5px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--gold);padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.7);border:1px solid #eadbb0}
.login .kick i{width:6px;height:6px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 4px rgba(184,134,43,.15)}
.login h1 em{font-style:italic;color:var(--gold);font-weight:500}
.trust{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:24px}@media(max-width:560px){.trust{grid-template-columns:1fr}}
.trust>div{display:flex;gap:10px;align-items:flex-start;min-width:0;padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.75);border:1px solid var(--line);box-shadow:var(--sh1)}@media(min-width:901px) and (max-width:1100px){.trust{grid-template-columns:1fr}}
.trust svg{flex:0 0 auto;margin-top:2px}.trust b{display:block;font-size:13.5px;line-height:1.3}.trust span{display:block;color:var(--mute);font-size:12.5px;line-height:1.35;margin-top:2px}
.lsteps{margin-top:22px;display:grid;gap:2px}
@media(max-width:900px){.login{gap:0}.login>div:first-child{display:contents}.login .kick{order:1;justify-self:start}.login h1{order:2}.login .sub{order:3;font-size:16px}.login .lbox{order:4;margin-top:22px;padding:24px 20px}.login .trust{order:5}.login .lsteps{order:6}}.lsteps .perk{border-top:1px solid rgba(15,18,34,.07)}
.lbox{position:relative;border:1px solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(140deg,#ecd08a,#e4e6ee 38%,#e4e6ee 62%,#c9972f) border-box;box-shadow:0 2px 6px rgba(15,18,34,.05),0 34px 80px -28px rgba(184,134,43,.38)}
.lbox h2{font-size:28px;line-height:1.15}.lbox .lsub{color:var(--ink2);font-size:14px;margin:8px 0 4px}
.lbox input[type=email]{height:52px;font-size:16px;border-radius:14px}
.btn.cta{width:100%;min-height:52px;margin-top:14px;border-radius:14px;font-size:15.5px;background:var(--ink);color:#fff;border-color:var(--ink);box-shadow:0 12px 26px -12px rgba(15,18,34,.55)}.btn.cta:hover{background:#1c2140}.btn.cta .ar{color:var(--gold2);transition:transform .2s}.btn.cta:hover .ar{transform:translateX(3px)}
.lbox .btn.ghost.s{min-height:44px}
.otp{position:relative;height:60px;margin-top:2px}.otp input{position:absolute;inset:0;z-index:2;width:100%;height:100%;border:0!important;background:transparent!important;box-shadow:none!important;color:transparent;caret-color:transparent;font-size:16px;letter-spacing:0;padding:0;outline:0}.otp input::selection{background:transparent}
.otp-slots{position:absolute;inset:0;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;pointer-events:none}
.otp-slots span{display:grid;place-items:center;border:1px solid var(--line2);border-radius:14px;background:#fff;font:700 24px ui-monospace,Menlo,Consolas,monospace;color:var(--ink);transition:border-color .15s,box-shadow .15s,background .15s}
.otp-slots span.f{border-color:#dcc48f;background:#fffaf0}.otp-slots span.on{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.16)}
.otp-slots span.on:empty::after{content:"";width:2px;height:26px;background:var(--gold);animation:otpc 1s steps(1) infinite}@keyframes otpc{50%{opacity:0}}
.lfine{color:var(--mute);font-size:12px;margin-top:16px;line-height:1.5}
/* Profil-Assistent */
body.pf-lock{overflow:hidden}
.pfw{position:fixed;inset:0;z-index:50;display:grid;place-items:center;padding:24px 16px;background:rgba(15,18,34,.42);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);animation:pfFade .25s ease}
.pfw-card{position:relative;width:min(760px,100%);max-height:min(860px,calc(100vh - 48px));display:flex;flex-direction:column;background:#fff;border-radius:28px;overflow:hidden;box-shadow:0 40px 120px -30px rgba(15,18,34,.55),0 0 0 1px rgba(184,134,43,.22)}
.pfw-card::before{content:"";position:absolute;inset:0 0 auto 0;height:220px;background:radial-gradient(520px 200px at 80% -40px,rgba(212,168,74,.22),rgba(212,168,74,0) 70%);pointer-events:none}
.pfw-hd{position:relative;display:flex;align-items:center;gap:14px;padding:18px 22px 12px}
.pfw-hd .logo .em{width:32px;height:32px}.pfw-prog{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;align-items:center}
.pfw-step{font:600 11.5px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--mute)}
.pfw-dots{display:flex;gap:6px}.pfw-dots i{width:8px;height:8px;border-radius:99px;background:var(--line2);transition:width .3s,background .3s}.pfw-dots i.done{background:var(--gold2)}.pfw-dots i.on{width:26px;background:linear-gradient(90deg,#ecd08a,#c9972f)}
.pfw-x{min-height:44px;padding:0 14px;border-radius:12px;border:1px solid transparent;background:transparent;color:var(--ink2);font-weight:600;cursor:pointer}.pfw-x:hover{background:var(--sur2)}
.pfw-body{position:relative;flex:1;overflow:auto;padding:6px 30px 18px;overscroll-behavior:contain}
.pfw-s{animation:pfIn .38s cubic-bezier(.2,.7,.2,1)}.pfw-s.back{animation-name:pfInB}
@keyframes pfIn{from{opacity:0;transform:translateX(22px)}to{opacity:1;transform:none}}@keyframes pfInB{from{opacity:0;transform:translateX(-22px)}to{opacity:1;transform:none}}@keyframes pfFade{from{opacity:0}to{opacity:1}}
.pfw-kick{font:600 12px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--gold);margin:6px 0 8px}
.pfw h2{font-size:clamp(26px,4.4vw,36px);line-height:1.12;outline:0}.pfw-sub{color:var(--ink2);margin:10px 0 0;font-size:15px;max-width:60ch}
.pfw-kinds{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}@media(max-width:560px){.pfw-kinds{grid-template-columns:1fr}}
.pfw-kind{display:flex;flex-direction:column;align-items:flex-start;gap:6px;text-align:left;min-height:150px;padding:20px;border-radius:20px;border:1.5px solid var(--line2);background:#fff;cursor:pointer;color:var(--ink);transition:border-color .15s,box-shadow .15s,transform .15s}
.pfw-kind:hover{border-color:#cdb27a;box-shadow:var(--sh1)}.pfw-kind .ic{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-size:22px;background:var(--goldl)}.pfw-kind b{font:600 19px var(--serif);margin-top:6px}.pfw-kind span:last-child{color:var(--ink2);font-size:13.5px}
.pfw-kind[aria-checked=true]{border-color:var(--gold);box-shadow:0 0 0 4px rgba(184,134,43,.14),var(--sh1);background:linear-gradient(180deg,#fffaf0,#fff)}
.pfw-lbl{display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin:18px 0 8px;font:600 12.5px var(--sans);color:var(--mute)}.pfw-lbl label{margin:0}
.pfw input[type=search]{width:100%;padding:11px 13px;border:1px solid var(--line2);background:#fff;color:var(--ink);outline:0;-webkit-appearance:none;appearance:none}
.pfw input[type=text],.pfw input[type=search],.pfw select{min-height:48px;font-size:16px;border-radius:14px}
.pfw-chips{display:flex;flex-wrap:wrap;gap:8px}.pfw-scroll{max-height:236px;overflow:auto;padding:2px;margin-top:10px}
.pc{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:9px 15px;border-radius:999px;border:1px solid var(--line2);background:#fff;color:var(--ink2);font-weight:500;font-size:14px;cursor:pointer;transition:background .15s,border-color .15s,color .15s}
.pc:hover{border-color:var(--gold)}.pc[aria-pressed=true],.pc[aria-checked=true]{background:var(--ink);border-color:var(--ink);color:#fff}.pc[aria-pressed=true]::after{content:"✓";color:var(--gold2);font-weight:700}
.pc.top{border-color:#e0c98f;background:#fffaf0}.pc.top[aria-pressed=true]{background:var(--ink)}.pfw-chips.big .pc{min-height:52px;padding:12px 18px;font-size:15px;border-radius:16px}
.pfw-seg{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}@media(max-width:420px){.pfw-seg{grid-template-columns:1fr 1fr}}.pfw-seg .pc{justify-content:center;border-radius:14px}
.pfw-2{display:grid;grid-template-columns:1.4fr 1fr;gap:12px}@media(max-width:560px){.pfw-2{grid-template-columns:1fr;gap:0}}
.pfw-sep{height:1px;background:var(--line);margin:22px 0 4px}
.pfw-ft{position:relative;display:flex;align-items:center;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--line);background:rgba(255,255,255,.96)}.pfw-ft .sp{flex:1}.pfw-ft .btn{min-height:48px;padding:0 20px;border-radius:14px}
.pfw-ft #pfNext{background:var(--ink);color:#fff;border-color:var(--ink)}.pfw-ft #pfNext.fin{background:linear-gradient(135deg,#ecd08a,#c9972f);color:#1a1407;border-color:transparent}
.pfw-areas{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}.pfw-areas span{padding:7px 12px;border-radius:999px;background:var(--goldl);border:1px solid #eadbb0;color:#5c4411;font-size:13px;font-weight:600}
.pfw-link{margin-top:14px;min-height:44px;padding:0;border:0;background:none;color:var(--gold);font-weight:700;cursor:pointer}
.pfw-load{display:flex;align-items:center;gap:12px;margin-top:28px;color:var(--ink2)}.pfw-load i{width:22px;height:22px;border-radius:50%;border:3px solid var(--goldl);border-top-color:var(--gold);animation:pfSpin .9s linear infinite}@keyframes pfSpin{to{transform:rotate(360deg)}}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
@media(max-width:600px){.pfw{padding:0}.pfw-card{width:100%;height:100%;max-height:none;border-radius:0}.pfw-hd{padding:12px 14px 8px;gap:8px}.pfw-hd .logo{display:none}.pfw-body{padding:4px 16px 16px}.pfw-ft{padding:10px 14px calc(12px + env(safe-area-inset-bottom));gap:6px}.pfw-ft .btn{padding:0 14px}}
@media(max-width:380px){.pfw-ft #pfSkip{padding:0 8px}}
/* Empfehlungen (Assistent-Zusammenfassung + Übersicht) */
.rec-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:12px;margin-top:16px}
.rec-c{display:flex;flex-direction:column;gap:8px;padding:16px;border-radius:18px;border:1px solid var(--line);background:#fff}.rec-c p{margin:0;color:var(--ink2);font-size:13.5px;line-height:1.5}
.rec-h{display:flex;gap:12px;align-items:flex-start}.rec-ic{flex:0 0 auto;width:42px;height:42px;border-radius:13px;display:grid;place-items:center;font:700 18px var(--sans);background:var(--goldl);color:var(--gold)}.rec-h b{display:block;font-size:15px;line-height:1.3}.rec-why{color:var(--gold);font-size:12.5px;font-weight:600;margin-top:3px}
.rec-a{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;padding-top:4px}.rec-a .btn{min-height:44px;flex:1 1 auto}.rec-pr{color:var(--mute);font-size:12px}
.card.rec{border-color:#eadbb0;background:linear-gradient(180deg,#fffaf0 0,#fff 140px)}.card.rec h3,.rec-cta h3{font-size:22px;margin-top:10px}
.rec-cta{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;border-color:#eadbb0;background:radial-gradient(420px 160px at 100% 0,rgba(212,168,74,.18),rgba(212,168,74,0) 70%),#fff}.rec-cta>div{flex:1 1 320px;min-width:0}.rec-cta .btn{min-height:48px}
#profBtn{min-height:36px}@media(max-width:720px){#profBtn{display:none}}
@media (prefers-reduced-motion:reduce){.pfw,.pfw *,.otp-slots span,.btn.cta .ar{animation:none!important;transition:none!important}}
/* Sprachauswahl (wie auf der Startseite) */
.topr{display:flex;align-items:center;gap:8px;min-width:0;flex-wrap:nowrap}
.lsw{display:inline-flex;align-items:stretch;height:36px;border:1px solid var(--line2);border-radius:999px;background:var(--sur);flex:0 0 auto}
.lsx{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:3px;padding:0 10px;font:600 12.5px/1 var(--sans);color:var(--ink2);cursor:pointer;border-radius:999px;margin:0}
.lsx b{display:inline-block;font-weight:700;color:var(--ink);letter-spacing:.03em;text-align:center;width:2.1em}.lsx i{font-style:normal;font-size:9px;color:var(--mute2)}.lsx:hover b{color:var(--gold)}
.lsx select{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;font-size:16px;border:0;padding:0;-webkit-appearance:none;appearance:none}
.lsx:focus-within{outline:2px solid var(--gold);outline-offset:2px}
@media(max-width:420px){.lsw{height:32px}.lsx{padding:0 7px;font-size:11.5px}}
/* lange Wörter (DE/FI/TR) und schmale Spalten */
.card h2,.card h3,.tag,.pill,.ag p,.mute,.call b,label{overflow-wrap:anywhere}
</style></head>
<body>
<div class="top">
  <a class="logo" href="/"><span class="em"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span>ChatHelp <span class="s">Studio</span></a>
  <div class="topr"><div class="lsw" id="stLsw" role="group"></div>
  <div class="row hidden" id="acct"><span class="pill" data-i18n-html="st.guthaben_tokens">Guthaben <b id="bal">–</b> Tokens</span><a class="btn s gold" href="/#preise" target="_blank" data-i18n="st.tokens_kaufen">Tokens kaufen</a><span class="pill small" id="who"></span><button class="btn s hidden" id="profBtn" type="button" title="Profil bearbeiten: Betrieb, Branche, Bedarf, Sprachen" data-i18n="st.profil" data-i18n-attr="title:st.profil_bearbeiten_betrieb_br">✎ Profil</button><button class="btn s" id="logout" data-i18n="st.abmelden">Abmelden</button></div>
</div></div>

<!-- ===== LOGIN ===== -->
<div id="vLogin" class="login-wrap"><div class="login">
  <div>
    <span class="kick" data-i18n-t="st.chathelp_studio_kostenlos_st"><i></i>ChatHelp Studio · kostenlos starten</span>
    <h1 style="margin-top:16px" data-i18n-html="st.dein_agent_arbeitet_heute">Dein Agent arbeitet <em>heute noch.</em></h1>
    <p class="sub" data-i18n-html="st.telefon_whatsapp_und_website">Telefon, WhatsApp und Website-Chat – ein KI-Agent, der zu deinem Betrieb passt. Anmelden, kurz erzählen, wofür du ihn brauchst, und sofort live testen. <b>300 Tokens geschenkt.</b></p>
    <div class="trust" role="list">
      <div role="listitem"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#f6ecd3"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#b8862b" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg><div><b data-i18n="st.keine_kreditkarte">Keine Kreditkarte</b><span data-i18n="st.testen_ohne_zahlungsdaten">Testen ohne Zahlungsdaten</span></div></div>
      <div role="listitem"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#f6ecd3"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#b8862b" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg><div><b data-i18n="st.in_10_minuten_startklar">In 10 Minuten startklar</b><span data-i18n="st.agent_waehlen_fakten_rein">Agent wählen, Fakten rein, testen</span></div></div>
      <div role="listitem"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#f6ecd3"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#b8862b" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg><div><b data-i18n="st.datenverarbeitung_nach_dsgvo">Datenverarbeitung nach DSGVO</b><span data-i18n="st.plaene_monatlich_kuendbar">Pläne monatlich kündbar</span></div></div>
    </div>
    <div class="lsteps">
      <div class="perk"><i>1</i><div><b data-i18n="st.kurz_erzaehlen_wer_du">Kurz erzählen, wer du bist</b><span data-i18n="st.betrieb_oder_privat_branche">Betrieb oder privat, Branche, Bedarf, Sprachen – vier kurze Schritte, alle freiwillig.</span></div></div>
      <div class="perk"><i>2</i><div><b data-i18n="st.passenden_agenten_live_teste">Passenden Agenten live testen</b><span data-i18n="st.im_chat_oder_wie">Im Chat oder wie am Telefon – mit deinen eigenen Fakten, bevor du etwas buchst.</span></div></div>
      <div class="perk"><i>3</i><div><b data-i18n="st.freischalten_wenn_es_passt">Freischalten, wenn es passt</b><span data-i18n="st.website_widget_telefon_und">Website-Widget, Telefon und WhatsApp mit aktivem Plan – alle Gespräche im Studio.</span></div></div>
    </div>
  </div>
  <div class="card lbox">
    <div id="welcome" class="banner hidden" data-i18n="st.zahlung_erfolgreich_melde_di">🎉 Zahlung erfolgreich! Melde dich mit der E-Mail aus dem Checkout an – dein Agent ist bereits freigeschaltet.</div>
    <h2 data-i18n="st.anmelden_oder_registrieren">Anmelden oder registrieren</h2><p class="lsub" data-i18n="st.ein_konto_fuer_studio">Ein Konto für Studio und App. Wir senden dir einen 6-stelligen Code – kein Passwort nötig.</p>
    <div id="stepEmail"><label for="lEmail" data-i18n="st.mail">E-Mail</label><input type="email" id="lEmail" placeholder="du@firma.de" autocomplete="email" inputmode="email"><button class="btn cta" id="lSend" data-i18n-t="st.code_senden">Code senden <span class="ar" aria-hidden="true">→</span></button></div>
    <div id="stepCode" class="hidden"><label for="lCode" data-i18n-t="st.code_aus_der_mail">Code aus der E-Mail <span class="mute" id="lTo"></span></label><div class="otp" id="lOtp"><input type="text" id="lCode" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" autocomplete="one-time-code" aria-label="6-stelliger Code" data-i18n-attr="aria-label:st.stelliger_code"><div class="otp-slots" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div></div><div class="row" style="justify-content:flex-end;margin-top:4px"><button type="button" class="btn ghost s" id="lPaste" data-i18n="st.code_einfuegen">📋 Code einfügen</button></div><button class="btn cta" id="lVerify" style="margin-top:6px" data-i18n-t="st.anmelden">Anmelden <span class="ar" aria-hidden="true">→</span></button><div class="row" style="justify-content:space-between;margin-top:8px"><button class="btn ghost s" id="lBack" data-i18n="st.andere_mail">andere E-Mail</button><button class="btn ghost s" id="lResend" data-i18n="st.code_erneut_senden">Code erneut senden</button></div></div>
    <div id="lMsg" aria-live="polite"></div>
    <details style="margin-top:18px"><summary class="mute small" style="cursor:pointer;min-height:32px" data-i18n="st.mit_api_key_anmelden">Mit API-Key anmelden (ch_live_…)</summary><label for="lKey">API-Key</label><input type="text" id="lKey" placeholder="ch_live_…"><button class="btn" id="lKeyGo" style="width:100%;margin-top:10px;min-height:44px" data-i18n="st.weiter">Weiter</button></details>
    <p class="lfine" data-i18n-html="st.mit_der_anmeldung_akzeptiers">Mit der Anmeldung akzeptierst du die <a href="/agb.html">AGB</a> und <a href="/datenschutz.html">Datenschutzhinweise</a>. Spam-Ordner prüfen, falls der Code nicht ankommt. · <a href="/impressum.html">Impressum</a></p>
  </div>
</div></div>

<!-- ===== APP ===== -->
<div class="nav2 hidden" id="nav2"><div class="tabs" id="tabs"><button class="tab on" data-v="home" data-i18n="st.uebersicht">Übersicht</button><?php if(isset($CH_PANELS["einrichtung"])): ?><button class="tab" data-v="einrichtung" data-i18n="st.einrichtung">✓ Einrichtung</button><?php endif; ?><?php if(isset($CH_PANELS["assistant"])): ?><button class="tab" data-v="assistant" data-i18n="st.assistent">✦ Assistent</button><?php endif; ?><button class="tab" data-v="agents" data-i18n="st.agenten">Agenten</button><span class="tabsep" data-sep="ch"></span><button class="tab" data-v="whatsapp" data-ch="wa" data-i18n="st.whatsapp">💬 WhatsApp</button><button class="tab" data-v="phone" data-ch="tel" data-i18n="st.telefon">☎ Telefon</button><?php if(isset($CH_PANELS["voices"])): ?><button class="tab" data-v="voices" data-i18n="st.stimmen">🎙 Stimmen</button><?php endif; ?><button class="tab" data-v="website" data-ch="web" data-i18n="st.website">🌐 Website</button><?php if(isset($CH_PANELS["website-builder"])): ?><button class="tab" data-v="website-builder" data-i18n="st.website_erstellen">✎ Website erstellen</button><?php endif; ?><span class="tabsep" data-sep="mod"></span><button class="tab" data-v="termine" data-i18n="st.termine">Termine</button><button class="tab" data-v="bestellungen" data-i18n="st.bestellungen">Bestellungen</button><?php if(isset($CH_PANELS["taxi"])): ?><button class="tab" data-v="taxi" data-i18n="st.taxi">Taxi</button><?php endif; ?><?php if(isset($CH_PANELS["hotel"])): ?><button class="tab" data-v="hotel" data-i18n="st.hotel">Hotel</button><?php endif; ?><?php if(isset($CH_PANELS["rueckruf"])): ?><button class="tab" data-v="rueckruf" data-i18n="st.rueckrufe">Rückrufe</button><?php endif; ?><?php if(isset($CH_PANELS["kunden"])): ?><button class="tab" data-v="kunden" data-i18n="st.kunden">Kunden</button><?php endif; ?><?php if(isset($CH_PANELS["training"])): ?><button class="tab" data-v="training" data-i18n="st.training">Training</button><?php endif; ?><span class="tabsep" data-sep="end"></span><?php if(isset($CH_PANELS["benachrichtigungen"])): ?><button class="tab" data-v="benachrichtigungen" data-i18n-t="st.benachrichtigungen">🔔 Benachrichtigungen<span class="dot hidden" id="bellDot" style="background:var(--rose)"></span></button><?php endif; ?><button class="tab" data-v="tokens" data-i18n="st.tokens_api">Tokens &amp; API</button><button class="tab tab-all" id="navAll" type="button" title="Alle Bereiche ein- oder ausblenden" data-i18n="st.alle_bereiche" data-i18n-attr="title:st.alle_bereiche_ein_oder">＋ Alle Bereiche</button></div></div>
<div id="vApp" class="wrap hidden">
  <?php foreach($CH_PANELS as $k=>$x): ?><div id="panel-<?= $k ?>" class="hidden"></div><?php endforeach; ?>
  <!-- Übersicht -->
  <div id="pHome">
    <div id="byokBanner" class="banner hidden" role="alert" aria-live="polite"></div>
    <div id="trialBanner" class="banner hidden" data-i18n-html="st.willkommen_300_tokens_sind">🎁 Willkommen! <b>&nbsp;300 Tokens&nbsp;</b> sind auf deinem Konto. Wähle einen Agenten, füge deine Wissensbasis ein und teste ihn – jede Antwort kostet ca. 6 Tokens.</div>
    <div id="hRec" style="margin-bottom:16px" class="hidden"></div>
    <div class="grid g4">
      <div class="card"><span class="tag" data-i18n="st.guthaben">Guthaben</span><div class="num" style="margin-top:10px" id="hBal">–</div><div class="mute small" data-i18n="st.tokens_verfallen_nicht">Tokens · verfallen nicht</div><a class="btn s gold" href="/#preise" target="_blank" style="margin-top:12px" data-i18n="st.tokens_kaufen">Tokens kaufen</a></div>
      <div class="card"><span class="tag indigo" data-i18n="st.aktive_agenten">Aktive Agenten</span><div class="num" style="margin-top:10px" id="hPlans">0</div><div class="mute small" data-i18n="st.gemietet_oder_gekauft">gemietet oder gekauft</div><button class="btn s" style="margin-top:12px" onclick="go('agents')" data-i18n="st.agenten_ansehen">Agenten ansehen</button></div>
      <div class="card"><span class="tag teal" data-i18n="st.gespraeche_30_tage">Gespräche (30 Tage)</span><div class="num" style="margin-top:10px" id="hConv">–</div><div class="mute small" data-i18n="st.studio_widget_telefon">Studio · Widget · Telefon</div></div>
      <div class="card"><span class="tag gold" data-i18n="st.telefon_termine">Telefon &amp; Termine</span><div class="num" style="margin-top:10px;font-size:22px" id="hPhone">–</div><div class="mute small" data-i18n="st.telefon_agent_status">Telefon-Agent Status</div><div class="row" style="margin-top:12px"><button class="btn s" onclick="go('phone')" data-i18n="st.telefon_2">Telefon</button><button class="btn s" onclick="go('termine')" data-i18n="st.termine">Termine</button><button class="btn s" onclick="go('bestellungen')" data-i18n="st.bestellungen">Bestellungen</button></div></div>
    </div>
    <div class="card" style="margin-top:16px"><div class="row" style="justify-content:space-between"><h3 style="font-size:19px" data-i18n="st.module_fuer_deinen_betrieb">Module für deinen Betrieb</h3><span class="mute small" data-i18n="st.jedes_modul_gibt_deinen">Jedes Modul gibt deinen Agenten echte Werkzeuge – im Chat, am Telefon und per WhatsApp.</span></div>
      <div class="grid g4" style="margin-top:12px">
        <button class="call" style="text-align:left;cursor:pointer" onclick="go('termine')"><b data-i18n="st.termine_2">📅 Termine</b><div class="mute small" data-i18n="st.praxis_salon_werkstatt_freie">Praxis, Salon, Werkstatt: freie Zeiten, Buchung, Erinnerung</div></button>
        <button class="call" style="text-align:left;cursor:pointer" onclick="go('bestellungen')"><b data-i18n="st.bestellungen_2">🍕 Bestellungen</b><div class="mute small" data-i18n="st.restaurant_lieferdienst_kart">Restaurant, Lieferdienst: Karte, Adresse, Küche, Fahrer</div></button>
        <?php if(isset($CH_PANELS['taxi'])): ?><button class="call" style="text-align:left;cursor:pointer" onclick="go('taxi')"><b data-i18n="st.taxi_zentrale">🚕 Taxi-Zentrale</b><div class="mute small" data-i18n="st.fahrten_richtpreise_taxistae">Fahrten, Richtpreise, Taxistände, Fahrer-App</div></button><?php endif; ?>
        <?php if(isset($CH_PANELS['hotel'])): ?><button class="call" style="text-align:left;cursor:pointer" onclick="go('hotel')"><b data-i18n="st.hotel_rezeption">🛎 Hotel-Rezeption</b><div class="mute small" data-i18n="st.zimmer_buchungen_gaestewuens">Zimmer, Buchungen, Gästewünsche, Rezeptions-Board</div></button><?php endif; ?>
        <?php if(isset($CH_PANELS['rueckruf'])): ?><button class="call" style="text-align:left;cursor:pointer" onclick="go('rueckruf')"><b data-i18n="st.rueckrufe_2">📞 Rückrufe</b><div class="mute small" data-i18n="st.rueckrufliste_ki_rueckruf_le">Rückrufliste, KI-Rückruf, Lead-Sofortrückruf in 60 s</div></button><?php endif; ?>
        <?php if(isset($CH_PANELS['website-builder'])): ?><button class="call" style="text-align:left;cursor:pointer" onclick="go('website-builder')"><b data-i18n="st.website_erstellen_2">✎ Website erstellen</b><div class="mute small" data-i18n="st.eigene_website_in_minuten">Eigene Website in Minuten: 8 Vorlagen, KI-Texte, 13 Sprachen, Agent eingebaut</div></button><?php endif; ?>
        <?php if(isset($CH_PANELS['training'])): ?><button class="call" style="text-align:left;cursor:pointer" onclick="go('training')"><b data-i18n="st.agent_trainieren">🎓 Agent trainieren</b><div class="mute small" data-i18n="st.website_dokumente_importiere">Website & Dokumente importieren, FAQ, Prüfung</div></button><?php endif; ?>
        <button class="call" style="text-align:left;cursor:pointer" onclick="go('phone')"><b data-i18n="st.telefon">☎ Telefon</b><div class="mute small" data-i18n="st.natuerliche_stimme_weiterlei">Natürliche Stimme, Weiterleitung, Transkripte</div></button>
      </div></div>
    <div class="grid g2" style="margin-top:16px">
      <div class="card"><h3 style="font-size:19px" data-i18n="st.schnellstart">Schnellstart</h3>
        <div class="perk" style="margin-top:12px"><i>1</i><div><b data-i18n="st.agent_waehlen">Agent wählen</b><span data-i18n="st.unter_agenten_jeder_agent">Unter „Agenten“ – jeder Agent kennt die Regeln seines Berufs.</span></div></div>
        <div class="perk"><i>2</i><div><b data-i18n="st.wissensbasis_einfuegen">Wissensbasis einfügen</b><span data-i18n="st.oeffnungszeiten_preise_ablae">Öffnungszeiten, Preise, Abläufe, FAQ, Textbausteine. Nur das nutzt der Agent.</span></div></div>
        <div class="perk"><i>3</i><div><b data-i18n="st.testen_dann_freischalten">Testen, dann freischalten</b><span data-i18n="st.im_studio_chatten_tokens">Im Studio chatten (Tokens). Widget &amp; Telefon mit gemietetem/gekauftem Agenten.</span></div></div>
        <div class="row" style="margin-top:10px"><button class="btn p" onclick="go('agents')" data-i18n="st.agenten_oeffnen">Agenten öffnen</button><a class="btn" href="/#berufe" target="_blank" data-i18n="st.agenten_katalog">Agenten-Katalog</a></div></div>
      <div class="card"><h3 style="font-size:19px" data-i18n="st.deine_agenten">Deine Agenten</h3><div id="hList" class="mute small" style="margin-top:10px">–</div></div>
    </div>
    <div class="card" style="margin-top:16px"><h3 style="font-size:19px" data-i18n="st.letzte_aktivitaet">Letzte Aktivität</h3><div style="overflow:auto;margin-top:10px"><table id="hRecent"><thead><tr><th data-i18n="st.zeit">Zeit</th><th data-i18n="st.aufgabe">Aufgabe</th><th data-i18n="st.bereich">Bereich</th><th data-i18n="st.tokens">Tokens</th></tr></thead><tbody></tbody></table></div></div>
  </div>

  <!-- Agenten -->
  <div id="pAgents" class="hidden">
    <div id="agList">
      <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px" data-i18n="st.agenten">Agenten</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.gemietete_gekaufte_agenten_s">Gemietete/gekaufte Agenten sind freigeschaltet (Widget, Telefon, Monats-Tokens). Alle anderen kannst du im Studio mit Tokens testen.</p></div><div class="chips" id="agFilter"></div></div>
      <div class="grid g3" id="agGrid"></div>
    </div>
    <div id="agWork" class="hidden">
      <div class="row" style="justify-content:space-between;margin-bottom:12px"><div class="row"><button class="btn s" onclick="showList()" data-i18n="st.alle_agenten">← Alle Agenten</button><h2 id="wName" style="font-size:24px" data-i18n="st.agent">Agent</h2><span class="tag" id="wStatus">–</span></div><div class="row"><a class="btn s gold" id="wRent" href="#" target="_blank" data-i18n="st.agent_mieten">Agent mieten</a></div></div>
      <div class="ws">
        <div>
          <div class="card">
            <h3 style="font-size:17px" data-i18n="st.einrichtung_2">Einrichtung</h3><p class="mute small" style="margin:4px 0 0" id="wDesc"></p>
            <label data-i18n="st.unternehmen_kanzlei_praxis">Unternehmen / Kanzlei / Praxis</label><input type="text" id="cCompany" placeholder="z. B. Kanzlei Berger &amp; Partner" data-i18n-attr="placeholder:st.kanzlei_berger_partner">
            <div class="grid g2" style="gap:10px"><div><label data-i18n="st.tonalitaet">Tonalität</label><select id="cTone"><option data-i18n="st.freundlich_professionell" value="freundlich-professionell">freundlich-professionell</option><option data-i18n="st.serioes_zugewandt" value="seriös-zugewandt">seriös-zugewandt</option><option data-i18n="st.sachlich_respektvoll" value="sachlich-respektvoll">sachlich-respektvoll</option><option data-i18n="st.locker_modern" value="locker-modern">locker-modern</option><option data-i18n="st.einfuehlsam_praezise" value="einfühlsam-präzise">einfühlsam-präzise</option><option data-i18n="st.kompetent_ehrlich" value="kompetent-ehrlich">kompetent-ehrlich</option></select></div><div><label data-i18n="st.standardsprache">Standardsprache</label><select id="cLang"><option value="de">Deutsch</option><option value="en">English</option><option value="fr">Français</option><option value="es">Español</option><option value="tr">Türkçe</option><option value="ru">Русский</option><option value="it">Italiano</option><option value="nl">Nederlands</option><option value="sv">Svenska</option><option value="no">Norsk</option><option value="fi">Suomi</option><option value="ja">日本語</option><option value="az">Azərbaycanca</option></select></div></div>
            <label data-i18n-html="st.kunden_duerfen_auch_sprechen">Kunden dürfen auch sprechen in <span class="mute">(der Agent antwortet automatisch in der Sprache des Kunden)</span></label><div class="chips" id="cLangs" role="group" aria-label="Erlaubte Sprachen" data-i18n-attr="aria-label:st.erlaubte_sprachen"><label class="chip" style="cursor:pointer"><input type="checkbox" value="de" style="margin-right:6px">Deutsch</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="en" style="margin-right:6px">English</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="fr" style="margin-right:6px">Français</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="es" style="margin-right:6px">Español</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="tr" style="margin-right:6px">Türkçe</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="ru" style="margin-right:6px">Русский</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="it" style="margin-right:6px">Italiano</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="nl" style="margin-right:6px">Nederlands</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="sv" style="margin-right:6px">Svenska</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="no" style="margin-right:6px">Norsk</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="fi" style="margin-right:6px">Suomi</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="ja" style="margin-right:6px">日本語</label><label class="chip" style="cursor:pointer"><input type="checkbox" value="az" style="margin-right:6px">Azərbaycanca</label></div>
            <label data-i18n-html="st.wissensbasis_einzige_faktenq">Wissensbasis <span class="mute">(einzige Faktenquelle des Agenten)</span></label><textarea id="cKb" placeholder="Öffnungszeiten, Adresse, Leistungen & Preise, Abläufe, Fristen, Textbausteine, Zuständigkeiten, Notfallnummern …" data-i18n-attr="placeholder:st.oeffnungszeiten_adresse_leis"></textarea>
            <div class="row" style="justify-content:space-between;margin-top:10px"><button class="btn s" id="cSample" data-i18n="st.beispiel_einfuegen">Beispiel einfügen</button><button class="btn p" id="cSave" data-i18n="st.speichern">Speichern</button></div>
            <div id="cMsg"></div>
            <div class="sec"><h3 data-i18n="st.website_widget">Website-Widget</h3><p class="mute small" style="margin:4px 0 0" data-i18n="st.eine_zeile_code_vor">Eine Zeile Code vor &lt;/body&gt;. Besucher chatten mit diesem Agenten; Antworten kosten 6 Tokens von deinem Konto.</p>
              <div class="sw"><input type="checkbox" id="cWidgetOn"><div><b data-i18n="st.widget_aktiv">Widget aktiv</b><div class="mute small" data-i18n="st.freischaltung_mit_aktivem_pl">Freischaltung mit aktivem Plan</div></div></div>
              <label data-i18n-html="st.erlaubte_domain_optional_mei">Erlaubte Domain(s) <span class="mute">(optional, z. B. meine-firma.de)</span></label><input type="text" id="cDomain" placeholder="meine-firma.de, shop.meine-firma.de" data-i18n-attr="placeholder:st.meine_firma_de_shop">
              <div class="snip" id="cSnip" data-i18n="st.speichern_um_den_code">Speichern, um den Code zu erhalten.</div></div>
            <div class="sec"><h3 data-i18n="st.telefon_2">Telefon</h3><p class="mute small" style="margin:4px 0 0" data-i18n-html="st.dieser_agent_kann_anrufe">Dieser Agent kann Anrufe annehmen. Begrüßung, Stimme, Weiterleitung und Nummer verwaltest du unter <a href="#" onclick="go('phone');return false">Telefon</a>.</p></div>
          </div>
        </div>
        <div class="card chat">
          <div class="row" style="justify-content:space-between"><div><b id="wChatTitle" data-i18n="st.test_chat">Test-Chat</b><div class="mute small" id="wCost" data-i18n="st.antwort_tokens_studio_test">Antwort ≈ 6 Tokens · Studio-Test</div></div><div class="row"><button class="btn s" id="wVoice" title="Als Telefon testen: sprechen & hören" data-i18n="st.telefon_test" data-i18n-attr="title:st.als_telefon_testen_sprechen">🎙️ Telefon-Test</button><button class="btn s" id="wClear" data-i18n="st.neu">Neu</button></div></div>
          <div class="chips" id="wChips"></div>
          <div class="ms" id="wMs"></div>
          <form id="wForm"><textarea id="wIn" placeholder="Nachricht an den Agenten … (Enter = senden, Shift+Enter = Zeile)" data-i18n-attr="placeholder:st.nachricht_an_den_agenten"></textarea><button class="mic hidden" type="button" id="wMic">🎙️</button><button class="btn p" type="submit" id="wSend" data-i18n="st.senden">Senden</button></form>
        </div>
      </div>
    </div>
  </div>

  <!-- WhatsApp (Kanal) -->
  <div id="pWhatsapp" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px">WhatsApp</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.kunden_schreiben_dir_per">Kunden schreiben dir per WhatsApp – dein Agent antwortet sofort, rund um die Uhr. Ein Verlauf pro Kunde, gemeinsam mit dem Telefon.</p></div><span class="pill" id="waState">–</span></div>
    <div id="waHost"></div>
    <div class="row" style="justify-content:flex-end;margin-top:12px"><button class="btn p" id="waSave" data-i18n="st.whatsapp_speichern">WhatsApp speichern</button></div><div id="waMsg"></div>
  </div>
  <!-- Website (Kanal) -->
  <div id="pWebsite" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px" data-i18n="st.website_2">Website</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.eine_zeile_code_auf">Eine Zeile Code auf deiner Website – Besucher chatten mit deinem Agenten. Wähle den Agenten, den du einbinden möchtest.</p></div></div>
    <div class="banner" style="margin-bottom:14px"><span>✎</span><div><b data-i18n="st.noch_keine_website">Noch keine Website?</b> <span data-i18n="st.im_baukasten_erstellst_du">Im Baukasten erstellst du in wenigen Minuten eine eigene Seite – der Agent ist schon eingebaut.</span> <a href="#website-builder" onclick="go('website-builder');return false" data-i18n="st.website_erstellen_oeffnen">Website erstellen öffnen →</a></div></div>
    <div class="grid g2" id="webList"></div>
  </div>

  <!-- Telefon -->
  <div id="pPhone" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px" data-i18n="st.telefon_agent">Telefon-Agent</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.anrufe_annehmen_fragen_beant">Anrufe annehmen, Fragen beantworten, Termine &amp; Rückrufe aufnehmen, weiterleiten. Jeder Anruf als Transkript.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-phone" target="_blank" data-i18n="st.telefon_agent_mieten_149">Telefon-Agent mieten · 149 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:1fr 1fr">
      <div class="card">
        <label data-i18n="st.welcher_agent_nimmt_ab">Welcher Agent nimmt ab?</label><select id="phPlan"></select>
        <div class="sw"><input type="checkbox" id="phOn"><div><b data-i18n="st.telefon_aktiv">Telefon aktiv</b><div class="mute small" id="phOnNote" data-i18n="st.freischaltung_mit_aktivem_pl">Freischaltung mit aktivem Plan</div></div></div>
        <label data-i18n="st.begruessung">Begrüßung</label><input type="text" id="phGreet" placeholder="Guten Tag, Kanzlei Berger &amp; Partner, hier ist der digitale Assistent. Wie kann ich Ihnen helfen?" data-i18n-attr="placeholder:st.guten_tag_kanzlei_berger">
        <div class="grid g2" style="gap:10px"><div><label data-i18n="st.stimme_sprache">Stimme / Sprache</label><select id="phVoice"><option value="de-DE" data-i18n="st.deutsch_vicki_weiblich_sehr">Deutsch · Vicki (weiblich, sehr natürlich)</option><option value="de-DE-m" data-i18n="st.deutsch_daniel_maennlich_seh">Deutsch · Daniel (männlich, sehr natürlich)</option><option value="de-DE-g" data-i18n="st.deutsch_aoede_google_hd">Deutsch · Aoede (Google HD, weiblich)</option><option value="de-DE-gm" data-i18n="st.deutsch_charon_google_hd">Deutsch · Charon (Google HD, männlich)</option><option value="tr-TR" data-i18n="st.tuerkce_aoede_google_hd">Türkçe · Aoede (Google HD, kadın)</option><option value="tr-TR-m" data-i18n="st.tuerkce_charon_google_hd">Türkçe · Charon (Google HD, erkek)</option><option value="en-US" data-i18n="st.english_us_joanna">English US · Joanna</option><option value="en-US-m" data-i18n="st.english_us_matthew">English US · Matthew</option><option value="en-GB" data-i18n="st.english_uk_amy">English UK · Amy</option><option value="en-GB-m" data-i18n="st.english_uk_charon_google">English UK · Charon (Google HD)</option><option value="fr-FR" data-i18n="st.francais_lea">Français · Léa</option><option value="fr-FR-m" data-i18n="st.francais_charon_google_hd">Français · Charon (Google HD)</option><option value="es-ES" data-i18n="st.espanol_lucia">Español · Lucía</option><option value="es-ES-m" data-i18n="st.espanol_sergio">Español · Sergio</option><option value="it-IT" data-i18n="st.italiano_bianca">Italiano · Bianca</option><option value="it-IT-m" data-i18n="st.italiano_charon_google_hd">Italiano · Charon (Google HD)</option><option value="nl-NL" data-i18n="st.nederlands_aoede_google_hd">Nederlands · Aoede (Google HD)</option><option value="nl-NL-m" data-i18n="st.nederlands_charon_google_hd">Nederlands · Charon (Google HD)</option><option value="pl-PL" data-i18n="st.polski_aoede_google_hd">Polski · Aoede (Google HD)</option><option value="pl-PL-m" data-i18n="st.polski_charon_google_hd">Polski · Charon (Google HD)</option><option value="ru-RU" data-i18n="st.aoede_google_hd">Русский · Aoede (Google HD)</option><option value="ru-RU-m" data-i18n="st.charon_google_hd">Русский · Charon (Google HD)</option></select></div><div><label data-i18n="st.weiterleitung_an_mensch">Weiterleitung an (Mensch)</label><input type="tel" id="phFwd" placeholder="+49 221 123456"></div></div>
        <label data-i18n="st.erreichbarkeit_des_teams_fue">Erreichbarkeit des Teams (für Rückruf-Zusagen)</label><input type="text" id="phHours" placeholder="Mo–Fr 9–17 Uhr" data-i18n-attr="placeholder:st.mo_fr_17_uhr">
        <div class="row" style="justify-content:space-between;margin-top:14px"><span class="mute small" data-i18n="st.transkripte_weiterleitung_la">Transkripte &amp; Weiterleitung landen hier im Studio.</span><button class="btn p" id="phSave" data-i18n="st.speichern">Speichern</button></div><div id="phMsg"></div>
        <div class="sec"><h3 data-i18n="st.deine_rufnummer">Deine Rufnummer</h3><div id="phNumber" class="mute" style="margin-top:6px">–</div>
          <p class="mute small" data-i18n="st.nach_buchung_richten_wir">Nach Buchung richten wir deine Nummer (DE/AT/CH/TR/EU) innerhalb von 24 h ein – oder du leitest deine bestehende Nummer bei Besetzt/außerhalb der Zeiten um. Technisch versiert? Eigene Twilio-Nummer: Voice-Webhook (POST) auf diese URL:</p><div class="snip" id="phHook" data-i18n="st.speichern_um_die_webhook">Speichern, um die Webhook-URL zu erhalten.</div>
          <details class="guide"><summary data-i18n="st.eigene_nummer_mit_twilio">Eigene Nummer mit Twilio verbinden – Schritt für Schritt</summary><ol>
            <li data-i18n-html="st.konto_auf_twilio_com">Konto auf <b>twilio.com</b> anlegen und eine Telefonnummer kaufen (Phone Numbers → Buy a number; für Deutschland ist ein Adressnachweis nötig, Freigabe meist 1–3 Tage).</li>
            <li data-i18n-html="st.nummer_oeffnen_voice_configu">Nummer öffnen → <b>Voice Configuration</b> → „A call comes in“: <b>Webhook</b>, Methode <b>HTTP POST</b>, URL = die Webhook-URL oben.</li>
            <li data-i18n="st.call_status_changes_status">„Call status changes“ (Status-Callback): dieselbe URL, ebenfalls POST – so landen Gesprächsende und Dauer im Studio.</li>
            <li data-i18n-html="st.hier_im_studio_agent">Hier im Studio: Agent wählen, Begrüßung, Stimme und Weiterleitungsnummer eintragen, <b>Telefon aktiv</b> anhaken, speichern.</li>
            <li data-i18n-html="st.bestehende_festnetznummer_be">Bestehende Festnetznummer behalten? Bei deinem Anbieter eine <b>Rufumleitung</b> auf die Twilio-Nummer einrichten (immer, bei Besetzt oder nach 20 Sekunden).</li>
            <li data-i18n="st.testanruf_machen_das_transkr">Testanruf machen – das Transkript erscheint rechts unter „Anrufe &amp; Transkripte“.</li></ol>
            <div class="tip" data-i18n="st.sicherheit_trag_unten_unter">Sicherheit: Trag unten unter „Verbindungen“ Account SID und Auth Token deines Twilio-Kontos ein – dann prüft ChatHelp jede Anfrage per Signatur, und SMS-Bestätigungen sowie KI-Rückrufe laufen über deine eigene Nummer. Lieber ohne Technik? support@chat-help.com richtet die Nummer für dich ein.</div></details></div>
        <div class="sec" id="cnSec"><h3 data-i18n="st.verbindungen_eigene_konten">Verbindungen (eigene Konten)</h3><p class="mute small" style="margin:4px 0 8px" data-i18n-html="st.fuer_telefon_sms_whatsapp">Für Telefon, SMS, WhatsApp und KI-Rückrufe über <b>deine</b> Nummern. Gespeicherte Schlüssel werden nie wieder angezeigt – nur die letzten Zeichen.</p>
          <div class="grid g2" style="gap:10px"><div><label>Twilio Account SID</label><input type="text" id="cnSid" placeholder="AC…" autocomplete="off" spellcheck="false"></div><div><label>Twilio Auth Token</label><input type="password" id="cnTok" placeholder="32 Zeichen" autocomplete="new-password" data-i18n-attr="placeholder:st.32_zeichen"></div></div>
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.absender_nummer_sms_rueckruf">Absender-Nummer (SMS &amp; Rückrufe)</label><input type="tel" id="cnFrom" placeholder="+49 221 …"></div><div><label data-i18n="st.meta_app_secret_whatsapp">Meta App-Secret (WhatsApp-Signatur)</label><input type="password" id="cnMeta" placeholder="32 Zeichen" autocomplete="new-password" data-i18n-attr="placeholder:st.32_zeichen"></div></div>
          <div class="row" style="justify-content:space-between;margin-top:10px"><span class="mute small" id="cnState">–</span><button class="btn s" id="cnSave" data-i18n="st.verbindungen_speichern">Verbindungen speichern</button></div><div id="cnMsg"></div>
          <div class="sec" id="aiSec"><h3 data-i18n="st.eigener_ki_schluessel_claude">Eigener KI-Schlüssel (Claude oder DeepSeek)</h3><p class="mute small" style="margin:4px 0 8px" data-i18n-html="st.mit_eigenem_schluessel_zahls">Mit eigenem Schlüssel zahlst du die KI direkt beim Anbieter – ChatHelp bucht dafür <b>keine Tokens</b> ab, es gilt nur die Plattformgebühr deines Pakets. Gespeichert wird verschlüsselt, angezeigt nur die letzten 4 Zeichen. Wird ein Schlüssel abgelehnt, bekommst du eine Benachrichtigung; der Betrieb läuft dann nur mit ChatHelp-Guthaben weiter.</p>
            <div id="aiAlert" aria-live="polite"></div>
            <div class="grid g2" style="gap:10px"><div><label for="aiClaude" data-i18n-t="st.claude_schluessel_claude_son">Claude-Schlüssel · Claude Sonnet 5.5 <span id="aiClaudeTag"></span></label><input type="password" id="aiClaude" placeholder="sk-ant-…" autocomplete="new-password" spellcheck="false"><div class="row" style="gap:6px;margin-top:6px"><button class="btn s" type="button" id="aiClaudeTest" data-i18n="st.testen">Testen</button><button class="btn s" type="button" id="aiClaudeDel" data-i18n="st.entfernen">Entfernen</button></div></div>
              <div><label for="aiDeep" data-i18n-t="st.deepseek_schluessel_deepseek">DeepSeek-Schlüssel · DeepSeek Flash <span id="aiDeepTag"></span></label><input type="password" id="aiDeep" placeholder="sk-…" autocomplete="new-password" spellcheck="false"><div class="row" style="gap:6px;margin-top:6px"><button class="btn s" type="button" id="aiDeepTest" data-i18n="st.testen">Testen</button><button class="btn s" type="button" id="aiDeepDel" data-i18n="st.entfernen">Entfernen</button></div></div></div>
            <div class="grid g2" style="gap:10px"><div><label for="aiPref" data-i18n="st.welcher_schluessel_zuerst">Welcher Schlüssel zuerst?</label><select id="aiPref"><option value="auto" data-i18n="st.automatisch_claude_zuerst_so">Automatisch (Claude zuerst, sonst DeepSeek)</option><option value="claude">Claude Sonnet 5.5</option><option value="deepseek" data-i18n="st.deepseek_flash_guenstig">DeepSeek Flash (günstig)</option></select></div><div class="mute small" id="aiNote" style="align-self:end"></div></div>
            <div class="row" style="justify-content:space-between;margin-top:10px"><a class="small" href="#tokens" onclick="go('tokens');return false" data-i18n="st.kosten_verbrauch_ansehen">Kosten &amp; Verbrauch ansehen →</a><button class="btn s" type="button" id="aiSave" data-i18n="st.ki_schluessel_speichern">KI-Schlüssel speichern</button></div><div id="aiMsg" aria-live="polite"></div></div></div>
        <div class="sec"><h3 data-i18n="st.im_browser_testen">Im Browser testen</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.sprich_mit_dem_agenten">Sprich mit dem Agenten wie am Telefon – Antwort wird vorgelesen (Chrome/Edge/Safari). Kostet 8 Tokens je Antwort.</p><button class="btn" id="phTest" data-i18n="st.telefon_test_starten">🎙️ Telefon-Test starten</button></div>
      </div>
      <div class="card"><h3 style="font-size:17px" data-i18n="st.anrufe_transkripte">Anrufe &amp; Transkripte</h3><p class="mute small" style="margin:4px 0 0" data-i18n="st.letzte_60_anrufe_dieses">Letzte 60 Anrufe dieses Agenten. Klick öffnet das Transkript.</p><div id="phCalls" style="margin-top:8px"><div class="mute small" data-i18n="st.noch_keine_anrufe">Noch keine Anrufe.</div></div></div>
    </div>
  </div>

  <!-- Termine -->
  <div id="pTermine" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px" data-i18n="st.terminbuchung">Terminbuchung</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.dein_kalender_fuer_praxis">Dein Kalender für Praxis, Salon, Studio oder Werkstatt. Der Agent bucht im Chat, am Telefon und über deine Buchungsseite – ohne Doppelbuchung, mit Bestätigung und Erinnerung.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-termine" target="_blank" data-i18n="st.termin_agent_mieten_99">Termin-Agent mieten · 99 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr))">
      <div>
        <div class="card">
          <div class="sw"><input type="checkbox" id="apOn"><div><b data-i18n="st.online_terminbuchung_aktiv">Online-Terminbuchung aktiv</b><div class="mute small" data-i18n="st.agenten_erhalten_die_werkzeu">Agenten erhalten die Werkzeuge „freie Zeiten“, „buchen“, „absagen“; Buchungsseite wird freigeschaltet</div></div></div>
          <label data-i18n="st.betrieb_praxis_salon">Betrieb / Praxis / Salon</label><input type="text" id="apCompany" placeholder="z. B. Salon Luna · Friseur & Kosmetik" data-i18n-attr="placeholder:st.salon_luna_friseur_kosmetik">
          <label data-i18n="st.adresse_fuer_bestaetigung_ka">Adresse (für Bestätigung &amp; Kalenderdatei)</label><input type="text" id="apAddress" placeholder="Straße, PLZ Ort" data-i18n-attr="placeholder:st.strasse_plz_ort">
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.benachrichtigung_an">Benachrichtigung an</label><input type="email" id="apNotify" placeholder="termine@deinbetrieb.de (leer = Konto-E-Mail)" data-i18n-attr="placeholder:st.termine_deinbetrieb_de_leer"></div><div><label data-i18n="st.zeitzone">Zeitzone</label><select id="apTz"><option>Europe/Berlin</option><option>Europe/Vienna</option><option>Europe/Zurich</option><option>Europe/Istanbul</option><option>Europe/Paris</option><option>Europe/London</option></select></div></div>
          <label data-i18n="st.zusatz_in_der_bestaetigung">Zusatz in der Bestätigung (optional)</label><input type="text" id="apConfirm" placeholder="z. B. Bitte 5 Minuten früher da sein. Absage bis 24 h vorher kostenlos." data-i18n-attr="placeholder:st.bitte_minuten_frueher_da">
          <div class="grid g4" style="gap:10px"><div><label data-i18n="st.taktung_min">Taktung (Min)</label><input type="text" id="apSlot" value="30"></div><div><label data-i18n="st.puffer_min">Puffer (Min)</label><input type="text" id="apBuffer" value="0"></div><div><label data-i18n="st.vorlauf_min">Vorlauf (Min)</label><input type="text" id="apLead" value="120"></div><div><label data-i18n="st.horizont_tage">Horizont (Tage)</label><input type="text" id="apMax" value="60"></div></div>
          <div class="sec"><h3 data-i18n="st.oeffnungszeiten">Öffnungszeiten</h3><div id="apHours"></div></div>
          <div class="sec"><h3 data-i18n="st.leistungen">Leistungen</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.name_dauer_in_minuten">Name, Dauer in Minuten, Preis in Euro. Dauer bestimmt die Slot-Länge.</p><div id="apSvcs"></div><button class="btn s" id="apAddSvc" style="margin-top:8px" data-i18n="st.leistung">+ Leistung</button></div>
          <div class="row" style="justify-content:space-between;margin-top:16px"><span class="mute small" data-i18n="st.aenderungen_wirken_sofort_fu">Änderungen wirken sofort für Agent &amp; Buchungsseite.</span><button class="btn p" id="apSave" data-i18n="st.speichern">Speichern</button></div><div id="apMsg"></div>
          <div class="sec"><h3 data-i18n="st.deine_buchungsseite">Deine Buchungsseite</h3><div class="snip" id="apUrl">–</div><div class="row" style="margin-top:8px"><a class="btn s" id="apOpen" href="#" target="_blank" data-i18n="st.seite_oeffnen">Seite öffnen</a><button class="btn s" id="apCopy" data-i18n="st.link_kopieren">Link kopieren</button><span class="mute small" data-i18n="st.verlinke_sie_auf_deiner">Verlinke sie auf deiner Website, in Google Business, Instagram oder im E-Mail-Footer.</span></div></div>
        </div>
      </div>
      <div>
        <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px" data-i18n="st.termine_naechste_30_tage">Termine (nächste 30 Tage)</h3><button class="btn s" id="apReload">↻</button></div><div id="apList" class="mute small" style="margin-top:8px">–</div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.termin_manuell_eintragen">Termin manuell eintragen</h3>
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.leistung_2">Leistung</label><select id="mbSvc"></select></div><div><label data-i18n="st.datum">Datum</label><input type="date" id="mbDate"></div></div>
          <label data-i18n="st.uhrzeit">Uhrzeit</label><select id="mbTime"><option value="" data-i18n="st.datum_waehlen">— Datum wählen —</option></select>
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.name">Name</label><input type="text" id="mbName"></div><div><label data-i18n="st.telefon_2">Telefon</label><input type="tel" id="mbPhone"></div></div>
          <label data-i18n="st.mail_bestaetigung">E-Mail (Bestätigung)</label><input type="email" id="mbEmail"><label data-i18n="st.notiz">Notiz</label><input type="text" id="mbNote">
          <div class="row" style="justify-content:flex-end;margin-top:12px"><button class="btn p" id="mbBook" data-i18n="st.eintragen">Eintragen</button></div><div id="mbMsg"></div></div>
      </div>
    </div>
  </div>

  <!-- Bestellungen (Restaurant / Pizzeria / Lieferdienst) -->
  <div id="pBestellungen" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px" data-i18n="st.bestellannahme">Bestellannahme</h2><p class="mute" style="margin:4px 0 0" data-i18n="st.dein_bestell_agent_nimmt">Dein Bestell-Agent nimmt per WhatsApp, Website-Chat und Telefon Bestellungen auf: Speisekarte mit Größen &amp; Extras, Adressprüfung mit Liefergebiet, Bezahlart, Zeit. Die Küche sieht alles live auf dem Board, der Fahrer bekommt Route und Kundendaten aufs Handy, der Kunde die Bestätigung.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-bestellung" target="_blank" data-i18n="st.bestell_agent_mieten_129">Bestell-Agent mieten · 129 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr))">
      <div>
        <div class="card">
          <div class="sw"><input type="checkbox" id="odOn"><div><b data-i18n="st.bestellannahme_aktiv">Bestellannahme aktiv</b><div class="mute small" data-i18n="st.agenten_erhalten_die_werkzeu_2">Agenten erhalten die Werkzeuge „Speisekarte“, „Adresse prüfen“, „Bestellung anlegen“, „Status“, „Stornieren“</div></div></div>
          <label data-i18n="st.restaurant">Restaurant</label><input type="text" id="odName" placeholder="z. B. Pizzeria Napoli" data-i18n-attr="placeholder:st.pizzeria_napoli">
          <label data-i18n="st.adresse_des_restaurants_fuer">Adresse des Restaurants (für Entfernung &amp; Route)</label><input type="text" id="odAddress" placeholder="Straße Nr., PLZ Ort" data-i18n-attr="placeholder:st.strasse_nr_plz_ort">
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.telefon_fuer_kunden">Telefon (für Kunden)</label><input type="tel" id="odPhone" placeholder="0221 …"></div><div><label data-i18n="st.bestell_mails_an">Bestell-Mails an</label><input type="email" id="odNotify" placeholder="kueche@… (leer = Konto-E-Mail)" data-i18n-attr="placeholder:st.kueche_leer_konto_mail"></div></div>
          <div class="grid g2" style="gap:10px"><div class="sw" style="margin-top:12px"><input type="checkbox" id="odDelivery"><div><b data-i18n="st.lieferung">Lieferung</b></div></div><div class="sw" style="margin-top:12px"><input type="checkbox" id="odPickup"><div><b data-i18n="st.abholung">Abholung</b></div></div></div>
          <div class="grid g4" style="gap:10px"><div><label data-i18n="st.mindestbestellwert">Mindestbestellwert €</label><input type="text" id="odMin" value="15,00"></div><div><label data-i18n="st.liefergebuehr_standard">Liefergebühr € (Standard)</label><input type="text" id="odFee" value="2,50"></div><div><label data-i18n="st.lieferzeit_min">Lieferzeit Min</label><input type="text" id="odEtaD" value="40"></div><div><label data-i18n="st.abholzeit_min">Abholzeit Min</label><input type="text" id="odEtaP" value="20"></div></div>
          <div class="grid g4" style="gap:10px"><div><label data-i18n="st.abholrabatt">Abholrabatt %</label><input type="text" id="odDisc" value="0" title="Wird bei Abholung automatisch vom Warenwert abgezogen" data-i18n-attr="title:st.wird_bei_abholung_automatisc"></div></div>
          <label data-i18n="st.zahlarten">Zahlarten</label><div class="row"><label class="chip" style="margin:0" data-i18n-t="st.bar"><input type="checkbox" class="odPay" value="bar" checked> bar</label><label class="chip" style="margin:0" data-i18n-t="st.karte_beim_fahrer"><input type="checkbox" class="odPay" value="karte" checked> Karte beim Fahrer</label><label class="chip" style="margin:0" data-i18n-t="st.paypal_link"><input type="checkbox" class="odPay" value="paypal"> PayPal-Link</label><label class="chip" style="margin:0" data-i18n-t="st.online_bezahlt"><input type="checkbox" class="odPay" value="online"> Online bezahlt</label></div>
          <div class="grid g2" style="gap:10px"><div><label data-i18n="st.zeitzone">Zeitzone</label><select id="odTz"><option>Europe/Berlin</option><option>Europe/Vienna</option><option>Europe/Zurich</option><option>Europe/Istanbul</option><option>Europe/Paris</option><option>Europe/London</option></select></div></div>
          <div class="sec"><h3 data-i18n="st.oeffnungszeiten_bestellannah">Öffnungszeiten (Bestellannahme)</h3><div id="odHours"></div></div>
          <div class="sec"><h3 data-i18n="st.liefergebiete">Liefergebiete</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.postleitzahlen_je_zone_komma">Postleitzahlen je Zone (Komma-getrennt), Liefergebühr und Mindestbestellwert. Adressen außerhalb → der Agent bietet Abholung an.</p><div id="odZones"></div><button class="btn s" id="odAddZone" style="margin-top:8px" data-i18n="st.zone">+ Zone</button></div>
          <div class="row" style="justify-content:space-between;margin-top:16px"><span class="mute small" data-i18n="st.speisekarte_fahrer_und_whats">Speisekarte, Fahrer und WhatsApp werden mitgespeichert.</span><button class="btn p" id="odSave" data-i18n="st.speichern">Speichern</button></div><div id="odMsg"></div>
        </div>
        <div class="card" style="margin-top:16px"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px" data-i18n="st.speisekarte">Speisekarte</h3><button class="btn s" id="odDemoMenu" data-i18n="st.beispielkarte_laden">Beispielkarte laden</button></div>
          <p class="mute small" style="margin:4px 0 8px" data-i18n-html="st.kategorie_name_beschreibung_">Kategorie · Name · Beschreibung · Preis € · Optionen · Hinweise. Optionen-Format: <code>Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50</code></p>
          <div id="odMenu"></div><button class="btn s" id="odAddItem" style="margin-top:8px" data-i18n="st.artikel">+ Artikel</button></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.fahrer">Fahrer</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.jeder_fahrer_bekommt_einen">Jeder Fahrer bekommt einen persönlichen Link (Handy-Seite mit Navigation, Anruf, „Zugestellt“). Bei „Unterwegs“ erhält er die Fahrt per WhatsApp/SMS.</p><div id="odDrivers"></div><button class="btn s" id="odAddDriver" style="margin-top:8px" data-i18n="st.fahrer_2">+ Fahrer</button></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.whatsapp_anbindung">WhatsApp-Anbindung</h3>
          <label data-i18n="st.anbieter">Anbieter</label><select id="odWaProv"><option value="meta" data-i18n="st.meta_whatsapp_business_cloud">Meta WhatsApp Business (Cloud API)</option><option value="twilio" data-i18n="st.twilio_whatsapp">Twilio WhatsApp</option></select>
          <div id="odWaMeta"><div class="grid g2" style="gap:10px"><div><label>Phone Number ID</label><input type="text" id="odWaPhoneId" placeholder="1234567890"></div><div><label>Access Token</label><input type="text" id="odWaToken" placeholder="EAAG… (wird verschlüsselt gespeichert)" data-i18n-attr="placeholder:st.eaag_verschluesselt"></div></div><label data-i18n="st.verify_token_frei_waehlbar">Verify-Token (frei wählbar)</label><input type="text" id="odWaVerify" placeholder="z. B. napoli-2026" data-i18n-attr="placeholder:st.napoli_2026"></div>
          <div id="odWaTwilio" class="hidden"><label data-i18n="st.whatsapp_nummer_twilio_sende">WhatsApp-Nummer (Twilio Sender)</label><input type="text" id="odWaNumber" placeholder="+4915…"><p class="mute small" data-i18n="st.account_sid_auth_token">Account SID / Auth Token hinterlegt ChatHelp serverseitig (support@chat-help.com).</p></div>
          <label data-i18n="st.webhook_url_bei_meta">Webhook-URL (bei Meta als Callback bzw. bei Twilio als „When a message comes in“)</label><div class="snip" id="odWaHook" data-i18n="st.speichern_um_die_url">Speichern, um die URL zu erhalten.</div><div class="row" style="margin-top:8px"><button class="btn s" id="odWaCopy" data-i18n="st.url_kopieren">URL kopieren</button></div>
          <details class="guide"><summary data-i18n="st.whatsapp_business_meta_verbi">WhatsApp Business (Meta) verbinden – Schritt für Schritt</summary><ol>
            <li data-i18n-html="st.auf_business_facebook_com">Auf <b>business.facebook.com</b> ein Business-Konto anlegen bzw. öffnen und das Unternehmen verifizieren.</li>
            <li data-i18n-html="st.auf_developers_facebook_com">Auf <b>developers.facebook.com</b> → Meine Apps → App erstellen (Typ „Business“) → Produkt <b>WhatsApp</b> hinzufügen.</li>
            <li data-i18n-html="st.whatsapp_api_setup_eigene">WhatsApp → <b>API-Setup</b>: eigene Telefonnummer hinzufügen und per SMS bestätigen. Die <b>Phone Number ID</b> hier oben eintragen.</li>
            <li data-i18n-html="st.business_einstellungen_syste">Business-Einstellungen → Systembenutzer anlegen → Token mit den Rechten <code>whatsapp_business_messaging</code> und <code>whatsapp_business_management</code> erzeugen (läuft nicht ab) → als <b>Access Token</b> eintragen.</li>
            <li data-i18n-html="st.einen_verify_token_frei">Einen <b>Verify-Token</b> frei wählen, hier eintragen und <b>Speichern</b>.</li>
            <li data-i18n-html="st.whatsapp_konfiguration_webho">WhatsApp → <b>Konfiguration → Webhook</b>: Callback-URL = Webhook-URL oben, Verify-Token = dein Verify-Token → „Bestätigen und speichern“ → Feld <code>messages</code> abonnieren.</li>
            <li data-i18n="st.testnachricht_an_deine_whats">Testnachricht an deine WhatsApp-Nummer schicken: Dein Agent antwortet sofort. Gespräche, Bestellungen, Termine und Rückrufwünsche erscheinen im Studio im jeweiligen Bereich.</li></ol>
            <div class="tip" data-i18n="st.twilio_statt_meta_anbieter">Twilio statt Meta: Anbieter „Twilio“ wählen, WhatsApp-Absendernummer eintragen und in Twilio unter „When a message comes in“ die Webhook-URL (POST) setzen. Einrichtung durch uns: support@chat-help.com.</div></details>
        </div>
      </div>
      <div>
        <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px" data-i18n="st.live_bestellungen">Live-Bestellungen</h3><div class="row"><label class="small mute" style="margin:0" data-i18n-t="st.auch_erledigte_tage"><input type="checkbox" id="odAll"> auch erledigte (7 Tage)</label><button class="btn s" id="odReload">↻</button></div></div><div id="odList" class="mute small" style="margin-top:8px">–</div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.kuechen_board_tablet">Küchen-Board (Tablet)</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.grossansicht_ohne_login_fuer">Großansicht ohne Login für Küche und Theke: neue Bestellungen mit Ton, Status per Tipp, Fahrer zuweisen.</p><div class="snip" id="odBoard" data-i18n="st.speichern_um_den_link">Speichern, um den Link zu erhalten.</div><div class="row" style="margin-top:8px"><a class="btn s" id="odBoardOpen" href="#" target="_blank" data-i18n="st.board_oeffnen">Board öffnen</a><button class="btn s" id="odBoardCopy" data-i18n="st.link_kopieren">Link kopieren</button></div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.adresse_testen">Adresse testen</h3><p class="mute small" style="margin:4px 0 8px" data-i18n="st.so_prueft_der_agent">So prüft der Agent Lieferadressen: Zone, Gebühr, Entfernung.</p><div class="row"><input type="text" id="odChk" placeholder="Severinstraße 12, 50678 Köln" style="flex:1" data-i18n-attr="placeholder:st.severinstrasse_12_50678_koel"><button class="btn s" id="odChkGo" data-i18n="st.pruefen">Prüfen</button></div><div id="odChkRes" class="small" style="margin-top:8px"></div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px" data-i18n="st.so_laeuft_eine_bestellung">So läuft eine Bestellung</h3><ol class="mute small" style="margin:8px 0 0 18px;padding:0;line-height:1.7"><li data-i18n="st.kunde_schreibt_per_whatsapp">Kunde schreibt per WhatsApp / Chat oder ruft an → Agent fragt Größe, Extras, Lieferung/Abholung</li><li data-i18n="st.adresse_plz_zone_entfernung">Adresse → PLZ-Zone + Entfernung → Gebühr &amp; Mindestbestellwert</li><li data-i18n="st.zusammenfassung_kunde_bestae">Zusammenfassung → Kunde bestätigt → Bestellcode + Zeit</li><li data-i18n="st.kueche_board_mail_status">Küche: Board/E-Mail · Status „Unterwegs“ → Fahrer bekommt Route · Kunde bekommt WhatsApp-Status</li></ol></div>
      </div>
    </div>
  </div>

  <!-- Tokens & API -->
  <div id="pTokens" class="hidden">
    <div class="grid g3">
      <div class="card"><span class="tag" data-i18n="st.guthaben">Guthaben</span><div class="num" style="margin-top:10px" id="tBal">–</div><div class="mute small" data-i18n="st.chat_telefon_widget_tokens">Chat 6 · Telefon 8 · Widget 6 Tokens je Antwort</div><div class="row" style="margin-top:12px"><a class="btn s gold" href="/chat/tokens-checkout.php?pack=10k" target="_blank">10.000 · 49 €</a><a class="btn s" href="/chat/tokens-checkout.php?pack=50k" target="_blank">50.000 · 199 €</a><a class="btn s" href="/#preise" target="_blank" data-i18n="st.mehr">mehr</a></div></div>
      <div class="card"><span class="tag indigo">API-Key</span><div class="snip" id="tKey">–</div><div class="mute small" style="margin-top:8px" data-i18n="st.bearer_key_fuer_die">Bearer-Key für die REST-API. Bei Verdacht auf Missbrauch: abmelden und neu anmelden erzeugt einen neuen Key (alte bleiben gültig – Deaktivierung auf Anfrage).</div></div>
      <div class="card"><span class="tag teal" data-i18n="st.endpunkte">Endpunkte</span><div class="snip" style="margin-top:10px">POST /chat/tokens-api.php/v1/agent
{"plan":"agent-kanzlei","messages":[{"role":"user","content":"…"}]}

GET  /v1/me · GET /v1/sectors · GET /v1/categories · GET /v1/pricing
GET  /v1/billing · GET /v1/usage?month=YYYY-MM · POST /v1/generate
POST /v1/translate · POST /v1/subtitles
GET|POST /v1/agent/config?plan=… · GET /v1/phone/calls
GET|POST /v1/appt/settings · GET /v1/appt/bookings
GET|POST /v1/order/settings · GET /v1/order/orders</div></div>
    </div>
    <div class="grid g2" style="margin-top:16px">
      <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:19px" data-i18n="st.lizenzen_betrieb">Lizenzen &amp; Betrieb</h3><span class="tag" id="bilTag">–</span></div>
        <p class="mute small" style="margin:6px 0 0" data-i18n-html="st.einmalkauf_einrichtung_lizen">Einmalkauf = Einrichtung + Lizenz + Startguthaben + <span id="bilDays">90</span> Tage Betrieb. Danach wählst du je Agent: <b>Betrieb</b> (günstiges Monatsabo inkl. Tokens) oder deinen <b>eigenen KI-Schlüssel</b> (kleine Plattformgebühr, keine Tokens). Monatlich kündbar; im Inklusiv-Zeitraum gebucht, beginnt die Abrechnung erst danach.</p>
        <div id="bilList" class="small" style="margin-top:10px">–</div></div>
      <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:19px" data-i18n="st.kosten_verbrauch">Kosten &amp; Verbrauch</h3><input type="month" id="useMonth" aria-label="Monat" style="width:auto;padding:7px 10px;border-radius:10px;border:1px solid var(--line2)" data-i18n-attr="aria-label:st.monat"></div>
        <div style="overflow:auto;margin-top:10px"><table id="useTbl"><thead><tr><th data-i18n="st.kanal">Kanal</th><th data-i18n="st.ki_aufrufe">KI-Aufrufe</th><th data-i18n="st.modell_tokens">Modell-Tokens</th><th data-i18n="st.ki_kosten">≈ KI-Kosten</th><th data-i18n="st.chathelp_tokens">ChatHelp-Tokens</th></tr></thead><tbody><tr><td colspan="5" class="mute">–</td></tr></tbody></table></div>
        <p class="mute small" id="useNote" style="margin:8px 0 0"></p></div>
    </div>
    <div class="card" style="margin-top:16px"><h3 style="font-size:19px" data-i18n="st.verbrauch">Verbrauch</h3><div style="overflow:auto;margin-top:10px"><table id="tRecent"><thead><tr><th data-i18n="st.zeit">Zeit</th><th data-i18n="st.aufgabe">Aufgabe</th><th data-i18n="st.bereich">Bereich</th><th data-i18n="st.tokens">Tokens</th></tr></thead><tbody></tbody></table></div></div>
  </div>
</div>

<!-- ===== Profil-Assistent (nach der ersten Anmeldung · „Profil bearbeiten“) ===== -->
<div id="pfWiz" class="pfw hidden" role="dialog" aria-modal="true" aria-labelledby="pfTitle">
  <div class="pfw-card">
    <div class="pfw-hd">
      <span class="logo" aria-hidden="true"><span class="em"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span></span>
      <div class="pfw-prog"><span class="pfw-step" id="pfStepTxt" data-i18n="st.schritt_von">Schritt 1 von 4</span><div class="pfw-dots" id="pfDots" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>
      <button class="pfw-x" id="pfLater" type="button" data-i18n="st.spaeter">Später</button>
    </div>
    <div class="sr" id="pfLive" aria-live="polite"></div>
    <div class="pfw-body" id="pfBody"></div>
    <div class="pfw-ft"><button class="btn" id="pfBack" type="button" data-i18n="st.zurueck">← Zurück</button><span class="sp"></span><button class="btn ghost" id="pfSkip" type="button" data-i18n="st.ueberspringen">Überspringen</button><button class="btn" id="pfNext" type="button" data-i18n="st.weiter">Weiter</button></div>
  </div>
</div>

<script>
I18N.apply(document);(function(){const w=document.getElementById('stLsw');if(w){w.innerHTML=I18N.switcher();w.setAttribute('aria-label',_t('i18n.lang'));I18N.bindSwitcher(w);}})();
const API='/chat/tokens-api.php/v1'; const $=s=>document.querySelector(s);
let KEY=localStorage.getItem('ch_api_key')||'', ME=null, PLANS=[], CUR=null, HIST=[], CFG=null;
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));const de=n=>I18N.num(n);
async function api(path,opts={}){const u=API+path+(path.includes('?')?'&':'?')+'_='+Date.now();const r=await fetch(u,{...opts,cache:'no-store',headers:{'Content-Type':'application/json',...(KEY?{'Authorization':'Bearer '+KEY}:{}),...(opts.headers||{})}});const j=locPl(await r.json().catch(()=>({})));if(!r.ok){const e=new Error(_te({data:j},j.error||('HTTP '+r.status)));e.data=j;e.status=r.status;throw e;}return j;}
/* Katalog: Agenten-Namen/-Beschreibungen nach Plan-ID übersetzen (pl.<id>.n/.d) */
function locPl(j){if(I18N.lang==='de'||!j||typeof j!=='object')return j;const fix=a=>{if(Array.isArray(a))a.forEach(p=>{if(!p||typeof p!=='object'||typeof p.id!=='string')return;const k='pl.'+p.id;if(p.name&&I18N.has(k+'.n'))p.name=_t(k+'.n');if(I18N.has(k+'.d'))['desc','description'].forEach(f=>{if(p[f])p[f]=_t(k+'.d');});});};fix(j.plans);if(j.rec)fix(j.rec.plans);if(j.recommend)fix(j.recommend.plans);return j;}
function msg(el,text,kind){el.innerHTML=text?`<div class="msg ${kind||'info'}">${text}</div>`:'';}
const ICON={inkasso:'€',kanzlei:'§',steuer:'%',versicherung:'☂',hausverwaltung:'⌂',automotive:'⚙',praxis:'+',service:'☎',ecommerce:'◎',marketing:'✎',finanzen:'Σ',immobilien:'⌂',gastro:'✦',hr:'☺',logistik:'⇄',bildung:'✎',creator:'▶',medien:'▶',recht:'§',amazon:'▣'};
const SAMPLE={default:_t('sj.sample.default'),
 'agent-marketing':_t('sj.sample.marketing'),
 'agent-amazon':_t('sj.sample.amazon'),
 'agent-kanzlei':"Kanzlei: Muster & Partner Rechtsanwälte, Musterstraße 1, 10115 Berlin, Tel. 030 123456, kanzlei@muster.de. Mo–Do 8:30–18, Fr 8:30–15.\nRechtsgebiete: Arbeitsrecht (RA Muster), Mietrecht (RAin Beispiel), Verkehrsrecht. Nicht: Steuerrecht, Patentrecht.\nErstberatung: 30 Min 150 € netto / 60 Min 190 € netto (§ 34 RVG), online oder vor Ort. Stundensatz 250 € netto oder RVG. Rechtsschutz: Deckungsanfrage kostenlos.\nTermine: Erstberatung binnen 3 Werktagen; Fristsachen am selben/nächsten Werktag. Unterlagen: Ausweis, Vertrag/Bescheid/Urteil, Korrespondenz, RS-Police.\nFristen (Vorprüfung, Anwalt bestätigt): Kündigungsschutzklage 3 Wochen (§ 4 KSchG); Berufung 1 Monat (§ 517 ZPO); Widerspruch 1 Monat (§ 70 VwGO); Bußgeld-Einspruch 2 Wochen (§ 67 OWiG).\nKonfliktcheck: Gegenseite immer erfragen.",
 'agent-inkasso':"Unternehmen: Muster Inkasso GmbH, registrierter Inkassodienstleister (§ 10 RDG), Reg.-Nr. …, Registrierungsbehörde ….\nBank: IBAN …, Verwendungszweck: Aktenzeichen.\nSachbearbeitung: Mo–Fr 8–17 Uhr, Tel. …, service@….\nRatenzahlung: ab 50 €/Monat, max. 24 Raten, Verfallklausel bei 2 Raten Rückstand. Vergleich: Erlass bis 30 % der Kosten bei Einmalzahlung binnen 14 Tagen (Sachbearbeiter entscheidet).\nKosten B2B: Verzugszinsen 9 Pp. über Basiszins (aktuell …%), Pauschale 40 € (§ 288 Abs. 5 BGB). Verbraucher: 5 Pp. über Basiszins.\nInkassokosten: 0,9 Gebühr Nr. 2300 VV RVG nach Streitwert: bis 500 € = 49 €, bis 1.000 € = 88 €, bis 2.000 € = 166 €, bis 3.000 € = 222 €, bis 5.000 € = 334 €; Auslagenpauschale 20 €.",
 'agent-steuer':"Kanzlei: Steuerkanzlei Muster, Musterstraße 1, 80335 München, Tel. 089 123456, mandanten@muster.de, Mo–Fr 8–17 Uhr.\nUpload: DATEV Unternehmen online oder Upload-Link per E-Mail. Keine Belege per WhatsApp.\nFristen: USt-VA monatlich bis 10. (mit Dauerfristverlängerung bis 10. des übernächsten Monats); Lohnsteuer bis 10.; ESt-Vorauszahlungen 10.03./10.06./10.09./10.12.; GewSt-Vorauszahlungen 15.02./15.05./15.08./15.11.; Jahresabschluss/Offenlegung ….\nHonorar: nach StBVV; Buchhaltung monatlich pauschal lt. Vertrag.\nAnsprechpartner: ….",
 'agent-phone':_t('sj.sample.phone'),
 'agent-bestellung':"Restaurant: Pizzeria Muster, Musterstraße 1, 50667 Köln, Tel. 0221 123456. Seit 1998, Holzofen.\nÖffnungszeiten & Liefergebiete: siehe Tab „Bestellungen“ (Speisekarte, Zonen, Zahlarten werden dort gepflegt – der Agent nutzt sie automatisch).\nAllergene: Weizen in allen Pizzen (glutenfrei +2 € auf Vorbestellung), Laktose in Käse; vollständige Liste liegt im Restaurant aus.\nAktionen: Dienstag Pizza-Tag −2 € (nur Abholung). Abholung −10 %.\nReklamation: innerhalb 30 Min nach Lieferung anrufen – Ersatz oder Gutschrift nach Rücksprache.\nSonstiges: Trinkgeld freiwillig; Bestellungen über 60 € bitte 30 Min Vorlauf.",
 'agent-termine':"Betrieb: Salon Muster, Musterstraße 1, 20357 Hamburg, Tel. 040 123456.\nTeam: … (Schnitt & Farbe), … (Kosmetik).\nLeistungen & Öffnungszeiten: siehe Tab „Termine“ (werden dort gepflegt – der Agent nutzt sie automatisch).\nRegeln: Absage bis 24 h vorher kostenlos, danach 50 %. Verspätung über 15 Min → Termin kann verkürzt werden.\nParken/Anfahrt: …\nProdukte: …"};
const CHIPS={default:[_t('sj.chip.def1'),_t('sj.chip.def2'),_t('sj.chip.def3')],
 'agent-kanzlei':[_t('sj.chip.kz1'),_t('sj.chip.kz2'),_t('sj.chip.kz3')],
 'agent-inkasso':[_t('sj.chip.ik1'),_t('sj.chip.ik2'),_t('sj.chip.ik3')],
 'agent-steuer':[_t('sj.chip.st1'),_t('sj.chip.st2'),_t('sj.chip.st3')],
 'agent-versicherung':[_t('sj.chip.vs1'),_t('sj.chip.vs2'),_t('sj.chip.vs3')],
 'agent-hausverwaltung':[_t('sj.chip.hv1'),_t('sj.chip.hv2'),_t('sj.chip.hv3')],
 'agent-autohaus':[_t('sj.chip.ah1'),_t('sj.chip.ah2'),_t('sj.chip.ah3')],
 'agent-phone':[_t('sj.chip.ph1'),_t('sj.chip.ph2'),_t('sj.chip.ph3')],
 'agent-support':[_t('sj.chip.su1'),_t('sj.chip.su2'),_t('sj.chip.su3')],
 'agent-sales':[_t('sj.chip.sa1'),_t('sj.chip.sa2'),_t('sj.chip.sa3')],
 'agent-backoffice':[_t('sj.chip.bo1'),_t('sj.chip.bo2'),_t('sj.chip.bo3')],
 'agent-website':[_t('sj.chip.ws1'),_t('sj.chip.ws2'),_t('sj.chip.ws3')],
 'agent-termine':[_t('sj.chip.tm1'),_t('sj.chip.tm2'),_t('sj.chip.tm3')],
 'agent-bestellung':[_t('sj.chip.be1'),_t('sj.chip.be2'),_t('sj.chip.be3')],
 'agent-content':[_t('sj.chip.ct1'),_t('sj.chip.ct2'),_t('sj.chip.ct3')],
 'agent-marketing':[_t('sj.chip.mk1'),_t('sj.chip.mk2'),_t('sj.chip.mk3')],
 'agent-amazon':[_t('sj.chip.az1'),_t('sj.chip.az2'),_t('sj.chip.az3')]};

/* ===== Login ===== */
(function(){const q=new URLSearchParams(location.search);if(q.get('welcome')){$('#welcome').classList.remove('hidden');}})();
let lEmail='';
$('#lSend').onclick=async()=>{lEmail=$('#lEmail').value.trim();if(!/.+@.+\..+/.test(lEmail)){msg($('#lMsg'),_t('sj.login.bad'),'err');return;}$('#lSend').disabled=true;msg($('#lMsg'),_t('sj.login.sending'));
  try{const j=await api('/auth/request',{method:'POST',body:JSON.stringify({email:lEmail,lang:I18N.lang})});$('#stepEmail').classList.add('hidden');$('#stepCode').classList.remove('hidden');$('#lTo').textContent='→ '+lEmail;msg($('#lMsg'),j.sent?_t('sj.login.sent'):_t('sj.login.failsend'),j.sent?'ok':'err');$('#lCode').focus();}
  catch(e){msg($('#lMsg'),e.message,'err');}$('#lSend').disabled=false;};
$('#lEmail').addEventListener('keydown',e=>{if(e.key==='Enter')$('#lSend').click();});
const codeOf=t=>{const m=String(t||'').match(/(?:\d[\s\u00a0.-]?){6}/);return m?m[0].replace(/\D/g,'').slice(0,6):String(t||'').replace(/\D/g,'').slice(0,6);};
$('#lCode').addEventListener('input',()=>{const v=$('#lCode').value.replace(/\D/g,'').slice(0,6);if($('#lCode').value!==v)$('#lCode').value=v;if(v.length===6)$('#lVerify').click();});
$('#lCode').addEventListener('paste',e=>{const t=(e.clipboardData||window.clipboardData)?.getData('text');if(t==null)return;e.preventDefault();$('#lCode').value=codeOf(t);$('#lCode').dispatchEvent(new Event('input'));});
$('#lPaste').onclick=async()=>{try{const t=await navigator.clipboard.readText();const c=codeOf(t);if(c.length!==6){msg($('#lMsg'),_t('sj.login.noclip'),'err');return;}$('#lCode').value=c;$('#lCode').dispatchEvent(new Event('input'));}catch(e){msg($('#lMsg'),_t('sj.login.nopaste'),'err');$('#lCode').focus();}};
if(!(navigator.clipboard&&navigator.clipboard.readText))$('#lPaste').classList.add('hidden');
/* Code-Eingabe: ein echtes Eingabefeld (Autofill, Einfügen, Screenreader) über sechs Anzeige-Feldern */
function otpPaint(){const i=$('#lCode'),v=(i.value||'').replace(/\D/g,'').slice(0,6),foc=document.activeElement===i;document.querySelectorAll('#lOtp .otp-slots span').forEach((s,k)=>{s.textContent=v[k]||'';s.classList.toggle('f',!!v[k]);s.classList.toggle('on',foc&&(k===Math.min(v.length,5)));});}
['input','focus','blur','keyup','select'].forEach(ev=>$('#lCode').addEventListener(ev,otpPaint));
/* Ein-Tipp-Übernahme aus der Login-Mail: #code=123456&e=<base64url(E-Mail)> – nur vorausfüllen, angemeldet wird per Klick (Linkprüfer verbrauchen den Code nicht) */
const LINK_LOGIN=(()=>{try{const h=new URLSearchParams(location.hash.slice(1));const c=(h.get('code')||'').replace(/\D/g,'');const e=h.get('e');if(c.length!==6||!e)return null;let b=e.replace(/-/g,'+').replace(/_/g,'/');while(b.length%4)b+='=';const em=decodeURIComponent(escape(atob(b)));history.replaceState(null,'',location.pathname+location.search);return /.+@.+\..+/.test(em)?{code:c,email:em}:null;}catch(_){return null;}})();
function linkLogin(){if(!LINK_LOGIN)return false;lEmail=LINK_LOGIN.email;$('#lEmail').value=lEmail;$('#stepEmail').classList.add('hidden');$('#stepCode').classList.remove('hidden');$('#lTo').textContent='→ '+lEmail;$('#lCode').value=LINK_LOGIN.code;otpPaint();msg($('#lMsg'),_t('sj.login.linked'),'ok');setTimeout(()=>$('#lVerify').focus(),50);return true;}
$('#lVerify').onclick=async()=>{const code=$('#lCode').value.replace(/\D/g,'');if(code.length!==6)return;$('#lVerify').disabled=true;msg($('#lMsg'),_t('sj.checking'));
  try{const j=await api('/auth/verify',{method:'POST',body:JSON.stringify({email:lEmail,code})});KEY=j.key;localStorage.setItem('ch_api_key',KEY);localStorage.setItem('ch_email',j.email);if(j.trial_credited)sessionStorage.setItem('ch_trial','1');await boot();}
  catch(e){msg($('#lMsg'),e.message,'err');$('#lCode').value='';otpPaint();}$('#lVerify').disabled=false;};
$('#lBack').onclick=()=>{$('#stepCode').classList.add('hidden');$('#stepEmail').classList.remove('hidden');msg($('#lMsg'),'');};
$('#lResend').onclick=()=>{$('#lEmail').value=lEmail;$('#lSend').click();};
$('#lKeyGo').onclick=async()=>{const k=$('#lKey').value.trim();if(!/^ch_(live|test)_[a-f0-9]{48}$/.test(k)){msg($('#lMsg'),_t('sj.login.keyfmt'),'err');return;}KEY=k;localStorage.setItem('ch_api_key',KEY);await boot();};
$('#logout').onclick=()=>{localStorage.removeItem('ch_api_key');location.href=location.pathname;};

/* ===== Boot ===== */
async function boot(){if(LINK_LOGIN&&!boot._link){boot._link=1;if(!KEY||(localStorage.getItem('ch_email')||'').toLowerCase()!==LINK_LOGIN.email.toLowerCase()){show('login');linkLogin();return;}}if(!KEY){show('login');return;}
  let prof=null;try{const [me,sec,pr]=await Promise.all([api('/me'),api('/sectors'),api('/profile').catch(()=>null)]);ME=me;PLANS=sec.plans||[];prof=pr;setProfile(pr);}
  catch(e){if(e.status===401){localStorage.removeItem('ch_api_key');KEY='';show('login');msg($('#lMsg'),_t('sj.login.expired'),'err');return;}alert(_t('sj.error',{m:e.message}));return;}
  if(I18N.autoFromProfile(prof&&prof.profile&&prof.profile.langs))return;
  show('app');$('#who').textContent=ME.email;renderHome();renderAgents();renderPhone();renderTokens();applyNav();renderRec();
  refreshBell();
  const q=new URLSearchParams(location.search);const w=q.get('welcome');if(w&&PANELS.einrichtung){go('einrichtung');}else if(w&&PLANS.find(p=>p.id===w)){go('agents');openAgent(w);}else{const h=(location.hash||'').slice(1);if(h&&(STATIC_TABS[h]||PANELS[h]))go(h);}
  if(sessionStorage.getItem('ch_trial')){$('#trialBanner').classList.remove('hidden');sessionStorage.removeItem('ch_trial');}
  /* Erste Anmeldung bzw. noch kein Profil: Profil-Assistent (nicht direkt nach einer Zahlung – dort zuerst die Einrichtung) */
  if(prof&&!prof.exists&&!w)setTimeout(()=>{if(!PW.open)openWiz(false);},200);}
function show(v){$('#vLogin').classList.toggle('hidden',v!=='login');$('#vApp').classList.toggle('hidden',v!=='app');$('#nav2').classList.toggle('hidden',v!=='app');$('#acct').classList.toggle('hidden',v!=='app');}
const STATIC_TABS={home:'pHome',agents:'pAgents',whatsapp:'pWhatsapp',phone:'pPhone',website:'pWebsite',termine:'pTermine',bestellungen:'pBestellungen',tokens:'pTokens'};
const PANELS=<?= json_encode((object)array_map(fn($x)=>$x['v'],$CH_PANELS)) ?>;window.STUDIO_PANELS=window.STUDIO_PANELS||{};
function go(v){if(!STATIC_TABS[v]&&!PANELS[v])v='home';document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t.dataset.v===v));
  Object.entries(STATIC_TABS).forEach(([k,id])=>{const el=document.getElementById(id);if(el)el.classList.toggle('hidden',k!==v);});
  Object.keys(PANELS).forEach(k=>{const el=document.getElementById('panel-'+k);if(el)el.classList.toggle('hidden',k!==v);});
  waPlace(v);if(v==='whatsapp')loadWa();if(v==='website')renderWeb();
  if(v==='termine'&&!AP)loadAppt();if(v==='bestellungen'&&!OS)loadOrd();if(PANELS[v])loadPanel(v);
  const t=document.querySelector('.tab[data-v="'+v+'"]');if(t&&t.scrollIntoView)t.scrollIntoView({inline:'nearest',block:'nearest'});
  try{history.replaceState(null,'','#'+v);}catch(e){}window.scrollTo({top:0,behavior:'smooth'});}
/* ===== Navigation nach gewähltem Agenten: nur passende Bereiche, Kanäle (WhatsApp · Telefon · Website) zuerst ===== */
const MOD_FOR={termine:['agent-termine','agent-beauty','praxis-agent','agent-autohaus','praxis-termine','gastro-agent'],bestellungen:['agent-bestellung','gastro-agent'],taxi:['agent-taxi'],hotel:['agent-hotel'],
  rueckruf:['agent-phone','immo-agent','agent-hausverwaltung','agent-versicherung','agent-kanzlei','agent-steuer','agent-inkasso','agent-bank','agent-behoerde','agent-aesthetic','agent-hair-tr','agent-dental-tr','praxis-agent','agent-autohaus','agent-hotel','agent-sales','privat-anruf']};
const ALWAYS_TABS=['home','einrichtung','assistant','agents','website-builder','benachrichtigungen','tokens'];
function planBase(id){const P=(PLANS||[]).find(x=>x.id===id);if(P&&P.demo&&P.demo!==id&&(PLANS||[]).find(x=>x.id===P.demo))return P.demo;
  const m=/^(buy|betrieb|byok)-(.+)$/.exec(id||'');if(m){for(const c of ['agent-'+m[2],m[2]+'-agent',m[2]])if((PLANS||[]).find(x=>x.id===c))return c;}return id;}
function planChannels(id){const P=(PLANS||[]).find(x=>x.id===id)||(PLANS||[]).find(x=>x.id===planBase(id));return (P&&P.channels)||['web'];}
function focusGet(){try{return JSON.parse(localStorage.getItem('ch_focus')||'[]').filter(x=>typeof x==='string');}catch(e){return [];}}
function focusAdd(id){if(!id)return;const f=focusGet().filter(x=>x!==id);f.unshift(id);try{localStorage.setItem('ch_focus',JSON.stringify(f.slice(0,6)));}catch(e){}}
function focusPlans(){const s=new Set();(ME&&ME.plans||[]).filter(p=>p.active).forEach(p=>s.add(planBase(p.id)));focusGet().forEach(id=>s.add(planBase(id)));if(CUR)s.add(planBase(CUR.id));return [...s].filter(Boolean);}
function navAllOn(){try{return localStorage.getItem('ch_nav_all')==='1';}catch(e){return false;}}
function applyNav(){const F=[...new Set([...focusPlans(),...profPlans()])],all=navAllOn(),ch=new Set();F.forEach(id=>planChannels(id).forEach(c=>ch.add(c)));
  const want=new Set(ALWAYS_TABS);profAreas().forEach(a=>want.add(a));   /* Profil: Bedarf → Bereiche (z. B. Termine, WhatsApp, Kunden) */
  if(F.length){want.add('website');want.add('training');want.add('kunden');want.add('voices');if(ch.has('whatsapp'))want.add('whatsapp');if(ch.has('phone'))want.add('phone');
    Object.entries(MOD_FOR).forEach(([tab,ids])=>{if(F.some(id=>ids.includes(id)))want.add(tab);});}
  document.querySelectorAll('#tabs .tab[data-v]').forEach(t=>{const v=t.dataset.v;t.classList.toggle('hidden',!(all||want.has(v)));});
  const vis=v=>{const t=document.querySelector('#tabs .tab[data-v="'+v+'"]');return t&&!t.classList.contains('hidden');};
  const sep=(k,list)=>{const e=document.querySelector('#tabs .tabsep[data-sep="'+k+'"]');if(e)e.classList.toggle('hidden',!list.some(vis));};
  sep('ch',['whatsapp','phone','voices','website','website-builder']);sep('mod',['termine','bestellungen','taxi','hotel','rueckruf','kunden','training']);sep('end',['benachrichtigungen','tokens']);
  const b=$('#navAll');if(b){b.textContent=all?_t('nav.fit'):_t('nav.all');b.setAttribute('aria-pressed',all?'true':'false');}
  /* Übersicht: Modul-Karten nur für passende Bereiche */
  document.querySelectorAll('#pHome .call[onclick]').forEach(c=>{const m=/go\('([a-z]+)'\)/.exec(c.getAttribute('onclick')||'');if(m)c.classList.toggle('hidden',!vis(m[1]));});
  const cur=(document.querySelector('#tabs .tab.on')||{}).dataset;if(cur&&cur.v&&!vis(cur.v)&&!all)go('home');}
document.addEventListener('click',e=>{if(e.target&&e.target.id==='navAll'){try{localStorage.setItem('ch_nav_all',navAllOn()?'0':'1');}catch(_){}applyNav();}});
/* WhatsApp-Ansicht nutzt die vorhandene WhatsApp-Karte (gleiche Speicherung wie im Bestell-Modul) */
let WA_CARD=null,WA_HOME=null;
function waPlace(v){if(!WA_CARD){const sel=$('#odWaProv');if(!sel)return;WA_CARD=sel.closest('.card');WA_HOME=document.createComment('wa-card');WA_CARD.parentNode.insertBefore(WA_HOME,WA_CARD);}
  if(v==='whatsapp'&&WA_CARD.parentNode!==$('#waHost')){$('#waHost').appendChild(WA_CARD);WA_CARD.style.marginTop='0';}
  else if(v==='bestellungen'&&WA_HOME&&WA_CARD.parentNode===$('#waHost')){WA_HOME.parentNode.insertBefore(WA_CARD,WA_HOME.nextSibling);WA_CARD.style.marginTop='16px';}}
async function loadWa(){try{const j=await api('/order/settings');renderOrd(j);const st=j.settings||{};const ok=(st.wa_provider==='meta'&&st.wa_phone_id&&(st.wa_token_set||st.wa_token))||(st.wa_provider==='twilio'&&st.wa_number);
  const e=$('#waState');if(e){e.textContent=ok?_t('sj.wa.on'):_t('sj.wa.off');e.style.color=ok?'var(--ok)':'var(--mute)';}}catch(e){msg($('#waMsg'),esc(e.message),'err');}}
$('#waSave').onclick=async()=>{const b=$('#waSave');b.disabled=true;try{const p={wa_provider:$('#odWaProv').value,wa_phone_id:$('#odWaPhoneId').value.trim(),wa_verify:$('#odWaVerify').value.trim(),wa_number:$('#odWaNumber').value.trim()};
  const t=$('#odWaToken').value.trim();if(t&&!/^•+$/.test(t))p.wa_token=t;
  const j=await api('/order/settings',{method:'POST',body:JSON.stringify(p)});renderOrd(j);msg($('#waMsg'),_t('sj.wa.saved'),'ok');loadWa();}catch(e){msg($('#waMsg'),esc(e.message),'err');}b.disabled=false;};
/* Website-Ansicht: gewählte Agenten mit direktem Weg zum Widget-Code */
function renderWeb(){const F=focusPlans();const el=$('#webList');if(!el)return;
  if(!F.length){el.innerHTML='<div class="card"><b>'+_t('sj.web.none')+'</b><p class="mute small">'+_t('sj.web.noneTxt')+'</p><button class="btn p s" onclick="go(\'agents\')">'+_t('sj.agents.view')+'</button></div>';return;}
  el.innerHTML=F.map(id=>{const P=(PLANS||[]).find(x=>x.id===id)||{name:id};const on=active(id);return `<div class="card"><div class="row" style="justify-content:space-between"><b>${esc(P.name||id)}</b><span class="pill small">${on?_t('sj.active.l'):_t('sj.test')}</span></div><p class="mute small" style="margin:6px 0 10px">${planChannels(id).includes('phone')?_t('sj.web.same'):_t('sj.web.chat')}</p><button class="btn p s" data-web="${esc(id)}">${_t('sj.web.get')}</button></div>`;}).join('');
  el.querySelectorAll('[data-web]').forEach(b=>b.onclick=async()=>{await openAgent(b.dataset.web);const w=$('#cWidgetOn');if(w){w.closest('.sec').scrollIntoView({behavior:'smooth',block:'center'});w.closest('.sec').style.outline='2px solid var(--gold2)';setTimeout(()=>{w.closest('.sec').style.outline='';},2500);}});}

/* Modul-Panels (Training, Rückrufe, Taxi, Hotel) werden beim ersten Öffnen nachgeladen: studio-panels/<key>.html + .js */
const PANEL_LOADING={};
function loadPanel(k){const el=document.getElementById('panel-'+k);if(!el)return Promise.resolve();
  if(el.dataset.loaded==='1'){try{STUDIO_PANELS[k]&&STUDIO_PANELS[k].show&&STUDIO_PANELS[k].show();}catch(e){console.error(e);}return Promise.resolve();}
  if(PANEL_LOADING[k])return PANEL_LOADING[k];
  el.innerHTML='<div class="card mute">'+_t('sj.loading')+'</div>';
  PANEL_LOADING[k]=fetch('/chat/studio-panels/'+k+'.html?v='+PANELS[k]).then(r=>{if(!r.ok)throw new Error('HTTP '+r.status);return r.text();}).then(html=>{el.innerHTML=html;I18N.apply(el);
    return new Promise((ok,no)=>{const sc=document.createElement('script');sc.src='/chat/studio-panels/'+k+'.js?v='+PANELS[k];sc.onload=ok;sc.onerror=()=>no(new Error('Script'));document.body.appendChild(sc);});})
   .then(()=>{el.dataset.loaded='1';if(STUDIO_PANELS[k]&&STUDIO_PANELS[k].init)STUDIO_PANELS[k].init();})
   .catch(e=>{el.innerHTML='<div class="card"><div class="msg err">'+esc(_t('sj.panelErr',{m:e.message}))+'</div></div>';})
   .finally(()=>{delete PANEL_LOADING[k];});
  return PANEL_LOADING[k];}
/* „Antwort korrigieren“ aus Chat/Telefon-Transkript → Training */
function correctAnswer(plan,question,answer){go('training');loadPanel('training').then(()=>{if(window.trainCorrect)window.trainCorrect(plan,question,answer);});}
document.querySelectorAll('.tab').forEach(t=>t.onclick=()=>go(t.dataset.v));
async function refreshMe(){try{ME=await api('/me');$('#bal').textContent=de(ME.balance);$('#hBal').textContent=de(ME.balance);$('#tBal').textContent=de(ME.balance);}catch(e){}}
/* Glocke: ungelesene Benachrichtigungen (alle 60 s, nur wenn das Panel existiert) */
async function refreshBell(){const d=$('#bellDot');if(!d||!PANELS.benachrichtigungen)return;try{const j=await api('/push/inbox?limit=1');const n=+(j.unread||0);d.classList.toggle('hidden',n<=0);d.title=n?_t('sj.unread',{n}):'';}catch(e){}}
setInterval(()=>{if(document.visibilityState==='visible'&&ME)refreshBell();},60000);
if(window.ChatHelpApp&&ChatHelpApp.onNotificationTap)try{ChatHelpApp.onNotificationTap(u=>{const h=(String(u||'').split('#')[1]||'');if(h&&(STATIC_TABS[h]||PANELS[h]))go(h);refreshBell();});}catch(e){}
const active=id=>!!(ME.plans||[]).find(p=>p.id===id&&p.active);
const planOf=id=>PLANS.find(p=>p.id===id);
const isAgent=p=>p.kind==='agent';

/* ===== Übersicht ===== */
const CHN={web:_t('sj.ch.web'),phone:_t('sj.ch.phone'),whatsapp:'🟢 WhatsApp',phone_out:_t('sj.ch.out')};
const chChips=c=>(c||[]).map(x=>`<span class="tag" style="margin:2px 2px 0 0;padding:3px 7px;font-size:10px">${CHN[x]||esc(x)}</span>`).join('');
function renderHome(){$('#bal').textContent=de(ME.balance);$('#hBal').textContent=de(ME.balance);
  const bb=$('#byokBanner');if(ME.byok&&ME.byok.alert){bb.innerHTML='⚠️ '+esc(_tx(ME.byok.alert))+' <a href="#phone" onclick="go(\'phone\');return false">'+_t('sj.byok.check')+'</a>';bb.classList.remove('hidden');}else bb.classList.add('hidden');
  const act=(ME.plans||[]).filter(p=>p.active||(p.kind==='buy'&&p.license==='expired'));$('#hPlans').textContent=(ME.plans||[]).filter(p=>p.active).length;
  $('#hConv').textContent=de((ME.recent||[]).length)+'+';
  const ph=(ME.configs||[]).find(c=>+c.phone_on);$('#hPhone').textContent=ph?(ph.phone_number||_t('sj.ph.pending')):_t('sj.ph.none');
  const dd=x=>x?I18N.date(x):'–';
  $('#hList').innerHTML=act.length?act.map(p=>`<div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)"><span><b>${esc(p.name)}</b><br><span class="mute small">${p.kind==='buy'?(p.license==='lifetime'?_t('sj.pl.lifetime'):_t('sj.pl.buyIncl',{d:dd(p.period_end)})):p.kind==='byok'?_t('sj.pl.byok'):p.kind==='betrieb'?_t('sj.pl.run',{n:de(p.tokens_month)}):_t('sj.pl.rent',{d:dd(p.period_end),n:de(p.tokens_month)})} ${chChips(p.channels)}</span></span><button class="btn s" onclick="go('agents');openAgent('${p.id}')">${_t('sj.open')}</button></div>`).join(''):'<div class="mute">'+_t('sj.pl.none')+'</div>';
  const tb=$('#hRecent tbody');tb.innerHTML=(ME.recent||[]).slice(0,10).map(r=>`<tr><td>${I18N.dt(r.created_at)}</td><td>${esc(r.task)}</td><td>${esc(r.sector||'')}</td><td>${de(r.tokens)}</td></tr>`).join('')||'<tr><td colspan="4" class="mute">'+_t('sj.act.none')+'</td></tr>';}

/* ===== Profil (Registrierung): Für wen? · Betrieb/Aufgaben · Bedarf & Sprachen · Zusammenfassung — formt Navigation, Agenten-Reihenfolge und Empfehlungen ===== */
let PROFILE=null,PROFILE_REC=null,PROFILE_OPT=null,PROFILE_EXISTS=false;
function setProfile(j){if(!j||typeof j!=='object')return;PROFILE=j.profile||null;PROFILE_EXISTS=!!j.exists;PROFILE_REC=j.rec||null;if(j.options)PROFILE_OPT=j.options;const b=$('#profBtn');if(b)b.classList.toggle('hidden',!PROFILE_OPT);}
const profPlans=()=>((PROFILE_REC&&PROFILE_REC.plans)||[]).map(p=>p.id).filter(id=>(PLANS||[]).some(x=>x.id===id));
const profAreas=()=>(PROFILE_REC&&PROFILE_REC.areas)||[];
const REC_IC={beauty:'💅',aesthetik:'✨',medtour:'✈️',taxi:'🚕',hotel:'🛎',gastro:'🍕',bank:'🏦',behoerde:'🏛',privat:'🙋',service:'☎'};
const tabLabel=v=>{const t=document.querySelector('#tabs .tab[data-v="'+v+'"]');return t?t.textContent.trim():'';};
const fold=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/ı/g,'i');
/* Begründung der Empfehlung: Server liefert why={k,id,label} (profile-lib), ältere Server nur reason (deutsch) */
function recWhy(p){const w=p.why;if(w&&w.k){const lab=w.k==='sector'?optLabel('sectors',w.id,w.label):w.k==='need'?optLabel('needs',w.id,w.label):w.k==='task'?optLabel('private_tasks',w.id,w.label):'';
  if(w.k==='country')return _t('rec.why.country',{c:countryName(w.id)});return I18N.has('rec.why.'+w.k)?_t('rec.why.'+w.k,{x:lab}):_tx(p.reason||'');}return _tx(p.reason||'');}
function recCard(p){const on=!!(ME&&active(p.id));const pr=Number(p.eur_month||0),why=recWhy(p);
  return `<div class="rec-c"><div class="rec-h"><span class="rec-ic" aria-hidden="true">${REC_IC[p.sector]||ICON[p.sector]||'✦'}</span><div><b>${esc(p.name)}</b>${why?`<div class="rec-why">${esc(why)}</div>`:''}</div></div><p>${esc(p.desc||'')}</p>
  <div class="rec-a"><button class="btn p" type="button" data-try="${esc(p.id)}">${on?_t('sj.open'):_t('sj.rec.try')}</button>${on?'<span class="tag ok" style="align-self:center">'+_t('sj.active')+'</span>':`<a class="btn gold" href="${esc(p.checkout)}&_=${Date.now().toString(36)}" target="_blank" rel="noopener">${_t('sj.rec.activate')}</a>`}</div>
  <div class="rec-pr">${pr?_t('sj.rec.from',{p:I18N.eur(pr,pr%1?2:0)})+' ':''}${_t('sj.rec.tokens')}</div></div>`;}
/* Profil-Optionen kommen vom Server auf Deutsch: Übersetzung über den Schlüssel opt.<liste>.<id>, sonst Quelltext-Wörterbuch */
const OPT_PFX={sectors:'opt.sec.',needs:'opt.need.',private_tasks:'opt.task.',sizes:'opt.size.'};
function optLabel(list,id,label){const k=(OPT_PFX[list]||'opt.x.')+id;return I18N.has(k)?_t(k):_tx(label||id);}
function countryName(cc){try{const n=new Intl.DisplayNames([I18N.tag],{type:'region'}).of(cc);if(n&&n!==cc)return n;}catch(e){}return ((PROFILE_OPT&&PROFILE_OPT.countries||[]).find(c=>c.id===cc)||{}).label||cc;}
/* Länder-Fokus (country-lib über profile-lib): ein Satz, warum diese Agenten im Land zuerst gebraucht werden – nur, wenn es ihn in der Oberflächensprache gibt */
function focusWhy(){const f=PROFILE_REC&&PROFILE_REC.focus;const w=f&&f.why&&(f.why[I18N.lang]||(I18N.lang!=='de'&&I18N.lang!=='en'?'':''));if(!w)return '';return `<div class="small" style="margin-top:8px;color:var(--ink2)"><span class="tag" style="margin-right:6px">${esc(countryName(f.cc))}</span>${esc(w)}</div>`;}
function catLabel(cat,label){return I18N.has('cat.'+cat)?_t('cat.'+cat):_tx(label||cat);}
function profSummary(){const P=PROFILE;if(!P||!P.kind)return '';const O=PROFILE_OPT||{},lab=(list,id)=>optLabel(list,id,((O[list]||[]).find(x=>x.id===id)||{}).label);
  const parts=P.kind==='private'?[(P.needs||[]).map(n=>lab('private_tasks',n)).join(', ')]:[(P.sectors||[]).map(s=>lab('sectors',s)).join(', '),(P.needs||[]).map(n=>lab('needs',n)).join(', ')];
  if(P.city||P.country)parts.push([P.city,P.country?countryName(P.country):''].filter(Boolean).join(', '));if((P.langs||[]).length)parts.push(_t('sj.prof.langs',{l:P.langs.map(x=>x.toUpperCase()).join(' · ')}));return parts.filter(Boolean).join(' · ');}
function renderRec(){const el=$('#hRec');if(!el)return;if(!PROFILE_OPT){el.classList.add('hidden');return;}el.classList.remove('hidden');
  const P=PROFILE,R=(PROFILE_REC&&PROFILE_REC.plans)||[];
  if(!P||!P.kind){el.innerHTML=`<div class="card rec-cta"><div><span class="tag gold">${_t('sj.rec.steps')}</span><h3>${_t('sj.rec.fitH')}</h3><p class="mute" style="margin:6px 0 0;font-size:14px">${_t('sj.rec.fitP')}</p></div><button class="btn gold" type="button" data-prof>${_t('sj.rec.create')}</button></div>`;}
  else{const who=P.kind==='private'?_t('sj.forPrivate'):(P.company||_t('sj.forBiz'));
    el.innerHTML=`<div class="card rec"><div class="row" style="justify-content:space-between;align-items:flex-start;gap:12px"><div style="flex:1 1 280px;min-width:0"><span class="tag gold">${_t('sj.rec.forYou')}</span><h3>${esc(who)}</h3><div class="mute small" style="margin-top:4px">${esc(profSummary())}</div>${focusWhy()}</div><button class="btn s" type="button" data-prof style="min-height:44px">${_t('sj.prof.edit')}</button></div>
      <div class="rec-grid">${R.map(recCard).join('')||'<div class="mute small">'+_t('sj.rec.none')+'</div>'}</div></div>`;}
  el.querySelectorAll('[data-prof]').forEach(b=>b.onclick=()=>openWiz(true));
  el.querySelectorAll('[data-try]').forEach(b=>b.onclick=()=>openAgent(b.dataset.try));}

const PW={open:false,step:1,edit:false,d:null,dir:1,busy:false,rec:null,err:'',ret:null};
const pwPriv=()=>PW.d&&PW.d.kind==='private';
const pwTitle=n=>n===1?_t('pw.t1'):n===2?(pwPriv()?_t('pw.t2p'):_t('pw.t2b')):n===3?(pwPriv()?_t('pw.t3p'):_t('pw.t3b')):_t('pw.t4');
function openWiz(edit){if(!PROFILE_OPT)return;const p=PROFILE||{};
  PW.d={kind:p.kind||null,company:p.company||'',sectors:[...(p.sectors||[])],size:p.size||'',city:p.city||'',country:p.country||'',needs:[...(p.needs||[])],langs:[...(p.langs||[])]};
  Object.assign(PW,{edit:!!edit,step:1,dir:1,rec:null,err:'',busy:false,open:true,ret:document.activeElement});
  $('#pfWiz').classList.remove('hidden');document.body.classList.add('pf-lock');pwRender();}
function closeWiz(){PW.open=false;$('#pfWiz').classList.add('hidden');document.body.classList.remove('pf-lock');try{if(PW.ret&&PW.ret.focus&&document.contains(PW.ret))PW.ret.focus();}catch(e){}}
async function pwSave(){const j=await api('/profile',{method:'POST',body:JSON.stringify(PW.d)});setProfile(j);PW.rec=j.rec||{plans:[],areas:[]};applyNav();renderAgents();renderRec();return j;}
async function pwLater(){if(PW.busy)return;
  if(!PW.edit&&PW.step<4){const d=PW.d,any=d.kind||d.company||d.city||d.country||d.size||d.sectors.length||d.needs.length||d.langs.length;
    try{if(any)await pwSave();else{const j=await api('/profile',{method:'POST',body:JSON.stringify({dismissed:true})});setProfile(j);renderRec();}}catch(e){}}
  closeWiz();}
function pwClearStep(){const d=PW.d;if(PW.step===1)d.kind=null;else if(PW.step===2){if(pwPriv())d.needs=[];else Object.assign(d,{company:'',sectors:[],size:''});d.city='';d.country='';}else if(PW.step===3){if(!pwPriv())d.needs=[];d.langs=[];}}
async function pwNext(skip){if(PW.busy)return;
  if(PW.step===4){closeWiz();go('home');return;}
  if(skip)pwClearStep();
  if(PW.step===1&&PW.d.kind!=='private')PW.d.needs=PW.d.needs.filter(n=>!/^p_/.test(n));
  if(PW.step===1&&PW.d.kind==='private')PW.d.needs=PW.d.needs.filter(n=>/^p_/.test(n));
  PW.dir=1;PW.step++;
  if(PW.step===4){PW.busy=true;PW.rec=null;PW.err='';pwRender();try{await pwSave();}catch(e){PW.err=e.message||_t('c.saveFail');}PW.busy=false;}
  pwRender();}
function pwBackStep(){if(PW.busy||PW.step<=1)return;PW.dir=-1;PW.step--;pwRender();}
function pc(attr,id,label,icon,on,extra){return `<button type="button" class="pc${extra||''}" data-${attr}="${esc(id)}" aria-pressed="${on?'true':'false'}">${icon?`<span aria-hidden="true">${icon}</span>`:''}${esc(label)}</button>`;}
function pwLangs(){const L=PROFILE_OPT.langs||[];return L.map(l=>pc('lang',l.id,l.label,'',PW.d.langs.includes(l.id),l.top?' top':'')).join('');}
/* Branchen im Assistenten: zuerst die Schwerpunkte des Landes (country-lib über profile-lib: sector_focus), dann die übrigen in Katalog-Reihenfolge */
function pwSectors(){const O=PROFILE_OPT,S=(O.sectors||[]).slice(),cc=(PW.d.country||O.country||'').toUpperCase(),F=(O.sector_focus&&O.sector_focus[cc])||(cc===(O.country||'')?O.sector_rank||[]:[]);
  if(!F.length)return S;const r=s=>{const i=F.indexOf(s.category);return i<0?99:i;};return S.map((s,i)=>[s,i]).sort((a,b)=>(r(a[0])-r(b[0]))||(a[1]-b[1])).map(x=>x[0]);}
function pwLoc(){const C=(PROFILE_OPT.countries||[]).map(c=>({id:c.id,label:countryName(c.id)})).sort((a,b)=>a.label.localeCompare(b.label,I18N.tag));return `<div class="pfw-2"><div><label for="pfCity">${_t('pw.city')}</label><input type="text" id="pfCity" maxlength="80" autocomplete="address-level2" placeholder="${esc(_t('pw.cityPh'))}" value="${esc(PW.d.city)}"></div>
  <div><label for="pfCountry">${_t('pw.country')}</label><select id="pfCountry"><option value="">${_t('pw.choose')}</option>${C.map(c=>`<option value="${c.id}"${PW.d.country===c.id?' selected':''}>${esc(c.label)}</option>`).join('')}</select></div></div>`;}
function pwStepHTML(n){const O=PROFILE_OPT,d=PW.d,priv=pwPriv();
  if(n===1){const k=(id,ic,t,s)=>{const on=d.kind===id,first=!d.kind&&id==='business';return `<button type="button" class="pfw-kind" role="radio" aria-checked="${on?'true':'false'}" tabindex="${on||first?0:-1}" data-kind="${id}"><span class="ic" aria-hidden="true">${ic}</span><b>${t}</b><span>${s}</span></button>`;};
    return `<p class="pfw-kick">${PW.edit?_t('pw.editKick'):_t('pw.welcome')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.h1')}</h2>
      <p class="pfw-sub">${_t('pw.sub1')}</p>
      <div class="pfw-kinds" role="radiogroup" aria-label="${esc(_t('pw.t1'))}">${k('business','🏢',_t('pw.biz'),_t('pw.bizTxt'))}${k('private','🙋',_t('pw.priv'),_t('pw.privTxt'))}</div>`;}
  if(n===2&&priv)return `<p class="pfw-kick">${_t('pw.privKick')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.h2p')}</h2><p class="pfw-sub">${_t('pw.sub2p')}</p>
      <div class="pfw-chips big" role="group" aria-label="${esc(_t('pw.tasks'))}" style="margin-top:20px">${(O.private_tasks||[]).map(t=>pc('need',t.id,optLabel('private_tasks',t.id,t.label),t.icon,d.needs.includes(t.id))).join('')}</div><div class="pfw-sep"></div>${pwLoc()}`;
  if(n===2){const max=O.max_sectors||6,sizes=O.sizes||[];
    return `<p class="pfw-kick">${_t('pw.t2b')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.h2b')}</h2><p class="pfw-sub">${_t('pw.sub2b')}</p>
      <label for="pfCompany">${_t('pw.company')}</label><input type="text" id="pfCompany" maxlength="120" autocomplete="organization" placeholder="${esc(_t('pw.companyPh'))}" value="${esc(d.company)}">
      <div class="pfw-lbl"><label for="pfSecQ">${_t('pw.sector')}</label><span id="pfSecN" aria-live="polite">${_t('pw.nOf',{n:d.sectors.length,max})}</span></div>
      <input type="search" id="pfSecQ" placeholder="${esc(_t('pw.secQ'))}" autocomplete="off" aria-controls="pfSecs">
      <div class="pfw-chips pfw-scroll" id="pfSecs" role="group" aria-label="${esc(_t('pw.sectors'))}">${pwSectors().map(s=>{const L=optLabel('sectors',s.id,s.label),G=catLabel(s.category,s.group);return `<button type="button" class="pc" data-sec="${esc(s.id)}" data-q="${esc(fold(L+' '+s.label+' '+G+' '+s.group+' '+(s.kw||'')))}" aria-pressed="${d.sectors.includes(s.id)?'true':'false'}" title="${esc(G)}"><span aria-hidden="true">${s.icon}</span>${esc(L)}</button>`;}).join('')}</div>
      <div id="pfSecNone" class="mute small hidden" style="margin-top:8px">${_t('pw.secNone')}</div>
      <div class="pfw-lbl"><span id="pfSizeL">${_t('pw.size')}</span></div>
      <div class="pfw-seg" role="radiogroup" aria-labelledby="pfSizeL">${sizes.map((s,i)=>`<button type="button" class="pc" role="radio" data-size="${s.id}" aria-checked="${d.size===s.id?'true':'false'}" tabindex="${d.size===s.id||(!d.size&&i===0)?0:-1}">${esc(optLabel('sizes',s.id,s.label))}</button>`).join('')}</div>
      ${pwLoc()}`;}
  if(n===3&&priv)return `<p class="pfw-kick">${_t('pw.langsKick')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.h3p')}</h2><p class="pfw-sub">${_t('pw.sub3p')}</p>
      <div class="pfw-chips" role="group" aria-label="${esc(_t('pw.langsKick'))}" style="margin-top:20px">${pwLangs()}</div>`;
  if(n===3)return `<p class="pfw-kick">${_t('pw.needKick')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.h3b')}</h2><p class="pfw-sub">${_t('pw.sub3b')}</p>
      <div class="pfw-chips big" role="group" aria-label="${esc(_t('pw.need'))}" style="margin-top:20px">${(O.needs||[]).map(t=>pc('need',t.id,optLabel('needs',t.id,t.label),t.icon,d.needs.includes(t.id))).join('')}</div>
      <div class="pfw-lbl" style="margin-top:24px"><span id="pfLangL">${_t('pw.custLangs')}</span></div>
      <div class="pfw-chips" role="group" aria-labelledby="pfLangL">${pwLangs()}</div>`;
  /* 4: Zusammenfassung */
  if(PW.busy||(!PW.rec&&!PW.err))return `<p class="pfw-kick">${_t('pw.wait')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.building')}</h2><div class="pfw-load" role="status"><i aria-hidden="true"></i>${_t('pw.eval')}</div>`;
  if(PW.err)return `<p class="pfw-kick">${_t('pw.almost')}</p><h2 id="pfTitle" tabindex="-1">${_t('pw.saveFail')}</h2><div class="msg err">${esc(PW.err)}</div><button type="button" class="btn p" id="pfRetry" style="margin-top:14px;min-height:48px">${_t('pw.retry')}</button>`;
  const R=PW.rec.plans||[],A=(PW.rec.areas||[]).map(tabLabel).filter(Boolean),who=priv?_t('pw.whoP'):(d.company?_t('pw.whoC',{c:d.company}):_t('pw.whoB'));
  return `<p class="pfw-kick">${_t('pw.done')}</p><h2 id="pfTitle" tabindex="-1">${esc(_t('pw.h4',{who}))}</h2>
    <p class="pfw-sub">${A.length?_t('pw.navFirst'):_t('pw.navAgents')}</p>${A.length?`<div class="pfw-areas">${A.map(a=>`<span>${esc(a)}</span>`).join('')}</div>`:''}
    <div class="pfw-lbl" style="margin-top:22px"><span>${R.length?_t('pw.recFor'):''}</span></div>
    <div class="rec-grid" style="margin-top:0">${R.map(recCard).join('')||'<div class="mute small">'+_t('pw.noRec')+'</div>'}</div>
    ${PANELS.einrichtung?'<button type="button" class="pfw-link" data-go="einrichtung">'+_t('pw.onb')+'</button>':''}
    <p class="mute small" style="margin:10px 0 0">${_t('pw.later')}</p>`;}
function pwRender(){const n=PW.step,B=$('#pfBody');
  $('#pfStepTxt').textContent=_t('pw.step',{n});document.querySelectorAll('#pfDots i').forEach((d,i)=>{d.className=i+1<n?'done':i+1===n?'on':'';});
  $('#pfBack').classList.toggle('hidden',n===1);$('#pfSkip').classList.toggle('hidden',n===4);
  const nx=$('#pfNext');nx.textContent=n===4?_t('pw.openStudio'):n===3?_t('pw.build'):_t('pw.next');nx.classList.toggle('fin',n===4);nx.disabled=PW.busy;$('#pfBack').disabled=PW.busy;
  $('#pfLater').textContent=n===4||PW.edit?_t('c.close'):_t('pw.laterBtn');
  B.innerHTML=`<section class="pfw-s${PW.dir<0?' back':''}">${pwStepHTML(n)}</section>`;B.scrollTop=0;pwBind();
  $('#pfLive').textContent=_t('pw.step',{n})+': '+pwTitle(n);const t=$('#pfTitle');if(t)try{t.focus({preventScroll:true});}catch(e){}}
function pwBind(){const B=$('#pfBody'),d=PW.d,max=(PROFILE_OPT&&PROFILE_OPT.max_sectors)||6;
  const tog=(arr,id)=>{const i=arr.indexOf(id);if(i>=0)arr.splice(i,1);else arr.push(id);};
  B.querySelectorAll('[data-kind]').forEach(x=>x.onclick=ev=>{d.kind=x.dataset.kind;B.querySelectorAll('[data-kind]').forEach(y=>{const on=y===x;y.setAttribute('aria-checked',on?'true':'false');y.tabIndex=on?0:-1;});if(ev.detail>0)setTimeout(()=>{if(PW.open&&PW.step===1)pwNext();},280);});
  B.querySelectorAll('[data-sec]').forEach(x=>x.onclick=()=>{const id=x.dataset.sec;if(!d.sectors.includes(id)&&d.sectors.length>=max){const c=$('#pfSecN');if(c){c.textContent=_t('pw.max',{max});c.style.color='var(--rose)';}return;}tog(d.sectors,id);x.setAttribute('aria-pressed',d.sectors.includes(id)?'true':'false');const c=$('#pfSecN');if(c){c.textContent=_t('pw.nOf',{n:d.sectors.length,max});c.style.color='';}});
  B.querySelectorAll('[data-need]').forEach(x=>x.onclick=()=>{tog(d.needs,x.dataset.need);x.setAttribute('aria-pressed',d.needs.includes(x.dataset.need)?'true':'false');});
  B.querySelectorAll('[data-lang]').forEach(x=>x.onclick=()=>{tog(d.langs,x.dataset.lang);x.setAttribute('aria-pressed',d.langs.includes(x.dataset.lang)?'true':'false');});
  B.querySelectorAll('[data-size]').forEach(x=>x.onclick=()=>{d.size=d.size===x.dataset.size?'':x.dataset.size;B.querySelectorAll('[data-size]').forEach(y=>{const on=y.dataset.size===d.size;y.setAttribute('aria-checked',on?'true':'false');y.tabIndex=on||(!d.size&&y===B.querySelector('[data-size]'))?0:-1;});});
  [['#pfCompany','company'],['#pfCity','city']].forEach(([s,k])=>{const i=B.querySelector(s);if(i)i.oninput=()=>{d[k]=i.value;};});
  const cs=B.querySelector('#pfCountry');if(cs)cs.onchange=()=>{d.country=cs.value;const box=B.querySelector('#pfSecs');if(box)pwSectors().forEach(s=>{const b=box.querySelector('[data-sec="'+s.id+'"]');if(b)box.appendChild(b);});};
  const q=B.querySelector('#pfSecQ');if(q){q.oninput=()=>{const f=fold(q.value).trim();let n=0;B.querySelectorAll('[data-sec]').forEach(x=>{const show=!f||f.split(/\s+/).every(w=>x.dataset.q.includes(w))||d.sectors.includes(x.dataset.sec);x.classList.toggle('hidden',!show);if(show)n++;});$('#pfSecNone').classList.toggle('hidden',n>0);};q.onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();const f=B.querySelector('[data-sec]:not(.hidden)');if(f&&q.value.trim())f.click();}};}
  B.querySelectorAll('[data-try]').forEach(x=>x.onclick=()=>{closeWiz();openAgent(x.dataset.try);});
  B.querySelectorAll('[data-go]').forEach(x=>x.onclick=()=>{closeWiz();go(x.dataset.go);});
  const r=B.querySelector('#pfRetry');if(r)r.onclick=async()=>{PW.busy=true;PW.err='';pwRender();try{await pwSave();}catch(e){PW.err=e.message||_t('c.saveFail');}PW.busy=false;pwRender();};}
$('#pfNext').onclick=()=>pwNext(false);$('#pfSkip').onclick=()=>pwNext(true);$('#pfBack').onclick=pwBackStep;$('#pfLater').onclick=pwLater;
$('#profBtn').onclick=()=>openWiz(true);
$('#pfWiz').addEventListener('click',e=>{if(e.target===e.currentTarget)pwLater();});
$('#pfWiz').addEventListener('keydown',e=>{
  if(e.key==='Escape'){e.preventDefault();pwLater();return;}
  if(e.key==='Tab'){const f=[...$('#pfWiz').querySelectorAll('button,a[href],input,select')].filter(x=>!x.disabled&&x.tabIndex>=0&&x.offsetParent!==null);if(!f.length)return;const a=f[0],z=f[f.length-1];
    if(e.shiftKey&&(document.activeElement===a||!$('#pfWiz').contains(document.activeElement)||document.activeElement.id==='pfTitle')){e.preventDefault();z.focus();}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();}return;}
  const rg=e.target.closest&&e.target.closest('[role=radiogroup]');
  if(rg&&/^Arrow(Left|Right|Up|Down)$/.test(e.key)){const r=[...rg.querySelectorAll('[role=radio]')];let i=r.indexOf(e.target);if(i<0)return;e.preventDefault();i=(i+(/Right|Down/.test(e.key)?1:-1)+r.length)%r.length;r[i].focus();if(r[i].getAttribute('aria-checked')!=='true')r[i].click();return;}
  if(e.key==='Enter'&&e.target.matches&&e.target.matches('#pfCompany,#pfCity')){e.preventDefault();pwNext(false);}});

/* ===== Agenten ===== */
let filter='all';
function renderAgents(){const f=$('#agFilter');const REC=profPlans();if(filter==='rec'&&!REC.length)filter='all';const F=[['all',_t('ag.f.all')],...(REC.length?[['rec',_t('ag.f.rec')]]:[]),['mine',_t('ag.f.mine')],['beruf',_t('ag.f.beruf')],['agent',_t('ag.f.agent')],['privat',_t('sj.forPrivate')],['abo',_t('ag.f.abo')]];f.innerHTML=F.map(([k,l])=>`<span class="chip${filter===k?' on':''}" style="${filter===k?'background:var(--ink);color:#fff;border-color:var(--ink)':''}" onclick="filter='${k}';renderAgents()">${l}</span>`).join('');
  const BERUF=['inkasso','kanzlei','steuer','versicherung','hausverwaltung','automotive','praxis','service'];
  let list=PLANS.filter(p=>p.kind!=='buy');
  if(filter==='rec')list=list.filter(p=>REC.includes(p.id));if(filter==='mine')list=list.filter(p=>active(p.id));if(filter==='beruf')list=list.filter(p=>BERUF.includes(p.sector)&&p.kind==='agent');if(filter==='agent')list=list.filter(p=>p.kind==='agent');if(filter==='abo')list=list.filter(p=>p.kind==='abo'&&p.sector!=='privat');if(filter==='privat')list=list.filter(p=>p.sector==='privat');
  /* Reihenfolge: aktiv → passend zum Profil (Branche + Bedarf) → Berufsgruppen */
  const rk=id=>{const i=REC.indexOf(id);return i<0?99:i;};
  list.sort((a,b)=>(active(b.id)-active(a.id))||(rk(a.id)-rk(b.id))||(BERUF.includes(b.sector)-BERUF.includes(a.sector)));
  $('#agGrid').innerHTML=list.map(p=>`<div class="card ag" onclick="openAgent('${p.id}')"><div class="row" style="justify-content:space-between"><div class="ic">${ICON[p.sector]||'✦'}</div>${active(p.id)?'<span class="tag ok">'+_t('sj.active')+'</span>':REC.includes(p.id)?'<span class="tag gold">'+_t('ag.rec')+'</span>':'<span class="tag">'+_t('ag.test')+'</span>'}</div><h3>${esc(p.name)}</h3><p>${esc(p.desc)}</p><div class="pr">${_t('ag.price',{p:I18N.eur(p.eur_month,p.eur_month%1?2:0),n:de(p.tokens_month)})}</div><div style="margin-top:6px">${chChips(p.channels)}</div></div>`).join('');}
function showList(){$('#agWork').classList.add('hidden');$('#agList').classList.remove('hidden');CUR=null;}
async function openAgent(id){const p=planOf(id);if(!p)return;CUR=p;focusAdd(p.id);applyNav();HIST=[];$('#agList').classList.add('hidden');$('#agWork').classList.remove('hidden');go('agents');
  $('#wName').textContent=p.name;$('#wDesc').textContent=p.desc;$('#wStatus').textContent=active(id)?_t('ag.on'):_t('ag.studioTest',{n:ME.pricing.agent_turn});$('#wStatus').className='tag '+(active(id)?'ok':'');
  $('#wRent').href='/chat/tokens-checkout.php?plan='+id+'&_='+Date.now().toString(36);$('#wRent').classList.toggle('hidden',active(id));
  $('#wMs').innerHTML='';$('#wChips').innerHTML=(CHIPS[id]||CHIPS.default).map(c=>`<span class="chip" onclick="send(${JSON.stringify(c).replace(/"/g,'&quot;')})">${esc(c)}</span>`).join('');
  $('#wCost').textContent=active(id)?_t('ag.incl',{n:ME.pricing.agent_turn}):_t('ag.studioTest',{n:ME.pricing.agent_turn});
  try{const j=await api('/agent/config?plan='+id);CFG=j.config;$('#cCompany').value=CFG.company||'';$('#cTone').value=CFG.tone||'freundlich-professionell';$('#cLang').value=CFG.lang||'de';{const al=String(CFG.langs||'').split(',').filter(Boolean);document.querySelectorAll('#cLangs input').forEach(x=>{x.checked=!al.length||al.includes(x.value);});};$('#cKb').value=CFG.kb||'';$('#cWidgetOn').checked=!!+CFG.widget_on;$('#cDomain').value=CFG.widget_domain||'';
    $('#cSnip').textContent=j.widget_snippet||_t('ag.snipSave');msg($('#cMsg'),j.plan_active?'':_t('ag.kbFree'),'info');
    sys(CFG.kb?_t('ag.kbLoaded',{n:de(CFG.kb.length)}):_t('ag.kbNone'));}catch(e){msg($('#cMsg'),e.message,'err');}}
$('#cSample').onclick=()=>{if($('#cKb').value.trim()&&!confirm(_t('ag.kbReplace')))return;$('#cKb').value=SAMPLE[CUR.id]||SAMPLE.default;};
$('#cSave').onclick=async()=>{if(!CUR)return;$('#cSave').disabled=true;try{const j=await api('/agent/config?plan='+CUR.id,{method:'POST',body:JSON.stringify({company:$('#cCompany').value,tone:$('#cTone').value,lang:$('#cLang').value,kb:$('#cKb').value,langs:[...document.querySelectorAll('#cLangs input:checked')].map(x=>x.value),widget_on:$('#cWidgetOn').checked?1:0,widget_domain:$('#cDomain').value})});CFG=j.config;$('#cSnip').textContent=j.widget_snippet;$('#cWidgetOn').checked=!!+CFG.widget_on;msg($('#cMsg'),_tx(j.note)||_t('ag.saved'),j.note?'info':'ok');}catch(e){msg($('#cMsg'),e.message,'err');}$('#cSave').disabled=false;};
function add(role,text,meta){const d=document.createElement('div');d.className='m '+role;d.textContent=text;if(meta){const s=document.createElement('span');s.className='meta';s.textContent=meta;d.appendChild(s);}$('#wMs').appendChild(d);$('#wMs').scrollTop=$('#wMs').scrollHeight;return d;}
function sys(t){add('sys',t);}
let phoneMode=false,micOn=false,rec=null;const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
async function send(text){text=(text||$('#wIn').value).trim();if(!text||!CUR)return;$('#wIn').value='';add('u',text);HIST.push({role:'user',content:text});const t=add('a','…');$('#wSend').disabled=true;
  try{const j=await api('/agent',{method:'POST',body:JSON.stringify({plan:CUR.id,messages:HIST.slice(-16),lang:$('#cLang').value||'de',channel:phoneMode?'phone':'chat'})});
    const a=String(j.result||'');t.textContent=a;HIST.push({role:'assistant',content:a});const meta=document.createElement('span');meta.className='meta';meta.textContent=_t('ag.meta',{n:j.tokens_used,b:de(j.balance),a:j.access==='plan'?_t('ag.plan'):'Tokens'});t.appendChild(meta);
    if(PANELS.training){const fix=document.createElement('a');fix.href='#';fix.className='meta';fix.style.color='var(--gold)';fix.textContent=_t('ag.improve');const qq=text,aa=a,pp=CUR.id;fix.onclick=e=>{e.preventDefault();correctAnswer(pp,qq,aa);};t.appendChild(fix);}$('#bal').textContent=de(j.balance);
    if(phoneMode)speak(a.replace(/\[(WEITERLEITEN|ENDE)\]/g,''),()=>{if(micOn)listen();});}
  catch(e){t.textContent='⚠ '+e.message;if(e.status===402){const b=document.createElement('a');b.className='btn s gold';b.style.marginTop='8px';b.href=(e.data&&e.data.rent)||'/#preise';b.target='_blank';b.textContent=_t('ag.rentOrBuy');t.appendChild(document.createElement('br'));t.appendChild(b);}}
  $('#wSend').disabled=false;$('#wIn').focus();}
$('#wForm').onsubmit=e=>{e.preventDefault();micOn=false;send();};$('#wIn').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();micOn=false;send();}});
$('#wClear').onclick=()=>{HIST=[];$('#wMs').innerHTML='';sys(_t('ag.newConv'));};
$('#wVoice').onclick=()=>{phoneMode=!phoneMode;$('#wVoice').classList.toggle('p',phoneMode);$('#wMic').classList.toggle('hidden',!(phoneMode&&SR));$('#wCost').textContent=phoneMode?_t('ag.phoneMode',{n:ME.pricing.phone_turn}):(active(CUR.id)?_t('ag.incl',{n:ME.pricing.agent_turn}):_t('ag.studioTest',{n:ME.pricing.agent_turn}));
  if(phoneMode){sys(_t('ag.phoneShort')+' '+(SR?_t('ag.pressMic'):_t('ag.noSR')));if(CFG&&CFG.phone_greeting){add('a',CFG.phone_greeting);HIST.push({role:'assistant',content:CFG.phone_greeting});speak(CFG.phone_greeting);}}else{try{speechSynthesis.cancel();}catch(e){}}};
const phLang=()=>(($('#phVoice')&&$('#phVoice').value)||'de-DE').slice(0,5), phMale=()=>/-g?m$/.test(($('#phVoice')&&$('#phVoice').value)||'');
/* Browser-Vorschau: möglichst natürliche Systemstimme (Edge „Online (Natural)“, Chrome „Google“, Safari „Premium/Enhanced“) – am echten Telefon spricht Amazon Polly Generative */
function pickVoice(l,male){const vs=speechSynthesis.getVoices().filter(v=>v.lang.replace('_','-').toLowerCase().startsWith(l.toLowerCase().slice(0,2)));const same=vs.filter(v=>v.lang.replace('_','-')===l);const pool=same.length?same:vs;
  const g=male?/daniel|stefan|conrad|killian|matthew|guy|arthur|ryan|remi|henri|sergio|alvaro|adriano|diego|maxim|dmitry|male|mann/i:/vicki|katja|amala|anna|petra|helena|joanna|jenny|aria|amy|sonia|lea|denise|lucia|elvira|bianca|isabella|elsa|female|frau/i;
  const score=v=>(/natural|online|neural|premium|enhanced|google|siri/i.test(v.name)?4:0)+(g.test(v.name)?2:0)+(v.localService?0:1);return pool.sort((a,b)=>score(b)-score(a))[0];}
function speak(text,done){try{if(!window.speechSynthesis){done&&done();return;}speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(text.replace(/\[(WEITERLEITEN|ENDE)\]/g,'').replace(/\s*…\s*/g,', '));const l=phLang();u.lang=l;const v=pickVoice(l,phMale());if(v)u.voice=v;u.rate=0.98;u.pitch=1;u.onend=()=>done&&done();speechSynthesis.speak(u);}catch(e){done&&done();}}
function listen(){if(!SR)return;try{rec&&rec.abort();}catch(e){}rec=new SR();rec.lang=phLang();rec.interimResults=false;$('#wMic').classList.add('rec');micOn=true;rec.onresult=e=>{$('#wMic').classList.remove('rec');send(e.results[0][0].transcript);};rec.onerror=()=>$('#wMic').classList.remove('rec');rec.onend=()=>$('#wMic').classList.remove('rec');rec.start();}
$('#wMic').onclick=()=>{if($('#wMic').classList.contains('rec')){try{rec.stop();}catch(e){}return;}try{speechSynthesis.cancel();}catch(e){}listen();};

/* ===== Telefon ===== */
const PHONE_OK=['inkasso','kanzlei','steuer','versicherung','hausverwaltung','automotive','praxis','service','ecommerce','immobilien','gastro','logistik','finanzen','marketing'];
let PHCFG=null;
function renderPhone(){const sel=$('#phPlan');const list=PLANS.filter(p=>p.kind==='agent').sort((a,b)=>(active(b.id)-active(a.id))||(a.sector==='service'?-1:1));
  sel.innerHTML=list.map(p=>`<option value="${p.id}">${esc(p.name)}${active(p.id)?' · '+_t('sj.active.l'):''}</option>`).join('');
  const pre=(ME.configs||[]).find(c=>+c.phone_on)||(ME.plans||[]).find(p=>p.active&&PLANS.find(x=>x.id===p.id&&x.kind==='agent'));sel.value=pre?(pre.plan||pre.id):(list[0]?list[0].id:'');loadPhone();}
$('#phPlan').onchange=loadPhone;
async function loadPhone(){const id=$('#phPlan').value;if(!id)return;try{const j=await api('/agent/config?plan='+id);PHCFG=j.config;$('#phOn').checked=!!+PHCFG.phone_on;$('#phGreet').value=PHCFG.phone_greeting||'';$('#phVoice').value=PHCFG.phone_voice||'de-DE';$('#phFwd').value=PHCFG.phone_forward||'';$('#phHours').value=PHCFG.phone_hours||'';
    $('#phOnNote').textContent=j.plan_active?_t('ph.onNote'):_t('ph.offNote');
    $('#phNumber').innerHTML=PHCFG.phone_number?`<b style="font-size:20px;color:var(--ink)">${esc(PHCFG.phone_number)}</b> <span class="tag ok">${_t('c.connected')}</span>`:(j.plan_active&&+PHCFG.phone_on?'<span class="tag gold">'+_t('ph.setup')+'</span> <span class="small">'+_t('ph.setupTxt')+'</span>':'<span class="small">'+_t('ph.noNum')+'</span>');
    $('#phHook').textContent=j.phone_webhook||_t('ph.hookSave');loadCalls(id);if(!CN)loadConn();}catch(e){msg($('#phMsg'),e.message,'err');}}
$('#phSave').onclick=async()=>{const id=$('#phPlan').value;$('#phSave').disabled=true;try{const j=await api('/agent/config?plan='+id,{method:'POST',body:JSON.stringify({phone_on:$('#phOn').checked?1:0,phone_greeting:$('#phGreet').value,phone_voice:$('#phVoice').value,phone_forward:$('#phFwd').value,phone_hours:$('#phHours').value})});PHCFG=j.config;$('#phOn').checked=!!+PHCFG.phone_on;$('#phHook').textContent=j.phone_webhook;msg($('#phMsg'),_tx(j.note)||_t('c.saved'),j.note?'info':'ok');await refreshMe();renderHome();loadPhone();}catch(e){msg($('#phMsg'),e.message,'err');}$('#phSave').disabled=false;};
async function loadCalls(id){try{const j=await api('/phone/calls?plan='+id);const el=$('#phCalls');if(!j.calls.length){el.innerHTML='<div class="mute small">'+_t('ph.noCalls')+'</div>';return;}
  el.innerHTML=j.calls.map(c=>`<div class="call" onclick="this.classList.toggle('open')"><div class="hd"><span><b>${esc(c.from_number||_t('c.unknown'))}</b> · ${I18N.dt(c.started_at)}</span><span>${_t('ph.callMeta',{n:c.turns,t:c.tokens,o:esc(_tx(c.outcome||c.status))})}</span></div><div class="tr">${(c.transcript||[]).map(m=>`<div class="${m.role==='user'?'u':'a'}">${esc(m.content)}</div>`).join('')}</div></div>`).join('');}catch(e){}}
/* Verbindungen: eigene Twilio-/Meta-Zugänge (Server gibt nur „••••1234“ zurück) */
let CN=null;
async function loadConn(){try{const j=await api('/connections');CN=j.connections||{};$('#cnSid').value=CN.twilio_sid||'';$('#cnFrom').value=CN.twilio_from||'';$('#cnTok').value='';$('#cnMeta').value='';
  $('#cnTok').placeholder=CN.twilio_token?_t('cn.stored',{h:CN.twilio_token}):_t('cn.32');$('#cnMeta').placeholder=CN.meta_app_secret?_t('cn.stored',{h:CN.meta_app_secret}):_t('cn.32');
  renderAi(j.ai);
  $('#cnState').innerHTML=(CN.twilio_sid&&CN.twilio_token?'<span class="tag ok">'+_t('cn.twOn')+'</span> ':'<span class="tag">'+_t('cn.twOff')+'</span> ')+(CN.meta_app_secret?'<span class="tag ok">'+_t('cn.metaOn')+'</span>':'<span class="tag">'+_t('cn.metaOff')+'</span>');}catch(e){}}
$('#cnSave').onclick=async()=>{const b={twilio_sid:$('#cnSid').value.trim(),twilio_from:$('#cnFrom').value.trim()};if($('#cnTok').value.trim())b.twilio_token=$('#cnTok').value.trim();if($('#cnMeta').value.trim())b.meta_app_secret=$('#cnMeta').value.trim();
  $('#cnSave').disabled=true;try{const j=await api('/connections',{method:'POST',body:JSON.stringify(b)});msg($('#cnMsg'),j.twilio_verified===true?_t('cn.verified'):_t('c.saved'),'ok');loadConn();}catch(e){msg($('#cnMsg'),e.message,'err');}$('#cnSave').disabled=false;};
/* Eigener KI-Schlüssel (BYOK): nur Hinweis „••••1234“ + Status; Test = 1-Token-Aufruf beim Anbieter */
const AIST={ok:['ok',_t('ai.works')],invalid:['rose',_t('ai.rejected')],'':['',_t('ai.unchecked')]};
function renderAi(a){if(!a)return;const P=a.providers||{};[['claude','#aiClaude','#aiClaudeTag'],['deepseek','#aiDeep','#aiDeepTag']].forEach(([k,inp,tag])=>{const x=P[k]||{};$(inp).value='';$(inp).placeholder=x.hint?_t('cn.stored',{h:x.hint}):(k==='claude'?'sk-ant-…':'sk-…');
    const st=x.hint?(AIST[x.status]||AIST['']):null;$(tag).innerHTML=st?`<span class="tag ${st[0]}">${st[1]}</span>`:'<span class="tag">'+_t('ai.none')+'</span>';});
  $('#aiPref').value=a.pref||'auto';$('#aiNote').textContent=_tx(a.note||'');
  $('#aiAlert').innerHTML=a.alert?`<div class="msg err">⚠️ ${esc(_tx(a.alert))}</div>`:'';}
async function aiPost(b,okText){$('#aiSave').disabled=true;try{const j=await api('/connections',{method:'POST',body:JSON.stringify(b)});renderAi(j.ai);const t=Object.values(j.ai_tests||{});msg($('#aiMsg'),t.length?t.map(x=>esc(_tx(x.message))).join('<br>'):okText,t.some(x=>!x.ok)?'info':'ok');refreshMe();}catch(e){msg($('#aiMsg'),esc(e.message),'err');}$('#aiSave').disabled=false;}
$('#aiSave').onclick=()=>{const b={ai_pref:$('#aiPref').value};if($('#aiClaude').value.trim())b.claude_key=$('#aiClaude').value.trim();if($('#aiDeep').value.trim())b.deepseek_key=$('#aiDeep').value.trim();aiPost(b,_t('c.saved'));};
$('#aiClaudeDel').onclick=()=>{if(confirm(_t('ai.delClaudeQ')))aiPost({claude_key:''},_t('ai.delClaude'));};
$('#aiDeepDel').onclick=()=>{if(confirm(_t('ai.delDeepQ')))aiPost({deepseek_key:''},_t('ai.delDeep'));};
async function aiTest(provider,btn){btn.disabled=true;msg($('#aiMsg'),_t('ai.testing'),'info');try{const j=await api('/connections/ai-test',{method:'POST',body:JSON.stringify({provider})});renderAi(j.ai);msg($('#aiMsg'),esc(_tx(j.message)),j.ok?'ok':'err');refreshMe();}catch(e){msg($('#aiMsg'),esc(e.message),'err');}btn.disabled=false;}
$('#aiClaudeTest').onclick=e=>aiTest('claude',e.currentTarget);$('#aiDeepTest').onclick=e=>aiTest('deepseek',e.currentTarget);
$('#phTest').onclick=()=>{const id=$('#phPlan').value;openAgent(id).then(()=>{if(!phoneMode)$('#wVoice').click();if(SR)setTimeout(listen,800);});};

/* ===== Termine ===== */
let AP=null,APSVC=[];const WDN=[0,1,2,3,4,5,6].map(i=>I18N.wd(i,'long'));
const eurS=c=>I18N.eur(c/100);
/* Euro-Eingabe → Cent (ein Parser für alle Tabs und Modul-Panels): das LETZTE Trennzeichen mit 1–2 Ziffern dahinter ist das Komma, alle anderen sind Tausenderpunkte.
   '8.50'|'8,50' → 850 · '1.290,00'|'1,290.00'|'1290'|'1.290' → 129000 · '€ 9,90' → 990 · ' 9.9 ' → 990 · leer/ungültig/negativ → 0 */
const eurIn=v=>{const s=String(v??'').trim();if(/^-/.test(s.replace(/^[^\d.,-]+/,'')))return 0;const t=s.replace(/[^\d.,]/g,''),m=t.match(/^(.*?)[.,](\d{1,2})$/);const i=parseInt((m?m[1]:t).replace(/[.,]/g,'')||'0',10),d=m?parseInt(m[2].padEnd(2,'0'),10):0;return Math.max(0,(i||0)*100+(d||0));}, eurOut=c=>((c||0)/100).toFixed(2).replace('.',I18N.dec==='.'?'.':',');
/* Öffnungszeiten-Editor für Termine ('appt': Ende 00:00/24:00 = Tagesende) und Bestellungen ('ord': Ende ≤ Beginn = endet am Folgetag, z. B. 17:00–00:00, 18:00–01:30); mehrere Zeiträume je Wochentag */
const hmN=t=>{t=String(t||'').trim();const m=t.match(/^(\d{1,2})[:.](\d{2})/);return m?(m[1].padStart(2,'0')+':'+m[2]):t;}, hm24=t=>hmN(t)==='24:00'?'00:00':hmN(t);
function hoursUI(box,hours,def,more,mode){box.dataset.mode=mode;box.innerHTML=[1,2,3,4,5,6,0].map(w=>`<div class="row hDay" data-w="${w}" style="gap:6px 8px;padding:6px 0;border-bottom:1px solid var(--line);align-items:flex-start"><span style="width:92px;font-weight:600;padding-top:7px;overflow-wrap:anywhere">${WDN[w]}</span><input type="checkbox" class="hOn" title="${esc(_t('hr.open'))}" style="margin-top:10px"><div class="hIvs" style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;flex:1 1 250px;min-width:0"><button class="hAdd" type="button" title="${esc(_t('hr.addT'))}" style="background:none;border:0;padding:1px 0;font:600 12.5px var(--sans);color:var(--indigo);cursor:pointer">${_t('hr.add')}</button></div></div>`).join('');
  box.querySelectorAll('.hDay').forEach(d=>{const on=d.querySelector('.hOn'),list=(((hours||{})[d.dataset.w])||[]).filter(iv=>Array.isArray(iv)&&iv.length>=2);on.checked=list.length>0;(list.length?list:[def]).forEach(iv=>hIvAdd(d,iv));
    on.onchange=()=>hDay(d);d.querySelector('.hAdd').onclick=()=>{if(on.checked)hIvAdd(d,more);on.checked=true;hDay(d);};hDay(d);});}
function hIvAdd(d,iv){const r=document.createElement('div');r.className='row hIv';r.style.cssText='gap:6px;width:100%';const ti='style="flex:0 1 118px;width:118px;min-width:96px"';r.innerHTML=`<input type="time" class="hFrom" value="${esc(hm24(iv[0]))}" ${ti}><span class="mute">–</span><input type="time" class="hTo" value="${esc(hm24(iv[1]))}" ${ti}><button class="btn s hDel" type="button" title="${esc(_t('hr.del'))}">✕</button><span class="hHint small"></span>`;
  r.querySelectorAll('input').forEach(i=>{i.oninput=i.onchange=()=>hHint(r);});r.querySelector('.hDel').onclick=()=>{if(d.querySelectorAll('.hIv').length>1)r.remove();else d.querySelector('.hOn').checked=false;hDay(d);};d.querySelector('.hIvs').insertBefore(r,d.querySelector('.hAdd'));hHint(r);}
function hDay(d){const on=d.querySelector('.hOn').checked;d.querySelectorAll('.hIv').forEach(r=>r.style.opacity=on?'':'.45');d.querySelectorAll('.hIv input').forEach(i=>i.disabled=!on);}
function hHint(r){const mode=r.closest('[data-mode]').dataset.mode,f=hmN(r.querySelector('.hFrom').value),t=hmN(r.querySelector('.hTo').value),h=r.querySelector('.hHint');let x='',bad=false;
  if(f&&t&&t<=f){if(mode==='ord')x=_t('hr.nextDay');else if(t==='00:00')x=_t('hr.eod');else{x=_t('hr.endBefore');bad=true;}}h.textContent=x;h.style.color=bad?'var(--rose)':'var(--mute)';}
/* → {hours:{'0':[[von,bis],…],…}, err:[…]} (geschlossene Tage = []) */
function hoursRead(box){const mode=box.dataset.mode,out={},err=[];box.querySelectorAll('.hDay').forEach(d=>{const w=d.dataset.w;out[w]=[];if(!d.querySelector('.hOn').checked)return;
  d.querySelectorAll('.hIv').forEach(r=>{const f=hmN(r.querySelector('.hFrom').value),t=hmN(r.querySelector('.hTo').value);if(!f||!t){err.push(WDN[w]+': '+_t('hr.noTime'));return;}
    if(mode!=='ord'&&t<=f&&t!=='00:00'&&t!=='24:00'){err.push(WDN[w]+' '+f+'–'+t+': '+_t('hr.endAfter'));return;}out[w].push([f,t]);});out[w].sort((a,b)=>a[0]<b[0]?-1:a[0]>b[0]?1:0);});return {hours:out,err};}
/* Tage, die gesendet wurden, aber nach dem Speichern geschlossen sind (Server hat Zeiten verworfen) */
const hoursLost=(sent,got)=>Object.keys(sent).filter(w=>(sent[w]||[]).length&&!(((got||{})[w])||[]).length).map(w=>WDN[w]);
async function loadAppt(){try{const j=await api('/appt/settings');AP=j.settings;APSVC=j.services||[];renderAppt(j.booking_url);loadBookings();}catch(e){msg($('#apMsg'),esc(e.message),'err');}}
function renderAppt(url){const s=AP;$('#apOn').checked=!!s.appt_on;$('#apCompany').value=s.company||'';$('#apAddress').value=s.address||'';$('#apNotify').value=s.notify_email||'';$('#apTz').value=s.tz||'Europe/Berlin';$('#apConfirm').value=s.confirm_text||'';$('#apSlot').value=s.slot_min;$('#apBuffer').value=s.buffer_min;$('#apLead').value=s.lead_min;$('#apMax').value=s.max_days;
  hoursUI($('#apHours'),s.hours,['09:00','18:00'],['14:00','18:00'],'appt');
  $('#apSvcs').innerHTML='';APSVC.filter(x=>x.active!==0).forEach(addSvcRow);if(!APSVC.length)addSvcRow({name:'',duration_min:30,price_cents:0});
  $('#apUrl').textContent=url||'–';$('#apOpen').href=url||'#';
  const sel=$('#mbSvc');sel.innerHTML=APSVC.filter(x=>x.active!==0).map(x=>`<option value="${x.id}">${esc(x.name)} · ${_t('c.min',{n:x.duration_min})}</option>`).join('')||'<option value="">'+_t('ap.defSlot')+'</option>';}
function addSvcRow(x){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="sId" value="${x.id||0}"><input type="text" class="sName" placeholder="${esc(_t('ap.svc'))}" value="${esc(x.name||'')}" style="flex:2;min-width:140px"><input type="text" class="sDur" value="${x.duration_min||30}" style="width:70px" title="${esc(_t('c.minutes'))}"><span class="mute small">${_t('c.minShort')}</span><input type="text" class="sPrice" value="${x.price_cents?eurOut(x.price_cents):''}" placeholder="${eurOut(0)}" style="width:84px"><span class="mute small">€</span><button class="btn s" type="button" title="${esc(_t('c.remove'))}">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#apSvcs').appendChild(d);}
$('#apAddSvc').onclick=()=>addSvcRow({});
$('#apSave').onclick=async()=>{const {hours,err}=hoursRead($('#apHours'));if(err.length){msg($('#apMsg'),_t('hr.check')+' '+esc(err.join(' · ')),'err');return;}
  /* Preis: Cent über eurIn (price_cents) + Euro als Zahl mit Punkt (price) für ältere Backends */
  const services=[...document.querySelectorAll('#apSvcs .row')].map(r=>{const c=eurIn(r.querySelector('.sPrice').value);return {id:+r.querySelector('.sId').value,name:r.querySelector('.sName').value,duration_min:+r.querySelector('.sDur').value,price_cents:c,price:Number((c/100).toFixed(2))};}).filter(s=>s.name.trim());
  $('#apSave').disabled=true;try{const j=await api('/appt/settings',{method:'POST',body:JSON.stringify({appt_on:$('#apOn').checked?1:0,company:$('#apCompany').value,address:$('#apAddress').value,notify_email:$('#apNotify').value,tz:$('#apTz').value,confirm_text:$('#apConfirm').value,slot_min:+$('#apSlot').value,buffer_min:+$('#apBuffer').value,lead_min:+$('#apLead').value,max_days:+$('#apMax').value,hours,services})});AP=j.settings;APSVC=j.services||[];renderAppt(j.booking_url);
    const lost=hoursLost(hours,AP.hours);msg($('#apMsg'),lost.length?_t('hr.lost',{d:esc(lost.join(', '))}):_t('ap.saved'),lost.length?'err':'ok');loadBookings();}catch(e){msg($('#apMsg'),esc(e.message),'err');}$('#apSave').disabled=false;};
$('#apCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText($('#apUrl').textContent);msg($('#apMsg'),_t('c.linkCopied'),'ok');};
$('#apReload').onclick=loadBookings;
async function loadBookings(){try{const j=await api('/appt/bookings');const el=$('#apList');if(!j.bookings.length){el.innerHTML='<div class="mute small">'+_t('ap.none')+'</div>';return;}
  let last='';el.innerHTML=j.bookings.map(b=>{const head=b.date!==last?`<div style="font:700 12px var(--sans);letter-spacing:.06em;text-transform:uppercase;color:var(--gold);margin:12px 0 4px">${esc(I18N.date(b.date+'T12:00:00',{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'}))}</div>`:'';last=b.date;const st={booked:'ok',done:'teal',cancelled:'rose',noshow:'rose'}[b.status]||'';
    return head+`<div class="call" style="margin-top:6px"><div class="row" style="justify-content:space-between"><span><b>${esc(b.time)}–${esc(b.end)}</b> · ${esc(b.service)}<br><span class="mute small">${esc(b.name)} · ${esc(b.phone)} ${b.email?'· '+esc(b.email):''} ${b.note?'· '+esc(b.note):''} · ${esc(b.source)} · ${_t('c.code',{c:b.code})}</span></span><span class="row" style="gap:6px"><span class="tag ${st}">${esc(I18N.has('ap.st.'+b.status)?_t('ap.st.'+b.status):b.status)}</span>${b.status==='booked'?`<button class="btn s" onclick="apStatus(${b.id},'done')">${_t('ap.came')}</button><button class="btn s" onclick="apStatus(${b.id},'noshow')">${_t('ap.noshow')}</button><button class="btn s" onclick="apCancel('${b.code}')">${_t('ap.cancel')}</button>`:''}</span></div></div>`;}).join('');}catch(e){$('#apList').textContent=e.message;}}
async function apStatus(id,st){try{await api('/appt/status',{method:'POST',body:JSON.stringify({id,status:st})});loadBookings();}catch(e){alert(e.message);}}
async function apCancel(code){if(!confirm(_t('ap.cancelQ')))return;try{await api('/appt/cancel',{method:'POST',body:JSON.stringify({code})});loadBookings();}catch(e){alert(e.message);}}
$('#mbDate').onchange=async()=>{const d=$('#mbDate').value;if(!d)return;const j=await api('/appt/slots?date='+d+'&service='+($('#mbSvc').value||''));$('#mbTime').innerHTML=(j.slots||[]).map(t=>`<option>${t}</option>`).join('')||'<option value="">'+_t('ap.noSlot')+'</option>';};
$('#mbSvc').onchange=()=>$('#mbDate').onchange();
$('#mbBook').onclick=async()=>{const d=$('#mbDate').value,t=$('#mbTime').value;if(!d||!t){msg($('#mbMsg'),_t('ap.pick'),'err');return;}try{const j=await api('/appt/book',{method:'POST',body:JSON.stringify({service_id:$('#mbSvc').value||null,start:d+' '+t,name:$('#mbName').value,phone:$('#mbPhone').value,email:$('#mbEmail').value,note:$('#mbNote').value})});msg($('#mbMsg'),_t('ap.booked',{c:j.booking.code}),'ok');loadBookings();$('#mbDate').onchange();}catch(e){msg($('#mbMsg'),e.message,'err');}};

/* ===== Bestellungen ===== */
let OS=null,OLINKS=null,odTimer=null,ODSEL={},ODHTML=null,ODSKIP=0;   /* eurIn/eurOut: siehe Termine (ein Parser für alle Tabs) · ODSEL: gewählter Fahrer je Bestell-ID, übersteht das Auto-Refresh · ODSKIP: übersprungene Auto-Refreshes in Folge */
const optStr=o=>(o||[]).map(g=>g.name+': '+(g.choices||[]).map(c=>c.label+' '+(c.delta>0?'+':'')+eurOut(c.delta)).join(' | ')).join('; ');
async function loadOrd(){try{const j=await api('/order/settings');renderOrd(j);loadOrders();if(odTimer)clearInterval(odTimer);odTimer=setInterval(()=>{if(!$('#pBestellungen').classList.contains('hidden'))loadOrders(true);},20000);}catch(e){msg($('#odMsg'),esc(e.message),'err');}}
function renderOrd(j){const s=OS=j.settings;OLINKS=j.links||{};$('#odOn').checked=!!s.order_on;$('#odName').value=s.name||'';$('#odAddress').value=s.address||'';$('#odPhone').value=s.phone||'';$('#odNotify').value=s.notify_email||'';$('#odTz').value=s.tz||'Europe/Berlin';
  $('#odDelivery').checked=!!s.delivery_on;$('#odPickup').checked=!!s.pickup_on;$('#odMin').value=eurOut(s.min_order_cents);$('#odFee').value=eurOut(s.delivery_fee_cents);$('#odEtaD').value=s.eta_delivery_min;$('#odEtaP').value=s.eta_pickup_min;$('#odDisc').value=s.pickup_discount_pct||0;
  const pm=String(s.payment_methods||'bar').split(',');document.querySelectorAll('.odPay').forEach(c=>c.checked=pm.includes(c.value));
  hoursUI($('#odHours'),s.hours,['11:30','22:00'],['17:30','22:30'],'ord');
  $('#odZones').innerHTML='';(s.zones||[]).forEach(addZoneRow);if(!(s.zones||[]).length)addZoneRow({name:_t('od.center'),plz:'',fee_cents:0,min_cents:1500});
  $('#odMenu').innerHTML='';(j.menu||[]).filter(m=>m.active!==0).forEach(addMenuRow);
  $('#odDrivers').innerHTML='';(j.drivers||[]).filter(d=>d.active!==0).forEach(addDriverRow);
  $('#odWaProv').value=s.wa_provider||'meta';waProv();$('#odWaPhoneId').value=s.wa_phone_id||'';$('#odWaToken').value=s.wa_token||'';$('#odWaVerify').value=s.wa_verify||'';$('#odWaNumber').value=s.wa_number||'';
  $('#odWaHook').textContent=OLINKS.whatsapp_webhook?OLINKS.whatsapp_webhook+'\nVerify-Token: '+(OLINKS.verify_token||''):_t('od.urlSave');
  $('#odBoard').textContent=OLINKS.board||_t('od.linkSave');$('#odBoardOpen').href=OLINKS.board||'#';}
function waProv(){const m=$('#odWaProv').value==='meta';$('#odWaMeta').classList.toggle('hidden',!m);$('#odWaTwilio').classList.toggle('hidden',m);}$('#odWaProv').onchange=waProv;
function addZoneRow(z){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="text" class="zName" placeholder="${esc(_t('od.zone'))}" value="${esc(z.name||'')}" style="flex:1;min-width:110px"><input type="text" class="zPlz" placeholder="${esc(_t('od.plzPh'))}" value="${esc(z.plz||'')}" style="flex:2;min-width:160px"><input type="text" class="zFee" value="${eurOut(z.fee_cents)}" style="width:70px" title="${esc(_t('od.fee'))}"><span class="mute small">€</span><input type="text" class="zMin" value="${eurOut(z.min_cents)}" style="width:76px" title="${esc(_t('od.minVal'))}"><span class="mute small">${_t('od.eurMin')}</span><button class="btn s" type="button">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#odZones').appendChild(d);}
function addMenuRow(m){const d=document.createElement('div');d.className='row';d.style.cssText='gap:6px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="mId" value="${m.id||0}"><input type="text" class="mCat" placeholder="${esc(_t('od.cat'))}" value="${esc(m.category||'')}" style="width:96px"><input type="text" class="mName" placeholder="${esc(_t('c.name'))}" value="${esc(m.name||'')}" style="flex:1.2;min-width:120px"><input type="text" class="mDesc" placeholder="${esc(_t('od.desc'))}" value="${esc(m.description||'')}" style="flex:1.5;min-width:120px"><input type="text" class="mPrice" value="${eurOut(m.price_cents)}" style="width:66px" title="${esc(_t('od.price'))}"><input type="text" class="mOpt" placeholder="${esc(_t('od.opts'))}" value="${esc(typeof m.options==='string'?m.options:optStr(m.options))}" style="flex:1.4;min-width:140px"><input type="text" class="mTags" placeholder="${esc(_t('od.tags'))}" value="${esc(m.tags||'')}" style="width:90px"><button class="btn s" type="button">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#odMenu').appendChild(d);}
function addDriverRow(x){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="dId" value="${x.id||0}"><input type="text" class="dName" placeholder="${esc(_t('c.name'))}" value="${esc(x.name||'')}" style="flex:1;min-width:110px"><input type="tel" class="dPhone" placeholder="+49 … (WhatsApp/SMS)" value="${esc(x.phone||'')}" style="flex:1;min-width:140px">${x.url?`<a class="btn s" href="${esc(x.url)}" target="_blank">${_t('od.drvLink')}</a><button class="btn s" type="button" data-u="${esc(x.url)}" title="${esc(_t('c.copyLink'))}">⧉</button>`:'<span class="mute small">'+_t('od.linkAfter')+'</span>'}<button class="btn s" type="button">✕</button>`;const bs=d.querySelectorAll('button');bs[bs.length-1].onclick=()=>d.remove();const cp=d.querySelector('[data-u]');if(cp)cp.onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText(cp.dataset.u);msg($('#odMsg'),_t('od.drvCopied'),'ok');};$('#odDrivers').appendChild(d);}
$('#odAddZone').onclick=()=>addZoneRow({});$('#odAddItem').onclick=()=>addMenuRow({});$('#odAddDriver').onclick=()=>addDriverRow({});
$('#odDemoMenu').onclick=async()=>{if($('#odMenu').children.length&&!confirm(_t('od.demoQ')))return;try{const r=await fetch('/chat/order-api.php?a=demo_menu&_='+Date.now(),{cache:'no-store'});const j=await r.json().catch(()=>({}));if(!r.ok||!Array.isArray(j.menu))throw new Error(_te({data:j},_t('od.demoNA',{s:r.status})));
    j.menu.forEach(m=>addMenuRow({...m,id:0}));
    /* Demo-Zonen nur, wenn noch keine Zone mit PLZ existiert (auch wenn alle Zeilen entfernt wurden); sonst bleibt mindestens eine Zeile zum Ausfüllen */
    if(![...document.querySelectorAll('#odZones .zPlz')].some(i=>i.value.trim())){$('#odZones').innerHTML='';(j.zones||[]).forEach(addZoneRow);}if(!$('#odZones').querySelector('.zPlz'))addZoneRow({name:_t('od.center'),plz:'',fee_cents:0,min_cents:1500});
    msg($('#odMsg'),_t('od.demoOk'),'info');}catch(e){msg($('#odMsg'),esc(e.message),'err');}};
function ordPayload(){const {hours}=hoursRead($('#odHours'));
  const zones=[...document.querySelectorAll('#odZones .row')].map(r=>({name:r.querySelector('.zName').value,plz:r.querySelector('.zPlz').value,fee_cents:eurIn(r.querySelector('.zFee').value),min_cents:eurIn(r.querySelector('.zMin').value)})).filter(z=>z.plz.trim());
  const menu=[...document.querySelectorAll('#odMenu .row')].map(r=>({id:+r.querySelector('.mId').value,category:r.querySelector('.mCat').value,name:r.querySelector('.mName').value,description:r.querySelector('.mDesc').value,price_cents:eurIn(r.querySelector('.mPrice').value),options:r.querySelector('.mOpt').value,tags:r.querySelector('.mTags').value})).filter(m=>m.name.trim());
  const drivers=[...document.querySelectorAll('#odDrivers .row')].map(r=>({id:+r.querySelector('.dId').value,name:r.querySelector('.dName').value,phone:r.querySelector('.dPhone').value})).filter(d=>d.name.trim());
  return {order_on:$('#odOn').checked?1:0,name:$('#odName').value,address:$('#odAddress').value,phone:$('#odPhone').value,notify_email:$('#odNotify').value,tz:$('#odTz').value,delivery_on:$('#odDelivery').checked?1:0,pickup_on:$('#odPickup').checked?1:0,min_order_cents:eurIn($('#odMin').value),delivery_fee_cents:eurIn($('#odFee').value),eta_delivery_min:+$('#odEtaD').value,eta_pickup_min:+$('#odEtaP').value,pickup_discount_pct:Math.max(0,Math.min(50,parseInt($('#odDisc').value,10)||0)),payment_methods:[...document.querySelectorAll('.odPay:checked')].map(c=>c.value).join(','),hours,zones,menu,drivers,wa_provider:$('#odWaProv').value,wa_phone_id:$('#odWaPhoneId').value,wa_token:$('#odWaToken').value,wa_verify:$('#odWaVerify').value,wa_number:$('#odWaNumber').value};}
$('#odSave').onclick=async()=>{const hr=hoursRead($('#odHours'));if(hr.err.length){msg($('#odMsg'),_t('hr.check')+' '+esc(hr.err.join(' · ')),'err');return;}
  $('#odSave').disabled=true;try{const p=ordPayload(),j=await api('/order/settings',{method:'POST',body:JSON.stringify(p)});renderOrd(j);const lost=hoursLost(p.hours,(j.settings||{}).hours);
    msg($('#odMsg'),lost.length?_t('hr.lost',{d:esc(lost.join(', '))}):_t('od.saved'),lost.length?'err':'ok');loadOrders();}catch(e){msg($('#odMsg'),esc(e.message),'err');}$('#odSave').disabled=false;};
$('#odWaCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText((OLINKS&&OLINKS.whatsapp_webhook)||'');msg($('#odMsg'),_t('od.hookCopied'),'ok');};
$('#odBoardCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText((OLINKS&&OLINKS.board)||'');msg($('#odMsg'),_t('od.boardCopied'),'ok');};
$('#odReload').onclick=()=>loadOrders();$('#odAll').onchange=()=>loadOrders();
const OST={new:[_t('od.st.new'),'gold'],confirmed:[_t('od.st.confirmed'),'indigo'],preparing:[_t('od.st.preparing'),'indigo'],ready:[_t('od.st.ready'),'teal'],out_for_delivery:[_t('od.st.out'),'teal'],delivered:[_t('od.st.delivered'),'ok'],cancelled:[_t('od.st.cancelled'),'rose']};
/* Auto-Refresh (quiet): nur bei Änderungen neu zeichnen; hat ein Feld in der Liste den Fokus (offenes Fahrer-Menü), höchstens 2 Refreshes in Folge aufschieben, danach trotzdem zeichnen und den Fokus aufs neue Feld zurückgeben. Fahrer-Vorauswahl: ODSEL (Wahl im Studio), sonst der bereits zugewiesene Fahrer (wie board.php) */
async function loadOrders(quiet){try{const j=await api('/order/orders?scope='+($('#odAll').checked?'all':'open'));const el=$('#odList');const drv=j.drivers||[];const orders=j.orders||[];
  const ids=new Set(orders.map(o=>String(o.id)));Object.keys(ODSEL).forEach(k=>{if(!ids.has(k))delete ODSEL[k];});
  const html=!orders.length?'<div class="mute small">'+_t('od.none')+'</div>':orders.map(o=>{const [lbl,cls]=OST[o.status]||[o.status,''];let acts='';
    if(o.status==='new')acts=`<button class="btn s" onclick="odStatus(${o.id},'confirmed')">${_t('od.a.confirm')}</button><button class="btn s" onclick="if(confirm(_t('od.cancelQ')))odStatus(${o.id},'cancelled')">${_t('od.a.cancel')}</button>`;
    else if(o.status==='confirmed')acts=`<button class="btn s" onclick="odStatus(${o.id},'preparing')">${_t('od.a.prep')}</button>`;
    else if(o.status==='preparing')acts=`<button class="btn s" onclick="odStatus(${o.id},'ready')">${_t('od.a.ready')}</button>`;
    else if(o.status==='ready'&&o.type==='delivery'){const cur=ODSEL[o.id]!=null?String(ODSEL[o.id]):String(((o.driver&&drv.find(d=>d.name===o.driver.name))||{}).id||'');acts=`<select id="odD${o.id}" onchange="ODSEL[${+o.id}]=this.value" style="width:auto;padding:6px 8px">${drv.map(d=>`<option value="${d.id}"${String(d.id)===cur?' selected':''}>${esc(d.name)}</option>`).join('')||'<option value="">'+_t('od.noDrv')+'</option>'}</select><button class="btn s" onclick="odStatus(${o.id},'out_for_delivery',document.getElementById('odD${o.id}').value)">${_t('od.a.out')}</button>`;}
    else if(o.status==='ready')acts=`<button class="btn s" onclick="odStatus(${o.id},'delivered')">${_t('od.a.picked')}</button>`;
    else if(o.status==='out_for_delivery')acts=`<button class="btn s" onclick="odStatus(${o.id},'delivered')">${_t('od.a.delivered')}</button>`;
    return `<div class="call" style="margin-top:8px"><div class="row" style="justify-content:space-between"><span><b>${esc(o.code)}</b> · ${o.type==='delivery'?_t('od.delivery'):_t('od.pickup')} · ⏱ ${esc(o.eta||'')} · <b>${esc(o.total)}</b> (${esc(_tx(o.payment))})<br><span class="mute small">${esc(o.items_text)}<br>${esc(o.name)} · ${esc(o.phone)}${o.address?' · '+esc(o.address):''}${o.zone?' · '+esc(o.zone):''}${o.distance_km!=null?' · '+o.distance_km+' km':''}${o.driver?' · '+_t('od.driver',{n:esc(o.driver.name)}):''} · ${esc(o.channel)} · ${_t('od.at',{t:esc(o.created)})}${o.note?' · 📝 '+esc(o.note):''}</span></span><span class="row" style="gap:6px"><span class="tag ${cls}">${lbl}</span>${acts}</span></div></div>`;}).join('');
  const a=document.activeElement,foc=quiet&&a&&a!==el&&el.contains(a)&&/^(SELECT|INPUT|TEXTAREA)$/.test(a.tagName)?a.id||'-':'';if(quiet&&html===ODHTML){ODSKIP=0;return;}if(foc&&ODSKIP++<2)return;ODSKIP=0;ODHTML=html;el.innerHTML=html;const f=foc&&document.getElementById(foc);if(f)f.focus({preventScroll:true});}catch(e){if(!quiet){ODHTML=null;$('#odList').textContent=e.message;}}}
async function odStatus(id,status,driver_id){try{await api('/order/status',{method:'POST',body:JSON.stringify({id,status,driver_id:driver_id||null})});delete ODSEL[id];loadOrders();}catch(e){alert(e.message);}}
$('#odChkGo').onclick=async()=>{const a=$('#odChk').value.trim();if(!a)return;$('#odChkRes').textContent=_t('od.checking');try{const j=await api('/order/check?address='+encodeURIComponent(a));$('#odChkRes').innerHTML=j.ok?`<span class="tag ok">${_t('od.chk.ok')}</span> ${_t('od.chk.res',{z:esc(j.zone||'–'),f:eurS(j.fee_cents),m:eurS(j.min_cents)})}${j.distance_km!=null?' · '+j.distance_km+' km':''}${j.geo?'<br><span class="mute">'+esc(j.geo.display)+'</span>':''}`:`<span class="tag rose">${j.reason==='unvollständig'?_t('od.chk.inc'):_t('od.chk.out')}</span> ${esc(_tx(j.hint)||_t('od.chk.hint'))}${j.distance_km!=null?' · '+j.distance_km+' km':''}`;}catch(e){$('#odChkRes').textContent=e.message;}};
$('#odChk').addEventListener('keydown',e=>{if(e.key==='Enter')$('#odChkGo').click();});

/* ===== Tokens ===== */
/* Lizenzen & Betrieb (GET /v1/billing) und Kostenübersicht (GET /v1/usage) */
const eurF=(n,d=2)=>I18N.eur(n,d);
async function loadBilling(){try{const j=await api('/billing');$('#bilDays').textContent=j.included_days;const L=j.licenses||[];
  $('#bilTag').textContent=j.byok&&j.byok.active?_t('bl.ownOn'):(L.length?(L.length>1?_t('bl.licN',{n:L.length}):_t('bl.lic1')):_t('bl.lic0'));$('#bilTag').className='tag'+(j.byok&&j.byok.active?' ok':'');
  $('#bilList').innerHTML=L.length?L.map(l=>{const st=l.state==='lifetime'?'<span class="tag ok">'+_t('bl.lifetime')+'</span>':l.state==='included'?`<span class="tag ok">${_t('bl.inclUntil',{d:I18N.date(l.included_until)})}</span>`:'<span class="tag rose">'+_t('bl.need')+'</span>';
    const run=l.run?`<div class="mute small" style="margin-top:4px">${_t('bl.runs')} <b>${l.run.kind==='byok'?_t('bl.byok'):_t('bl.run')}</b>${l.run.status==='trialing'?' '+_t('bl.trial'):''}</div>`:'';
    const opts=l.state==='lifetime'?'':(l.options||[]).filter(o=>!l.run||o.id!==l.run.plan).map(o=>`<a class="btn s${o.kind==='betrieb'?' gold':''}" style="min-height:44px" href="${o.checkout}&_=${Date.now().toString(36)}" target="_blank">${o.kind==='betrieb'?_t('bl.optRun',{p:eurF(o.eur_month,0),n:de(o.tokens_month)}):_t('bl.optByok',{p:eurF(o.eur_month,0)})}</a>`).join(' ');
    return `<div style="padding:10px 0;border-bottom:1px solid var(--line)"><b>${esc(l.name)}</b> ${st}${run}<div class="row" style="gap:6px;margin-top:8px;flex-wrap:wrap">${opts}</div></div>`;}).join('')
    :'<span class="mute">'+_t('bl.none')+'</span>';}catch(e){$('#bilList').textContent=e.message;}}
async function loadUsage(){const m=$('#useMonth').value||new Date().toISOString().slice(0,7);if(!$('#useMonth').value)$('#useMonth').value=m;
  try{const j=await api('/usage?month='+m);const tb=$('#useTbl tbody');const C=j.channels||[];
  tb.innerHTML=C.length?C.map(c=>{const mt=c.byok.in+c.byok.out+c.byok.cache_read+c.platform_ai.in+c.platform_ai.out+c.platform_ai.cache_read;
    return `<tr><td>${esc(_tx(c.label))}</td><td>${de(c.calls)}</td><td>${de(mt)}</td><td>${c.byok.eur?eurF(c.byok.eur)+' <span class="mute small">'+_t('bl.own')+'</span><br>':''}${c.platform_ai.eur?'<span class="mute small">'+_t('bl.platform',{p:eurF(c.platform_ai.eur)})+'</span>':(!c.byok.eur?'–':'')}</td><td>${de(c.platform_tokens)}${c.platform_tokens?' <span class="mute small">≈ '+eurF(c.platform_tokens_eur)+'</span>':''}</td></tr>`;}).join('')
    +`<tr><th>${_t('bl.sum')}</th><th>${de(j.totals.calls)}</th><th>${de(j.totals.model_tokens)}</th><th>${eurF(j.totals.byok_eur)} <span class="mute small">${_t('bl.own')}</span></th><th>${de(j.totals.platform_tokens)} <span class="mute small">≈ ${eurF(j.totals.platform_tokens_eur)}</span></th></tr>`
    :'<tr><td colspan="5" class="mute">'+_t('bl.noUse')+'</td></tr>';
  $('#useNote').textContent=(_tx(j.note||'')||'')+' '+_t('bl.fx',{r:I18N.num(j.fx_usd_eur,{maximumFractionDigits:4})});}catch(e){$('#useNote').textContent=e.message;}}
$('#useMonth').onchange=loadUsage;
function renderTokens(){loadBilling();loadUsage();$('#tBal').textContent=de(ME.balance);$('#tKey').textContent=KEY.slice(0,16)+'…'+KEY.slice(-6)+'   '+_t('tk.full',{k:KEY});const tb=$('#tRecent tbody');tb.innerHTML=(ME.recent||[]).map(r=>`<tr><td>${I18N.dt(r.created_at)}</td><td>${esc(r.task)}</td><td>${esc(r.sector||'')}</td><td>${de(r.tokens)}</td></tr>`).join('')||'<tr><td colspan="4" class="mute">'+_t('tk.noUse')+'</td></tr>';}
boot();
</script>
</body></html>
