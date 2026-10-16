<?php header('Cache-Control: no-cache, no-store, must-revalidate'); header('X-Robots-Tag: noindex'); ?><!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp Studio – Agenten, Pakete & Tokens</title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap">
<style>
:root{--bg:#0b0b12;--bg2:#12121c;--card:#161624;--line:rgba(255,255,255,.08);--ink:#f3f3f8;--ink2:#c9c9d6;--mute:#8d8da3;--gold:#e3c27a;--gold2:#b0801f;--teal:#2ad4b8;--violet:#8b7cf6;--rose:#f26d8b;--ok:#4cd98a}
*{box-sizing:border-box}html,body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 Inter,system-ui,sans-serif}
a{color:var(--gold);text-decoration:none}button,input,select,textarea{font:inherit}
.top{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;background:rgba(11,11,18,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.logo{font-family:Fraunces,serif;font-size:20px;font-weight:600;color:#fff}.logo span{color:var(--gold)}
.pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid var(--line);border-radius:999px;color:var(--ink2);font-size:13px}.pill b{color:#fff}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.04);color:#fff;cursor:pointer;font-weight:500;transition:.15s}.btn:hover{border-color:rgba(255,255,255,.25);background:rgba(255,255,255,.08)}
.btn.p{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#1a1408;border-color:transparent}.btn.p:hover{filter:brightness(1.06)}.btn.s{padding:6px 10px;font-size:13px}.btn:disabled{opacity:.5;cursor:not-allowed}
.wrap{max-width:1180px;margin:0 auto;padding:26px 20px 60px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px}
h1{font-family:Fraunces,serif;font-weight:600;font-size:30px;margin:0 0 6px}h2{font-family:Fraunces,serif;font-weight:600;font-size:22px;margin:0 0 10px}h3{font-size:16px;margin:0 0 6px}
.sub{color:var(--mute);margin:0 0 18px}
.login{max-width:520px;margin:50px auto}.login input{width:100%;padding:13px 14px;border-radius:12px;border:1px solid var(--line);background:#0e0e17;color:#fff;margin:10px 0 12px}
.banner{border:1px solid rgba(76,217,138,.35);background:rgba(76,217,138,.08);color:#d9ffe9;border-radius:14px;padding:14px 16px;margin-bottom:18px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.plan{cursor:pointer;position:relative;transition:.15s}.plan:hover{border-color:rgba(227,194,122,.45);transform:translateY(-2px)}.plan .tag{position:absolute;top:14px;right:14px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--violet);border:1px solid rgba(139,124,246,.4);border-radius:999px;padding:3px 8px}.plan .tag.abo{color:var(--gold);border-color:rgba(227,194,122,.4)}.plan .tag.free{color:var(--teal);border-color:rgba(42,212,184,.4)}
.plan .st{font-size:12.5px;color:var(--mute);margin-top:8px}.plan .st i{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ok);margin-right:6px}.plan .st i.off{background:var(--rose)}
.ws{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start}@media(max-width:960px){.ws{grid-template-columns:1fr}}
label{display:block;font-size:12.5px;color:var(--mute);margin:12px 0 5px}input.f,select.f,textarea.f{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0e0e17;color:#fff}textarea.f{min-height:160px;resize:vertical;font-size:13.5px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.hint{font-size:12.5px;color:var(--mute)}
.chat{display:flex;flex-direction:column;height:min(78vh,760px)}.msgs{flex:1;overflow:auto;padding:6px 4px;display:flex;flex-direction:column;gap:12px}
.m{max-width:88%;padding:12px 14px;border-radius:14px;white-space:pre-wrap;word-wrap:break-word;font-size:14.5px;position:relative}.m.u{align-self:flex-end;background:rgba(227,194,122,.16);border:1px solid rgba(227,194,122,.3);border-bottom-right-radius:4px}.m.a{align-self:flex-start;background:#0e0e17;border:1px solid var(--line);border-bottom-left-radius:4px}.m .cp{position:absolute;top:6px;right:8px;font-size:11px;color:var(--mute);cursor:pointer;background:transparent;border:0}.m .cp:hover{color:#fff}.m .meta{display:block;margin-top:8px;font-size:11.5px;color:var(--mute)}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.chip{padding:6px 10px;border-radius:999px;border:1px solid var(--line);background:rgba(255,255,255,.03);color:var(--ink2);font-size:12.5px;cursor:pointer}.chip:hover{border-color:var(--gold);color:#fff}
.comp{display:flex;gap:10px;margin-top:10px}.comp textarea{flex:1;min-height:56px;max-height:200px;padding:12px;border-radius:12px;border:1px solid var(--line);background:#0e0e17;color:#fff;resize:vertical}
.snippet{font:12.5px/1.5 ui-monospace,Menlo,Consolas,monospace;background:#0e0e17;border:1px solid var(--line);border-radius:10px;padding:10px;word-break:break-all;color:var(--teal)}
.sw{display:inline-flex;align-items:center;gap:8px;cursor:pointer}.sw input{width:18px;height:18px;accent-color:var(--gold)}
.hid{display:none}.err{color:var(--rose);font-size:13px;margin-top:8px}.ok{color:var(--ok);font-size:13px;margin-top:8px}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:40px}
</style></head><body>
<div class="top"><a class="logo" href="/">Chat<span>Help</span> Studio</a>
  <div class="row"><span class="pill hid" id="bal">Guthaben <b id="balN">–</b> Tokens</span><a class="btn s" href="/#tokens">Tokens kaufen</a><a class="btn s" href="/#agenten">Agenten</a><button class="btn s hid" id="logout">Abmelden</button></div></div>
<div class="wrap">
  <div id="banner" class="banner hid"></div>

  <!-- LOGIN -->
  <div id="vLogin" class="card login">
    <h1>Willkommen im Studio</h1>
    <p class="sub">Hier arbeiten deine gemieteten KI-Agenten und Pakete. Melde dich mit deinem API-Key an – du hast ihn nach dem Kauf per E-Mail erhalten.</p>
    <input id="key" type="password" placeholder="ch_live_…" autocomplete="off" spellcheck="false">
    <div class="row"><button class="btn p" id="login">Anmelden</button><a class="btn" href="/#tokens">Noch kein Konto? Tokens oder Agent kaufen →</a></div>
    <div class="err hid" id="loginErr"></div>
    <p class="hint" style="margin-top:16px">Key verloren? Schreib an <a href="mailto:info@chat-help.com">info@chat-help.com</a> (mit deiner Kauf-E-Mail). Rechtsdokumente (Widerspruch, Mieterhöhung, Visum) findest du weiterhin unter <a href="/chat/">ChatHelp Recht</a>.</p>
  </div>

  <!-- DASHBOARD -->
  <div id="vDash" class="hid">
    <h1>Deine Agenten &amp; Pakete</h1><p class="sub" id="who"></p>
    <div class="grid" id="plans"></div>
    <div class="row" style="margin-top:18px"><a class="btn p" href="/#branchen">Weitere Agenten &amp; Pakete mieten</a><a class="btn" href="/#tokens">Tokens aufladen</a><a class="btn" href="/#api">API-Doku</a></div>
    <div class="card" style="margin-top:22px"><h3>Letzte Nutzung</h3><div id="recent" class="hint">–</div></div>
  </div>

  <!-- WORKSPACE -->
  <div id="vWs" class="hid">
    <div class="row" style="justify-content:space-between;margin-bottom:14px"><div><button class="btn s" id="back">← Übersicht</button></div><div class="hint" id="wsCost"></div></div>
    <div class="ws">
      <div class="card" id="cfgCard">
        <h2 id="wsName">Agent</h2><p class="hint" id="wsDesc"></p>
        <form id="cfg">
          <label>Unternehmen / Marke</label><input class="f" name="company" placeholder="z. B. Müller Sanitär GmbH">
          <div class="row"><div style="flex:1"><label>Tonalität</label><select class="f" name="tone"><option>freundlich-professionell</option><option>locker-du</option><option>formell-sie</option><option>sachlich-knapp</option><option>herzlich-persönlich</option></select></div>
          <div style="flex:1"><label>Sprache</label><select class="f" name="lang"><option value="de">Deutsch</option><option value="en">English</option><option value="tr">Türkçe</option><option value="fr">Français</option><option value="es">Español</option><option value="ru">Русский</option></select></div></div>
          <label>Wissensbasis <span class="hint">(FAQ, Produkte, Preise, Öffnungszeiten, Regeln – einzige Faktenquelle des Agenten)</span></label>
          <textarea class="f" name="kb" placeholder="Öffnungszeiten: Mo–Fr 9–18 Uhr&#10;Versand: DHL, 2–4 Werktage, ab 50 € kostenlos&#10;Retouren: 14 Tage, Portal unter …"></textarea>
          <div id="widgetBox">
            <label>Website-Widget</label>
            <label class="sw" style="color:var(--ink2)"><input type="checkbox" name="widget_on"> Chat-Bubble auf meiner Website aktivieren</label>
            <label>Erlaubte Domain(s) <span class="hint">(optional, z. B. meinshop.de, www.meinshop.de)</span></label><input class="f" name="widget_domain" placeholder="meinshop.de">
            <div id="snip" class="hid"><label>Einbetten – vor &lt;/body&gt; einfügen</label><div class="snippet" id="snippet"></div><button type="button" class="btn s" id="copySnip" style="margin-top:8px">Code kopieren</button></div>
          </div>
          <div class="row" style="margin-top:16px"><button class="btn p" type="submit">Speichern</button><span id="cfgMsg" class="hint"></span></div>
        </form>
      </div>
      <div class="card chat">
        <div class="chips" id="chips"></div>
        <div class="msgs" id="msgs"></div>
        <form class="comp" id="comp"><textarea id="inp" placeholder="Schreib deinem Agenten… (Enter = senden, Shift+Enter = neue Zeile)"></textarea><button class="btn p" type="submit" id="send">Senden</button></form>
      </div>
    </div>
  </div>
  <div class="foot">ChatHelp Studio · Tokens verfallen nicht · <a href="/chat/?view=datenschutz">Datenschutz</a> · <a href="/chat/?view=impressum">Impressum</a> · <a href="mailto:info@chat-help.com">Support</a></div>
</div>
<script>
const API='/chat/tokens-api.php/v1/'; const $=s=>document.querySelector(s); const de=n=>Number(n).toLocaleString('de-DE');
let KEY=localStorage.getItem('ch_api_key')||''; let ME=null; let CUR=null; let HIST=[];
const q=new URLSearchParams(location.search);
/* ---- Aufgaben-Vorlagen je Plan / Sektor ---- */
const T={
 'agent-support':[['Antwort auf Kundenfrage','Ein Kunde fragt: "Wann kommt meine Bestellung #4711?" – antworte als Support.'],['Beschwerde deeskalieren','Kunde ist verärgert, Lieferung beschädigt. Schreibe eine empathische Antwort mit Lösung.'],['FAQ aus Wissensbasis','Erstelle 10 FAQ mit Antworten aus meiner Wissensbasis.']],
 'ec-support':[['Retourenfrage beantworten','Kunde möchte nach 10 Tagen zurücksenden – wie geht er vor? Antworte freundlich.'],['Lieferverzögerung','Paket seit 7 Tagen unterwegs, Kunde fragt nach. Antwort mit nächsten Schritten.'],['Gewährleistungsfall','Produkt nach 8 Monaten defekt. Erkläre Rechte und Ablauf.']],
 'log-support':[['Sendungsstatus-Anfrage','Partner fragt nach Sendung 123456 – was antworte ich ohne Zusagen zu erfinden?'],['Doku-Frage','Welche Verpackungsvorschriften gelten laut Doku für Gefahrgut Klasse 3?']],
 'agent-sales':[['Lead qualifizieren','Neuer Lead: "Wir brauchen eine Lösung für 20 Mitarbeiter, Budget unklar." Stelle die 2 wichtigsten Fragen.'],['Follow-up E-Mail','Schreibe ein Follow-up nach Erstgespräch gestern (Betreff + Text).'],['CRM-Notiz','Fasse dieses Gespräch als CRM-Notiz zusammen: …']],
 'agent-content':[['Redaktionsplan 2 Wochen','Erstelle einen Redaktionsplan für 2 Wochen, 3 Posts/Woche, Instagram + LinkedIn.'],['5 Posts zum Thema','Schreibe 5 Instagram-Posts mit Caption und Hashtags zum Thema: …'],['Monatsthemen','Schlage 8 Content-Themen für den nächsten Monat vor.']],
 'agent-backoffice':[['Zahlungserinnerung','Rechnung 2024-118 über 1.250 € ist 10 Tage überfällig – Zahlungserinnerung schreiben.'],['Beleg erklären','Erkläre mir diesen Bescheid und die Fristen: …'],['Fristenliste','Erstelle meine Fristenliste für das nächste Quartal.']],
 'immo-agent':[['Anfrage beantworten','Interessent fragt: "Ist die 3-Zimmer-Wohnung noch frei und ab wann?" – antworte und qualifiziere.'],['Besichtigung vorschlagen','Schlage 3 Besichtigungstermine vor und frage die wichtigsten Daten ab.'],['Lead-Zusammenfassung','Fasse den Interessenten strukturiert zusammen: …']],
 'praxis-agent':[['Organisatorische Frage','Patient fragt: "Kann ich mein Rezept ohne Termin abholen?"'],['Terminart erklären','Welche Unterlagen brauche ich für den Erstbesuch?'],['Notfall-Hinweis','Patient beschreibt starke Schmerzen – wie antworte ich korrekt?']],
 'hr-screening':[['Vorauswahl','Stellenprofil: … Bewerbung: … – bewerte Passung und begründe.'],['Interviewfragen','Erstelle 8 strukturierte Interviewfragen für: …'],['Absage schreiben','Wertschätzende Absage für Bewerber nach Zweitgespräch.']],
 'fin-mahnung':[['3 Mahnstufen','Rechnung 4711, 980 €, fällig seit 15.09. – erstelle alle 3 Mahnstufen.'],['Verzugszinsen','Berechne Verzugszinsen für 2.400 € B2B, 45 Tage Verzug.']],
 'gastro-agent':[['Reservierungsanfrage','Gast: "Tisch für 6 am Samstag 19 Uhr, eine Nussallergie" – antworte.'],['Frage zur Karte','Gibt es vegane Hauptgerichte? (aus Wissensbasis)'],['Englische Anfrage','"Do you have parking and are dogs allowed?"']],
 'creator-social':[['Wochen-Content-Pool','7 Posts für diese Woche zum Thema: …, Kanal Instagram, Zielgruppe: …'],['Hashtag-Strategie','3 Hashtag-Sets (Reichweite/Nische/Marke) für meine Nische: …'],['Caption in 2 Längen','Caption kurz + lang für ein Reel über: …']],
 'creator-youtube':[['Komplettes Skript','Skript für ein 8-Minuten-Video: Thema … Zielgruppe … Hook + Kapitel + CTA.'],['5 Titel + Beschreibung','5 Titelvarianten, Beschreibung mit Keywords und 15 Tags für: …'],['Shorts-Skript','45-Sekunden-Shorts-Skript über: …']],
 'ec-texte':[['Produkttext Shopify','Produkt: … Zielgruppe: … – Titel, 5 Bullets, Beschreibung, Meta.'],['Amazon-Listing','Amazon-Listing (Titel ≤ 200 Zeichen, 5 Bullets, Beschreibung) für: …'],['Kategorietext SEO','SEO-Kategorietext (300 Wörter) für: …']],
 'ec-retouren':[['Retoure außerhalb Frist','Kunde will nach 20 Tagen zurückgeben – freundliche, rechtssichere Antwort.'],['Reklamation Defekt','Artikel defekt nach 3 Wochen – Antwort mit Ablauf Nacherfüllung.']],
 'immo-expose':[['Exposé erstellen','Eckdaten: 3 Zi, 84 m², 2. OG, Balkon, Bj 1998, Energie C, 1.250 € kalt, Berlin-Pankow.'],['Lagebeschreibung','Lagetext für: …'],['Headline-Varianten','5 Headlines für ein Portal-Inserat: …']],
 'praxis-termine':[['Erinnerung 24h','SMS + E-Mail Terminerinnerung 24 h vorher.'],['Terminabsage','Absage wegen Krankheit des Arztes mit Ersatzterminen.']],
 'praxis-info':[['Infoblatt Erstbesuch','Infoblatt: Ablauf Erstbesuch, Unterlagen, Anfahrt – leichte Sprache.'],['Mehrsprachig','Übersetze das Infoblatt in TR und EN.']],
 'hr-anzeigen':[['Stellenanzeige','Stelle: … Standort: … Aufgaben: … – AGG-konforme Anzeige (Indeed-Format).'],['LinkedIn-Version','Kürzere LinkedIn-Version der Anzeige.']],
 'hr-onboarding':[['30-60-90-Plan','30-60-90-Tage-Plan für Rolle: …'],['Checkliste Tag 1','Checkliste vor Tag 1 und Woche 1.']],
 'fin-belege':[['Bescheid erklären','Erkläre diesen Steuerbescheid und Fristen: …'],['Beleg prüfen','Was fehlt auf dieser Rechnung für den Vorsteuerabzug? …']],
 'fin-fristen':[['Fristenplan Jahr','Fristenplan für GmbH, Quartals-USt-VA, Kalenderjahr.']],
 'gastro-karte':[['Karte 6 Sprachen','Übersetze meine Karte in EN/FR/IT/ES/TR/RU mit Allergenen: …'],['Tagesangebot','Appetitliche Beschreibung für das Tagesgericht: …']],
 'gastro-reviews':[['Kritische Bewertung','Antwort auf 2-Sterne-Bewertung: "…"'],['Positive Bewertung','Persönliche Antwort auf 5-Sterne-Bewertung: "…"']],
 'hw-angebot':[['Angebot erstellen','Aufmaß: … Leistungen: … – Angebot mit Positionen.'],['Nachtragsangebot','Nachtrag für Zusatzleistung: …']],
 'hw-kunden':[['Terminbestätigung','Termin Mo 8 Uhr, Dauer 3 h, Zugang nötig – SMS + E-Mail.'],['Verzögerung','Material verspätet, neuer Termin – Nachricht.']],
 'hw-rechnung':[['Rechnungstext','Rechnungstext mit Pflichtangaben-Check für: …'],['Reklamationsantwort','Kunde reklamiert Fliesenarbeit nach 6 Monaten – Antwort (VOB/BGB).']],
 'edu-kurs':[['Kurs entwerfen','Kurs: … Zielgruppe: … Dauer: … – Module, Lernziele, Übungen, Quiz.'],['Quiz erstellen','10 Quizfragen mit Lösungen zu: …']],
 'edu-material':[['Arbeitsblatt','Arbeitsblatt mit Lösungen, Niveau B1, Thema: …'],['Karteikarten','20 Karteikarten (Frage/Antwort) zu: …']],
 'mkt-seo':[['Keyword-Cluster','Keyword-Cluster für: …'],['Blogartikel','SEO-Blogartikel 1.000 Wörter zu: … Hauptkeyword: …'],['Meta-Daten','Meta-Title/-Description für 5 Seiten: …']],
 'log-doku':[['Fachübersetzung','Übersetze fachgerecht nach EN (Glossar beachten): …'],['Sicherheitshinweis','Sicherheitshinweise normgerecht formulieren für: …']],
 'log-liefer':[['Lieferavis','Lieferavis B2B für Sendung … ETA …, DE + EN.'],['Verzögerung','Verzögerungsmeldung mit neuem ETA und Entschuldigung.']],
 _sector:[['Aufgabe beschreiben','Ziel: … Kontext: … – liefere das fertige Ergebnis.'],['Text verbessern','Verbessere diesen Text (Ton: professionell): …'],['Zusammenfassen','Fasse zusammen und nenne nächste Schritte: …']]
};
/* ---- API ---- */
async function api(path,opts={}){const r=await fetch(API+path,{...opts,headers:{'Content-Type':'application/json','Authorization':'Bearer '+KEY,...(opts.headers||{})}});const j=await r.json().catch(()=>({}));if(!r.ok){const e=new Error(j.message||j.error||('HTTP '+r.status));e.data=j;e.status=r.status;throw e;}return j;}
function show(v){['vLogin','vDash','vWs'].forEach(i=>$('#'+i).classList.toggle('hid',i!==v));$('#logout').classList.toggle('hid',v==='vLogin');$('#bal').classList.toggle('hid',v==='vLogin');}
function banner(html){const b=$('#banner');b.innerHTML=html;b.classList.remove('hid');}
/* ---- Login ---- */
$('#login').onclick=async()=>{KEY=$('#key').value.trim();if(!/^ch_(live|test)_[a-f0-9]{48}$/.test(KEY)){$('#loginErr').textContent='Das sieht nicht wie ein ChatHelp-Key aus (ch_live_…).';$('#loginErr').classList.remove('hid');return;}
  try{await loadMe();localStorage.setItem('ch_api_key',KEY);}catch(e){$('#loginErr').textContent='Anmeldung fehlgeschlagen: '+e.message;$('#loginErr').classList.remove('hid');}};
$('#key').addEventListener('keydown',e=>{if(e.key==='Enter')$('#login').click();});
$('#logout').onclick=()=>{localStorage.removeItem('ch_api_key');KEY='';ME=null;show('vLogin');};
async function loadMe(){ME=await api('me');$('#balN').textContent=de(ME.balance);$('#who').textContent=ME.email+' · Guthaben '+de(ME.balance)+' Tokens';renderPlans();show('vDash');
  const w=q.get('welcome');if(w){const p=ME.plans.find(x=>x.id===w);banner('🎉 <b>'+(p?p.name:w)+'</b> ist aktiv – '+(p&&p.kind==='agent'?'konfiguriere unten die Wissensbasis und teste deinen Agenten.':'wähle das Paket unten und starte mit den Vorlagen.'));if(p)setTimeout(()=>openPlan(p.id),400);}
  if(q.get('tokens')==='success')banner('✅ Tokens gutgeschrieben – aktuelles Guthaben: <b>'+de(ME.balance)+'</b>.');}
function renderPlans(){const g=$('#plans');g.innerHTML='';
  ME.plans.forEach(p=>{const d=document.createElement('div');d.className='card plan';d.innerHTML=`<span class="tag ${p.kind}">${p.kind==='agent'?'Agent':'Paket'}</span><h3>${p.name}</h3><div class="hint">${de(p.tokens_month)} Tokens / Monat</div><div class="st"><i class="${p.active?'':'off'}"></i>${p.active?'aktiv':p.status}${p.period_end?' · bis '+p.period_end.slice(0,10):''}</div>`;d.onclick=()=>openPlan(p.id);g.appendChild(d);});
  const f=document.createElement('div');f.className='card plan';f.innerHTML=`<span class="tag free">Tokens</span><h3>Freier Assistent</h3><div class="hint">Jeder Sektor, bezahlt pro Antwort aus deinem Guthaben (${10} Tokens).</div><div class="st"><i></i>verfügbar</div>`;f.onclick=()=>openSector();g.appendChild(f);
  if(!ME.plans.length){const n=document.createElement('div');n.className='card plan';n.innerHTML='<span class="tag">Mieten</span><h3>Noch kein Agent</h3><div class="hint">Miete einen fertigen Agenten ab 79 €/Monat – inkl. Tokens, Website-Widget und Wissensbasis.</div><div class="st">→ Agenten ansehen</div>';n.onclick=()=>location.href='/#agenten';g.appendChild(n);}
  $('#recent').innerHTML=(ME.recent||[]).length?ME.recent.map(r=>`${r.created_at.slice(0,16)} · ${r.task} · ${r.tokens} Tokens`).join('<br>'):'Noch keine Nutzung.';}
/* ---- Workspace ---- */
async function openPlan(id){const p=ME.plans.find(x=>x.id===id);if(!p)return;CUR={type:'plan',id,name:p.name,kind:p.kind,sector:p.sector};HIST=[];$('#wsName').textContent=p.name;$('#wsDesc').textContent=(p.kind==='agent'?'Dein gemieteter Agent. ':'Dein Paket. ')+'Antworten kosten 6 Tokens + 1 je 2.000 Zeichen Kontext.';$('#wsCost').textContent=de(p.tokens_month)+' Tokens/Monat inklusive · '+(p.active?'aktiv':p.status);
  $('#cfgCard').classList.remove('hid');$('#widgetBox').classList.toggle('hid',p.kind!=='agent');chips(T[id]||T._sector);$('#msgs').innerHTML='';show('vWs');
  try{const c=await api('agent/config?plan='+id);fillCfg(c.config,c.widget_snippet);}catch(e){$('#cfgMsg').textContent=e.message;}
  addMsg('a','Hallo! Ich bin dein '+p.name+'. Beschreibe deine Aufgabe – oder wähle oben eine Vorlage. Fakten nehme ich nur aus deiner Wissensbasis.');}
function openSector(){const sec=prompt('Sektor wählen: '+ME.sectors.map(s=>s.id).join(', '),'marketing');if(!sec)return;const s=ME.sectors.find(x=>x.id===sec)||ME.sectors[0];CUR={type:'sector',id:s.id,name:s.name};HIST=[];$('#wsName').textContent='Freier Assistent · '+s.name;$('#wsDesc').textContent='Bezahlt pro Antwort aus deinem Guthaben (10 Tokens).';$('#wsCost').textContent='Guthaben '+de(ME.balance)+' Tokens';$('#cfgCard').classList.add('hid');chips(T._sector);$('#msgs').innerHTML='';show('vWs');addMsg('a','Bereit. Was soll ich für dich erledigen?');}
function fillCfg(c,snip){const f=$('#cfg');f.company.value=c.company||'';f.tone.value=c.tone||'freundlich-professionell';f.lang.value=c.lang||'de';f.kb.value=c.kb||'';f.widget_on.checked=!!+c.widget_on;f.widget_domain.value=c.widget_domain||'';if(snip){$('#snippet').textContent=snip;$('#snip').classList.remove('hid');}else $('#snip').classList.add('hid');}
$('#cfg').onsubmit=async e=>{e.preventDefault();const f=e.target;const d={plan:CUR.id,company:f.company.value,tone:f.tone.value,lang:f.lang.value,kb:f.kb.value,widget_on:f.widget_on.checked?1:0,widget_domain:f.widget_domain.value};$('#cfgMsg').textContent='Speichern…';
  try{const r=await api('agent/config',{method:'POST',body:JSON.stringify(d)});fillCfg(r.config,r.widget_snippet);$('#cfgMsg').textContent='Gespeichert ✓'+(d.widget_on?' – Widget aktiv.':'');}catch(err){$('#cfgMsg').textContent='Fehler: '+err.message;}};
$('#copySnip').onclick=()=>{navigator.clipboard.writeText($('#snippet').textContent).then(()=>{$('#copySnip').textContent='Kopiert ✓';setTimeout(()=>$('#copySnip').textContent='Code kopieren',1500);});};
function chips(list){const c=$('#chips');c.innerHTML='';list.forEach(([t,p])=>{const b=document.createElement('button');b.type='button';b.className='chip';b.textContent=t;b.onclick=()=>{$('#inp').value=p;$('#inp').focus();};c.appendChild(b);});}
function addMsg(role,text,meta){const m=$('#msgs');const d=document.createElement('div');d.className='m '+role;d.textContent=text;if(role==='a'){const cp=document.createElement('button');cp.className='cp';cp.textContent='Kopieren';cp.onclick=()=>{navigator.clipboard.writeText(text);cp.textContent='✓';};d.appendChild(cp);}if(meta){const s=document.createElement('span');s.className='meta';s.textContent=meta;d.appendChild(s);}m.appendChild(d);m.scrollTop=m.scrollHeight;return d;}
$('#inp').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();$('#comp').requestSubmit();}});
$('#comp').onsubmit=async e=>{e.preventDefault();const t=$('#inp').value.trim();if(!t||!CUR)return;$('#inp').value='';addMsg('u',t);HIST.push({role:'user',content:t});const w=addMsg('a','…denkt nach');$('#send').disabled=true;
  try{const body=CUR.type==='plan'?{plan:CUR.id,messages:HIST,lang:$('#cfg').lang.value}:{sector:CUR.id,messages:HIST,lang:'de'};const r=await api('agent',{method:'POST',body:JSON.stringify(body)});w.remove();const txt=typeof r.result==='string'?r.result:JSON.stringify(r.result,null,2);addMsg('a',txt,`${r.tokens_used} Tokens · Guthaben ${de(r.balance)}`);HIST.push({role:'assistant',content:txt});$('#balN').textContent=de(r.balance);ME.balance=r.balance;}
  catch(err){w.remove();let msg='Fehler: '+err.message;if(err.status===402)msg+=' → Tokens aufladen: chat-help.com/#tokens';if(err.status===403)msg+=' → Paket nicht aktiv.';addMsg('a',msg);HIST.pop();}
  $('#send').disabled=false;$('#inp').focus();};
$('#back').onclick=()=>{loadMe().catch(()=>show('vLogin'));};
/* ---- Start ---- */
if(KEY){loadMe().catch(()=>{show('vLogin');});}else{show('vLogin');if(q.get('welcome')||q.get('tokens'))banner('✅ Zahlung erfolgreich. Dein API-Key kommt in wenigen Minuten per E-Mail (auch Spam prüfen) – damit meldest du dich hier an.');}
</script>
</body></html>
