// Shopify Asset API ile değişen 4 dosyayı canlı temaya push eder
const TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const H = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

// 1. Canlı tema ID'sini bul
const themesRes = await fetch(`https://${SHOP}/admin/api/2024-04/themes.json`, { headers: H });
const { themes } = await themesRes.json();
const live = themes.find(t => t.role === 'main');
if (!live) { console.error('❌ Canlı tema bulunamadı'); process.exit(1); }
console.log(`✅ Canlı tema: "${live.name}" (ID: ${live.id})\n`);

// 2. GitHub'dan dosyaları çek ve Shopify'a yükle
const BRANCH = 'claude/dreamy-mccarthy-ycux6x';
const REPO = 'acerasoft-debug/chat-help';
const BASE = `https://raw.githubusercontent.com/${REPO}/${encodeURIComponent(BRANCH)}/mondimart`;

const FILES = [
  // layout — KRİTİK: alt menü (bottom nav) burada
  'layout/theme.liquid',
  // assets
  'assets/mondimart-premium.css',
  'assets/theme.css',
  'assets/theme.js',
  // sections
  'sections/announcement-bar.liquid',
  'sections/header.liquid',
  'sections/footer.liquid',
  'sections/trust-bar.liquid',
  'sections/collections-grid.liquid',
  'sections/cuisines.liquid',
  'sections/featured-products.liquid',
  'sections/flash-deals.liquid',
  'sections/hero.liquid',
  'sections/newsletter.liquid',
  'sections/promo-banners.liquid',
  'sections/recipes.liquid',
  // snippets
  'snippets/cart-drawer.liquid',
  // templates
  'templates/index.liquid',
  'templates/product.liquid',
  'templates/collection.liquid',
  'templates/cart.liquid',
  'templates/search.liquid',
  'templates/blog.liquid',
  'templates/article.liquid',
  'templates/404.liquid',
  'templates/page.liquid',
  'templates/page.faq.liquid',
  'templates/page.delivery.liquid',
  'templates/page.about.liquid',
  'templates/page.contact.liquid',
  'templates/page.impressum.liquid',
  'templates/customers/account.liquid',
  'templates/customers/login.liquid',
  'templates/customers/register.liquid',
];

for (const file of FILES) {
  process.stdout.write(`📤 ${file} ... `);

  // GitHub'dan içeriği al
  const raw = await fetch(`${BASE}/${file}?t=${Date.now()}`);
  if (!raw.ok) { console.log(`❌ GitHub'dan alınamadı (${raw.status})`); continue; }
  const value = await raw.text();

  // Shopify'a yükle
  const res = await fetch(`https://${SHOP}/admin/api/2024-04/themes/${live.id}/assets.json`, {
    method: 'PUT',
    headers: H,
    body: JSON.stringify({ asset: { key: file, value } })
  });
  const data = await res.json();
  if (data.errors || data.asset === undefined) {
    console.log(`❌ Hata: ${JSON.stringify(data.errors || data)}`);
  } else {
    console.log(`✅`);
  }
}

console.log('\n🎉 Tamamlandı! Siteyi yenile: https://pftzey-y0.myshopify.com');
