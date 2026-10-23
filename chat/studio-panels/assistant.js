/* ChatHelp Studio — Panel „✦ Assistent“: persönliche Assistentin bzw. persönlicher Assistent des Inhabers (assistant-lib.php).
   Links das Porträt (atmet, goldener Ring + Stimmbalken beim Sprechen, Status „hört zu…/denkt…/spricht…“), in der Mitte das Gespräch
   mit Aktions-Chips (was geändert wurde + Link zum Bereich), rechts Checkliste mit Fortschrittsring und „Was ich über dich weiß“.
   Stimme: Server-Stimme über /v1/assistant/speak (speech-lib), sonst echte Stimme im Browser (Piper, /chat/voice/voice.js – je Voreinstellung
   ein eigenes Stimmmodell), solange die lädt bzw. ohne Modell für die Sprache Browserstimmen (speechSynthesis) – je Voreinstellung eine andere Stimme
   (feste Zuordnung je Geschlecht, siehe buildVoiceMap), ehrlich angezeigt als „Browserstimme: <Name>“ – Satz für Satz.
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
  /* 13 Sprachen – Ersatz, bis /assistant/profile die Tabelle aus assistant-lib (langs) liefert */
  var LANGS = [['de', 'Deutsch', 'Deutsch', 'de-DE'], ['en', 'Englisch', 'English', 'en-GB'], ['fr', 'Französisch', 'Français', 'fr-FR'], ['es', 'Spanisch', 'Español', 'es-ES'],
    ['tr', 'Türkisch', 'Türkçe', 'tr-TR'], ['ru', 'Russisch', 'Русский', 'ru-RU'], ['it', 'Italienisch', 'Italiano', 'it-IT'], ['nl', 'Niederländisch', 'Nederlands', 'nl-NL'],
    ['sv', 'Schwedisch', 'Svenska', 'sv-SE'], ['no', 'Norwegisch', 'Norsk', 'nb-NO'], ['fi', 'Finnisch', 'Suomi', 'fi-FI'], ['ja', 'Japanisch', '日本語', 'ja-JP'],
    ['az', 'Aserbaidschanisch', 'Azərbaycanca', 'az-AZ']].map(function (x) { return { code: x[0], name: x[1], native: x[2], tag: x[3], preview: null }; });
  var LANG_TAG = {}; LANGS.forEach(function (l) { LANG_TAG[l.code] = l.tag; });
  var TABN = { home: 'Übersicht', agents: 'Agenten', training: 'Training', phone: 'Telefon', rueckruf: 'Rückrufe', termine: 'Termine', bestellungen: 'Bestellungen',
    taxi: 'Taxi', hotel: 'Hotel', benachrichtigungen: 'Benachrichtigungen', tokens: 'Tokens & API' };
  /* Oberflächentexte: Tab-Namen aus der (übersetzten) Navigation, Sprachnamen über Intl, du/Sie-Varianten über Schlüssel „…“ / „….f“ */
  function tabName(k) { var t = document.querySelector('#tabs .tab[data-v="' + k + '"]'); var s = t ? t.textContent.replace(/^[^\p{L}\p{N}]+/u, '').trim() : ''; return s || TABN[k]; }
  var DN = null; try { DN = new Intl.DisplayNames([I18N.tag], { type: 'language' }); } catch (e) { DN = null; }
  function lname(l) { if (!l) return ''; if (I18N.lang === 'de' || !DN) return l.name; try { var n = DN.of(l.code === 'no' ? 'nb' : l.code); return n ? n.charAt(0).toLocaleUpperCase(I18N.tag) + n.slice(1) : l.native; } catch (e) { return l.native; } }
  function TS(k, v) { return _t(isSie() ? k + '.f' : k, v); }
  function vpL(v) { return v ? (I18N.lang !== 'de' && I18N.has('asj.vp.' + v.id) ? _t('asj.vp.' + v.id) : _tx(v.label)) : ''; }
  function vpD(v) { return v && v.desc ? (I18N.lang !== 'de' && I18N.has('asj.vpd.' + v.id) ? _t('asj.vpd.' + v.id) : _tx(v.desc)) : ''; }
  var STATUS = { idle: _t('asj.st.idle'), listening: _t('asj.st.listen'), thinking: _t('asj.st.think'), speaking: _t('asj.st.speak') };
  var C_RING = 169.65;
  var CHECK = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  var SILENT_WAV = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';

  var PROF = null, PORTRAITS = [], VOICES = [], TTS = false, TTS_LANGS = [], TOOLS = true, PHOTO_MAX = 358400, COST = { turn: 6, tool: 2 };
  var HIST = [], busy = false, bound = false, lastLoad = 0, state = 'idle';
  var voiceOn = LS.get('ch_as_voice') === '1', micUsed = false, lastViaVoice = false;
  var micLangPick = LS.get('ch_as_miclang') || 'auto';
  var imgCache = {};

  /* ======================================================================= Hilfen */
  function isSie() { return !!PROF && PROF.personality && PROF.personality.address === 'sie'; }
  function fem(p) { return (p || PROF || {}).gender !== 'm'; }
  function normLang(c) { c = String(c || '').toLowerCase().slice(0, 2); return c === 'nb' || c === 'nn' ? 'no' : c; }
  function tagOf(code) { return LANG_TAG[normLang(code)] || LANG_TAG[(PROF && PROF.lang) || 'de'] || 'de-DE'; }
  function lang() { return tagOf(PROF && PROF.lang); }
  /** Sprache des Gesprächs: Sprache der letzten Antwort (vom Server erkannt), sonst die Profilsprache. */
  function convLang() {
    for (var i = HIST.length - 1; i >= 0; i--) { if (HIST[i].role === 'assistant' && HIST[i].lang && LANG_TAG[HIST[i].lang]) return HIST[i].lang; }
    return (PROF && PROF.lang) || 'de';
  }
  /** Spracheingabe: gewählte Sprache oder „Auto“ = Sprache des Gesprächs. */
  function micLang() { return micLangPick !== 'auto' && LANG_TAG[micLangPick] ? micLangPick : convLang(); }
  function T(du, sie) { return isSie() ? sie : du; }
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
    var parts = t.match(/[^.!?…。！？]+(?:[.!?…。！？]+|$)/g) || [], out = [];
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
  /* gen = Abbruchzähler, pv = gerade laufende Hörprobe (Voreinstellung oder „main“), trouble = Browser gibt nachweislich keinen Ton aus,
     why = warum die Server-Stimme ausfällt (balance = kein Guthaben), voicesReady = Browserstimmen geladen (oder lange genug gewartet) */
  var SPK = { gen: 0, el: null, url: null, serverOk: true, noLang: {}, pv: null, trouble: false, why: '', voicesReady: false, voicesWait: [] };
  var NO_VOICE_TXT = _t('asj.noVoice');
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
    if (SPK.pv) { SPK.pv = null; markPlaying(); }
  }
  /** Hörprobe-Knöpfe ([data-play]) und Stimm-Chips zeigen, was gerade klingt. */
  function markPlaying() {
    document.querySelectorAll('#' + PANEL + ' [data-play]').forEach(function (b) {
      var on = !!SPK.pv && b.dataset.play === SPK.pv;
      b.classList.toggle('playing', on);
      if (b.classList.contains('pl') || b.classList.contains('as-cho-play')) b.textContent = on ? _t('asj.stop') : _t('asj.sample');
      else if (b.id === 'asFPreview') b.textContent = on ? _t('asj.sampleStop') : _t('asj.sampleSet');
      var base = b.getAttribute('data-label') || _t('asj.sampleW');
      b.setAttribute('aria-label', on ? _t('asj.stopX',{x:base}) : base);
    });
  }
  function presetOf(id, gender) {
    var p = null; VOICES.forEach(function (v) { if (v.id === id) p = v; });
    if (p && (!gender || p.gender === gender)) return p;
    var f = null; VOICES.forEach(function (v) { if (!f && v.gender === (gender || 'f')) f = v; });
    return f;
  }
  /** Sprechweise zur Voreinstellung (vom Server mitgeliefert; Ersatz wie ASSISTANT_TONE_PRESET). */
  function toneOf(id) {
    var p = null; VOICES.forEach(function (v) { if (v.id === id) p = v; });
    if (p && p.tone) return p.tone;
    return { f_warm: 'warm', f_sanft: 'ruhig', f_frisch: 'energisch', f_klar: 'sachlich', m_freundlich: 'warm', m_ruhig: 'ruhig', m_tief: 'ruhig', m_souveraen: 'sachlich' }[id] || null;
  }
  function shortLabel(v) { return String((v && v.label) || '').split(/\s*&\s*/)[0]; }
  /** Persona der Voreinstellung („Lea“, „Sophie“ …) – vom Server (speech-lib-Label vor „·“), sonst der Klang-Name („Warm“). */
  function personaOf(v) { return String((v && v.persona) || '').trim() || shortLabel(v); }
  function joinNames(a) { a = a.slice(); if (a.length < 2) return a.join(''); try { if (I18N.lang !== 'de' && Intl.ListFormat) return new Intl.ListFormat(I18N.tag, { type: 'conjunction' }).format(a); } catch (e) { /* alt */ } var last = a.pop(); return a.join(', ') + ' und ' + last; }
  var DEFAULT_PRESET = { f: 'f_warm', m: 'm_freundlich' };
  var NAMES_F = ['katja', 'seraphina', 'amala', 'anna', 'petra', 'helena', 'hedda', 'marlene', 'vicki', 'louisa', 'elke', 'gisela', 'klarissa', 'maja', 'tanja', 'ingrid', 'sabine', 'female', 'frau', 'aria', 'jenny', 'samantha', 'ava', 'emma', 'libby', 'sonia', 'zira', 'hazel', 'susan', 'karen', 'moira', 'tessa', 'fiona', 'serena', 'denise', 'elsa', 'isabella', 'paulina', 'milena', 'zosia', 'yelda', 'filiz', 'emel', 'amelie', 'audrey', 'marie', 'alice', 'federica', 'fenna', 'colette', 'dariya', 'svetlana', 'kyoko', 'nanami', 'alva', 'nora', 'satu', 'sara', 'ellen', 'carmit', 'monica', 'luciana', 'joana', 'mariska', 'laura', 'kate', 'victoria', 'allison', 'nicky', 'catherine', 'veena', 'lekha'];
  var NAMES_M = ['conrad', 'florian', 'killian', 'markus', 'yannick', 'stefan', 'martin', 'hans', 'bernd', 'christoph', 'kasper', 'ralf', 'klaus', 'jonas', 'viktor', 'male', 'mann', 'guy', 'davis', 'andrew', 'brian', 'ryan', 'thomas', 'george', 'daniel', 'alex', 'fred', 'david', 'mark', 'oliver', 'henri', 'remy', 'diego', 'alvaro', 'cosimo', 'maarten', 'marek', 'dmitry', 'ahmet', 'xander', 'giuseppe', 'pavel', 'otoya', 'keita', 'jorge', 'juan', 'luca', 'reed', 'aaron', 'arthur', 'gordon', 'rishi', 'tom', 'lee', 'jacques', 'yuri', 'magnus', 'onni', 'cem', 'tolga'];
  /* Stimmen ohne Vornamen im Namen – bekannte Kennungen je Plattform (Android/Google TTS „de-de-x-…“, Samsung „SMTf/SMTm“, Google-Chrome-Stimmen).
     Unbekannte Kennungen gelten als neutral und kommen nach den eindeutig zugeordneten Stimmen an die Reihe. */
  var KNOWN_GENDER = [
    [/\bde-de-x-(dea|deb|deg|nfh)\b/, 'f'], [/\bde-de-x-(dec|ded)\b/, 'm'],
    [/\bsmtf\d*\b/, 'f'], [/\bsmtm\d*\b/, 'm'],
    [/google (deutsch|us english|uk english female|français|español|italiano|nederlands|русский|日本語|polski|português)/, 'f'], [/google uk english male/, 'm'],
    [/\b(female|weiblich|frauenstimme)\b/, 'f'], [/\b(male|männlich|männerstimme)\b/, 'm']
  ];
  function voiceGender(name) {
    var n = String(name || '').toLowerCase(), f = false, m = false;
    for (var i = 0; i < KNOWN_GENDER.length; i++) { if (KNOWN_GENDER[i][0].test(n)) return KNOWN_GENDER[i][1]; }
    NAMES_F.forEach(function (x) { if (new RegExp('\\b' + x + '\\b').test(n)) f = true; });
    NAMES_M.forEach(function (x) { if (new RegExp('\\b' + x + '\\b').test(n)) m = true; });
    return f && !m ? 'f' : (m && !f ? 'm' : '');
  }
  function voicesNow() { try { return (window.speechSynthesis && speechSynthesis.getVoices()) || []; } catch (e) { return []; } }
  /** Chrome lädt die Stimmen asynchron: kurz nach dem Laden höchstens ~1 s auf „voiceschanged“ warten, danach nie wieder. */
  function voicesReady() { if (SPK.voicesReady) return; SPK.voicesReady = true; var w = SPK.voicesWait; SPK.voicesWait = []; w.forEach(function (f) { try { f(); } catch (e) { /* egal */ } }); }
  function whenVoices(cb) { if (SPK.voicesReady || voicesNow().length) { SPK.voicesReady = true; cb(); } else SPK.voicesWait.push(cb); }
  /* Spaß-, Roboter- und Kompaktstimmen (macOS/iOS/eSpeak) – nur, wenn sonst nichts da ist */
  var NOVELTY = /\b(espeak|eloquence|compact|eddy|flo|grandma|grandpa|reed|rocko|sandy|shelley|junior|kathy|ralph|fred|albert|bahh|bells|boing|bubbles|cellos|jester|organ|superstar|trinoids|whisper|wobble|zarvox|hysterical|deranged|bad news|good news|pipe organ)\b/i;
  /** Qualität einer Stimme unabhängig von Voreinstellung und Geschlecht: Natural/Neural/Premium/Google/Siri hoch, Spaßstimmen tief. */
  function voiceQuality(v, tag, anyLang) {
    var vl = String(v.lang || '').toLowerCase().replace('_', '-'), n = String(v.name || ''), s = 0;
    if (vl === String(tag).toLowerCase()) s += 6;
    if (/natural/i.test(n)) s += 34; if (/online/i.test(n)) s += 12; if (/neural/i.test(n)) s += 26;
    if (/premium/i.test(n)) s += 30; if (/enhanced|erweitert/i.test(n)) s += 22; if (/google/i.test(n)) s += 16; if (/siri/i.test(n)) s += 18;
    if (/-network\b/i.test(n)) s += 2;   // Android: Netzstimme klingt natürlicher als die lokale Variante
    if (NOVELTY.test(n)) s -= 80;
    if (/compact/i.test(String(v.voiceURI || ''))) s -= 20;
    if (anyLang) { if (v['default']) s += 12; if (/^en/.test(vl)) s += 4; }   // fremde Sprache: Standardstimme, sonst Englisch bevorzugen
    return s;
  }
  /** Gleiche Stimme in zwei Ausgaben (Android „-local“/„-network“, doppelte Einträge) zählt nur einmal. */
  function voiceKey(v) { return String(v.name || '').toLowerCase().replace(/-(local|network)$/, '').replace(/\s+/g, ' ').trim() + '|' + String(v.lang || '').toLowerCase().replace('_', '-'); }
  function voiceSig() { return voicesNow().map(function (v) { return v.name + '|' + v.lang; }).join(';'); }
  /* Zuordnung je Geschlecht + Sprache, gültig für diese Stimmenliste (voiceschanged → neue Liste → neue Zuordnung) */
  var VMAP = { sig: null, maps: {} };
  function voiceMap(gender, tag) {
    tag = tag || lang(); gender = gender === 'm' ? 'm' : 'f';
    var sig = voiceSig(), k = gender + '|' + tag + '|' + VOICES.map(function (v) { return v.id; }).join(',');
    if (VMAP.sig !== sig) { VMAP.sig = sig; VMAP.maps = {}; }
    if (!VMAP.maps[k]) VMAP.maps[k] = buildVoiceMap(gender, tag);
    return VMAP.maps[k];
  }
  /**
   * Feste Zuordnung Voreinstellung → Browserstimme, damit vier Assistentinnen vier verschiedene Stimmen bekommen:
   * 1. Stimmen der Sprache (de-DE zuerst, dann anderes de-*), sonst alle Stimmen (level any), nach Qualität sortiert, Doppel entfernt;
   * 2. nach Geschlecht geteilt (Namenstabellen + Plattform-Kennungen; unbekannt = neutral, kommt nach den eigenen);
   * 3. Voreinstellungen (Standardstimme zuerst) bekommen nacheinander: den ersten freien Wunschnamen, sonst die nächste freie Stimme nach Rang;
   *    gibt es nur eine eigene Stimme, folgen freie Stimmen des anderen Geschlechts mit Tonhöhen-Ausgleich (adj);
   *    sind alle vergeben, teilen sich Voreinstellungen eine Stimme (shared) – zyklisch über die eigenen, mit anderem Tempo/Tonhöhe.
   * level: lang = Stimme der Sprache, any = irgendeine Stimme, none = keine Stimmenliste (Standardstimme über utterance.lang).
   */
  function buildVoiceMap(gender, tag) {
    var out = { level: 'none', by: {}, shares: [], n: 0 };
    if (!window.speechSynthesis) return out;
    var all = voicesNow(), l2 = normLang(tag);
    var inLang = all.filter(function (v) { return normLang(String(v.lang || '').replace('_', '-')) === l2; });
    var pool = inLang.length ? inLang : all, anyLang = !inLang.length;
    out.level = inLang.length ? 'lang' : (all.length ? 'any' : 'none');
    if (!pool.length) return out;
    var idx = {}, ranked = [];
    pool.forEach(function (v) {
      var key = voiceKey(v), e = { v: v, q: voiceQuality(v, tag, anyLang), g: voiceGender(v.name), key: key };
      if (idx[key] === undefined) { idx[key] = ranked.length; ranked.push(e); } else if (e.q > ranked[idx[key]].q) ranked[idx[key]] = e;
    });
    ranked.sort(function (a, b) { return b.q - a.q; });
    var usable = ranked.filter(function (e) { return e.q > -50; }); if (!usable.length) usable = ranked;   // Spaßstimmen nur, wenn es sonst gar keine gibt
    var own = usable.filter(function (e) { return e.g === gender; }), neutral = usable.filter(function (e) { return !e.g; }), other = usable.filter(function (e) { return e.g && e.g !== gender; });
    var queue = own.concat(neutral).map(function (e) { return { v: e.v, key: e.key, adj: 1 }; }), ownN = queue.length;
    if (ownN <= 1) queue = queue.concat(other.map(function (e) { return { v: e.v, key: e.key, adj: gender === 'm' ? 0.72 : 1.3 }; }));
    out.n = queue.length;
    var presets = VOICES.filter(function (v) { return v.gender === gender; });
    if (!presets.length) presets = [{ id: '_', browser: [] }];
    presets = presets.filter(function (p) { return p.id === DEFAULT_PRESET[gender]; }).concat(presets.filter(function (p) { return p.id !== DEFAULT_PRESET[gender]; }));
    var taken = {}, used = {}, wantOf = function (p) { return l2 === 'de' ? (p.browser || []) : []; };   // Wunschnamen sind deutsche Stimmen
    var matches = function (q, w) { return String(q.v.name || '').toLowerCase().indexOf(String(w).toLowerCase()) >= 0; };
    presets.forEach(function (p) {
      var pick = null, shared = false;
      wantOf(p).some(function (w) { queue.some(function (q) { if (!taken[q.key] && matches(q, w)) { pick = q; return true; } return false; }); return !!pick; });
      if (!pick) queue.some(function (q) { if (!taken[q.key]) { pick = q; return true; } return false; });
      if (!pick) {   // alle vergeben → teilen: am seltensten genutzte eigene Stimme, bei Gleichstand Wunschname, sonst Rang
        shared = true;
        var cyc = queue.slice(0, ownN); if (!cyc.length) cyc = queue;
        var min = Infinity; cyc.forEach(function (q) { min = Math.min(min, used[q.key] || 0); });
        var cand = cyc.filter(function (q) { return (used[q.key] || 0) === min; });
        wantOf(p).some(function (w) { return cand.some(function (q) { if (matches(q, w)) { pick = q; return true; } return false; }); });
        if (!pick) pick = cand[0];
      }
      taken[pick.key] = p.id; used[pick.key] = (used[pick.key] || 0) + 1;
      out.by[p.id] = { v: pick.v, adj: pick.adj, shared: shared, key: pick.key };
    });
    /* geteilte Stimmen: Gruppen mit mehr als einer Voreinstellung, in der Reihenfolge der Stimmkarten */
    var groups = {}, order = VOICES.map(function (v) { return v.id; });
    presets.forEach(function (p) { var e = out.by[p.id]; (groups[e.key] = groups[e.key] || { v: e.v, ids: [] }).ids.push(p.id); });
    Object.keys(groups).forEach(function (key) {
      var gp = groups[key]; if (gp.ids.length < 2) return;
      gp.ids.sort(function (a, b) { return order.indexOf(a) - order.indexOf(b); });
      gp.ids.forEach(function (id) { out.by[id].shared = true; });
      out.shares.push({ v: gp.v, ids: gp.ids });
    });
    return out;
  }
  /** Browserstimme zur Voreinstellung: {v, level, adj, shared}. adj = Tonhöhen-Ausgleich bei einer Stimme des anderen Geschlechts. */
  function pickVoiceEx(gender, presetId, tag) {
    var out = { v: null, level: 'none', adj: 1, shared: false };
    if (!window.speechSynthesis) return out;
    gender = gender === 'm' ? 'm' : 'f';
    var m = voiceMap(gender, tag || lang()), pre = presetOf(presetId, gender), e = (pre && m.by[pre.id]) || m.by._ || null;
    if (!e) for (var k in m.by) { if (Object.prototype.hasOwnProperty.call(m.by, k)) { e = m.by[k]; break; } }
    out.level = m.level;
    if (e) { out.v = e.v; out.adj = e.adj; out.shared = e.shared; }
    return out;
  }
  function pickVoice(gender, presetId, tag) { return pickVoiceEx(gender, presetId, tag).v; }
  /** Anzeigename der Browserstimme: „Microsoft Katja Online (Natural) - German (Germany)“ → „Katja (Natural)“. */
  function voiceLabel(v) {
    return String((v && v.name) || '').replace(/^Microsoft\s+/i, '').replace(/\s+-\s+[^-]*$/, '').replace(/\s+Online\b/i, '').replace(/\s{2,}/g, ' ').trim();
  }
  /**
   * Tempo/Tonhöhe für die Browserstimme: brate/bpitch der Voreinstellung (weiter gespreizt als beim Sprachdienst), im Browser verstärkt (×2,5),
   * damit man die Sprechweise hört – begrenzt auf 0,7–1,4 (Tempo) und 0,6–1,4 (Tonhöhe).
   */
  function browserParams(over, tag) {
    var gender = (over && over.gender) || PROF.gender, pid = (over && over.preset) || (PROF.voice && PROF.voice.preset), pre = presetOf(pid, gender) || {};
    var pk = pickVoiceEx(gender, pid, tag), k = 2.5;
    var pr = Number(pre.brate || pre.rate || 1), pp = Number(pre.bpitch || pre.pitch || 1);
    var rate = Number((over && over.rate) || (PROF.voice && PROF.voice.rate) || 1) * (1 + (pr - 1) * k);
    var pitch = Number((over && over.pitch) || (PROF.voice && PROF.voice.pitch) || 1) * (1 + (pp - 1) * k) * pk.adj;
    return { voice: pk.v, level: pk.level, rate: Math.max(0.7, Math.min(1.4, rate)), pitch: Math.max(0.6, Math.min(1.4, pitch)) };
  }
  /** Browser gibt keinen Ton aus → ehrlich sagen (Statuszeilen + einmal als Hinweis), nie still scheitern. */
  function voiceTrouble() {
    var first = !SPK.trouble; SPK.trouble = true; voiceInfo();
    if (first && !D) note(h(NO_VOICE_TXT), 'err');
  }
  function voiceFine() { if (SPK.trouble) { SPK.trouble = false; voiceInfo(); } }
  /**
   * Satz für Satz über die Browserstimme. Scheitert ein Satz (Fehler oder 7 s ohne jedes Ereignis), wird er einmal mit der
   * Standardstimme wiederholt; klingt dann gar nichts, meldet voiceTrouble() das ehrlich.
   */
  function speakBrowser(list, gen, done, over, tag) {
    if (!window.speechSynthesis) { voiceTrouble(); done(); return; }
    if (!list.length) { done(); return; }
    whenVoices(function () {
      if (gen !== SPK.gen) return;
      tag = tag || lang();
      var p = browserParams(over, tag), i = 0, anyOk = false;
      try { if (speechSynthesis.paused) speechSynthesis.resume(); } catch (e) { /* egal */ }
      function next() {
        if (gen !== SPK.gen) return;
        if (i >= list.length) { done(); return; }
        say(list[i++], false);
      }
      function say(text, plain) {
        var u, ended = false, wd = 0;
        try { u = new SpeechSynthesisUtterance(text); } catch (e) { voiceTrouble(); done(); return; }
        if (plain) u.lang = p.level === 'any' && p.voice ? p.voice.lang : tag;   // Wiederholung: Standardstimme
        else { u.lang = p.voice ? p.voice.lang : tag; if (p.voice) { try { u.voice = p.voice; } catch (e) { /* egal */ } } }
        u.rate = p.rate; u.pitch = p.pitch;
        var fin = function (good) {
          if (ended) return; ended = true; clearTimeout(wd);
          if (gen !== SPK.gen) return;
          if (good) { anyOk = true; voiceFine(); next(); return; }
          if (!plain) { say(text, true); return; }
          if (!anyOk) { voiceTrouble(); done(); return; }   // nichts klingt → aufhören statt jeden Satz einzeln scheitern zu lassen
          next();
        };
        u.onstart = function () { clearTimeout(wd); anyOk = true; voiceFine(); };
        u.onend = function () { fin(true); };
        u.onerror = function (e) { if (e && (e.error === 'interrupted' || e.error === 'canceled')) { ended = true; clearTimeout(wd); return; } fin(false); };
        wd = setTimeout(function () {
          if (ended || gen !== SPK.gen) return;
          var busyNow = false; try { busyNow = speechSynthesis.speaking && !speechSynthesis.pending; } catch (e) { /* egal */ }
          if (busyNow) return;   // spricht bereits (manche Browser melden onstart nicht)
          ended = true; try { speechSynthesis.cancel(); } catch (e) { /* egal */ }
          ended = false; fin(false);
        }, 7000);
        try { speechSynthesis.speak(u); } catch (e) { fin(false); }
      }
      next();
    });
  }
  /** Server liest nur eigene Sätze vor: {mid,i} = Satz i der Assistenten-Nachricht mid, {preview} = Hörprobe (Text baut der Server). */
  function ttsFetch(src, idx) {
    var body = src.preview ? { preview: src.preview } : { mid: src.mid, i: idx };
    return fetch(API_BASE + '/assistant/speak?_=' + Date.now(), { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + key() }, body: JSON.stringify(body) })
      .then(function (r) {
        var ct = r.headers.get('Content-Type') || '';
        if (!r.ok || ct.indexOf('audio') < 0) {
          var e = new Error('tts ' + r.status); e.status = r.status;
          return r.json().catch(function () { return {}; }).then(function (d) { e.code = d && d.error; throw e; });
        }
        return r.blob();
      });
  }
  function speakServer(list, gen, done, src, over) {
    var i = 0, tag = tagOf(src.lang), pending = ttsFetch(src, 0);
    pending.catch(function () {});
    function fail(e, from) {
      if (gen !== SPK.gen) return;
      if (e && e.code === 'tts_lang_unavailable') SPK.noLang[normLang(src.lang)] = true;   // nur diese Sprache über den Browser
      else if (e && (e.status === 501 || e.status === 429 || e.status === 401 || e.status === 402)) { SPK.serverOk = false; SPK.why = e.status === 402 ? 'balance' : (e.status === 429 ? 'limit' : ''); }
      voiceInfo();
      speakBrowser(list.slice(from), gen, done, over, tag);   // Rest mit der Browserstimme
    }
    function step() {
      if (gen !== SPK.gen) return;
      if (i >= list.length) { done(); return; }
      var cur = pending, idx = i; i++;
      pending = i < list.length ? ttsFetch(src, i) : null;
      if (pending) pending.catch(function () {});
      cur.then(function (blob) {
        if (gen !== SPK.gen) return;
        var a = audioEl(); if (SPK.url) { try { URL.revokeObjectURL(SPK.url); } catch (e) { /* egal */ } }
        SPK.url = URL.createObjectURL(blob); a.src = SPK.url;
        var failed = false, once = function () { if (failed) return; failed = true; a.onended = null; a.onerror = null; fail(null, idx); };   // onerror UND play().catch → nur einmal
        a.onended = function () { if (failed) return; failed = true; step(); };
        a.onerror = once;
        var p = a.play(); if (p && p.catch) p.catch(once);
      }, function (e) { fail(e, idx); });
    }
    step();
  }
  /* ======================================================================= Echte Stimme im Browser (Piper, /chat/voice/voice.js)
     Jede Voreinstellung hat ein eigenes Stimmmodell bzw. einen eigenen Sprecher. Das Modell lädt einmalig (21–77 MB, danach im Browser
     gespeichert); bis es da ist, spricht die Systemstimme. Ohne Datensparmodus/Mobilfunk lädt es bei der ersten Hörprobe von selbst. */
  var PIP = { st: {}, pct: {}, js: null };
  /* ---- Stimmenkatalog (voice-catalog.php über /chat/voice-api.php/v1/voices?lang=…): je Sprache f/m ≥ 6 Stimmen, beste zuerst.
     Einträge {id, label, desc[], tier real|variant|premium, gender, lang, preset, mb, speaker, source{type,…}, available}. CAT.by = alle geladenen, CAT.lang[l] = {f,m}. */
  var CAT = { by: {}, lang: {}, loading: {}, prov: null };
  function catLoad(lng) {
    lng = normLang(lng || (PROF && PROF.lang) || 'de');
    if (CAT.lang[lng]) return Promise.resolve(CAT.lang[lng]);
    if (CAT.loading[lng]) return CAT.loading[lng];
    var p = fetch('/chat/voice-api.php/v1/voices?lang=' + encodeURIComponent(lng) + '&_=' + Date.now(), { cache: 'no-store', headers: { 'Authorization': 'Bearer ' + key() } })
      .then(function (r) { if (!r.ok) throw new Error('voices ' + r.status); return r.json(); })
      .then(function (j) {
        var L = { f: (j.voices && j.voices.f) || [], m: (j.voices && j.voices.m) || [], def: j['default'] || {} };
        CAT.lang[lng] = L; CAT.prov = j.providers || CAT.prov;
        var all = L.f.concat(L.m); all.forEach(function (e) { CAT.by[e.id] = e; });
        if (window.CHVoice && CHVoice.register) CHVoice.register(all);
        return L;
      });
    p.then(function () { delete CAT.loading[lng]; }, function () { delete CAT.loading[lng]; });
    CAT.loading[lng] = p; return p;
  }
  /** Verfügbare Stimmen (Premium nur mit Schlüssel – der Server blendet sie sonst aus) der Sprache und des Geschlechts. */
  function catList(lng, gender) { var L = CAT.lang[normLang(lng)]; return L ? (L[gender === 'm' ? 'm' : 'f'] || []).filter(function (e) { return e.available !== false; }) : []; }
  function catEntry(id) { return (id && CAT.by[String(id)]) || null; }
  function catLoaded(lng) { return !!CAT.lang[normLang(lng)]; }
  /** Passende Katalogstimme: dieselbe id, wenn sie zu Sprache + Geschlecht passt, sonst erste Stimme derselben Voreinstellung, sonst die erste. '' ohne Katalog. */
  function catFit(id, lng, gender, preset) {
    var e = catEntry(id); if (e && e.lang === normLang(lng) && e.gender === gender) return e.id;
    var L = catList(lng, gender), want = (e && e.preset) || preset || DEFAULT_PRESET[gender] || '';
    var hit = L.filter(function (x) { return x.preset === want; })[0] || L[0]; return hit ? hit.id : '';
  }
  function catDesc(e) { return ((e && e.desc) || []).map(function (t) { return I18N.has('vc.d.' + t) ? _t('vc.d.' + t) : t; }).join(' · '); }
  function tierBadge(e) { var t = (e && e.tier) || 'real'; return '<span class="as-tier t-' + h(t) + '" title="' + h(_t('vc.tierT.' + t)) + '">' + h(_t('vc.tier.' + t)) + '</span>'; }
  /** Alle Katalogstimmen der Sprache nachladen und danach neu zeichnen (einmal je Sprache). */
  function catSync(lng, after) { if (catLoaded(lng)) return; catLoad(lng).then(function () { if (window.CHVoice) pipCheck(); if (after) after(); voiceInfo(); }).catch(function () { /* Karten aus den Voreinstellungen */ }); }
  function pipLib() {
    if (window.CHVoice || PIP.js) return;
    PIP.js = document.createElement('script'); PIP.js.src = '/chat/voice/voice.js?v=4'; PIP.js.async = true;
    PIP.js.onload = function () { if (CHVoice.register) { var all = []; Object.keys(CAT.by).forEach(function (k) { all.push(CAT.by[k]); }); CHVoice.register(all); } pipCheck(); voiceInfo(); };
    document.head.appendChild(PIP.js);
  }
  /** Echte Stimme (Piper) zur Katalog-ID; ohne Katalog (oder Cloud-Stimme) wie bisher über Sprache + Geschlecht + Voreinstellung. null = keine. */
  function pipOf(gender, preset, lng, id) {
    if (!window.CHVoice || !CHVoice.ok()) return null;
    var e = catEntry(id || (PROF && PROF.voice && PROF.voice.id));
    if (e && (!gender || e.gender === gender) && (!lng || e.lang === normLang(lng))) { if (e.source && e.source.type !== 'piper') return null; var v = CHVoice.pickId ? CHVoice.pickId(e.id) : null; if (v) return v; }
    return CHVoice.pick(normLang(lng || convLang()), gender || (PROF && PROF.gender), preset || (PROF && PROF.voice && PROF.voice.preset));
  }
  function pipOver(over, lng) { return pipOf(over && over.gender, over && over.preset, lng, over && over.id); }
  function pipReady(v) { return !!v && PIP.st[v.key] === 'ready'; }
  /** Ist die Stimme schon im Browser gespeichert? Dann gleich als bereit markieren. */
  function pipCheck() {
    var v = pipOf(null, null, PROF && PROF.lang, PROF && PROF.voice && PROF.voice.id); if (!v || PIP.st[v.key]) return;
    CHVoice.stored(v).then(function (ok) { if (ok && !PIP.st[v.key]) { PIP.st[v.key] = 'ready'; voiceInfo(); } });
  }
  function pipLoad(v, asked) {
    if (!v || PIP.st[v.key] === 'loading' || PIP.st[v.key] === 'ready') return;
    if (!asked && (PIP.st[v.key] === 'fail' || !CHVoice.auto())) return;
    PIP.st[v.key] = 'loading'; PIP.pct[v.key] = 0; voiceInfo();
    CHVoice.load(v, function (l, t) {
      var p = t ? Math.min(99, Math.floor(l * 100 / t)) : 0;
      if (p >= (PIP.pct[v.key] || 0) + 4) { PIP.pct[v.key] = p; voiceInfo(); }
    }).then(function () {
      PIP.st[v.key] = 'ready'; voiceInfo();
      var ce = catEntry(v.id); note(h(_t('asj.pipReady',{n:ce ? ce.label : personaOf(presetOf((PROF && PROF.voice && PROF.voice.preset) || '', PROF && PROF.gender))})), 'ok');
    }, function () { PIP.st[v.key] = 'fail'; voiceInfo(); });
  }
  /** Wie speakServer, nur dass der Ton im Browser entsteht; nächster Satz wird schon berechnet, während der aktuelle läuft. */
  function speakPiper(list, gen, done, v, over, tag) {
    var rate = Number((over && over.rate) || (PROF && PROF.voice && PROF.voice.rate) || 1) || 1;
    var vv = {}; for (var k in v) { if (Object.prototype.hasOwnProperty.call(v, k)) vv[k] = v[k]; }
    vv.length = (v.length || 1) / Math.max(0.75, Math.min(1.3, rate));
    var i = 0, next = CHVoice.say(list[0], vv); next.catch(function () {});
    function fail(from) {
      if (gen !== SPK.gen) return;
      PIP.st[v.key] = 'fail'; voiceInfo();
      speakBrowser(list.slice(from), gen, done, over, tag);
    }
    function step() {
      if (gen !== SPK.gen) return;
      if (i >= list.length) { done(); return; }
      var cur = next, idx = i; i++;
      next = i < list.length ? CHVoice.say(list[i], vv) : null;
      if (next) next.catch(function () {});
      cur.then(function (blob) {
        if (gen !== SPK.gen) return;
        var a = audioEl(); if (SPK.url) { try { URL.revokeObjectURL(SPK.url); } catch (e) { /* egal */ } }
        SPK.url = URL.createObjectURL(blob); a.src = SPK.url;
        var failed = false, once = function () { if (failed) return; failed = true; a.onended = null; a.onerror = null; fail(idx); };
        a.onended = function () { if (failed) return; failed = true; step(); };
        a.onerror = once;
        var p = a.play(); if (p && p.catch) p.catch(once);
      }, function () { fail(idx); });
    }
    step();
  }
  function serverLangOk(lng) { lng = normLang(lng); return !SPK.noLang[lng] && (!TTS_LANGS.length || TTS_LANGS.indexOf(lng) >= 0); }
  function serverCan(lng) { return TTS && SPK.serverOk && serverLangOk(lng); }
  /**
   * Sätze vorlesen. src = {mid, lang} (Antwort aus dem Verlauf) oder {preview:{…}, lang} (Hörprobe); ohne mid/preview nur Browserstimme.
   * onend läuft nur, wenn die Ausgabe nicht unterbrochen wurde.
   */
  function speakList(list, onend, over, src) {
    stopSpeaking();
    src = src || {};
    list = (list || []).filter(function (s) { return String(s).trim() !== ''; }).map(function (s) { return String(s).slice(0, 400); });
    if (!list.length) { if (onend) onend(); return; }
    var gen = SPK.gen, lng = src.lang || convLang();
    setState('speaking');
    var done = function () { if (gen !== SPK.gen) return; setState('idle'); if (onend) onend(); };
    /* Welche Stimme klingt: die gewählte Katalogstimme entscheidet – Premium (Cloud) über den Server, echte Stimme/Variante (Piper) im Browser
       (lädt beim ersten Mal, bis dahin Systemstimme); ohne Katalog wie bisher Server zuerst. */
    var pv = pipOver(over, lng), piperWanted = !!(pv && pv.id);
    if ((src.mid || src.preview) && serverCan(lng) && !piperWanted) speakServer(list, gen, done, { mid: src.mid, preview: src.preview, lang: lng }, over);
    else {
      if (pipReady(pv)) speakPiper(list, gen, done, pv, over, tagOf(lng));
      else { if (pv) pipLoad(pv, false); speakBrowser(list, gen, done, over, tagOf(lng)); }
    }
  }
  function speakMsg(m, onend) { if (m) speakList(m.speech && m.speech.length ? m.speech : sentences(m.content), onend, null, { mid: m.speech && m.speech.length ? m.id : 0, lang: m.lang }); }
  /** Ehrliche Statuszeile: KI-Stimme nur, wenn der Server sie wirklich liefert – sonst sagen, dass es die Systemstimme ist und warum. */
  function voiceStatus(lng, id) {
    var L = LANGS.filter(function (l) { return l.code === normLang(lng); })[0];
    var e = catEntry(id); if (e && e.source && e.source.type === 'piper' && window.speechSynthesis && !SPK.trouble) return { k: 'info', t: _t('asj.vs.op') };   // echte Stimme gewählt, lädt noch → Systemstimme
    if (serverCan(lng)) return { k: 'ok', t: _t('asj.vs.ok') };
    if (!window.speechSynthesis || SPK.trouble) return { k: 'warn', t: NO_VOICE_TXT };
    if (TTS && SPK.serverOk && !serverLangOk(lng)) return { k: 'info', t: _t('asj.vs.lang',{l:L ? lname(L) : _t('asj.thisLang')}) };
    if (TTS && SPK.why === 'balance') return { k: 'info', t: _t('asj.vs.bal') };
    if (TTS && SPK.why === 'limit') return { k: 'info', t: _t('asj.vs.limit') };
    return { k: 'info', t: _t('asj.vs.op') };
  }
  /** Wird gerade die Browserstimme benutzt (kein Sprachdienst, aber Ton möglich)? Dann zeigen wir ehrlich, welche. */
  function browserUsed(lng) { return voiceStatus(lng).k === 'info'; }
  /** Tooltip-Zusatz „ · Browserstimme: Katja“ für Chips und Hörprobe-Knöpfe (leer, wenn die Server-Stimme spricht). */
  function voiceTip(gender, preset, lng, id) {
    var pv = pipOf(gender, preset, lng, id);
    if (pipReady(pv) && (!serverCan(lng) || pv.id)) return ' · ' + _t('asj.real');
    if (!browserUsed(lng) && !(pv && pv.id)) return '';
    var pk = pickVoiceEx(gender, preset, tagOf(lng));
    return pk.v ? ' · ' + _t('asj.bvoice',{n:voiceLabel(pk.v)}) + (pk.shared ? ' ' + _t('asj.shared') : '') : '';
  }
  /** Zeile „Browserstimme: <Name>“ für die aktive Voreinstellung + ein Satz, wenn sich Voreinstellungen eine Stimme teilen müssen. */
  function voiceLineHtml(gender, preset, lng) {
    if (!browserUsed(lng)) return '';
    var tag = tagOf(lng), m = voiceMap(gender, tag), pk = pickVoiceEx(gender, preset, tag);
    if (!pk.v) return '';
    var L = LANGS.filter(function (l) { return l.code === normLang(lng); })[0];
    var s = '<span class="as-vname">' + _t('asj.bvoice',{n:'<b>' + h(voiceLabel(pk.v)) + '</b>'}) +
      (m.level === 'any' ? ' <span class="as-vlang">' + h(_t('asj.noLangVoice',{c:pk.v.lang || '',l:L ? lname(L) : _t('asj.thisLang')})) + '</span>' : '') + '</span>';
    if (m.shares.length) {
      s += '<span class="as-vshare">' + m.shares.map(function (x) {
        return h(_t('asj.share',{n:joinNames(x.ids.map(function (id) { return personaOf(presetOf(id, gender)); })),v:voiceLabel(x.v)}));
      }).join('; ') + ' – ' + _t('asj.shareTail') + '</span>';
    }
    return s;
  }
  /** Hinweis in Auswahl und Seitenleiste: mit KI-Stimme hat jede Assistenz eine eigene, echte Stimme. */
  function kiHint(gender) {
    var names = VOICES.filter(function (v) { return v.gender === gender; }).map(personaOf);
    return names.length > 1 ? _t('asj.kiN',{n:joinNames(names)}) : _t('asj.ki1');
  }
  /** Zeile zur echten Stimme: bereit, lädt (Prozent) oder Knopf zum Laden. Leer, wenn es für Sprache/Geschlecht keine gibt. */
  function pipLine(v) {
    if (!v) return '';
    var s = PIP.st[v.key];
    if (s === 'loading') return '<span class="as-vpip">' + h(_t('asj.pipLoading',{p:PIP.pct[v.key] || 0,mb:I18N.num(v.mb)})) + '</span>';
    if (s === 'fail') return '<span class="as-vpip">' + _t('asj.pipFail') + ' <button type="button" class="as-pipload" data-pip="1">' + _t('pw.retry') + '</button></span>';
    return '<span class="as-vpip"><button type="button" class="as-pipload" data-pip="1">' + _t('asj.pipLoad') + '</button> ' + h(_t('asj.pipOnce',{mb:I18N.num(v.mb)})) + '</span>';
  }
  function paintStatus(el, gender, preset, lng, nameEl, hintEl, id) {
    var pv = pipOf(gender, preset, lng, id); if (serverCan(lng) && !(pv && pv.id)) pv = null;   // Katalog-Piperstimme gilt auch neben der Server-Stimme
    if (pipReady(pv)) {
      if (el) { el.textContent = _t('asj.pipOn'); el.setAttribute('data-k', 'ok'); el.title = _t('asj.model',{n:pv.name}); }
      if (nameEl) nameEl.innerHTML = '<span class="as-vpip">' + h(_t('asj.oss',{n:pv.name})) + (pv.tier === 'variant' ? ' · ' + h(_t('vc.tier.variant')) : '') + ' · <a href="/chat/vendor/voice/LICENSES.txt" target="_blank" rel="noopener">' + _t('asj.lic') + '</a></span>';
      if (hintEl) { hintEl.textContent = ''; hintEl.classList.add('hidden'); }
      return;
    }
    var st = voiceStatus(lng, pv && pv.id ? id : null), used = st.k === 'info', pk = used ? pickVoiceEx(gender, preset, tagOf(lng)) : null;
    if (el) {
      el.textContent = st.t; el.setAttribute('data-k', st.k);
      if (pk && pk.v) el.title = _t('asj.bvoice',{n:voiceLabel(pk.v)}); else el.removeAttribute('title');
    }
    if (nameEl) nameEl.innerHTML = used ? voiceLineHtml(gender, preset, lng) + pipLine(pv) : '';
    if (hintEl) { hintEl.textContent = used && !pv ? kiHint(gender) : ''; hintEl.classList.toggle('hidden', !used || !!pv); }
  }
  /** Tooltips der Stimm-Chips und Hörprobe-Knöpfe nachziehen (Stimmenliste kommt asynchron). */
  /** Name + Klang einer Stimmkarte: Katalogstimme (Vorname, Stichworte) oder Voreinstellung. */
  function cardOf(idOrPreset, gender) {
    var e = catEntry(idOrPreset); if (e) return { id: e.id, name: e.label, desc: catDesc(e), preset: e.preset, e: e };
    var v = presetOf(idOrPreset, gender); return v ? { id: v.id, name: vpL(v), desc: vpD(v), preset: v.id, e: null } : null;
  }
  function voiceTips() {
    if (PROF) document.querySelectorAll('#asTones button[data-v]').forEach(function (b) {
      var c = cardOf(b.dataset.v, PROF.gender); if (c) b.title = c.name + (c.desc ? ' – ' + c.desc : '') + voiceTip(PROF.gender, c.preset, PROF.lang, c.e ? c.id : null);
    });
    if (D) {
      document.querySelectorAll('#' + (isCho() ? 'asCTones' : 'asFVoices') + ' .pl[data-play]').forEach(function (b) {
        var c = cardOf(b.dataset.play, D.gender); if (c) b.title = _t('asj.sampleOf',{n:c.name}) + voiceTip(D.gender, c.preset, D.lang, c.e ? c.id : null);
      });
      var main = g(isCho() ? 'asChoPlay' : 'asFPreview'), cur = cardOf(D.voice.id || D.voice.preset, D.gender);
      if (main) main.title = (cur ? _t('asj.sampleOf',{n:cur.name}) : _t('asj.sampleW')) + voiceTip(D.gender, D.voice.preset, D.lang, D.voice.id);
    }
  }
  function voiceInfo() {
    if (PROF) paintStatus(g('asVoiceInfo'), PROF.gender, PROF.voice && PROF.voice.preset, PROF.lang, g('asVoiceName'), null, PROF.voice && PROF.voice.id);
    if (D) { var cho = D.mode === 'cho'; paintStatus(g(cho ? 'asCVoiceStat' : 'asFPrevInfo'), D.gender, D.voice.preset, D.lang, g(cho ? 'asCVoiceName' : 'asFVoiceName'), g(cho ? 'asCVoiceHint' : 'asFVoiceHint'), D.voice.id); }
    voiceTips();
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
    r.lang = tagOf(micLang()); r.interimResults = true; r.continuous = false; r.maxAlternatives = 1;
    var input = g('asInput'), before = auto ? '' : input.value.trim();
    r.onresult = function (ev) {
      var interim = '';
      for (var i = ev.resultIndex; i < ev.results.length; i++) { var x = ev.results[i]; if (x.isFinal) REC.final += x[0].transcript; else interim += x[0].transcript; }
      input.value = ((before ? before + ' ' : '') + REC.final + interim).trim(); autosize();
    };
    r.onerror = function (e) {
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { micUsed = false; note(_t('asj.micBlocked'), 'err'); }
      else if (e.error === 'network') note(_t('asj.micNet'), 'err');
      else if (e.error === 'language-not-supported') note(_t('asj.micLang'), 'err');
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
    if (!SR) { btn.disabled = true; btn.title = _t('asj.noSR'); return; }
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
    if (img && img.getAttribute('src') !== url) { img.classList.add('ld'); img.onload = function () { img.classList.remove('ld'); }; img.onerror = function () { img.classList.remove('ld'); }; img.src = url; img.alt = _t('asj.portraitOf',{n:PROF.name}); }
    g('asName').textContent = PROF.name;
    g('asRole').textContent = _tx(PROF.role);
    g('asTitle').textContent = _tx(PROF.role);
    var sie = isSie(), f = fem();
    g('asSub').textContent = TS('asj.sub', { n: PROF.name });
    g('asHeadInfo').textContent = !TOOLS ? TS('asj.advOnly') : TS('asj.noChange');
    g('asHeadInfo').classList.toggle('as-warn', !TOOLS);
    g('asTip').innerHTML = TS('asj.tip');
    g('asTonesL').innerHTML = h(_t('asj.voice')) + '<span>' + h(_t('asj.tapSwitch')) + '</span>';
    renderToneChips();
    micLangUi();
    g('asMemTitle').textContent = TS('asj.memT');
    g('asMemEmpty').textContent = TS('asj.memE');
    g('asInput').placeholder = TS('asj.inPh');
    g('asTip').classList.toggle('hidden', !!PROF.saved);
    var sec = document.querySelector('#' + PANEL + ' .as-chat'); if (sec) sec.setAttribute('aria-label', _t('asj.convWith',{n:PROF.name,r:f ? _t('asj.asstF') : _t('asj.asstM')}));
    document.querySelectorAll('#' + PANEL + ' .as-av').forEach(function (a) { a.src = url; });
    voiceBtn(); if (window.CHVoice) pipCheck(); voiceInfo();
  }
  /** Stimmkarten (Katalog) oder Voreinstellungen (Ersatz) für Sprache + Geschlecht: [{id, name, desc, tier, preset, e}], die aktive zuerst markiert vom Aufrufer. */
  function voiceCards(lng, gender) {
    var L = catList(lng, gender);
    if (L.length) return L.map(function (e) { return { id: e.id, name: e.label, desc: catDesc(e), tier: e.tier, preset: e.preset, e: e, mb: e.mb }; });
    return VOICES.filter(function (v) { return v.gender === gender; }).map(function (v) { return { id: v.id, name: vpL(v), desc: vpD(v), tier: null, preset: v.id, e: null }; });
  }
  /** Stimmkarten auf der Profilkarte: alle Stimmen der Sprache und des Geschlechts (echt, Variante, Premium), aktive markiert – Antippen wechselt sofort (gespeichert) und spielt eine Hörprobe. */
  function renderToneChips() {
    var box = g('asTones'); if (!box || !PROF) return;
    var cur = PROF.voice && (PROF.voice.id || PROF.voice.preset), cards = voiceCards(PROF.lang, PROF.gender);
    box.innerHTML = cards.map(function (c) {
      var on = c.id === cur || (!catEntry(cur) && c.preset === PROF.voice.preset && !c.e);
      return '<button type="button" data-v="' + h(c.id) + '" data-play="chip:' + h(c.id) + '" data-label="' + h(on ? _t('asj.chipOn',{n:c.name}) : _t('asj.chipPick',{n:c.name})) + '" aria-pressed="' + (on ? 'true' : 'false') + '" title="' + h(c.name + (c.desc ? ' – ' + c.desc : '') + voiceTip(PROF.gender, c.preset, PROF.lang, c.e ? c.id : null)) + '" aria-label="' + h(_t('asj.voiceN',{n:c.name})) + '"><span class="as-vn">' + h(c.name) + '</span>' + (c.tier ? tierBadge(c.e) : '') + '</button>';
    }).join('');
    box.classList.toggle('as-many', cards.length > 6);
    var lb = g('asTonesL'); if (lb) lb.innerHTML = h(_t('asj.voice')) + (cards.length ? ' <i class="as-vcount">' + cards.length + '</i>' : '') + '<span>' + h(_t('asj.tapSwitch')) + '</span>';
    markPlaying();
  }
  /** Profilwerte als Hörprobe-Beschreibung. $voice = Katalog-ID oder Voreinstellung. */
  function profVoice(p, voice) {
    var c = cardOf(voice, p.gender) || {};
    return { name: p.name, gender: p.gender, address: p.personality.address, lang: p.lang || 'de', tone: toneOf(c.preset) || p.voice.tone, rate: Number(p.voice.rate) || 1, pitch: Number(p.voice.pitch) || 1, preset: c.preset || p.voice.preset, id: c.e ? c.id : null };
  }
  var toneBusy = 0;
  function quickTone(id) {
    if (!PROF || !id) return;
    unlockAudio();
    var c = cardOf(id, PROF.gender); if (!c || c.id !== id) return;
    var cur = PROF.voice.id || PROF.voice.preset;
    if (cur === id) { playPreview(profVoice(PROF, id), 'chip:' + id); return; }   // aktive Stimme: nur anhören
    var old = { preset: PROF.voice.preset, tone: PROF.voice.tone, id: PROF.voice.id }, tone = toneOf(c.preset) || PROF.voice.tone, my = ++toneBusy;
    PROF.voice.preset = c.preset; PROF.voice.tone = tone; PROF.voice.id = c.e ? c.id : PROF.voice.id; renderToneChips(); voiceInfo();
    playPreview(profVoice(PROF, id), 'chip:' + id);
    var body = c.e ? { voice: { id: c.id } } : { voice: { preset: c.preset, tone: tone } };
    api('/assistant/profile', { method: 'POST', body: JSON.stringify(body) }).then(function (j) {
      if (my !== toneBusy) return;
      takeProfile(j); renderToneChips(); voiceInfo();
      note(h(_t('asj.switched',{n:c.name})), 'ok');
    }).catch(function (e) {
      if (my !== toneBusy) return;
      PROF.voice.preset = old.preset; PROF.voice.tone = old.tone; PROF.voice.id = old.id; renderToneChips(); voiceInfo();
      note(h(((e && e.message) || _t('asj.voiceFail'))), 'err');
    });
  }
  /** Auswahl „Sprache der Spracheingabe“ (Auto = Sprache des Gesprächs) und Sprachliste im Anpassen-Dialog aus derselben Tabelle. */
  function langOptions(sel, withAuto) {
    if (!sel) return;
    var cur = sel.value;
    var pre = withAuto ? '🎙 ' : '';
    var cl = (LANGS.filter(function (l) { return l.code === convLang(); })[0]) || LANGS[0];
    sel.innerHTML = (withAuto ? '<option value="auto">' + pre + h(_t('asj.auto')) + '</option>' : '') +
      LANGS.map(function (l) { var ln = lname(l); return '<option value="' + h(l.code) + '">' + (withAuto ? pre + h(l.code.toUpperCase()) + ' · ' + h(l.native) : h(l.native) + (l.native !== ln ? ' · ' + h(ln) : '')) + '</option>'; }).join('');
    if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
  }
  function micLangUi() {
    var sel = g('asMicLang'); if (!sel) return;
    langOptions(sel, true);
    sel.value = micLangPick !== 'auto' && LANG_TAG[micLangPick] ? micLangPick : 'auto';
    var cl = ((LANGS.filter(function (l) { return l.code === convLang(); })[0]) || LANGS[0]);
    sel.title = _t('asj.micT',{l:lname(cl)});
    sel.setAttribute('aria-label', sel.title);
  }
  function voiceBtn() {
    var b = g('asVoiceBtn'); if (!b) return;
    b.textContent = voiceOn ? _t('asj.vOn') : _t('asj.vOff');
    b.setAttribute('aria-pressed', voiceOn ? 'true' : 'false');
    b.classList.toggle('gold', voiceOn);
    b.title = voiceOn ? _t('asj.vOnT') : _t('asj.vOffT');
  }

  /* ======================================================================= Gespräch */
  function actsHtml(acts) {
    if (!acts || !acts.length) return '';
    return '<div class="as-acts">' + acts.map(function (a) {
      var link = a.link && /^https:\/\//.test(String(a.link.url || '')) ? '<a class="as-go" href="' + h(a.link.url) + '" target="_blank" rel="noopener noreferrer">' + h(a.link.label ? _tx(a.link.label) : _t('sj.open')) + ' →</a>' : '';
      var tab = a.ok && a.tab && TABN[a.tab] ? '<button type="button" class="as-go" data-tab="' + h(a.tab) + '">' + h(tabName(a.tab)) + ' →</button>' : '';
      return '<div class="as-act' + (a.ok ? '' : ' err') + '"><span class="ic" aria-hidden="true">' + (a.ok ? '✓' : '!') + '</span><span class="tx">' + h(a.summary || a.name) + '</span>' + link + tab + '</div>';
    }).join('') + '</div>';
  }
  function msgHtml(m, i, fresh) {
    if (m.role === 'sys') return '<div class="as-m sys' + (m.err ? ' err' : '') + (fresh ? ' as-new' : '') + '"><div class="as-b">' + (m.html || h(m.content)) + '</div></div>';
    if (m.role === 'user') return '<div class="as-m u' + (fresh ? ' as-new' : '') + '"><div class="as-col"><div class="as-b">' + fmt(m.content) + '</div></div></div>';
    var canPlay = !!(window.speechSynthesis || TTS);
    return '<div class="as-m a' + (fresh ? ' as-new' : '') + '"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-col"><div class="as-b">' + fmt(m.content) + '</div>' + actsHtml(m.actions) +
      (canPlay ? '<button type="button" class="as-play" data-i="' + i + '" aria-label="' + h(_t('asj.readL')) + '">' + _t('asj.read') + '</button>' : '') + '</div></div>';
  }
  function renderHistory() {
    var box = g('asMsgs'); if (!box) return;
    box.innerHTML = HIST.map(function (m, i) { return msgHtml(m, i, false); }).join('');
    box.scrollTop = box.scrollHeight;
    micLangUi();   // „Auto · <Sprache des Gesprächs>“
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
    if (on && !t) { g('asMsgs').insertAdjacentHTML('beforeend', '<div class="as-m a as-typing as-new" id="asTyping" aria-label="' + h(_t('asj.typing',{n:PROF ? PROF.name : ''})) + '"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-b"><i></i><i></i><i></i></div></div>'); scrollDown(); }
    if (!on && t) t.remove();
  }
  function busyUi(on) {
    g('asSend').disabled = on;
    document.querySelectorAll('#asSugg button').forEach(function (b) { b.disabled = on; });
  }
  function renderSugg(list) {
    g('asSugg').innerHTML = (list || []).slice(0, 4).map(function (s) { s = _tx(s); return '<button type="button" data-s="' + h(s) + '">' + h(s) + '</button>'; }).join('');
  }
  function renderCheck(items, pct) {
    pct = Math.max(0, Math.min(100, Number(pct) || 0));
    g('asRingFg').setAttribute('stroke-dashoffset', String(C_RING * (1 - pct / 100)));
    g('asPct').textContent = pct + ' %';
    g('asRing').setAttribute('aria-label', _t('asj.ringL',{p:pct}));
    var n = (items || []).filter(function (i) { return i.done; }).length, t = (items || []).length;
    g('asPctTxt').textContent = pct === 0 ? _t('asj.start') : (pct >= 100 ? _t('asj.allDone') : (pct >= 50 ? _t('asj.halfN',{n:n,t:t}) : _t('asj.nOfT',{n:n,t:t})));
    g('asCheck').innerHTML = (items || []).map(function (i) {
      return '<li class="' + (i.done ? 'done' : 'open') + '"' + (i.done ? '' : ' role="button" tabindex="0" data-p="' + h(_tx(i.prompt || '')) + '" title="' + h(_t('asj.doWith')) + '"') + '><span class="as-ck" aria-hidden="true">' + (i.done ? CHECK : '') + '</span><div><b>' + h(_tx(i.label)) + '</b><span>' + h(_tx(i.hint || '')) + '</span></div></li>';
    }).join('');
  }
  function renderMem(list) {
    list = list || [];
    g('asMemN').textContent = String(list.length);
    g('asMemEmpty').classList.toggle('hidden', list.length > 0);
    g('asMem').innerHTML = list.map(function (m) {
      return '<li data-id="' + h(m.id) + '"><div style="min-width:0"><span class="tp">' + h(m.topic) + '</span>' + h(m.fact) + '</div><button type="button" class="as-x" data-id="' + h(m.id) + '" aria-label="' + h(_t('asj.forgetL',{t:m.topic})) + '" title="' + h(_t('asj.forget')) + '">×</button></li>';
    }).join('');
  }
  function applyState(j) {
    if (!j) return;
    if (j.checklist) renderCheck(j.checklist, j.progress);
    if (j.memory) renderMem(j.memory);
    if (j.suggestions) renderSugg(j.suggestions);
    if (typeof j.balance === 'number') setBalance(j.balance);
    if (j.cost) { COST = j.cost; g('asCost').textContent = _t('asj.cost',{t:COST.turn,a:COST.tool}); }
  }
  function runOpens(acts) {
    var a = (acts || []).filter(function (x) { return x.ok && x.open && x.tab; })[0];
    if (a && typeof go === 'function') setTimeout(function () { go(a.tab); }, 900);
  }
  function send(text, o) {
    text = String(text || '').trim();
    if (!text || busy) return;
    if (text.length > 2000) { note(h(_t('asj.tooLong',{n:I18N.num(2000)})), 'err'); return; }
    stopSpeaking(); abortListen(); unlockAudio();
    busy = true; busyUi(true); lastViaVoice = !!(o && o.viaVoice);
    var input = g('asInput'); input.value = ''; autosize(); msg(g('asNote'), '');
    addMsg({ role: 'user', content: text });
    typing(true); setState('thinking');
    api('/assistant/chat', { method: 'POST', body: JSON.stringify({ message: text, voice: voiceOn }) }).then(function (j) {
      typing(false);
      var am = { id: j.id, role: 'assistant', content: String(j.reply || ''), actions: j.actions || [], lang: j.lang || null, speech: j.speech || null };
      addMsg(am); micLangUi();
      if (typeof j.tools_ready === 'boolean' && j.tools_ready !== TOOLS) { TOOLS = j.tools_ready; applyProfile(); }
      applyState(j);
      var acts = j.actions || [];
      if (voiceOn && j.speech && j.speech.length) {
        speakMsg(am, function () {
          runOpens(acts);
          if (voiceOn && micUsed && lastViaVoice && SR && visible() && !document.hidden && !acts.some(function (a) { return a.open; })) setTimeout(function () { if (!busy && !speaking()) startListen(true); }, 250);
        });
      } else { setState('idle'); runOpens(acts); }
    }).catch(function (e) {
      typing(false); setState('idle');
      var d = (e && e.data) || {};
      if (e && e.status === 402) {
        sysMsg(h((I18N.lang === 'de' && d.message) || TS('asj.noBal')) + ' <a href="/#preise" target="_blank" rel="noopener">' + _t('st.tokens_kaufen') + '</a>', true);
        if (typeof d.balance === 'number') setBalance(d.balance);
      } else if (e && e.status === 503) {
        sysMsg(h((I18N.lang === 'de' && d.message) || _t('asj.away')), true);
        if (d.actions && d.actions.length) { HIST.push({ role: 'assistant', content: '', actions: d.actions }); g('asMsgs').insertAdjacentHTML('beforeend', '<div class="as-m a as-new"><img class="as-av" src="' + h(avatar()) + '" alt=""><div class="as-col">' + actsHtml(d.actions) + '</div></div>'); scrollDown(); }
        input.value = text; autosize();
      } else {
        sysMsg(h((e && e.message) || _t('asj.failed')), true);
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
      HIST = histFrom(st);
      applyProfile(); renderHistory(); applyState(st); micLangUi();
      if (r[1]) { if (PROF && PROF.chosen === false) showChooser(); else if (D && D.mode === 'cho') hideChooser(); }
      if (!TOOLS) sysMsg(h(TS('asj.noTools')));
      lastLoad = Date.now();
    });
  }
  function histFrom(st) {
    return (st.history || []).map(function (m) { return { id: m.id, role: m.role, content: m.content, actions: m.actions || [], greeting: !!m.greeting, lang: m.lang || null, speech: m.speech || null }; });
  }
  function takeProfile(j) {
    PROF = j.profile; PORTRAITS = j.portraits || []; VOICES = j.voices || []; TTS = !!j.tts; if (j.photo_max) PHOTO_MAX = j.photo_max;
    if (TTS && j.tts_active === false && SPK.serverOk) { SPK.serverOk = false; SPK.why = 'balance'; }   // eingerichtet, aber ohne Guthaben/Agent nicht nutzbar
    else if (TTS && j.tts_active === true && SPK.why === 'balance') { SPK.serverOk = true; SPK.why = ''; }
    if (j.tts_langs) TTS_LANGS = j.tts_langs.map(normLang);
    if (typeof j.tools_ready === 'boolean') TOOLS = j.tools_ready;
    if (j.langs && j.langs.length) { LANGS = j.langs; LANG_TAG = {}; LANGS.forEach(function (l) { LANG_TAG[l.code] = l.tag; }); }
    langOptions(g('asFLang'), false);
    if (PROF) catSync(PROF.lang, function () { if (PROF) renderToneChips(); if (D) redraw(); });   // Stimmenkatalog der Profilsprache → Karten
  }

  /* ======================================================================= Entwurf (Auswahl beim ersten Besuch + „Person & Stimme ändern“) */
  /* D = ungespeicherter Entwurf; D.mode = 'cho' (Auswahl im Panel) oder 'drawer' (Seitenleiste). Beide nutzen dieselben Funktionen. */
  var D = null, lastFocus = null;
  function draftFrom(p) {
    return { name: p.name, gender: p.gender, portrait: p.portrait, photo: null, remove_photo: false, photoUrl: p.portrait === 'custom' ? p.portrait_url : (p.photo_url || null),
      voice: { tone: p.voice.tone, rate: Number(p.voice.rate) || 1, pitch: Number(p.voice.pitch) || 1, preset: p.voice.preset, id: p.voice.id || null },
      personality: { address: p.personality.address, mood: p.personality.mood, humor: Number(p.personality.humor), detail: p.personality.detail }, lang: p.lang || 'de', autoName: true, mode: 'drawer' };
  }
  /** Entwurfsstimme an Sprache/Geschlecht anpassen (Katalog nachladen, dann id + Voreinstellung nachziehen). */
  function draftFit() {
    if (!D) return;
    var fix = function () { if (!D) return; var id = catFit(D.voice.id, D.lang, D.gender, D.voice.preset); if (id) { var e = catEntry(id); D.voice.id = id; if (e) { D.voice.preset = e.preset; D.voice.tone = toneOf(e.preset) || D.voice.tone; } } };
    if (catLoaded(D.lang)) fix(); else catSync(D.lang, function () { fix(); redraw(); });
  }
  function isCho() { return !!D && D.mode === 'cho'; }
  function nameEl() { return g(isCho() ? 'asCName' : 'asFName'); }
  function msgEl() { return g(isCho() ? 'asCMsg' : 'asFMsg'); }
  function redraw() { if (!D) return; if (isCho()) drawChooser(); else drawDrawer(); }
  function portraitById(id) { var x = null; PORTRAITS.forEach(function (p) { if (p.id === id) x = p; }); return x; }
  function draftImg() { if (D.portrait === 'custom' && D.photoUrl) return imgUrl(D.photoUrl); var p = portraitById(D.portrait); return p ? imgUrl(p.url) : avatar(); }
  function seg(id, val) { document.querySelectorAll('#' + id + ' button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.v) === String(val) ? 'true' : 'false'); }); }
  function fx(n) { return I18N.num(Math.round(Number(n) * 100) / 100, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '×'; }
  function draftRole() { var f = D.gender !== 'm', sie = D.personality.address === 'sie'; return f ? (sie ? _t('asj.roleFf') : _t('asj.roleF')) : (sie ? _t('asj.roleMf') : _t('asj.roleM')); }
  function draftName() { return (D.name || '').replace(/\s+/g, ' ').trim() || (D.gender !== 'm' ? _t('asj.asstF') : _t('asj.asstM')); }
  /** Porträt-Kacheln (eigenes Foto zuerst, Hochladen zuletzt) – gleiche Kacheln in Auswahl und Seitenleiste. */
  function portraitTiles(upId) {
    var tiles = PORTRAITS.filter(function (p) { return p.gender === D.gender; }).map(function (p) {
      return '<button type="button" data-p="' + h(p.id) + '" aria-pressed="' + (D.portrait === p.id ? 'true' : 'false') + '" aria-label="' + h(_t('asj.portraitN',{n:p.label})) + '"><img src="' + h(imgUrl(p.url)) + '" alt="" loading="lazy"><span class="lb">' + h(p.label) + '</span></button>';
    });
    if (D.photoUrl) tiles.unshift('<button type="button" data-p="custom" aria-pressed="' + (D.portrait === 'custom' ? 'true' : 'false') + '" aria-label="' + h(_t('asj.own')) + '"><img src="' + h(imgUrl(D.photoUrl)) + '" alt=""><span class="lb">' + h(_t('asj.own')) + '</span></button>');
    tiles.push('<button type="button" class="up"' + (upId ? ' id="' + upId + '"' : '') + ' aria-label="' + h(_t('asj.ownUp')) + '"><i aria-hidden="true">＋</i>' + h(_t('asj.own')) + '</button>');
    return tiles.join('');
  }
  /** Stimmkarten (Katalog der Entwurfssprache, ≥ 6 je Geschlecht: Name, Klang, Stufe echt/Variante/Premium): Auswahl-Knopf (data-v, aria-pressed) + ▶-Hörprobe (data-play). */
  function toneCards() {
    var cards = voiceCards(D.lang, D.gender), cur = D.voice.id || D.voice.preset;
    if (!cards.length) return '<div class="mute small as-vempty">' + h(catLoaded(D.lang) ? _t('vc.none') : _t('vc.loading')) + '</div>';
    return cards.map(function (c) {
      var on = c.id === cur || (!catEntry(cur) && !c.e && c.preset === D.voice.preset);
      return '<div class="as-tcard' + (on ? ' on' : '') + '" data-tier="' + h(c.tier || '') + '"><button type="button" class="sel" data-v="' + h(c.id) + '" aria-pressed="' + (on ? 'true' : 'false') + '" aria-label="' + h(_t('vc.pickN',{n:c.name})) + '"><b>' + h(c.name) + (c.tier ? tierBadge(c.e) : '') + '</b>' + h(c.desc) + (c.e && c.e.source && c.e.source.type === 'piper' && c.mb ? ' <span class="as-vmb">· ' + h(_t('vc.onceMb',{mb:I18N.num(c.mb)})) + '</span>' : '') + '</button>' +
        '<button type="button" class="pl" data-play="' + h(c.id) + '" data-label="' + h(_t('asj.sampleOf',{n:c.name})) + '" aria-label="' + h(_t('asj.sampleOf',{n:c.name})) + '" title="' + h(_t('asj.sampleOf',{n:c.name}) + voiceTip(D.gender, c.preset, D.lang, c.e ? c.id : null)) + '">' + _t('asj.sample') + '</button></div>';
    }).join('');
  }
  function drawDrawer() {
    var f = D.gender !== 'm';
    g('asDrImg').src = draftImg();
    g('asDrTitle').textContent = (D.name || (f ? _t('asj.asstF') : _t('asj.asstM'))) + ' · ' + _t('asj.pv');
    g('asDrSub').textContent = draftRole();
    seg('asFGender', D.gender); seg('asFTone', D.voice.tone); seg('asFAddress', D.personality.address); seg('asFMood', D.personality.mood); seg('asFHumor', D.personality.humor); seg('asFDetail', D.personality.detail);
    g('asFPortraits').innerHTML = portraitTiles('asFUp');
    g('asFPhotoRm').classList.toggle('hidden', !D.photoUrl);
    g('asFVoices').innerHTML = toneCards();
    g('asFRate').value = String(D.voice.rate); g('asFRateO').textContent = fx(D.voice.rate);
    g('asFPitch').value = String(D.voice.pitch); g('asFPitchO').textContent = fx(D.voice.pitch);
    g('asFLang').value = D.lang;
    var sie = D.personality.address === 'sie';
    g('asFGenderL').textContent = sie ? _t('asj.who.f') : _t('asj.who');
    g('asFRights').textContent = sie ? _t('asj.rights.f') : _t('asj.rights');
    g('asFLangHint').textContent = _t('asj.langHint' + (f ? 'F' : 'M') + (sie ? '.f' : ''), { n: LANGS.length });
    voiceInfo(); markPlaying();
  }
  function drawChooser() {
    var f = D.gender !== 'm', sie = D.personality.address === 'sie', nm = draftName(), cur = cardOf(D.voice.id || D.voice.preset, D.gender);
    var img = g('asChoImg'), u = draftImg(); if (img.getAttribute('src') !== u) img.src = u;
    img.alt = _t('asj.portraitOf',{n:nm});
    g('asChoName').textContent = nm; g('asChoRole').textContent = draftRole();
    g('asChoWho').textContent = f ? _t('asj.she') : _t('asj.he');
    g('asChoVoice').textContent = cur ? cur.name + (cur.desc ? ' · ' + cur.desc : '') : '';
    g('asChoTitle').textContent = sie ? _t('asj.who.f') : _t('asj.who');
    g('asChoLead').textContent = sie ? _t('asj.lead.f') : _t('asj.lead');
    g('asCHint').textContent = sie ? _t('asj.hint.f') : _t('asj.hint');
    seg('asCGender', D.gender); seg('asCAddress', D.personality.address); seg('asCMood', D.personality.mood);
    g('asCPortraits').innerHTML = portraitTiles('');
    g('asCTones').innerHTML = toneCards();
    g('asCStartName').textContent = nm;
    g('asCStart').setAttribute('aria-label', _t('asj.startL',{n:nm}));
    voiceInfo(); markPlaying();
  }
  function showChooser() {
    if (!PROF) return;
    D = draftFrom(PROF); D.mode = 'cho'; draftFit();
    g('asCName').value = D.name; msg(g('asCMsg'), '');
    g('asCho').classList.remove('hidden');
    document.querySelectorAll('#' + PANEL + ' .as-grid, #' + PANEL + ' .as-titlerow').forEach(function (el) { el.classList.add('hidden'); });
    drawChooser();
  }
  function hideChooser() {
    stopSpeaking();
    g('asCho').classList.add('hidden'); if (isCho()) D = null;
    document.querySelectorAll('#' + PANEL + ' .as-grid, #' + PANEL + ' .as-titlerow').forEach(function (el) { el.classList.remove('hidden'); });
  }
  function openDrawer() {
    if (!PROF) return;
    D = draftFrom(PROF); lastFocus = document.activeElement; draftFit();
    g('asFName').value = D.name; msg(g('asFMsg'), '');
    drawDrawer();
    g('asDrawer').classList.remove('hidden');
    document.addEventListener('keydown', drawerKey, true);
    setTimeout(function () { try { g('asFName').focus(); } catch (e) { /* egal */ } }, 60);
  }
  function closeDrawer() {
    stopSpeaking();
    g('asDrawer').classList.add('hidden'); if (D && !isCho()) D = null;
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
  /** Stimme im Entwurf wählen: Katalog-ID (setzt Voreinstellung + Sprechweise nach) oder Voreinstellung (ohne Katalog). */
  function pickPreset(id) {
    var e = catEntry(id);
    if (e) { if (e.gender !== D.gender || e.lang !== normLang(D.lang)) return false; D.voice.id = e.id; D.voice.preset = e.preset; D.voice.tone = toneOf(e.preset) || D.voice.tone; return true; }
    var v = presetOf(id, D.gender); if (!v || v.id !== id) return false; D.voice.preset = id; D.voice.tone = toneOf(id) || D.voice.tone; D.voice.id = catFit(null, D.lang, D.gender, id) || null; return true;
  }
  function setGender(gd) {
    if (D.gender === gd) return;
    var oldP = portraitById(D.portrait);
    D.gender = gd;
    if (D.portrait !== 'custom') {
      var first = PORTRAITS.filter(function (p) { return p.gender === gd; })[0];
      if (first) { if (D.autoName && oldP && (D.name === oldP.label || D.name === 'Lea')) { D.name = first.label; nameEl().value = D.name; } D.portrait = first.id; }
    }
    var cur = VOICES.filter(function (v) { return v.id === D.voice.preset; })[0];   // exakt nachschlagen – presetOf fiele auf die erste Stimme des neuen Geschlechts zurück
    if (!cur || cur.gender !== gd) D.voice.preset = toneToPreset(gd, D.voice.tone);
    D.voice.id = catFit(null, D.lang, gd, D.voice.preset) || null;   // Katalogstimme des neuen Geschlechts mit derselben Voreinstellung
    stopSpeaking(); redraw();
  }
  function pickPortrait(id) {
    if (id === 'custom') { D.portrait = 'custom'; redraw(); return; }
    var p = portraitById(id), old = portraitById(D.portrait); if (!p) return;
    if (D.autoName && (!D.name || (old && D.name === old.label) || D.name === 'Lea' || D.name === 'Assistent')) { D.name = p.label; nameEl().value = D.name; }
    D.portrait = id; redraw();
  }
  /** Foto im Browser quadratisch zuschneiden, auf 512 px verkleinern und als JPEG ≤ 350 KB kodieren. */
  function handleFile(file) {
    if (!file || !D) return;
    var box = msgEl();
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { msg(box, _t('asj.ph.type'), 'err'); return; }
    if (file.size > 15 * 1024 * 1024) { msg(box, _t('asj.ph.big'), 'err'); return; }
    msg(box, _t('asj.ph.prep'), 'info');
    var url = URL.createObjectURL(file), im = new Image();
    im.onload = function () {
      try {
        if (!D) return;
        var w = im.naturalWidth, hh = im.naturalHeight;
        if (w < 64 || hh < 64) { msg(box, _t('asj.ph.small'), 'err'); return; }
        var s = Math.min(w, hh), sx = (w - s) / 2, sy = Math.max(0, (hh - s) * 0.35), out = Math.min(512, s);
        var c = document.createElement('canvas'); c.width = out; c.height = out;
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, out, out); x.imageSmoothingQuality = 'high';
        x.drawImage(im, sx, sy, s, s, 0, 0, out, out);
        var q = 0.88, d = c.toDataURL('image/jpeg', q);
        while (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX && q > 0.4) { q -= 0.08; d = c.toDataURL('image/jpeg', q); }
        if (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX) { msg(box, _t('asj.ph.comp'), 'err'); return; }
        D.photo = d; D.photoUrl = d; D.portrait = 'custom'; D.remove_photo = false;
        msg(box, isCho() ? _t('asj.ph.ok') : _t('asj.ph.okSave'), 'ok'); redraw();
      } catch (e) { msg(box, _t('asj.ph.read'), 'err'); }
      finally { URL.revokeObjectURL(url); }
    };
    im.onerror = function () { URL.revokeObjectURL(url); msg(box, _t('asj.ph.read2'), 'err'); };
    im.src = url;
  }
  /** Hörprobe: Text in der gewählten Sprache (vom Server: {preview}, sonst Browser). Erneutes Antippen desselben Knopfs stoppt. */
  function previewText(o) {
    var sie = o.address === 'sie', f = o.gender !== 'm';
    var nm = o.name || (f ? (sie ? 'Ihre Assistentin' : 'deine Assistentin') : (sie ? 'Ihr Assistent' : 'dein Assistent'));
    var L = LANGS.filter(function (l) { return l.code === o.lang; })[0];
    var tpl = L && L.preview ? L.preview[sie ? 'sie' : 'du'][f ? 'f' : 'm'] : (o.lang === 'de' ? 'Hallo, ich bin {name}. So klinge ich – passt das für ' + (sie ? 'Sie' : 'dich') + '?' : 'Hello, I\'m {name}. This is how I sound – does that suit you?');
    return { text: tpl.split('{name}').join(nm), name: nm };
  }
  function playPreview(o, pvId) {
    unlockAudio();
    if (pvId && SPK.pv === pvId && speaking()) { stopSpeaking(); return; }
    var t = previewText(o);
    var over = { gender: o.gender, tone: o.tone, rate: o.rate, pitch: o.pitch, preset: o.preset, id: o.id || null };
    var pv = { name: t.name, lang: o.lang, gender: o.gender, address: o.address, tone: o.tone, rate: o.rate, pitch: o.pitch, preset: o.preset, id: o.id || null };
    speakList([t.text], function () { SPK.pv = null; markPlaying(); }, over, { preview: pv, lang: o.lang });
    if (pvId && speaking()) { SPK.pv = pvId; markPlaying(); }
  }
  function draftVoice() {
    var nm = (nameEl().value || '').replace(/\s+/g, ' ').trim();
    return { name: nm, gender: D.gender, address: D.personality.address, lang: D.lang, tone: D.voice.tone, rate: D.voice.rate, pitch: D.voice.pitch, preset: D.voice.preset, id: D.voice.id || null };
  }
  function preview(pvId) { if (D) playPreview(draftVoice(), pvId || 'main'); }
  /** Entwurf speichern – aus der Auswahl („Mit … starten“) oder der Seitenleiste („Speichern“). */
  function saveDraft() {
    if (!D) return;
    var cho = isCho(), box = msgEl(), inp = nameEl();
    var name = (inp.value || '').replace(/\s+/g, ' ').trim();
    var ok = false; try { ok = new RegExp('^\\p{L}[\\p{L}\\p{M} \\-]*$', 'u').test(name); } catch (e) { ok = /^[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ \-]*$/.test(name); }
    if (!name || name.length > 30 || !ok) { msg(box, _t('asj.nameRule'), 'err'); inp.focus(); return; }
    var body = { name: name, gender: D.gender, voice: { tone: D.voice.tone, rate: D.voice.rate, pitch: D.voice.pitch, preset: D.voice.preset }, personality: D.personality, lang: D.lang };
    if (D.voice.id && catEntry(D.voice.id)) body.voice.id = D.voice.id;   // Katalogstimme (Server prüft Sprache + Geschlecht)
    if (D.photo) body.photo = D.photo; else body.portrait = D.portrait;
    if (D.remove_photo && !D.photo) body.remove_photo = 1;
    var btn = g(cho ? 'asCStart' : 'asFSave'); btn.disabled = true; msg(box, _t('asj.saving'), 'info');
    unlockAudio();
    var onlyGreeting = HIST.length === 1 && HIST[0].greeting;
    var changedIntro = PROF.name !== name || PROF.gender !== D.gender || PROF.personality.address !== D.personality.address || PROF.personality.mood !== D.personality.mood || PROF.lang !== D.lang;
    api('/assistant/profile', { method: 'POST', body: JSON.stringify(body) }).then(function (j) {
      takeProfile(j);
      if (cho) hideChooser(); else closeDrawer();
      applyProfile();
      if (cho) { try { window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }); } catch (e) { window.scrollTo(0, 0); } }
      note(h(cho ? TS('asj.here', { n: PROF.name }) : _t('asj.ready',{n:PROF.name})), 'ok');
      /* Nur die Begrüßung im Verlauf? Dann neu begrüßen – mit neuem Namen, neuer Anrede (kostenlos, ohne KI). */
      if (onlyGreeting && changedIntro) return api('/assistant/reset', { method: 'POST', body: '{}' }).then(function (st) {
        HIST = histFrom(st);
        renderHistory(); applyState(st);
        if (voiceOn && HIST.length) speakMsg(HIST[HIST.length - 1]);
      });
      renderHistory();
    }).catch(function (e) {
      var d = (e && e.data) || {};
      msg(box, h((e && e.message) || _t('c.saveFail')), 'err');
    }).then(function () { btn.disabled = false; });
  }
  function saveDrawer() { saveDraft(); }

  /* ======================================================================= Ereignisse */
  function bind() {
    if (bound) return; bound = true;
    pipLib();
    var form = g('asForm'), input = g('asInput');
    g(PANEL).addEventListener('click', function (e) {   // „Echte Stimme laden“ – für den offenen Entwurf oder das gespeicherte Profil
      if (!e.target.closest('[data-pip]')) return;
      unlockAudio();
      pipLoad(D ? pipOf(D.gender, D.voice.preset, D.lang, D.voice.id) : pipOf(null, null, PROF && PROF.lang, PROF && PROF.voice && PROF.voice.id), true);
    });
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
        if (only.length === 1 && HIST.length <= 2) speakMsg(only[0]);   // erster Besuch: Begrüßung vorlesen
      } else stopSpeaking();
      voiceBtn(); voiceInfo();
    });
    g('asCustomize').addEventListener('click', openDrawer);
    g('asTones').addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b) quickTone(b.dataset.v); });
    /* Auswahl beim ersten Besuch */
    g('asCGender').addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && isCho()) setGender(b.dataset.v); });
    g('asCPortraits').addEventListener('click', function (e) {
      if (!isCho()) return;
      if (e.target.closest('.up')) { g('asFFile').value = ''; g('asFFile').click(); return; }
      var b = e.target.closest('button[data-p]'); if (b) pickPortrait(b.dataset.p);
    });
    g('asCTones').addEventListener('click', function (e) {
      if (!isCho()) return;
      var pl = e.target.closest('button[data-play]');
      if (pl) { var id = pl.dataset.play; if (SPK.pv === id && speaking()) { stopSpeaking(); return; } if (pickPreset(id)) { drawChooser(); preview(id); } return; }
      var b = e.target.closest('button[data-v]'); if (b && pickPreset(b.dataset.v)) { stopSpeaking(); drawChooser(); }
    });
    g('asChoPlay').addEventListener('click', function () { if (isCho()) preview('main'); });
    g('asCName').addEventListener('input', function () {
      if (!isCho()) return; D.name = g('asCName').value.replace(/\s+/g, ' ').trim(); D.autoName = false;
      var nm = draftName(); g('asCStartName').textContent = nm; g('asChoName').textContent = nm; g('asCStart').setAttribute('aria-label', _t('asj.startL',{n:nm}));
    });
    g('asCName').addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); saveDraft(); } });
    ['asCAddress', 'asCMood'].forEach(function (id) {
      g(id).addEventListener('click', function (e) {
        var b = e.target.closest('button[data-v]'); if (!b || !isCho()) return;
        if (id === 'asCAddress') D.personality.address = b.dataset.v; else D.personality.mood = b.dataset.v;
        drawChooser();
      });
    });
    g('asCStart').addEventListener('click', saveDraft);
    g('asReset').addEventListener('click', function () {
      if (busy) return;
      if (!window.confirm(TS('asj.resetQ', { n: PROF ? PROF.name : _t('asj.asstM') }))) return;
      stopSpeaking(); abortListen();
      api('/assistant/reset', { method: 'POST', body: '{}' }).then(function (st) {
        HIST = histFrom(st);
        renderHistory(); applyState(st);
      }).catch(function (e) { note(h(e.message), 'err'); });
    });
    g('asMsgs').addEventListener('click', function (e) {
      var t = e.target.closest('.as-go[data-tab]');
      if (t) { e.preventDefault(); stopSpeaking(); go(t.dataset.tab); return; }
      var p = e.target.closest('.as-play');
      if (p) { unlockAudio(); var m = HIST[Number(p.dataset.i)]; if (m) { if (speaking()) stopSpeaking(); else speakMsg(m); } }
    });
    g('asSugg').addEventListener('click', function (e) { var b = e.target.closest('button[data-s]'); if (b) send(b.dataset.s); });
    var cl = g('asCheck');
    cl.addEventListener('click', function (e) { var li = e.target.closest('li[data-p]'); if (li && li.dataset.p) send(li.dataset.p); });
    cl.addEventListener('keydown', function (e) { if (e.key !== 'Enter' && e.key !== ' ') return; var li = e.target.closest('li[data-p]'); if (li && li.dataset.p) { e.preventDefault(); send(li.dataset.p); } });
    g('asMem').addEventListener('click', function (e) {
      var b = e.target.closest('.as-x[data-id]'); if (!b) return;
      var li = b.closest('li'); if (li) li.style.opacity = '.4'; b.disabled = true;
      api('/assistant/forget', { method: 'POST', body: JSON.stringify({ id: Number(b.dataset.id) }) }).then(function (j) { applyState(j); note(_t('asj.forgot'), 'ok'); })
        .catch(function (err) { if (li) li.style.opacity = ''; b.disabled = false; note(h(err.message), 'err'); });
    });
    /* Drawer */
    g('asScrim').addEventListener('click', closeDrawer);
    g('asDrClose').addEventListener('click', closeDrawer);
    g('asFCancel').addEventListener('click', closeDrawer);
    g('asFSave').addEventListener('click', saveDrawer);
    g('asFPreview').addEventListener('click', function () { preview('main'); });
    g('asFName').addEventListener('input', function () { if (!D || isCho()) return; D.name = g('asFName').value.trim(); D.autoName = false; g('asDrTitle').textContent = (D.name || _t('asj.asstM')) + ' · ' + _t('asj.pv'); });
    g('asFGender').addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && D && !isCho()) setGender(b.dataset.v); });
    g('asFPortraits').addEventListener('click', function (e) {
      if (!D || isCho()) return;
      if (e.target.closest('#asFUp')) { g('asFFile').value = ''; g('asFFile').click(); return; }
      var b = e.target.closest('button[data-p]'); if (b) pickPortrait(b.dataset.p);
    });
    g('asFFile').addEventListener('change', function () { if (D) handleFile(this.files && this.files[0]); });
    g('asFPhotoRm').addEventListener('click', function () {
      if (!D) return; D.photo = null; D.photoUrl = null; D.remove_photo = true;
      if (D.portrait === 'custom') { var f = PORTRAITS.filter(function (p) { return p.gender === D.gender; })[0]; D.portrait = f ? f.id : ''; }
      drawDrawer();
    });
    g('asFVoices').addEventListener('click', function (e) {
      if (!D || isCho()) return;
      var pl = e.target.closest('button[data-play]');
      if (pl) { var id = pl.dataset.play; if (SPK.pv === id && speaking()) { stopSpeaking(); return; } if (pickPreset(id)) { drawDrawer(); preview(id); } return; }
      var b = e.target.closest('button[data-v]'); if (b && pickPreset(b.dataset.v)) { stopSpeaking(); drawDrawer(); }
    });
    var segBind = function (id, fn) { g(id).addEventListener('click', function (e) { var b = e.target.closest('button[data-v]'); if (b && D && !isCho()) { fn(b.dataset.v); drawDrawer(); } }); };
    segBind('asFTone', function (v) { D.voice.tone = v; D.voice.preset = toneToPreset(D.gender, v); D.voice.id = catFit(null, D.lang, D.gender, D.voice.preset) || null; });
    segBind('asFAddress', function (v) { D.personality.address = v; });
    segBind('asFMood', function (v) { D.personality.mood = v; });
    segBind('asFHumor', function (v) { D.personality.humor = Number(v); });
    segBind('asFDetail', function (v) { D.personality.detail = v; });
    g('asFRate').addEventListener('input', function () { if (D) { D.voice.rate = Number(this.value); g('asFRateO').textContent = fx(D.voice.rate); } });
    g('asFPitch').addEventListener('input', function () { if (D) { D.voice.pitch = Number(this.value); g('asFPitchO').textContent = fx(D.voice.pitch); } });
    g('asFLang').addEventListener('change', function () { if (D) { D.lang = this.value; draftFit(); drawDrawer(); } });
    g('asMicLang').addEventListener('change', function () { micLangPick = this.value; LS.set('ch_as_miclang', micLangPick); micLangUi(); if (REC.on) abortListen(); });
    /* Browserstimmen laden asynchron */
    var onVoices = function () { voicesReady(); voiceInfo(); };
    if (window.speechSynthesis) { try { speechSynthesis.addEventListener('voiceschanged', onVoices); } catch (e) { speechSynthesis.onvoiceschanged = onVoices; } }
    if (voicesNow().length) voicesReady(); else setTimeout(voicesReady, 1000);   // keine Meldung → nach 1 s ohne Liste weiter (Standardstimme)
    /* Panel verlassen → Ausgabe und Zuhören beenden (kein Geister-Mikrofon im Hintergrund) */
    var panel = g(PANEL);
    if (panel && window.MutationObserver) new MutationObserver(function () { if (!visible()) { stopSpeaking(); abortListen(); if (D && !isCho()) closeDrawer(); } }).observe(panel, { attributes: true, attributeFilter: ['class'] });
    document.addEventListener('visibilitychange', function () { if (document.hidden) abortListen(); });
  }

  
  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  STUDIO_PANELS['assistant'] = {
    init: function () {
      bind(); setState('idle'); voiceBtn();
      g('asMsgs').innerHTML = '<div class="as-m sys"><div class="as-b">' + _t('asj.moment') + '</div></div>';
      load(true).catch(function (e) {
        g('asMsgs').innerHTML = '';
        sysMsg(h((e && e.message) || _t('asj.loadFail')) + ' – ' + _t('asj.reload'), true);
      });
    },
    show: function () {
      if (Date.now() - lastLoad > 8000 && !busy) load(false).catch(function () { /* Anzeige bleibt */ });   // Checkliste nach Änderungen in anderen Bereichen auffrischen
    }
  };
})();
