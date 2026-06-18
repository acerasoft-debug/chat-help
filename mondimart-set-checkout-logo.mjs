// Mondimart — Ödeme sayfasına logo ekle
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-set-checkout-logo.mjs
// Logo dosyası: mondimart-logo.png (beyaz arka plan)

import { readFileSync } from 'fs';

const TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP  = 'pftzey-y0.myshopify.com';
const GQL   = `https://${SHOP}/admin/api/2024-04/graphql.json`;
const H     = { 'X-Shopify-Access-Token': TOKEN, 'Content-Type': 'application/json' };

if (!TOKEN) { console.error('❌ SHOPIFY_TOKEN gerekli'); process.exit(1); }

const delay = ms => new Promise(r => setTimeout(r, ms));

async function gql(query, variables = {}) {
  const r = await fetch(GQL, { method: 'POST', headers: H, body: JSON.stringify({ query, variables }) });
  const d = await r.json();
  if (d.errors) { console.error('GraphQL hata:', JSON.stringify(d.errors, null, 2)); }
  return d;
}

// ── 1. Staged upload hedefi al ──────────────────────────────
async function stagedUpload(filename, mimeType, fileSize) {
  const d = await gql(`
    mutation stagedUploadsCreate($input:[StagedUploadInput!]!) {
      stagedUploadsCreate(input:$input) {
        stagedTargets { url resourceUrl parameters { name value } }
        userErrors { field message }
      }
    }`, {
    input: [{ filename, mimeType, fileSize: String(fileSize), resource: 'IMAGE', httpMethod: 'POST' }]
  });
  const errors = d.data?.stagedUploadsCreate?.userErrors;
  if (errors?.length) throw new Error('Staged upload hatası: ' + JSON.stringify(errors));
  return d.data.stagedUploadsCreate.stagedTargets[0];
}

// ── 2. S3'e dosyayı gönder ──────────────────────────────────
async function uploadToS3(target, buffer, mimeType) {
  const form = new FormData();
  for (const { name, value } of target.parameters) form.append(name, value);
  form.append('file', new Blob([buffer], { type: mimeType }), 'mondimart-logo.png');
  const r = await fetch(target.url, { method: 'POST', body: form });
  if (!r.ok) throw new Error(`S3 upload başarısız: HTTP ${r.status}`);
}

// ── 3. Shopify Files'a kaydet ───────────────────────────────
async function createShopifyFile(resourceUrl) {
  await delay(1500); // Shopify'ın dosyayı işlemesi için bekle
  const d = await gql(`
    mutation fileCreate($files:[FileCreateInput!]!) {
      fileCreate(files:$files) {
        files { ...on MediaImage { id image { url } } }
        userErrors { field message }
      }
    }`, {
    files: [{ originalSource: resourceUrl, contentType: 'IMAGE' }]
  });
  const errors = d.data?.fileCreate?.userErrors;
  if (errors?.length) throw new Error('File create hatası: ' + JSON.stringify(errors));
  await delay(2000); // işlenmesini bekle
  return d.data.fileCreate.files[0]?.id;
}

// ── 4. Checkout profil ID'sini al ──────────────────────────
async function getCheckoutProfile() {
  const d = await gql(`{ checkoutProfiles(first:5) { edges { node { id name isPublished } } } }`);
  const profiles = d.data?.checkoutProfiles?.edges || [];
  // Önce yayındaki profili seç
  const pub = profiles.find(e => e.node.isPublished);
  return (pub || profiles[0])?.node;
}

// ── 5. Checkout branding logo ayarla ───────────────────────
async function setCheckoutLogo(profileId, imageId) {
  const d = await gql(`
    mutation checkoutBrandingUpsert($profileId:ID!, $input:CheckoutBrandingInput!) {
      checkoutBrandingUpsert(checkoutProfileId:$profileId, checkoutBrandingInput:$input) {
        checkoutBranding {
          customizations {
            header { logo { maxWidth image { url } } }
          }
        }
        userErrors { field message }
      }
    }`, {
    profileId,
    input: {
      customizations: {
        header: {
          logo: { maxWidth: 180, image: { id: imageId } }
        }
      }
    }
  });
  const errors = d.data?.checkoutBrandingUpsert?.userErrors;
  if (errors?.length) throw new Error('Branding hatası: ' + JSON.stringify(errors));
  return d.data.checkoutBrandingUpsert.checkoutBranding;
}

// ── ANA AKIŞ ────────────────────────────────────────────────
console.log('🎨 Mondimart — Ödeme sayfası logo kurulumu\n');

const logoBuffer = readFileSync('./mondimart-logo.png');
console.log(`📁 Logo: mondimart-logo.png (${Math.round(logoBuffer.length/1024)} KB)`);

console.log('⬆️  1/4 — Staged upload hedefi alınıyor...');
const target = await stagedUpload('mondimart-logo.png', 'image/png', logoBuffer.length);

console.log('⬆️  2/4 — Dosya S3\'e yükleniyor...');
await uploadToS3(target, logoBuffer, 'image/png');

console.log('📂 3/4 — Shopify Files\'a kaydediliyor...');
const imageId = await createShopifyFile(target.resourceUrl);
if (!imageId) throw new Error('Image ID alınamadı — dosya henüz hazır olmayabilir, 30 sn bekleyip tekrar dene');
console.log(`   ✅ Image ID: ${imageId}`);

console.log('🛒 4/4 — Checkout branding ayarlanıyor...');
const profile = await getCheckoutProfile();
if (!profile) throw new Error('Checkout profili bulunamadı');
console.log(`   Profil: "${profile.name}" (${profile.isPublished ? 'yayında' : 'taslak'})`);

const branding = await setCheckoutLogo(profile.id, imageId);
const logoUrl = branding?.customizations?.header?.logo?.image?.url;

console.log('\n🎉 Tamamlandı! Logo ödeme sayfasına eklendi.');
if (logoUrl) console.log('   Logo URL:', logoUrl);
