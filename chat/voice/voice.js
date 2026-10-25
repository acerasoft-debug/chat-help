/* ChatHelp — echte Stimmen im Browser (Piper, Open Source, kein API-Schlüssel). Gemeinsam für Studio-Assistent und Startseiten-Demos.
   CHVoice.pick(lang, gender, preset[, who]) → Stimme der Voreinstellung bzw. mit who die eigene Stimme einer Persona (jede Voreinstellung eine eigene Stimme bzw. ein eigener Sprecher)
   CHVoice.pickId(id) → Stimme des Stimmenkatalogs (voice-catalog.php, z. B. „tr-f-elif“) – aus CHVoice.register(einträge) oder /chat/voice/catalog.json (CHVoice.catalog() lädt ihn)
   CHVoice.list(lang, gender) → Katalog-Einträge der Sprache (ohne Katalog: die MAP-Voreinstellungen)  ·  MAP/POOL arbeiten weiter ohne Netz
   CHVoice.say(text, voice, onProg) → Promise<Blob> (WAV)  ·  CHVoice.load(voice, onProg) lädt nur  ·  CHVoice.stored(voice) → Promise<bool>
   CHVoice.ok() = Browser kann es (Worker, WebAssembly, Cache Storage)  ·  CHVoice.auto() = darf ohne Nachfrage laden (kein Datensparmodus/Mobilfunk)
   Modelle: selbst gehostet unter /chat/vendor/voice/models/<key>.bin (Lizenzen: /chat/vendor/voice/LICENSES.txt + models/<key>.card.txt), einmalig im Browser zwischengespeichert. */
