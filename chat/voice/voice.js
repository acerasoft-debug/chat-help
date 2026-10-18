/* ChatHelp — echte Stimmen im Browser (Piper, Open Source, kein API-Schlüssel). Gemeinsam für Studio-Assistent und Startseiten-Demos.
   CHVoice.pick(lang, gender, preset) → Stimme der Voreinstellung (jede Voreinstellung eine eigene Stimme bzw. ein eigener Sprecher)
   CHVoice.say(text, voice, onProg) → Promise<Blob> (WAV)  ·  CHVoice.load(voice, onProg) lädt nur  ·  CHVoice.stored(voice) → Promise<bool>
   CHVoice.ok() = Browser kann es (Worker, WebAssembly, Cache Storage)  ·  CHVoice.auto() = darf ohne Nachfrage laden (kein Datensparmodus/Mobilfunk)
   Modelle: selbst gehostet unter /chat/vendor/voice/models/<key>.bin (Lizenzen: /chat/vendor/voice/LICENSES.txt + models/<key>.card.txt), einmalig im Browser zwischengespeichert. */
(function () {
  'use strict';
  /* Voreinstellungen (speech-lib): f_warm Lea, f_klar Sophie, f_frisch Mia, f_sanft Hanna · m_freundlich Max, m_ruhig Jonas, m_souveraen Thomas, m_tief Paul.
     Eintrag: [Modell, Sprecher-ID (Mehrsprecher-Modelle) oder null, Tempo (length_scale-Faktor: >1 langsamer), Anzeigename, MB] */
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
    es: {   /* nur zwei freie Frauenstimmen → „frisch“ und „sanft“ mit anderem Tempo */
      f_warm: ['es_ES-sharvard-medium', 'F', 1.0, 'Sharvard F', 77], f_klar: ['es_MX-claude-high', null, 1.0, 'Claude (MX)', 63],
      f_frisch: ['es_MX-claude-high', null, 0.9, 'Claude (MX)', 63], f_sanft: ['es_ES-sharvard-medium', 'F', 1.12, 'Sharvard F', 77],
      m_freundlich: ['es_ES-davefx-medium', null, 1.0, 'Davefx', 63], m_ruhig: ['es_ES-sharvard-medium', 'M', 1.06, 'Sharvard M', 77],
      m_souveraen: ['es_ES-carlfm-x_low', null, 1.0, 'Carlfm', 28], m_tief: ['es_ES-davefx-medium', null, 1.12, 'Davefx', 63]
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
    tr: {   /* zwei freie türkische Stimmen (CC0): Fettah (Frau), Fahrettin (Mann) – die Voreinstellungen unterscheiden sich im Tempo */
      f_warm: ['tr_TR-fettah-medium', null, 1.0, 'Fettah', 63], f_klar: ['tr_TR-fettah-medium', null, 0.96, 'Fettah', 63],
      f_frisch: ['tr_TR-fettah-medium', null, 0.9, 'Fettah', 63], f_sanft: ['tr_TR-fettah-medium', null, 1.1, 'Fettah', 63],
      m_freundlich: ['tr_TR-fahrettin-medium', null, 1.0, 'Fahrettin', 63], m_ruhig: ['tr_TR-fahrettin-medium', null, 1.06, 'Fahrettin', 63],
      m_souveraen: ['tr_TR-fahrettin-medium', null, 0.97, 'Fahrettin', 63], m_tief: ['tr_TR-fahrettin-medium', null, 1.1, 'Fahrettin', 63]
    },
    it: { m_freundlich: ['it_IT-riccardo-x_low', null, 1.0, 'Riccardo', 28] },
    ru: { m_freundlich: ['ru_RU-denis-medium', null, 1.0, 'Denis', 63] },
    sv: { m_freundlich: ['sv_SE-nst-medium', null, 1.0, 'NST', 63] },
    no: { m_freundlich: ['no_NO-talesyntese-medium', null, 1.0, 'Talesyntese', 63] },
    fi: { m_freundlich: ['fi_FI-harri-medium', null, 1.0, 'Harri', 63] }
  };
  /* Sprachen ohne eigenes Modell: Stimme einer verwandten Sprache mit eigener Aussprache (espeak) und Lautangleichung */
  var VIA = { az: { lang: 'tr', espeak: 'az', map: { 'æ': 'ɛ', 'x': 'h', 'ɣ': 'ɡ' } } };
  var W = null, seq = 0, wait = {};
  function ok() {
    try { return !!(window.Worker && window.WebAssembly && window.caches && window.isSecureContext && typeof BigInt64Array !== 'undefined'); } catch (e) { return false; }
  }
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
  function pick(lang, gender, preset) {
    var l = norm(lang), via = VIA[l], src = MAP[via ? via.lang : l]; if (!src) return null;
    var g = gender === 'm' ? 'm' : 'f', e = src[preset] || null;
    if (!e || (preset && preset.charAt(0) !== g)) { var k = Object.keys(src).filter(function (x) { return x.charAt(0) === g; })[0]; e = k ? src[k] : null; }
    if (!e) return null;
    return { key: e[0], sid: e[1], length: e[2], name: e[3], mb: e[4], lang: l, espeak: via ? via.espeak : null, map: via ? via.map : null };
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
    for (var i = 0, o = 44; i < n; i++, o += 2) { var x = Math.max(-1, Math.min(1, pcm[i])); b.setInt16(o, x < 0 ? x * 32768 : x * 32767, true); }
    return new Blob([b.buffer], { type: 'audio/wav' });
  }
  function say(text, v, prog) {
    return call({ t: 'say', key: v.key, sid: v.sid, length: v.length, text: String(text || ''), espeak: v.espeak, map: v.map }, prog).then(function (m) { return wav(m.pcm, m.rate); });
  }
  window.CHVoice = { ok: ok, auto: auto, pick: pick, stored: stored, load: load, say: say, langs: function () { return Object.keys(MAP).concat(Object.keys(VIA)); } };
})();
