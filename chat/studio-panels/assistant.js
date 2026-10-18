/* ChatHelp Studio — Panel „✦ Assistent“: persönliche Assistentin bzw. persönlicher Assistent des Inhabers (assistant-lib.php).
   Links das Porträt (atmet, goldener Ring + Stimmbalken beim Sprechen, Status „hört zu…/denkt…/spricht…“), in der Mitte das Gespräch
   mit Aktions-Chips (was geändert wurde + Link zum Bereich), rechts Checkliste mit Fortschrittsring und „Was ich über dich weiß“.
   Stimme: Server-Stimme über /v1/assistant/speak (speech-lib), sonst Browserstimmen (speechSynthesis) – je Voreinstellung eine andere Stimme
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
  var STATUS = { idle: 'bereit', listening: 'hört zu…', thinking: 'denkt…', speaking: 'spricht…' };
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
  var NO_VOICE_TXT = 'Kein Ton möglich – dieser Browser hat keine nutzbare Stimme. Natürliche Stimmen brauchen einen Sprachdienst-Schlüssel, den der Betreiber hinterlegt.';
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
      if (b.classList.contains('pl') || b.classList.contains('as-cho-play')) b.textContent = on ? '■ Stopp' : '▶ Hörprobe';
      else if (b.id === 'asFPreview') b.textContent = on ? '■ Hörprobe stoppen' : '▶ Hörprobe mit diesen Einstellungen';
      var base = b.getAttribute('data-label') || 'Hörprobe';
      b.setAttribute('aria-label', on ? base + ' stoppen' : base);
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
  function joinNames(a) { a = a.slice(); if (a.length < 2) return a.join(''); var last = a.pop(); return a.join(', ') + ' und ' + last; }
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
    if ((src.mid || src.preview) && serverCan(lng)) speakServer(list, gen, done, { mid: src.mid, preview: src.preview, lang: lng }, over);
    else speakBrowser(list, gen, done, over, tagOf(lng));
  }
  function speakMsg(m, onend) { if (m) speakList(m.speech && m.speech.length ? m.speech : sentences(m.content), onend, null, { mid: m.speech && m.speech.length ? m.id : 0, lang: m.lang }); }
  /** Ehrliche Statuszeile: KI-Stimme nur, wenn der Server sie wirklich liefert – sonst sagen, dass es die Systemstimme ist und warum. */
  function voiceStatus(lng) {
    var L = LANGS.filter(function (l) { return l.code === normLang(lng); })[0];
    if (serverCan(lng)) return { k: 'ok', t: 'Natürliche KI-Stimme aktiv' };
    if (!window.speechSynthesis || SPK.trouble) return { k: 'warn', t: NO_VOICE_TXT };
    if (TTS && SPK.serverOk && !serverLangOk(lng)) return { k: 'info', t: 'Systemstimme des Browsers – die natürliche KI-Stimme spricht ' + (L ? L.name : 'diese Sprache') + ' noch nicht' };
    if (TTS && SPK.why === 'balance') return { k: 'info', t: 'Systemstimme des Browsers – die natürliche KI-Stimme braucht Guthaben oder einen aktiven Agenten' };
    if (TTS && SPK.why === 'limit') return { k: 'info', t: 'Systemstimme des Browsers – das Tageslimit der KI-Stimme ist erreicht' };
    return { k: 'info', t: 'Systemstimme des Browsers – natürliche Stimmen schaltet der Betreiber frei' };
  }
  /** Wird gerade die Browserstimme benutzt (kein Sprachdienst, aber Ton möglich)? Dann zeigen wir ehrlich, welche. */
  function browserUsed(lng) { return voiceStatus(lng).k === 'info'; }
  /** Tooltip-Zusatz „ · Browserstimme: Katja“ für Chips und Hörprobe-Knöpfe (leer, wenn die Server-Stimme spricht). */
  function voiceTip(gender, preset, lng) {
    if (!browserUsed(lng)) return '';
    var pk = pickVoiceEx(gender, preset, tagOf(lng));
    return pk.v ? ' · Browserstimme: ' + voiceLabel(pk.v) + (pk.shared ? ' (geteilt)' : '') : '';
  }
  /** Zeile „Browserstimme: <Name>“ für die aktive Voreinstellung + ein Satz, wenn sich Voreinstellungen eine Stimme teilen müssen. */
  function voiceLineHtml(gender, preset, lng) {
    if (!browserUsed(lng)) return '';
    var tag = tagOf(lng), m = voiceMap(gender, tag), pk = pickVoiceEx(gender, preset, tag);
    if (!pk.v) return '';
    var L = LANGS.filter(function (l) { return l.code === normLang(lng); })[0];
    var s = '<span class="as-vname">Browserstimme: <b>' + h(voiceLabel(pk.v)) + '</b>' +
      (m.level === 'any' ? ' <span class="as-vlang">(' + h(pk.v.lang || '') + ' – keine Stimme für ' + h(L ? L.name : 'diese Sprache') + ' installiert)</span>' : '') + '</span>';
    if (m.shares.length) {
      s += '<span class="as-vshare">' + m.shares.map(function (x) {
        return h(joinNames(x.ids.map(function (id) { return personaOf(presetOf(id, gender)); }))) + ' teilen sich die Browserstimme ' + h(voiceLabel(x.v));
      }).join('; ') + ' – nur Tempo und Tonhöhe unterscheiden sich.</span>';
    }
    return s;
  }
  /** Hinweis in Auswahl und Seitenleiste: mit KI-Stimme hat jede Assistenz eine eigene, echte Stimme. */
  function kiHint(gender) {
    var names = VOICES.filter(function (v) { return v.gender === gender; }).map(personaOf);
    return 'Mit aktivierter KI-Stimme klingt jede Assistenz wie eine echte Person' + (names.length > 1 ? ' – ' + joinNames(names) + ' haben dann eigene Stimmen.' : '.');
  }
  function paintStatus(el, gender, preset, lng, nameEl, hintEl) {
    var st = voiceStatus(lng), used = st.k === 'info', pk = used ? pickVoiceEx(gender, preset, tagOf(lng)) : null;
    if (el) {
      el.textContent = st.t; el.setAttribute('data-k', st.k);
      if (pk && pk.v) el.title = 'Browserstimme: ' + voiceLabel(pk.v); else el.removeAttribute('title');
    }
    if (nameEl) nameEl.innerHTML = used ? voiceLineHtml(gender, preset, lng) : '';
    if (hintEl) { hintEl.textContent = used ? kiHint(gender) : ''; hintEl.classList.toggle('hidden', !used); }
  }
  /** Tooltips der Stimm-Chips und Hörprobe-Knöpfe nachziehen (Stimmenliste kommt asynchron). */
  function voiceTips() {
    if (PROF) document.querySelectorAll('#asTones button[data-v]').forEach(function (b) {
      var v = presetOf(b.dataset.v, PROF.gender); if (v) b.title = v.label + (v.desc ? ' – ' + v.desc : '') + voiceTip(PROF.gender, v.id, PROF.lang);
    });
    if (D) {
      document.querySelectorAll('#' + (isCho() ? 'asCTones' : 'asFVoices') + ' .pl[data-play]').forEach(function (b) {
        var v = presetOf(b.dataset.play, D.gender); if (v) b.title = 'Hörprobe: ' + v.label + voiceTip(D.gender, v.id, D.lang);
      });
      var main = g(isCho() ? 'asChoPlay' : 'asFPreview'), pre = presetOf(D.voice.preset, D.gender);
      if (main) main.title = 'Hörprobe' + (pre ? ': ' + pre.label : '') + voiceTip(D.gender, D.voice.preset, D.lang);
    }
  }
  function voiceInfo() {
    if (PROF) paintStatus(g('asVoiceInfo'), PROF.gender, PROF.voice && PROF.voice.preset, PROF.lang, g('asVoiceName'), null);
    if (D) { var cho = D.mode === 'cho'; paintStatus(g(cho ? 'asCVoiceStat' : 'asFPrevInfo'), D.gender, D.voice.preset, D.lang, g(cho ? 'asCVoiceName' : 'asFVoiceName'), g(cho ? 'asCVoiceHint' : 'asFVoiceHint')); }
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
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { micUsed = false; note('Das Mikrofon ist blockiert – bitte in den Browser-Einstellungen für diese Seite erlauben.', 'err'); }
      else if (e.error === 'network') note('Die Spracherkennung braucht eine Internetverbindung.', 'err');
      else if (e.error === 'language-not-supported') note('Diese Sprache kann der Browser nicht erkennen – bitte oben eine andere Sprache für die Spracheingabe wählen oder tippen.', 'err');
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
    g('asHeadInfo').textContent = !TOOLS ? T('Gerade kann ich nur beraten – Einstellungen trägst du im jeweiligen Bereich selbst ein.', 'Gerade kann ich nur beraten – Einstellungen tragen Sie im jeweiligen Bereich selbst ein.')
      : (sie ? 'Ich ändere nichts, ohne Sie vorher zu fragen.' : 'Ich ändere nichts, ohne dich vorher zu fragen.');
    g('asHeadInfo').classList.toggle('as-warn', !TOOLS);
    g('asTip').innerHTML = T('Tipp: Unter <b>Person &amp; Stimme ändern</b> legst du Aussehen, Stimme, Sprache und Persönlichkeit fest – Frau oder Mann, du oder Sie, herzlich oder sachlich.',
      'Tipp: Unter <b>Person &amp; Stimme ändern</b> legen Sie Aussehen, Stimme, Sprache und Persönlichkeit fest – Frau oder Mann, du oder Sie, herzlich oder sachlich.');
    g('asTonesL').innerHTML = 'Stimme<span>' + T('antippen = sofort wechseln', 'antippen = sofort wechseln') + '</span>';
    renderToneChips();
    micLangUi();
    g('asMemTitle').textContent = sie ? 'Was ich über Sie weiß' : 'Was ich über dich weiß';
    g('asMemEmpty').textContent = sie ? 'Noch nichts – erzählen Sie mir von Ihrem Betrieb, ich merke mir das Wichtigste.' : 'Noch nichts – erzähl mir von deinem Betrieb, ich merke mir das Wichtigste.';
    g('asInput').placeholder = sie ? 'Sagen Sie mir, was ich erledigen soll …' : 'Erzähl mir, was ich erledigen soll …';
    g('asTip').classList.toggle('hidden', !!PROF.saved);
    var sec = document.querySelector('#' + PANEL + ' .as-chat'); if (sec) sec.setAttribute('aria-label', 'Gespräch mit ' + PROF.name + ' (' + (f ? 'Assistentin' : 'Assistent') + ')');
    document.querySelectorAll('#' + PANEL + ' .as-av').forEach(function (a) { a.src = url; });
    voiceBtn(); voiceInfo();
  }
  /** Stimm-Chips auf der Karte: alle Voreinstellungen des Geschlechts, aktive markiert – Antippen wechselt sofort (gespeichert) und spielt eine Hörprobe. */
  function renderToneChips() {
    var box = g('asTones'); if (!box || !PROF) return;
    box.innerHTML = VOICES.filter(function (v) { return v.gender === PROF.gender; }).map(function (v) {
      var on = PROF.voice && PROF.voice.preset === v.id;
      return '<button type="button" data-v="' + h(v.id) + '" data-play="chip:' + h(v.id) + '" data-label="Stimme ' + h(v.label) + (on ? ' (aktiv) – Hörprobe' : ' wählen und anhören') + '" aria-pressed="' + (on ? 'true' : 'false') + '" title="' + h(v.label + (v.desc ? ' – ' + v.desc : '') + voiceTip(PROF.gender, v.id, PROF.lang)) + '" aria-label="Stimme ' + h(v.label) + '">' + h(shortLabel(v)) + '</button>';
    }).join('');
    markPlaying();
  }
  /** Profilwerte als Hörprobe-Beschreibung. */
  function profVoice(p, preset) {
    return { name: p.name, gender: p.gender, address: p.personality.address, lang: p.lang || 'de', tone: toneOf(preset) || p.voice.tone, rate: Number(p.voice.rate) || 1, pitch: Number(p.voice.pitch) || 1, preset: preset };
  }
  var toneBusy = 0;
  function quickTone(id) {
    if (!PROF || !id) return;
    unlockAudio();
    var pre = presetOf(id, PROF.gender); if (!pre || pre.id !== id) return;
    if (PROF.voice.preset === id) { playPreview(profVoice(PROF, id), 'chip:' + id); return; }   // aktive Stimme: nur anhören
    var old = { preset: PROF.voice.preset, tone: PROF.voice.tone }, tone = toneOf(id) || PROF.voice.tone, my = ++toneBusy;
    PROF.voice.preset = id; PROF.voice.tone = tone; renderToneChips(); voiceInfo();
    playPreview(profVoice(PROF, id), 'chip:' + id);
    api('/assistant/profile', { method: 'POST', body: JSON.stringify({ voice: { preset: id, tone: tone } }) }).then(function (j) {
      if (my !== toneBusy) return;
      takeProfile(j); renderToneChips(); voiceInfo();
      note('Stimme gewechselt: ' + h(pre.label) + ' – gespeichert.', 'ok');
    }).catch(function (e) {
      if (my !== toneBusy) return;
      PROF.voice.preset = old.preset; PROF.voice.tone = old.tone; renderToneChips(); voiceInfo();
      note(h(((e && e.data && e.data.message) || (e && e.message) || 'Die Stimme konnte nicht gespeichert werden.')), 'err');
    });
  }
  /** Auswahl „Sprache der Spracheingabe“ (Auto = Sprache des Gesprächs) und Sprachliste im Anpassen-Dialog aus derselben Tabelle. */
  function langOptions(sel, withAuto) {
    if (!sel) return;
    var cur = sel.value;
    var pre = withAuto ? '🎙 ' : '';
    var cl = (LANGS.filter(function (l) { return l.code === convLang(); })[0]) || LANGS[0];
    sel.innerHTML = (withAuto ? '<option value="auto">' + pre + 'Auto</option>' : '') +
      LANGS.map(function (l) { return '<option value="' + h(l.code) + '">' + (withAuto ? pre + h(l.code.toUpperCase()) + ' · ' + h(l.native) : h(l.native) + (l.native !== l.name ? ' · ' + h(l.name) : '')) + '</option>'; }).join('');
    if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
  }
  function micLangUi() {
    var sel = g('asMicLang'); if (!sel) return;
    langOptions(sel, true);
    sel.value = micLangPick !== 'auto' && LANG_TAG[micLangPick] ? micLangPick : 'auto';
    var cl = ((LANGS.filter(function (l) { return l.code === convLang(); })[0]) || LANGS[0]);
    sel.title = 'Sprache der Spracheingabe – Auto: ' + cl.name + ' (Sprache des Gesprächs)';
    sel.setAttribute('aria-label', sel.title);
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
        sysMsg(h(d.message || T('Dein', 'Ihr') + ' Guthaben reicht nicht für eine Antwort.') + ' <a href="/#preise" target="_blank" rel="noopener">Tokens kaufen</a>', true);
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
      HIST = histFrom(st);
      applyProfile(); renderHistory(); applyState(st); micLangUi();
      if (r[1]) { if (PROF && PROF.chosen === false) showChooser(); else if (D && D.mode === 'cho') hideChooser(); }
      if (!TOOLS) sysMsg(h(T('Hinweis: Das automatische Einrichten ist gerade nicht verfügbar. Ich kann dich beraten und Texte vorschlagen – eintragen musst du sie im jeweiligen Bereich selbst.',
        'Hinweis: Das automatische Einrichten ist gerade nicht verfügbar. Ich kann Sie beraten und Texte vorschlagen – eintragen müssen Sie sie im jeweiligen Bereich selbst.')));
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
  }

  /* ======================================================================= Entwurf (Auswahl beim ersten Besuch + „Person & Stimme ändern“) */
  /* D = ungespeicherter Entwurf; D.mode = 'cho' (Auswahl im Panel) oder 'drawer' (Seitenleiste). Beide nutzen dieselben Funktionen. */
  var D = null, lastFocus = null;
  function draftFrom(p) {
    return { name: p.name, gender: p.gender, portrait: p.portrait, photo: null, remove_photo: false, photoUrl: p.portrait === 'custom' ? p.portrait_url : (p.photo_url || null),
      voice: { tone: p.voice.tone, rate: Number(p.voice.rate) || 1, pitch: Number(p.voice.pitch) || 1, preset: p.voice.preset },
      personality: { address: p.personality.address, mood: p.personality.mood, humor: Number(p.personality.humor), detail: p.personality.detail }, lang: p.lang || 'de', autoName: true, mode: 'drawer' };
  }
  function isCho() { return !!D && D.mode === 'cho'; }
  function nameEl() { return g(isCho() ? 'asCName' : 'asFName'); }
  function msgEl() { return g(isCho() ? 'asCMsg' : 'asFMsg'); }
  function redraw() { if (!D) return; if (isCho()) drawChooser(); else drawDrawer(); }
  function portraitById(id) { var x = null; PORTRAITS.forEach(function (p) { if (p.id === id) x = p; }); return x; }
  function draftImg() { if (D.portrait === 'custom' && D.photoUrl) return imgUrl(D.photoUrl); var p = portraitById(D.portrait); return p ? imgUrl(p.url) : avatar(); }
  function seg(id, val) { document.querySelectorAll('#' + id + ' button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.v) === String(val) ? 'true' : 'false'); }); }
  function fx(n) { return (Math.round(Number(n) * 100) / 100).toFixed(2).replace('.', ',') + '×'; }
  function draftRole() { var f = D.gender !== 'm', sie = D.personality.address === 'sie'; return f ? (sie ? 'Ihre persönliche Assistentin' : 'Deine persönliche Assistentin') : (sie ? 'Ihr persönlicher Assistent' : 'Dein persönlicher Assistent'); }
  function draftName() { return (D.name || '').replace(/\s+/g, ' ').trim() || (D.gender !== 'm' ? 'Assistentin' : 'Assistent'); }
  /** Porträt-Kacheln (eigenes Foto zuerst, Hochladen zuletzt) – gleiche Kacheln in Auswahl und Seitenleiste. */
  function portraitTiles(upId) {
    var tiles = PORTRAITS.filter(function (p) { return p.gender === D.gender; }).map(function (p) {
      return '<button type="button" data-p="' + h(p.id) + '" aria-pressed="' + (D.portrait === p.id ? 'true' : 'false') + '" aria-label="Porträt ' + h(p.label) + '"><img src="' + h(imgUrl(p.url)) + '" alt="" loading="lazy"><span class="lb">' + h(p.label) + '</span></button>';
    });
    if (D.photoUrl) tiles.unshift('<button type="button" data-p="custom" aria-pressed="' + (D.portrait === 'custom' ? 'true' : 'false') + '" aria-label="Eigenes Foto"><img src="' + h(imgUrl(D.photoUrl)) + '" alt=""><span class="lb">Eigenes Foto</span></button>');
    tiles.push('<button type="button" class="up"' + (upId ? ' id="' + upId + '"' : '') + ' aria-label="Eigenes Foto hochladen"><i aria-hidden="true">＋</i>Eigenes Foto</button>');
    return tiles.join('');
  }
  /** Stimmkarten: Auswahl-Knopf (data-v, aria-pressed) + eigener ▶-Hörprobe-Knopf (data-play) je Voreinstellung. */
  function toneCards() {
    return VOICES.filter(function (v) { return v.gender === D.gender; }).map(function (v) {
      var on = D.voice.preset === v.id;
      return '<div class="as-tcard' + (on ? ' on' : '') + '"><button type="button" class="sel" data-v="' + h(v.id) + '" aria-pressed="' + (on ? 'true' : 'false') + '"><b>' + h(v.label) + '</b>' + h(v.desc || '') + '</button>' +
        '<button type="button" class="pl" data-play="' + h(v.id) + '" data-label="Hörprobe: ' + h(v.label) + '" aria-label="Hörprobe: ' + h(v.label) + '" title="' + h('Hörprobe: ' + v.label + voiceTip(D.gender, v.id, D.lang)) + '">▶ Hörprobe</button></div>';
    }).join('');
  }
  function drawDrawer() {
    var f = D.gender !== 'm';
    g('asDrImg').src = draftImg();
    g('asDrTitle').textContent = (D.name || (f ? 'Assistentin' : 'Assistent')) + ' · Person & Stimme';
    g('asDrSub').textContent = draftRole();
    seg('asFGender', D.gender); seg('asFTone', D.voice.tone); seg('asFAddress', D.personality.address); seg('asFMood', D.personality.mood); seg('asFHumor', D.personality.humor); seg('asFDetail', D.personality.detail);
    g('asFPortraits').innerHTML = portraitTiles('asFUp');
    g('asFPhotoRm').classList.toggle('hidden', !D.photoUrl);
    g('asFVoices').innerHTML = toneCards();
    g('asFRate').value = String(D.voice.rate); g('asFRateO').textContent = fx(D.voice.rate);
    g('asFPitch').value = String(D.voice.pitch); g('asFPitchO').textContent = fx(D.voice.pitch);
    g('asFLang').value = D.lang;
    var sie = D.personality.address === 'sie';
    g('asFGenderL').textContent = sie ? 'Wer soll Sie begleiten?' : 'Wer soll dich begleiten?';
    g('asFRights').textContent = sie ? 'Nur Fotos verwenden, für die Sie die Rechte haben.' : 'Nur Fotos verwenden, für die du die Rechte hast.';
    g('asFLangHint').textContent = (sie ? 'Schreiben oder sprechen Sie' : 'Schreibst oder sprichst du') + ' in einer anderen Sprache, antwortet ' + (f ? 'die Assistentin' : 'der Assistent') + ' automatisch darin – in allen ' + LANGS.length + ' Sprachen.';
    voiceInfo(); markPlaying();
  }
  function drawChooser() {
    var f = D.gender !== 'm', sie = D.personality.address === 'sie', nm = draftName(), pre = presetOf(D.voice.preset, D.gender);
    var img = g('asChoImg'), u = draftImg(); if (img.getAttribute('src') !== u) img.src = u;
    img.alt = 'Porträt von ' + nm;
    g('asChoName').textContent = nm; g('asChoRole').textContent = draftRole();
    g('asChoWho').textContent = f ? 'sie' : 'er';
    g('asChoVoice').textContent = pre ? pre.label : '';
    g('asChoTitle').textContent = sie ? 'Wer soll Sie begleiten?' : 'Wer soll dich begleiten?';
    g('asChoLead').textContent = sie ? 'Wählen Sie Person, Stimme und Art – so, wie es zu Ihnen passt. Alles lässt sich später jederzeit ändern.' : 'Wähle Person, Stimme und Art – so, wie es zu dir passt. Alles lässt sich später jederzeit ändern.';
    g('asCHint').textContent = (sie ? 'Sie können' : 'Du kannst') + ' Person und Stimme jederzeit unter „Person & Stimme ändern“ wechseln.';
    seg('asCGender', D.gender); seg('asCAddress', D.personality.address); seg('asCMood', D.personality.mood);
    g('asCPortraits').innerHTML = portraitTiles('');
    g('asCTones').innerHTML = toneCards();
    g('asCStartName').textContent = nm;
    g('asCStart').setAttribute('aria-label', 'Mit ' + nm + ' starten – Auswahl speichern');
    voiceInfo(); markPlaying();
  }
  function showChooser() {
    if (!PROF) return;
    D = draftFrom(PROF); D.mode = 'cho';
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
    D = draftFrom(PROF); lastFocus = document.activeElement;
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
  function pickPreset(id) { var v = presetOf(id, D.gender); if (!v || v.id !== id) return false; D.voice.preset = id; D.voice.tone = toneOf(id) || D.voice.tone; return true; }
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
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { msg(box, 'Bitte ein Foto als JPEG, PNG oder WebP wählen.', 'err'); return; }
    if (file.size > 15 * 1024 * 1024) { msg(box, 'Das Foto ist zu groß (höchstens 15 MB vor dem Verkleinern).', 'err'); return; }
    msg(box, 'Foto wird vorbereitet …', 'info');
    var url = URL.createObjectURL(file), im = new Image();
    im.onload = function () {
      try {
        if (!D) return;
        var w = im.naturalWidth, hh = im.naturalHeight;
        if (w < 64 || hh < 64) { msg(box, 'Das Foto ist zu klein (mindestens 64 × 64 Pixel).', 'err'); return; }
        var s = Math.min(w, hh), sx = (w - s) / 2, sy = Math.max(0, (hh - s) * 0.35), out = Math.min(512, s);
        var c = document.createElement('canvas'); c.width = out; c.height = out;
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, out, out); x.imageSmoothingQuality = 'high';
        x.drawImage(im, sx, sy, s, s, 0, 0, out, out);
        var q = 0.88, d = c.toDataURL('image/jpeg', q);
        while (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX && q > 0.4) { q -= 0.08; d = c.toDataURL('image/jpeg', q); }
        if (Math.floor((d.length - d.indexOf(',') - 1) * 3 / 4) > PHOTO_MAX) { msg(box, 'Das Foto ließ sich nicht klein genug speichern – bitte ein anderes wählen.', 'err'); return; }
        D.photo = d; D.photoUrl = d; D.portrait = 'custom'; D.remove_photo = false;
        msg(box, isCho() ? 'Foto übernommen.' : 'Foto übernommen – mit „Speichern“ bestätigen.', 'ok'); redraw();
      } catch (e) { msg(box, 'Das Foto konnte nicht gelesen werden.', 'err'); }
      finally { URL.revokeObjectURL(url); }
    };
    im.onerror = function () { URL.revokeObjectURL(url); msg(box, 'Das Foto konnte nicht gelesen werden – bitte JPEG, PNG oder WebP verwenden.', 'err'); };
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
    var over = { gender: o.gender, tone: o.tone, rate: o.rate, pitch: o.pitch, preset: o.preset };
    var pv = { name: t.name, lang: o.lang, gender: o.gender, address: o.address, tone: o.tone, rate: o.rate, pitch: o.pitch, preset: o.preset };
    speakList([t.text], function () { SPK.pv = null; markPlaying(); }, over, { preview: pv, lang: o.lang });
    if (pvId && speaking()) { SPK.pv = pvId; markPlaying(); }
  }
  function draftVoice() {
    var nm = (nameEl().value || '').replace(/\s+/g, ' ').trim();
    return { name: nm, gender: D.gender, address: D.personality.address, lang: D.lang, tone: D.voice.tone, rate: D.voice.rate, pitch: D.voice.pitch, preset: D.voice.preset };
  }
  function preview(pvId) { if (D) playPreview(draftVoice(), pvId || 'main'); }
  /** Entwurf speichern – aus der Auswahl („Mit … starten“) oder der Seitenleiste („Speichern“). */
  function saveDraft() {
    if (!D) return;
    var cho = isCho(), box = msgEl(), inp = nameEl();
    var name = (inp.value || '').replace(/\s+/g, ' ').trim();
    var ok = false; try { ok = new RegExp('^\\p{L}[\\p{L}\\p{M} \\-]*$', 'u').test(name); } catch (e) { ok = /^[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ \-]*$/.test(name); }
    if (!name || name.length > 30 || !ok) { msg(box, 'Name: 1–30 Zeichen, nur Buchstaben, Leerzeichen und Bindestrich.', 'err'); inp.focus(); return; }
    var body = { name: name, gender: D.gender, voice: D.voice, personality: D.personality, lang: D.lang };
    if (D.photo) body.photo = D.photo; else body.portrait = D.portrait;
    if (D.remove_photo && !D.photo) body.remove_photo = 1;
    var btn = g(cho ? 'asCStart' : 'asFSave'); btn.disabled = true; msg(box, 'Wird gespeichert …', 'info');
    unlockAudio();
    var onlyGreeting = HIST.length === 1 && HIST[0].greeting;
    var changedIntro = PROF.name !== name || PROF.gender !== D.gender || PROF.personality.address !== D.personality.address || PROF.personality.mood !== D.personality.mood || PROF.lang !== D.lang;
    api('/assistant/profile', { method: 'POST', body: JSON.stringify(body) }).then(function (j) {
      takeProfile(j);
      if (cho) hideChooser(); else closeDrawer();
      applyProfile();
      if (cho) { try { window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }); } catch (e) { window.scrollTo(0, 0); } }
      note(cho ? h(PROF.name) + ' ist für ' + T('dich', 'Sie') + ' da – los geht’s.' : 'Gespeichert – ' + h(PROF.name) + ' ist bereit.', 'ok');
      /* Nur die Begrüßung im Verlauf? Dann neu begrüßen – mit neuem Namen, neuer Anrede (kostenlos, ohne KI). */
      if (onlyGreeting && changedIntro) return api('/assistant/reset', { method: 'POST', body: '{}' }).then(function (st) {
        HIST = histFrom(st);
        renderHistory(); applyState(st);
        if (voiceOn && HIST.length) speakMsg(HIST[HIST.length - 1]);
      });
      renderHistory();
    }).catch(function (e) {
      var d = (e && e.data) || {};
      msg(box, h(d.message || (e && e.message) || 'Speichern fehlgeschlagen.'), 'err');
    }).then(function () { btn.disabled = false; });
  }
  function saveDrawer() { saveDraft(); }

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
      var nm = draftName(); g('asCStartName').textContent = nm; g('asChoName').textContent = nm; g('asCStart').setAttribute('aria-label', 'Mit ' + nm + ' starten – Auswahl speichern');
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
      if (!window.confirm('Gespräch neu beginnen? Der Verlauf wird geleert – was ' + (PROF ? PROF.name : 'der Assistent') + ' über ' + (isSie() ? 'Sie' : 'dich') + ' weiß, bleibt erhalten.')) return;
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
      api('/assistant/forget', { method: 'POST', body: JSON.stringify({ id: Number(b.dataset.id) }) }).then(function (j) { applyState(j); note('Vergessen.', 'ok'); })
        .catch(function (err) { if (li) li.style.opacity = ''; b.disabled = false; note(h(err.message), 'err'); });
    });
    /* Drawer */
    g('asScrim').addEventListener('click', closeDrawer);
    g('asDrClose').addEventListener('click', closeDrawer);
    g('asFCancel').addEventListener('click', closeDrawer);
    g('asFSave').addEventListener('click', saveDrawer);
    g('asFPreview').addEventListener('click', function () { preview('main'); });
    g('asFName').addEventListener('input', function () { if (!D || isCho()) return; D.name = g('asFName').value.trim(); D.autoName = false; g('asDrTitle').textContent = (D.name || 'Assistent') + ' · Person & Stimme'; });
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
    segBind('asFTone', function (v) { D.voice.tone = v; D.voice.preset = toneToPreset(D.gender, v); });
    segBind('asFAddress', function (v) { D.personality.address = v; });
    segBind('asFMood', function (v) { D.personality.mood = v; });
    segBind('asFHumor', function (v) { D.personality.humor = Number(v); });
    segBind('asFDetail', function (v) { D.personality.detail = v; });
    g('asFRate').addEventListener('input', function () { if (D) { D.voice.rate = Number(this.value); g('asFRateO').textContent = fx(D.voice.rate); } });
    g('asFPitch').addEventListener('input', function () { if (D) { D.voice.pitch = Number(this.value); g('asFPitchO').textContent = fx(D.voice.pitch); } });
    g('asFLang').addEventListener('change', function () { if (D) { D.lang = this.value; drawDrawer(); } });
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
