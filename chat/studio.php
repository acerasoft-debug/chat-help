<?php header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false); header('X-Robots-Tag: noindex'); ?><!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp Studio – Agenten, Telefon, Widget & Tokens</title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap">
<style>
:root{--bg:#f6f7fb;--sur:#fff;--sur2:#f1f2f7;--line:#e4e6ee;--line2:#d6d9e4;--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--mute2:#9094a6;--gold:#b8862b;--gold2:#d4a84a;--goldl:#f6ecd3;--indigo:#4f46e5;--indigol:#eceafe;--teal:#0f9f9c;--teall:#e2f6f4;--rose:#d9336b;--rosel:#fde7ee;--ok:#1a9e5a;--okl:#e4f5ea;
--sans:'Inter',system-ui,sans-serif;--serif:'Fraunces',Georgia,serif;--sh1:0 1px 2px rgba(15,18,34,.05),0 8px 26px rgba(15,18,34,.06);--sh2:0 2px 6px rgba(15,18,34,.06),0 24px 60px rgba(15,18,34,.12)}
*{box-sizing:border-box}html,body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 var(--sans);-webkit-font-smoothing:antialiased}
a{color:var(--gold);text-decoration:none}button,input,select,textarea{font:inherit}h1,h2,h3{font-family:var(--serif);font-weight:600;letter-spacing:-.01em;margin:0}
.top{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 22px;background:rgba(255,255,255,.85);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
.logo{display:flex;align-items:center;gap:10px;font:600 19px var(--serif);color:var(--ink)}.logo .em{width:34px;height:34px;border-radius:11px;background:linear-gradient(135deg,#f3dfa6,#c9972f);display:grid;place-items:center}.logo span.s{color:var(--gold)}
.tabs{display:flex;gap:4px}.tab{padding:8px 14px;border-radius:999px;border:1px solid transparent;background:transparent;color:var(--ink2);font-weight:500;cursor:pointer}.tab:hover{background:var(--sur2)}.tab.on{background:var(--ink);color:#fff}
.pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid var(--line);border-radius:999px;color:var(--ink2);font-size:13px;background:#fff}.pill b{color:var(--ink)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:12px;border:1px solid var(--line2);background:#fff;color:var(--ink);cursor:pointer;font-weight:600;transition:.15s;white-space:nowrap}.btn:hover{border-color:#c3c7d4;box-shadow:var(--sh1)}
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
.ws{display:grid;grid-template-columns:400px 1fr;gap:18px;align-items:start}@media(max-width:1000px){.ws{grid-template-columns:1fr}}
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
.banner{display:flex;gap:12px;align-items:center;padding:12px 16px;border-radius:14px;background:var(--goldl);border:1px solid #eadbb0;color:#5c4411;margin-bottom:16px}
</style></head>
<body>
<div class="top">
  <a class="logo" href="/"><span class="em"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18.5 7.5A8 8 0 1 0 19 15" stroke="#1a1407" stroke-width="3" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" fill="#1a1407"/></svg></span>ChatHelp <span class="s">Studio</span></a>
  <div class="tabs hidden" id="tabs"><button class="tab on" data-v="home">Übersicht</button><button class="tab" data-v="agents">Agenten</button><button class="tab" data-v="phone">Telefon</button><button class="tab" data-v="termine">Termine</button><button class="tab" data-v="bestellungen">Bestellungen</button><button class="tab" data-v="tokens">Tokens &amp; API</button></div>
  <div class="row hidden" id="acct"><span class="pill">Guthaben <b id="bal">–</b> Tokens</span><a class="btn s gold" href="/#preise" target="_blank">Tokens kaufen</a><span class="pill small" id="who"></span><button class="btn s" id="logout">Abmelden</button></div>
</div>

<!-- ===== LOGIN ===== -->
<div id="vLogin" class="login">
  <div>
    <span class="tag gold">Studio · kostenlos starten</span>
    <h1 style="margin-top:14px">Dein Agent arbeitet <span style="color:var(--gold)">heute noch.</span></h1>
    <p class="sub">Mit deiner E-Mail anmelden, Wissensbasis einfügen, Agent testen – für Inkasso, Kanzlei, Steuerberatung, Praxis, Shop und mehr. <b>300 Tokens geschenkt</b>, keine Zahlungsdaten.</p>
    <div style="margin-top:22px">
      <div class="perk"><i>1</i><div><b>Alle Agenten testen</b><span>Telefon-, Kanzlei-, Inkasso-, Steuer-, Support-Agent … mit deinen eigenen Fakten.</span></div></div>
      <div class="perk"><i>2</i><div><b>Telefon-Agent im Browser hören</b><span>Sprich mit deinem Agenten – bevor du eine Nummer buchst.</span></div></div>
      <div class="perk"><i>3</i><div><b>Website-Widget &amp; Transkripte</b><span>Eine Zeile Code, alle Gespräche im Studio. Freischaltung mit aktivem Plan.</span></div></div>
    </div>
  </div>
  <div class="card lbox">
    <div id="welcome" class="banner hidden">🎉 Zahlung erfolgreich! Melde dich mit der E-Mail aus dem Checkout an – dein Agent ist bereits freigeschaltet.</div>
    <h2>Anmelden</h2><p class="mute small" style="margin:6px 0 0">Wir senden dir einen 6-stelligen Code. Kein Passwort nötig.</p>
    <div id="stepEmail"><label>E-Mail</label><input type="email" id="lEmail" placeholder="du@firma.de" autocomplete="email"><button class="btn p" id="lSend" style="width:100%;margin-top:14px">Code senden</button></div>
    <div id="stepCode" class="hidden"><label>Code aus der E-Mail <span class="mute" id="lTo"></span></label><input type="text" id="lCode" class="code" inputmode="numeric" maxlength="6" placeholder="••••••" autocomplete="one-time-code"><button class="btn p" id="lVerify" style="width:100%;margin-top:14px">Anmelden</button><div class="row" style="justify-content:space-between;margin-top:10px"><button class="btn ghost s" id="lBack">andere E-Mail</button><button class="btn ghost s" id="lResend">Code erneut senden</button></div></div>
    <div id="lMsg"></div>
    <details style="margin-top:18px"><summary class="mute small" style="cursor:pointer">Mit API-Key anmelden (ch_live_…)</summary><label>API-Key</label><input type="text" id="lKey" placeholder="ch_live_…"><button class="btn" id="lKeyGo" style="width:100%;margin-top:10px">Weiter</button></details>
    <p class="mute small" style="margin-top:16px">Mit der Anmeldung akzeptierst du die <a href="/chat/?view=agb">AGB</a> und <a href="/chat/?view=datenschutz">Datenschutzhinweise</a>. Spam-Ordner prüfen, falls der Code nicht ankommt.</p>
  </div>
</div>

<!-- ===== APP ===== -->
<div id="vApp" class="wrap hidden">
  <!-- Übersicht -->
  <div id="pHome">
    <div id="trialBanner" class="banner hidden">🎁 Willkommen! <b>&nbsp;300 Tokens&nbsp;</b> sind auf deinem Konto. Wähle einen Agenten, füge deine Wissensbasis ein und teste ihn – jede Antwort kostet ca. 6 Tokens.</div>
    <div class="grid g4">
      <div class="card"><span class="tag">Guthaben</span><div class="num" style="margin-top:10px" id="hBal">–</div><div class="mute small">Tokens · verfallen nicht</div><a class="btn s gold" href="/#preise" target="_blank" style="margin-top:12px">Tokens kaufen</a></div>
      <div class="card"><span class="tag indigo">Aktive Agenten</span><div class="num" style="margin-top:10px" id="hPlans">0</div><div class="mute small">gemietet oder gekauft</div><button class="btn s" style="margin-top:12px" onclick="go('agents')">Agenten ansehen</button></div>
      <div class="card"><span class="tag teal">Gespräche (30 Tage)</span><div class="num" style="margin-top:10px" id="hConv">–</div><div class="mute small">Studio · Widget · Telefon</div></div>
      <div class="card"><span class="tag gold">Telefon &amp; Termine</span><div class="num" style="margin-top:10px;font-size:22px" id="hPhone">–</div><div class="mute small">Telefon-Agent Status</div><div class="row" style="margin-top:12px"><button class="btn s" onclick="go('phone')">Telefon</button><button class="btn s" onclick="go('termine')">Termine</button><button class="btn s" onclick="go('bestellungen')">Bestellungen</button></div></div>
    </div>
    <div class="grid g2" style="margin-top:16px">
      <div class="card"><h3 style="font-size:19px">Schnellstart</h3>
        <div class="perk" style="margin-top:12px"><i>1</i><div><b>Agent wählen</b><span>Unter „Agenten“ – jeder Agent kennt die Regeln seines Berufs.</span></div></div>
        <div class="perk"><i>2</i><div><b>Wissensbasis einfügen</b><span>Öffnungszeiten, Preise, Abläufe, FAQ, Textbausteine. Nur das nutzt der Agent.</span></div></div>
        <div class="perk"><i>3</i><div><b>Testen, dann freischalten</b><span>Im Studio chatten (Tokens). Widget &amp; Telefon mit gemietetem/gekauftem Agenten.</span></div></div>
        <div class="row" style="margin-top:10px"><button class="btn p" onclick="go('agents')">Agenten öffnen</button><a class="btn" href="/#berufe" target="_blank">Agenten-Katalog</a></div></div>
      <div class="card"><h3 style="font-size:19px">Deine Agenten</h3><div id="hList" class="mute small" style="margin-top:10px">–</div></div>
    </div>
    <div class="card" style="margin-top:16px"><h3 style="font-size:19px">Letzte Aktivität</h3><div style="overflow:auto;margin-top:10px"><table id="hRecent"><thead><tr><th>Zeit</th><th>Aufgabe</th><th>Bereich</th><th>Tokens</th></tr></thead><tbody></tbody></table></div></div>
  </div>

  <!-- Agenten -->
  <div id="pAgents" class="hidden">
    <div id="agList">
      <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px">Agenten</h2><p class="mute" style="margin:4px 0 0">Gemietete/gekaufte Agenten sind freigeschaltet (Widget, Telefon, Monats-Tokens). Alle anderen kannst du im Studio mit Tokens testen.</p></div><div class="chips" id="agFilter"></div></div>
      <div class="grid g3" id="agGrid"></div>
    </div>
    <div id="agWork" class="hidden">
      <div class="row" style="justify-content:space-between;margin-bottom:12px"><div class="row"><button class="btn s" onclick="showList()">← Alle Agenten</button><h2 id="wName" style="font-size:24px">Agent</h2><span class="tag" id="wStatus">–</span></div><div class="row"><a class="btn s gold" id="wRent" href="#" target="_blank">Agent mieten</a></div></div>
      <div class="ws">
        <div>
          <div class="card">
            <h3 style="font-size:17px">Einrichtung</h3><p class="mute small" style="margin:4px 0 0" id="wDesc"></p>
            <label>Unternehmen / Kanzlei / Praxis</label><input type="text" id="cCompany" placeholder="z. B. Kanzlei Berger &amp; Partner">
            <div class="grid g2" style="gap:10px"><div><label>Tonalität</label><select id="cTone"><option>freundlich-professionell</option><option>seriös-zugewandt</option><option>sachlich-respektvoll</option><option>locker-modern</option><option>einfühlsam-präzise</option><option>kompetent-ehrlich</option></select></div><div><label>Sprache</label><select id="cLang"><option value="de">Deutsch</option><option value="en">English</option><option value="tr">Türkçe</option><option value="fr">Français</option><option value="es">Español</option><option value="it">Italiano</option><option value="nl">Nederlands</option><option value="pl">Polski</option><option value="ru">Русский</option></select></div></div>
            <label>Wissensbasis <span class="mute">(einzige Faktenquelle des Agenten)</span></label><textarea id="cKb" placeholder="Öffnungszeiten, Adresse, Leistungen & Preise, Abläufe, Fristen, Textbausteine, Zuständigkeiten, Notfallnummern …"></textarea>
            <div class="row" style="justify-content:space-between;margin-top:10px"><button class="btn s" id="cSample">Beispiel einfügen</button><button class="btn p" id="cSave">Speichern</button></div>
            <div id="cMsg"></div>
            <div class="sec"><h3>Website-Widget</h3><p class="mute small" style="margin:4px 0 0">Eine Zeile Code vor &lt;/body&gt;. Besucher chatten mit diesem Agenten; Antworten kosten 6 Tokens von deinem Konto.</p>
              <div class="sw"><input type="checkbox" id="cWidgetOn"><div><b>Widget aktiv</b><div class="mute small">Freischaltung mit aktivem Plan</div></div></div>
              <label>Erlaubte Domain(s) <span class="mute">(optional, z. B. meine-firma.de)</span></label><input type="text" id="cDomain" placeholder="meine-firma.de, shop.meine-firma.de">
              <div class="snip" id="cSnip">Speichern, um den Code zu erhalten.</div></div>
            <div class="sec"><h3>Telefon</h3><p class="mute small" style="margin:4px 0 0">Dieser Agent kann Anrufe annehmen. Begrüßung, Stimme, Weiterleitung und Nummer verwaltest du unter <a href="#" onclick="go('phone');return false">Telefon</a>.</p></div>
          </div>
        </div>
        <div class="card chat">
          <div class="row" style="justify-content:space-between"><div><b id="wChatTitle">Test-Chat</b><div class="mute small" id="wCost">Antwort ≈ 6 Tokens · Studio-Test</div></div><div class="row"><button class="btn s" id="wVoice" title="Als Telefon testen: sprechen & hören">🎙️ Telefon-Test</button><button class="btn s" id="wClear">Neu</button></div></div>
          <div class="chips" id="wChips"></div>
          <div class="ms" id="wMs"></div>
          <form id="wForm"><textarea id="wIn" placeholder="Nachricht an den Agenten … (Enter = senden, Shift+Enter = Zeile)"></textarea><button class="mic hidden" type="button" id="wMic">🎙️</button><button class="btn p" type="submit" id="wSend">Senden</button></form>
        </div>
      </div>
    </div>
  </div>

  <!-- Telefon -->
  <div id="pPhone" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px">Telefon-Agent</h2><p class="mute" style="margin:4px 0 0">Anrufe annehmen, Fragen beantworten, Termine &amp; Rückrufe aufnehmen, weiterleiten. Jeder Anruf als Transkript.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-phone" target="_blank">Telefon-Agent mieten · 149 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:1fr 1fr">
      <div class="card">
        <label>Welcher Agent nimmt ab?</label><select id="phPlan"></select>
        <div class="sw"><input type="checkbox" id="phOn"><div><b>Telefon aktiv</b><div class="mute small" id="phOnNote">Freischaltung mit aktivem Plan</div></div></div>
        <label>Begrüßung</label><input type="text" id="phGreet" placeholder="Guten Tag, Kanzlei Berger &amp; Partner, hier ist der digitale Assistent. Wie kann ich Ihnen helfen?">
        <div class="grid g2" style="gap:10px"><div><label>Stimme / Sprache</label><select id="phVoice"><option value="de-DE">Deutsch · Vicki (weiblich, natürlich)</option><option value="de-DE-m">Deutsch · Daniel (männlich)</option><option value="en-US">English US · Joanna</option><option value="en-US-m">English US · Matthew</option><option value="en-GB">English UK · Amy</option><option value="en-GB-m">English UK · Arthur</option><option value="tr-TR">Türkçe · Filiz</option><option value="fr-FR">Français · Léa</option><option value="fr-FR-m">Français · Rémi</option><option value="es-ES">Español · Lucía</option><option value="es-ES-m">Español · Sergio</option><option value="it-IT">Italiano · Bianca</option><option value="it-IT-m">Italiano · Adriano</option><option value="nl-NL">Nederlands · Laura</option><option value="pl-PL">Polski · Ola</option><option value="ru-RU">Русский · Tatyana</option><option value="ru-RU-m">Русский · Maxim</option></select></div><div><label>Weiterleitung an (Mensch)</label><input type="tel" id="phFwd" placeholder="+49 221 123456"></div></div>
        <label>Erreichbarkeit des Teams (für Rückruf-Zusagen)</label><input type="text" id="phHours" placeholder="Mo–Fr 9–17 Uhr">
        <div class="row" style="justify-content:space-between;margin-top:14px"><span class="mute small">Transkripte &amp; Weiterleitung landen hier im Studio.</span><button class="btn p" id="phSave">Speichern</button></div><div id="phMsg"></div>
        <div class="sec"><h3>Deine Rufnummer</h3><div id="phNumber" class="mute" style="margin-top:6px">–</div>
          <p class="mute small">Nach Buchung richten wir deine Nummer (DE/AT/CH/TR/EU) innerhalb von 24 h ein – oder du leitest deine bestehende Nummer bei Besetzt/außerhalb der Zeiten um. Technisch versiert? Eigene Twilio-Nummer: Voice-Webhook (POST) auf diese URL:</p><div class="snip" id="phHook">Speichern, um die Webhook-URL zu erhalten.</div></div>
        <div class="sec"><h3>Im Browser testen</h3><p class="mute small" style="margin:4px 0 8px">Sprich mit dem Agenten wie am Telefon – Antwort wird vorgelesen (Chrome/Edge/Safari). Kostet 8 Tokens je Antwort.</p><button class="btn" id="phTest">🎙️ Telefon-Test starten</button></div>
      </div>
      <div class="card"><h3 style="font-size:17px">Anrufe &amp; Transkripte</h3><p class="mute small" style="margin:4px 0 0">Letzte 60 Anrufe dieses Agenten. Klick öffnet das Transkript.</p><div id="phCalls" style="margin-top:8px"><div class="mute small">Noch keine Anrufe.</div></div></div>
    </div>
  </div>

  <!-- Termine -->
  <div id="pTermine" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px">Terminbuchung</h2><p class="mute" style="margin:4px 0 0">Dein Kalender für Praxis, Salon, Studio oder Werkstatt. Der Agent bucht im Chat, am Telefon und über deine Buchungsseite – ohne Doppelbuchung, mit Bestätigung und Erinnerung.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-termine" target="_blank">Termin-Agent mieten · 99 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:1fr 1fr">
      <div>
        <div class="card">
          <div class="sw"><input type="checkbox" id="apOn"><div><b>Online-Terminbuchung aktiv</b><div class="mute small">Agenten erhalten die Werkzeuge „freie Zeiten“, „buchen“, „absagen“; Buchungsseite wird freigeschaltet</div></div></div>
          <label>Betrieb / Praxis / Salon</label><input type="text" id="apCompany" placeholder="z. B. Salon Luna · Friseur & Kosmetik">
          <label>Adresse (für Bestätigung &amp; Kalenderdatei)</label><input type="text" id="apAddress" placeholder="Straße, PLZ Ort">
          <div class="grid g2" style="gap:10px"><div><label>Benachrichtigung an</label><input type="email" id="apNotify" placeholder="termine@deinbetrieb.de (leer = Konto-E-Mail)"></div><div><label>Zeitzone</label><select id="apTz"><option>Europe/Berlin</option><option>Europe/Vienna</option><option>Europe/Zurich</option><option>Europe/Istanbul</option><option>Europe/Paris</option><option>Europe/London</option></select></div></div>
          <label>Zusatz in der Bestätigung (optional)</label><input type="text" id="apConfirm" placeholder="z. B. Bitte 5 Minuten früher da sein. Absage bis 24 h vorher kostenlos.">
          <div class="grid g4" style="gap:10px"><div><label>Taktung (Min)</label><input type="text" id="apSlot" value="30"></div><div><label>Puffer (Min)</label><input type="text" id="apBuffer" value="0"></div><div><label>Vorlauf (Min)</label><input type="text" id="apLead" value="120"></div><div><label>Horizont (Tage)</label><input type="text" id="apMax" value="60"></div></div>
          <div class="sec"><h3>Öffnungszeiten</h3><div id="apHours"></div></div>
          <div class="sec"><h3>Leistungen</h3><p class="mute small" style="margin:4px 0 8px">Name, Dauer in Minuten, Preis in Euro. Dauer bestimmt die Slot-Länge.</p><div id="apSvcs"></div><button class="btn s" id="apAddSvc" style="margin-top:8px">+ Leistung</button></div>
          <div class="row" style="justify-content:space-between;margin-top:16px"><span class="mute small">Änderungen wirken sofort für Agent &amp; Buchungsseite.</span><button class="btn p" id="apSave">Speichern</button></div><div id="apMsg"></div>
          <div class="sec"><h3>Deine Buchungsseite</h3><div class="snip" id="apUrl">–</div><div class="row" style="margin-top:8px"><a class="btn s" id="apOpen" href="#" target="_blank">Seite öffnen</a><button class="btn s" id="apCopy">Link kopieren</button><span class="mute small">Verlinke sie auf deiner Website, in Google Business, Instagram oder im E-Mail-Footer.</span></div></div>
        </div>
      </div>
      <div>
        <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px">Termine (nächste 30 Tage)</h3><button class="btn s" id="apReload">↻</button></div><div id="apList" class="mute small" style="margin-top:8px">–</div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">Termin manuell eintragen</h3>
          <div class="grid g2" style="gap:10px"><div><label>Leistung</label><select id="mbSvc"></select></div><div><label>Datum</label><input type="date" id="mbDate"></div></div>
          <label>Uhrzeit</label><select id="mbTime"><option value="">— Datum wählen —</option></select>
          <div class="grid g2" style="gap:10px"><div><label>Name</label><input type="text" id="mbName"></div><div><label>Telefon</label><input type="tel" id="mbPhone"></div></div>
          <label>E-Mail (Bestätigung)</label><input type="email" id="mbEmail"><label>Notiz</label><input type="text" id="mbNote">
          <div class="row" style="justify-content:flex-end;margin-top:12px"><button class="btn p" id="mbBook">Eintragen</button></div><div id="mbMsg"></div></div>
      </div>
    </div>
  </div>

  <!-- Bestellungen (Restaurant / Pizzeria / Lieferdienst) -->
  <div id="pBestellungen" class="hidden">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><h2 style="font-size:26px">Bestellannahme</h2><p class="mute" style="margin:4px 0 0">Dein Bestell-Agent nimmt per WhatsApp, Website-Chat und Telefon Bestellungen auf: Speisekarte mit Größen &amp; Extras, Adressprüfung mit Liefergebiet, Bezahlart, Zeit. Die Küche sieht alles live auf dem Board, der Fahrer bekommt Route und Kundendaten aufs Handy, der Kunde die Bestätigung.</p></div><a class="btn gold" href="/chat/tokens-checkout.php?plan=agent-bestellung" target="_blank">Bestell-Agent mieten · 129 €/Monat</a></div>
    <div class="ws" style="grid-template-columns:1fr 1fr">
      <div>
        <div class="card">
          <div class="sw"><input type="checkbox" id="odOn"><div><b>Bestellannahme aktiv</b><div class="mute small">Agenten erhalten die Werkzeuge „Speisekarte“, „Adresse prüfen“, „Bestellung anlegen“, „Status“, „Stornieren“</div></div></div>
          <label>Restaurant</label><input type="text" id="odName" placeholder="z. B. Pizzeria Napoli">
          <label>Adresse des Restaurants (für Entfernung &amp; Route)</label><input type="text" id="odAddress" placeholder="Straße Nr., PLZ Ort">
          <div class="grid g2" style="gap:10px"><div><label>Telefon (für Kunden)</label><input type="tel" id="odPhone" placeholder="0221 …"></div><div><label>Bestell-Mails an</label><input type="email" id="odNotify" placeholder="kueche@… (leer = Konto-E-Mail)"></div></div>
          <div class="grid g2" style="gap:10px"><div class="sw" style="margin-top:12px"><input type="checkbox" id="odDelivery"><div><b>Lieferung</b></div></div><div class="sw" style="margin-top:12px"><input type="checkbox" id="odPickup"><div><b>Abholung</b></div></div></div>
          <div class="grid g4" style="gap:10px"><div><label>Mindestbestellwert €</label><input type="text" id="odMin" value="15,00"></div><div><label>Liefergebühr € (Standard)</label><input type="text" id="odFee" value="2,50"></div><div><label>Lieferzeit Min</label><input type="text" id="odEtaD" value="40"></div><div><label>Abholzeit Min</label><input type="text" id="odEtaP" value="20"></div></div>
          <label>Zahlarten</label><div class="row"><label class="chip" style="margin:0"><input type="checkbox" class="odPay" value="bar" checked> bar</label><label class="chip" style="margin:0"><input type="checkbox" class="odPay" value="karte" checked> Karte beim Fahrer</label><label class="chip" style="margin:0"><input type="checkbox" class="odPay" value="paypal"> PayPal-Link</label><label class="chip" style="margin:0"><input type="checkbox" class="odPay" value="online"> Online bezahlt</label></div>
          <div class="grid g2" style="gap:10px"><div><label>Zeitzone</label><select id="odTz"><option>Europe/Berlin</option><option>Europe/Vienna</option><option>Europe/Zurich</option><option>Europe/Istanbul</option><option>Europe/Paris</option><option>Europe/London</option></select></div></div>
          <div class="sec"><h3>Öffnungszeiten (Bestellannahme)</h3><div id="odHours"></div></div>
          <div class="sec"><h3>Liefergebiete</h3><p class="mute small" style="margin:4px 0 8px">Postleitzahlen je Zone (Komma-getrennt), Liefergebühr und Mindestbestellwert. Adressen außerhalb → der Agent bietet Abholung an.</p><div id="odZones"></div><button class="btn s" id="odAddZone" style="margin-top:8px">+ Zone</button></div>
          <div class="row" style="justify-content:space-between;margin-top:16px"><span class="mute small">Speisekarte, Fahrer und WhatsApp werden mitgespeichert.</span><button class="btn p" id="odSave">Speichern</button></div><div id="odMsg"></div>
        </div>
        <div class="card" style="margin-top:16px"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px">Speisekarte</h3><button class="btn s" id="odDemoMenu">Beispielkarte laden</button></div>
          <p class="mute small" style="margin:4px 0 8px">Kategorie · Name · Beschreibung · Preis € · Optionen · Hinweise. Optionen-Format: <code>Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50</code></p>
          <div id="odMenu"></div><button class="btn s" id="odAddItem" style="margin-top:8px">+ Artikel</button></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">Fahrer</h3><p class="mute small" style="margin:4px 0 8px">Jeder Fahrer bekommt einen persönlichen Link (Handy-Seite mit Navigation, Anruf, „Zugestellt“). Bei „Unterwegs“ erhält er die Fahrt per WhatsApp/SMS.</p><div id="odDrivers"></div><button class="btn s" id="odAddDriver" style="margin-top:8px">+ Fahrer</button></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">WhatsApp-Anbindung</h3>
          <label>Anbieter</label><select id="odWaProv"><option value="meta">Meta WhatsApp Business (Cloud API)</option><option value="twilio">Twilio WhatsApp</option></select>
          <div id="odWaMeta"><div class="grid g2" style="gap:10px"><div><label>Phone Number ID</label><input type="text" id="odWaPhoneId" placeholder="1234567890"></div><div><label>Access Token</label><input type="text" id="odWaToken" placeholder="EAAG… (wird verschlüsselt gespeichert)"></div></div><label>Verify-Token (frei wählbar)</label><input type="text" id="odWaVerify" placeholder="z. B. napoli-2026"></div>
          <div id="odWaTwilio" class="hidden"><label>WhatsApp-Nummer (Twilio Sender)</label><input type="text" id="odWaNumber" placeholder="+4915…"><p class="mute small">Account SID / Auth Token hinterlegt ChatHelp serverseitig (support@chat-help.com).</p></div>
          <label>Webhook-URL (bei Meta als Callback bzw. bei Twilio als „When a message comes in“)</label><div class="snip" id="odWaHook">Speichern, um die URL zu erhalten.</div><div class="row" style="margin-top:8px"><button class="btn s" id="odWaCopy">URL kopieren</button><span class="mute small">Meta: developers.facebook.com → App → WhatsApp → Konfiguration → Webhook; Feld „messages“ abonnieren.</span></div>
        </div>
      </div>
      <div>
        <div class="card"><div class="row" style="justify-content:space-between"><h3 style="font-size:17px">Live-Bestellungen</h3><div class="row"><label class="small mute" style="margin:0"><input type="checkbox" id="odAll"> auch erledigte (7 Tage)</label><button class="btn s" id="odReload">↻</button></div></div><div id="odList" class="mute small" style="margin-top:8px">–</div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">Küchen-Board (Tablet)</h3><p class="mute small" style="margin:4px 0 8px">Großansicht ohne Login für Küche und Theke: neue Bestellungen mit Ton, Status per Tipp, Fahrer zuweisen.</p><div class="snip" id="odBoard">Speichern, um den Link zu erhalten.</div><div class="row" style="margin-top:8px"><a class="btn s" id="odBoardOpen" href="#" target="_blank">Board öffnen</a><button class="btn s" id="odBoardCopy">Link kopieren</button></div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">Adresse testen</h3><p class="mute small" style="margin:4px 0 8px">So prüft der Agent Lieferadressen: Zone, Gebühr, Entfernung.</p><div class="row"><input type="text" id="odChk" placeholder="Severinstraße 12, 50678 Köln" style="flex:1"><button class="btn s" id="odChkGo">Prüfen</button></div><div id="odChkRes" class="small" style="margin-top:8px"></div></div>
        <div class="card" style="margin-top:16px"><h3 style="font-size:17px">So läuft eine Bestellung</h3><ol class="mute small" style="margin:8px 0 0 18px;padding:0;line-height:1.7"><li>Kunde schreibt per WhatsApp / Chat oder ruft an → Agent fragt Größe, Extras, Lieferung/Abholung</li><li>Adresse → PLZ-Zone + Entfernung → Gebühr &amp; Mindestbestellwert</li><li>Zusammenfassung → Kunde bestätigt → Bestellcode + Zeit</li><li>Küche: Board/E-Mail · Status „Unterwegs“ → Fahrer bekommt Route · Kunde bekommt WhatsApp-Status</li></ol></div>
      </div>
    </div>
  </div>

  <!-- Tokens & API -->
  <div id="pTokens" class="hidden">
    <div class="grid g3">
      <div class="card"><span class="tag">Guthaben</span><div class="num" style="margin-top:10px" id="tBal">–</div><div class="mute small">Chat 6 · Telefon 8 · Widget 6 Tokens je Antwort</div><div class="row" style="margin-top:12px"><a class="btn s gold" href="/chat/tokens-checkout.php?pack=10k" target="_blank">10.000 · 49 €</a><a class="btn s" href="/chat/tokens-checkout.php?pack=50k" target="_blank">50.000 · 199 €</a><a class="btn s" href="/#preise" target="_blank">mehr</a></div></div>
      <div class="card"><span class="tag indigo">API-Key</span><div class="snip" id="tKey">–</div><div class="mute small" style="margin-top:8px">Bearer-Key für die REST-API. Bei Verdacht auf Missbrauch: abmelden und neu anmelden erzeugt einen neuen Key (alte bleiben gültig – Deaktivierung auf Anfrage).</div></div>
      <div class="card"><span class="tag teal">Endpunkte</span><div class="snip" style="margin-top:10px">POST /chat/tokens-api.php/v1/agent
{"plan":"agent-kanzlei","messages":[{"role":"user","content":"…"}]}

GET  /v1/me · GET /v1/sectors · POST /v1/generate
POST /v1/translate · POST /v1/subtitles
GET|POST /v1/agent/config?plan=… · GET /v1/phone/calls
GET|POST /v1/appt/settings · GET /v1/appt/bookings
GET|POST /v1/order/settings · GET /v1/order/orders</div></div>
    </div>
    <div class="card" style="margin-top:16px"><h3 style="font-size:19px">Verbrauch</h3><div style="overflow:auto;margin-top:10px"><table id="tRecent"><thead><tr><th>Zeit</th><th>Aufgabe</th><th>Bereich</th><th>Tokens</th></tr></thead><tbody></tbody></table></div></div>
  </div>
</div>

<script>
const API='/chat/tokens-api.php/v1'; const $=s=>document.querySelector(s);
let KEY=localStorage.getItem('ch_api_key')||'', ME=null, PLANS=[], CUR=null, HIST=[], CFG=null;
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));const de=n=>Number(n||0).toLocaleString('de-DE');
async function api(path,opts={}){const u=API+path+(path.includes('?')?'&':'?')+'_='+Date.now();const r=await fetch(u,{...opts,cache:'no-store',headers:{'Content-Type':'application/json',...(KEY?{'Authorization':'Bearer '+KEY}:{}),...(opts.headers||{})}});const j=await r.json().catch(()=>({}));if(!r.ok){const e=new Error(j.message||j.error||('HTTP '+r.status));e.data=j;e.status=r.status;throw e;}return j;}
function msg(el,text,kind){el.innerHTML=text?`<div class="msg ${kind||'info'}">${text}</div>`:'';}
const ICON={inkasso:'€',kanzlei:'§',steuer:'%',versicherung:'☂',hausverwaltung:'⌂',automotive:'⚙',praxis:'+',service:'☎',ecommerce:'◎',marketing:'✎',finanzen:'Σ',immobilien:'⌂',gastro:'✦',hr:'☺',logistik:'⇄',bildung:'✎',creator:'▶',medien:'▶',recht:'§'};
const SAMPLE={default:"Unternehmen: Muster GmbH, Musterstraße 1, 10115 Berlin. Telefon 030 123456, info@muster.de.\nÖffnungszeiten: Mo–Fr 9–17 Uhr.\nLeistungen & Preise: …\nAbläufe: …\nHäufige Fragen: …\nZuständigkeiten / Weiterleitung: …\nNotfall: …",
 'agent-kanzlei':"Kanzlei: Muster & Partner Rechtsanwälte, Musterstraße 1, 10115 Berlin, Tel. 030 123456, kanzlei@muster.de. Mo–Do 8:30–18, Fr 8:30–15.\nRechtsgebiete: Arbeitsrecht (RA Muster), Mietrecht (RAin Beispiel), Verkehrsrecht. Nicht: Steuerrecht, Patentrecht.\nErstberatung: 30 Min 150 € netto / 60 Min 190 € netto (§ 34 RVG), online oder vor Ort. Stundensatz 250 € netto oder RVG. Rechtsschutz: Deckungsanfrage kostenlos.\nTermine: Erstberatung binnen 3 Werktagen; Fristsachen am selben/nächsten Werktag. Unterlagen: Ausweis, Vertrag/Bescheid/Urteil, Korrespondenz, RS-Police.\nFristen (Vorprüfung, Anwalt bestätigt): Kündigungsschutzklage 3 Wochen (§ 4 KSchG); Berufung 1 Monat (§ 517 ZPO); Widerspruch 1 Monat (§ 70 VwGO); Bußgeld-Einspruch 2 Wochen (§ 67 OWiG).\nKonfliktcheck: Gegenseite immer erfragen.",
 'agent-inkasso':"Unternehmen: Muster Inkasso GmbH, registrierter Inkassodienstleister (§ 10 RDG), Reg.-Nr. …, Registrierungsbehörde ….\nBank: IBAN …, Verwendungszweck: Aktenzeichen.\nSachbearbeitung: Mo–Fr 8–17 Uhr, Tel. …, service@….\nRatenzahlung: ab 50 €/Monat, max. 24 Raten, Verfallklausel bei 2 Raten Rückstand. Vergleich: Erlass bis 30 % der Kosten bei Einmalzahlung binnen 14 Tagen (Sachbearbeiter entscheidet).\nKosten B2B: Verzugszinsen 9 Pp. über Basiszins (aktuell …%), Pauschale 40 € (§ 288 Abs. 5 BGB). Verbraucher: 5 Pp. über Basiszins.\nInkassokosten: 0,9 Gebühr Nr. 2300 VV RVG nach Streitwert: bis 500 € = 49 €, bis 1.000 € = 88 €, bis 2.000 € = 166 €, bis 3.000 € = 222 €, bis 5.000 € = 334 €; Auslagenpauschale 20 €.",
 'agent-steuer':"Kanzlei: Steuerkanzlei Muster, Musterstraße 1, 80335 München, Tel. 089 123456, mandanten@muster.de, Mo–Fr 8–17 Uhr.\nUpload: DATEV Unternehmen online oder Upload-Link per E-Mail. Keine Belege per WhatsApp.\nFristen: USt-VA monatlich bis 10. (mit Dauerfristverlängerung bis 10. des übernächsten Monats); Lohnsteuer bis 10.; ESt-Vorauszahlungen 10.03./10.06./10.09./10.12.; GewSt-Vorauszahlungen 15.02./15.05./15.08./15.11.; Jahresabschluss/Offenlegung ….\nHonorar: nach StBVV; Buchhaltung monatlich pauschal lt. Vertrag.\nAnsprechpartner: ….",
 'agent-phone':"Unternehmen: …\nÖffnungszeiten: …\nLeistungen & Preise: …\nTermine: Dauer, Vorlauf, Unterlagen …\nAnfahrt/Parken: …\nWeiterleitung an Mensch: wann, an wen …\nNotfall: …",
 'agent-bestellung':"Restaurant: Pizzeria Muster, Musterstraße 1, 50667 Köln, Tel. 0221 123456. Seit 1998, Holzofen.\nÖffnungszeiten & Liefergebiete: siehe Tab „Bestellungen“ (Speisekarte, Zonen, Zahlarten werden dort gepflegt – der Agent nutzt sie automatisch).\nAllergene: Weizen in allen Pizzen (glutenfrei +2 € auf Vorbestellung), Laktose in Käse; vollständige Liste liegt im Restaurant aus.\nAktionen: Dienstag Pizza-Tag −2 € (nur Abholung). Abholung −10 %.\nReklamation: innerhalb 30 Min nach Lieferung anrufen – Ersatz oder Gutschrift nach Rücksprache.\nSonstiges: Trinkgeld freiwillig; Bestellungen über 60 € bitte 30 Min Vorlauf.",
 'agent-termine':"Betrieb: Salon Muster, Musterstraße 1, 20357 Hamburg, Tel. 040 123456.\nTeam: … (Schnitt & Farbe), … (Kosmetik).\nLeistungen & Öffnungszeiten: siehe Tab „Termine“ (werden dort gepflegt – der Agent nutzt sie automatisch).\nRegeln: Absage bis 24 h vorher kostenlos, danach 50 %. Verspätung über 15 Min → Termin kann verkürzt werden.\nParken/Anfahrt: …\nProdukte: …"};
const CHIPS={default:['Was kannst du für uns übernehmen?','Fasse unsere Öffnungszeiten und Leistungen zusammen','Ein Kunde beschwert sich über Wartezeit – antworte freundlich'],
 'agent-kanzlei':['Ich habe gestern eine Kündigung bekommen','Was kostet eine Erstberatung?','Intern: Aktennotiz für zugestelltes Urteil vom 10.10.2026'],
 'agent-inkasso':['Forderungsaufstellung: Rechnung 2026-118 über 2.450 € brutto, fällig seit 2. Juli, B2B','Schreib die 1. Mahnung','Der Schuldner sagt, die Forderung sei verjährt (Rechnung aus 2022)'],
 'agent-steuer':['Welche Belege fehlen für meinen Abschluss?','Erkläre den Vorauszahlungsbescheid','Erinnerungs-E-Mail an Mandanten mit fehlenden Kontoauszügen'],
 'agent-versicherung':['Ich hatte gestern einen Auffahrunfall','Wasserschaden in der Küche – was tun?','Ich möchte meine Haftpflicht kündigen'],
 'agent-hausverwaltung':['Wasser tropft aus der Decke','Warum ist meine Nebenkostenabrechnung so hoch?','Wann ist die Eigentümerversammlung?'],
 'agent-autohaus':['Termin für Inspektion und HU, VW Golf, 84.000 km','Gelbe Motorkontrollleuchte – kann ich noch fahren?','Probefahrt am Samstag?'],
 'agent-phone':['Wann haben Sie geöffnet?','Ich hätte gern einen Termin','Ich möchte mit einem Mitarbeiter sprechen'],
 'agent-support':['Wo ist meine Bestellung?','Wie kann ich etwas zurückschicken?','Versandkosten nach Österreich?'],
 'agent-sales':['Was kostet eine Anlage für ein Einfamilienhaus?','Schreib ein Follow-up für Kunde Müller','CRM-Notiz aus unserem Gespräch'],
 'agent-backoffice':['Zahlungserinnerung für Rechnung 2026-0142 über 1.840 €','Welche Fristen habe ich im November?','Rechnungstext für 12 Stunden Montage'],
 'agent-website':['Erstelle die Startseite','Schreibe die Über-uns-Seite','SEO-Title und Description'],
 'agent-termine':['Ich hätte gern einen Termin, am liebsten Donnerstag nachmittags','Was habt ihr morgen noch frei?','Ich muss meinen Termin absagen'],
 'agent-bestellung':['Eine große Pizza Salami und eine Cola, bitte liefern an Severinstraße 12, 50678 Köln','Was habt ihr Vegetarisches?','Wo bleibt meine Bestellung?'],
 'agent-content':['Redaktionsplan für nächste Woche','3 Instagram-Posts zu unserem Angebot','Caption + Hashtags für ein Behind-the-Scenes-Reel']};

/* ===== Login ===== */
(function(){const q=new URLSearchParams(location.search);if(q.get('welcome')){$('#welcome').classList.remove('hidden');}})();
let lEmail='';
$('#lSend').onclick=async()=>{lEmail=$('#lEmail').value.trim();if(!/.+@.+\..+/.test(lEmail)){msg($('#lMsg'),'Bitte eine gültige E-Mail eingeben.','err');return;}$('#lSend').disabled=true;msg($('#lMsg'),'Code wird gesendet …');
  try{const j=await api('/auth/request',{method:'POST',body:JSON.stringify({email:lEmail,lang:'de'})});$('#stepEmail').classList.add('hidden');$('#stepCode').classList.remove('hidden');$('#lTo').textContent='→ '+lEmail;msg($('#lMsg'),j.sent?'Code gesendet – bitte E-Mail (und Spam-Ordner) prüfen.':'Code erzeugt, aber der Versand ist fehlgeschlagen – bitte erneut versuchen oder support@chat-help.com.',j.sent?'ok':'err');$('#lCode').focus();}
  catch(e){msg($('#lMsg'),e.message,'err');}$('#lSend').disabled=false;};
$('#lEmail').addEventListener('keydown',e=>{if(e.key==='Enter')$('#lSend').click();});
$('#lCode').addEventListener('input',()=>{if($('#lCode').value.replace(/\D/g,'').length===6)$('#lVerify').click();});
$('#lVerify').onclick=async()=>{const code=$('#lCode').value.replace(/\D/g,'');if(code.length!==6)return;$('#lVerify').disabled=true;msg($('#lMsg'),'Prüfe …');
  try{const j=await api('/auth/verify',{method:'POST',body:JSON.stringify({email:lEmail,code})});KEY=j.key;localStorage.setItem('ch_api_key',KEY);localStorage.setItem('ch_email',j.email);if(j.trial_credited)sessionStorage.setItem('ch_trial','1');await boot();}
  catch(e){msg($('#lMsg'),e.message,'err');$('#lCode').value='';}$('#lVerify').disabled=false;};
$('#lBack').onclick=()=>{$('#stepCode').classList.add('hidden');$('#stepEmail').classList.remove('hidden');msg($('#lMsg'),'');};
$('#lResend').onclick=()=>{$('#lEmail').value=lEmail;$('#lSend').click();};
$('#lKeyGo').onclick=async()=>{const k=$('#lKey').value.trim();if(!/^ch_(live|test)_[a-f0-9]{48}$/.test(k)){msg($('#lMsg'),'Key-Format: ch_live_… (56 Zeichen)','err');return;}KEY=k;localStorage.setItem('ch_api_key',KEY);await boot();};
$('#logout').onclick=()=>{localStorage.removeItem('ch_api_key');location.href=location.pathname;};

/* ===== Boot ===== */
async function boot(){if(!KEY){show('login');return;}
  try{const [me,sec]=await Promise.all([api('/me'),api('/sectors')]);ME=me;PLANS=sec.plans||[];}
  catch(e){if(e.status===401){localStorage.removeItem('ch_api_key');KEY='';show('login');msg($('#lMsg'),'Sitzung ungültig – bitte neu anmelden.','err');return;}alert('Fehler: '+e.message);return;}
  show('app');$('#who').textContent=ME.email;renderHome();renderAgents();renderPhone();renderTokens();
  const q=new URLSearchParams(location.search);const w=q.get('welcome');if(w&&PLANS.find(p=>p.id===w)){go('agents');openAgent(w);}
  if(sessionStorage.getItem('ch_trial')){$('#trialBanner').classList.remove('hidden');sessionStorage.removeItem('ch_trial');}}
function show(v){$('#vLogin').classList.toggle('hidden',v!=='login');$('#vApp').classList.toggle('hidden',v!=='app');$('#tabs').classList.toggle('hidden',v!=='app');$('#acct').classList.toggle('hidden',v!=='app');}
function go(v){document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t.dataset.v===v));['Home','Agents','Phone','Termine','Bestellungen','Tokens'].forEach(p=>$('#p'+p).classList.toggle('hidden',p.toLowerCase()!==v));if(v==='termine'&&!AP)loadAppt();if(v==='bestellungen'&&!OS)loadOrd();window.scrollTo({top:0,behavior:'smooth'});}
document.querySelectorAll('.tab').forEach(t=>t.onclick=()=>go(t.dataset.v));
async function refreshMe(){try{ME=await api('/me');$('#bal').textContent=de(ME.balance);$('#hBal').textContent=de(ME.balance);$('#tBal').textContent=de(ME.balance);}catch(e){}}
const active=id=>!!(ME.plans||[]).find(p=>p.id===id&&p.active);
const planOf=id=>PLANS.find(p=>p.id===id);
const isAgent=p=>p.kind==='agent';

/* ===== Übersicht ===== */
function renderHome(){$('#bal').textContent=de(ME.balance);$('#hBal').textContent=de(ME.balance);
  const act=(ME.plans||[]).filter(p=>p.active);$('#hPlans').textContent=act.length;
  $('#hConv').textContent=de((ME.recent||[]).length)+'+';
  const ph=(ME.configs||[]).find(c=>+c.phone_on);$('#hPhone').textContent=ph?(ph.phone_number||'aktiv · Nummer folgt'):'nicht eingerichtet';
  $('#hList').innerHTML=act.length?act.map(p=>`<div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)"><span><b>${esc(p.name)}</b><br><span class="mute small">${p.kind==='buy'?'gekauft · dauerhaft':'gemietet · bis '+(p.period_end?new Date(p.period_end).toLocaleDateString('de-DE'):'–')} · ${de(p.tokens_month)} Tokens/Monat</span></span><button class="btn s" onclick="go('agents');openAgent('${p.id}')">Öffnen</button></div>`).join(''):'<div class="mute">Noch kein Agent gemietet oder gekauft. Teste jeden Agenten im Studio mit deinem Guthaben – oder <a href="/#berufe" target="_blank">wähle einen im Katalog</a>.</div>';
  const tb=$('#hRecent tbody');tb.innerHTML=(ME.recent||[]).slice(0,10).map(r=>`<tr><td>${new Date(r.created_at).toLocaleString('de-DE')}</td><td>${esc(r.task)}</td><td>${esc(r.sector||'')}</td><td>${de(r.tokens)}</td></tr>`).join('')||'<tr><td colspan="4" class="mute">Noch keine Aktivität.</td></tr>';}

/* ===== Agenten ===== */
let filter='all';
function renderAgents(){const f=$('#agFilter');const F=[['all','Alle'],['mine','Meine'],['beruf','Berufsgruppen'],['agent','Agenten'],['abo','Content-Pakete']];f.innerHTML=F.map(([k,l])=>`<span class="chip${filter===k?' on':''}" style="${filter===k?'background:var(--ink);color:#fff;border-color:var(--ink)':''}" onclick="filter='${k}';renderAgents()">${l}</span>`).join('');
  const BERUF=['inkasso','kanzlei','steuer','versicherung','hausverwaltung','automotive','praxis','service'];
  let list=PLANS.filter(p=>p.kind!=='buy');
  if(filter==='mine')list=list.filter(p=>active(p.id));if(filter==='beruf')list=list.filter(p=>BERUF.includes(p.sector)&&p.kind==='agent');if(filter==='agent')list=list.filter(p=>p.kind==='agent');if(filter==='abo')list=list.filter(p=>p.kind==='abo');
  list.sort((a,b)=>(active(b.id)-active(a.id))||(BERUF.includes(b.sector)-BERUF.includes(a.sector)));
  $('#agGrid').innerHTML=list.map(p=>`<div class="card ag" onclick="openAgent('${p.id}')"><div class="row" style="justify-content:space-between"><div class="ic">${ICON[p.sector]||'✦'}</div>${active(p.id)?'<span class="tag ok">Aktiv</span>':'<span class="tag">Testen mit Tokens</span>'}</div><h3>${esc(p.name)}</h3><p>${esc(p.desc)}</p><div class="pr">${p.eur_month.toLocaleString('de-DE')} €/Monat · ${de(p.tokens_month)} Tokens${BERUF.includes(p.sector)?' · Telefon möglich':''}</div></div>`).join('');}
function showList(){$('#agWork').classList.add('hidden');$('#agList').classList.remove('hidden');CUR=null;}
async function openAgent(id){const p=planOf(id);if(!p)return;CUR=p;HIST=[];$('#agList').classList.add('hidden');$('#agWork').classList.remove('hidden');go('agents');
  $('#wName').textContent=p.name;$('#wDesc').textContent=p.desc;$('#wStatus').textContent=active(id)?'Aktiv · freigeschaltet':'Studio-Test · '+ME.pricing.agent_turn+' Tokens je Antwort';$('#wStatus').className='tag '+(active(id)?'ok':'');
  $('#wRent').href='/chat/tokens-checkout.php?plan='+id+'&_='+Date.now().toString(36);$('#wRent').classList.toggle('hidden',active(id));
  $('#wMs').innerHTML='';$('#wChips').innerHTML=(CHIPS[id]||CHIPS.default).map(c=>`<span class="chip" onclick="send(${JSON.stringify(c).replace(/"/g,'&quot;')})">${esc(c)}</span>`).join('');
  $('#wCost').textContent=(active(id)?'Inklusive im Plan · ':'Studio-Test · ')+ME.pricing.agent_turn+' Tokens je Antwort';
  try{const j=await api('/agent/config?plan='+id);CFG=j.config;$('#cCompany').value=CFG.company||'';$('#cTone').value=CFG.tone||'freundlich-professionell';$('#cLang').value=CFG.lang||'de';$('#cKb').value=CFG.kb||'';$('#cWidgetOn').checked=!!+CFG.widget_on;$('#cDomain').value=CFG.widget_domain||'';
    $('#cSnip').textContent=j.widget_snippet||'Speichern, um den Code zu erhalten.';msg($('#cMsg'),j.plan_active?'':'Wissensbasis und Test sind sofort möglich. Widget & Telefon schaltest du mit gemietetem/gekauftem Agenten frei.','info');
    sys(CFG.kb?'Wissensbasis geladen ('+de(CFG.kb.length)+' Zeichen). Stell dem Agenten eine Frage.':'Noch keine Wissensbasis – füge links deine Fakten ein (oder „Beispiel einfügen“) und speichere. Dann testen.');}catch(e){msg($('#cMsg'),e.message,'err');}}
$('#cSample').onclick=()=>{if($('#cKb').value.trim()&&!confirm('Vorhandene Wissensbasis ersetzen?'))return;$('#cKb').value=SAMPLE[CUR.id]||SAMPLE.default;};
$('#cSave').onclick=async()=>{if(!CUR)return;$('#cSave').disabled=true;try{const j=await api('/agent/config?plan='+CUR.id,{method:'POST',body:JSON.stringify({company:$('#cCompany').value,tone:$('#cTone').value,lang:$('#cLang').value,kb:$('#cKb').value,widget_on:$('#cWidgetOn').checked?1:0,widget_domain:$('#cDomain').value})});CFG=j.config;$('#cSnip').textContent=j.widget_snippet;$('#cWidgetOn').checked=!!+CFG.widget_on;msg($('#cMsg'),j.note||'Gespeichert ✓ – der Agent nutzt die Wissensbasis sofort.',j.note?'info':'ok');}catch(e){msg($('#cMsg'),e.message,'err');}$('#cSave').disabled=false;};
function add(role,text,meta){const d=document.createElement('div');d.className='m '+role;d.textContent=text;if(meta){const s=document.createElement('span');s.className='meta';s.textContent=meta;d.appendChild(s);}$('#wMs').appendChild(d);$('#wMs').scrollTop=$('#wMs').scrollHeight;return d;}
function sys(t){add('sys',t);}
let phoneMode=false,micOn=false,rec=null;const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
async function send(text){text=(text||$('#wIn').value).trim();if(!text||!CUR)return;$('#wIn').value='';add('u',text);HIST.push({role:'user',content:text});const t=add('a','…');$('#wSend').disabled=true;
  try{const j=await api('/agent',{method:'POST',body:JSON.stringify({plan:CUR.id,messages:HIST.slice(-16),lang:$('#cLang').value||'de',channel:phoneMode?'phone':'chat'})});
    const a=String(j.result||'');t.textContent=a;HIST.push({role:'assistant',content:a});const meta=document.createElement('span');meta.className='meta';meta.textContent=`${j.tokens_used} Tokens · Guthaben ${de(j.balance)} · ${j.access==='plan'?'Plan':'Tokens'}`;t.appendChild(meta);$('#bal').textContent=de(j.balance);
    if(phoneMode)speak(a.replace(/\[(WEITERLEITEN|ENDE)\]/g,''),()=>{if(micOn)listen();});}
  catch(e){t.textContent='⚠ '+(e.data&&e.data.message?e.data.message:e.message);if(e.status===402){const b=document.createElement('a');b.className='btn s gold';b.style.marginTop='8px';b.href=(e.data&&e.data.rent)||'/#preise';b.target='_blank';b.textContent='Agent mieten oder Tokens kaufen';t.appendChild(document.createElement('br'));t.appendChild(b);}}
  $('#wSend').disabled=false;$('#wIn').focus();}
$('#wForm').onsubmit=e=>{e.preventDefault();micOn=false;send();};$('#wIn').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();micOn=false;send();}});
$('#wClear').onclick=()=>{HIST=[];$('#wMs').innerHTML='';sys('Neues Gespräch.');};
$('#wVoice').onclick=()=>{phoneMode=!phoneMode;$('#wVoice').classList.toggle('p',phoneMode);$('#wMic').classList.toggle('hidden',!(phoneMode&&SR));$('#wCost').textContent=phoneMode?'Telefon-Modus · '+ME.pricing.phone_turn+' Tokens je Antwort · Antworten werden vorgelesen':(active(CUR.id)?'Inklusive im Plan · ':'Studio-Test · ')+ME.pricing.agent_turn+' Tokens je Antwort';
  if(phoneMode){sys('Telefon-Modus: kurze gesprochene Antworten. '+(SR?'Drück 🎙️ und sprich.':'Dein Browser unterstützt keine Spracherkennung – tippe stattdessen.'));if(CFG&&CFG.phone_greeting){add('a',CFG.phone_greeting);HIST.push({role:'assistant',content:CFG.phone_greeting});speak(CFG.phone_greeting);}}else{try{speechSynthesis.cancel();}catch(e){}}};
