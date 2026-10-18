/* ChatHelp — echte Sprachausgabe im Browser (Piper, Open Source, ohne API-Schlüssel), läuft als Web Worker.
   Text → Phoneme (espeak-ng als WebAssembly, piper_phonemize) → Stimme (VITS-Modell über onnxruntime-web) → PCM (Float32).
   Bibliothek und Stimmen liegen selbst gehostet unter /chat/vendor/voice/ (kein Drittanbieter im Browser) und werden einmalig
   in den Cache Storage des Browsers geladen („ch-voice-v1“). Höchstens zwei Stimmen gleichzeitig im Speicher.
   Nachrichten: {t:'say', id, key, sid, length, text, espeak?, map?} → {t:'pcm', id, pcm, rate} | {t:'progress', id, key, loaded, total} | {t:'err', id, msg}
                {t:'load', id, key} → {t:'loaded', id} (nur laden, z. B. im Hintergrund) */
'use strict';
var BASE = '/chat/vendor/voice/', LIB = BASE + 'lib/', MODELS = BASE + 'models/', CACHE = 'ch-voice-v1';
var boot = null, wasmBuf = null, dataBuf = null, sessions = {}, order = [], loading = {};

function cacheGet(url) { return self.caches ? caches.open(CACHE).then(function (c) { return c.match(url); }) : Promise.resolve(null); }
/** Datei laden: erst aus dem Cache, sonst mit Fortschritt aus dem Netz und danach in den Cache. */
function fetchBuf(url, onProg) {
  return cacheGet(url).catch(function () { return null; }).then(function (hit) {
    if (hit) return hit.arrayBuffer();
    return fetch(url, { credentials: 'omit' }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status + ' ' + url);
      var total = +(r.headers.get('Content-Length') || 0), got = 0, parts = [];
      var done = function (buf) {
        if (self.caches) caches.open(CACHE).then(function (c) { return c.put(url, new Response(buf.slice(0), { headers: { 'Content-Type': 'application/octet-stream' } })); }).catch(function () {});
        return buf;
      };
      if (!r.body || !r.body.getReader) return r.arrayBuffer().then(done);
      var rd = r.body.getReader();
      return (function pump() {
        return rd.read().then(function (x) {
          if (x.done) {
            var out = new Uint8Array(got), o = 0; parts.forEach(function (p) { out.set(p, o); o += p.length; });
            return done(out.buffer);
          }
          parts.push(x.value); got += x.value.length; if (onProg) onProg(got, total);
          return pump();
        });
      })();
    });
  });
}
function init() {
  if (boot) return boot;
  boot = Promise.resolve().then(function () {
    importScripts(LIB + 'ort.wasm.min.js', LIB + 'piper_phonemize.js');
    ort.env.wasm.wasmPaths = LIB;
    ort.env.wasm.numThreads = self.crossOriginIsolated ? Math.max(1, Math.min(4, (navigator.hardwareConcurrency || 2) - 1)) : 1;
    return Promise.all([fetchBuf(LIB + 'piper_phonemize.wasm'), fetchBuf(LIB + 'piper_phonemize.data.bin')]);
  }).then(function (b) { wasmBuf = b[0]; dataBuf = b[1]; });
  boot.catch(function () { boot = null; });
  return boot;
}
/** Text → Phonem-IDs. Das Emscripten-Modul wird je Satz neu erzeugt (espeak hält Zustand), Wasm und Daten kommen aus dem Speicher. */
function phonemize(text, espeakVoice) {
  var lines = [], errs = [];
  return createPiperPhonemize({
    wasmBinary: wasmBuf,
    getPreloadedPackage: function () { return dataBuf.slice(0); },
    locateFile: function (f) { return LIB + f; },
    print: function (s) { lines.push(s); },
    printErr: function (s) { errs.push(s); },
    noInitialRun: true
  }).then(function (mod) {
    mod.callMain(['-l', espeakVoice, '--input', JSON.stringify([{ text: text }]), '--espeak_data', '/espeak-ng-data']);
    var ids = [], ph = [];
    lines.forEach(function (l) { try { var j = JSON.parse(l); if (j && j.phoneme_ids) { ids = ids.concat(j.phoneme_ids); ph.push(j.phonemes || []); } } catch (e) { /* keine JSON-Zeile */ } });
    if (!ids.length) throw new Error('Phoneme leer ' + errs.join(' ').slice(0, 200));
    return { ids: ids, ph: ph };
  });
}
/**
 * Phoneme einer verwandten Sprache auf die Stimme abbilden (z. B. Aserbaidschanisch mit türkischer Stimme: æ → ɛ, x → h).
 * Aufbau wie in Piper: ^ _ (Phonem _)* $ – die IDs kommen aus der phoneme_id_map des Modells.
 */
