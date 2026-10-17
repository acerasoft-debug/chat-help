/* ChatHelp Studio — Panel „✦ Assistent“: persönliche Assistentin bzw. persönlicher Assistent des Inhabers (assistant-lib.php).
   Links das Porträt (atmet, goldener Ring + Stimmbalken beim Sprechen, Status „hört zu…/denkt…/spricht…“), in der Mitte das Gespräch
   mit Aktions-Chips (was geändert wurde + Link zum Bereich), rechts Checkliste mit Fortschrittsring und „Was ich über dich weiß“.
   Stimme: Server-Stimme über /v1/assistant/speak (speech-lib), sonst die beste Browserstimme passend zum Geschlecht – Satz für Satz.
   Spracheingabe: SpeechRecognition (Antippen oder gedrückt halten), im Sprachmodus hört der Assistent nach dem Sprechen wieder zu.
   Barge-in: Tippen, Mikrofon oder Senden beenden die laufende Ausgabe sofort.
   Nutzt die Studio-Globals api, msg, esc, de, go, ME, KEY. Jeder Text von Nutzer oder KI läuft vor innerHTML durch esc(). */
(function () {
  'use strict';
  var PANEL = 'panel-assistant';
  var API_BASE = '/chat/tokens-api.php/v1';
  var g = function (id) { return document.getElementById(id); };
  var h = function (v) { return esc(v == null ? '' : String(v)); };
  var visible = function () { var el = g(PANEL); return !!el && !el.closest('.hidden'); };
  var key = function () { try { return KEY; } catch (e) { return ''; } };
  var LS = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* privater Modus */ } }
  };
  var REDUCED = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var LANG_TAG = { de: 'de-DE', en: 'en-US', tr: 'tr-TR', fr: 'fr-FR', es: 'es-ES', it: 'it-IT', nl: 'nl-NL', pl: 'pl-PL', ru: 'ru-RU' };
  var TABN = { home: 'Übersicht', agents: 'Agenten', training: 'Training', phone: 'Telefon', rueckruf: 'Rückrufe', termine: 'Termine', bestellungen: 'Bestellungen',
    taxi: 'Taxi', hotel: 'Hotel', benachrichtigungen: 'Benachrichtigungen', tokens: 'Tokens & API' };
  var STATUS = { idle: 'bereit', listening: 'hört zu…', thinking: 'denkt…', speaking: 'spricht…' };
  var C_RING = 169.65;
  var CHECK = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  var SILENT_WAV = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';

  var PROF = null, PORTRAITS = [], VOICES = [], TTS = false, PHOTO_MAX = 358400, COST = { turn: 6, tool: 2 };
  var HIST = [], busy = false, bound = false, lastLoad = 0, state = 'idle';
  var voiceOn = LS.get('ch_as_voice') === '1', micUsed = false, lastViaVoice = false;
  var imgCache = {};

  /* ======================================================================= Hilfen */
  function isSie() { return !!PROF && PROF.personality && PROF.personality.address === 'sie'; }
  function fem(p) { return (p || PROF || {}).gender !== 'm'; }
  function lang() { return (PROF && LANG_TAG[PROF.lang]) || 'de-DE'; }
  function note(html, kind) { msg(g('asNote'), html, kind); if (html && kind !== 'err') { setTimeout(function () { if (g('asNote')) msg(g('asNote'), ''); }, 4500); } }
  function setBalance(b) {
    if (typeof b !== 'number') return;
    try { if (ME) ME.balance = b; } catch (e) { /* ME fehlt */ }
    ['bal', 'hBal', 'tBal'].forEach(function (id) { var el = g(id); if (el) el.textContent = de(b); });
  }
  /** data:-URLs (eigenes Foto, SVG-Platzhalter) einmal in eine Blob-URL wandeln – sonst stünde das Foto in jeder Sprechblase im DOM. */
  function imgUrl(u) {
    u = String(u || '');
    if (u.indexOf('data:') !== 0) return u;
    if (imgCache[u]) return imgCache[u];
    try {
      var m = u.match(/^data:([^;,]+)(;base64)?,(.*)$/);
      if (!m) return '';
      var raw = m[2] ? atob(m[3]) : decodeURIComponent(m[3]);
      var arr = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
      imgCache[u] = URL.createObjectURL(new Blob([arr], { type: m[1] }));
      return imgCache[u];
    } catch (e) { return ''; }
  }
  function avatar() { return PROF ? imgUrl(PROF.portrait_url) : ''; }
  function bold(s) { return s.replace(/\*\*([^*\n]{1,200})\*\*/g, '<b>$1</b>'); }
  /** Text sicher formatieren: erst Links herauslösen (nur http/https), alles andere escapen, **fett** erlauben. */
  function fmt(t) {
    t = String(t == null ? '' : t);
    var out = '', re = /https?:\/\/[^\s<>"'`]+/g, last = 0, m;
    while ((m = re.exec(t))) {
      var u = m[0].replace(/[.,;:!?)\]}]+$/, '');
      out += bold(h(t.slice(last, m.index))) + '<a href="' + h(u) + '" target="_blank" rel="noopener noreferrer">' + h(u.replace(/^https?:\/\//, '')) + '</a>';
      last = m.index + u.length; re.lastIndex = last;
    }
    return out + bold(h(t.slice(last)));
  }
  /** Sätze zum Vorlesen (für Verlauf und Hörprobe; Antworten bringen ihre Sätze vom Server mit). */
  function sentences(t) {
    t = String(t || '').replace(/https?:\/\/\S+/g, '').replace(/\*\*|__|`/g, '').replace(/^\s*[-•*]\s+/gm, '')
      .replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, ' ').replace(/\s+/g, ' ').trim();
    var parts = t.match(/[^.!?…]+(?:[.!?…]+|$)/g) || [], out = [];
    parts.forEach(function (p) {
      p = p.trim();
      while (p.length > 380) { var cut = p.lastIndexOf(', ', 380); if (cut < 60) cut = p.lastIndexOf(' ', 380); if (cut < 30) cut = 380; out.push(p.slice(0, cut + 1).trim()); p = p.slice(cut + 1).trim(); }
      if (p) out.push(p);
    });
    return out;
  }
  function scrollDown() { var b = g('asMsgs'); if (!b) return; try { b.scrollTo({ top: b.scrollHeight, behavior: REDUCED ? 'auto' : 'smooth' }); } catch (e) { b.scrollTop = b.scrollHeight; } }

  /* ======================================================================= Zustand des Porträts */
  function setState(s) {
    state = s;
    var por = g('asPor'); if (por) por.setAttribute('data-state', s);
    var st = g('asStatus'); if (st) st.textContent = STATUS[s] || STATUS.idle;
  }

  /* ======================================================================= Sprachausgabe */
  var SPK = { gen: 0, el: null, url: null, serverOk: true, vlist: [] };
  function audioEl() { if (!SPK.el) { SPK.el = new Audio(); SPK.el.preload = 'auto'; } return SPK.el; }
  /** Im Klick-Handler aufrufen: entsperrt Audio (Safari/iOS) und die Browserstimme. */
  function unlockAudio() {
    try { var a = audioEl(); if (!a.dataset.ok) { a.src = SILENT_WAV; var p = a.play(); if (p && p.catch) p.catch(function () {}); a.dataset.ok = '1'; } } catch (e) { /* egal */ }
    try { if (window.speechSynthesis && !SPK.synthOk) { var u = new SpeechSynthesisUtterance(' '); u.volume = 0; speechSynthesis.speak(u); SPK.synthOk = true; } } catch (e) { /* egal */ }
  }
  function speaking() { return state === 'speaking'; }
  function stopSpeaking() {
    SPK.gen++;
    if (SPK.el) { try { SPK.el.pause(); SPK.el.removeAttribute('src'); SPK.el.load(); } catch (e) { /* egal */ } }
    if (SPK.url) { try { URL.revokeObjectURL(SPK.url); } catch (e) { /* egal */ } SPK.url = null; }
    try { if (window.speechSynthesis) speechSynthesis.cancel(); } catch (e) { /* egal */ }
    if (state === 'speaking') setState('idle');
  }
  function presetOf(id, gender) {
    var p = null; VOICES.forEach(function (v) { if (v.id === id) p = v; });
    if (p && (!gender || p.gender === gender)) return p;
    var f = null; VOICES.forEach(function (v) { if (!f && v.gender === (gender || 'f')) f = v; });
    return f;
  }
  var NAMES_F = ['katja', 'seraphina', 'amala', 'anna', 'petra', 'helena', 'hedda', 'marlene', 'vicki', 'louisa', 'elke', 'gisela', 'klarissa', 'maja', 'tanja', 'ingrid', 'sabine', 'female', 'frau', 'aria', 'jenny', 'samantha', 'ava', 'emma', 'libby', 'sonia', 'zira', 'hazel', 'susan', 'karen', 'moira', 'tessa', 'fiona', 'serena', 'denise', 'elsa', 'isabella', 'paulina', 'milena', 'zosia', 'yelda', 'filiz', 'emel', 'amelie', 'audrey', 'marie', 'alice', 'federica', 'fenna', 'colette', 'dariya', 'svetlana'];
  var NAMES_M = ['conrad', 'florian', 'killian', 'markus', 'yannick', 'stefan', 'martin', 'hans', 'bernd', 'christoph', 'kasper', 'ralf', 'klaus', 'jonas', 'viktor', 'male', 'mann', 'guy', 'davis', 'andrew', 'brian', 'ryan', 'thomas', 'george', 'daniel', 'alex', 'fred', 'david', 'mark', 'oliver', 'henri', 'remy', 'diego', 'alvaro', 'cosimo', 'maarten', 'marek', 'dmitry', 'ahmet', 'xander', 'giuseppe', 'pavel'];
  function voiceGender(name) {
    var n = name.toLowerCase(), f = false, m = false;
    NAMES_F.forEach(function (x) { if (new RegExp('\\b' + x + '\\b').test(n)) f = true; });
    NAMES_M.forEach(function (x) { if (new RegExp('\\b' + x + '\\b').test(n)) m = true; });
    if (/google deutsch|google us english|google uk english female/.test(n)) f = true;
    if (/google uk english male/.test(n)) { m = true; f = false; }
    return f && !m ? 'f' : (m && !f ? 'm' : '');
  }
  /** Beste Browserstimme: Sprache passend, Geschlecht passend, Natural/Online/Neural/Premium/Enhanced/Google bevorzugt, Wunschnamen der Voreinstellung zuerst. */
  function pickVoice(gender, presetId) {
    if (!window.speechSynthesis) return null;
    var all = speechSynthesis.getVoices() || [], l2 = lang().slice(0, 2).toLowerCase(), pre = presetOf(presetId, gender), want = (pre && pre.browser) || [];
    var best = null, bestS = -1e9;
    all.forEach(function (v) {
      var vl = String(v.lang || '').toLowerCase().replace('_', '-');
      if (vl.slice(0, 2) !== l2) return;
      var n = String(v.name || ''), s = 0, vg = voiceGender(n);
      if (vl === lang().toLowerCase()) s += 6;
      if (vg === gender) s += 40; else if (vg) s -= 120;
      want.forEach(function (w, i) { if (n.toLowerCase().indexOf(String(w).toLowerCase()) >= 0) s += 30 - i * 3; });
      if (/natural/i.test(n)) s += 34; if (/online/i.test(n)) s += 12; if (/neural/i.test(n)) s += 26;
      if (/premium/i.test(n)) s += 30; if (/enhanced|erweitert/i.test(n)) s += 22; if (/google/i.test(n)) s += 16; if (/siri/i.test(n)) s += 18;
      if (/espeak|compact|eloquence|grandpa|grandma|bad news|bells|bubbles|jester|organ|superstar|whisper|wobble|zarvox|trinoids|albert|bahh|boing|cellos|good news/i.test(n)) s -= 80;
      if (s > bestS) { bestS = s; best = v; }
    });
    return best;
  }
  function browserParams(over) {
    var gender = (over && over.gender) || PROF.gender, pid = (over && over.preset) || (PROF.voice && PROF.voice.preset), pre = presetOf(pid, gender) || {};
    var rate = Number((over && over.rate) || (PROF.voice && PROF.voice.rate) || 1) * Number(pre.rate || 1);
    var pitch = Number((over && over.pitch) || (PROF.voice && PROF.voice.pitch) || 1) * Number(pre.pitch || 1);
    return { voice: pickVoice(gender, pid), rate: Math.max(0.6, Math.min(1.5, rate)), pitch: Math.max(0.5, Math.min(1.6, pitch)) };
  }
  function speakBrowser(list, gen, done, over) {
    if (!window.speechSynthesis || !list.length) { done(); return; }
    var p = browserParams(over), i = 0;
    if (!p.voice && !speechSynthesis.getVoices().length) { /* Stimmen noch nicht geladen – Standardstimme mit Sprache */ }
    function next() {
      if (gen !== SPK.gen) return;
      if (i >= list.length) { done(); return; }
      var u = new SpeechSynthesisUtterance(list[i++]);
      u.lang = p.voice ? p.voice.lang : lang(); if (p.voice) u.voice = p.voice; u.rate = p.rate; u.pitch = p.pitch;
      u.onend = function () { next(); };
      u.onerror = function (e) { if (e && (e.error === 'interrupted' || e.error === 'canceled')) return; next(); };
      speechSynthesis.speak(u);
    }
    next();
  }
  function ttsFetch(text, over) {
    var body = { text: text }; if (over) body.voice = over;
    return fetch(API_BASE + '/assistant/speak?_=' + Date.now(), { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + key() }, body: JSON.stringify(body) })
      .then(function (r) {
        var ct = r.headers.get('Content-Type') || '';
        if (!r.ok || ct.indexOf('audio') < 0) { var e = new Error('tts ' + r.status); e.status = r.status; throw e; }
        return r.blob();
      });
  }
  function speakServer(list, gen, done, over) {
    var i = 0, pending = ttsFetch(list[0], over);
    pending.catch(function () {});
    function fail(e, from) {
      if (gen !== SPK.gen) return;
      if (e && (e.status === 501 || e.status === 429 || e.status === 401)) SPK.serverOk = false;
      speakBrowser(list.slice(from), gen, done, over);   // Rest mit der Browserstimme
    }
    function step() {
      if (gen !== SPK.gen) return;
      if (i >= list.length) { done(); return; }
      var cur = pending, idx = i; i++;
      pending = i < list.length ? ttsFetch(list[i], over) : null;
      if (pending) pending.catch(function () {});
      cur.then(function (blob) {
        if (gen !== SPK.gen) return;
        var a = audioEl(); if (SPK.url) { try { URL.revokeObjectURL(SPK.url); } catch (e) { /* egal */ } }
        SPK.url = URL.createObjectURL(blob); a.src = SPK.url;
        a.onended = function () { step(); };
        a.onerror = function () { fail(null, idx); };
        var p = a.play(); if (p && p.catch) p.catch(function () { fail(null, idx); });
      }, function (e) { fail(e, idx); });
    }
    step();
  }
  /** Sätze vorlesen. onend läuft nur, wenn die Ausgabe nicht unterbrochen wurde. */
  function speakList(list, onend, over) {
    stopSpeaking();
    list = (list || []).filter(function (s) { return String(s).trim() !== ''; }).map(function (s) { return String(s).slice(0, 400); });
    if (!list.length) { if (onend) onend(); return; }
    var gen = SPK.gen;
    setState('speaking');
    var done = function () { if (gen !== SPK.gen) return; setState('idle'); if (onend) onend(); };
    if (TTS && SPK.serverOk) speakServer(list, gen, done, over); else speakBrowser(list, gen, done, over);
  }
  function voiceInfo() {
    var el = g('asVoiceInfo'); if (!el || !PROF) return;
    var pre = presetOf(PROF.voice && PROF.voice.preset, PROF.gender);
    if (TTS && SPK.serverOk) { el.textContent = 'Stimme: natürliche Studio-Stimme' + (pre ? ' · ' + pre.label : '') + (voiceOn ? '' : ' – zum Vorlesen „Stimme“ einschalten.'); return; }
    if (!window.speechSynthesis) { el.textContent = 'Dieser Browser kann nicht vorlesen – Antworten erscheinen als Text.'; return; }
    var v = pickVoice(PROF.gender, PROF.voice && PROF.voice.preset);
    el.textContent = v ? 'Browserstimme: ' + v.name.replace(/^Microsoft\s+/, '').replace(/\s*-\s*[^-]+$/, '') : 'Keine passende Browserstimme gefunden – Antworten erscheinen als Text.';
  }

  /* ======================================================================= Spracheingabe */
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  var REC = { r: null, on: false, final: '', down: 0, sent: false };
  function micUi(on) { ['asMic', 'asMicBig'].forEach(function (id) { var b = g(id); if (b) { b.classList.toggle('rec', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); } }); }
  function startListen(auto) {
    if (!SR || REC.on || busy) return;
    stopSpeaking();
    var r; try { r = new SR(); } catch (e) { return; }
    REC.r = r; REC.final = ''; REC.sent = false;
    r.lang = lang(); r.interimResults = true; r.continuous = false; r.maxAlternatives = 1;
    var input = g('asInput'), before = auto ? '' : input.value.trim();
    r.onresult = function (ev) {
      var interim = '';
      for (var i = ev.resultIndex; i < ev.results.length; i++) { var x = ev.results[i]; if (x.isFinal) REC.final += x[0].transcript; else interim += x[0].transcript; }
      input.value = ((before ? before + ' ' : '') + REC.final + interim).trim(); autosize();
    };
    r.onerror = function (e) {
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { micUsed = false; note('Das Mikrofon ist blockiert – bitte in den Browser-Einstellungen für diese Seite erlauben.', 'err'); }
      else if (e.error === 'network') note('Die Spracherkennung braucht eine Internetverbindung.', 'err');
    };
    r.onend = function () {
      REC.on = false; micUi(false); if (state === 'listening') setState('idle');
      var t = input.value.trim();
      if (REC.final.trim() && t && !REC.sent) { REC.sent = true; send(t, { viaVoice: true }); }
    };
    try { r.start(); REC.on = true; micUsed = true; micUi(true); setState('listening'); } catch (e) { REC.on = false; }
  }
  function stopListen() { if (REC.r && REC.on) { try { REC.r.stop(); } catch (e) { /* egal */ } } }
  function abortListen() { if (REC.r && REC.on) { REC.sent = true; try { REC.r.abort(); } catch (e) { /* egal */ } } }
  function bindMic(btn) {
    if (!btn) return;
    if (!SR) { btn.disabled = true; btn.title = 'Spracheingabe wird von diesem Browser nicht unterstützt – Chrome, Edge oder Safari verwenden.'; return; }
    btn.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      e.preventDefault(); unlockAudio();
      if (REC.on) { stopListen(); REC.down = 0; return; }
      REC.down = Date.now(); startListen(false);
    });
    var up = function () { if (REC.down && Date.now() - REC.down > 450) stopListen(); REC.down = 0; };   // gedrückt gehalten = Push-to-Talk
    btn.addEventListener('pointerup', up); btn.addEventListener('pointercancel', up); btn.addEventListener('pointerleave', function () { if (REC.down) up(); });
    btn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); unlockAudio(); if (REC.on) stopListen(); else startListen(false); } });
    btn.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  }

  /* ======================================================================= Profil anwenden */
  function applyProfile() {
    if (!PROF) return;
    var img = g('asImg'), url = avatar();
    if (img && img.getAttribute('src') !== url) { img.classList.add('ld'); img.onload = function () { img.classList.remove('ld'); }; img.onerror = function () { img.classList.remove('ld'); }; img.src = url; img.alt = 'Porträt von ' + PROF.name; }
    g('asName').textContent = PROF.name;
    g('asRole').textContent = PROF.role;
    g('asTitle').textContent = PROF.role;
    var sie = isSie(), f = fem();
    g('asSub').textContent = (sie ? 'Erzählen Sie einfach, was erledigt werden soll – ' : 'Erzähl einfach, was erledigt werden soll – ') + PROF.name + ' fragt nach, was noch fehlt, merkt sich alles und richtet ' + (sie ? 'Ihre Agenten mit Ihnen' : 'deine Agenten mit dir') + ' ein.';
    g('asHeadInfo').textContent = sie ? 'Ich ändere nichts, ohne Sie vorher zu fragen.' : 'Ich ändere nichts, ohne dich vorher zu fragen.';
    g('asMemTitle').textContent = sie ? 'Was ich über Sie weiß' : 'Was ich über dich weiß';
    g('asMemEmpty').textContent = sie ? 'Noch nichts – erzählen Sie mir von Ihrem Betrieb, ich merke mir das Wichtigste.' : 'Noch nichts – erzähl mir von deinem Betrieb, ich merke mir das Wichtigste.';
    g('asInput').placeholder = sie ? 'Sagen Sie mir, was ich erledigen soll …' : 'Erzähl mir, was ich erledigen soll …';
    g('asTip').classList.toggle('hidden', !!PROF.saved);
    var sec = document.querySelector('#' + PANEL + ' .as-chat'); if (sec) sec.setAttribute('aria-label', 'Gespräch mit ' + PROF.name + ' (' + (f ? 'Assistentin' : 'Assistent') + ')');
    document.querySelectorAll('#' + PANEL + ' .as-av').forEach(function (a) { a.src = url; });
    voiceBtn(); voiceInfo();
  }
  function voiceBtn() {
    var b = g('asVoiceBtn'); if (!b) return;
    b.textContent = voiceOn ? '🔊 Stimme an' : '🔇 Stimme aus';
    b.setAttribute('aria-pressed', voiceOn ? 'true' : 'false');
    b.classList.toggle('gold', voiceOn);
    b.title = voiceOn ? 'Antworten werden vorgelesen – klicken zum Ausschalten' : 'Antworten vorlesen lassen';
  }

  /* ======================================================================= Gespräch */
  function actsHtml(acts) {
    if (!acts || !acts.length) return '';
    return '<div class="as-acts">' + acts.map(function (a) {
      var link = a.link && /^https:\/\//.test(String(a.link.url || '')) ? '<a class="as-go" href="' + h(a.link.url) + '" target="_blank" rel="noopener noreferrer">' + h(a.link.label || 'Öffnen') + ' →</a>' : '';
      var tab = a.ok && a.tab && TABN[a.tab] ? '<button type="button" class="as-go" data-tab="' + h(a.tab) + '">' + h(TABN[a.tab]) + ' →</button>' : '';
      return '<div class="as-act' + (a.ok ? '' : ' err') + '"><span class="ic" aria-hidden="true">' + (a.ok ? '✓' : '!') + '</span><span class="tx">' + h(a.summary || a.name) + '</span>' + link + tab + '</div>';
    }).join('') + '</div>';
  }
  function msgHtml(m, i, fresh) {
    if (m.role === 'sys') return '<div class="as-m sys' + (m.err ? ' err' : '') + (fresh ? ' as-new' : '') + '"><div class="as-b">' + (m.html || h(m.content)) + '</div></div>';
    if (m.role === 'user') return '<div class="as-m u' + (fresh ? ' as-new' : '') + '"><div class="as-col"><div class="as-b">' + fmt(m.content) + '</div></div></div>';
    var canPlay = !!(window.speechSynthesis || TTS);
    return '<div class="as-m a' + (fresh ? ' as-new' : '') + '"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-col"><div class="as-b">' + fmt(m.content) + '</div>' + actsHtml(m.actions) +
      (canPlay ? '<button type="button" class="as-play" data-i="' + i + '" aria-label="Vorlesen">▶ vorlesen</button>' : '') + '</div></div>';
  }
  function renderHistory() {
    var box = g('asMsgs'); if (!box) return;
    box.innerHTML = HIST.map(function (m, i) { return msgHtml(m, i, false); }).join('');
    box.scrollTop = box.scrollHeight;
  }
  function addMsg(m) {
    HIST.push(m);
    var box = g('asMsgs'); typing(false);
    box.insertAdjacentHTML('beforeend', msgHtml(m, HIST.length - 1, true));
    scrollDown();
  }
  function sysMsg(html, err) { addMsg({ role: 'sys', html: html, err: !!err }); }
  function typing(on) {
    var t = g('asTyping');
    if (on && !t) { g('asMsgs').insertAdjacentHTML('beforeend', '<div class="as-m a as-typing as-new" id="asTyping" aria-label="' + h(PROF ? PROF.name : '') + ' schreibt …"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-b"><i></i><i></i><i></i></div></div>'); scrollDown(); }
    if (!on && t) t.remove();
  }
  function busyUi(on) {
    g('asSend').disabled = on;
    document.querySelectorAll('#asSugg button').forEach(function (b) { b.disabled = on; });
  }
  function renderSugg(list) {
    g('asSugg').innerHTML = (list || []).slice(0, 4).map(function (s) { return '<button type="button" data-s="' + h(s) + '">' + h(s) + '</button>'; }).join('');
  }
  function renderCheck(items, pct) {
    pct = Math.max(0, Math.min(100, Number(pct) || 0));
    g('asRingFg').setAttribute('stroke-dashoffset', String(C_RING * (1 - pct / 100)));
    g('asPct').textContent = pct + ' %';
    g('asRing').setAttribute('aria-label', 'Einrichtung zu ' + pct + ' % erledigt');
    var n = (items || []).filter(function (i) { return i.done; }).length, t = (items || []).length;
    g('asPctTxt').textContent = pct === 0 ? 'Wir starten gemeinsam.' : (pct >= 100 ? 'Alles eingerichtet – stark!' : n + ' von ' + t + ' erledigt' + (pct >= 50 ? ' – mehr als die Hälfte.' : '.'));
    g('asCheck').innerHTML = (items || []).map(function (i) {
      return '<li class="' + (i.done ? 'done' : 'open') + '"' + (i.done ? '' : ' role="button" tabindex="0" data-p="' + h(i.prompt || '') + '" title="Mit dem Assistenten erledigen"') + '><span class="as-ck" aria-hidden="true">' + (i.done ? CHECK : '') + '</span><div><b>' + h(i.label) + '</b><span>' + h(i.hint || '') + '</span></div></li>';
    }).join('');
  }
  function renderMem(list) {
    list = list || [];
    g('asMemN').textContent = String(list.length);
    g('asMemEmpty').classList.toggle('hidden', list.length > 0);
    g('asMem').innerHTML = list.map(function (m) {
      return '<li data-id="' + h(m.id) + '"><div style="min-width:0"><span class="tp">' + h(m.topic) + '</span>' + h(m.fact) + '</div><button type="button" class="as-x" data-id="' + h(m.id) + '" aria-label="„' + h(m.topic) + '“ vergessen" title="Vergessen">×</button></li>';
    }).join('');
  }
  function applyState(j) {
    if (!j) return;
    if (j.checklist) renderCheck(j.checklist, j.progress);
    if (j.memory) renderMem(j.memory);
    if (j.suggestions) renderSugg(j.suggestions);
    if (typeof j.balance === 'number') setBalance(j.balance);
    if (j.cost) { COST = j.cost; g('asCost').textContent = '≈ ' + COST.turn + ' Tokens je Antwort · +' + COST.tool + ' je Aktion'; }
  }
  function runOpens(acts) {
    var a = (acts || []).filter(function (x) { return x.ok && x.open && x.tab; })[0];
    if (a && typeof go === 'function') setTimeout(function () { go(a.tab); }, 900);
  }
  function send(text, o) {
    text = String(text || '').trim();
    if (!text || busy) return;
    if (text.length > 2000) { note('Die Nachricht ist zu lang (höchstens 2.000 Zeichen).', 'err'); return; }
    stopSpeaking(); abortListen(); unlockAudio();
    busy = true; busyUi(true); lastViaVoice = !!(o && o.viaVoice);
    var input = g('asInput'); input.value = ''; autosize(); msg(g('asNote'), '');
    addMsg({ role: 'user', content: text });
    typing(true); setState('thinking');
    api('/assistant/chat', { method: 'POST', body: JSON.stringify({ message: text, voice: voiceOn }) }).then(function (j) {
      typing(false);
      addMsg({ role: 'assistant', content: String(j.reply || ''), actions: j.actions || [] });
      applyState(j);
      var acts = j.actions || [];
      if (voiceOn && j.speech && j.speech.length) {
        speakList(j.speech, function () {
          runOpens(acts);
          if (voiceOn && micUsed && lastViaVoice && SR && visible() && !document.hidden && !acts.some(function (a) { return a.open; })) setTimeout(function () { if (!busy && !speaking()) startListen(true); }, 250);
        });
      } else { setState('idle'); runOpens(acts); }
    }).catch(function (e) {
      typing(false); setState('idle');
      var d = (e && e.data) || {};
      if (e && e.status === 402) {
        sysMsg(h(d.message || 'Dein Guthaben reicht nicht für eine Antwort.') + ' <a href="/#preise" target="_blank" rel="noopener">Tokens kaufen</a>', true);
        if (typeof d.balance === 'number') setBalance(d.balance);
      } else if (e && e.status === 503) {
        sysMsg(h(d.message || 'Ich bin gerade nicht erreichbar – bitte gleich noch einmal versuchen.'), true);
        if (d.actions && d.actions.length) { HIST.push({ role: 'assistant', content: '', actions: d.actions }); g('asMsgs').insertAdjacentHTML('beforeend', '<div class="as-m a as-new"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-col">' + actsHtml(d.actions) + '</div></div>'); scrollDown(); }
        input.value = text; autosize();
      } else {
        sysMsg(h((e && e.message) || 'Das hat nicht geklappt.'), true);
        input.value = text; autosize();
      }
    }).then(function () { busy = false; busyUi(false); if (!REDUCED && window.innerWidth > 820 && !lastViaVoice) { try { input.focus({ preventScroll: true }); } catch (x) { /* egal */ } } });
  }
  function autosize() { var t = g('asInput'); if (!t) return; t.style.height = '46px'; t.style.height = Math.min(160, Math.max(46, t.scrollHeight + 2)) + 'px'; }

  /* ======================================================================= Laden */
  function load(full) {
    var jobs = [api('/assistant/state')];
    if (full || !PROF) jobs.push(api('/assistant/profile'));
    return Promise.all(jobs).then(function (r) {
      if (r[1]) takeProfile(r[1]);
      var st = r[0];
      HIST = (st.history || []).map(function (m) { return { role: m.role, content: m.content, actions: m.actions || [], greeting: !!m.greeting }; });
      applyProfile(); renderHistory(); applyState(st);
      lastLoad = Date.now();
    });
  }
  function takeProfile(j) {
    PROF = j.profile; PORTRAITS = j.portraits || []; VOICES = j.voices || []; TTS = !!j.tts; if (j.photo_max) PHOTO_MAX = j.photo_max;
  }

  /* ======================================================================= Anpassen (Drawer) */
  var D = null, lastFocus = null;
  function draftFrom(p) {
    return { name: p.name, gender: p.gender, portrait: p.portrait, photo: null, remove_photo: false, photoUrl: p.portrait === 'custom' ? p.portrait_url : (p.photo_url || null),
      voice: { tone: p.voice.tone, rate: Number(p.voice.rate) || 1, pitch: Number(p.voice.pitch) || 1, preset: p.voice.preset },
      personality: { address: p.personality.address, mood: p.personality.mood, humor: Number(p.personality.humor), detail: p.personality.detail }, lang: p.lang || 'de', autoName: true };
  }
  function portraitById(id) { var x = null; PORTRAITS.forEach(function (p) { if (p.id === id) x = p; }); return x; }
  function draftImg() { if (D.portrait === 'custom' && D.photoUrl) return imgUrl(D.photoUrl); var p = portraitById(D.portrait); return p ? imgUrl(p.url) : avatar(); }
  function seg(id, val) { document.querySelectorAll('#' + id + ' button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.v) === String(val) ? 'true' : 'false'); }); }
  function fx(n) { return (Math.round(Number(n) * 100) / 100).toFixed(2).replace('.', ',') + '×'; }
  function drawDrawer() {
    var f = D.gender !== 'm';
    g('asDrImg').src = draftImg();
    g('asDrTitle').textContent = (D.name || (f ? 'Assistentin' : 'Assistent')) + ' anpassen';
    g('asDrSub').textContent = f ? (D.personality.address === 'sie' ? 'Ihre persönliche Assistentin' : 'Deine persönliche Assistentin') : (D.personality.address === 'sie' ? 'Ihr persönlicher Assistent' : 'Dein persönlicher Assistent');
    seg('asFGender', D.gender); seg('asFTone', D.voice.tone); seg('asFAddress', D.personality.address); seg('asFMood', D.personality.mood); seg('asFHumor', D.personality.humor); seg('asFDetail', D.personality.detail);
    var tiles = PORTRAITS.filter(function (p) { return p.gender === D.gender; }).map(function (p) {
      return '<button type="button" data-p="' + h(p.id) + '" aria-pressed="' + (D.portrait === p.id ? 'true' : 'false') + '" aria-label="Porträt ' + h(p.label) + '"><img src="' + h(imgUrl(p.url)) + '" alt="" loading="lazy"><span class="lb">' + h(p.label) + '</span></button>';
    });
    if (D.photoUrl) tiles.unshift('<button type="button" data-p="custom" aria-pressed="' + (D.portrait === 'custom' ? 'true' : 'false') + '" aria-label="Eigenes Foto"><img src="' + h(imgUrl(D.photoUrl)) + '" alt=""><span class="lb">Eigenes Foto</span></button>');
    tiles.push('<button type="button" class="up" id="asFUp" aria-label="Eigenes Foto hochladen">＋<br>Eigenes Foto</button>');
    g('asFPortraits').innerHTML = tiles.join('');
    g('asFPhotoRm').classList.toggle('hidden', !D.photoUrl);
    g('asFVoices').innerHTML = VOICES.filter(function (v) { return v.gender === D.gender; }).map(function (v) {
      return '<button type="button" data-v="' + h(v.id) + '" aria-pressed="' + (D.voice.preset === v.id ? 'true' : 'false') + '"><b>' + h(v.label) + '</b>' + h(v.desc || '') + '</button>';
    }).join('');
    g('asFRate').value = String(D.voice.rate); g('asFRateO').textContent = fx(D.voice.rate);
    g('asFPitch').value = String(D.voice.pitch); g('asFPitchO').textContent = fx(D.voice.pitch);
    g('asFLang').value = D.lang;
    g('asFPrevInfo').textContent = TTS && SPK.serverOk ? 'natürliche Studio-Stimme' : (window.speechSynthesis ? 'Browserstimme' : 'Vorlesen nicht verfügbar');
  }
  function openDrawer() {
    if (!PROF) return;
    D = draftFrom(PROF); lastFocus = document.activeElement;
    g('asFName').value = D.name; msg(g('asFMsg'), '');
    drawDrawer();
    g('asDrawer').classList.remove('hidden');
    document.addEventListener('keydown', drawerKey, true);
    setTimeout(function () { try { g('asFName').focus(); } catch (e) { /* egal */ } }, 60);
  }
  function closeDrawer() {
    stopSpeaking();
    g('asDrawer').classList.add('hidden'); D = null;
    document.removeEventListener('keydown', drawerKey, true);
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) { /* egal */ } }
  }
  function drawerKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); closeDrawer(); return; }
    if (e.key === 'Tab') {   // Fokus im Dialog halten
      var f = Array.prototype.filter.call(g('asDrawer').querySelectorAll('button,input,select,textarea,[tabindex]'), function (x) { return !x.disabled && x.offsetParent !== null && x.tabIndex >= 0; });
      if (!f.length) return; var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }
  function toneToPreset(gender, tone) {
    var map = { f: { warm: 'f_warm', ruhig: 'f_sanft', energisch: 'f_frisch', sachlich: 'f_klar' }, m: { warm: 'm_freundlich', ruhig: 'm_ruhig', energisch: 'm_freundlich', sachlich: 'm_souveraen' } };   // wie ASSISTANT_TONE_PRESET; fehlt eine Stimme, nimmt presetOf die erste passende
    var id = (map[gender] || map.f)[tone]; return presetOf(id, gender) ? presetOf(id, gender).id : (presetOf('', gender) || {}).id;
  }
  function setGender(gd) {
    if (D.gender === gd) return;
    var oldP = portraitById(D.portrait);
    D.gender = gd;
    if (D.portrait !== 'custom') {
      var first = PORTRAITS.filter(function (p) { return p.gender === gd; })[0];
      if (first) { if (D.autoName && oldP && (D.name === oldP.label || D.name === 'Lea')) { D.name = first.label; g('asFName').value = D.name; } D.portrait = first.id; }
    }
    var pre = presetOf(D.voice.preset, gd); if (!pre || pre.gender !== gd) D.voice.preset = toneToPreset(gd, D.voice.tone);
    drawDrawer();
  }
  function pickPortrait(id) {
    if (id === 'custom') { D.portrait = 'custom'; drawDrawer(); return; }
    var p = portraitById(id), old = portraitById(D.portrait); if (!p) return;
    if (D.autoName && (!D.name || (old && D.name === old.label) || D.name === 'Lea' || D.name === 'Assistent')) { D.name = p.label; g('asFName').value = D.name; }
    D.portrait = id; drawDrawer();
  }
  /** Foto im Browser quadratisch zuschneiden, auf 512 px verkleinern und als JPEG ≤ 350 KB kodieren. */
  function handleFile(file) {
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { msg(g('asFMsg'), 'Bitte ein Foto als JPEG, PNG oder WebP wählen.', 'err'); return; }
    if (file.size > 15 * 1024 * 1024) { msg(g('asFMsg'), 'Das Foto ist zu groß (höchstens 15 MB vor dem Verkleinern).', 'err'); return; }
    msg(g('asFMsg'), 'Foto wird vorbereitet …', 'info');
    var url = URL.createObjectURL(file), im = new Image();
    im.onload = function () {
      try {
        var w = im.naturalWidth, hh = im.naturalHeight;
        if (w < 64 || hh < 64) { msg(g('asFMsg'), 'Das Foto ist zu klein (mindestens 64 × 64 Pixel).', 'err'); return; }
        var s = Math.min(w, hh), sx = (w - s) / 2, sy = Math.max(0, (hh - s) * 0.35), out = Math.min(512, s);
        var c = document.createElement('canvas'); c.width = out; c.height = out;
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, out, out); x.imageSmoothingQuality = 'high';
        x.drawImage(im, sx, sy, s, s, 0, 0, out, out);
        var q = 0.88, d = c.toDataURL('image/jpeg', q);
        while (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX && q > 0.4) { q -= 0.08; d = c.toDataURL('image/jpeg', q); }
        if (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX) { msg(g('asFMsg'), 'Das Foto ließ sich nicht klein genug speichern – bitte ein anderes wählen.', 'err'); return; }
        D.photo = d; D.photoUrl = d; D.portrait = 'custom'; D.remove_photo = false;
        msg(g('asFMsg'), 'Foto übernommen – mit „Speichern“ bestätigen.', 'ok'); drawDrawer();
      } catch (e) { msg(g('asFMsg'), 'Das Foto konnte nicht gelesen werden.', 'err'); }
      finally { URL.revokeObjectURL(url); }
    };
    im.onerror = function () { URL.revokeObjectURL(url); msg(g('asFMsg'), 'Das Foto konnte nicht gelesen werden – bitte JPEG, PNG oder WebP verwenden.', 'err'); };
    im.src = url;
  }
  function preview() {
    if (!D) return;
    unlockAudio();
    var n = (g('asFName').value || '').trim() || (D.gender === 'm' ? 'dein Assistent' : 'deine Assistentin');
    var sie = D.personality.address === 'sie';
    var txt = D.lang === 'en' ? 'Hello, I\'m ' + n + '. This is how I sound – does that suit you?' : 'Hallo, ich bin ' + n + '. So klinge ich – passt das für ' + (sie ? 'Sie' : 'dich') + '?';
    var over = { gender: D.gender, tone: D.voice.tone, rate: D.voice.rate, pitch: D.voice.pitch, preset: D.voice.preset };
    var btn = g('asFPreview'); btn.disabled = true;
    speakList([txt], function () { btn.disabled = false; }, over);
    setTimeout(function () { btn.disabled = false; }, 6000);
  }
  function saveDrawer() {
    if (!D) return;
    var name = (g('asFName').value || '').replace(/\s+/g, ' ').trim();
    var ok = false; try { ok = new RegExp('^\\p{L}[\\p{L}\\p{M} \\-]*$', 'u').test(name); } catch (e) { ok = /^[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ \-]*$/.test(name); }
    if (!name || name.length > 30 || !ok) { msg(g('asFMsg'), 'Name: 1–30 Zeichen, nur Buchstaben, Leerzeichen und Bindestrich.', 'err'); g('asFName').focus(); return; }
    var body = { name: name, gender: D.gender, voice: D.voice, personality: D.personality, lang: D.lang };
    if (D.photo) body.photo = D.photo; else body.portrait = D.portrait;
    if (D.remove_photo && !D.photo) body.remove_photo = 1;
    var btn = g('asFSave'); btn.disabled = true; msg(g('asFMsg'), 'Wird gespeichert …', 'info');
    var onlyGreeting = HIST.length === 1 && HIST[0].greeting;
    var changedIntro = PROF.name !== name || PROF.gender !== D.gender || PROF.personality.address !== D.personality.address || PROF.personality.mood !== D.personality.mood || PROF.lang !== D.lang;
    api('/assistant/profile', { method: 'POST', body: JSON.stringify(body) }).then(function (j) {
      takeProfile(j); applyProfile();
      closeDrawer();
      note('Gespeichert – ' + h(PROF.name) + ' ist bereit.', 'ok');
      /* Nur die Begrüßung im Verlauf? Dann neu begrüßen – mit neuem Namen, neuer Anrede (kostenlos, ohne KI). */
      if (onlyGreeting && changedIntro) return api('/assistant/reset', { method: 'POST', body: '{}' }).then(function (st) {
        HIST = (st.history || []).map(function (m) { return { role: m.role, content: m.content, actions: m.actions || [], greeting: !!m.greeting }; });
        renderHistory(); applyState(st);
        if (voiceOn && HIST.length) speakList(sentences(HIST[HIST.length - 1].content));
      });
      renderHistory();
    }).catch(function (e) {
      var d = (e && e.data) || {};
      msg(g('asFMsg'), h(d.message || (e && e.message) || 'Speichern fehlgeschlagen.'), 'err');
    }).then(function () { btn.disabled = false; });
  }

  /* ======================================================================= Ereignisse */
  function bind() {
    if (bound) return; bound = true;
    var form = g('asForm'), input = g('asInput');
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    input.addEventListener('keydown', function (e) {
      if (speaking()) stopSpeaking();   // Barge-in: Tippen unterbricht
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); }
    });
    input.addEventListener('input', function () { if (speaking()) stopSpeaking(); autosize(); });
    bindMic(g('asMic')); bindMic(g('asMicBig'));
    g('asVoiceBtn').addEventListener('click', function () {
      voiceOn = !voiceOn; LS.set('ch_as_voice', voiceOn ? '1' : '0');
      if (voiceOn) {
        unlockAudio();
        var only = HIST.filter(function (m) { return m.role === 'assistant'; });
        if (only.length === 1 && HIST.length <= 2) speakList(sentences(only[0].content));   // erster Besuch: Begrüßung vorlesen
      } else stopSpeaking();
      voiceBtn(); voiceInfo();
    });
    g('asCustomize').addEventListener('click', openDrawer);
    g('asReset').addEventListener('click', function () {
      if (busy) return;
      if (!window.confirm('Gespräch neu beginnen? Der Verlauf wird geleert – was ' + (PROF ? PROF.name : 'der Assistent') + ' über ' + (isSie() ? 'Sie' : 'dich') + ' weiß, bleibt erhalten.')) return;
      stopSpeaking(); abortListen();
      api('/assistant/reset', { method: 'POST', body: '{}' }).then(function (st) {
        HIST = (st.history || []).map(function (m) { return { role: m.role, content: m.content, actions: m.actions || [], greeting: !!m.greeting }; });
        renderHistory(); applyState(st);
      }).catch(function (e) { note(h(e.message), 'err'); });
    });
    g('asMsgs').addEventListener('click', function (e) {
      var t = e.target.closest('.as-go[data-tab]');
      if (t) { e.preventDefault(); stopSpeaking(); go(t.dataset.tab); return; }
      var p = e.target.closest('.as-play');
      if (p) { unlockAudio(); var m = HIST[Number(p.dataset.i)]; if (m) { if (speaking()) stopSpeaking(); else speakList(sentences(m.content)); } }
    });
    g('asSugg').addEventListener('click', function (e) { var b = e.target.closest('button[data-s]'); if (b) send(b.dataset.s); });
    var cl = g('asCheck');
    cl.addEventListener('click', function (e) { var li = e.target.closest('li[data-p]'); if (li && li.dataset.p) send(li.dataset.p); });
    cl.addEventListener('keydown', function (e) { if (e.key !== 'Enter' && e.key !== ' ') return; var li = e.target.closest('li[data-p]'); if (li && li.dataset.p) { e.preventDefault(); send(li.dataset.p); } });
    g('asMem').addEventListener('click', function (e) {
      var b = e.target.closest('.as-x[data-id]'); if (!b) return;
      var li = b.closest('li'); if (li) li.style.opacity = '.4'; b.disabled = true;
      api('/assistant/forget', { method: 'POST', body: JSON.stringify({ id: Number(b.dataset.id) }) }).then(function (j) { applyState(j); note('Vergessen.', 'ok'); })
        .catch(function (err) { if (li) li.style.opacity = ''; b.disabled = false; note(h(err.message), 'err'); });
    });
    /* Drawer */
    g('asScrim').addEventListener('click', closeDrawer);
    g('asDrClose').addEventListener('click', closeDrawer);
    g('asFCancel').addEventListener('click', closeDrawer);
    g('asFSave').addEventListener('click', saveDrawer);
    g('asFPreview').addEventListener('click', preview);
    g('asFName').addEventListener('input', function () { if (!D) return; D.name = g('asFName').value.trim(); D.autoName = false; g('asDrTitle').textContent = (D.name || 'Assistent') + ' anpassen'; });
    g('asFGender').addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && D) setGender(b.dataset.v); });
    g('asFPortraits').addEventListener('click', function (e) {
      if (!D) return;
      if (e.target.closest('#asFUp')) { g('asFFile').value = ''; g('asFFile').click(); return; }
      var b = e.target.closest('button[data-p]'); if (b) pickPortrait(b.dataset.p);
    });
    g('asFFile').addEventListener('change', function () { if (D) handleFile(this.files && this.files[0]); });
    g('asFPhotoRm').addEventListener('click', function () {
      if (!D) return; D.photo = null; D.photoUrl = null; D.remove_photo = true;
      if (D.portrait === 'custom') { var f = PORTRAITS.filter(function (p) { return p.gender === D.gender; })[0]; D.portrait = f ? f.id : ''; }
      drawDrawer();
    });
    g('asFVoices').addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && D) { D.voice.preset = b.dataset.v; drawDrawer(); } });
    var segBind = function (id, fn) { g(id).addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && D) { fn(b.dataset.v); drawDrawer(); } }); };
    segBind('asFTone', function (v) { D.voice.tone = v; D.voice.preset = toneToPreset(D.gender, v); });
    segBind('asFAddress', function (v) { D.personality.address = v; });
    segBind('asFMood', function (v) { D.personality.mood = v; });
    segBind('asFHumor', function (v) { D.personality.humor = Number(v); });
    segBind('asFDetail', function (v) { D.personality.detail = v; });
    g('asFRate').addEventListener('input', function () { if (D) { D.voice.rate = Number(this.value); g('asFRateO').textContent = fx(D.voice.rate); } });
    g('asFPitch').addEventListener('input', function () { if (D) { D.voice.pitch = Number(this.value); g('asFPitchO').textContent = fx(D.voice.pitch); } });
    g('asFLang').addEventListener('change', function () { if (D) D.lang = this.value; });
    /* Browserstimmen laden asynchron */
    if (window.speechSynthesis) { try { speechSynthesis.addEventListener('voiceschanged', voiceInfo); } catch (e) { speechSynthesis.onvoiceschanged = voiceInfo; } }
    /* Panel verlassen → Ausgabe und Zuhören beenden (kein Geister-Mikrofon im Hintergrund) */
    var panel = g(PANEL);
    if (panel && window.MutationObserver) new MutationObserver(function () { if (!visible()) { stopSpeaking(); abortListen(); if (D) closeDrawer(); } }).observe(panel, { attributes: true, attributeFilter: ['class'] });
    document.addEventListener('visibilitychange', function () { if (document.hidden) abortListen(); });
  }

  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  STUDIO_PANELS['assistant'] = {
    init: function () {
      bind(); setState('idle'); voiceBtn();
      g('asMsgs').innerHTML = '<div class="as-m sys"><div class="as-b">Einen Moment …</div></div>';
      load(true).catch(function (e) {
        g('asMsgs').innerHTML = '';
        sysMsg(h((e && e.message) || 'Der Assistent konnte nicht geladen werden.') + ' – bitte die Seite neu laden.', true);
      });
    },
    show: function () {
      if (Date.now() - lastLoad > 8000 && !busy) load(false).catch(function () { /* Anzeige bleibt */ });   // Checkliste nach Änderungen in anderen Bereichen auffrischen
    }
  };
})();
