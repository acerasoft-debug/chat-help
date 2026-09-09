#!/usr/bin/env node
/** Renders src/assets/og.svg → src/assets/og.png (1200×630) for crawlers that ignore SVG. Needs a global Playwright. */
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
const root = execSync('npm root -g').toString().trim();
const { chromium } = createRequire(import.meta.url)(path.join(root, 'playwright'));
const svg = readFileSync(new URL('../dist/assets/og.svg', import.meta.url), 'utf8');
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1200, height: 630 } });
await p.setContent(`<style>html,body{margin:0}</style>${svg.replace('<svg ', '<svg width="1200" height="630" ')}`);
await p.screenshot({ path: new URL('../src/assets/og.png', import.meta.url).pathname, type: 'png' });
await b.close();
console.log('og.png written');
