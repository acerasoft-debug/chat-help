// ============================================================
// Karaköy Güllüoğlu Baklava → Mondimart import
// Çalıştır: SHOPIFY_TOKEN='...' node mondimart-import-gulluoglu.mjs
// Önizleme:  DRY=1 node mondimart-import-gulluoglu.mjs
// Sınırlı:   LIMIT=5 DRY=1 ...
// ============================================================

import * as https from 'https';
import * as zlib from 'zlib';

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const DRY   = process.env.DRY === '1';
const LIMIT = process.env.LIMIT ? parseInt(process.env.LIMIT) : 0;
const MARKUP = process.env.MARKUP ? parseFloat(process.env.MARKUP) : 1.20;

// TRY → EUR kuru (güncelle: https://www.ecb.europa.eu)
// Haziran 2026 itibarıyla yaklaşık 1 EUR = 38 TRY
const TRY_TO_EUR = process.env.TRY_EUR ? parseFloat(process.env.TRY_EUR) : 1 / 38;

const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const SH = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN && !DRY) {
  console.error('❌ SHOPIFY_TOKEN gerekli (veya DRY=1 ile önizle)');
  process.exit(1);
}

const delay = ms => new Promise(r => setTimeout(r, ms));

// ── HTTP fetch with browser headers ─────────────────────────
function fetchPage(url) {
  return new Promise((resolve, reject) => {
    const parsedUrl = new URL(url);
    const options = {
      hostname: parsedUrl.hostname,
      path: parsedUrl.pathname + parsedUrl.search,
      method: 'GET',
      headers: {
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language': 'tr-TR,tr;q=0.9,fr;q=0.8,en;q=0.7',
        'Accept-Encoding': 'gzip, deflate, br',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
        'Cache-Control': 'max-age=0',
      }
    };

    let req;
    const handleResponse = (res) => {
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        return fetchPage(new URL(res.headers.location, url).href).then(resolve).catch(reject);
      }
      let data = [];
      // Handle gzip
      let stream = res;
      if (res.headers['content-encoding'] === 'gzip') {
        stream = res.pipe(zlib.createGunzip());
      } else if (res.headers['content-encoding'] === 'br') {
        stream = res.pipe(zlib.createBrotliDecompress());
      } else if (res.headers['content-encoding'] === 'deflate') {
        stream = res.pipe(zlib.createInflate());
      }
      stream.on('data', chunk => data.push(chunk));
      stream.on('end', () => resolve(Buffer.concat(data).toString('utf8')));
      stream.on('error', reject);
    };

    req = https.request(options, handleResponse);
    req.on('error', reject);
    req.setTimeout(15000, () => { req.destroy(); reject(new Error('timeout')); });
    req.end();
  });
}

// ── Shopify API ──────────────────────────────────────────────
async function shopify(method, path, body) {
  for (let i = 1; i <= 4; i++) {
    try {
      const r = await fetch(`${BASE}/${path}`, {
        method, headers: SH,
        body: body ? JSON.stringify(body) : undefined
      });
      if (method === 'DELETE') return { ok: r.ok };
      return await r.json();
    } catch (e) {
      if (i === 4) throw e;
      await delay(2000 * i);
    }
  }
}

async function uploadImageToShopify(productId, imageUrl, altText) {
  try {
    const d = await shopify('POST', `products/${productId}/images.json`, {
      image: { src: imageUrl, alt: altText }
    });
    return d.image?.id;
  } catch (e) {
    console.warn(`   ⚠️  Resim yüklenemedi: ${e.message}`);
    return null;
  }
}

// ── HTML Parser ──────────────────────────────────────────────
function extractText(html, tag, cls) {
  const re = new RegExp(`<${tag}[^>]*class="[^"]*${cls}[^"]*"[^>]*>([\\s\\S]*?)<\\/${tag}>`, 'i');
  const m = html.match(re);
  return m ? m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() : null;
}

function extractAttr(html, tag, attr, cls = '') {
  const clsPart = cls ? `[^>]*class="[^"]*${cls}[^"]*"` : '';
  const re = new RegExp(`<${tag}${clsPart}[^>]*${attr}="([^"]+)"`, 'i');
  const m = html.match(re);
  return m ? m[1] : null;
}

