# ChatHelp — SEO live schalten (Deploy)

> Der PHP-Installer wurde entfernt: ein großes PHP mit base64-Inhalt und dem Namen
> „install…" wird von Server-Sicherheit/WAF fast immer als Backdoor blockiert (403).
> Deshalb der zuverlässige Weg: **die 4 statischen Dateien direkt per SFTP hochladen**
> (kein PHP → kein WAF). Von der Sandbox aus ist der Live-Host nicht erreichbar,
> also **auf deinem Rechner / im SFTP-Client** ausführen.

## 1) Diese 4 Dateien ins Web-Root laden

Aus `marketing/seo/`:
- `robots.txt`
- `sitemap.xml`
- `og-de.png`
- `og-en.png`

**Am einfachsten (FileZilla/Panel):** die 4 Dateien einfach ins Web-Root ziehen
(dorthin, wo auch `index.html` liegt).

**Oder per Terminal (`lftp`, Platzhalter ersetzen; fragt nach Passwort):**
```bash
lftp -u DEIN_SFTP_USER sftp://DEIN_SFTP_HOST -e "
  set sftp:auto-confirm yes;
  cd /pfad/zum/webroot;
  put marketing/seo/robots.txt;
  put marketing/seo/sitemap.xml;
  put marketing/seo/og-de.png;
  put marketing/seo/og-en.png;
  bye"
```
**Oder per `scp` (bei SSH-Zugang):**
```bash
scp marketing/seo/robots.txt marketing/seo/sitemap.xml \
    marketing/seo/og-de.png marketing/seo/og-en.png \
    DEIN_SFTP_USER@DEIN_SFTP_HOST:/pfad/zum/webroot/
```

## 2) Falls schon hochgeladen: `install-seo.php` vom Server LÖSCHEN
Sie ist blockiert und sollte aus Sicherheitsgründen nicht liegen bleiben.

## 3) Meta-Tags in die Startseite (einmaliger Code-Schritt)
Den kompletten Inhalt von `marketing/seo/head-de.html` in den `<head>` der Startseite
einfügen (englisch: `head-en.html` in `<head>` von `/en/`).
**Wichtigster SEO-Schritt:** `chat-help.com/` sollte echten Inhalt ausliefern statt sofort
auf `/chat/` weiterzuleiten (siehe `marketing/seo/seo-strategy.md` §0).

## 4) Prüfen (im Browser oder Terminal auf deinem Rechner)
```bash
curl -sI https://chat-help.com/robots.txt | head -1   # 200 OK
curl -sI https://chat-help.com/og-de.png | head -1    # 200 OK, image/png
curl -s  https://chat-help.com/sitemap.xml | head -3
```
Danach: Google Search Console → Sitemap `https://chat-help.com/sitemap.xml` einreichen.
