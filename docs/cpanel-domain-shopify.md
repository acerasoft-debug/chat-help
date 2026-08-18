# cPanel'den Domain Kurulumu — Shopify Mağazası (acerasoft.com teması)

> Kaynak dosya: `theme_export__acerasoftcomaktualisiertekopievontinker__18AUG20260235am.zip`

## 0. Önce en önemli nokta

Elimizdeki zip bir **Shopify tema export'u** (Horizon tabanlı "Tinker" kopyası).
İçinde `.liquid` dosyaları, `config/settings_data.json`, `sections/`, `blocks/`,
`locales/` var — yani **Shopify'ın kendi şablon motoruna (Liquid) ait kod**.

Bunun pratik sonucu:

| İstenen | Bu zip ile olur mu? |
|---|---|
| Zip'i cPanel `public_html`'e yükleyip site açmak | ❌ **Hayır.** Apache/nginx Liquid'i işleyemez; ziyaretçi ya boş sayfa ya ham `{{ }}` kodu görür. |
| Temayı yayına almak | ✅ Shopify Admin → **Online Store → Themes → Add theme → Upload zip** |
| Domain'i mağazaya bağlamak | ✅ cPanel'de **DNS kaydı** (dosya yükleme yok) + Shopify Admin'de domain bağlama |

Yani **cPanel'in rolü dosya barındırmak değil, sadece DNS kaydı tutmak.**
Domain kurulumu tamamen DNS işidir; tema zip'inin domain kurulumuyla hiçbir
ilgisi yoktur — o ayrı bir kanaldan (Shopify Admin) yüklenir.

## 1. Mevcut durum (18.08.2026 ölçümü)

| Alan adı | A kaydı | DNS nerede yönetiliyor (NS) | Durum |
|---|---|---|---|
| `acerasoft.com` | `23.227.38.69` + IPv6 `2620:127:f00f:9::` | **Google Cloud DNS** (`*.googledomains.com`) | ✅ Zaten Shopify'da, kayıtlar doğru |
| `www.acerasoft.com` | CNAME → `shops.myshopify.com` | aynı | ✅ Doğru |
| `chat-help.com` | `160.153.0.182` | **GoDaddy** (`*.domaincontrol.com`) | 🏠 Kasıtlı olarak cPanel'de (ChatHelp uygulaması) |

İki önemli sonuç:

1. **`acerasoft.com` için yapılacak domain kurulumu yok.** Kök A, IPv6 AAAA ve
   `www` CNAME kayıtlarının üçü de Shopify'ı doğru gösteriyor. Zip'i yüklemek
   için tek yapılacak Adım 5'teki tema yüklemesi.
2. **Bu domainlerin DNS'i cPanel'de değil.** `acerasoft.com` Google Cloud DNS'te,
   `chat-help.com` GoDaddy'de. Yani cPanel → Zone Editor'de yapacağın değişiklik
   bu iki domain için **hiçbir şey yapmaz** — kayıtları o panellerden girmen
   gerekir. cPanel Zone Editor ancak nameserver'ları hosting'i gösteren bir
   domain için geçerlidir (Adım 2).

Kontrol için:

```bash
node scripts/dns-check.mjs acerasoft.com
node scripts/dns-check.mjs baglamak-istedigin-domain.com
```

> `chat-help.com` bu script'te 🔴 verir — beklenen davranış: script "bu domain
> Shopify'ı gösteriyor mu?" diye sorar, chat-help.com ise bilerek cPanel'de.

## 2. Ön kontrol: DNS gerçekten cPanel'de mi?

Bu adımı atlama. cPanel'deki Zone Editor'ü düzenlemek, **ancak domain'in
nameserver'ları o hosting'i gösteriyorsa** işe yarar. Nameserver'lar
Cloudflare'de veya registrar'da ise cPanel'de yaptığın değişiklik hiçbir şey
yapmaz — kaydı orada değiştirmen gerekir.

```bash
node scripts/dns-check.mjs domain.com   # çıktıdaki "NS" satırına bak
```

- NS → `ns1.hosting-saglayicin.com` gibi ise → **cPanel Zone Editor** kullan (Adım 3).
- NS → `*.ns.cloudflare.com` ise → kayıtları **Cloudflare** panelinden gir,
  ve Shopify kayıtlarını mutlaka **DNS only (gri bulut)** yap; turuncu bulut
  (proxy) Shopify SSL sertifikasının çıkmasını engeller.

## 3. cPanel adımları (DNS)

**cPanel → Domains → Zone Editor → ilgili domain'in yanında "Manage"**

