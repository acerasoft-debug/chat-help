# MAXSALES — Premium Retail

Perakende (B2C) mağaza katmanı: vitrin, ürün sayfası, **Premium Outlet (Vault)**,
sepet, Stripe ödeme, satıcı kaydı (Anmeldung) ve Stripe **Connect** ile satıcı
ödemeleri — özel satıcılar (Privatverkäufer) dahil. İşletmeci: **Acerasoft LLC**.

Ayrıca: Merkliste, son bakılanlar, görsel büyüteci, arama önerileri, Vault fiyat
alarmı, Größenberatung, Kontakt formu, Journal ve arama sunan 404 sayfası.

Bağımlılık yok: composer yok, npm yok, veritabanı yok. PHP 8.1+ ve `curl` yeter.
Canlı B2B kataloğunu (`data/listings.json`) **salt okunur** kullanır, ona hiçbir
koşulda yazmaz.

---

## 1. Hızlı kurulum

```bash
# 1) dosyaları sunucuya kopyala (örnek: alt klasör kurulumu)
rsync -az --delete retail/ user@host:~/public_html/shop/

# 2) veri dizini
ssh user@host 'mkdir -p ~/public_html/data && chmod 750 ~/public_html/data'

# 3) kontrol
ssh user@host 'cd ~/public_html/shop && php tools/selftest.php'
```

`selftest.php` yolları, yazma izinlerini, katalogu, Vault planlarını, Stripe ve
e-posta ayarlarını, Impressum eksiklerini ve dil dosyalarını tek tek raporlar.
**FAIL satırı kalmadan canlıya çıkma.**

### Nereye kurulur?

Kod yol-bağımsızdır; hiçbir yere sabit URL yazılı değil.

| Senaryo | Yol | `data/` |
|---|---|---|
| Alt klasör | `public_html/shop/` | üstteki `public_html/data/` paylaşılır (B2B kataloğu görünür) |
| Kök | `public_html/` | `public_html/data/` |
| Ayrı vhost | `/var/www/maxsales/` | `/var/www/maxsales/data/` |

Otomatik tespit yetmezse ortam değişkenleriyle sabitle:

```
VR_DATA_DIR=/home/user/public_html/data
VR_DOC_ROOT=/home/user/public_html      # /uploads/... yollarının kökü
VR_BASE_URL=/shop                       # URL ön eki ('' = kök)
VR_ORIGIN=https://maxsales.com
```

---

## 2. Stripe kurulumu

### 2.1 Anahtarlar

Anahtar **koda yazılmaz**. İki kaynaktan biri:

```bash
# A) sunucuda dosya (kalıcı, önerilen)
cat > ~/public_html/data/stripe_settings.json <<'JSON'
{
  "secret_key": "sk_live_...",
  "publishable_key": "pk_live_...",
  "webhook_secret": "whsec_..."
}
JSON
chmod 600 ~/public_html/data/stripe_settings.json
```