const phLang=()=>(($('#phVoice')&&$('#phVoice').value)||'de-DE').slice(0,5), phMale=()=>/-m$/.test(($('#phVoice')&&$('#phVoice').value)||'');
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
  sel.innerHTML=list.map(p=>`<option value="${p.id}">${esc(p.name)}${active(p.id)?' · aktiv':''}</option>`).join('');
  const pre=(ME.configs||[]).find(c=>+c.phone_on)||(ME.plans||[]).find(p=>p.active&&PLANS.find(x=>x.id===p.id&&x.kind==='agent'));sel.value=pre?(pre.plan||pre.id):(list[0]?list[0].id:'');loadPhone();}
$('#phPlan').onchange=loadPhone;
async function loadPhone(){const id=$('#phPlan').value;if(!id)return;try{const j=await api('/agent/config?plan='+id);PHCFG=j.config;$('#phOn').checked=!!+PHCFG.phone_on;$('#phGreet').value=PHCFG.phone_greeting||'';$('#phVoice').value=PHCFG.phone_voice||'de-DE';$('#phFwd').value=PHCFG.phone_forward||'';$('#phHours').value=PHCFG.phone_hours||'';
    $('#phOnNote').textContent=j.plan_active?'Anrufe werden angenommen, sobald eine Nummer verbunden ist':'Freischaltung mit gemietetem/gekauftem Agenten';
    $('#phNumber').innerHTML=PHCFG.phone_number?`<b style="font-size:20px;color:var(--ink)">${esc(PHCFG.phone_number)}</b> <span class="tag ok">verbunden</span>`:(j.plan_active&&+PHCFG.phone_on?'<span class="tag gold">Einrichtung läuft</span> <span class="small">Wir melden uns innerhalb von 24 h mit deiner Nummer (support@chat-help.com).</span>':'<span class="small">Noch keine Nummer. Nach Buchung: Telefon aktivieren → wir richten die Nummer ein.</span>');
    $('#phHook').textContent=j.phone_webhook||'Speichern, um die Webhook-URL zu erhalten.';loadCalls(id);}catch(e){msg($('#phMsg'),e.message,'err');}}