// ── API / sitemap stratejileri ───────────────────────────────
async function tryWooCommerceAPI() {
  // Çeşitli WooCommerce endpoint kombinasyonları
  const endpoints = [
    'https://www.karakoygulluoglu.com/wp-json/wc/store/v1/products?category=baklavalar&per_page=100',
    'https://www.karakoygulluoglu.com/wp-json/wc/store/v1/products?search=baklava&per_page=100',
    'https://www.karakoygulluoglu.com/wp-json/wp/v2/product?per_page=100&search=baklava',
  ];
  for (const url of endpoints) {
    try {
      const html = await fetchPage(url);
      const data = JSON.parse(html);
      if (Array.isArray(data) && data.length > 0) {
        console.log(`   ✅ WooCommerce API: ${data.length} ürün (${url.split('?')[0].split('/').pop()})`);
        return data.map(p => ({
          name: p.name || p.title?.rendered || '',
          priceTRY: parseFloat(p.prices?.price || p.price || 0) / 100, // WooCommerce minor units
          imageUrl: p.images?.[0]?.src || p.image?.src || '',
          url: p.permalink || p.link || '',
          description: (p.short_description || p.description || '').replace(/<[^>]+>/g, '').slice(0, 400),
        }));
      }
    } catch {}
    await delay(300);
  }
  return null;
}

async function tryNextJsAPI() {
  // Next.js statik data veya API route'ları
  const endpoints = [
    'https://www.karakoygulluoglu.com/api/products?slug=baklavalar',
    'https://www.karakoygulluoglu.com/api/products?category=baklava',
    'https://www.karakoygulluoglu.com/_next/data/baklavalar.json',
  ];
  for (const url of endpoints) {
    try {
      const html = await fetchPage(url);
      const data = JSON.parse(html);
      const products = data?.products || data?.items || data?.data || data?.pageProps?.products;
      if (Array.isArray(products) && products.length > 0) {
        console.log(`   ✅ Next.js API: ${products.length} ürün`);
        return products.map(p => ({
          name: p.name || p.title || '',
          priceTRY: parseFloat(p.price || p.regularPrice || 0),
          imageUrl: p.image || p.thumbnail || p.imageUrl || '',
          url: p.url || p.slug || '',
          description: (p.description || p.shortDescription || '').slice(0, 400),
        }));
      }
    } catch {}
    await delay(300);
  }
  return null;
}

async function tryScriptTagData(html) {
  // Next.js / Nuxt __NEXT_DATA__ veya window.__INITIAL_STATE__
  const patterns = [
    /__NEXT_DATA__\s*=\s*(\{[\s\S]*?\})<\/script>/,
    /window\.__INITIAL_STATE__\s*=\s*(\{[\s\S]*?\})\s*;?\s*<\/script>/,
    /window\.__NUXT__\s*=\s*(\{[\s\S]*?\})\s*;?\s*<\/script>/,
    /<script[^>]*type="application\/json"[^>]*>([\s\S]*?)<\/script>/g,
  ];

  for (const pattern of patterns) {
    try {
      const matches = typeof pattern.exec === 'function'
        ? [html.match(pattern)]
        : [...html.matchAll(pattern)].map(m => [m[0], m[1]]);

      for (const m of matches) {
        if (!m?.[1]) continue;
        const data = JSON.parse(m[1]);
        // Ürün listesini ara
        const findProducts = (obj, depth = 0) => {
          if (depth > 6 || !obj || typeof obj !== 'object') return null;
          if (Array.isArray(obj) && obj.length > 2 && obj[0]?.name && (obj[0]?.price !== undefined)) return obj;
          for (const v of Object.values(obj)) {
            const r = findProducts(v, depth + 1);
            if (r) return r;
          }
          return null;
        };
        const products = findProducts(data);
        if (products) {
          console.log(`   ✅ Script tag data: ${products.length} ürün`);
          return products.map(p => ({
            name: p.name || p.title || '',
            priceTRY: parseFloat(p.price || p.regularPrice || p.salePrice || 0),
            imageUrl: p.image || p.imageUrl || p.thumbnail || '',
            url: p.url || p.permalink || p.slug || '',
            description: (p.description || p.shortDescription || '').replace(/<[^>]+>/g, '').slice(0, 400),
          }));
        }
      }
    } catch {}
  }
  return null;
}