```bash
# B) ortam değişkeni (CI/CD)
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Mod otomatik anlaşılır: `sk_test_…` → test, `sk_live_…` → canlı. Test modunda
kasa sayfası bunu açıkça yazar.

### 2.2 Webhook

Stripe Dashboard → **Developers → Webhooks → Add endpoint**

```
https://<host>/<kurulum>/api/webhook.php
```

Dinlenecek olaylar:

| Olay | Ne yapar |
|---|---|
| `checkout.session.completed` | siparişi `paid` yapar, stok düşer, los kapanır, transferler açılır, mailler gider |
| `checkout.session.async_payment_succeeded` | Klarna/SEPA gecikmeli onayı |
| `checkout.session.async_payment_failed` | siparişi `failed` yapar, los rezervasyonunu bırakır |
| `checkout.session.expired` | aynı şekilde temizler |
| `charge.refunded` | iadeyi işler, tam iadede stoğu geri verir |
| `account.updated` | satıcının Connect durumunu tazeler (`charges_enabled`) |

**Webhook secret olmadan hiçbir sipariş "ödendi" olmaz.** Uç nokta imzasız
istekleri 400 ile reddeder; imzalanmamış hiçbir veriye güvenilmez.

Yerel test:

```bash
stripe listen --forward-to https://<host>/<kurulum>/api/webhook.php
stripe trigger checkout.session.completed
```

### 2.3 Connect (satıcı ödemeleri)

Dashboard → **Connect → Get started** (platform profili doldurulmalı).
Kod **Express** hesap açar; satıcı tipine göre:

* Händler → `business_type: company`
* Privatverkäufer → `business_type: individual`

Para akışı duruma göre otomatik seçilir:

| Sepet | Model | Stripe tarafı |
|---|---|---|
| Tek satıcı, Connect hazır | **destination charge** | `transfer_data.destination` + `application_fee_amount` |
| Çok satıcı | **separate charges & transfers** | platformda tahsil, webhook'ta satıcı başına `Transfer` |
| Satıcı henüz doğrulanmadı | pay platformda bekler | siparişte `payout_pending: true`, `transfers[].status = held` |
| Yalnızca kendi stoğumuz | dağıtım yok | — |

Kargo bedeli **platformda kalır**: destination modelinde `application_fee`
komisyona ek olarak kargoyu da içerir (Verkäuferbedingungen §6.2 ile aynı).

### 2.4 Ödeme yöntemleri

`payment_method_types` bilinçli olarak GÖNDERİLMİYOR — hangi yöntemin
görüneceğine Stripe Dashboard'daki ayar karar verir. Kart, Apple Pay, Google
Pay, Klarna ve SEPA'yı **Settings → Payment methods** altından aç.

---

## 3. Yapılandırma

Tüm ayarlar `inc/config.php` içinde varsayılan olarak durur ve
`data/retail-settings.json` ile ezilir (kod güncellemesi ayarları silmez):

```json
{
  "company": {
    "street": "30 N Gould St, Ste R",
    "city": "Sheridan",
    "zip": "82801",
    "state": "Wyoming",
    "country": "United States",
    "reg_authority": "Wyoming Secretary of State",
    "reg_number": "2024-001234567",
    "represented_by": "Vorname Nachname",
    "email": "support@maxsales.com",
    "phone": "+1 ..."
  },
  "retail_multiplier": 2.4,
  "fee_bps_business": 1200,
  "fee_bps_private": 900,
  "fee_bps_outlet": 1500,
  "vault_steps": 6,
  "vault_step_hours": 24
}
```

> Yukarıdaki adres **örnektir**. Gerçek Acerasoft LLC verileri girilene kadar
> Impressum ve AGB sayfaları görünür bir uyarı basar ve eksik alanları
> `[köşeli parantez]` içinde gösterir. Uydurma veri yazılmadı — bilinçli tercih.

Sık kullanılanlar:

| Anahtar | Ne yapar |
|---|---|
| `retail_multiplier` | B2B birim fiyattan perakende fiyat türetme katsayısı (satırda `retail_price` varsa o kazanır) |
| `fee_bps_*` | komisyon, baz puan (1200 = %12) |
| `vault_steps` / `vault_step_hours` | Vault planının varsayılanı |
| `return_days` / `withdrawal_days` | gönüllü iade / yasal cayma süresi |
| `shipping` | bölge başına kargo, ücretsiz eşiği, süre |
| `demo_catalog` | `listings.json` yoksa örnek katalog göster (canlıda `false` yap) |
| `stripe_tax` | `true` → vergiyi Stripe Tax hesaplasın |

---

## 4. Katalog ve fiyat

### vestrasales.com ürünleri buraya nasıl gelir?

**Aynı sunucuya kurulduysa: kendiliğinden.** Vitrin `~/public_html/data/listings.json`
dosyasını doğrudan okur — canlı toptan katalogdaki HER ürün, perakende fiyatıyla
vitrine düşer. İçe aktarma, kopyalama, senkron işi yok; toptan tarafta fiyat
değişince perakende de değişir.

**Ayrı sunucuya kurulduysa** (ya da donmuş bir kopya isteniyorsa):

```bash
# aynı sunucuda, dosyadan (en hızlısı)
php tools/import-live.php --from=~/public_html --images

# başka sunucudan, HTTPS üzerinden
php tools/import-live.php --from=https://vestrasales.com --images

