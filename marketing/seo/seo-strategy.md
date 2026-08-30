# ChatHelp — SEO-Strategie

Drei Content-Säulen (die drei Verkaufsargumente):

1. **Dilekçe / Schreiben erstellen** — Widerspruch, Antrag, Mahnung, Kündigung, Einspruch.
2. **Korrekte, geprüfte Informationen** — echte Rechtsquellen, von einer zweiten KI geprüft
   („geprüft statt geraten"), keine oberflächlichen Standard-Antworten, kein Template-Ausfüllen.
3. **Versand** — Brief, Fax und Einschreiben (mit Zustellnachweis) online versenden lassen.

---

## 0) Wichtigster technischer Befund zuerst

Die Startseite (`index.html`) leitet **sofort per Meta-Refresh + JS** auf `/chat/` um und hat
**keinen eigenen Inhalt**. Für Google ist die Homepage damit praktisch leer → schlechtes Ranking.

**Empfehlung:** `chat-help.com/` als echte, gerenderte HTML-Seite ausliefern (H1, Fließtext,
FAQ, interne Links, die `head-de.html`-Tags), statt sofort weiterzuleiten. Der Chat/App-Aufruf
gehört hinter einen Button („Jetzt starten" → `/chat/`), nicht in einen Auto-Redirect.
Sicherstellen, dass Seiten **serverseitig gerenderten, crawlbaren Text** enthalten (nicht nur
eine leere JS-Hülle).

---

## 1) Keyword-Map (Deutsch)

### Säule 1 — Schreiben erstellen (kommerziell/transaktional)
| Keyword | Intent |
|---|---|
| widerspruch schreiben / widerspruch einlegen muster | commercial |
| widerspruch gegen bescheid vorlage | commercial |
| widerspruch mieterhöhung / jobcenter / krankenkasse / bußgeld | commercial (long-tail, hohes Volumen) |
| antrag schreiben · mahnung schreiben vorlage · kündigung schreiben | commercial |
| einspruch einlegen · musterbrief behörde · rechtssicherer brief | commercial |
| ki brief schreiben · ki rechtsassistent · brief von ki erstellen | commercial (Marken-/Innovationsnische) |

### Säule 2 — Wissen/Vertrauen (informational)
| Keyword | Intent |
|---|---|
| widerspruchsfrist bescheid · wie lege ich widerspruch ein | informational |
| behördenbrief verstehen · was bedeutet dieser bescheid | informational |
| ist ein widerspruch sinnvoll · widerspruch begründung | informational |
| geprüfte musterbriefe · rechtssichere vorlage | informational→commercial |

### Säule 3 — Versand (transaktional)
| Keyword | Intent |
|---|---|
| brief online versenden / verschicken lassen | transactional |
| fax online senden · online fax an behörde | transactional |
| einschreiben online versenden / beauftragen | transactional |
| brief drucken und versenden lassen · post online versenden | transactional |

## 1b) Keyword-Map (English / UK)
- write a letter to challenge rent increase · section 13 challenge letter · appeal a decision letter
- how to write a formal complaint letter uk · dispute letter template uk
- send a letter online uk · signed for delivery online · send a fax online uk
- ai legal letter · ai legal assistant uk

---

## 2) Seiten-/URL-Architektur (Topic-Cluster)

**Pillar-Pages** (je eigene, indexierbare Seite mit einzigartigem Title/H1/FAQ):

- `/` — Home: „Widerspruch, Antrag & Brief mit KI erstellen und versenden"
- `/widerspruch/` — Widerspruch schreiben & versenden (Pillar)
- `/antrag/`, `/mahnung/`, `/kuendigung/`, `/einspruch/` — je ein Dokumenttyp
- `/versand/` — Brief · Fax · Einschreiben online versenden lassen
- `/ratgeber/` — Blog-Hub + Artikel (siehe §4)
- `/en/` — UK-Home, `/en/challenge-rent-increase/`, `/en/appeal-a-decision/`, `/en/send-a-letter-online/`

**Interne Verlinkung:** jede Ratgeber-Seite verlinkt auf die passende Pillar-Page und mit klarem
CTA in die App (`/chat/`). Pillar-Pages verlinken untereinander (Widerspruch ↔ Versand ↔ Fristen).

---

## 3) On-Page-Checkliste (pro Seite)

- **Title** ≤ 60 Zeichen, primäres Keyword vorne, Marke hinten: `… | ChatHelp`.
- **Meta-Description** ≤ 155 Zeichen, Nutzen + Handlungsaufruf.
- **Genau ein `<h1>`** mit dem primären Keyword; `<h2>`/`<h3>` für Unterthemen + „Häufige Fragen".
- **Erste 100 Wörter** enthalten das Keyword natürlich; kurze Absätze, Aufzählungen.
- **FAQ-Block** sichtbar auf der Seite **und** als `FAQPage`-JSON-LD (siehe `head-de.html`).
- **Interne Links** (3–6) auf verwandte Pillar-/Ratgeber-Seiten.
- **Bilder** mit sprechendem `alt` (z. B. „Widerspruch-Vorlage von ChatHelp").
- **Klarer CTA** oben & unten: „Jetzt Schreiben erstellen → chat-help.com".
- **Canonical** self-referencing; **hreflang** de / en-GB / x-default.

Meta-Vorlagen (Beispiele):
- Home DE: siehe `head-de.html`.
- `/widerspruch/`: Title `Widerspruch schreiben & versenden – geprüft, in Minuten | ChatHelp` ·
  Desc `Widerspruch gegen Bescheid, Mieterhöhung & Co. – von KI erstellt, von einer 2. KI geprüft, per Einschreiben versendet. Fristwahrend, rechtssicher formuliert.`
- `/versand/`: Title `Brief, Fax & Einschreiben online versenden lassen | ChatHelp` ·
  Desc `Ihr Schreiben ohne Drucker und ohne Gang zur Post: als Brief, Fax oder Einschreiben mit Zustellnachweis versenden – oder als PDF & E-Mail.`

---

## 4) Content-Ideen (Ratgeber = Long-Tail-Traffic)

Rechts-„How-to"-Suchen haben hohes Volumen und geringe Konkurrenz für gute Antworten:

- „Widerspruch gegen Bescheid: Frist, Aufbau, Muster (2026)"
- „Widerspruch gegen Mieterhöhung – wann er sich lohnt + Musterbrief"
- „Bescheid vom Jobcenter/Krankenkasse: was tun? Fristen & Widerspruch"
- „Einschreiben, Einwurf-Einschreiben oder Fax an die Behörde? Was zählt fristwahrend?"
- „Behördendeutsch übersetzt: die 20 häufigsten Formulierungen"

Jeder Artikel: klare Struktur, FAQ-Schema, CTA in die App. So wird ChatHelp zur Antwort auf
die Frage **und** zum Werkzeug, das sie löst.

---

## 5) Technisches SEO

- **Startseite mit Inhalt** ausliefern (siehe §0), kein Auto-Redirect.
- **hreflang** de / en-GB / x-default konsistent (in `head-*.html` enthalten).
- **XML-Sitemap** unter `/sitemap.xml` (Vorlage in `sitemap.xml`), in Search Console einreichen.
- **robots.txt** (Vorlage in `robots.txt`): Dev-/Diagnose-Skripte (`apply-*.php`, `dump-*.php`,
  `*-test.php`, `audit.php`, `diagnostic.php`) von der Indexierung ausschließen.
- **Structured Data** validieren: <https://validator.schema.org/> und Rich-Results-Test.
- **Core Web Vitals / Mobile**: schnelle Ladezeit, `<meta name="viewport">`, komprimierte Bilder,
  `og-de.png`/`og-en.png` (1200×630) ins Web-Root hochladen.
- **HTTPS + eine kanonische Host-Variante** (www ↔ non-www per 301 vereinheitlichen).
- **Sicherheitshinweis (SEO-nah):** Diagnose-Skripte wie `show-keys.php` dürfen weder indexiert
  noch öffentlich erreichbar sein – serverseitig sperren, nicht nur per robots.txt.

---

## 6) Preise als strukturierte Daten (echte Werte einsetzen)

Sobald die aktuellen Preise feststehen, dieses JSON-LD ergänzen (Platzhalter ersetzen):

```json
{
  "@context": "https://schema.org", "@type": "Product", "name": "ChatHelp Pro",
  "description": "20 Fälle pro Monat – Erstellung und Versand rechtlicher Schreiben.",
  "brand": { "@type": "Brand", "name": "ChatHelp" },
  "offers": { "@type": "Offer", "price": "PREIS_EINSETZEN", "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock", "url": "https://chat-help.com/" }
}
```
> Keine erfundenen Bewertungen/`aggregateRating` einbauen – Google kann das abstrafen.

---

## 7) Messung
- **Google Search Console** + **Bing Webmaster Tools** einrichten, Sitemap einreichen.
- Rankings/Impressionen je Cluster (Widerspruch, Versand, Fristen) verfolgen.
- Ziel-Queries: „widerspruch schreiben", „einschreiben online", „ki brief" u. a.
