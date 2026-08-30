# ChatHelp Reel — Voiceover-Skript (Deutsch, weibliche Stimme)

Zeitgenau auf die 44-Sekunden-Reel abgestimmt (`chathelp-reel.mp4`).
Sprechstil: ruhig, seriös, vertrauensvoll — „Werbespot-Qualität", nicht hektisch.

## Zeitleiste (Szene → Zeit → Text)

| # | Szene | Einsatz (s) | Text |
|---|---|---|---|
| 1 | Problem | 03.4 | „Ein amtliches Schreiben. Kompliziert — und mit einer Frist." |
| 2 | Schildern | 08.2 | „Mit ChatHelp schildern Sie Ihr Anliegen in eigenen Worten und laden das Dokument hoch. Die KI liest alles." |
| 3 | Analyse | 14.8 | „Jetzt arbeiten mehrere künstliche Intelligenzen zusammen: Sie holen echte Fakten aus geprüften Rechtsquellen — und eine zweite KI kontrolliert jede Angabe." |
| 4 | Unterschied | 24.4 | „Der Unterschied zu einer normalen KI? Geprüft — statt geraten." |
| 5 | Ergebnis | 30.3 | „In Sekunden entsteht Ihr fertiges, rechtssicheres Dokument." |
| 6 | Versand | 35.4 | „Den Versand übernehmen wir: per Fax, Brief oder Einschreiben." |
| 7 | Outro | 40.9 | „ChatHelp. Recht, einfach, digital." |

## Fließtext (am Stück, falls eine durchgehende Aufnahme gewünscht ist)

> Ein amtliches Schreiben. Kompliziert — und mit einer Frist.
> Mit ChatHelp schildern Sie Ihr Anliegen in eigenen Worten und laden das Dokument hoch.
> Die KI liest alles. Jetzt arbeiten mehrere künstliche Intelligenzen zusammen: sie holen
> echte Fakten aus geprüften Rechtsquellen — und eine zweite KI kontrolliert jede Angabe.
> Der Unterschied zu einer normalen KI? Geprüft — statt geraten.
> In Sekunden entsteht Ihr fertiges, rechtssicheres Dokument.
> Den Versand übernehmen wir: per Fax, Brief oder Einschreiben.
> ChatHelp. Recht, einfach, digital.

## Empfohlene weibliche Stimmen (Werbequalität)

- **Google Cloud TTS** (in dieser Umgebung erreichbar): `de-DE-Neural2-C` oder Studio-Stimme
  `de-DE-Studio-C` — sehr natürlich, für Werbung geeignet.
- **Azure Neural TTS**: `de-DE-KatjaNeural` oder `de-DE-SeraphinaMultilingualNeural`.
- **ElevenLabs** (Multilingual v2): eine deutsche weibliche Stimme, Stability ~50 %, Style ~30 %.
- Oder ein **menschlicher Sprecher** (Fiverr / voice123) mit diesem Skript.

## So kommt der Ton ins Video

Sobald eine Audiodatei vorliegt (ein durchgehendes 44-Sekunden-WAV/MP3 **oder** sieben
Einzelclips je Szene), wird sie lippen-/szenengenau unter das Video gelegt:

```bash
# durchgehende Spur:
ffmpeg -i chathelp-reel.mp4 -i voice.wav -c:v copy -c:a aac -b:a 160k -shortest \
       -movflags +faststart chathelp-reel-vo.mp4

# oder Einzelclips exakt auf die Einsatzzeiten oben (adelay in ms) legen und mischen.
```

> Hinweis: In dieser Session ist der Modell-Download für lokale TTS (z. B. Piper) durch die
> Netzwerk-Policy gesperrt (HuggingFace → 403), und ElevenLabs/OpenAI sind nicht erreichbar.
> Erreichbar ist Google Cloud TTS — mit einem Google-TTS-API-Key kann die Stimme direkt hier
> erzeugt und gemuxt werden. Alternativ extern erzeugen und die Audiodatei bereitstellen.
