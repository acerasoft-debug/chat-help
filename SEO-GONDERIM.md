# SARVESTO — Arama motoru gönderim kılavuzu

Kod tarafındaki teknik SEO tamamlandı ve canlıda. Aşağıdakiler **yalnızca senin
hesaplarınla** yapılabilecek adımlar. Hepsi ücretsiz, bir kez yapılır.

## 1. Google Search Console (en önemli)

1. https://search.google.com/search-console adresine gir, mülk ekle.
2. **Domain** mülkü öner (sarvesto.com). Google bir DNS TXT kaydı ister →
   GoDaddy/DNS panelinde ekle. (Alternatif: **URL prefix** → HTML meta.)
3. Meta ile doğrulayacaksan Google'ın verdiği `google-site-verification`
   içeriğini bana ver; `data/retail-settings.json` içine `seo.google_verify`
   olarak yazdırırım, head'de meta çıkar. (DNS yöntemi daha kalıcı.)
4. Doğrulandıktan sonra **Sitemaps** bölümüne `https://sarvesto.com/sitemap.xml`
   gir ve gönder.
5. **URL Inspection** ile `https://sarvesto.com/` ve birkaç ürün adresini
   "Request indexing" yap.

## 2. Bing Webmaster Tools

1. https://www.bing.com/webmasters adresine gir.
2. "Import from Google Search Console" ile tek tıkla aktar (en kolay), ya da
   sarvesto.com'u elle ekle ve `msvalidate.01` içeriğini bana ver →
   `seo.bing_verify` olarak yazarım.
3. Sitemap: `https://sarvesto.com/sitemap.xml` gönder.

## 3. IndexNow (Bing + Yandex anında indeksleme) — opsiyonel ama hızlı

1. Rastgele bir anahtar üret (sadece 0-9 ve a-f, 16-128 karakter). Örn:
   `openssl rand -hex 16` çıktısı.
2. Bana ver; `seo.indexnow_key` olarak sunucuya yazarım. Doğrulama dosyası
   `https://sarvesto.com/<anahtar>.txt` otomatik sunulur (indexnow-key.php).
3. Sunucuda `php tools/indexnow.php --sample` çalıştırınca ana sayfalar + en
   yeni 200 ürün Bing/Yandex'e anında bildirilir. İstersen bunu deploy sonrası
   otomatikleştiririm.

## 4. Doğrulama tokenlarını girme (bana verince)

Bana `google_verify`, `bing_verify`, `yandex_verify`, `indexnow_key`
değerlerini verirsen sunucudaki `data/retail-settings.json`'a yazarım; hiçbiri
repoya girmez. Head'de meta olarak çıkar, doğrulama geçer.

## 5. Ek öneriler

- **Google Merchant Center** (ücretsiz ürün listelemeleri): ürün şeması artık
  fiyat, stok, iade ve kargo içeriyor, uygun. İstersen sitemap'ten ürün feed'i
  de üretirim.
- **DNS**: sarvesto.com ve www.sarvesto.com ikisi de çözülüyor. Canonical
  hepsini sarvesto.com'a topluyor; istersen www → sarvesto.com 301
  yönlendirmesini de ekleyebilirim (şu an etiketle hallediliyor).

## Kod tarafında neler yapıldı (tamam)

- Canonical hatası düzeltildi: her ürün/los/yazı artık kendi adresine canonical
  veriyor (eskiden hepsi tek /product.php'ye düşüyordu — en büyük indeksleme
  engeli buydu).
- Kanonik host dışı adreslerde noindex; hreflang ve canonical asıl adrese sabit.
- Zengin JSON-LD: WebSite+SearchAction, Organization (logo, iletişim), Product
  (fiyat geçerliliği, iade, kargo, görsel dizisi), liste sayfalarında ItemList.
- Marka ve kategori sayfaları indekslenebilir + benzersiz açıklamalar (10 dil).
- Open Graph/Twitter tamamlandı, doğrulama meta desteği ve IndexNow altyapısı.
