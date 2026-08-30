# ChatHelp — SEO live schalten (Deploy)

**Neu:** `chat-help.com/` ist jetzt eine echte **Landingpage** (`index.html`) statt einer
Sofort-Weiterleitung auf `/chat/`. Damit sieht Google endlich indexierbaren Inhalt +
alle Meta-Tags, das OG-Bild und die FAQ (strukturierte Daten). Der Button
„Jetzt starten" führt weiter mit **einem Klick** in die App (`/chat/`).

> Von der Sandbox aus ist der Live-Host nicht erreichbar → **auf deinem Rechner /
> im SFTP-Client (FileZilla, Panel-Dateimanager) ausführen.**

## Die Dateien ins Web-Root laden (dorthin, wo `index.php` liegt)

| Datei | Aus | Zweck |
|---|---|---|
| `index.html` | Repo-Root | **Landingpage** mit komplettem SEO-`<head>` + Inhalt |
| `index.php` | Repo-Root | liefert `index.html` mit Status 200 aus (kein Redirect mehr) |
| `og-de.png` | `marketing/seo/` | Share-Bild (Facebook/WhatsApp/X). URL steht im `<head>` |
| `robots.txt` | `marketing/seo/` | Crawler-Regeln (falls noch nicht live) |
| `sitemap.xml` | `marketing/seo/` | Sitemap (falls noch nicht live) |

**Wichtig:** `index.php` **und** `index.html` beide hochladen. nginx führt `index.php`
zuerst aus — die neue `index.php` gibt jetzt die Landingpage aus (die alte hat auf
`/chat/` weitergeleitet). Ohne das Ersetzen der `index.php` bleibt die Weiterleitung aktiv.

### Per Terminal (`lftp`, Platzhalter ersetzen; fragt nach Passwort)
```bash
lftp -u DEIN_SFTP_USER sftp://DEIN_SFTP_HOST -e "
  set sftp:auto-confirm yes;
  cd /pfad/zum/webroot;
  put index.html;
  put index.php;
  put marketing/seo/og-de.png;
  put marketing/seo/robots.txt;
  put marketing/seo/sitemap.xml;
  bye"
```
### Oder per `scp` (bei SSH-Zugang)
```bash
scp index.html index.php DEIN_SFTP_USER@DEIN_SFTP_HOST:/pfad/zum/webroot/
scp marketing/seo/og-de.png marketing/seo/robots.txt marketing/seo/sitemap.xml \
    DEIN_SFTP_USER@DEIN_SFTP_HOST:/pfad/zum/webroot/
```

## Prüfen (im Browser oder Terminal auf deinem Rechner)
```bash
curl -s  https://chat-help.com/            | grep -o '<title>[^<]*'   # Titel muss erscheinen
curl -sI https://chat-help.com/            | head -1                  # 200 OK (kein 302!)
curl -sI https://chat-help.com/og-de.png   | head -1                  # 200 OK, image/png
```
- **Strg+U** auf der Startseite → `og:title` und `application/ld+json` müssen auftauchen.
- OG-Bild testen: <https://www.opengraph.xyz/> → `https://chat-help.com/` eingeben.
- Rich Results: <https://search.google.com/test/rich-results> → Startseite prüfen (FAQ + Organization).
- **Google Search Console** → Sitemap `https://chat-help.com/sitemap.xml` einreichen,
  dann „URL-Prüfung" für `https://chat-help.com/` → **Indexierung beantragen**.

## Später: weitere Sprachen (en/fr/tr/ru/es/it)
Die `head-*.html` und `og-*.png` für die anderen Sprachen liegen fertig in `marketing/seo/`.
Sobald die Sprach-URLs (`/en/`, `/fr/` …) als echte Seiten existieren:
`head-XX.html` in deren `<head>`, `og-XX.png` ins Web-Root, und die **hreflang**-Zeilen
in `index.html` wieder um die dann existierenden Sprachen ergänzen
(siehe `marketing/seo/seo-multilang.md`). Erst live schalten, wenn die Seiten wirklich
erreichbar sind — sonst zeigt hreflang auf 404.