// ── Baklava sayfasını parse et ───────────────────────────────
async function scrapeBaklavas() {
  // Önce API endpoint'leri dene
  console.log('   🔌 WooCommerce API deneniyor...');
  const wooResult = await tryWooCommerceAPI();
  if (wooResult?.length > 0) return wooResult;

  console.log('   🔌 Next.js API deneniyor...');
  const nextResult = await tryNextJsAPI();
  if (nextResult?.length > 0) return nextResult;

  const PAGES = [
    'https://www.karakoygulluoglu.com/baklavalar',
    'https://www.karakoygulluoglu.com/urunler/baklavalar',
    'https://www.karakoygulluoglu.com/kategori/baklavalar',
    'https://www.karakoygulluoglu.com/category/baklavalar',
  ];

  let html = null;
  for (const url of PAGES) {
    try {
      console.log(`   Deneniyor: ${url}`);
      html = await fetchPage(url);
      if (html && html.length > 3000) {
        console.log(`   ✅ Sayfa alındı (${Math.round(html.length/1024)}KB)`);
        // Script tag'dan data bul
        const scriptData = await tryScriptTagData(html);
        if (scriptData?.length > 0) return scriptData;
        break;
      }
    } catch (e) {
      console.log(`   ❌ ${url}: ${e.message}`);
    }
    await delay(800);
  }

  // HTML dump — incelemek için
  if (html) {
    const fs = await import('fs');
    fs.writeFileSync('./gulluoglu-debug.html', html);
    console.log(`   📄 HTML kaydedildi: gulluoglu-debug.html (${Math.round(html.length/1024)}KB)`);
    console.log(`      İncele: open gulluoglu-debug.html`);
  }

  if (!html || html.length < 500) throw new Error('Sayfa alınamadı — site bot koruması olabilir');

  const products = [];

  // WooCommerce standart ürün kartı
  const cardPatterns = [
    // Standart WooCommerce li.product
    /<li[^>]*class="[^"]*product[^"]*"[^>]*>([\s\S]*?)<\/li>/g,
    // Alternatif div.product-item
    /<div[^>]*class="[^"]*product[^"]*"[^>]*>([\s\S]*?)<\/div>/g,
  ];

  let cards = [];
  for (const pattern of cardPatterns) {
    const matches = [...html.matchAll(pattern)];
    if (matches.length > 3) { cards = matches.map(m => m[1]); break; }
  }

  if (cards.length === 0) {
    // JSON-LD fallback
    const jsonLdMatches = [...html.matchAll(/<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)];
    for (const m of jsonLdMatches) {
      try {
        const data = JSON.parse(m[1]);
        const items = data['@graph'] || (Array.isArray(data) ? data : [data]);
        for (const item of items) {
          if (item['@type'] === 'Product') {
            products.push({
              name: item.name,
              priceTRY: parseFloat(item.offers?.price || 0),
              imageUrl: item.image?.[0] || item.image || '',
              url: item.url || '',
              description: item.description || '',
            });
          }
        }
      } catch {}
    }
    if (products.length > 0) return products;

    // Son çare: fiyat ve isim regex
    const priceRe = /<[^>]*class="[^"]*price[^"]*"[^>]*>[\s\S]*?(\d[\d.,]+)\s*(?:TL|₺|TRY)/g;
    const nameRe = /<[^>]*class="[^"]*(?:title|name|product-title)[^"]*"[^>]*>\s*<a[^>]*href="([^"]*)"[^>]*>([^<]+)<\/a>/g;

    // Raw extraction
    const priceMatches = [...html.matchAll(priceRe)].map(m => m[1]);
    const nameMatches  = [...html.matchAll(nameRe)].map(m => ({ url: m[1], name: m[2].trim() }));

    for (let i = 0; i < Math.min(nameMatches.length, priceMatches.length); i++) {
      products.push({
        name: nameMatches[i].name,
        priceTRY: parseFloat(priceMatches[i].replace(/\./g, '').replace(',', '.')),
        imageUrl: '',
        url: nameMatches[i].url,
        description: '',
      });
    }
    return products;
  }

  for (const card of cards) {
    // Name
    let name = null;
    const namePatterns = [
      /<a[^>]*class="[^"]*(?:woocommerce-loop-product__title|product-title|product_title)[^"]*"[^>]*>([^<]+)<\/a>/i,
      /<h2[^>]*class="[^"]*(?:woocommerce-loop-product__title|product-title)[^"]*"[^>]*>([^<]+)<\/h2>/i,
      /<h3[^>]*class="[^"]*(?:product-title)[^"]*"[^>]*>([^<]+)<\/h3>/i,
      /<a[^>]*>([\w\sÀ-ɏİ-ı]+Baklava[\w\sÀ-ɏİı]*)<\/a>/i,
    ];
    for (const p of namePatterns) {
      const m = card.match(p);
      if (m) { name = m[1].trim(); break; }
    }
    if (!name) continue;

    // Price (TRY)
    let priceTRY = 0;
    const priceMatch = card.match(/(\d[\d.,]+)\s*(?:TL|₺|TRY)/i)
      || card.match(/<bdi>([^<]+)<\/bdi>/i)
      || card.match(/class="[^"]*(?:price|woocommerce-Price-amount)[^"]*"[^>]*>[\s\S]*?(\d[\d.,]+)/i);
    if (priceMatch) {
      priceTRY = parseFloat(priceMatch[1].replace(/\./g, '').replace(',', '.'));
    }

    // Image
    const imgMatch = card.match(/<img[^>]*src="([^"]+)"[^>]*>/i)
      || card.match(/data-src="([^"]+\.(?:jpg|jpeg|png|webp))"/i);
    const imageUrl = imgMatch ? imgMatch[1] : '';

    // Product URL
    const urlMatch = card.match(/<a[^>]*href="(https?:\/\/[^"]*karakoygulluoglu[^"]*)"[^>]*>/i)
      || card.match(/<a[^>]*href="(\/urun[^"]*|\/product[^"]*)"[^>]*>/i);
    const productUrl = urlMatch ? urlMatch[1] : '';

    products.push({ name, priceTRY, imageUrl, url: productUrl, description: '' });
  }

  return products;
}

