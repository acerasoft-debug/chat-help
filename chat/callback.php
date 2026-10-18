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
require_once __DIR__.'/i18n.php';
th_nocache(); th_bootstrap(); cb_bootstrap(); th_fresh();
header('Content-Type: text/html; charset=UTF-8'); header('X-Robots-Tag: noindex');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$k=is_scalar($_GET['k'] ?? null) ? (string)$_GET['k'] : ''; $kk=cb_key_account($k);
[$defL,$langs]=ch_account_langs($kk ? (int)$kk['account_id'] : 0); $L=ch_page_lang($langs,$defL);   // ?lang= → Cookie → Accept-Language → CF-IPCountry → Standard des Betriebs
$t=fn($k,$v=[])=>t($k,$L,$v);
$fail=null;
if(!$kk){ http_response_code(404); $fail=[$t('cb.f1'),$t('cb.f1t')]; }
elseif(!cb_enabled($kk['account_id'])){ http_response_code(403); $fail=[$t('cb.f2'),$t('cb.f2t')]; }
elseif(!cb_public_ok($kk['account_id'],$kk['plan'])){ http_response_code(403); $fail=[$t('cb.f3'),$t('cb.f3t')]; }
$acc=$kk ? $kk['account_id'] : -1; $s=$kk ? cb_settings($acc) : null; $company=$kk ? cb_company($acc,$s,$kk) : '';
$demo=$acc===0; $ai=$s && $s['ai_callback']===1; $now=$s ? cb_now($s) : new DateTimeImmutable('now');
$inWin=$s && cb_in_window($s,$now); $instant=$ai && $s['lead_instant']===1 && $inWin;
$next=$s && !$inWin ? cb_next_window($s,$now) : null;
$slots=$s ? cb_slots($s,$now,10,30) : [];
$consent=$L==='de' ? cb_consent_text($company,$ai) : $t($ai ? 'cb.consent_ai' : 'cb.consent',['company'=>$company!=='' ? $company : $t('cb.thecompany')]);   // gespeichert wird der angezeigte Wortlaut
if($L!=='de'){   // Tagesnamen der Wunschzeit (callback-lib: „Heute, 18.10.“) in der Seitensprache
    foreach($slots as $i=>$d){ $dd=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$d['date'],$now->getTimezone()); if(!$dd) continue;
        $diff=(int)$now->setTime(0,0)->diff($dd)->format('%r%a'); $w=[0=>'today',1=>'tomorrow',2=>'dayafter'][$diff] ?? '';
        $lab=($w!=='' ? $t($w) : ch_fmt_wd($dd,$L,true)).', '.ch_fmt_date($dd,$L,'dm'); $slots[$i]['label']=mb_strtoupper(mb_substr($lab,0,1)).mb_substr($lab,1); } }
$winText=$s ? ch_de_phrase(cb_window_text($s),$L) : '';
$topic=is_scalar($_GET['topic'] ?? null) ? cb_line($_GET['topic'],300) : ''; $srcParam=is_scalar($_GET['source'] ?? null) ? cb_line($_GET['source'],40) : '';
$asapNote=$instant ? $t('cb.asap60') : ($inWin ? $t('cb.asapq') : ($next ? $t('cb.earliest',['when'=>$L==='de' ? cb_when_label($next,$now) : ch_fmt_when($next,$now,$L)]) : $t('cb.asapq')));
?><!DOCTYPE html><html lang="<?= $e(CH_LANGS[$L]['tag']) ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $fail ? $e($t('cb.eb')) : $e($t('cb.eb')).' · '.$e($company ?: $t('cb.h')) ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--line2:#d6d9e4;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee;--indigo:#4f46e5;--indigol:#eceafe}
*{box-sizing:border-box}[hidden]{display:none !important}html{overflow-x:hidden}html,body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
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
</style></head><body><?= ch_lang_switcher($L,$langs) ?><div class="w">
<?php if($fail): ?>
<div class="hd"><div class="eb"><?= $e($t('cb.eb')) ?></div></div>
<div class="c fail"><div class="big">!</div><h2 style="font:600 22px Fraunces,Georgia,serif;margin:0 0 8px"><?= $e($fail[0]) ?></h2><p class="priv" style="font-size:14px;color:#3b4055"><?= $e($fail[1]) ?></p></div>
<?php else: ?>
<div class="hd"><div class="eb"><?= $e($t('cb.eb')) ?></div><h1><?= $e($company ?: $t('cb.h')) ?></h1>
  <p><?= $e($t($instant ? 'cb.i_fast' : 'cb.i')) ?></p></div>