$('#phSave').onclick=async()=>{const id=$('#phPlan').value;$('#phSave').disabled=true;try{const j=await api('/agent/config?plan='+id,{method:'POST',body:JSON.stringify({phone_on:$('#phOn').checked?1:0,phone_greeting:$('#phGreet').value,phone_voice:$('#phVoice').value,phone_forward:$('#phFwd').value,phone_hours:$('#phHours').value})});PHCFG=j.config;$('#phOn').checked=!!+PHCFG.phone_on;$('#phHook').textContent=j.phone_webhook;msg($('#phMsg'),j.note||'Gespeichert ✓',j.note?'info':'ok');await refreshMe();renderHome();loadPhone();}catch(e){msg($('#phMsg'),e.message,'err');}$('#phSave').disabled=false;};
async function loadCalls(id){try{const j=await api('/phone/calls?plan='+id);const el=$('#phCalls');if(!j.calls.length){el.innerHTML='<div class="mute small">Noch keine Anrufe für diesen Agenten.</div>';return;}
  el.innerHTML=j.calls.map(c=>`<div class="call" onclick="this.classList.toggle('open')"><div class="hd"><span><b>${esc(c.from_number||'unbekannt')}</b> · ${new Date(c.started_at).toLocaleString('de-DE')}</span><span>${c.turns} Antworten · ${c.tokens} Tokens · ${esc(c.outcome||c.status)}</span></div><div class="tr">${(c.transcript||[]).map(m=>`<div class="${m.role==='user'?'u':'a'}">${esc(m.content)}</div>`).join('')}</div></div>`).join('');}catch(e){}}
