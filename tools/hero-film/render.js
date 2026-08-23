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

/* Ten pieces, hand-picked rather than taken off the top of the catalogue:
 *   - hue rotates between neighbours, so two pieces in a row never read as one block;
 *   - silhouette alternates (polo / hoodie / sweat / tee), so the lookbook is not the
 *     same outline ten times in different colours;
 *   - nothing from index.php's $HERO_SKIP. The operator took `rl/csf-polo` and
 *     `burberry-8039175` out of the homepage by name, and a clip that put them back
 *     would quietly undo that.
 *
 * Each entry carries the colour of the spotlight that will stand behind it. The value
 * is SAMPLED from the packshot itself (a narrow crop of the garment body, away from
 * the white sweep), not chosen by eye -- so a re-cut with different pieces stays
 * truthful without anyone re-picking swatches.
 * Near-white and near-black pieces are deliberately absent: their sampled colour
 * gives no glow, and on a dark stage they read as a smudge rather than a garment.
 */
const PICKS = [
  ['lacoste/l1212-blue.jpg',            '#21419d'],  // royal blue polo
  ['lac-sweat/lacoste-sweat-beige.png', '#c8a886'],  // camel sweat
  ['lacoste/l1212-bordeaux.jpg',        '#81041c'],  // bordeaux polo
  ['lac-hoodie/lacoste-hoodie-blue.png','#529dda'],  // sky hoodie
  ['lacoste/l1212-green.jpg',           '#255335'],  // forest polo
  ['lac-tee/lacoste-trim-tshirt-1.png', '#821a25'],  // red trim tee
  ['lac-sweat/lacoste-sweat-blue.png',  '#66a8da'],  // light blue sweat
  ['dsquared/dsq-101220.png',           '#7a5f52'],  // DSQ ICON hoodie (print carries it)
  ['lac-hoodie/lacoste-hoodie-green.png','#265331'], // green hoodie
  ['dg/101206.png',                     '#4a4550'],  // D&G graphic tee
];

(async () => {
  const missing = PICKS.filter(([p]) => !fs.existsSync(path.join(UP, p)));
  if (missing.length) {
    /* Fail loudly. A silently short rail still renders and still loops -- it just
       quietly drops pieces, which is the kind of defect nobody notices for months. */
    throw new Error('missing catalogue images:\n  ' + missing.map(m => m[0]).join('\n  '));
  }
  fs.mkdirSync(OUT, { recursive: true });

  const items = PICKS.map(([p, color]) => ({ src: 'file://' + path.join(UP, p), color }));
  const tmp  = path.join(OUT, '_film.rendered.html');
  fs.writeFileSync(tmp, fs.readFileSync(path.join(__dirname, 'film.html'), 'utf8')
                          .replace('window.__ITEMS__ || []', JSON.stringify(items)));

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
