/* ChatHelp hero video: hero-anim.html → kareler (Playwright) → ffmpeg (webm/mp4/poster)
   Kullanım: node site/media-src/render.js [fps] [saniye] [dil|all|stills]   (varsayılan 30 fps, 12 s, de)
     dil   → site/media/hero-<dil>.webm|.mp4|-poster.jpg   (de ayrıca hero.webm/.mp4/hero-poster.jpg = eski adlar, geriye uyumlu)
     all   → 13 dilin hepsi sırayla
     stills→ yalnız kontrol kareleri (t = 2.6 / 6.2 / 9.8 s) → $FRAMES_DIR/still-<dil>-<t>.png
   Playwright yolu: PLAYWRIGHT_PATH (varsayılan 'playwright', yerelde /opt/node22/lib/node_modules/playwright).            */
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('fs'), path = require('path'), { execSync } = require('child_process');
const FPS = +(process.argv[2] || 30), DUR = +(process.argv[3] || 12), WHAT = process.argv[4] || 'de';
const LANGS = ['de', 'fr', 'tr', 'az', 'es', 'it', 'en', 'ru', 'nl', 'sv', 'no', 'fi', 'ja'];
const SRC = path.resolve(__dirname, 'hero-anim.html');
const OUT = path.resolve(__dirname, '..', 'media');
const FR0 = path.resolve(process.env.FRAMES_DIR || '/tmp/ch-hero-frames');
const FFMPEG = process.env.FFMPEG || 'ffmpeg';
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1600, height: 900 }, deviceScaleFactor: 1 });
  fs.mkdirSync(OUT, { recursive: true }); fs.mkdirSync(FR0, { recursive: true });
  const open = async (l) => { await p.goto('file://' + SRC + '?still&lang=' + l); await p.evaluate(() => document.fonts ? document.fonts.ready : null); };
  if (WHAT === 'stills') {
    for (const l of LANGS) { await open(l); for (const t of [2.6, 6.2, 9.8]) { await p.evaluate(x => window.seek(x), t); await p.screenshot({ path: path.join(FR0, `still-${l}-${t}.png`) }); } }
    await b.close(); console.log('stills →', FR0); return;
  }
  for (const l of WHAT === 'all' ? LANGS : [WHAT]) {
    const FR = path.join(FR0, l); fs.rmSync(FR, { recursive: true, force: true }); fs.mkdirSync(FR, { recursive: true });
    await open(l);
    const n = FPS * DUR; const t0 = Date.now();
    for (let i = 0; i < n; i++) {
      await p.evaluate(t => window.seek(t), i / FPS);
      await p.screenshot({ path: path.join(FR, `f${String(i).padStart(4, '0')}.png`), type: 'png' });
      if (i % 120 === 0) console.log(`${l} frame ${i}/${n}  ${(Date.now() - t0) / 1000}s`);
    }
    const inp = `-framerate ${FPS} -i ${FR}/f%04d.png`, base = `${OUT}/hero-${l}`;
    execSync(`${FFMPEG} -y -loglevel error ${inp} -c:v libvpx-vp9 -b:v 0 -crf 33 -row-mt 1 -pix_fmt yuv420p -an ${base}.webm`, { stdio: 'inherit' });
    execSync(`${FFMPEG} -y -loglevel error ${inp} -c:v libx264 -crf 23 -preset slow -profile:v high -pix_fmt yuv420p -movflags +faststart -an ${base}.mp4`, { stdio: 'inherit' });
    execSync(`${FFMPEG} -y -loglevel error -i ${FR}/f0075.png -q:v 3 ${base}-poster.jpg`, { stdio: 'inherit' });
    if (l === 'de') for (const [a, z] of [['.webm', 'hero.webm'], ['.mp4', 'hero.mp4'], ['-poster.jpg', 'hero-poster.jpg']]) fs.copyFileSync(base + a, path.join(OUT, z));
    for (const f of ['.webm', '.mp4', '-poster.jpg']) console.log(`hero-${l}${f}`, (fs.statSync(base + f).size / 1024).toFixed(0) + ' KB');
    fs.rmSync(FR, { recursive: true, force: true });
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
