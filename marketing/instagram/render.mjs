import { chromium } from 'playwright-core';
import ffmpegPath from 'ffmpeg-static';
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const SCENE = 'file://' + path.join(__dir, 'scene.html') + '?capture=1';
const EXE = '/opt/pw-browsers/chromium';
const W = 1080, H = 1920, FPS = 30, DUR = 40000;

const mode = process.argv[2] || 'full';
const outName = process.argv[3] || 'chathelp-reel.mp4';

async function main() {
  const browser = await chromium.launch({
    executablePath: EXE,
    args: ['--no-sandbox', '--force-color-profile=srgb', '--disable-lcd-text', '--font-render-hinting=none', '--hide-scrollbars'],
  });
  const page = await browser.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
  await page.goto(SCENE, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForFunction(() => typeof window.renderFrame === 'function');

  const clip = { x: 0, y: 0, width: W, height: H };

  if (mode === 'test') {
    const dir = path.join(__dir, 'test');
    rmSync(dir, { recursive: true, force: true }); mkdirSync(dir, { recursive: true });
    const keys = [1600, 5400, 13200, 22400, 25400, 26400, 27400, 28800, 32600, 34200, 37800];
    for (const t of keys) {
      await page.evaluate((tt) => window.renderFrame(tt), t);
      await page.screenshot({ path: path.join(dir, `t${String(t).padStart(5,'0')}.png`), clip });
    }
    const err = await page.evaluate(() => window.__err || null);
    console.log('TEST frames written to', dir, '| err:', err);
    await browser.close();
    return;
  }

  // FULL
  const frameDir = path.join(__dir, 'frames');
  rmSync(frameDir, { recursive: true, force: true }); mkdirSync(frameDir, { recursive: true });
  const total = Math.round(DUR / 1000 * FPS);
  const t0 = Date.now();
  for (let i = 0; i < total; i++) {
    const t = i * (1000 / FPS);
    await page.evaluate((tt) => window.renderFrame(tt), t);
    await page.screenshot({ path: path.join(frameDir, `f${String(i).padStart(5,'0')}.png`), clip });
    if (i % 60 === 0) console.log(`frame ${i}/${total} (${((Date.now()-t0)/1000).toFixed(0)}s)`);
  }
  const err = await page.evaluate(() => window.__err || null);
  console.log('render error state:', err);
  await browser.close();

  // ENCODE
  const out = path.join(__dir, outName);
  if (existsSync(out)) rmSync(out);
  const args = [
    '-y', '-framerate', String(FPS),
    '-i', path.join(frameDir, 'f%05d.png'),
    '-c:v', 'libx264', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
    '-preset', 'slow', '-crf', '18',
    '-movflags', '+faststart',
    '-r', String(FPS),
    out,
  ];
  console.log('encoding →', out);
  await new Promise((res, rej) => {
    const ff = spawn(ffmpegPath, args, { stdio: ['ignore', 'ignore', 'inherit'] });
    ff.on('close', c => c === 0 ? res() : rej(new Error('ffmpeg exit ' + c)));
  });
  console.log('DONE', out);
}
main().catch(e => { console.error(e); process.exit(1); });
