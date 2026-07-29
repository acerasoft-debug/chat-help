# ChatHelp — SEO live schalten (Deploy)

> Hinweis: Von der Entwicklungs-Umgebung (Sandbox) aus lässt sich der Live-Server **nicht**
> erreichen (keine Deploy-Pipeline, keine Server-Zugangsdaten, gesperrter Ausgang).
> Deploy daher **auf deinem Rechner / Server** ausführen. Zwei Wege — nimm einen.

---

## Weg A — 1-Klick per PHP-Installer (wie deine `apply-*.php`)

1. `deploy/install-seo.php` per SFTP/Panel ins **Web-Root** hochladen
   (schreibt automatisch ins `DOCUMENT_ROOT`; alternativ nach `/chat/` legen).
2. Im Browser öffnen:

   ```
   https://chat-help.com/install-seo.php?go=DEPLOY
   ```

   → schreibt `robots.txt`, `sitemap.xml`, `og-de.png`, `og-en.png` ins Web-Root.
3. Die Datei danach **wieder löschen** (`install-seo.php`).

---

## Weg B — direkt per Terminal (SFTP, auf deinem Rechner)

Repo lokal geklont, dann (Platzhalter ersetzen; fragt nach Passwort):

```bash
# robots.txt, sitemap.xml, og-de.png, og-en.png ins Web-Root spiegeln
lftp -u DEIN_SFTP_USER sftp://DEIN_SFTP_HOST -e "
  set sftp:auto-confirm yes;
  cd /pfad/zum/webroot;
  put marketing/seo/robots.txt;
  put marketing/seo/sitemap.xml;
  put marketing/seo/og-de.png;
  put marketing/seo/og-en.png;
  bye"
```

Alternativ mit `scp` (falls SSH-Zugang):

```bash
scp marketing/seo/robots.txt marketing/seo/sitemap.xml \
    marketing/seo/og-de.png marketing/seo/og-en.png \
    DEIN_SFTP_USER@DEIN_SFTP_HOST:/pfad/zum/webroot/
```

---

## Danach (einmalig, Code-Schritt) — die eigentlichen Meta-Tags

Die Datei-Uploads oben liefern OG-Bild, robots & sitemap. Damit Google Title/Description/FAQ
sieht, müssen noch die **`<head>`-Tags** aus `marketing/seo/head-de.html` in die **Startseite**:

1. Den kompletten Inhalt von `marketing/seo/head-de.html` in den `<head>` der Startseite kopieren.
   (Englische Seite: `head-en.html` in `<head>` von `/en/`.)
2. **Wichtigster SEO-Schritt:** Die Startseite `chat-help.com/` sollte **echten Inhalt**
   ausliefern (H1, Text, FAQ) statt sofort auf `/chat/` weiterzuleiten (siehe `marketing/seo/seo-strategy.md` §0).

## Prüfen (nach dem Deploy)

```bash
curl -sI https://chat-help.com/robots.txt        | head -1
curl -sI https://chat-help.com/og-de.png         | head -1
curl -s  https://chat-help.com/sitemap.xml       | head -3
```
Danach: Google Search Console → Sitemap einreichen; Rich-Results-Test für die Startseite.
