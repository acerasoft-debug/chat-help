/**
 * Brand imagery without stock photos: every card, banner and hero gets a
 * generated composition tied to its subject and accent colour. Motifs are
 * computed (waves, pebbles, droplets, leaves, rings) rather than hand-drawn,
 * share one grain filter and one light palette, and can be replaced by real
 * photography per slug via data/images/<slug>.jpg (see README → Imagery).
 */
const hex = (c) => c.replace('#', '').match(/.{2}/g).map((x) => parseInt(x, 16));
const toHex = (rgb) => '#' + rgb.map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('');
export const mix = (a, b, t) => { const A = hex(a), B = hex(b); return toHex(A.map((v, i) => v + (B[i] - v) * t)); };

/** xorshift so the same slug always renders the same picture. */
function rng(seedStr) {
  let x = seedStr.split('').reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 2166136261) || 7;
  return () => { x ^= x << 13; x >>>= 0; x ^= x >> 17; x ^= x << 5; x >>>= 0; return x / 0xffffffff; };
}

const PAPER = '#faf9f7';
const defs = (id, accent, r) => `<defs>
  <linearGradient id="g${id}" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="${mix(accent, PAPER, 0.86)}"/><stop offset=".55" stop-color="${mix(accent, PAPER, 0.7)}"/><stop offset="1" stop-color="${mix(accent, '#ffffff', 0.5)}"/>
  </linearGradient>
  <radialGradient id="l${id}" cx="${(0.2 + r() * 0.5).toFixed(2)}" cy="0.15" r="0.9">
    <stop offset="0" stop-color="#ffffff" stop-opacity=".85"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
  </radialGradient>
  <filter id="n${id}" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="${Math.floor(r() * 90)}"/><feColorMatrix values="0 0 0 0 0.2 0 0 0 0 0.19 0 0 0 0 0.17 0 0 0 .06 0"/></filter>
  <filter id="s${id}" x="-20%" y="-20%" width="140%" height="160%"><feGaussianBlur stdDeviation="8"/></filter>
</defs>`;
const ground = (id, w, h) => `<rect width="${w}" height="${h}" fill="url(#g${id})"/><rect width="${w}" height="${h}" fill="url(#l${id})"/>`;
const grain = (id, w, h) => `<rect width="${w}" height="${h}" filter="url(#n${id})" opacity=".9"/>`;