$('#phTest').onclick=()=>{const id=$('#phPlan').value;openAgent(id).then(()=>{if(!phoneMode)$('#wVoice').click();if(SR)setTimeout(listen,800);});};

/* ===== Termine ===== */
let AP=null,APSVC=[];const WDN=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
const eurS=c=>(c/100).toLocaleString('de-DE',{style:'currency',currency:'EUR'});
async function loadAppt(){try{const j=await api('/appt/settings');AP=j.settings;APSVC=j.services||[];renderAppt(j.booking_url);loadBookings();}catch(e){msg($('#apMsg'),e.message,'err');}}
function renderAppt(url){const s=AP;$('#apOn').checked=!!s.appt_on;$('#apCompany').value=s.company||'';$('#apAddress').value=s.address||'';$('#apNotify').value=s.notify_email||'';$('#apTz').value=s.tz||'Europe/Berlin';$('#apConfirm').value=s.confirm_text||'';$('#apSlot').value=s.slot_min;$('#apBuffer').value=s.buffer_min;$('#apLead').value=s.lead_min;$('#apMax').value=s.max_days;
  $('#apHours').innerHTML=[1,2,3,4,5,6,0].map(w=>{const r=(s.hours&&s.hours[String(w)]&&s.hours[String(w)][0])||null;return `<div class="row" style="gap:8px;padding:6px 0;border-bottom:1px solid var(--line)"><span style="width:92px;font-weight:600">${WDN[w]}</span><input type="checkbox" class="hOn" data-w="${w}" ${r?'checked':''} title="geöffnet"><input type="time" class="hFrom" data-w="${w}" value="${r?r[0]:'09:00'}" style="width:120px"><span class="mute">–</span><input type="time" class="hTo" data-w="${w}" value="${r?r[1]:'18:00'}" style="width:120px"></div>`;}).join('');
  $('#apSvcs').innerHTML='';APSVC.filter(x=>x.active!==0).forEach(addSvcRow);if(!APSVC.length)addSvcRow({name:'',duration_min:30,price_cents:0});
  $('#apUrl').textContent=url||'–';$('#apOpen').href=url||'#';
  const sel=$('#mbSvc');sel.innerHTML=APSVC.filter(x=>x.active!==0).map(x=>`<option value="${x.id}">${esc(x.name)} · ${x.duration_min} Min</option>`).join('')||'<option value="">(Standard-Taktung)</option>';}
