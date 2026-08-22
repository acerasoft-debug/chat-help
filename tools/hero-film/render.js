/* Render the homepage hero film to a numbered JPEG sequence.
 *
 * Frames are driven by setT(t) rather than by wall-clock playback, so the render is
 * deterministic: the same catalogue always yields the same clip, and frame N lines up
 * exactly with frame 0 (see film.html -- every property is a function of the plate's
 * screen position, never of raw time, which is what makes the loop seam invisible).
 *
 *   node render.js <outDir> <frameCount>
 */
const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const UP   = path.join(ROOT, 'vestra/uploads');
const OUT    = process.argv[2];
const FRAMES = parseInt(process.argv[3] || '480', 10);

/* Sixteen pieces, hand-picked rather than taken off the top of the catalogue, on
 * three rules:
 *   - hue rotates between neighbours, so no two adjacent plates read as one block;
 *   - silhouette alternates (polo / hoodie / sweat / tee), so the rail is not a row
 *     of the same outline in different colours;
 *   - nothing from index.php's $HERO_SKIP. The operator took `rl/csf-polo` and
 *     `burberry-8039175` out of the homepage by name, and a clip that put them back
 *     would quietly undo that.
 * Near-black and pure-white pieces are mostly avoided: on a dark stage the first
 * disappears and the second is all sweep and no garment.
 */
const PICKS = [
  'lacoste/l1212-blue.jpg',
  'dsquared/dsq-101220.png',
  'lac-sweat/lacoste-sweat-lightpink.png',
  'lacoste/l1212-green.jpg',
  'dg/101201.png',
  'lacoste/l1212-bordeaux.jpg',
  'lac-hoodie/lacoste-hoodie-blue.png',
  'lac-sweat/lacoste-sweat-green.png',
  'dg/101206.png',
  'lacoste/l1212-lightblue.png',
  'lac-tee/lacoste-trim-tshirt-1.png',
  'lac-hoodie/lacoste-hoodie-green.png',
  'dsquared/dsq-101211.png',
  'lac-sweat/lacoste-sweat-beige.png',
  'lac-trim/lacoste-trim-polo-2.png',
  'lac-sweat/lacoste-sweat-blue.png',
];

(async () => {
  const missing = PICKS.filter(p => !fs.existsSync(path.join(UP, p)));
  if (missing.length) {
    /* Fail loudly. A silently short rail still renders and still loops -- it just
       quietly drops pieces, which is the kind of defect nobody notices for months. */
    throw new Error('missing catalogue images:\n  ' + missing.join('\n  '));
  }
  fs.mkdirSync(OUT, { recursive: true });

  const imgs = PICKS.map(p => 'file://' + path.join(UP, p));
  const tmp  = path.join(OUT, '_film.rendered.html');
  fs.writeFileSync(tmp, fs.readFileSync(path.join(__dirname, 'film.html'), 'utf8')
                          .replace('window.__IMGS__ || []', JSON.stringify(imgs)));

  const browser = await chromium.launch({ args: ['--allow-file-access-from-files'] });
  const page = await browser.newPage({ viewport: { width: 1600, height: 900 },
                                       deviceScaleFactor: 1 });
  await page.goto('file://' + tmp);
  await page.waitForTimeout(2500);            // let every packshot decode
  const stage = await page.$('#stage');

  for (let i = 0; i < FRAMES; i++) {
    await page.evaluate(t => window.setT(t), i / FRAMES);
    await stage.screenshot({ path: path.join(OUT, String(i).padStart(4, '0') + '.jpg'),
                             type: 'jpeg', quality: 92 });
    if (i % 60 === 0) console.log('  frame', i, '/', FRAMES);
  }
  await browser.close();
  fs.unlinkSync(tmp);
  console.log('rendered', FRAMES, 'frames ->', OUT);
})().catch(e => { console.error(e); process.exit(1); });