/* ---- motifs -------------------------------------------------------------- */
function waves(id, accent, r, w, h) {
  let out = '';
  const n = 6;
  for (let i = 0; i < n; i++) {
    const y0 = h * (0.35 + i * 0.11);
    const amp = h * (0.05 + r() * 0.04);
    const freq = 1.2 + r() * 0.8;
    const phase = r() * Math.PI * 2;
    let d = `M0 ${y0.toFixed(1)}`;
    for (let x = 0; x <= w; x += w / 40) d += ` L${x.toFixed(1)} ${(y0 + Math.sin((x / w) * Math.PI * 2 * freq + phase) * amp).toFixed(1)}`;
    d += ` L${w} ${h} L0 ${h} Z`;
    out += `<path d="${d}" fill="${mix(accent, '#ffffff', 0.55 - i * 0.06)}" opacity="${(0.55 + i * 0.07).toFixed(2)}"/>`;
  }
  return out;
}
function pebbles(id, accent, r, w, h) {
  let out = '';
  const cols = ['#d9d3c9', '#c9c0b4', '#b8aea1', '#e6e1d8'];
  const items = [];
  for (let i = 0; i < 5; i++) {
    const rx = w * (0.14 + r() * 0.12), ry = rx * (0.62 + r() * 0.2);
    const cx = w * (0.18 + i * 0.16 + (r() - 0.5) * 0.06), cy = h * (0.5 + (r() - 0.5) * 0.28) + i * 4;
    items.push({ rx, ry, cx, cy, rot: (r() - 0.5) * 40, c: cols[i % cols.length] });
  }
  items.sort((a, b) => a.cy - b.cy).forEach((p) => {
    out += `<ellipse cx="${p.cx.toFixed(1)}" cy="${(p.cy + p.ry * 0.35).toFixed(1)}" rx="${(p.rx * 1.05).toFixed(1)}" ry="${(p.ry * 0.6).toFixed(1)}" fill="${mix(accent, '#000000', 0.55)}" opacity=".16" filter="url(#s${id})"/>`;
    out += `<ellipse cx="${p.cx.toFixed(1)}" cy="${p.cy.toFixed(1)}" rx="${p.rx.toFixed(1)}" ry="${p.ry.toFixed(1)}" fill="${mix(p.c, accent, 0.25)}" transform="rotate(${p.rot.toFixed(1)} ${p.cx.toFixed(1)} ${p.cy.toFixed(1)})"/>`;
    out += `<ellipse cx="${(p.cx - p.rx * 0.3).toFixed(1)}" cy="${(p.cy - p.ry * 0.35).toFixed(1)}" rx="${(p.rx * 0.45).toFixed(1)}" ry="${(p.ry * 0.28).toFixed(1)}" fill="#ffffff" opacity=".35" transform="rotate(${p.rot.toFixed(1)} ${p.cx.toFixed(1)} ${p.cy.toFixed(1)})"/>`;
  });
  return out;
}
function droplets(id, accent, r, w, h) {
  let out = '';
  for (let i = 0; i < 9; i++) {
    const rad = w * (0.03 + r() * 0.09);
    const cx = w * (0.1 + r() * 0.8), cy = h * (0.15 + r() * 0.7);
    out += `<circle cx="${cx.toFixed(1)}" cy="${(cy + rad * 0.25).toFixed(1)}" r="${(rad * 1.1).toFixed(1)}" fill="${mix(accent, '#000', 0.4)}" opacity=".08" filter="url(#s${id})"/>`;
    out += `<circle cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="${rad.toFixed(1)}" fill="${mix(accent, '#ffffff', 0.35)}" opacity=".85"/>`;
    out += `<circle cx="${(cx - rad * 0.35).toFixed(1)}" cy="${(cy - rad * 0.4).toFixed(1)}" r="${(rad * 0.28).toFixed(1)}" fill="#ffffff" opacity=".9"/>`;
  }
  return out;
}
function rings(id, accent, r, w, h) {
  let out = '';
  const cx = w * (0.55 + (r() - 0.5) * 0.2), cy = h * 0.55;
  for (let i = 0; i < 7; i++) {
    const rr = w * (0.08 + i * 0.075);
    out += `<circle cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="${rr.toFixed(1)}" fill="none" stroke="${mix(accent, '#ffffff', 0.15 + i * 0.05)}" stroke-width="${(1.6 - i * 0.15).toFixed(2)}" opacity="${(0.9 - i * 0.1).toFixed(2)}"/>`;
  }
  out += `<circle cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="${(w * 0.05).toFixed(1)}" fill="${mix(accent, '#ffffff', 0.1)}" opacity=".9"/>`;
  return out;
}
function leaves(id, accent, r, w, h) {
  // Eucalyptus-like sprigs: a stem with alternating rounded leaves, all computed.
  let out = '';
  const sprigs = 3;
  const green = mix('#7a8f7c', accent, 0.25);
  for (let s = 0; s < sprigs; s++) {
    const x0 = w * (0.15 + s * 0.35 + r() * 0.1), y0 = h * (1.05);
    const x1 = x0 + w * (r() - 0.5) * 0.35, y1 = h * (0.1 + r() * 0.2);
    const cx = (x0 + x1) / 2 + w * (r() - 0.5) * 0.3, cy = (y0 + y1) / 2;
    out += `<path d="M${x0.toFixed(1)} ${y0.toFixed(1)} Q${cx.toFixed(1)} ${cy.toFixed(1)} ${x1.toFixed(1)} ${y1.toFixed(1)}" fill="none" stroke="${mix(green, '#000', 0.25)}" stroke-width="1.4" opacity=".7"/>`;
    const n = 9 + Math.floor(r() * 4);
    for (let i = 1; i <= n; i++) {
      const t = i / (n + 1);
      const px = (1 - t) * (1 - t) * x0 + 2 * (1 - t) * t * cx + t * t * x1;
      const py = (1 - t) * (1 - t) * y0 + 2 * (1 - t) * t * cy + t * t * y1;
      const size = w * (0.065 + (1 - t) * 0.06);
      const side = i % 2 ? 1 : -1;
      const ang = -60 * side + (r() - 0.5) * 30;
      out += `<ellipse cx="${(px + side * size * 0.7).toFixed(1)}" cy="${py.toFixed(1)}" rx="${size.toFixed(1)}" ry="${(size * 0.55).toFixed(1)}" fill="${mix(green, '#ffffff', 0.15 + r() * 0.35)}" opacity=".85" transform="rotate(${ang.toFixed(1)} ${px.toFixed(1)} ${py.toFixed(1)})"/>`;
    }
  }
  return out;
}
function skyline(id, accent, r, w, h) {
  // Soft horizon with a sun disc and a distant, abstract roofline.
  let out = `<circle cx="${(w * (0.6 + r() * 0.25)).toFixed(1)}" cy="${(h * 0.42).toFixed(1)}" r="${(w * 0.11).toFixed(1)}" fill="${mix(accent, '#ffffff', 0.25)}" opacity=".9"/>`;
  let d = `M0 ${h}`;
  let x = 0;
  while (x < w) {
    const bw = w * (0.05 + r() * 0.08), bh = h * (0.18 + r() * 0.3);
    d += ` L${x.toFixed(1)} ${(h - bh).toFixed(1)} L${(x + bw).toFixed(1)} ${(h - bh).toFixed(1)}`;
    x += bw;
  }
  d += ` L${w} ${h} Z`;
  out += `<path d="${d}" fill="${mix(accent, '#000000', 0.35)}" opacity=".18"/>`;
  out += `<rect x="0" y="${(h * 0.78).toFixed(1)}" width="${w}" height="${(h * 0.22).toFixed(1)}" fill="${mix(accent, '#ffffff', 0.55)}" opacity=".6"/>`;
  return out;
}

const MOTIFS = { waves, pebbles, droplets, rings, leaves, skyline };
export const motifForCategory = { body: 'waves', therapy: 'pebbles', skincare: 'droplets', signature: 'rings' };

/**
 * art({ motif, accent, seed, w, h, label }) → inline <svg>. `label` becomes a
 * subtle serif monogram — useful for cities — and is omitted when empty.
 */
export function art({ motif = 'rings', accent = '#c9a961', seed = 'lumea', w = 800, h = 600, label = '' } = {}) {
  const r = rng(`${motif}:${seed}`);
  const id = seed.replace(/[^a-z0-9]/gi, '').slice(0, 12) + Math.floor(r() * 1e4).toString(36);
  const draw = MOTIFS[motif] || rings;
  const mono = label ? `<text x="${w * 0.07}" y="${h * 0.9}" font-family="Cormorant Garamond, Georgia, serif" font-size="${h * 0.22}" fill="${mix(accent, '#000', 0.5)}" opacity=".16" font-weight="500">${label}</text>` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">${defs(id, accent, r)}${ground(id, w, h)}${draw(id, accent, r, w, h)}${mono}${grain(id, w, h)}</svg>`;
}