function addSvcRow(x){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="sId" value="${x.id||0}"><input type="text" class="sName" placeholder="Leistung" value="${esc(x.name||'')}" style="flex:2;min-width:140px"><input type="text" class="sDur" value="${x.duration_min||30}" style="width:70px" title="Minuten"><span class="mute small">Min</span><input type="text" class="sPrice" value="${x.price_cents?(x.price_cents/100).toFixed(2).replace('.',','):''}" placeholder="0,00" style="width:84px"><span class="mute small">€</span><button class="btn s" type="button" title="entfernen">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#apSvcs').appendChild(d);}
$('#apAddSvc').onclick=()=>addSvcRow({});
$('#apSave').onclick=async()=>{$('#apSave').disabled=true;const hours={};document.querySelectorAll('.hOn').forEach(c=>{const w=c.dataset.w;hours[w]=c.checked?[[document.querySelector(`.hFrom[data-w="${w}"]`).value,document.querySelector(`.hTo[data-w="${w}"]`).value]]:[];});
  const services=[...document.querySelectorAll('#apSvcs .row')].map(r=>({id:+r.querySelector('.sId').value,name:r.querySelector('.sName').value,duration_min:+r.querySelector('.sDur').value,price:r.querySelector('.sPrice').value})).filter(s=>s.name.trim());
  try{const j=await api('/appt/settings',{method:'POST',body:JSON.stringify({appt_on:$('#apOn').checked?1:0,company:$('#apCompany').value,address:$('#apAddress').value,notify_email:$('#apNotify').value,tz:$('#apTz').value,confirm_text:$('#apConfirm').value,slot_min:+$('#apSlot').value,buffer_min:+$('#apBuffer').value,lead_min:+$('#apLead').value,max_days:+$('#apMax').value,hours,services})});AP=j.settings;APSVC=j.services||[];renderAppt(j.booking_url);msg($('#apMsg'),'Gespeichert ✓ – Agent und Buchungsseite nutzen die neuen Zeiten sofort.','ok');loadBookings();}catch(e){msg($('#apMsg'),e.message,'err');}$('#apSave').disabled=false;};
$('#apCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText($('#apUrl').textContent);msg($('#apMsg'),'Link kopiert.','ok');};
$('#apReload').onclick=loadBookings;
async function loadBookings(){try{const j=await api('/appt/bookings');const el=$('#apList');if(!j.bookings.length){el.innerHTML='<div class="mute small">Noch keine Termine. Teile deine Buchungsseite oder lass den Agenten buchen.</div>';return;}
  let last='';el.innerHTML=j.bookings.map(b=>{const head=b.date!==last?`<div style="font:700 12px var(--sans);letter-spacing:.06em;text-transform:uppercase;color:var(--gold);margin:12px 0 4px">${esc(b.weekday)}, ${b.date.split('-').reverse().join('.')}</div>`:'';last=b.date;const st={booked:'ok',done:'teal',cancelled:'rose',noshow:'rose'}[b.status]||'';
    return head+`<div class="call" style="margin-top:6px"><div class="row" style="justify-content:space-between"><span><b>${esc(b.time)}–${esc(b.end)}</b> · ${esc(b.service)}<br><span class="mute small">${esc(b.name)} · ${esc(b.phone)} ${b.email?'· '+esc(b.email):''} ${b.note?'· '+esc(b.note):''} · ${esc(b.source)} · Code ${b.code}</span></span><span class="row" style="gap:6px"><span class="tag ${st}">${esc(b.status)}</span>${b.status==='booked'?`<button class="btn s" onclick="apStatus(${b.id},'done')">✓ war da</button><button class="btn s" onclick="apStatus(${b.id},'noshow')">nicht erschienen</button><button class="btn s" onclick="apCancel('${b.code}')">absagen</button>`:''}</span></div></div>`;}).join('');}catch(e){$('#apList').textContent=e.message;}}
async function apStatus(id,st){try{await api('/appt/status',{method:'POST',body:JSON.stringify({id,status:st})});loadBookings();}catch(e){alert(e.message);}}
async function apCancel(code){if(!confirm('Termin absagen? Der Kunde wird per E-Mail informiert (falls hinterlegt).'))return;try{await api('/appt/cancel',{method:'POST',body:JSON.stringify({code})});loadBookings();}catch(e){alert(e.message);}}
$('#mbDate').onchange=async()=>{const d=$('#mbDate').value;if(!d)return;const j=await api('/appt/slots?date='+d+'&service='+($('#mbSvc').value||''));$('#mbTime').innerHTML=(j.slots||[]).map(t=>`<option>${t}</option>`).join('')||'<option value="">keine freie Zeit</option>';};
$('#mbSvc').onchange=()=>$('#mbDate').onchange();
$('#mbBook').onclick=async()=>{const d=$('#mbDate').value,t=$('#mbTime').value;if(!d||!t){msg($('#mbMsg'),'Datum und Uhrzeit wählen.','err');return;}try{const j=await api('/appt/book',{method:'POST',body:JSON.stringify({service_id:$('#mbSvc').value||null,start:d+' '+t,name:$('#mbName').value,phone:$('#mbPhone').value,email:$('#mbEmail').value,note:$('#mbNote').value})});msg($('#mbMsg'),'Eingetragen ✓ Code '+j.booking.code,'ok');loadBookings();$('#mbDate').onchange();}catch(e){msg($('#mbMsg'),e.message,'err');}};

/* ===== Bestellungen ===== */
let OS=null,OLINKS=null,odTimer=null;
const eurIn=v=>Math.max(0,Math.round(parseFloat(String(v||'0').replace(/\./g,'').replace(',','.'))*100||0)), eurOut=c=>((c||0)/100).toFixed(2).replace('.',',');
const optStr=o=>(o||[]).map(g=>g.name+': '+(g.choices||[]).map(c=>c.label+' '+(c.delta>0?'+':'')+eurOut(c.delta)).join(' | ')).join('; ');
async function loadOrd(){try{const j=await api('/order/settings');renderOrd(j);loadOrders();if(odTimer)clearInterval(odTimer);odTimer=setInterval(()=>{if(!$('#pBestellungen').classList.contains('hidden'))loadOrders(true);},20000);}catch(e){msg($('#odMsg'),e.message,'err');}}
function renderOrd(j){const s=OS=j.settings;OLINKS=j.links||{};$('#odOn').checked=!!s.order_on;$('#odName').value=s.name||'';$('#odAddress').value=s.address||'';$('#odPhone').value=s.phone||'';$('#odNotify').value=s.notify_email||'';$('#odTz').value=s.tz||'Europe/Berlin';
  $('#odDelivery').checked=!!s.delivery_on;$('#odPickup').checked=!!s.pickup_on;$('#odMin').value=eurOut(s.min_order_cents);$('#odFee').value=eurOut(s.delivery_fee_cents);$('#odEtaD').value=s.eta_delivery_min;$('#odEtaP').value=s.eta_pickup_min;
  const pm=String(s.payment_methods||'bar').split(',');document.querySelectorAll('.odPay').forEach(c=>c.checked=pm.includes(c.value));
  $('#odHours').innerHTML=[1,2,3,4,5,6,0].map(w=>{const r=(s.hours&&s.hours[String(w)]&&s.hours[String(w)][0])||null;return `<div class="row" style="gap:8px;padding:6px 0;border-bottom:1px solid var(--line)"><span style="width:92px;font-weight:600">${WDN[w]}</span><input type="checkbox" class="ohOn" data-w="${w}" ${r?'checked':''}><input type="time" class="ohFrom" data-w="${w}" value="${r?r[0]:'11:30'}" style="width:120px"><span class="mute">–</span><input type="time" class="ohTo" data-w="${w}" value="${r?r[1]:'22:00'}" style="width:120px"></div>`;}).join('');
  $('#odZones').innerHTML='';(s.zones||[]).forEach(addZoneRow);if(!(s.zones||[]).length)addZoneRow({name:'Innenstadt',plz:'',fee_cents:0,min_cents:1500});
  $('#odMenu').innerHTML='';(j.menu||[]).filter(m=>m.active!==0).forEach(addMenuRow);
  $('#odDrivers').innerHTML='';(j.drivers||[]).filter(d=>d.active!==0).forEach(addDriverRow);
  $('#odWaProv').value=s.wa_provider||'meta';waProv();$('#odWaPhoneId').value=s.wa_phone_id||'';$('#odWaToken').value=s.wa_token||'';$('#odWaVerify').value=s.wa_verify||'';$('#odWaNumber').value=s.wa_number||'';
  $('#odWaHook').textContent=OLINKS.whatsapp_webhook?OLINKS.whatsapp_webhook+'\nVerify-Token: '+(OLINKS.verify_token||''):'Speichern, um die URL zu erhalten.';
  $('#odBoard').textContent=OLINKS.board||'Speichern, um den Link zu erhalten.';$('#odBoardOpen').href=OLINKS.board||'#';}
function waProv(){const m=$('#odWaProv').value==='meta';$('#odWaMeta').classList.toggle('hidden',!m);$('#odWaTwilio').classList.toggle('hidden',m);}$('#odWaProv').onchange=waProv;
function addZoneRow(z){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="text" class="zName" placeholder="Zone" value="${esc(z.name||'')}" style="flex:1;min-width:110px"><input type="text" class="zPlz" placeholder="PLZ, PLZ, …" value="${esc(z.plz||'')}" style="flex:2;min-width:160px"><input type="text" class="zFee" value="${eurOut(z.fee_cents)}" style="width:70px" title="Liefergebühr €"><span class="mute small">€</span><input type="text" class="zMin" value="${eurOut(z.min_cents)}" style="width:76px" title="Mindestbestellwert €"><span class="mute small">€ min</span><button class="btn s" type="button">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#odZones').appendChild(d);}
function addMenuRow(m){const d=document.createElement('div');d.className='row';d.style.cssText='gap:6px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="mId" value="${m.id||0}"><input type="text" class="mCat" placeholder="Kategorie" value="${esc(m.category||'')}" style="width:96px"><input type="text" class="mName" placeholder="Name" value="${esc(m.name||'')}" style="flex:1.2;min-width:120px"><input type="text" class="mDesc" placeholder="Beschreibung" value="${esc(m.description||'')}" style="flex:1.5;min-width:120px"><input type="text" class="mPrice" value="${eurOut(m.price_cents)}" style="width:66px" title="Preis €"><input type="text" class="mOpt" placeholder="Optionen" value="${esc(typeof m.options==='string'?m.options:optStr(m.options))}" style="flex:1.4;min-width:140px"><input type="text" class="mTags" placeholder="Hinweise" value="${esc(m.tags||'')}" style="width:90px"><button class="btn s" type="button">✕</button>`;d.querySelector('button').onclick=()=>d.remove();$('#odMenu').appendChild(d);}
function addDriverRow(x){const d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=`<input type="hidden" class="dId" value="${x.id||0}"><input type="text" class="dName" placeholder="Name" value="${esc(x.name||'')}" style="flex:1;min-width:110px"><input type="tel" class="dPhone" placeholder="+49 … (WhatsApp/SMS)" value="${esc(x.phone||'')}" style="flex:1;min-width:140px">${x.url?`<a class="btn s" href="${esc(x.url)}" target="_blank">Fahrer-Link</a><button class="btn s" type="button" data-u="${esc(x.url)}" title="Link kopieren">⧉</button>`:'<span class="mute small">Link nach Speichern</span>'}<button class="btn s" type="button" class="rm">✕</button>`;const bs=d.querySelectorAll('button');bs[bs.length-1].onclick=()=>d.remove();const cp=d.querySelector('[data-u]');if(cp)cp.onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText(cp.dataset.u);msg($('#odMsg'),'Fahrer-Link kopiert.','ok');};$('#odDrivers').appendChild(d);}
$('#odAddZone').onclick=()=>addZoneRow({});$('#odAddItem').onclick=()=>addMenuRow({});$('#odAddDriver').onclick=()=>addDriverRow({});
$('#odDemoMenu').onclick=async()=>{if($('#odMenu').children.length&&!confirm('Beispielkarte zusätzlich zu den vorhandenen Artikeln einfügen?'))return;try{const r=await fetch('/chat/order-api.php?a=demo_menu&_='+Date.now(),{cache:'no-store'});const j=await r.json();(j.menu||[]).forEach(m=>addMenuRow({...m,id:0}));if(!$('#odZones').querySelector('.zPlz').value)(j.zones||[]).forEach((z,i)=>{if(i===0){$('#odZones').innerHTML='';}addZoneRow(z);});msg($('#odMsg'),'Beispielkarte eingefügt – anpassen und speichern.','info');}catch(e){msg($('#odMsg'),e.message,'err');}};
function ordPayload(){const hours={};document.querySelectorAll('.ohOn').forEach(c=>{const w=c.dataset.w;hours[w]=c.checked?[[document.querySelector(`.ohFrom[data-w="${w}"]`).value,document.querySelector(`.ohTo[data-w="${w}"]`).value]]:[];});
  const zones=[...document.querySelectorAll('#odZones .row')].map(r=>({name:r.querySelector('.zName').value,plz:r.querySelector('.zPlz').value,fee_cents:eurIn(r.querySelector('.zFee').value),min_cents:eurIn(r.querySelector('.zMin').value)})).filter(z=>z.plz.trim());
  const menu=[...document.querySelectorAll('#odMenu .row')].map(r=>({id:+r.querySelector('.mId').value,category:r.querySelector('.mCat').value,name:r.querySelector('.mName').value,description:r.querySelector('.mDesc').value,price_cents:eurIn(r.querySelector('.mPrice').value),options:r.querySelector('.mOpt').value,tags:r.querySelector('.mTags').value})).filter(m=>m.name.trim());
  const drivers=[...document.querySelectorAll('#odDrivers .row')].map(r=>({id:+r.querySelector('.dId').value,name:r.querySelector('.dName').value,phone:r.querySelector('.dPhone').value})).filter(d=>d.name.trim());
  return {order_on:$('#odOn').checked?1:0,name:$('#odName').value,address:$('#odAddress').value,phone:$('#odPhone').value,notify_email:$('#odNotify').value,tz:$('#odTz').value,delivery_on:$('#odDelivery').checked?1:0,pickup_on:$('#odPickup').checked?1:0,min_order_cents:eurIn($('#odMin').value),delivery_fee_cents:eurIn($('#odFee').value),eta_delivery_min:+$('#odEtaD').value,eta_pickup_min:+$('#odEtaP').value,payment_methods:[...document.querySelectorAll('.odPay:checked')].map(c=>c.value).join(','),hours,zones,menu,drivers,wa_provider:$('#odWaProv').value,wa_phone_id:$('#odWaPhoneId').value,wa_token:$('#odWaToken').value,wa_verify:$('#odWaVerify').value,wa_number:$('#odWaNumber').value};}
$('#odSave').onclick=async()=>{$('#odSave').disabled=true;try{const j=await api('/order/settings',{method:'POST',body:JSON.stringify(ordPayload())});renderOrd(j);msg($('#odMsg'),'Gespeichert ✓ – Agent, Board und WhatsApp-Webhook nutzen die Einstellungen sofort.','ok');loadOrders();}catch(e){msg($('#odMsg'),e.message,'err');}$('#odSave').disabled=false;};
$('#odWaCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText((OLINKS&&OLINKS.whatsapp_webhook)||'');msg($('#odMsg'),'Webhook-URL kopiert.','ok');};
$('#odBoardCopy').onclick=()=>{navigator.clipboard&&navigator.clipboard.writeText((OLINKS&&OLINKS.board)||'');msg($('#odMsg'),'Board-Link kopiert.','ok');};
$('#odReload').onclick=()=>loadOrders();$('#odAll').onchange=()=>loadOrders();
const OST={new:['Neu','gold'],confirmed:['Bestätigt','indigo'],preparing:['In Zubereitung','indigo'],ready:['Fertig','teal'],out_for_delivery:['Unterwegs','teal'],delivered:['Zugestellt','ok'],cancelled:['Storniert','rose']};
async function loadOrders(quiet){try{const j=await api('/order/orders?scope='+($('#odAll').checked?'all':'open'));const el=$('#odList');const drv=j.drivers||[];if(!j.orders.length){el.innerHTML='<div class="mute small">Noch keine Bestellungen. Teste den Agenten im Tab „Agenten“ (Bestell-Agent) oder verbinde WhatsApp.</div>';return;}
  el.innerHTML=j.orders.map(o=>{const [lbl,cls]=OST[o.status]||[o.status,''];let acts='';
    if(o.status==='new')acts=`<button class="btn s" onclick="odStatus(${o.id},'confirmed')">✓ bestätigen</button><button class="btn s" onclick="if(confirm('Stornieren?'))odStatus(${o.id},'cancelled')">stornieren</button>`;
    else if(o.status==='confirmed')acts=`<button class="btn s" onclick="odStatus(${o.id},'preparing')">in Zubereitung</button>`;
    else if(o.status==='preparing')acts=`<button class="btn s" onclick="odStatus(${o.id},'ready')">fertig</button>`;
    else if(o.status==='ready'&&o.type==='delivery')acts=`<select id="odD${o.id}" style="width:auto;padding:6px 8px">${drv.map(d=>`<option value="${d.id}">${esc(d.name)}</option>`).join('')||'<option value="">kein Fahrer</option>'}</select><button class="btn s" onclick="odStatus(${o.id},'out_for_delivery',document.getElementById('odD${o.id}').value)">🚗 unterwegs</button>`;
    else if(o.status==='ready')acts=`<button class="btn s" onclick="odStatus(${o.id},'delivered')">abgeholt</button>`;
    else if(o.status==='out_for_delivery')acts=`<button class="btn s" onclick="odStatus(${o.id},'delivered')">✅ zugestellt</button>`;
    return `<div class="call" style="margin-top:8px"><div class="row" style="justify-content:space-between"><span><b>${esc(o.code)}</b> · ${o.type==='delivery'?'🚗 Lieferung':'🛍️ Abholung'} · ⏱ ${esc(o.eta||'')} · <b>${esc(o.total)}</b> (${esc(o.payment)})<br><span class="mute small">${esc(o.items_text)}<br>${esc(o.name)} · ${esc(o.phone)}${o.address?' · '+esc(o.address):''}${o.zone?' · '+esc(o.zone):''}${o.distance_km!=null?' · '+o.distance_km+' km':''}${o.driver?' · Fahrer '+esc(o.driver.name):''} · ${esc(o.channel)} · ${esc(o.created)} Uhr${o.note?' · 📝 '+esc(o.note):''}</span></span><span class="row" style="gap:6px"><span class="tag ${cls}">${lbl}</span>${acts}</span></div></div>`;}).join('');}catch(e){if(!quiet)$('#odList').textContent=e.message;}}
async function odStatus(id,status,driver_id){try{await api('/order/status',{method:'POST',body:JSON.stringify({id,status,driver_id:driver_id||null})});loadOrders();}catch(e){alert(e.message);}}
$('#odChkGo').onclick=async()=>{const a=$('#odChk').value.trim();if(!a)return;$('#odChkRes').textContent='prüfe …';try{const j=await api('/order/check?address='+encodeURIComponent(a));$('#odChkRes').innerHTML=j.ok?`<span class="tag ok">lieferbar</span> Zone ${esc(j.zone||'–')} · Gebühr ${eurOut(j.fee_cents)} € · Mindestbestellwert ${eurOut(j.min_cents)} €${j.distance_km!=null?' · '+j.distance_km+' km':''}${j.geo?'<br><span class="mute">'+esc(j.geo.display)+'</span>':''}`:`<span class="tag rose">${j.reason==='unvollständig'?'unvollständig':'außerhalb'}</span> ${esc(j.hint||'Nicht im Liefergebiet – Abholung anbieten.')}${j.distance_km!=null?' · '+j.distance_km+' km':''}`;}catch(e){$('#odChkRes').textContent=e.message;}};
$('#odChk').addEventListener('keydown',e=>{if(e.key==='Enter')$('#odChkGo').click();});

/* ===== Tokens ===== */
function renderTokens(){$('#tBal').textContent=de(ME.balance);$('#tKey').textContent=KEY.slice(0,16)+'…'+KEY.slice(-6)+'   (vollständig: '+KEY+')';const tb=$('#tRecent tbody');tb.innerHTML=(ME.recent||[]).map(r=>`<tr><td>${new Date(r.created_at).toLocaleString('de-DE')}</td><td>${esc(r.task)}</td><td>${esc(r.sector||'')}</td><td>${de(r.tokens)}</td></tr>`).join('')||'<tr><td colspan="4" class="mute">Noch kein Verbrauch.</td></tr>';}
boot();
</script>
</body></html>
