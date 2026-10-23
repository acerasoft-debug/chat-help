/* ChatHelp Studio — Panel „🎙 Stimmen“: kundenseitige Stimme je Agent (Telefon, Sprachnachrichten) aus dem Stimmenkatalog (voice-catalog.php).
   Agent wählen → Sprache = Telefonsprache des Agenten → Tabs Frau/Mann → Karten (Name, Klang, Stufe Echt/Variante/Premium) → Hörprobe → speichern (agent_configs.voice_id).
   Hörprobe: echte Stimmen (Piper) im Browser über /chat/voice/voice.js (lädt das Modell einmalig), Premium über POST /chat/voice-api.php/v1/voices/sample.
   Nutzt die Studio-Globals esc, msg, KEY, PLANS, ME, I18N/_t. Einbindung in studio.php: $CH_PANELS 'voices'=>'Stimmen' + Tab data-v="voices". */
(function () {
  'use strict';
  var PANEL = 'panel-voices', API = '/chat/voice-api.php/v1';
  var g = function (id) { return document.getElementById(id); };
  var h = function (v) { return esc(v == null ? '' : String(v)); };
  var key = function () { try { return KEY; } catch (e) { return ''; } };
  var S = { plan: '', lang: 'de', g: 'f', list: { f: [], m: [] }, cur: '', pick: '', prov: {}, pip: {}, pct: {}, audio: null, url: null, playing: '', js: null, bound: false, gen: 0 };
  function err(e) { var d = (e && e.data) || {}; return I18N.lang === 'de' ? (d.message || (e && e.message) || 'Fehler') : _te(e); }
  function req(path, opts) {
    opts = opts || {};
    return fetch(API + path + (path.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), { method: opts.method || 'GET', cache: 'no-store', body: opts.body, headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + key() } })
      .then(function (r) { var ct = r.headers.get('Content-Type') || ''; if (ct.indexOf('audio') === 0 && r.ok) return r.blob(); return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(j.message || j.error || ('HTTP ' + r.status)); e.data = j; e.status = r.status; throw e; } return j; }); });
  }
  function desc(e) { return (e.desc || []).map(function (t) { return I18N.has('vc.d.' + t) ? _t('vc.d.' + t) : t; }).join(' · '); }
  function badge(e) { return '<span class="vc-tier t-' + h(e.tier) + '" title="' + h(_t('vc.tierT.' + e.tier)) + '">' + h(_t('vc.tier.' + e.tier)) + '</span>'; }
  function entry(id) { var x = null; ['f', 'm'].forEach(function (gg) { S.list[gg].forEach(function (e) { if (e.id === id) x = e; }); }); return x; }
  function plans() { var P = []; try { (PLANS || []).forEach(function (p) { if (p.kind === 'agent') P.push(p); }); } catch (e) { /* kein PLANS */ } return P; }
  function isActive(id) { try { return (ME.plans || []).some(function (p) { return p.id === id && p.active; }) || (ME.configs || []).some(function (c) { return c.plan === id; }); } catch (e) { return false; } }
  /* ---------- Piper im Browser ---------- */
  function pipLib() { if (window.CHVoice || S.js) return; S.js = document.createElement('script'); S.js.src = '/chat/voice/voice.js?v=4'; S.js.async = true; S.js.onload = function () { regAll(); pipCheck(); render(); }; document.head.appendChild(S.js); }
  function regAll() { if (window.CHVoice && CHVoice.register) CHVoice.register(S.list.f.concat(S.list.m)); }
  function pipOf(id) { var e = entry(id); if (!e || !window.CHVoice || !CHVoice.ok() || !e.source || e.source.type !== 'piper') return null; return CHVoice.pickId(e.id); }
  function pipCheck() { var v = pipOf(S.pick || S.cur); if (!v || S.pip[v.key]) return; CHVoice.stored(v).then(function (ok) { if (ok && !S.pip[v.key]) { S.pip[v.key] = 'ready'; render(); } }); }
  function pipLoad(v, asked) {
    if (!v || S.pip[v.key] === 'loading' || S.pip[v.key] === 'ready') return;
    if (!asked && (S.pip[v.key] === 'fail' || !CHVoice.auto())) return;
    S.pip[v.key] = 'loading'; S.pct[v.key] = 0; render();
    CHVoice.load(v, function (l, t) { var p = t ? Math.min(99, Math.floor(l * 100 / t)) : 0; if (p >= (S.pct[v.key] || 0) + 4) { S.pct[v.key] = p; paintPip(); } })
      .then(function () { S.pip[v.key] = 'ready'; render(); }, function () { S.pip[v.key] = 'fail'; render(); });
  }
  /* ---------- Hörprobe ---------- */
  function audio() { if (!S.audio) { S.audio = new Audio(); S.audio.preload = 'auto'; } return S.audio; }
  function stop() { S.gen++; S.playing = ''; try { if (S.audio) { S.audio.pause(); S.audio.removeAttribute('src'); S.audio.load(); } } catch (e) { /* egal */ } if (S.url) { try { URL.revokeObjectURL(S.url); } catch (e) { /* egal */ } S.url = null; } try { if (window.speechSynthesis) speechSynthesis.cancel(); } catch (e) { /* egal */ } mark(); }
  function mark() { document.querySelectorAll('#vcGrid .pl').forEach(function (b) { var on = b.dataset.play === S.playing; b.classList.toggle('playing', on); b.textContent = on ? _t('vc.stop') : _t('vc.sample'); }); }
  function sampleText(lang, name) {
    var T = { de: 'Guten Tag, hier ist {n}. Schön, dass Sie anrufen – wie kann ich Ihnen helfen?', en: 'Hello, this is {n}. Thanks for calling – how can I help you today?', fr: 'Bonjour, ici {n}. Merci de votre appel – que puis-je faire pour vous ?',
      tr: 'Merhaba, ben {n}. Aradığınız için teşekkürler – size nasıl yardımcı olabilirim?', az: 'Salam, mən {n}. Zəng etdiyiniz üçün təşəkkür edirəm – sizə necə kömək edə bilərəm?', es: 'Hola, soy {n}. Gracias por llamar, ¿en qué puedo ayudarle?',
      it: 'Buongiorno, sono {n}. Grazie per la chiamata – come posso aiutarla?', ru: 'Здравствуйте, это {n}. Спасибо за звонок – чем могу помочь?', nl: 'Goedendag, u spreekt met {n}. Fijn dat u belt – waarmee kan ik u helpen?',
      sv: 'Hej, det här är {n}. Tack för att du ringer – hur kan jag hjälpa dig?', no: 'Hei, dette er {n}. Takk for at du ringer – hva kan jeg hjelpe deg med?', fi: 'Hei, täällä {n}. Kiitos soitostasi – miten voin auttaa?', ja: 'こんにちは、{n}でございます。お電話ありがとうございます。ご用件をお伺いいたします。' };
    return (T[lang] || T.de).split('{n}').join(name);
  }
  function playBlob(blob, gen) {
    if (gen !== S.gen) return;
    var a = audio(); if (S.url) { try { URL.revokeObjectURL(S.url); } catch (e) { /* egal */ } }
    S.url = URL.createObjectURL(blob); a.src = S.url;
    a.onended = function () { if (gen === S.gen) { S.playing = ''; mark(); } }; a.onerror = function () { if (gen === S.gen) { S.playing = ''; mark(); } };
    var p = a.play(); if (p && p.catch) p.catch(function () { if (gen === S.gen) { S.playing = ''; mark(); } });
  }
  function browserSay(text, lang, gen) {
    if (!window.speechSynthesis) { S.playing = ''; mark(); return; }
    try { var u = new SpeechSynthesisUtterance(text); u.lang = ({ de: 'de-DE', en: 'en-GB', fr: 'fr-FR', es: 'es-ES', tr: 'tr-TR', az: 'az-AZ', ru: 'ru-RU', it: 'it-IT', nl: 'nl-NL', sv: 'sv-SE', no: 'nb-NO', fi: 'fi-FI', ja: 'ja-JP' })[lang] || 'de-DE';
      u.onend = u.onerror = function () { if (gen === S.gen) { S.playing = ''; mark(); } }; speechSynthesis.speak(u); } catch (e) { S.playing = ''; mark(); }
  }
  function sample(id) {
    var e = entry(id); if (!e) return;
    if (S.playing === id) { stop(); return; }
    stop(); var gen = S.gen; S.playing = id; mark();
    var text = sampleText(S.lang, e.label);
    if (e.source && e.source.type === 'piper') {
      var v = pipOf(id);
      if (v && S.pip[v.key] === 'ready') { CHVoice.say(text, v).then(function (b) { playBlob(b, gen); }, function () { if (gen === S.gen) { S.pip[v.key] = 'fail'; render(); browserSay(text, S.lang, gen); } }); return; }
      if (v) pipLoad(v, false);
      browserSay(text, S.lang, gen); return;   // bis das Modell da ist: Systemstimme
    }
    req('/voices/sample', { method: 'POST', body: JSON.stringify({ id: id }) }).then(function (b) { if (b instanceof Blob) playBlob(b, gen); else throw new Error('audio'); })
      .catch(function (x) { if (gen !== S.gen) return; S.playing = ''; mark(); msg(g('vcMsg'), h(err(x) || _t('vc.p.sampleFail')), 'err'); });
  }
  /* ---------- Zeichnen ---------- */
  function paintPip() {
    var box = g('vcPip'); if (!box) return;
    var v = pipOf(S.pick || S.cur); if (!v) { box.innerHTML = ''; return; }
    var st = S.pip[v.key];
    if (st === 'ready') box.innerHTML = h(_t('asj.pipOn')) + ' · ' + h(_t('asj.oss', { n: v.name })) + ' · <a href="/chat/vendor/voice/LICENSES.txt" target="_blank" rel="noopener">' + _t('asj.lic') + '</a>';
    else if (st === 'loading') box.innerHTML = h(_t('asj.pipLoading', { p: S.pct[v.key] || 0, mb: I18N.num(v.mb) }));
    else if (st === 'fail') box.innerHTML = h(_t('asj.pipFail')) + ' <button type="button" data-pip="1">' + _t('pw.retry') + '</button>';
    else box.innerHTML = '<button type="button" data-pip="1">' + _t('asj.pipLoad') + '</button> ' + h(_t('asj.pipOnce', { mb: I18N.num(v.mb) }));
  }
  function render() {
    var grid = g('vcGrid'); if (!grid) return;
    var list = S.list[S.g] || [], sel = S.pick || S.cur;
    document.querySelectorAll('#vcTabs button').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.g === S.g ? 'true' : 'false'); });
    grid.innerHTML = list.length ? list.map(function (e) {
      var on = e.id === sel;
      return '<div class="vc-card' + (on ? ' on' : '') + '" role="listitem" data-tier="' + h(e.tier) + '"><button type="button" class="sel" data-v="' + h(e.id) + '" aria-pressed="' + (on ? 'true' : 'false') + '" aria-label="' + h(_t('vc.pickN', { n: e.label })) + '"><b>' + h(e.label) + badge(e) + '</b>' + h(desc(e)) +
        (e.source && e.source.type === 'piper' && e.mb ? ' <span class="vc-mb">· ' + h(_t('vc.onceMb', { mb: I18N.num(e.mb) })) + '</span>' : '') + '</button>' +
        '<button type="button" class="pl" data-play="' + h(e.id) + '" aria-label="' + h(_t('vc.sampleN', { n: e.label })) + '">' + _t('vc.sample') + '</button></div>';
    }).join('') : '<div class="mute small">' + h(_t('vc.none')) + '</div>';
    var cur = entry(S.cur), pk = entry(S.pick), box = g('vcCur');
    box.innerHTML = (cur ? h(_t('vc.p.current', { n: '' })) + '<b>' + h(cur.label) + '</b> ' + badge(cur) + ' <span class="mute">' + h(desc(cur)) + '</span>' : '<span class="mute">' + h(_t('vc.p.pool')) + '</span>') +
      (pk && pk.id !== S.cur ? '<button type="button" class="btn p" id="vcSave">' + h(_t('vc.p.save')) + ' · ' + h(pk.label) + '</button>' : (cur ? '<button type="button" class="btn ghost s" id="vcReset">' + h(_t('vc.p.reset')) + '</button>' : ''));
    var anyPrem = ['f', 'm'].some(function (gg) { return S.list[gg].some(function (e) { return e.tier === 'premium'; }); });
    g('vcPremOff').classList.toggle('hidden', anyPrem);
    paintPip(); mark();
  }
  function load() {
    var plan = S.plan; if (!plan) return Promise.resolve();
    msg(g('vcMsg'), '');
    return req('/voices/agent?plan=' + encodeURIComponent(plan)).then(function (j) {
      if (S.plan !== plan) return;
      S.lang = j.lang || 'de'; S.list = { f: (j.voices && j.voices.f) || [], m: (j.voices && j.voices.m) || [] }; S.cur = j.voice_id || ''; S.pick = ''; S.prov = j.providers || {};
      S.g = j.gender === 'm' ? 'm' : 'f';
      var L = (I18N.langs || []).filter(function (x) { return x[0] === S.lang; })[0]; g('vcLang').textContent = L ? L[1] + ' · ' + S.lang.toUpperCase() : S.lang.toUpperCase();
      regAll(); render(); pipCheck();
    }).catch(function (e) { msg(g('vcMsg'), h(err(e)), 'err'); });
  }
  function save(id) {
    var btn = g('vcSave') || g('vcReset'); if (btn) btn.disabled = true;
    req('/voices/agent', { method: 'POST', body: JSON.stringify({ plan: S.plan, voice_id: id }) }).then(function (j) {
      S.cur = j.voice_id || ''; S.pick = ''; render();
      var e = entry(S.cur); msg(g('vcMsg'), h(e ? _t('vc.p.saved', { n: e.label }) : _t('c.saved')), 'ok');
    }).catch(function (e) { if (btn) btn.disabled = false; msg(g('vcMsg'), h(err(e)), 'err'); });
  }
  function bind() {
    if (S.bound) return; S.bound = true;
    pipLib();
    g('vcPlan').addEventListener('change', function () { stop(); S.plan = this.value; load(); });
    g('vcTabs').addEventListener('click', function (e) { var b = e.target.closest('button[data-g]'); if (b) { S.g = b.dataset.g; render(); } });
    g('vcGrid').addEventListener('click', function (e) {
      var pl = e.target.closest('.pl[data-play]'); if (pl) { S.pick = pl.dataset.play; render(); sample(pl.dataset.play); return; }
      var b = e.target.closest('.sel[data-v]'); if (b) { S.pick = b.dataset.v; render(); pipCheck(); }
    });
    g(PANEL).addEventListener('click', function (e) {
      if (e.target.closest('#vcSave')) { save(S.pick); return; }
      if (e.target.closest('#vcReset')) { save(''); return; }
      if (e.target.closest('[data-pip]')) { var v = pipOf(S.pick || S.cur); if (v) pipLoad(v, true); }
    });
    var panel = g(PANEL);
    if (panel && window.MutationObserver) new MutationObserver(function () { if (panel.classList.contains('hidden')) stop(); }).observe(panel, { attributes: true, attributeFilter: ['class'] });
  }
  function fillPlans() {
    var sel = g('vcPlan'), P = plans();
    if (!P.length) { sel.innerHTML = ''; g('vcGrid').innerHTML = '<div class="mute small">' + h(_t('vc.p.noPlans')) + '</div>'; return false; }
    P.sort(function (a, b) { return (isActive(b.id) ? 1 : 0) - (isActive(a.id) ? 1 : 0); });
    sel.innerHTML = P.map(function (p) { return '<option value="' + h(p.id) + '">' + h(p.name) + (isActive(p.id) ? ' · ' + _t('sj.active.l') : '') + '</option>'; }).join('');
    var pre = null; try { pre = (ME.configs || []).filter(function (c) { return +c.phone_on; })[0]; } catch (e) { /* egal */ }
    S.plan = (pre && P.some(function (p) { return p.id === pre.plan; })) ? pre.plan : P[0].id; sel.value = S.plan;
    return true;
  }
  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  STUDIO_PANELS['voices'] = {
    init: function () { bind(); if (fillPlans()) load(); },
    show: function () { if (!S.plan && fillPlans()) load(); }
  };
})();