<div class="c" id="formCard">
  <?php if($demo): ?><div class="demo"><span>ℹ️</span><span><b><?= $e($t('cb.demo_b')) ?></b> <?= $e($t('cb.demo')) ?></span></div><?php endif; ?>
  <div class="pills"><span class="pill">🕘 <?= $e($t('cb.hours',['t'=>$winText])) ?></span><?php if($instant): ?><span class="pill live"><i></i> <?= $e($t('cb.instant')) ?></span><?php endif; ?></div>
  <form id="f" novalidate autocomplete="on">
    <div class="g2">
      <div><label for="fName"><?= $e($t('f.name')) ?></label><input type="text" id="fName" autocomplete="name" maxlength="120" placeholder="<?= $e($t('f.name_ph')) ?>" required></div>
      <div><label for="fPhone"><?= $e($t('cb.phone')) ?></label><input type="tel" id="fPhone" autocomplete="tel" maxlength="40" placeholder="<?= $e($t('cb.phone_ph')) ?>" inputmode="tel" required></div>
    </div>
    <label for="fEmail"><?= $e($t('f.email')) ?> <span class="o"><?= $e($t('f.opt')) ?></span></label><input type="email" id="fEmail" autocomplete="email" maxlength="190" placeholder="<?= $e($t('f.email_ph')) ?>">
    <label for="fMsg"><?= $e($t('cb.what')) ?> <span class="o"><?= $e($t('f.opt')) ?></span></label><textarea id="fMsg" maxlength="500" placeholder="<?= $e($t('cb.what_ph')) ?>"><?= $e($topic) ?></textarea>
    <label id="whenLbl"><?= $e($t('cb.when')) ?></label>
    <div class="opts" role="radiogroup" aria-labelledby="whenLbl">
      <label class="opt on"><input type="radio" name="when" value="asap" checked><span><b><?= $e($t('cb.asap')) ?></b><small><?= $e($asapNote) ?></small></span></label>
      <label class="opt" id="optToday" hidden><input type="radio" name="when" value="today"><span><b><?= $e($t('cb.today')) ?></b><small><?= $e($t('cb.today_d')) ?></small></span></label>
      <label class="opt" id="optDate"><input type="radio" name="when" value="date"><span><b><?= $e($t('cb.date')) ?></b><small><?= $e($t('cb.date_d')) ?></small></span></label>
    </div>
    <div class="sub" id="todayBox" hidden><label for="fToday" style="margin-top:4px"><?= $e($t('cb.ttime')) ?></label><select id="fToday"></select></div>
    <div class="sub g2" id="dateBox" hidden><div><label for="fDay" style="margin-top:4px"><?= $e($t('cb.day')) ?></label><select id="fDay"></select></div><div><label for="fTime" style="margin-top:4px"><?= $e($t('cb.time')) ?></label><select id="fTime"></select></div></div>
    <label class="consent" id="consentBox"><input type="checkbox" id="fConsent" required><span id="consentText"><?= $e($consent) ?></span></label>
    <input type="text" id="fWebsite" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div id="msg" role="alert" aria-live="polite"></div>
    <button class="btn gold" id="send" type="submit"><?= $e($t('cb.btn')) ?></button>
    <p class="priv"><?= $e($t('cb.priv',['company'=>$company ?: $t('cb.thecompany')])) ?><?= $ai ? ' '.$e($t('cb.priv_ai')) : '' ?> <?= $e($t('cb.priv2')) ?></p>
  </form>
</div>
<div class="c done" id="doneCard" hidden>
  <div class="big">✓</div><h2 id="dTitle" tabindex="-1"><?= $e($t('cb.thanks')) ?></h2><p id="dMsg" aria-live="polite"></p>
  <div class="ring" id="dRing" hidden><i>📞</i><span id="dRingTxt"><?= $e($t('cb.ring')) ?></span></div>
  <div class="sum" id="dSum" hidden></div>
  <p class="priv" id="dDemo" hidden></p>
