// ============================================================
// Karaköy Güllüoğlu Baklava → Mondimart import (Puppeteer)
// Kurulum: npm install puppeteer   (ilk çalıştırmada otomatik Chrome indirir)
// Çalıştır: SHOPIFY_TOKEN='...' node mondimart-import-gulluoglu.mjs
// Önizleme: DRY=1 node mondimart-import-gulluoglu.mjs
// ============================================================

import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const DRY    = process.env.DRY === '1';
const LIMIT  = process.env.LIMIT ? parseInt(process.env.LIMIT) : 0;
const MARKUP = process.env.MARKUP ? parseFloat(process.env.MARKUP) : 1.20;
const TRY_TO_EUR = process.env.TRY_EUR ? parseFloat(process.env.TRY_EUR) : 1 / 38;

const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH   = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN && !DRY) {
  console.error('❌ SHOPIFY_TOKEN gerekli (veya DRY=1)'); process.exit(1);
}

const delay = ms => new Promise(r => setTimeout(r, ms));

// ── Shopify helper ───────────────────────────────────────────
async function shopify(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, {
        method, headers: SH, body: body ? JSON.stringify(body) : undefined
      });
      if (method === 'DELETE') return { ok: r.ok };
      return await r.json();
    } catch (e) { if (i === 4) throw e; await delay(2000 * i); }
  }
}

// ── Puppeteer scraper ────────────────────────────────────────
async function scrapeBaklavas() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch {
    console.error('\n❌ Puppeteer bulunamadı. Şu komutu çalıştır:\n');
    console.error('   npm install puppeteer\n');
    console.error('Sonra tekrar dene.\n');
    process.exit(1);
  }

  console.log('   🌐 Chrome başlatılıyor...');
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--lang=tr-TR']
  });

  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  await page.setExtraHTTPHeaders({ 'Accept-Language': 'tr-TR,tr;q=0.9' });

  // Ağ isteklerini dinle — API endpoint'i yakala
  let apiProducts = [];
  page.on('response', async (response) => {
    const url = response.url();
    if (!url.includes('karakoygulluoglu')) return;
    if (/(product|urun|item)/i.test(url) && response.headers()['content-type']?.includes('json')) {
      try {
        const data = await response.json();
        const items = data?.data?.products || data?.products || data?.items || data?.data || [];
        if (Array.isArray(items) && items.length > 0) {
          console.log(`   🎯 API endpoint yakalandı: ${url.split('?')[0].slice(-60)}`);
          apiProducts = items;
        }
      } catch {}
    }
  });

  console.log('   📖 Sayfa yükleniyor...');
  try {
    await page.goto('https://www.karakoygulluoglu.com/baklavalar', {
      waitUntil: 'networkidle2', timeout: 30000
    });
  } catch (e) {
    console.log(`   ⚠️  Yükleme hatası: ${e.message} — devam ediyorum`);
  }

  // Ürünlerin yüklenmesi için bekle
  await delay(2000);

  // Scroll — lazy-load ürünler için
  await page.evaluate(async () => {
    for (let i = 0; i < 5; i++) {
      window.scrollBy(0, window.innerHeight);
      await new Promise(r => setTimeout(r, 600));
    }
  });
  await delay(1500);

  // API'den ürün geldiyse önce onu kullan
  if (apiProducts.length > 0) {
    await browser.close();
    return apiProducts.map(p => ({
      name: p.name || p.title || p.product_name || '',
      priceTRY: parseFloat(p.price || p.regularPrice || p.salePrice || 0),
      imageUrl: p.image || p.imageUrl || p.images?.[0]?.src || p.images?.[0] || '',
      url: p.url || p.permalink || p.slug || '',
      description: (p.description || p.shortDescription || '').replace(/<[^>]+>/g,'').slice(0,400),
    }));
  }

  // DOM'dan ürünleri çek
  console.log('   🔍 DOM parse ediliyor...');
  const products = await page.evaluate(() => {
    const results = [];
    const seen = new Set();

    // Tüm olası ürün container seçicileri dene
    const selectors = [
      '[class*="product-card"]',
      '[class*="product-item"]',
      '[class*="ProductCard"]',
      '[class*="product_card"]',
      'article[class*="product"]',
      'li[class*="product"]',
      '.products-grid > div',
      '.product-list > div',
      '[data-product-id]',
      '[data-id][class*="product"]',
    ];

    let cards = [];
    for (const sel of selectors) {
      const found = document.querySelectorAll(sel);
      if (found.length > 2) { cards = Array.from(found); break; }
    }

    for (const card of cards) {
      // Name
      const nameEl = card.querySelector(
        '[class*="title"], [class*="name"], [class*="product-name"], h2, h3, h4, a[class*="name"]'
      );
      const name = nameEl?.textContent?.trim();
      if (!name || seen.has(name)) continue;
      seen.add(name);

      // Price
      const priceEl = card.querySelector('[class*="price"], [class*="Price"]');
      const priceText = priceEl?.textContent || '';
      const priceMatch = priceText.match(/([\d.,]+)\s*(?:TL|₺|TRY)?/);
      const priceTRY = priceMatch ? parseFloat(priceMatch[1].replace(/\./g,'').replace(',','.')) : 0;

      // Image
      const img = card.querySelector('img');
      const imageUrl = img?.src || img?.dataset?.src || img?.dataset?.lazySrc || '';

      // URL
      const link = card.querySelector('a');
      const url = link?.href || '';

      results.push({ name, priceTRY, imageUrl, url, description: '' });
    }

    return results;
  });

  if (products.length === 0) {
    // Son çare: tüm sayfanın HTML'ini kaydet
    const html = await page.content();
    const fs = require('fs');
    fs.writeFileSync('./gulluoglu-debug.html', html);
    console.log(`   📄 Debug HTML kaydedildi (${Math.round(html.length/1024)}KB): gulluoglu-debug.html`);
    console.log('   👉 Lütfen gulluoglu-debug.html içeriğini incele');
  }

  await browser.close();
  return products;
}

