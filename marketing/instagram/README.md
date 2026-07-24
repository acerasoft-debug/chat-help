# ChatHelp — Instagram-Assets (Reel + Karussell)

Social-Media-Assets, die das System von ChatHelp erklären. Alle Texte auf **Deutsch**
(im Video eingebrannt). Kernbotschaft: **anders als eine Standard-KI** — ChatHelp holt
echte Fakten aus einer **Wissensdatenbank**, lässt sie von einer **zweiten KI prüfen** und
formuliert gemeinsam ein **fehlerfreies, versandfertiges** Dokument. **Geprüft statt geraten.**

## Inhalt

| Datei | Was | Maße |
|---|---|---|
| `chathelp-reel.mp4` | **Erklär-Reel (stumm)** — 44 Sek., inkl. Vergleichs- und Versand-Szene; stiller Tonspur, für eigene Musik/Trend-Audio | 1080×1920 (9:16), H.264, 30 fps |
| `chathelp-reel-vo.mp4` | **Erklär-Reel mit Voiceover** — dieselbe 44-Sek.-Reel mit deutscher weiblicher Sprecherin (Google **Chirp3-HD-Vindemiatrix**, natürlich/Werbeton), szenengenau | 1080×1920, H.264 + AAC |
| `carousel/01-cover.png` | Karussell 1 — Aufhänger: „Amtliches Schreiben erhalten?" | 1080×1350 (4:5) |
| `carousel/02-problem.png` | Karussell 2 — Problem: unverständlicher Bescheid + laufende Frist | 1080×1350 |
| `carousel/03-schildern.png` | Karussell 3 — Schritt 1: in eigenen Worten schildern, Dokument hochladen | 1080×1350 |
| `carousel/04-analyse.png` | Karussell 4 — Schritt 2: **Wissensdatenbank + 2 KIs (Recherche · Kontrolle)** | 1080×1350 |
| `carousel/05-unterschied.png` | Karussell 5 — **Der Unterschied: geprüft statt geraten** (vs. Standard-KI) | 1080×1350 |
| `carousel/06-ergebnis.png` | Karussell 6 — Schritt 3: versandfertiges Dokument | 1080×1350 |
| `carousel/07-versand.png` | Karussell 7 — Schritt 4: **Versand — Fax · Brief · Einschreiben** | 1080×1350 |
| `carousel/08-cta.png` | Karussell 8 — CTA: chat-help.com | 1080×1350 |
| `CAPTIONS.md` | Fertige Beschreibungstexte (DE, optional TR) + Hashtags | — |
| `scene.html` | **Quelle** der Reel-Animation (bearbeitbar) | — |
| `carousel.html` | **Quelle** der Karussell-Slides (bearbeitbar) | — |
| `render.mjs` / `carousel.mjs` | Render-Skripte (Playwright + ffmpeg) | — |

## Erzählte Botschaft

1. **Schildern** — Nutzer schildert sein Anliegen, lädt Dokument/Foto hoch; die KI liest es
   (Datum, Frist, Aktenzeichen) und fragt nach, bis alles klar ist.
2. **Analyse** — mehrere spezialisierte KIs arbeiten zusammen:
   **Wissensdatenbank** (echte §§/Fristen/Urteile) → **Recherche (KI 1)** holt die echten Fakten →
   **Kontrolle (KI 2)** prüft & verifiziert jede Angabe → **Formulierung** (gemeinsam, fehlerfrei).
3. **Der Unterschied** — *geprüft statt geraten*: keine oberflächlichen Standard-Antworten,
   echte Quellen, doppelt kontrolliert.
4. **Ergebnis** — geprüftes, versandfertiges Dokument in korrekter Briefform.
5. **Versand** (letzter Schritt) — ChatHelp verschickt es für Sie: per **Fax**, **Brief** oder
   **Einschreiben** (mit Zustellnachweis, fristwahrend); auf Wunsch auch als PDF & E-Mail.
   Persönliche Daten werden nur serverseitig zusammengeführt, nicht an Dritte gegeben.

> Anbieternamen (Modelle im Hintergrund) werden bewusst **nicht** gezeigt — alles unter der
> Marke „ChatHelp KI", aufgabenbasiert. Die Positionierung basiert auf dem realen Ablauf in
> `chat/fall-api.php` und den länderspezifischen Wissens-/Checklisten-Katalogen im Repo.

## Neu erzeugen / bearbeiten

Text/Farben in `scene.html` (Reel) bzw. `carousel.html` ändern, dann neu rendern.
Voraussetzung: Node.js + `playwright-core` + `ffmpeg-static` (Chromium ist im System vorhanden).

```bash
npm install playwright-core ffmpeg-static

# Reel (Frames rendern + H.264-MP4 kodieren) — 40 Sek.
node render.mjs full chathelp-reel.mp4

# Schnelle Keyframe-Vorschau (Ordner test/)
node render.mjs test

# Karussell-PNGs (Ordner carousel/)
node carousel.mjs
```

- `scene.html` läuft auf einer deterministischen Zeitachse: `window.renderFrame(ms)` zeichnet
  jeden Frame rein funktional — Frame-für-Frame-Capture ist damit exakt reproduzierbar.
- `scene.html` direkt im Browser geöffnet: die Animation läuft automatisch in Schleife
  (im Vollbild auf dem Handy per **Bildschirmaufnahme** als Alternative zum MP4 aufnehmbar).

## Marke

Gold-Verlauf `#F6E2A6 → #D4A84A → #B0801F`, anthrazit `#07070e/#12121d`, Emblem = Waage im
Chat-Bubble (siehe `brand/`). Serif für Headlines, Sans für Fließtext.
