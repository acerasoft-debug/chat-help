/* ChatHelp hero video: hero-anim.html → kareler (Playwright) → ffmpeg (webm/mp4/poster)
   Kullanım: node site/media-src/render.js [fps] [saniye]   (varsayılan 30 fps, 12 s)            */
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path'), { execSync } = require('child_process');
const FPS = +(process.argv[2] || 30), DUR = +(process.argv[3] || 12);
const SRC = path.resolve(__dirname, 'hero-anim.html');
const OUT = path.resolve(__dirname, '..', 'media');
const FR = path.resolve(process.env.FRAMES_DIR || '/tmp/ch-hero-frames');
fs.rmSync(FR, { recursive: true, force: true }); fs.mkdirSync(FR, { recursive: true }); fs.mkdirSync(OUT, { recursive: true });
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1600, height: 900 }, deviceScaleFactor: 1 });
  await p.goto('file://' + SRC + '?still');
  await p.evaluate(() => document.fonts ? document.fonts.ready : null);
  const n = FPS * DUR; const t0 = Date.now();
  for (let i = 0; i < n; i++) {
    await p.evaluate(t => window.seek(t), i / FPS);
    await p.screenshot({ path: path.join(FR, `f${String(i).padStart(4, '0')}.png`), type: 'png' });
    if (i % 60 === 0) console.log(`frame ${i}/${n}  ${(Date.now() - t0) / 1000}s`);
  }
  await b.close();
  const inp = `-framerate ${FPS} -i ${FR}/f%04d.png`;
  console.log('encode webm…'); execSync(`ffmpeg -y -loglevel error ${inp} -c:v libvpx-vp9 -b:v 0 -crf 33 -row-mt 1 -pix_fmt yuv420p -an ${OUT}/hero.webm`, { stdio: 'inherit' });
  console.log('encode mp4…');  execSync(`ffmpeg -y -loglevel error ${inp} -c:v libx264 -crf 23 -preset slow -profile:v high -pix_fmt yuv420p -movflags +faststart -an ${OUT}/hero.mp4`, { stdio: 'inherit' });
  console.log('poster…');      execSync(`ffmpeg -y -loglevel error -i ${FR}/f0075.png -q:v 3 ${OUT}/hero-poster.jpg`, { stdio: 'inherit' });
  for (const f of ['hero.webm', 'hero.mp4', 'hero-poster.jpg']) console.log(f, (fs.statSync(path.join(OUT, f)).size / 1024).toFixed(0) + ' KB');
})().catch(e => { console.error(e); process.exit(1); });