### 3a. Çakışan kayıtları temizle
Şunlar varsa **sil** (yoksa Shopify kayıtları devreye girmez):
- `@` (kök) için mevcut **A** kaydı — hosting IP'sini gösteren
- `@` için **AAAA** kaydı (IPv6) — **eski sunucuyu gösteriyorsa**. Shopify kendi IPv6'sını (`2620:127:f00f::/48`) verir; Shopify'a ait bir AAAA varsa dokunma.
- `www` için mevcut **A** veya **CNAME** kaydı
- Registrar'ın "parked/redirect" kaydı varsa o da

⚠️ **MX kayıtlarına dokunma.** E-posta cPanel'de kalacaksa MX + `mail.` A kaydı
+ SPF/DKIM TXT kayıtları aynen dursun. Shopify e-posta barındırmaz.

### 3b. Yeni kayıtları ekle

| Type | Name | Value | TTL |
|---|---|---|---|
| `A` | `@` (veya `domain.com.`) | `23.227.38.65` | `14400` |
| `CNAME` | `www` | `shops.myshopify.com.` | `14400` |

> **Doğrula:** Shopify Admin → **Settings → Domains → Connect existing domain**
> akışı sana gösterilecek **tam** A kaydı IP'sini ve CNAME hedefini ekranda
> yazar. Shopify bu değeri zaman içinde güncelleyebiliyor — ekranda yazan
> değer buradaki tablodan farklıysa **ekrandakini** kullan.

> CNAME değerinin sonundaki **nokta** (`shops.myshopify.com.`) önemlidir;
> cPanel bazı sürümlerde nokta yoksa domain'i sonuna ekleyip
> `shops.myshopify.com.domain.com` gibi bozuk bir kayıt üretir.

### 3c. Domain cPanel'de "Addon/Subdomain" olarak duruyorsa
cPanel → **Domains** listesinde bu domain bir document root ile duruyorsa,
DNS artık Shopify'ı gösterdiği için o klasör zaten kullanılmayacak. Karışıklık
olmaması için domain'i cPanel'den kaldırabilirsin — **ama önce** e-posta
hesapları o domain'e bağlıysa onları kaybetmemeye dikkat et.

## 4. Shopify Admin adımları

1. **Settings → Domains → Connect existing domain**
2. Domain'i yaz → **Next** → **Verify connection**
   - "Verification failed" alırsan panik yok: DNS yayılması 1–48 saat sürebilir.
     `node scripts/dns-check.mjs domain.com` ile kayıtların doğru göründüğünü
     teyit et, sonra tekrar dene.
3. Bağlantı doğrulanınca Shopify **ücretsiz SSL** sertifikasını otomatik alır
   (genelde dakikalar, bazen 48 saate kadar). "SSL pending" görmen normaldir.
4. **Set as primary** ile ana domain yap; "Redirect all traffic to this domain"
   kutusunu işaretle ki `www` ve eski domain buraya yönlensin.

## 5. Temayı yükleme (zip'in gerçek yeri)

1. Shopify Admin → **Online Store → Themes**
2. **Add theme → Upload zip file** → bu zip'i seç
3. Yüklendikten sonra **Preview** ile kontrol et
4. Sorun yoksa **Publish**

Not: Tema export'u **ürünleri, koleksiyonları, sayfaları ve resimleri
içermez** — sadece şablon + tema ayarlarını taşır. Bu yüzden başka bir
mağazaya yüklersen tasarım gelir, içerik boş görünür.
`templates/*.context.*.json` dosyaları Markets'e özel (Aserbaycan, DACH, AB,
Rusya, İsveç) varyantlardır; hedef mağazada aynı market'ler tanımlı değilse
o varyantlar devreye girmez.

## 6. Sık karşılaşılan hatalar

| Belirti | Sebep / Çözüm |
|---|---|
| Domain hâlâ eski siteyi gösteriyor | Tarayıcı/ISP cache. Gizli pencere + `node scripts/dns-check.mjs domain.com` ile gerçek kaydı gör. |
| Shopify "verification failed" diyor | Eski A/AAAA kaydı silinmemiş, ya da NS başka sağlayıcıda. |
| SSL "pending" takılı kaldı | Cloudflare proxy (turuncu bulut) açık, ya da CAA kaydı Let's Encrypt'i engelliyor. Proxy'yi kapat / CAA kaydını düzelt. |
| `www` çalışıyor, kök domain çalışmıyor | `@` için A kaydı eksik veya CNAME olarak girilmiş (kök domain CNAME olamaz). |
| Bazı kullanıcıda eski site açılıyor | Eski **AAAA** kaydı duruyor; IPv6'lı bağlantılar eski sunucuya düşüyor. |
| cPanel'de kaydı değiştirdim, hiçbir şey olmadı | Domain'in NS'leri cPanel hosting'i göstermiyor (Adım 2). Kaydı gerçek DNS panelinden gir. |
| E-postalar gelmiyor oldu | MX kayıtları silinmiş. cPanel'de MX + SPF (`v=spf1 ...`) geri ekle. |
