#!/usr/bin/env node
/**
 * ChatHelp — Günlük içerik üretici (GitHub Actions agent'ı)
 * Claude API ile her gün bir "Rechtstipp des Tages" + kısa Antrag taslağı üretir,
 * content/generated/YYYY-MM-DD.md olarak kaydeder. Workflow bunu commit eder.
 *
 * ENV: ANTHROPIC_API_KEY (zorunlu)
 */
import { mkdirSync, writeFileSync } from 'node:fs';

const KEY = process.env.ANTHROPIC_API_KEY;
if (!KEY) { console.log('ANTHROPIC_API_KEY yok — atlanıyor.'); process.exit(0); }

const themen = [
  'Widerspruch gegen einen Bescheid', 'Kündigung Mietvertrag', 'Einspruch gegen Bußgeldbescheid',
  'Mahnung / Zahlungsaufforderung', 'Widerruf eines Vertrags', 'Antrag auf Ratenzahlung',
  'Beschwerde über mangelhafte Ware', 'Antrag auf Akteneinsicht', 'Einspruch Steuerbescheid',
  'Kündigung Arbeitsvertrag', 'Antrag Wohngeld', 'Widerspruch Jobcenter-Bescheid',
];
const thema = themen[new Date().getDate() % themen.length];

const system =
  'Du bist ein erfahrener deutscher Rechtsexperte und Content-Autor für die Plattform ChatHelp. ' +
  'Schreibe sachlich, korrekt, mit §-Verweisen. Reines Markdown, keine erfundenen Aktenzeichen, keine echten Personennamen.';

const prompt =
  `Erstelle einen kompakten, hilfreichen Beitrag zum Thema „${thema}" für deutsche Nutzer. Struktur:\n` +
  `# ${thema}\n\n## Worum geht es?\n(2-3 Sätze)\n\n## Wichtige Fristen & Gesetze\n(Stichpunkte mit §)\n\n` +
  `## Schritt-für-Schritt\n(nummerierte Liste)\n\n## Muster-Einstieg\n(2-3 Sätze Beispieltext mit [PLATZHALTERN])\n\n` +
  `## Häufige Fehler\n(Stichpunkte)`;

const res = await fetch('https://api.anthropic.com/v1/messages', {
  method: 'POST',
  headers: { 'x-api-key': KEY, 'anthropic-version': '2023-06-01', 'content-type': 'application/json' },
  body: JSON.stringify({
    model: 'claude-sonnet-4-6', max_tokens: 1800,
    system, messages: [{ role: 'user', content: prompt }],
  }),
});

if (!res.ok) { console.error('Claude API Fehler:', res.status, await res.text()); process.exit(0); }
const data = await res.json();
const text = data?.content?.[0]?.text;
if (!text) { console.error('Leere Antwort — übersprungen.'); process.exit(0); }

const date = new Date().toISOString().slice(0, 10);
mkdirSync('content/generated', { recursive: true });
const file = `content/generated/${date}.md`;
const front = `---\ntitel: "${thema}"\ndatum: ${date}\nquelle: ChatHelp-Agent (Claude)\n---\n\n`;
writeFileSync(file, front + text + '\n');
console.log('Geschrieben:', file);