// ── Filtre & dönüşüm ─────────────────────────────────────────
function isBoxed(name) {
  return /kutu|box|paket|set|gift|hediye|gift\s*box|özel\s*sunum/i.test(name);
}

function frenchifyName(name) {
  let n = name
    .replace(/\bFıstıklı Baklava\b/gi, 'Baklava aux Pistaches')
    .replace(/\bFıstıklı\b/gi, 'aux Pistaches')
    .replace(/\bCevizli Baklava\b/gi, 'Baklava aux Noix')
    .replace(/\bCevizli\b/gi, 'aux Noix')
    .replace(/\bBülbül Yuvası\b/gi, 'Nid de Rossignol (Baklava)')
    .replace(/\bHavuç Dilimi\b/gi, 'Baklava Tranche de Carotte')
    .replace(/\bŞöbiyet\b/gi, 'Şöbiyet (Feuilleté Kaymak)')
    .replace(/\bBurma Kadayıf\b/gi, 'Burma Kadayıf')
    .replace(/\bKadayıf\b/gi, 'Kadayıf')
    .replace(/\bSade Baklava\b/gi, 'Baklava Nature')
    .replace(/\bSade\b/gi, 'Nature')
    .replace(/\bAntep Fıstıklı\b/gi, 'aux Pistaches d\'Antep')
    .replace(/\bKaymak(?:lı)?\b/gi, 'à la Crème')
    .replace(/\bDürüm\b/gi, 'Roulé')
    .replace(/\bKestaneli\b/gi, 'aux Châtaignes')
    .replace(/\bFındıklı\b/gi, 'aux Noisettes')
    .replace(/\bBadem(?:li)?\b/gi, 'aux Amandes')
    .replace(/\bÇikolata(?:lı)?\b/gi, 'au Chocolat')
    .replace(/\bPortakal(?:lı)?\b/gi, 'à l\'Orange')
    .replace(/\bLimon(?:lu)?\b/gi, 'au Citron')
    .replace(/\bı/g,'ı').replace(/\bİ/g,'İ')
    .trim();

  // "Baklava" kelimesi yoksa ekle
  if (!/baklava/i.test(n) && !/kadayıf|şöbiyet|nid|roulé/i.test(n)) {
    n = 'Baklava ' + n;
  }

  // Karaköy Güllüoğlu prefix
  return `Karaköy Güllüoğlu — ${n}`;
}

