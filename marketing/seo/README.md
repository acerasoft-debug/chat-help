# ChatHelp — SEO-Paket

Deploy-fertige SEO-Bausteine für chat-help.com. Drei Content-Säulen:
**Schreiben erstellen (Widerspruch/Antrag)** · **geprüfte, korrekte Informationen** ·
**Versand per Fax, Brief & Einschreiben**.

## Dateien

| Datei | Was | Wohin |
|---|---|---|
| `head-de.html … head-it.html` | Kompletter SEO-`<head>` je Sprache (**de, en, fr, tr, ru, es, it**): Title, Meta, OG, Twitter, JSON-LD (Organization, WebSite, SoftwareApplication, Service, **FAQPage**) + **hreflang**-Cluster | je in den `<head>` der Sprach-URL (`/`, `/en/`, `/fr/` …) |
| `og-de.png … og-it.png` | Share-Bild 1200×630 je Sprache (Open Graph/Twitter) | ins Web-Root: `chat-help.com/og-XX.png` |
| `seo-multilang.md` | **Mehrsprachiges SEO korrekt:** URL-je-Sprache + hreflang, IP nur für Menschen (nicht Bots), fertige PHP-Snippets | Anleitung |
| `seo-strategy.md` | Keyword-Map, Seitenarchitektur, On-Page-Checkliste, Content-Ideen, Technik | Referenz/Fahrplan |
| `robots.txt` | Crawler-Regeln (Dev-/Diagnose-Skripte ausschließen) | Web-Root: `chat-help.com/robots.txt` |
| `sitemap.xml` | Sitemap-Vorlage mit hreflang-Alternates | Web-Root: `chat-help.com/sitemap.xml` |
| `google-play-button.html` | „Get it on Google Play"-Button (HTML, 4 Varianten: dunkel/gold · DE/EN), verlinkt die App | Snippet in Seite/Footer/CTA einfügen |
| `google-play-badge-*.png` | Fertige Button-Bilder (transparent, Fallback) | z. B. Bio-Links, E-Mails |

**App:** ChatHelp ist bei Google Play – `com.acerasoft.chathelp`
(<https://play.google.com/store/apps/details?id=com.acerasoft.chathelp>). Der Link ist in
`head-de.html`/`head-en.html` als `sameAs` + `installUrl`/`downloadUrl` hinterlegt (App-SEO).

## In 6 Schritten live

1. **Startseite mit Inhalt**: `chat-help.com/` als echte HTML-Seite ausliefern (H1, Text, FAQ) –
   **nicht** sofort auf `/chat/` weiterleiten (aktuell der größte SEO-Blocker, siehe Strategie §0).
2. `head-de.html` in den `<head>` der Startseite kopieren (Title/Meta ggf. je Seite anpassen).
3. `og-de.png` und `og-en.png` ins Web-Root hochladen (URLs im `<head>` prüfen).
4. `robots.txt` und `sitemap.xml` ins Web-Root legen.
5. In **Google Search Console** (+ Bing Webmaster Tools) die Domain verifizieren und die Sitemap
   einreichen.
6. Strukturierte Daten testen: <https://validator.schema.org/> und der Rich-Results-Test.

## Wichtig

- Titel/Descriptions **pro Seite einzigartig** halten (Vorlagen in `seo-strategy.md`).
- **Keine erfundenen Bewertungen** (`aggregateRating`) im JSON-LD – nur echte Werte.
- Preise erst als `Product/Offer`-JSON-LD ergänzen, wenn die realen Preise feststehen
  (Vorlage in `seo-strategy.md` §6).
- `sameAs` (Instagram u. a.) und den Logo-Pfad in `head-*.html` auf die echten URLs anpassen.
