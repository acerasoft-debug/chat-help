import { chromium } from 'playwright-core';
import { mkdirSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const URL = 'file://' + path.join(__dir, 'carousel.html');
const outDir = path.join(__dir, 'carousel');
rmSync(outDir, { recursive: true, force: true }); mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium',
  args: ['--no-sandbox', '--force-color-profile=srgb', '--font-render-hinting=none', '--hide-scrollbars'],
});
const page = await browser.newPage({ viewport: { width: 1160, height: 1400 }, deviceScaleFactor: 1 });
await page.goto(URL, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
await page.waitForFunction(() => window.__ready === true);
const slides = page.locator('.slide');
const n = await slides.count();
for (let i = 0; i < n; i++) {
  await slides.nth(i).screenshot({ path: path.join(outDir, `slide${i + 1}.png`) });
}
console.log('carousel slides:', n, '→', outDir);
await browser.close();