// ── Ürün sayfasından açıklama al ────────────────────────────
async function fetchDescription(url) {
  if (!url) return '';
  try {
    const html = await fetchPage(url.startsWith('http') ? url : `https://www.karakoygulluoglu.com${url}`);
    const descMatch = html.match(/<div[^>]*class="[^"]*(?:woocommerce-product-details__short-description|product-description|entry-summary)[^"]*"[^>]*>([\s\S]*?)<\/div>/i);
    if (descMatch) return descMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500);
  } catch {}
  return '';
}

// ── Shopify'a ürün ekle ──────────────────────────────────────
async function createProduct(p) {
  const priceEUR = (p.priceTRY * TRY_TO_EUR * MARKUP).toFixed(2);
  const body = {
    product: {
      title: p.frenchName,
      body_html: p.description ? `<p>${p.description}</p>` : `<p>Baklava traditionnel turc Karaköy Güllüoğlu — Fabrication artisanale depuis 1820.</p>`,
      vendor: 'Karaköy Güllüoğlu',
      product_type: 'Douceurs Orientales',
      tags: 'baklava,turc,oriental,halal,dessert,karakoy',
      status: 'active',
      variants: [{ price: priceEUR, requires_shipping: true, taxable: true }],
    }
  };

  const d = await shopify('POST', 'products.json', body);
  const product = d.product;
  if (!product) throw new Error(JSON.stringify(d));

  // Koleksiyona ekle
  const colls = ['686917583173', '687825617221']; // Baklava + Snacks&Apéro
  for (const cid of colls) {
    await shopify('POST', 'collects.json', { collect: { product_id: product.id, collection_id: parseInt(cid) } });
    await delay(150);
  }

  // Resim ekle
  if (p.imageUrl) {
    await uploadImageToShopify(product.id, p.imageUrl, p.frenchName);
    await delay(300);
  }

  return { id: product.id, price: priceEUR };
}