# önce ne olacağını gör
php tools/import-live.php --from=~/public_html --dry
```

Actions'tan da çalıştırılabilir: **Canlı katalogu perakendeye aktar**.
Sonuç `data/retail-imported.json` dosyasına yazılır; canlı `listings.json`'a
dokunulmaz.

### Fiyat: ×3

`retail_multiplier` **3.00**. Toptan birim fiyat üçle çarpılır ve 10 €'luk
ızgarada "…9,00" ile biten en yakın değere yuvarlanır:

| Toptan | ×3 | Vitrin |
|---|---|---|
| 59,90 € | 179,70 € | **179,00 €** |
| 89,00 € | 267,00 € | **269,00 €** |
| 119,00 € | 357,00 € | **359,00 €** |
| 149,00 € | 447,00 € | **449,00 €** |

Çarpanı değiştirmek tek satır: `data/retail-settings.json` → `"retail_multiplier": 3.0`.
Tek bir ürüne özel fiyat isterseniz o satıra `retail_price` yazın — çarpanı ezer.

### Ürün fotoğrafları

* **Canlı sitenin kendi fotoğrafları** (`/uploads/...`) içe aktarımda birlikte
  gelir (`--images`). Ürün başına kaç fotoğraf varsa hepsi taşınır ve ürün
  sayfasında galeri + büyüteç olarak gösterilir.
* **Ek fotoğraf** eklemek için URL listesi verin:

  ```json
  { "blm-ah0eg000bc27": ["https://…/1.jpg", "https://…/2.jpg"],
    "AH1EG010": ["https://…/detay.jpg"] }
  ```

  ```bash
  php tools/import-live.php --from=~/public_html --images --extra=extra.json
  ```

  Anahtar ürün id'si veya SKU olabilir. İndirilen dosya gerçekten görsel mi diye
  kontrol edilir, `/uploads/imported/` altına rastgele adla yazılır ve klasörde
  PHP yürütme kapatılır.

> **Telif uyarısı:** Araç internette kendiliğinden fotoğraf ARAMAZ. Marka
> sitelerinden ya da başka perakendecilerden alınan ürün fotoğrafları o
> sitelerin/fotoğrafçıların telifindedir; kendi mağazanızda yayınlamak
> uyarı-ihtar (Abmahnung) riski demektir. `--extra` listesine yalnızca kendi
> çektiğiniz veya tedarikçinizin size kullanım hakkıyla verdiği görselleri
> koyun. Tedarikçi fotoğrafları zaten canlı sitede `/uploads/` altında duruyor —
> ilk madde onları getiriyor.

* **Fotoğrafsız satır** boş kalmaz: kategoriye göre çizilen bir giysi silueti
  üretilir (`assets/art.php`) — tişört, hoodie, jean, mont, çanta, ayakkabı…

* **B2B satırları** (`data/listings.json`) salt okunur okunur. Perakende fiyat:
  `retail_price` varsa o, yoksa `list × retail_multiplier`, "…9,00" ile biten
  cazip fiyata yuvarlanır.
* **Beden/stok**, B2B satırındaki `"sizes"` metninden ayrıştırılır:
  `"S×1 · M×3 · L×3 · 10/pack"` → S:1, M:3, L:3 (paket bilgisi otomatik elenir).
* **Satılan adetler** `data/retail-stock.json` defterine yazılır ve katalog
  okunurken düşülür — `listings.json` böylece hiç değişmez.
* **Satıcı ürünleri** `data/retail-listings.json` içinde; yazma yalnızca oraya.

---

## 5. Premium Outlet (Vault)

Her los = tek parça + yayınlanmış bir düşüş planı (Hollanda usulü eksiltme).

```bash
php tools/vault-open.php find balmain                 # ürün ara
php tools/vault-open.php open <urun-id> 349 179 6 24  # aç: 349€ → 179€, 6 kademe, 24 saatte bir
php tools/vault-open.php list                         # canlı durum
php tools/vault-open.php close <lot-id>               # kapat
```

Kurallar koda gömülü:

* Plan **açılışta yazılır, bir daha değişmez.** Tabanı sonradan yükseltebilen
  bir arayüz kasten yok — sahte indirim tam olarak öyle doğar.
* Fiyat **sunucuda** hesaplanır, ziyaretçiye göre değişmez, tarayıcıya
  güvenilmez. Geri sayım bitince sayfa tazelenir, fiyatı yine sunucu söyler.
* Sepete eklenen los 20 dakika **rezerve** edilir ve fiyat sabitlenir.
* **PAngV §11**: indirim duyurulduğunda son 30 günün en düşük fiyatı gösterilir.
  Plan monoton azaldığı için bu, bir önceki kademenin fiyatıdır. **1. kademede
  indirim yoktur** → hiçbir referans fiyat/yüzde basılmaz.

Doğrulaması: `php tools/test-flow.php` (62 test, Stripe'a istek atmaz).

---

## 6. Vitrin özellikleri

| Özellik | Nerede | Nasıl çalışır |
|---|---|---|
| **Merkliste** | kart + ürün sayfasındaki kalp | Çerezde yalnızca ürün id'si; sunucuda profil yok. JS varsa sayfa yenilenmeden, yoksa form gönderimiyle. |
| **Son bakılanlar** | ürün + katalog sayfası altı | Son 12 ürün, 30 gün, yine sadece çerez. |
| **Görsel büyüteci** | ürün sayfası | Her görsel dosyaya giden normal bir bağlantı; JS varsa Esc/←/→ ile gezilen katman. |
| **Arama önerileri** | üst çubuk | `api/suggest.php`, 220 ms gecikmeli, klavyeyle gezilebilir. Sonuç yoksa marka önerir. JS kapalıysa kutu normal form olarak çalışır. |
| **Vault fiyat alarmı** | los sayfası | "Şu fiyata inince haber ver" — **tek** e-posta, sonra alarm silinir. Eşik güncel fiyatın altında ve tabanın üstünde olmak zorunda. |
| **Größenberatung** | `size-guide.php` | IT/DE/FR/UK-US dönüşüm tabloları, ölçüm talimatı, ev bazında kalıp notları. Ürün sayfasındaki beden başlığından ilgili sekmeye açılır. |
| **Kontakt** | `contact.php` | Gerçekten gönderir; kopyası `retail-messages.json`'a yazılır. Spam koruması bal küpü + zaman ölçümü (dış captcha YOK). |
| **Journal** | `journal.php` | Beş editoryal yazı (herkunft, Vault mekaniği, bakım, kalıp, privat/gewerblich) — DE + EN. |
| **404** | `404.php` | Adresteki kelimelerden ürün önerir, arama kutusu ve canlı Vault losları sunar. |

### Fiyat alarmı — işletme tarafı

```bash
php tools/vault-alerts.php --dry     # kimseye yazmadan ne olacağını göster
php tools/vault-alerts.php           # gönder (saatte bir cron)
php tools/vault-alerts.php list      # kurulu alarmlar
```

Zamanlama: `.github/workflows/vault-alerts.yml` (saat başı). Her alarm en fazla
**bir** posta üretir; hatırlatma/"son şans" postası üretecek bir kod yolu yok.

---

## 7. Satıcılar (Anmeldung)

Akış: `sell.php` → `seller/register.php` → `seller/payouts.php` (Stripe Connect)
→ `seller/listing.php` → moderasyon → canlı.

```bash
php tools/moderate.php list review        # bekleyen ilanlar
php tools/moderate.php show <id>          # detay + görsel kontrolü + satıcı geçmişi
php tools/moderate.php approve <id>
php tools/moderate.php reject <id> "Grund"
php tools/moderate.php sellers            # tüm satıcılar + Connect durumu
```

Her yeni/değişen ilan `review` durumuna düşer; onaysız hiçbir satır vitrine
çıkmaz. Görseller `seller/listing.php`'den yüklenir:
`getimagesize()` ile içerik doğrulanır, GD varsa **yeniden kodlanır** (EXIF/GPS
temizlenir, polyglot yük yok edilir), rastgele adla `/uploads/retail/<satıcı>/`
altına yazılır ve klasöre PHP çalıştırmayı kapatan `.htaccess` konur.

**nginx kullanıyorsan** `.htaccess` okunmaz — şu blokları ekle:

```nginx
location ~* /uploads/.*\.(php|phtml|phps|pl|py|cgi|sh)$ { deny all; }
location ~ /(data|tools)/ { deny all; }
location = /robots.txt  { rewrite ^ /shop/robots.php  last; }
location = /sitemap.xml { rewrite ^ /shop/sitemap.php last; }
```

### Privat vs. gewerblich

Ayrım her yerde taşınır: ürün kartı, ürün sayfası, sepet, kasa onayı, sipariş
maili ve AGB. Privatverkäufer'de cayma hakkı yoktur ve gewährleistung
dışlanabilir — alıcı bunu **ödeme öncesinde** ayrıca onaylar.

---

## 8. Hukuki sayfalar

`legal/` altında, Almanca (bağlayıcı dil):

`impressum` · `agb` · `widerruf` (+ Muster-Widerrufsformular) · `rueckgabe` ·
`versand` · `zahlung` · `datenschutz` · `cookies` · `verkaeufer`
(Verkäuferbedingungen) · `streitbeilegung` (DSA md. 16 bildirim usulü dahil) ·
`barrierefreiheit` (BFSG)

Sayısal değerler (kargo, komisyon, süreler) **yapılandırmadan** okunur; ayarı
değiştirdiğinde hukuki metin de değişir, ikisi ayrışmaz.

> Metinler işler durumda ve pazaryeri modeline göre yazıldı, ama hukuki
> danışmanlık değildir. Canlıya çıkmadan önce firma verileri, ABD merkezli bir
> LLC'nin Almanya'ya satışında KDV yükümlülüğü ve satıcı sözleşmesinin
> ayrıntıları avukata gösterilmeli.

**Çerez bandı yok — ve gerekmiyor:** izleme yok, analitik yok, dış CDN yok,
Google Fonts yok (tipografi sistem yazı tipleriyle). Yalnızca teknik zorunlu
çerezler kullanılıyor (TDDDG §25 Abs. 2 Nr. 2).

---

## 9. Diller

10 dil: `en` (varsayılan) · `de` · `fr` · `it` · `es` · `ru` · `az` · `da` ·
`sv` · `nl` (Flamanca, hreflang `nl-BE`).

`?lang=de` ile geçilir, seçim çerezde saklanır, `hreflang` etiketleri her sayfada
basılır. Eksik anahtar → İngilizce karşılığı (asla boş/kırık metin).
`en` ve `de` %100; diğerleri vitrin + sepet + kasa + Vault kapsıyor, satıcı
paneli ve hukuki metinler İngilizce/Almanca'ya düşüyor.

Kapsamı görmek için: `php tools/selftest.php` → "Sprachen" bölümü.

---

## Erişilebilirlik (BFSG)

Ana sayfaların **her metin düğümü** gerçek tarayıcıda ölçüldü: gerçekten
render edilen renk, gerçekten render edilen zemine karşı (şeffaflıklar dahil).
Tümü WCAG 2.1 AA eşiğini geçiyor — küçük metin 4,5:1, büyük metin 3:1.

Bunun için iki şey gerekti: koyu bölgelerde (Vault, hero, altlık) grilerin
yeniden tanımlanması ve pirinç/kızıl vurguların açık zeminde daha koyu bir
metin varyantına (`--brass-text`, `--ember-text`) bağlanması. Ölçümü tekrar
etmek istersen `legal/barrierefreiheit.php` neyin nasıl test edildiğini yazıyor.

JavaScript kapalıyken site tam çalışır — beliriş efekti bile
`@media (scripting: enabled)` ile korumalı, yani betik çalışmazsa içerik
gizli kalmaz.

---

## 10. Araçlar

| Komut | Ne yapar |
|---|---|
| `php tools/selftest.php [--stripe]` | kurulum kontrolü; `--stripe` gerçek API çağrısı yapar |
| `php tools/test-flow.php` | 86 akış testi (fiyat planı, komisyon, webhook imzası, idempotency, alarmlar, Merkliste, abonelik iptali) — geçici dizinde çalışır, canlı veriye dokunmaz |
| `php tools/vault-open.php` | Vault losu aç/kapat/listele |
| `php tools/moderate.php` | ilan moderasyonu, satıcı listesi |
| `php tools/vault-alerts.php` | fiyat alarmlarını gönder/listele |
| `php tools/import-live.php` | canlı katalogu + görselleri içe aktar |

Hepsi **yalnızca CLI**; web'den çağrılırsa 403 döner.

---

## 11. Güvenlik notları

* Fiyat hiçbir zaman istemciden gelmez; sepette yalnızca kimlikler tutulur.
* Tüm POST'larda CSRF token; giriş/kayıt/ödeme/newsletter'da hız sınırı.
* Şifreler `password_hash()` (bcrypt); giriş cevabı e-postanın kayıtlı olup
  olmadığını sızdırmaz.
* Sipariş sayfası girişsiz açılır ama `id` + 16 baytlık `token` ister.
* JSON yazma atomik (`rename`), eşzamanlı değişiklikler `flock` ile serileşir.
* CSP başlığı: `default-src 'self'`, dış script/font/görsel yok.
* Webhook: imza + zaman toleransı + işlenen olay id kaydı (çift işleme yok).
* Yüklenen görseller içerik doğrulamasından geçer, yeniden kodlanır (EXIF silinir)
  ve klasörde PHP yürütme kapatılır.
* Kontakt formunda dış captcha yok: bal küpü + zaman ölçümü + hız sınırı.
* Merkliste/son bakılanlar çerezleri okunurken doğrulanır — çöp değer atılır.

---

## 12. Bilinen sınırlar

* Ödemeyi **webhook** kesinleştirir. Webhook secret girilmezse siparişler
  `pending` kalır (dönüş sayfası yedek doğrulama yapar ama tek başına yeterli
  değildir).
* Sipariş/satıcı verisi JSON dosyalarında. Mevcut hacim için fazlasıyla yeterli;
  günde binlerce siparişte veritabanına geçilmeli.
* Kargo etiketi/takip entegrasyonu yok — sendungsnummer elle girilir.
* Fatura PDF'i üretilmiyor; sipariş onayı e-postası metin olarak gidiyor.