function remap(r, cfg, map) {
  var pm = cfg.phoneme_id_map || {}, id = function (p) { return pm[p] ? pm[p][0] : null; }, out = [];
  r.ph.forEach(function (sent) {
    out.push(id('^'), id('_'));
    sent.forEach(function (p) { var q = map[p] != null ? map[p] : p; if (q === '') return; var i = id(q); if (i == null) return; out.push(i, id('_')); });
    out.push(id('$'));
  });
  return out.indexOf(null) < 0 && out.length > 3 ? out : r.ids;
}
function session(key, onProg) {
  if (sessions[key]) { order = order.filter(function (k) { return k !== key; }).concat(key); return Promise.resolve(sessions[key]); }
  if (loading[key]) return loading[key];
  var p = init().then(function () {
    return Promise.all([fetchBuf(MODELS + key + '.json').then(function (b) { return JSON.parse(new TextDecoder().decode(b)); }), fetchBuf(MODELS + key + '.bin', onProg)]);
  }).then(function (r) {
    return ort.InferenceSession.create(r[1], { executionProviders: ['wasm'], graphOptimizationLevel: 'all' }).then(function (s) {
      var e = { s: s, cfg: r[0] };
      sessions[key] = e; order.push(key);
      while (order.length > 2) { var old = order.shift(); try { sessions[old].s.release(); } catch (x) { /* egal */ } delete sessions[old]; }
      return e;
    });
  });
  loading[key] = p;
  p.then(function () { delete loading[key]; }, function () { delete loading[key]; });
  return p;
}
function synth(e, ids, sid, length) {
  var inf = e.cfg.inference || {}, n = ids.length;
  var feeds = {
    input: new ort.Tensor('int64', BigInt64Array.from(ids.map(function (x) { return BigInt(x); })), [1, n]),
    input_lengths: new ort.Tensor('int64', BigInt64Array.from([BigInt(n)]), [1]),
    scales: new ort.Tensor('float32', Float32Array.from([inf.noise_scale != null ? inf.noise_scale : 0.667, (inf.length_scale || 1) * (length || 1), inf.noise_w != null ? inf.noise_w : 0.8]), [3])
  };
  if ((e.cfg.num_speakers || 1) > 1) {   // Sprecher als Name (MLS-Nummer, „jessica“) oder Zahl
    var map = e.cfg.speaker_id_map || {}, n2 = typeof sid === 'string' ? map[sid] : sid;
    feeds.sid = new ort.Tensor('int64', BigInt64Array.from([BigInt(n2 || 0)]), [1]);
  }
  return e.s.run(feeds).then(function (out) {
    var pcm = out[e.s.outputNames[0]].data, peak = 0, i;
    pcm = new Float32Array(pcm);
    for (i = 0; i < pcm.length; i++) { var a = Math.abs(pcm[i]); if (a > peak) peak = a; }
    if (peak > 0.01) { var k = 0.92 / peak; for (i = 0; i < pcm.length; i++) pcm[i] *= k; }   // gleiche Lautstärke wie Piper (normalize_audio)
    return pcm;
  });
}
self.onmessage = function (ev) {
  var m = ev.data || {}, id = m.id;
  var prog = function (loaded, total) { self.postMessage({ t: 'progress', id: id, key: m.key, loaded: loaded, total: total }); };
  if (m.t === 'load') {
    session(m.key, prog).then(function () { self.postMessage({ t: 'loaded', id: id }); }, function (e) { self.postMessage({ t: 'err', id: id, msg: String(e && e.message || e) }); });
    return;
  }
  if (m.t !== 'say') return;
  session(m.key, prog).then(function (e) {
    return phonemize(String(m.text || '').slice(0, 600), m.espeak || (e.cfg.espeak && e.cfg.espeak.voice) || 'de').then(function (r) {
      return synth(e, m.map ? remap(r, e.cfg, m.map) : r.ids, m.sid, m.length);
    }).then(function (pcm) {
      self.postMessage({ t: 'pcm', id: id, pcm: pcm, rate: (e.cfg.audio && e.cfg.audio.sample_rate) || 22050 }, [pcm.buffer]);
    });
  }).catch(function (e) { self.postMessage({ t: 'err', id: id, msg: String(e && e.message || e) }); });
};