// ── İsim Fransızcalaştır ─────────────────────────────────────
function frenchifyName(name) {
  return name
    .replace(/Baklava/gi, 'Baklava')
    .replace(/Fıstıklı/gi, 'Baklava aux Pistaches')
    .replace(/Cevizli/gi, 'Baklava aux Noix')
    .replace(/Bülbül Yuvası/gi, 'Nid de Rossignol')
    .replace(/Burma/gi, 'Baklava Burma')
    .replace(/Havuç Dilimi/gi, 'Baklava Tranche de Carotte')
    .replace(/Şöbiyet/gi, 'Şöbiyet')
    .replace(/Sade/gi, 'Nature')
    .replace(/Antep/gi, 'Antep')
    .replace(/Kaymak/gi, 'à la Crème')
    .replace(/Dürüm/gi, 'Roulé')
    .replace(/Kadayıf/gi, 'Kadayıf')
    .trim();
}

// ── "Kutulu" (kutu/hediye) filtresi ─────────────────────────
function isBoxed(name) {
  return /kutu|box|paket|set|gift|hediye/i.test(name);
}

// ── ANA AKIŞ ────────────────────────────────────────────────
console.log('🍯 Karaköy Güllüoğlu Baklava → Mondimart\n');
console.log(`   Kur: 1 EUR = ${(1/TRY_TO_EUR).toFixed(0)} TRY | Markup: ×${MARKUP} | ${DRY ? '🔍 ÖNİZLEME' : '🚀 CANLI'}\n`);

console.log('🌐 Baklava sayfası scrape ediliyor...');
let baklavas;
try {
  baklavas = await scrapeBaklavas();
} catch (e) {
  console.error(`❌ Scrape başarısız: ${e.message}`);
  console.error('\n💡 İpucu: Site bot koruması varsa tarayıcıda açıp HTML\'i kaydet,');
  console.error('   ardından: node mondimart-import-gulluoglu.mjs --file baklavalar.html');
  process.exit(1);
}

console.log(`   ${baklavas.length} ürün bulundu`);

// Filtrele: kutu ve fiyatsız ürünleri çıkar
const filtered = baklavas
  .filter(p => !isBoxed(p.name))
  .filter(p => p.priceTRY > 0);

console.log(`   Kutulu/fiyatsız çıkarıldı: ${baklavas.length - filtered.length}`);
console.log(`   İşlenecek: ${filtered.length} ürün\n`);

let targets = LIMIT ? filtered.slice(0, LIMIT) : filtered;

let added = 0, skipped = 0;

for (const p of targets) {
  p.frenchName = frenchifyName(p.name);
  const priceEUR = (p.priceTRY * TRY_TO_EUR * MARKUP).toFixed(2);

  if (DRY) {
    console.log(`   📦 "${p.name}"`);
    console.log(`      → "${p.frenchName}"`);
    console.log(`      💰 ${p.priceTRY} TRY × ${TRY_TO_EUR.toFixed(4)} × ${MARKUP} = ${priceEUR}€`);
    console.log(`      🖼️  ${p.imageUrl ? p.imageUrl.slice(0,60)+'...' : '(resim yok)'}`);
    added++;
    continue;
  }

  // Açıklama çek (isteğe bağlı — yavaşlatır)
  // p.description = await fetchDescription(p.url);

  try {
    const result = await createProduct(p);
    console.log(`   ✅ "${p.frenchName}" → ${result.price}€ (ID: ${result.id})`);
    added++;
    await delay(500);
  } catch (e) {
    console.error(`   ❌ "${p.name}": ${e.message}`);
    skipped++;
  }
}

console.log(`\n🎉 Tamamlandı: ${added} ${DRY ? 'önizlendi' : 'eklendi'} | ${skipped} hata`);
if (!DRY) console.log('   Baklava ve Snacks&Apéro koleksiyonlarına otomatik eklendi.');
