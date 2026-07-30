<?php
/**
 * chat-help.com (kök) — Startseite.
 * --------------------------------------------------------
 * FRÜHER: sofortige Weiterleitung auf /chat/  →  größter SEO-Blocker,
 * weil Google an der Startseite keinen indexierbaren Inhalt fand.
 * JETZT: Wir liefern die echte Landingpage (index.html) mit Status 200 aus.
 * nginx führt index.php vor index.html aus, deshalb geben wir die HTML-Datei
 * hier bewusst selbst aus (eine einzige Inhaltsquelle: index.html).
 * Der Button „Jetzt starten" in der Seite führt mit einem Klick in die App (/chat/).
 */
header('Content-Type: text/html; charset=utf-8');
// Kurz cachebar (Startseite ist statisch); CDN/Browser dürfen zwischenspeichern.
header('Cache-Control: public, max-age=600');
readfile(__DIR__ . '/index.html');