</div>
<?php endif; ?>
<div class="foot"><?= $t('cb.foot',['link'=>'<a href="https://chat-help.com" target="_blank" rel="noopener">ChatHelp</a>']) ?></div>
</div>
<?php if(!$fail): ?>
<script>
(function(){
  var K=<?= json_encode($k) ?>, API='/chat/lead-api.php?k='+encodeURIComponent(K), SLOTS=<?= json_encode($slots,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>, TS=<?= time() ?>, LANG=<?= json_encode($L) ?>, LANGNAME=<?= json_encode(CH_LANGS[$L]['de'].' ('.CH_LANGS[$L]['native'].')',JSON_UNESCAPED_UNICODE) ?>, LOC=<?= json_encode(CH_LANGS[$L]['tag']) ?>, T=<?= json_encode(ch_t_prefix('cb.',$L)+['clock'=>t('clock',$L)],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, SRC=<?= json_encode($srcParam,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, INSTANT=<?= $instant ? 'true' : 'false' ?>, DEMO=<?= $demo ? 'true' : 'false' ?>;
  function $(s){ return document.querySelector(s); }
  function clk(t){ return String(T.clock||'{t}').replace('{t}',t); }
  function esc(s){ return String(s==null ? '' : s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function opts(sel,list){ sel.innerHTML=list.map(function(o){ return '<option value="'+esc(o[0])+'">'+esc(o[1])+'</option>'; }).join(''); }
  var today=SLOTS.filter(function(d){ return d.today; })[0]||null, days=SLOTS;
  if(today){ $('#optToday').hidden=false; opts($('#fToday'),today.slots.map(function(t){ return [t,clk(t)]; })); }
  if(days.length){ opts($('#fDay'),days.map(function(d){ return [d.date,d.label]; })); } else { $('#optDate').hidden=true; }
  function dayTimes(){ var d=days.filter(function(x){ return x.date===$('#fDay').value; })[0]; opts($('#fTime'),(d ? d.slots : []).map(function(t){ return [t,clk(t)]; })); }
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
    if(name.length<2) return fail(T['e.name'],$('#fName'));
    if(phone.replace(/\D/g,'').length<6) return fail(T['e.phone'],$('#fPhone'));
    if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail(T['e.email'],$('#fEmail'));
    if(m==='today' && today) when=today.date+' '+$('#fToday').value; else if(m==='date') when=$('#fDay').value+' '+$('#fTime').value;
    if(!$('#fConsent').checked){ $('#consentBox').classList.add('bad'); return fail(T['e.consent'],$('#fConsent')); }
    var b=$('#send'); b.disabled=true; b.textContent=T.sending;
    var body={name:name,phone:phone,email:email,message:$('#fMsg').value.trim(),preferred_time:when,consent:1,consent_text:$('#consentText').textContent,website:$('#fWebsite').value,form_ts:TS,via:'hosted'};
    if(LANG!=='de') body.Sprache=LANGNAME;   // landet in den Details: Team bzw. KI-Rückruf weiß, in welcher Sprache zurückgerufen wird
    if(SRC) body.source=SRC;
    fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){ return r.json().catch(function(){ return {ok:false,message:T['e.odd']}; }); })
      .then(function(j){
        if(!j || !j.ok){ b.disabled=false; b.textContent=T.btn; return fail((LANG==='de' && j && j.message) || T['e.fail']); }
        var cb=j.callback||null, first=name.split(' ')[0], de=LANG==='de';
        $('#formCard').hidden=true; $('#doneCard').hidden=false; $('#dTitle').textContent=String(T.thanks_n).replace('{name}',first); $('#dMsg').textContent=(de && j.message)||T.got;
        if(cb){ var when=de ? cb.preferred : (cb.preferred_at ? new Date(String(cb.preferred_at).replace(' ','T')).toLocaleString(LOC,{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : T.asap_l);
          $('#dSum').hidden=false; $('#dSum').innerHTML='<div><span>'+esc(T.to)+'</span><b>'+esc(cb.phone)+'</b></div><div><span>'+esc(T.w)+'</span><b>'+esc(when)+'</b></div><div><span>'+esc(T.who)+'</span><b>'+esc(de ? cb.mode_label : (cb.mode==='ai' ? T['m.ai'] : T['m.team']))+'</b></div><div><span>'+esc(T.ref)+'</span><b>'+esc(j.code)+'</b></div>';
          if(cb.mode==='ai' && !cb.preferred_at && INSTANT){ $('#dRing').hidden=false; if(DEMO) $('#dRingTxt').textContent=T.ring_demo; } }
        if(j.demo){ $('#dDemo').hidden=false; $('#dDemo').textContent=de ? j.demo : T.demo_done; }
        window.scrollTo({top:0,behavior:'smooth'}); try{ $('#dTitle').focus({preventScroll:true}); }catch(e){}
      }).catch(function(){ b.disabled=false; b.textContent=T.btn; fail(<?= json_encode(t('netfail',$L),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS) ?>); });
  });
})();
</script>
<?php endif; ?>
</body></html>