(function () {
  'use strict';
  /* Voreinstellungen (speech-lib): f_warm Lea, f_klar Sophie, f_frisch Mia, f_sanft Hanna · m_freundlich Max, m_ruhig Jonas, m_souveraen Thomas, m_tief Paul.
     Eintrag: [Modell, Sprecher-ID (Mehrsprecher-Modelle) oder null, Tempo (length_scale-Faktor: >1 langsamer), Anzeigename, MB, Klangfarbe (optional, 1 = natürlich)] */
  var MAP = {
    /* Auswahl per Messung (Tonhöhe, Tempo) auf dem Runner, nur Lizenzen mit kommerzieller Nutzung (CC0, CC-BY, CC-BY-SA, M-AILABS, Public Domain, Apache, Unlicense) */
    de: {
      f_warm: ['de_DE-kerstin-low', null, 1.0, 'Kerstin', 63], f_klar: ['de_DE-ramona-low', null, 1.0, 'Ramona', 63],
      f_frisch: ['de_DE-eva_k-x_low', null, 0.94, 'Eva', 21], f_sanft: ['de_DE-mls-medium', '589', 1.04, 'MLS 589', 77],
      m_freundlich: ['de_DE-thorsten-medium', null, 0.98, 'Thorsten', 63], m_ruhig: ['de_DE-karlsson-low', null, 1.06, 'Karlsson', 63],
      m_souveraen: ['de_DE-mls-medium', '5675', 1.0, 'MLS 5675', 77], m_tief: ['de_DE-mls-medium', '11480', 1.04, 'MLS 11480', 77]
    },
    fr: {
      f_warm: ['fr_FR-siwis-medium', null, 1.0, 'Siwis', 63], f_klar: ['fr_FR-upmc-medium', 'jessica', 1.0, 'Jessica', 77],
      f_frisch: ['fr_FR-mls-medium', '12899', 0.95, 'MLS 12899', 77], f_sanft: ['fr_FR-mls-medium', '13634', 1.02, 'MLS 13634', 77],
      m_freundlich: ['fr_FR-upmc-medium', 'pierre', 1.0, 'Pierre', 77], m_ruhig: ['fr_FR-mls-medium', '5525', 1.04, 'MLS 5525', 77],
      m_souveraen: ['fr_FR-mls-medium', '3182', 1.0, 'MLS 3182', 77], m_tief: ['fr_FR-mls-medium', '66', 1.0, 'MLS 66', 77]
    },
    es: {   /* drei freie Frauen-, vier Männerstimmen; „sanft“ als Klangfarben-Variante */
      f_warm: ['es_ES-sharvard-medium', 'F', 1.0, 'Sharvard F', 77], f_klar: ['es_MX-claude-high', null, 1.0, 'Claude (MX)', 63],
      f_frisch: ['es_AR-daniela-high', null, 0.96, 'Daniela (AR)', 114], f_sanft: ['es_ES-sharvard-medium', 'F', 1.06, 'Sharvard F', 77, 0.93],
      m_freundlich: ['es_ES-davefx-medium', null, 1.0, 'Davefx', 63], m_ruhig: ['es_ES-sharvard-medium', 'M', 1.06, 'Sharvard M', 77],
      m_souveraen: ['es_MX-ald-medium', null, 1.0, 'Ald (MX)', 63], m_tief: ['es_ES-carlfm-x_low', null, 1.04, 'Carlfm', 28, 0.92]
    },
    en: {
      f_warm: ['en_US-kristin-medium', null, 1.0, 'Kristin', 64], f_klar: ['en_GB-alba-medium', null, 1.0, 'Alba', 63],
      f_frisch: ['en_GB-cori-medium', null, 0.96, 'Cori', 64], f_sanft: ['en_GB-southern_english_female-low', null, 1.06, 'Southern', 63],
      m_freundlich: ['en_US-bryce-medium', null, 1.0, 'Bryce', 64], m_ruhig: ['en_US-john-medium', null, 1.0, 'John', 64],
      m_souveraen: ['en_GB-northern_english_male-medium', null, 1.0, 'Northern', 63], m_tief: ['en_US-joe-medium', null, 1.04, 'Joe', 63]
    },
    nl: {
      f_warm: ['nl_BE-nathalie-medium', null, 1.0, 'Nathalie', 63], f_klar: ['nl_NL-mls_5809-low', null, 1.0, 'MLS 5809', 63],
      f_frisch: ['nl_NL-mls_7432-low', null, 0.96, 'MLS 7432', 63], f_sanft: ['nl_NL-mls-medium', '1724', 1.04, 'MLS 1724', 77],
      m_freundlich: ['nl_BE-rdh-medium', null, 1.0, 'Rdh', 63], m_ruhig: ['nl_NL-mls-medium', '2450', 1.04, 'MLS 2450', 77],
      m_souveraen: ['nl_NL-mls-medium', '4429', 1.0, 'MLS 4429', 77], m_tief: ['nl_NL-mls-medium', '11290', 1.04, 'MLS 11290', 77]
    },
    tr: {   /* zwei freie türkische Stimmen (CC0): Fettah (Frau), Fahrettin (Mann) – die Voreinstellungen unterscheiden sich in Klangfarbe (6. Feld) und Tempo */
      f_warm: ['tr_TR-fettah-medium', null, 1.0, 'Fettah', 63], f_klar: ['tr_TR-fettah-medium', null, 0.98, 'Fettah', 63, 1.07],
      f_frisch: ['tr_TR-fettah-medium', null, 0.92, 'Fettah', 63, 1.12], f_sanft: ['tr_TR-fettah-medium', null, 1.06, 'Fettah', 63, 0.93],
      m_freundlich: ['tr_TR-fahrettin-medium', null, 1.0, 'Fahrettin', 63], m_ruhig: ['tr_TR-fahrettin-medium', null, 1.04, 'Fahrettin', 63, 0.95],
      m_souveraen: ['tr_TR-fahrettin-medium', null, 0.98, 'Fahrettin', 63, 1.06], m_tief: ['tr_TR-fahrettin-medium', null, 1.05, 'Fahrettin', 63, 0.88]
    },
    it: { f_warm: ['it_IT-serena-medium', null, 1.0, 'Serena', 63], m_freundlich: ['it_IT-riccardo-x_low', null, 1.0, 'Riccardo', 28] },
    ru: { m_freundlich: ['ru_RU-denis-medium', null, 1.0, 'Denis', 63] },
    sv: { m_freundlich: ['sv_SE-nst-medium', null, 1.0, 'NST', 63] },
    no: { m_freundlich: ['no_NO-talesyntese-medium', null, 1.0, 'Talesyntese', 63] },
    fi: { m_freundlich: ['fi_FI-harri-medium', null, 1.0, 'Harri', 63] }
  };
  /* Stimmen je Persona (Startseiten-Demos, Agenten): jede Persona bekommt eine eigene Stimme aus dem Vorrat ihrer Sprache und ihres Geschlechts.
     Vorrat = verschiedene Sprecher (Mehrsprecher-Modelle MLS: viele echte Menschen in einem Download), danach Klangfarben-Varianten:
     shift > 1 heller/jünger, < 1 tiefer/voller (Abtastrate × shift, Tempo über length ausgeglichen) – für Sprachen mit wenigen freien Modellen. */
  var B = function (key, sid, name, mb) { return [key, sid, 1.0, name, mb]; };
  var DE_MLS = function (ids) { return ids.map(function (i) { return B('de_DE-mls-medium', i, 'MLS ' + i, 77); }); };
  var FR_MLS = function (ids) { return ids.map(function (i) { return B('fr_FR-mls-medium', i, 'MLS ' + i, 77); }); };
  var POOL = {
    de: { f: [B('de_DE-kerstin-low', null, 'Kerstin', 63), B('de_DE-ramona-low', null, 'Ramona', 63), B('de_DE-eva_k-x_low', null, 'Eva', 21)].concat(DE_MLS(['589', '3990', '4174', '1649', '10179', '20', '327', '4650', '7261', '13871', '136', '9514', '5324'])),
          m: [B('de_DE-thorsten-medium', null, 'Thorsten', 63), B('de_DE-karlsson-low', null, 'Karlsson', 63)].concat(DE_MLS(['5675', '11480', '5055', '252', '3494', '143', '9646', '145', '3040', '13255', '287', '6719', '10904'])) },
    fr: { f: [B('fr_FR-siwis-medium', null, 'Siwis', 63), B('fr_FR-upmc-medium', 'jessica', 'Jessica', 77)].concat(FR_MLS(['12899', '13634', '12709', '3060', '11772', '2506', '14', '7614', '5553', '2155'])),
          m: [B('fr_FR-upmc-medium', 'pierre', 'Pierre', 77)].concat(FR_MLS(['5525', '3182', '66', '1840', '5764', '12501', '62', '8582', '7377', '4699', '94'])) },
    es: { f: [B('es_ES-sharvard-medium', 'F', 'Sharvard F', 77), B('es_MX-claude-high', null, 'Claude (MX)', 63), B('es_AR-daniela-high', null, 'Daniela (AR)', 114)],
          m: [B('es_ES-davefx-medium', null, 'Davefx', 63), B('es_ES-sharvard-medium', 'M', 'Sharvard M', 77), B('es_ES-carlfm-x_low', null, 'Carlfm', 28), B('es_MX-ald-medium', null, 'Ald (MX)', 63)] },
    tr: { f: [B('tr_TR-fettah-medium', null, 'Fettah', 63)], m: [B('tr_TR-fahrettin-medium', null, 'Fahrettin', 63)] }
  };
  var SHIFTS = [1.0, 0.93, 1.07, 0.88, 1.12, 0.96, 1.04];
  /** Vorrat einer Sprache/eines Geschlechts: erst alle echten Sprecher, dann Klangfarben-Varianten reihum (mind. 8 verschiedene Stimmen). */
  function pool(l, g) {
    var src = POOL[l] && POOL[l][g];
    if (!src) { src = []; var m = MAP[l] || {}; Object.keys(m).forEach(function (k) { if (k.charAt(0) === g && !src.some(function (x) { return x[0] === m[k][0] && x[1] === m[k][1]; })) src.push(B(m[k][0], m[k][1], m[k][3], m[k][4])); }); }
    if (!src.length) return [];
    var out = src.map(function (e) { return { e: e, shift: 1 }; });
    for (var i = 1; out.length < 8 && i < SHIFTS.length; i++) src.forEach(function (e) { if (out.length < 12) out.push({ e: e, shift: SHIFTS[i] }); });
    return out;
  }
  var TAKEN = {};
  function hash(s) { var h = 2166136261; for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }
  /** Persona → feste Stimme (gleiche Persona = gleiche Stimme), verschiedene Personas derselben Seite → verschiedene Stimmen. */
  function pickWho(l, g, who) {
    var P = pool(l, g); if (!P.length) return null;
    var k = l + '|' + g, t = TAKEN[k] || (TAKEN[k] = {}), i = hash(String(who)) % P.length, n = 0;
    for (var w in t) { if (Object.prototype.hasOwnProperty.call(t, w) && w === String(who)) { i = t[w]; n = -1; break; } }
    if (n === 0) {
      var used = {}; for (var w2 in t) { if (Object.prototype.hasOwnProperty.call(t, w2)) used[t[w2]] = 1; }
      while (used[i] && n < P.length) { i = (i + 1) % P.length; n++; }
      t[String(who)] = i;
    }
    return P[i];
  }
  /* Sprachen ohne eigenes Modell: Stimme einer verwandten Sprache mit eigener Aussprache (espeak) und Lautangleichung */
  var VIA = { az: { lang: 'tr', espeak: 'az', map: { 'æ': 'ɛ', 'x': 'h', 'ɣ': 'ɡ' } } };
  var W = null, seq = 0, wait = {};
  /* Browser-Stimmen (Piper) sind abgeschaltet: ChatHelp spricht nur noch mit Premium-Stimmen vom Server. Fällt der Server aus,
     nutzen die Seiten die Gerätestimme – ok() bleibt deshalb immer false (keine Modell-Downloads, keine Piper-Wiedergabe). */
  function ok() { return false; }
  function auto() {
    try { var c = navigator.connection; if (c && (c.saveData || c.type === 'cellular' || /(^|-)2g|3g/.test(c.effectiveType || ''))) return false; } catch (e) { /* egal */ }
    return true;
  }
  function worker() {
    if (W) return W;
    W = new Worker('/chat/voice/piper-worker.js?v=1');
    W.onmessage = function (ev) {
      var m = ev.data || {}, w = wait[m.id]; if (!w) return;
      if (m.t === 'progress') { if (w.prog) try { w.prog(m.loaded, m.total); } catch (e) { /* egal */ } return; }
      delete wait[m.id];
      if (m.t === 'err') w.no(new Error(m.msg)); else w.yes(m);
    };
    W.onerror = function () { Object.keys(wait).forEach(function (k) { wait[k].no(new Error('worker')); delete wait[k]; }); W = null; };
    return W;
  }
  function call(msg, prog) {
    return new Promise(function (yes, no) { var id = ++seq; wait[id] = { yes: yes, no: no, prog: prog }; msg.id = id; worker().postMessage(msg); });
  }
  function norm(l) { l = String(l || 'de').toLowerCase().slice(0, 2); return l === 'nb' || l === 'nn' ? 'no' : l; }
  /** Stimme für Sprache + Geschlecht + Voreinstellung; mit who (Persona-Kennung) die eigene Stimme dieser Persona. */
  function pick(lang, gender, preset, who) {
    var l = norm(lang), via = VIA[l], ll = via ? via.lang : l, g = gender === 'm' ? 'm' : 'f', e = null, shift = 1;
    if (who != null && who !== '') { var x = pickWho(ll, g, who); if (x) { e = x.e; shift = x.shift; } }
    if (!e) {
      var src = MAP[ll]; if (!src) return null;
      e = src[preset] || null;
      if (!e || (preset && preset.charAt(0) !== g)) { var k = Object.keys(src).filter(function (y) { return y.charAt(0) === g; })[0]; e = k ? src[k] : null; }
      if (!e) return null;
      shift = e[5] || 1;
    }
    return { key: e[0], sid: e[1], length: e[2], name: e[3] + (shift !== 1 ? (shift > 1 ? ' · hell' : ' · voll') : ''), mb: e[4], shift: shift, lang: l, espeak: via ? via.espeak : null, map: via ? via.map : null };
  }
  /* ---- Stimmenkatalog (voice-catalog.php): Einträge {id, label, tier, key, sid, shift, length, speaker, mb, preset, lang, gender, via?} ---- */
  var CAT = { by: {}, lang: {}, p: null };
  function register(list) {
    (list || []).forEach(function (e) {
      if (!e || !e.id) return;
      var s = e.source && e.source.type ? e.source : e;   // API-Form {source:{type:'piper',key,…}} oder flache Form aus catalog.json
      if (s.type && s.type !== 'piper') return;          // Cloud-Stimmen spricht der Server
      var m = /^([a-z]{2})-([fm])-/.exec(e.id); if (!m && !(e.lang && e.gender)) return;
      var x = { id: e.id, label: e.label || e.id, tier: e.tier || 'real', key: s.key, sid: s.sid == null ? null : s.sid, shift: Number(s.shift) || 1, length: Number(s.length) || 1,
        speaker: e.speaker || e.label || s.key, mb: Number(e.mb) || 63, preset: e.preset || null, lang: e.lang || m[1], gender: e.gender || m[2], via: e.via || null };
      if (!x.key) return;
      CAT.by[x.id] = x; var L = CAT.lang[x.lang] || (CAT.lang[x.lang] = { f: [], m: [] });
      if (!L[x.gender].some(function (y) { return y.id === x.id; })) L[x.gender].push(x);
    });
  }
  /** Katalog einmalig laden (statisch exportiert: php chat/voice-catalog.php export). Ohne Netz bleibt alles Bisherige nutzbar. */
  function catalog() {
    if (CAT.p) return CAT.p;
    CAT.p = fetch('/chat/voice/catalog.json', { credentials: 'omit', cache: 'force-cache' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (j) { var L = (j && j.langs) || {}; Object.keys(L).forEach(function (l) { ['f', 'm'].forEach(function (g) { (L[l][g] || []).forEach(function (e) { e.lang = l; e.gender = g; }); register(L[l][g]); }); }); return CAT; })
      .catch(function (e) { CAT.p = null; throw e; });
    return CAT.p;
  }
  function fromEntry(x) {
    var via = VIA[x.lang] || (x.via ? VIA[x.via] : null);
    return { id: x.id, key: x.key, sid: x.sid, length: x.length, name: x.speaker + (x.shift !== 1 ? (x.shift > 1 ? ' · hell' : ' · voll') : ''), label: x.label, tier: x.tier, mb: x.mb, shift: x.shift,
      lang: x.lang, preset: x.preset, espeak: via ? via.espeak : null, map: via ? via.map : null };
  }
  /** Stimme zu einer Katalog-ID (null = unbekannt oder Cloud-Stimme). */
  function pickId(id) { var x = id && CAT.by[String(id)]; return x ? fromEntry(x) : null; }
  /** Katalog-Einträge der Sprache; ohne registrierten Katalog die Voreinstellungen aus MAP (Ersatz, damit die Oberfläche immer etwas zeigt). */
  function list(lang, gender) {
    var l = norm(lang), g = gender === 'm' ? 'm' : 'f', L = CAT.lang[l];
    if (L && L[g].length) return L[g].map(fromEntry);
    var via = VIA[l], src = MAP[via ? via.lang : l] || {}, out = [];
    Object.keys(src).forEach(function (k) { if (k.charAt(0) !== g) return; var v = pick(l, g, k); if (v) { v.id = l + '-' + k; v.label = v.name; v.tier = (src[k][5] || 1) !== 1 ? 'variant' : 'real'; v.preset = k; out.push(v); } });
    return out;
  }
  function url(v) { return '/chat/vendor/voice/models/' + v.key + '.bin'; }
  function stored(v) {
    if (!v || !ok()) return Promise.resolve(false);
    return caches.open('ch-voice-v1').then(function (c) { return c.match(url(v)); }).then(function (r) { return !!r; }).catch(function () { return false; });
  }
  function load(v, prog) { return call({ t: 'load', key: v.key }, prog); }
  function wav(pcm, rate) {
    var n = pcm.length, b = new DataView(new ArrayBuffer(44 + n * 2)), s = function (o, t) { for (var i = 0; i < t.length; i++) b.setUint8(o + i, t.charCodeAt(i)); };
    s(0, 'RIFF'); b.setUint32(4, 36 + n * 2, true); s(8, 'WAVE'); s(12, 'fmt '); b.setUint32(16, 16, true); b.setUint16(20, 1, true); b.setUint16(22, 1, true);
    b.setUint32(24, rate, true); b.setUint32(28, rate * 2, true); b.setUint16(32, 2, true); b.setUint16(34, 16, true); s(36, 'data'); b.setUint32(40, n * 2, true);
    var fade = Math.min(Math.floor(rate * 0.004), Math.floor(n / 2));   // 4 ms Ein-/Ausblenden: keine Knackser an Satzgrenzen
    for (var i = 0, o = 44; i < n; i++, o += 2) {
      var x = Math.max(-1, Math.min(1, pcm[i]));
      if (i < fade) x *= i / fade; else if (i >= n - fade) x *= (n - 1 - i) / fade;
      b.setInt16(o, x < 0 ? x * 32768 : x * 32767, true);
    }
    return new Blob([b.buffer], { type: 'audio/wav' });
  }
  function say(text, v, prog) {
    var sh = Math.max(0.85, Math.min(1.15, Number(v.shift) || 1));   // Klangfarbe: schneller abspielen = heller, Tempo vorab über length ausgeglichen
    return call({ t: 'say', key: v.key, sid: v.sid, length: (v.length || 1) * sh, shift: sh, vid: v.id || null, text: String(text || ''), espeak: v.espeak, map: v.map }, prog).then(function (m) { return wav(m.pcm, Math.round(m.rate * sh)); });
  }
  window.CHVoice = { ok: ok, auto: auto, pick: pick, pickId: pickId, list: list, register: register, catalog: catalog, stored: stored, load: load, say: say, langs: function () { return Object.keys(MAP).concat(Object.keys(VIA)); } };
})();
