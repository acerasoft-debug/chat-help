<?php
/**
 * ChatHelp — Gehostete Seite „Rückruf anfordern“: callback.php?k=<widget_key>   (k=demo → Demo-Makler, es wird niemand angerufen)
 * Formular: Name, Telefon, E-Mail (optional), Anliegen, Wunschzeit (so schnell wie möglich · heute HH:MM · Tag + Uhrzeit innerhalb der Rückrufzeiten),
 * Einwilligung (Wortlaut wird mit Zeitpunkt + IP-Prüfsumme gespeichert), Datenschutzhinweis → POST (JSON) an lead-api.php → Bestätigung.
 * Optional vorbelegen: &topic=… (Anliegen, z. B. Objektnummer) und &source=… (Herkunft, z. B. „Exposé LQ-2041“).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/callback-lib.php';
require_once __DIR__.'/agent-tools.php';   // Planprüfung (ch_plan_active_any)
th_nocache(); th_bootstrap(); cb_bootstrap(); th_fresh();
header('Content-Type: text/html; charset=UTF-8'); header('X-Robots-Tag: noindex');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$k=is_scalar($_GET['k'] ?? null) ? (string)$_GET['k'] : ''; $kk=cb_key_account($k);
$fail=null;
if(!$kk){ http_response_code(404); $fail=['Dieser Link ist ungültig','Bitte verwenden Sie den Rückruf-Link von der Website des Unternehmens.']; }
elseif(!cb_enabled($kk['account_id'])){ http_response_code(403); $fail=['Rückrufanfragen sind gerade nicht möglich','Dieses Unternehmen nimmt derzeit keine Rückrufwünsche über dieses Formular an. Bitte nutzen Sie die Kontaktdaten auf der Website.']; }
elseif(!cb_public_ok($kk['account_id'],$kk['plan'])){ http_response_code(403); $fail=['Dieses Rückrufformular ist nicht aktiv','Bitte nutzen Sie die Kontaktdaten auf der Website des Unternehmens.']; }
$acc=$kk ? $kk['account_id'] : -1; $s=$kk ? cb_settings($acc) : null; $company=$kk ? cb_company($acc,$s,$kk) : '';
$demo=$acc===0; $ai=$s && $s['ai_callback']===1; $now=$s ? cb_now($s) : new DateTimeImmutable('now');
$inWin=$s && cb_in_window($s,$now); $instant=$ai && $s['lead_instant']===1 && $inWin;
$next=$s && !$inWin ? cb_next_window($s,$now) : null;
$slots=$s ? cb_slots($s,$now,10,30) : [];
$consent=cb_consent_text($company,$ai);
$topic=is_scalar($_GET['topic'] ?? null) ? cb_line($_GET['topic'],300) : ''; $srcParam=is_scalar($_GET['source'] ?? null) ? cb_line($_GET['source'],40) : '';
$asapNote=$instant ? 'In der Regel innerhalb von 60 Sekunden' : ($inWin ? 'Wir melden uns schnellstmöglich' : ($next ? 'Frühestens '.cb_when_label($next,$now) : 'Wir melden uns schnellstmöglich'));
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $fail ? 'Rückruf anfordern' : 'Rückruf anfordern · '.$e($company ?: 'Kontakt') ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--line2:#d6d9e4;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee;--indigo:#4f46e5;--indigol:#eceafe}
*{box-sizing:border-box}html,body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:640px;margin:26px auto 60px;padding:0 16px}.hd{text-align:center;margin:10px 0 20px}.hd .eb{font:700 12px Inter,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}
.hd h1{font:600 clamp(26px,5vw,36px)/1.15 Fraunces,Georgia,serif;margin:8px 0 6px;overflow-wrap:anywhere}.hd p{color:var(--ink2);margin:0}
.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.demo{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:14px;background:var(--indigol);color:#3730a3;font-size:13.5px;margin-bottom:14px}
.pills{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px}.pill{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:999px;background:#f1f2f7;color:var(--ink2);font-size:12.5px;border:1px solid var(--line)}
.pill.live{background:var(--okl);color:#15703f;border-color:#bfe8cf}.pill.live i{width:8px;height:8px;border-radius:50%;background:var(--ok);display:inline-block;animation:pu 1.6s infinite}
@keyframes pu{0%{box-shadow:0 0 0 0 rgba(26,158,90,.5)}70%{box-shadow:0 0 0 8px rgba(26,158,90,0)}100%{box-shadow:0 0 0 0 rgba(26,158,90,0)}}
label{display:block;font:600 12.5px Inter,sans-serif;color:var(--mute);margin:14px 0 6px}label .o{font-weight:500;color:#9094a6}
input[type=text],input[type=tel],input[type=email],select,textarea{width:100%;padding:12px 13px;border-radius:12px;border:1px solid var(--line2);font:inherit;color:var(--ink);background:#fff;outline:0}
input:focus,select:focus,textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.14)}textarea{min-height:84px;resize:vertical}
input.bad{border-color:#e58aa6;box-shadow:0 0 0 3px rgba(217,51,107,.1)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}.g2>div>label{margin-top:14px}@media(max-width:560px){.g2{grid-template-columns:1fr;gap:0}}
.opts{display:grid;gap:8px}.opt{display:flex;gap:12px;align-items:center;margin:0;padding:12px 14px;border:1px solid var(--line2);border-radius:14px;cursor:pointer;color:var(--ink);font:500 14.5px Inter,sans-serif;background:#fff;transition:.15s}
.opt:hover{border-color:var(--gold)}.opt.on{border-color:var(--gold);background:var(--goldl)}.opt input{width:18px;height:18px;accent-color:var(--gold);flex:0 0 auto;margin:0}.opt b{display:block;font-size:15px}.opt small{display:block;color:var(--mute);font-size:12.5px;font-weight:500}
.sub{margin-top:8px}.sub select{margin-top:0}
.consent{display:flex;gap:10px;align-items:flex-start;margin:16px 0 0;padding:12px 14px;border-radius:14px;background:#f6f7fb;border:1px solid var(--line);color:var(--ink2);font:500 13.5px/1.5 Inter,sans-serif;cursor:pointer}
.consent input{width:18px;height:18px;accent-color:var(--gold);flex:0 0 auto;margin:2px 0 0}.consent.bad{border-color:#e58aa6;background:var(--rosel)}
.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 18px;border-radius:14px;border:1px solid var(--line2);background:#fff;color:var(--ink);font:700 15.5px Inter,sans-serif;cursor:pointer;text-decoration:none;width:100%;margin-top:16px}
.btn.gold{background:linear-gradient(135deg,#ecd08a,#c9972f);color:#1a1407;border-color:transparent}.btn:disabled{opacity:.6;cursor:wait}
.msg{padding:10px 12px;border-radius:12px;font-size:14px;margin-top:12px}.err{background:var(--rosel);color:var(--rose)}
.priv{color:var(--mute);font-size:12px;line-height:1.5;margin:14px 0 0}
.done{text-align:center}.big{width:66px;height:66px;border-radius:50%;background:var(--okl);color:var(--ok);display:grid;place-items:center;font-size:30px;margin:4px auto 12px}
.done h2{font:600 24px Fraunces,Georgia,serif;margin:0 0 8px}.done p{color:var(--ink2);margin:0 auto;max-width:46ch}
.sum{text-align:left;padding:14px 16px;border-radius:16px;background:var(--goldl);margin:16px 0 0;font-size:14.5px}.sum div{display:flex;justify-content:space-between;gap:12px;padding:3px 0}.sum span{color:#6b5a2e}.sum b{text-align:right;overflow-wrap:anywhere}
.ring{display:flex;align-items:center;gap:12px;justify-content:center;margin-top:16px;padding:12px 14px;border-radius:14px;background:var(--okl);color:#15703f;font-weight:600;font-size:14px}
.ring i{font-style:normal;font-size:22px;display:inline-block;animation:sh 1.2s infinite}@keyframes sh{0%,100%{transform:rotate(0)}10%,30%{transform:rotate(-14deg)}20%,40%{transform:rotate(14deg)}50%{transform:rotate(0)}}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:18px}.foot a{color:var(--gold);text-decoration:none}
.fail{text-align:center;padding:26px 18px}.fail .big{background:var(--rosel);color:var(--rose)}
</style></head><body><div class="w">
<?php if($fail): ?>
<div class="hd"><div class="eb">Rückruf anfordern</div></div>
<div class="c fail"><div class="big">!</div><h2 style="font:600 22px Fraunces,Georgia,serif;margin:0 0 8px"><?= $e($fail[0]) ?></h2><p class="priv" style="font-size:14px;color:#3b4055"><?= $e($fail[1]) ?></p></div>
<?php else: ?>
<div class="hd"><div class="eb">Rückruf anfordern</div><h1><?= $e($company ?: 'Wir rufen Sie zurück') ?></h1>
  <p><?= $instant ? 'Hinterlassen Sie Ihre Nummer – wir rufen Sie in der Regel innerhalb von 60 Sekunden zurück.' : 'Hinterlassen Sie Ihre Nummer – wir rufen Sie zu Ihrer Wunschzeit zurück.' ?></p></div>
<div class="c" id="formCard">
  <?php if($demo): ?><div class="demo"><span>ℹ️</span><span><b>Live-Demo:</b> So sieht das Rückrufformular eines Maklers aus. Probieren Sie es gern aus – in der Demo wird niemand angerufen und nichts verschickt.</span></div><?php endif; ?>
  <div class="pills"><span class="pill">🕘 Rückrufzeiten: <?= $e(cb_window_text($s)) ?></span><?php if($instant): ?><span class="pill live"><i></i> Sofort-Rückruf aktiv</span><?php endif; ?></div>
  <form id="f" novalidate autocomplete="on">
    <div class="g2">
      <div><label for="fName">Name</label><input type="text" id="fName" autocomplete="name" maxlength="120" placeholder="Vor- und Nachname" required></div>
      <div><label for="fPhone">Telefonnummer</label><input type="tel" id="fPhone" autocomplete="tel" maxlength="40" placeholder="0170 1234567" inputmode="tel" required></div>
    </div>
    <label for="fEmail">E-Mail <span class="o">(optional)</span></label><input type="email" id="fEmail" autocomplete="email" maxlength="190" placeholder="name@beispiel.de">
    <label for="fMsg">Worum geht es? <span class="o">(optional)</span></label><textarea id="fMsg" maxlength="500" placeholder="z. B. Besichtigung, Rückfrage zu einem Angebot, Terminwunsch …"><?= $e($topic) ?></textarea>
    <label id="whenLbl">Wann dürfen wir anrufen?</label>
    <div class="opts" role="radiogroup" aria-labelledby="whenLbl">
      <label class="opt on"><input type="radio" name="when" value="asap" checked><span><b>So schnell wie möglich</b><small><?= $e($asapNote) ?></small></span></label>
      <label class="opt" id="optToday" hidden><input type="radio" name="when" value="today"><span><b>Heute zu einer bestimmten Uhrzeit</b><small>Innerhalb unserer Rückrufzeiten</small></span></label>
      <label class="opt" id="optDate"><input type="radio" name="when" value="date"><span><b>Tag &amp; Uhrzeit wählen</b><small>Bis zu 10 Rückruftage im Voraus</small></span></label>
    </div>
    <div class="sub" id="todayBox" hidden><label for="fToday" style="margin-top:4px">Uhrzeit heute</label><select id="fToday"></select></div>
    <div class="sub g2" id="dateBox" hidden><div><label for="fDay" style="margin-top:4px">Tag</label><select id="fDay"></select></div><div><label for="fTime" style="margin-top:4px">Uhrzeit</label><select id="fTime"></select></div></div>
    <label class="consent" id="consentBox"><input type="checkbox" id="fConsent" required><span id="consentText"><?= $e($consent) ?></span></label>
    <input type="text" id="fWebsite" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div id="msg" role="alert" aria-live="polite"></div>
    <button class="btn gold" id="send" type="submit">📞 Rückruf anfordern</button>
    <p class="priv">Datenschutz: Ihre Angaben (Name, Telefonnummer, ggf. E-Mail und Anliegen) verwendet <?= $e($company ?: 'das Unternehmen') ?> ausschließlich, um Ihre Rückrufbitte zu bearbeiten.<?= $ai ? ' Der Rückruf kann durch einen digitalen KI-Telefonassistenten erfolgen; das Gespräch wird zur Bearbeitung protokolliert.' : '' ?> Ihre Einwilligung wird mit Zeitpunkt gespeichert und kann jederzeit widerrufen werden – zum Beispiel einfach im Gespräch. Wir rufen nur innerhalb der Rückrufzeiten an. Technischer Dienstleister: ChatHelp (chat-help.com).</p>
  </form>
</div>
<div class="c done" id="doneCard" hidden>
  <div class="big">✓</div><h2 id="dTitle">Vielen Dank!</h2><p id="dMsg"></p>
  <div class="ring" id="dRing" hidden><i>📞</i><span id="dRingTxt">Halten Sie Ihr Telefon bereit – gleich klingelt es.</span></div>
  <div class="sum" id="dSum" hidden></div>
  <p class="priv" id="dDemo" hidden></p>
</div>
<?php endif; ?>
<div class="foot">Rückruf-Service von <a href="https://chat-help.com" target="_blank" rel="noopener">ChatHelp</a></div>
</div>
<?php if(!$fail): ?>
<script>
(function(){
  var K=<?= json_encode($k) ?>, API='/chat/lead-api.php?k='+encodeURIComponent(K), SLOTS=<?= json_encode($slots,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>, TS=<?= time() ?>, SRC=<?= json_encode($srcParam,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, INSTANT=<?= $instant ? 'true' : 'false' ?>, DEMO=<?= $demo ? 'true' : 'false' ?>;
  function $(s){ return document.querySelector(s); }
  function esc(s){ return String(s==null ? '' : s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function opts(sel,list){ sel.innerHTML=list.map(function(o){ return '<option value="'+esc(o[0])+'">'+esc(o[1])+'</option>'; }).join(''); }
  var today=SLOTS.filter(function(d){ return d.today; })[0]||null, days=SLOTS;
  if(today){ $('#optToday').hidden=false; opts($('#fToday'),today.slots.map(function(t){ return [t,t+' Uhr']; })); }
  if(days.length){ opts($('#fDay'),days.map(function(d){ return [d.date,d.label]; })); } else { $('#optDate').hidden=true; }
  function dayTimes(){ var d=days.filter(function(x){ return x.date===$('#fDay').value; })[0]; opts($('#fTime'),(d ? d.slots : []).map(function(t){ return [t,t+' Uhr']; })); }
  if(days.length) dayTimes();
  $('#fDay').addEventListener('change',dayTimes);
  function mode(){ var r=document.querySelector('input[name=when]:checked'); return r ? r.value : 'asap'; }
  function sync(){ var m=mode(); [].forEach.call(document.querySelectorAll('.opt'),function(o){ o.classList.toggle('on',o.querySelector('input').checked); }); $('#todayBox').hidden=m!=='today'; $('#dateBox').hidden=m!=='date'; }
  [].forEach.call(document.querySelectorAll('input[name=when]'),function(r){ r.addEventListener('change',sync); });
  function fail(t,el){ $('#msg').innerHTML='<div class="msg err">'+esc(t)+'</div>'; if(el){ el.classList.add('bad'); if(el.focus) el.focus(); } }
  ['#fName','#fPhone','#fEmail'].forEach(function(id){ $(id).addEventListener('input',function(){ this.classList.remove('bad'); }); });
  $('#fConsent').addEventListener('change',function(){ $('#consentBox').classList.remove('bad'); });
  $('#f').addEventListener('submit',function(ev){
    ev.preventDefault(); $('#msg').innerHTML='';
    var name=$('#fName').value.trim(), phone=$('#fPhone').value.trim(), email=$('#fEmail').value.trim(), m=mode(), when='';
    if(name.length<2) return fail('Bitte geben Sie Ihren Namen an.',$('#fName'));
    if(phone.replace(/\D/g,'').length<6) return fail('Bitte geben Sie Ihre Telefonnummer mit Vorwahl an.',$('#fPhone'));
    if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail('Die E-Mail-Adresse sieht nicht vollständig aus.',$('#fEmail'));
    if(m==='today' && today) when=today.date+' '+$('#fToday').value; else if(m==='date') when=$('#fDay').value+' '+$('#fTime').value;
    if(!$('#fConsent').checked){ $('#consentBox').classList.add('bad'); return fail('Bitte bestätigen Sie die Einwilligung in den Rückruf.'); }
    var b=$('#send'); b.disabled=true; b.textContent='Wird gesendet …';
    var body={name:name,phone:phone,email:email,message:$('#fMsg').value.trim(),preferred_time:when,consent:1,consent_text:$('#consentText').textContent,website:$('#fWebsite').value,form_ts:TS,via:'hosted'};
    if(SRC) body.source=SRC;
    fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'Unerwartete Antwort – bitte erneut versuchen.'}; }); })
      .then(function(j){
        if(!j || !j.ok){ b.disabled=false; b.textContent='📞 Rückruf anfordern'; return fail((j && j.message) || 'Das hat leider nicht geklappt – bitte erneut versuchen.'); }
        var cb=j.callback||null, first=name.split(' ')[0];
        $('#formCard').hidden=true; $('#doneCard').hidden=false; $('#dTitle').textContent='Vielen Dank, '+first+'!'; $('#dMsg').textContent=j.message||'Ihre Rückrufbitte ist eingegangen.';
        if(cb){ $('#dSum').hidden=false; $('#dSum').innerHTML='<div><span>Rückruf an</span><b>'+esc(cb.phone)+'</b></div><div><span>Wann</span><b>'+esc(cb.preferred)+'</b></div><div><span>Es ruft an</span><b>'+esc(cb.mode_label)+'</b></div><div><span>Ihre Vorgangsnummer</span><b>'+esc(j.code)+'</b></div>';
          if(cb.mode==='ai' && !cb.preferred_at && INSTANT){ $('#dRing').hidden=false; if(DEMO) $('#dRingTxt').textContent='Im echten Betrieb klingelt jetzt Ihr Telefon – der digitale Assistent ruft innerhalb von 60 Sekunden an.'; } }
        if(j.demo){ $('#dDemo').hidden=false; $('#dDemo').textContent=j.demo; }
        window.scrollTo({top:0,behavior:'smooth'});
      }).catch(function(){ b.disabled=false; b.textContent='📞 Rückruf anfordern'; fail('Keine Verbindung – bitte prüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.'); });
  });
})();
</script>
<?php endif; ?>
</body></html>
