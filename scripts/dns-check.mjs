#!/usr/bin/env node
/**
 * Domain → Shopify DNS kontrolü
 * - Kök domain'in A kaydı Shopify'ı gösteriyor mu?
 * - www CNAME'i shops.myshopify.com mu?
 * - NS kayıtları nerede (cPanel mi, Cloudflare mı)? -> kaydı NEREDE düzenleyeceğini söyler
 * - MX / AAAA / CAA gibi kurulumu bozan kayıtlar var mı?
 *
 * KULLANIM:  node scripts/dns-check.mjs acerasoft.com [domain2 ...]
 *
 * Ayrıntılı kurulum: docs/cpanel-domain-shopify.md
 */

import { Resolver } from 'node:dns/promises';

// Shopify kök domain'ler için 23.227.38.0/24 (IPv4) ve 2620:127:f00f::/48 (IPv6) kullanır.
const SHOPIFY_NET = '23.227.38.';
const SHOPIFY_NET6 = '2620:127:f00f:';
const SHOPIFY_CNAME = 'shops.myshopify.com';

// NS sonekinden DNS'i NEREDE düzenleyeceğini bul. cPanel'in Zone Editor'ü,
// ancak nameserver'lar o hosting'i gösteriyorsa işe yarar.
const NS_PROVIDERS = [
  [/\.ns\.cloudflare\.com$/i,   'Cloudflare',        'Cloudflare DNS panelinden düzenle; Shopify kayıtlarını "DNS only" (gri bulut) yap, yoksa SSL çıkmaz.'],
  [/\.googledomains\.com$/i,     'Google Cloud DNS',  'Google Cloud DNS / Squarespace Domains panelinden düzenle — cPanel Zone Editor bu domaini yönetmiyor.'],
  [/\.domaincontrol\.com$/i,     'GoDaddy',           'GoDaddy → Domain → DNS Management ekranından düzenle — cPanel Zone Editor bu domaini yönetmiyor.'],
  [/\.registrar-servers\.com$/i, 'Namecheap',         'Namecheap → Advanced DNS ekranından düzenle.'],
  [/\.awsdns-/i,                 'AWS Route 53',      'Route 53 hosted zone üzerinden düzenle.'],
  [/\.hosteurope\.de$/i,         'Host Europe',       'Host Europe KIS / cPanel Zone Editor üzerinden düzenle.'],
];

function nsProvider(ns) {
  for (const [re, name, hint] of NS_PROVIDERS) if (ns.some(h => re.test(h))) return { name, hint };
  return { name: null, hint: 'Nameserver sağlayıcısı tanınmadı — kayıtları bu NS\'leri yöneten panelden düzenle (cPanel Zone Editor ancak NS hosting\'i gösteriyorsa geçerlidir).' };
}

// Otoriter değil ama tarafsız çözümleyiciler — hosting'in kendi DNS'i yanıltmasın.
const resolver = new Resolver({ timeout: 5000, tries: 2 });
resolver.setServers(['1.1.1.1', '8.8.8.8']);

const domains = process.argv.slice(2);
if (domains.length === 0) {
  console.error('Kullanım: node scripts/dns-check.mjs <domain> [domain ...]');
  process.exit(2);
}

/** Kayıt yoksa hata fırlatmasın, boş dizi dönsün. */
async function q(type, name) {
  try {
    return await resolver.resolve(name, type);
  } catch {
    return [];
  }
}

function isShopifyIp(ip) { return String(ip).startsWith(SHOPIFY_NET); }
function isShopifyIp6(ip) { return String(ip).toLowerCase().startsWith(SHOPIFY_NET6); }

async function inspect(domain) {
  const apex = domain.replace(/^www\./, '');
  const www = `www.${apex}`;

  const [ns, a, aaaa, mx, caa, wwwCname, wwwA] = await Promise.all([
    q('NS', apex), q('A', apex), q('AAAA', apex), q('MX', apex), q('CAA', apex),
    q('CNAME', www), q('A', www),
  ]);

  const notes = [];
  const nsText = ns.join(', ') || '(bulunamadı)';
  const provider = nsProvider(ns);

  console.log(`\n=== ${apex} ===`);
  console.log(`NS          : ${nsText}`);
  console.log(`A     @     : ${a.join(', ') || '(yok)'}`);
  console.log(`AAAA  @     : ${aaaa.join(', ') || '(yok)'}`);
  console.log(`CNAME www   : ${wwwCname.join(', ') || '(yok)'}`);
  console.log(`A     www   : ${wwwA.join(', ') || '(yok)'}`);
  console.log(`MX          : ${mx.map(r => `${r.priority} ${r.exchange}`).join(', ') || '(yok)'}`);
  console.log(`CAA         : ${caa.map(r => `${r.flags} ${r.tag} ${r.value}`).join(', ') || '(yok)'}`);

  // --- Değerlendirme ---
  if (a.length === 0) notes.push('🔴 Kök domain için A kaydı yok — Shopify A kaydını (23.227.38.65) ekle.');
  else if (a.every(isShopifyIp)) notes.push('🟢 Kök domain Shopify\'ı gösteriyor.');
  else notes.push(`🔴 Kök domain Shopify\'da değil (${a.join(', ')}) — eski A kaydını sil, Shopify A kaydını ekle.`);

  if (aaaa.length && aaaa.every(isShopifyIp6)) notes.push('🟢 AAAA (IPv6) kaydı da Shopify\'ı gösteriyor.');
  else if (aaaa.length) notes.push(`🔴 AAAA (IPv6) kaydı Shopify'da değil (${aaaa.join(', ')}) — sil, yoksa IPv6 kullanan ziyaretçiler eski sunucuya düşer.`);

  const cnameOk = wwwCname.some(c => c.replace(/\.$/, '').toLowerCase() === SHOPIFY_CNAME);
  if (cnameOk) notes.push('🟢 www → shops.myshopify.com CNAME doğru.');
  else if (wwwCname.length) notes.push(`🔴 www CNAME yanlış (${wwwCname.join(', ')}) — ${SHOPIFY_CNAME}. olmalı.`);
  else if (wwwA.every(isShopifyIp) && wwwA.length) notes.push('🟡 www A kaydıyla Shopify\'ı gösteriyor — çalışır ama CNAME tercih edilir.');
  else notes.push('🔴 www için CNAME yok — CNAME www → shops.myshopify.com. ekle.');

  notes.push(`ℹ️  DNS yönetimi: ${provider.name || 'bilinmiyor'} — ${provider.hint}`);

  if (mx.length === 0) notes.push('🟡 MX kaydı yok — bu domainde e-posta alamazsın (Shopify e-posta barındırmaz).');
  if (caa.length && !caa.some(r => /letsencrypt|digicert|amazon|globalsign|\s*;?\s*$/i.test(r.value))) {
    notes.push('🟡 CAA kaydı var — Shopify\'ın SSL sağlayıcısını engelliyor olabilir, kontrol et.');
  }

  console.log('');
  for (const n of notes) console.log(`  ${n}`);

  return notes.every(n => !n.startsWith('🔴'));
}

let allOk = true;
for (const d of domains) allOk = (await inspect(d)) && allOk;

console.log(`\n${allOk ? '✅ Kritik sorun yok.' : '⛔ Düzeltilmesi gereken kayıtlar var (🔴 satırlar).'}`);
console.log('Ayrıntılı adımlar: docs/cpanel-domain-shopify.md');
process.exit(allOk ? 0 : 1);
