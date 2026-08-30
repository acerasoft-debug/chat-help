# ChatHelp — Mehrsprachiges SEO (7 Sprachen) + IP/Geo — richtig gemacht

Unterstützte Sprachen (aus `geo.php`): **de, en, fr, tr, ru, es, it**.
Für jede Sprache liegt ein fertiger `<head>` bereit: `head-de.html … head-it.html`,
je mit übersetztem Title/Description/OG/JSON-LD (inkl. FAQ) und **hreflang**-Cluster.
Passende Share-Bilder: `og-de.png … og-it.png` (1200×630).

## ⚠️ Der wichtigste Punkt: NICHT per IP dieselbe URL umschreiben

Wenn `chat-help.com/` je nach IP andere Sprache/Meta ausliefert, sieht **Googlebot**
(crawlt fast nur aus den USA) immer **nur eine** Version → Deutsch/Französisch/Türkisch werden
**nie indexiert**, und es droht **Cloaking**. Deshalb:

### Richtig: eine URL pro Sprache + hreflang
| Sprache | URL |
|---|---|
| de (Standard, x-default) | `https://chat-help.com/` |
| en | `https://chat-help.com/en/` |
| fr | `https://chat-help.com/fr/` |
| tr | `https://chat-help.com/tr/` |
| ru | `https://chat-help.com/ru/` |
| es | `https://chat-help.com/es/` |
| it | `https://chat-help.com/it/` |

Jede dieser URLs liefert **serverseitig** den passenden `<head>` (die `head-XX.html`) und den
Inhalt in der jeweiligen Sprache. Der hreflang-Cluster (in jedem `<head>` enthalten) sagt Google,
welche Version für welche Sprache gilt. So wird **jede** Sprache indexiert und rankt.

### IP nur für echte Besucher (nicht für Bots)
Die IP-Erkennung (`geo.php` / Cloudflare `CF-IPCountry`) darf nur einen **menschlichen**
Erstbesucher von `/` auf seine Sprach-URL leiten — **niemals** Bots, und nur **einmal** (Cookie),
mit **302** (nicht 301).

```php
<?php
/* geo-redirect.php — ganz oben in der Startseite (nur auf "/") einbinden.
   Leitet echte Besucher einmalig auf ihre Sprach-URL; Bots und /xx/ bleiben unberührt. */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = preg_match('#bot|crawl|spider|slurp|bing|google|yandex|baidu|duckduck|facebookexternalhit#i', $ua);
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (!$isBot && $uri === '/' && empty($_COOKIE['ch_lang'])) {
    $cc  = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
    $map = ['GB'=>'en','US'=>'en','NL'=>'en','IE'=>'en',
            'FR'=>'fr','BE'=>'fr','LU'=>'fr',
            'TR'=>'tr','RU'=>'ru','ES'=>'es','IT'=>'it'];   // DE/AT/CH -> bleibt "/"
    if (isset($map[$cc])) {
        setcookie('ch_lang', $map[$cc], time()+31536000, '/');
        header('Location: /'.$map[$cc].'/', true, 302);
        exit;
    }
}
```

### Passenden `<head>` je URL ausliefern (serverseitig)
```php
<?php
/* seo-head.php — im <head> jeder Seite einbinden: gibt die richtige head-XX.html aus. */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$lang = 'de';
foreach (['en','fr','tr','ru','es','it'] as $l) {
    if (preg_match('#^/'.$l.'(/|$|\?)#', $uri)) { $lang = $l; break; }
}
$file = __DIR__ . "/seo/head-$lang.html";   // Pfad an deine Struktur anpassen
if (is_file($file)) readfile($file);
```

## Deploy-Checkliste
1. `head-de.html … head-it.html` in den `<head>` der jeweiligen Sprach-URL einbauen
   (oder per `seo-head.php` automatisch).
2. `og-de.png … og-it.png` ins Web-Root hochladen (URLs stehen in den `<head>`-Dateien).
3. `sitemap.xml` erweitern, **sobald** die Sprach-URLs (`/en/` …) wirklich existieren
   (vorher keine 404-URLs eintragen!). hreflang-Alternates je URL ergänzen.
4. `geo-redirect.php` nur auf `/` einbinden (Bots ausgeschlossen, 302, Cookie).
5. Google Search Console: „Internationale Ausrichtung" prüfen, Rich-Results-Test je Sprach-URL.

> Kurz: Sprache **per URL** (indexierbar) + hreflang; IP nur als **sanfte Weiterleitung** für
> Menschen. So ist „in allen Sprachen, nach IP" umgesetzt — ohne SEO zu verlieren.