// ── Shopify ürün oluştur ─────────────────────────────────────
async function createProduct(p) {
  const priceEUR = (p.priceTRY * TRY_TO_EUR * MARKUP).toFixed(2);
  const d = await shopify('POST', 'products.json', {
    product: {
      title: p.frenchName,
      body_html: p.description
        ? `<p>${p.description}</p><p><em>Karaköy Güllüoğlu — Maître artisan depuis 1820, Istanbul.</em></p>`
        : `<p>Baklava artisanal de la prestigieuse maison Karaköy Güllüoğlu, fondée à Istanbul en 1820. Préparé selon des recettes transmises de génération en génération, avec les meilleurs ingrédients sélectionnés.</p>`,
      vendor: 'Karaköy Güllüoğlu',
      product_type: 'Douceurs Orientales',
      tags: 'baklava,turc,oriental,pâtisserie,artisanal,istanbul,karakoy-gulluoglu',
      status: 'active',
      variants: [{
        price: priceEUR,
        requires_shipping: true,
        taxable: true,
        inventory_management: null,
      }],
    }
  });
  const product = d.product;
  if (!product?.id) throw new Error(JSON.stringify(d).slice(0,200));

  // Koleksiyonlar: Baklava + Snacks & Apéro + Cuisine Turque
  for (const cid of [686917583173, 687825617221, 686915551557]) {
    await shopify('POST', 'collects.json', { collect: { product_id: product.id, collection_id: cid } });
    await delay(150);
  }

  // Resim
  if (p.imageUrl && p.imageUrl.startsWith('http')) {
    await shopify('POST', `products/${product.id}/images.json`, {
      image: { src: p.imageUrl, alt: p.frenchName }
    });
    await delay(300);
  }

  return { id: product.id, price: priceEUR };
}

// ── ANA AKIŞ ────────────────────────────────────────────────
console.log('🍯 Karaköy Güllüoğlu → Mondimart\n');
console.log(`   Kur: 1 EUR ≈ ${(1/TRY_TO_EUR).toFixed(0)} TRY | Markup: ×${MARKUP} | ${DRY?'🔍 ÖNİZLEME':'🚀 CANLI'}\n`);

console.log('🌐 Baklava sayfası scrape ediliyor...');
const raw = await scrapeBaklavas();
console.log(`   ${raw.length} ürün bulundu`);

const filtered = raw
  .filter(p => !isBoxed(p.name))
  .filter(p => p.name?.length > 2)
  .filter(p => p.priceTRY > 0);

console.log(`   Kutulu/geçersiz çıkarıldı: ${raw.length - filtered.length}`);
console.log(`   İşlenecek: ${filtered.length} ürün\n`);

const targets = LIMIT ? filtered.slice(0, LIMIT) : filtered;
let added = 0, skipped = 0;

for (const p of targets) {
  p.frenchName = frenchifyName(p.name);
  const priceEUR = (p.priceTRY * TRY_TO_EUR * MARKUP).toFixed(2);

  if (DRY) {
    console.log(`📦 "${p.name}"`);
    console.log(`   → "${p.frenchName}"`);
    console.log(`   💰 ${p.priceTRY} TRY → ${priceEUR}€`);
    console.log(`   🖼️  ${p.imageUrl ? '✅ resim var' : '❌ resim yok'}`);
    console.log('');
    added++;
    continue;
  }

  try {
    const result = await createProduct(p);
    console.log(`✅ "${p.frenchName}" → ${result.price}€`);
    added++;
    await delay(600);
  } catch (e) {
    console.error(`❌ "${p.name}": ${e.message}`);
    skipped++;
  }
}

console.log(`\n🎉 ${added} ${DRY?'önizlendi':'eklendi'} | ${skipped} hata`);
