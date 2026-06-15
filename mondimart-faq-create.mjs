// FAQ sayfasını Shopify'da oluşturur (write_content scope gerekli)
const TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP  = 'pftzey-y0.myshopify.com';
const H = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

const res = await fetch(`https://${SHOP}/admin/api/2024-04/pages.json`, {
  method: 'POST',
  headers: H,
  body: JSON.stringify({
    page: {
      title: 'Questions Fréquentes (FAQ)',
      handle: 'faq',
      body_html: '<p>Voir la FAQ ci-dessous.</p>',
      template_suffix: 'faq',
      published: true
    }
  })
});
const data = await res.json();
if (data.errors) {
  console.error('❌ Hata:', JSON.stringify(data.errors));
  console.log('\n⚠️  Token\'a "write_content" scope\'u ekleyin:');
  console.log('   Shopify Admin → Apps → Private apps → Token\'ı düzenle → write_content ✓');
} else {
  console.log('✅ FAQ sayfası oluşturuldu!');
  console.log('   URL: https://mondimart.fr/pages/faq');
  console.log('   Shopify ID:', data.page.id);
}
