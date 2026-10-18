// Ende-zu-Ende: echte Browser-Stimme (chat/voice/voice.js + piper-worker.js) in Chromium – erzeugt WAVs je Sprache/Voreinstellung.
import { chromium } from 'playwright';
import fs from 'fs';
const OUT = process.argv[2] || 'out';
fs.mkdirSync(OUT, { recursive: true });
const CASES = [
  ['de', 'f', 'f_warm', 'Guten Tag, schön, dass Sie da sind. Ihr Termin ist am Donnerstag um halb zehn.'],
  ['de', 'f', 'f_sanft', 'Guten Tag, schön, dass Sie da sind. Ihr Termin ist am Donnerstag um halb zehn.'],
  ['de', 'm', 'm_tief', 'Guten Tag, schön, dass Sie da sind. Ihr Termin ist am Donnerstag um halb zehn.'],
  ['fr', 'f', 'f_klar', 'Bonjour, ravi de vous entendre. Votre rendez-vous est jeudi à neuf heures et demie.'],
  ['es', 'f', 'f_warm', 'Buenos días, encantada de saludarle. Su cita es el jueves a las nueve y media.'],
  ['tr', 'm', 'm_freundlich', 'Merhaba, sizi duymak çok güzel. Randevunuz perşembe saat dokuz buçukta.'],
  ['az', 'm', 'm_freundlich', 'Salam, sizi eşitmək çox xoşdur. Görüşünüz cümə axşamı saat doqquz otuzda.'],
];
const b = await chromium.launch();
const p = await b.newPage();
const logs = [];
p.on('console', m => logs.push(m.type() + ': ' + m.text()));
p.on('pageerror', e => logs.push('pageerror: ' + e.message));
await p.goto('http://localhost:8099/test.html');
await p.waitForFunction(() => window.CHVoice, null, { timeout: 20000 });
console.log('ok() =', await p.evaluate(() => CHVoice.ok()));
for (const [lang, g, pre, text] of CASES) {
  const t0 = Date.now();
  const r = await p.evaluate(async ([lang, g, pre, text]) => {
    const v = CHVoice.pick(lang, g, pre);
    if (!v) return { err: 'keine Stimme' };
    try {
      const t1 = performance.now();
      const blob = await CHVoice.say(text, v);
      const t2 = performance.now();
      const blob2 = await CHVoice.say('Zweiter Satz, schon geladen.', v);   // zweiter Satz: Modell im Speicher
      const t3 = performance.now();
      const buf = new Uint8Array(await blob.arrayBuffer());
      let s = ''; for (let i = 0; i < buf.length; i += 32768) s += String.fromCharCode.apply(null, buf.subarray(i, i + 32768));
      return { v, b64: btoa(s), first: Math.round(t2 - t1), second: Math.round(t3 - t2), size: buf.length, stored: await CHVoice.stored(v) };
    } catch (e) { return { err: String(e && e.message || e), v }; }
  }, [lang, g, pre, text]);
  if (r.err) { console.log('FEHLER', lang, pre, r.err, JSON.stringify(r.v || {})); continue; }
  const f = `${OUT}/browser_${lang}_${pre}.wav`;
  fs.writeFileSync(f, Buffer.from(r.b64, 'base64'));
  console.log(`${lang} ${pre} ${r.v.key}${r.v.sid ? '#' + r.v.sid : ''}: erster Satz ${r.first} ms (inkl. Laden), zweiter ${r.second} ms, ${r.size} Bytes, gespeichert=${r.stored}  (${Date.now() - t0} ms)`);
}
console.log(logs.slice(0, 40).join('\n'));
await b.close();
