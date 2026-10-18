#!/usr/bin/env node
/**
 * ChatHelp Business – Plattform einrichten (idempotent)
 * =====================================================
 *   node scripts/setup-platform.js android [--no-assets]
 *   node scripts/setup-platform.js ios     [--no-assets]
 * Ablauf: www bauen → `cap add` (nur wenn der Ordner fehlt; iOS mit CocoaPods wegen FirebaseMessaging) →
 * `cap sync` → Symbole/Splash (scripts/assets.js) → native Anpassungen (patch-android.js / patch-ios.js).
 * Die Ordner android/ und ios/ werden NICHT eingecheckt – sie entstehen in CI und lokal immer frisch aus diesem Skript.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const plat = (process.argv[2] || '').toLowerCase();
if (!['android', 'ios'].includes(plat)) { console.error('Aufruf: node scripts/setup-platform.js android|ios [--no-assets]'); process.exit(1); }
if (!fs.existsSync(path.join(ROOT, 'node_modules', '@capacitor', 'cli'))) { console.error('✗ Bitte zuerst `npm install` (bzw. `npm ci`) im Ordner mobile-business ausführen.'); process.exit(1); }

const run = (cmd, args, opt) => { console.log('› ' + cmd + ' ' + args.join(' ')); execFileSync(cmd, args, Object.assign({ cwd: ROOT, stdio: 'inherit' }, opt || {})); };

run('node', ['scripts/build-www.js']);
if (!fs.existsSync(path.join(ROOT, plat))) {
  run('npx', ['cap', 'add', plat].concat(plat === 'ios' ? ['--packagemanager', 'CocoaPods'] : []));
}
/* Erst patchen (Podfile mit FirebaseMessaging), dann sync – so installiert `pod install` alles in einem Lauf */
run('node', ['scripts/patch-' + plat + '.js']);
try { run('npx', ['cap', 'sync', plat]); }
catch (e) {
  if (plat === 'ios' && process.platform !== 'darwin') console.warn('⚠ cap sync ios ohne macOS/CocoaPods nicht vollständig – auf dem Mac `npx cap sync ios` wiederholen.');
  else throw e;
}
if (!process.argv.includes('--no-assets')) {
  try { run('node', ['scripts/assets.js', plat]); }
  catch (e) { console.warn('⚠ Symbole/Splash konnten nicht erzeugt werden – Build läuft mit Standard-Symbolen weiter.'); }
}
run('node', ['scripts/patch-' + plat + '.js']);   // nach sync erneut (sync überschreibt einzelne Dateien wie capacitor.config.json, Patches sind idempotent)
console.log('✓ ' + plat + ' eingerichtet – weiter mit ' + (plat === 'android' ? '`npm run build:apk`' : '`npx cap open ios` bzw. xcodebuild'));
