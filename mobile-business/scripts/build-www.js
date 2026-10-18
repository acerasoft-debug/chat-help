#!/usr/bin/env node
/**
 * ChatHelp Business – www bauen (Hülle um die Business-App)
 * ==========================================================
 * Die native App lädt die Live-Seite https://chat-help.com/chat/app.php (capacitor.config.json → server.url).
 * www/ enthält nur die Start-/Offline-Seite (errorPath), die erscheint, wenn der Server nicht erreichbar ist,
 * und von dort automatisch zur App weiterleitet, sobald das Netz zurück ist.
 *
 *   node scripts/build-www.js            Standard: https://chat-help.com/chat/app.php?src=native
 *   CH_APP_URL=https://staging.chat-help.com/chat/app.php?src=native node scripts/build-www.js
 *   node scripts/build-www.js --check    nur prüfen (CI): URL in www/index.html und capacitor.config.json stimmen überein
 *
 * Setzt APP_URL in www/index.html, server.url + allowNavigation in capacitor.config.json und schreibt www/version.json.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const INDEX = path.join(ROOT, 'www', 'index.html');
const CONFIG = path.join(ROOT, 'capacitor.config.json');
const DEFAULT_URL = 'https://chat-help.com/chat/app.php?src=native';
const check = process.argv.includes('--check');

function fail(msg) { console.error('✗ ' + msg); process.exit(1); }

let url = process.env.CH_APP_URL || DEFAULT_URL;
let u;
try { u = new URL(url); } catch (e) { fail('CH_APP_URL ist keine gültige URL: ' + url); }
if (u.protocol !== 'https:') fail('CH_APP_URL muss https:// verwenden (Push, Service Worker und App-Store-Regeln): ' + url);
if (!/\/chat\/app\.php$/.test(u.pathname)) fail('CH_APP_URL muss auf /chat/app.php zeigen: ' + url);
if (!u.searchParams.has('src')) u.searchParams.set('src', 'native');
url = u.toString();

if (!fs.existsSync(INDEX)) fail('www/index.html fehlt');
let html = fs.readFileSync(INDEX, 'utf8');
const re = /var APP_URL = '([^']*)';/;
const m = re.exec(html);
if (!m) fail('www/index.html: Zeile „var APP_URL = …;“ nicht gefunden');
const cfg = JSON.parse(fs.readFileSync(CONFIG, 'utf8'));

if (check) {
  const problems = [];
  if (m[1] !== cfg.server.url) problems.push('APP_URL (' + m[1] + ') ≠ server.url (' + cfg.server.url + ')');
  if (!/^https:\/\//.test(cfg.server.url)) problems.push('server.url ist nicht https');
  if (cfg.server.errorPath !== 'index.html') problems.push('server.errorPath muss index.html sein');
  const host = new URL(cfg.server.url).hostname;
  if (!(cfg.server.allowNavigation || []).some((h) => h === host || (h.startsWith('*.') && host.endsWith(h.slice(1))))) problems.push('allowNavigation enthält ' + host + ' nicht');
  if (problems.length) fail(problems.join('\n  '));
  console.log('✓ www/index.html und capacitor.config.json zeigen auf ' + cfg.server.url);
  process.exit(0);
}

html = html.replace(re, "var APP_URL = '" + url.replace(/'/g, '%27') + "';");
fs.writeFileSync(INDEX, html);
cfg.server.url = url;
const nav = new Set(cfg.server.allowNavigation || []);
nav.add(u.hostname);
cfg.server.allowNavigation = [...nav];
fs.writeFileSync(CONFIG, JSON.stringify(cfg, null, 2) + '\n');
fs.writeFileSync(path.join(ROOT, 'www', 'version.json'), JSON.stringify({ url, built: new Date().toISOString(), version: require(path.join(ROOT, 'package.json')).version }, null, 2) + '\n');
console.log('✓ www gebaut – App lädt ' + url);
