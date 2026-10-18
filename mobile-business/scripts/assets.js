#!/usr/bin/env node
/**
 * ChatHelp Business – App-Symbole und Splash erzeugen
 * ===================================================
 *   node scripts/assets.js [android|ios|all]
 * 1. rendert die Vektorvorlagen aus assets/src/*.svg mit sharp (kommt mit @capacitor/assets) zu PNGs in assets/:
 *      icon-only.png (1024, ohne Transparenz – Apple), icon-foreground.png + icon-background.png (Android Adaptive Icon),
 *      splash.png / splash-dark.png (2732×2732)
 * 2. ruft `capacitor-assets generate` auf → alle Größen für Android (mipmap-*, drawable-*) bzw. iOS (Assets.xcassets).
 * Das Benachrichtigungs-Symbol (ic_stat_chathelp, weiß/monochrom) legt scripts/patch-android.js als Vektor an.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const SRC = path.join(ROOT, 'assets', 'src');
const OUT = path.join(ROOT, 'assets');
const target = (process.argv[2] || 'all').toLowerCase();

let sharp;
try { sharp = require('sharp'); } catch (e) {
  console.error('✗ sharp fehlt – bitte zuerst `npm install` im Ordner mobile-business ausführen (kommt mit @capacitor/assets).');
  process.exit(1);
}

const jobs = [
  ['icon.svg', 'icon-only.png', 1024, true],
  ['icon-foreground.svg', 'icon-foreground.png', 1024, false],
  ['icon-background.svg', 'icon-background.png', 1024, true],
  ['splash.svg', 'splash.png', 2732, true],
  ['splash-dark.svg', 'splash-dark.png', 2732, true]
];

(async () => {
  for (const [src, dst, size, flatten] of jobs) {
    const svg = fs.readFileSync(path.join(SRC, src));
    let img = sharp(svg, { density: Math.ceil(72 * size / 1024) }).resize(size, size);
    if (flatten) img = img.flatten({ background: src.startsWith('splash-dark') ? '#0f1222' : (src.startsWith('splash') ? '#f6f7fb' : '#141831') });
    await img.png({ compressionLevel: 9 }).toFile(path.join(OUT, dst));
    console.log('✓ assets/' + dst + ' (' + size + '×' + size + ')');
  }
  if (process.argv.includes('--png-only')) return;
  const args = ['capacitor-assets', 'generate', '--assetPath', 'assets',
    '--iconBackgroundColor', '#141831', '--iconBackgroundColorDark', '#141831',
    '--splashBackgroundColor', '#f6f7fb', '--splashBackgroundColorDark', '#0f1222'];
  if (target === 'android') args.push('--android');
  else if (target === 'ios') args.push('--ios');
  else args.push('--android', '--ios');
  const want = target === 'all' ? ['android', 'ios'] : [target];
  const missing = want.filter((p) => !fs.existsSync(path.join(ROOT, p)));
  if (missing.length === want.length) { console.log('ℹ Plattform-Ordner fehlen (' + missing.join(', ') + ') – nur PNGs erzeugt. Erst `npm run setup:android` bzw. `setup:ios`.'); return; }
  execFileSync('npx', args, { cwd: ROOT, stdio: 'inherit' });
  console.log('✓ Symbole und Splash für ' + want.join(' + ') + ' erzeugt');
})().catch((e) => { console.error('✗ ' + (e && e.message || e)); process.exit(1); });
