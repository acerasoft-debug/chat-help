#!/usr/bin/env node
/**
 * ChatHelp — Übersetzungen der Startseite (site/i18n/<lang>.json) prüfen und versionieren
 * ========================================================================================
 *   node tools/site-i18n.mjs                 → jede Sprachdatei prüfen (gültiges JSON, nur Texte), Inhalts-Hash berechnen,
 *                                              window.I18N_V={…} in site/index.html setzen (URL /i18n/<lang>.json?v=<hash> – Cloudflare-sicher)
 *                                              und fehlende Zeilen in deploy-manifest.txt ergänzen
 *   node tools/site-i18n.mjs --check de.json → zusätzlich gegen die deutschen Quelltexte prüfen (fehlende/überzählige Schlüssel,
 *                                              Platzhalter {n}/{p:…}/{e:…} und HTML-Tags müssen übereinstimmen). de.json = l10nKeys()+I18N.de der Seite
 *                                              (der Playwright-Test i18n-site schreibt sie).
 * Deutsch ist die Quelle und steht im HTML/JS der Startseite – es gibt keine de.json im Deploy.
 */
import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIR = join(ROOT, 'site/i18n'), HTML = join(ROOT, 'site/index.html'), MAN = join(ROOT, 'deploy-manifest.txt');
const LANGS = ['en', 'fr', 'es', 'tr', 'ru', 'it', 'nl', 'sv', 'no', 'fi', 'ja', 'az'];
const args = process.argv.slice(2), ci = args.indexOf('--check'), src = ci >= 0 ? JSON.parse(readFileSync(args[ci + 1], 'utf8')) : null;
let bad = 0;
/* Preise: im deutschen HTML als <span data-eur(x)="N">…</span>, in der Übersetzung als {p:N} bzw. {e:N}; {e:…} in Gesprächen ersetzt feste „12,90 €“ (nicht verglichen) */
const norm = s => String(s).replace(/<span data-eur="([\d.]+)">[^<]*<\/span>/g, '{p:$1}').replace(/<span data-eurx="([\d.]+)">[^<]*<\/span>/g, '{e:$1}');
const ph = s => [...norm(s).matchAll(/\{(?:p:[\d.]+|\w+)\}/g)].map(m => m[0]).sort().join(' ');
const tags = s => [...norm(s).matchAll(/<\/?([a-z0-9]+)\b/gi)].map(m => m[1].toLowerCase()).sort().join(' ');
const ver = {};
for (const l of LANGS) {
  let j;
  try { j = JSON.parse(readFileSync(join(DIR, l + '.json'), 'utf8')); } catch (e) { console.log(`✗ ${l}: ${e.code === 'ENOENT' ? 'fehlt' : e.message}`); bad++; continue; }
  if (!j || typeof j !== 'object' || Array.isArray(j)) { console.log(`✗ ${l}: kein Objekt`); bad++; continue; }
  const nonStr = Object.keys(j).filter(k => typeof j[k] !== 'string');
  if (nonStr.length) { console.log(`✗ ${l}: keine Texte: ${nonStr.slice(0, 5).join(', ')}`); bad++; }
  ver[l] = createHash('sha1').update(readFileSync(join(DIR, l + '.json'))).digest('hex').slice(0, 10);
  if (src) {
    const miss = Object.keys(src).filter(k => !(k in j)), extra = Object.keys(j).filter(k => !(k in src)), mism = [];
    for (const k of Object.keys(src)) if (k in j && !/^(kw|seckw)\./.test(k)) { if (ph(src[k]) !== ph(j[k])) mism.push(k + ' (Platzhalter)'); else if (tags(src[k]) !== tags(j[k])) mism.push(k + ' (HTML)'); }
    console.log(`${miss.length || mism.length ? '•' : '✓'} ${l}: ${Object.keys(j).length} Schlüssel · fehlend ${miss.length}${miss.length ? ' (' + miss.slice(0, 6).join(', ') + (miss.length > 6 ? ' …' : '') + ')' : ''} · überzählig ${extra.length} · Abweichungen ${mism.length}${mism.length ? ' (' + mism.slice(0, 6).join(', ') + ')' : ''} · v=${ver[l]}`);
  } else console.log(`✓ ${l}: ${Object.keys(j).length} Schlüssel · v=${ver[l]}`);
}
/* Versionen in den Kopf der Startseite schreiben */
const html = readFileSync(HTML, 'utf8'), re = /window\.I18N_V=\{[^}]*\};/;
if (!re.test(html)) { console.log('✗ window.I18N_V nicht in site/index.html gefunden'); process.exit(1); }
const next = html.replace(re, 'window.I18N_V=' + JSON.stringify(ver) + ';');
if (next !== html) { writeFileSync(HTML, next); console.log('→ site/index.html: I18N_V aktualisiert'); }
/* Skript der Startseite (site/site.js): Version = Inhalts-Hash → src="/site.js?v=<hash>" (Cloudflare-sicher wie die Sprachdateien) */
try {
  const JS = join(ROOT, 'site/site.js'), jv = createHash('sha1').update(readFileSync(JS)).digest('hex').slice(0, 10), cur = readFileSync(HTML, 'utf8'), re2 = /src="\/site\.js\?v=[0-9a-f]*"/;
  if (!re2.test(cur)) { console.log('✗ src="/site.js?v=…" nicht in site/index.html'); bad++; }
  else { const n2 = cur.replace(re2, `src="/site.js?v=${jv}"`); if (n2 !== cur) { writeFileSync(HTML, n2); console.log('→ site/index.html: site.js v=' + jv); } else console.log('✓ site.js v=' + jv); }
} catch (e) { console.log('✗ site/site.js: ' + e.message); bad++; }
/* Deploy-Manifest: jede Sprachdatei einmal */
let man = readFileSync(MAN, 'utf8'); const add = [];
for (const l of Object.keys(ver)) { const line = `site/i18n/${l}.json`; if (!man.split('\n').some(x => x.trim().startsWith(line + ' '))) add.push(`${line.padEnd(28)} i18n/${l}.json`); }
if (add.length) { man = man.replace(/\n*$/, '\n') + (man.includes('# Startseite: Übersetzungen') ? '' : '# Startseite: Übersetzungen je Sprache (Deutsch steht in index.html; ?v=<hash> setzt tools/site-i18n.mjs)\n') + add.join('\n') + '\n'; writeFileSync(MAN, man); console.log('→ deploy-manifest.txt: +' + add.length); }
process.exit(bad ? 1 : 0);
