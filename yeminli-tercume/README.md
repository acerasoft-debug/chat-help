# Mühür — Noter Onaylı Yeminli Tercüme Web Sitesi

Türkiye'de ve yurt dışında — Almanya, Belçika, Fransa, İspanya, İtalya,
Birleşik Krallık, ABD, Kanada, Japonya, Çin ve Avustralya'da — yaşayanlara
noter onaylı yeminli tercüme hizmeti sunan premium tanıtım sitesi.

## Özellikler

- **6 dilde arayüz** — Türkçe (varsayılan), İngilizce, Almanca, Fransızca,
  İspanyolca, İtalyanca. Masaüstünde sağ üstteki dil düğmesi (ör. „TR ▾")
  altı dilin listesini açar; mobil menüde TR/EN/DE/FR/ES/IT düğmeleriyle geçiş;
  seçim tarayıcıda hatırlanır, ilk ziyarette tarayıcı diline göre açılır.
- **Tamamen statik** — sunucu, veritabanı ya da derleme adımı gerekmez.
  GitHub Pages, Netlify, Vercel veya herhangi bir hosting'e olduğu gibi
  yüklenebilir.
- **Açık/koyu tema** — ziyaretçinin sistem tercihine göre otomatik.
- **Mobil uyumlu** — telefon, tablet ve masaüstünde test edilmiş responsive
  tasarım.

## Dosya yapısı

```
index.html                  Ana sayfa (yeminli tercüme)
sirket-kurulumu/index.html  Şirket kurulumu sayfası (DE, UK, FR, ABD)
vize-oturum/index.html      Vize, oturum, vatandaşlık ve şirket işlemleri
                            (Türkiye'deki yabancılar + Avrupa'daki Türkler)
gizlilik/index.html         Gizlilik politikası ve künye (KVKK/GDPR)
404.html                    Bulunamadı sayfası (GitHub Pages/Netlify otomatik; cPanel/Apache için .htaccess gerekir)
.htaccess                   Apache/cPanel: bulunamayan adreslerde 404.html'i gösterir
robots.txt                  Arama motoru izinleri (+ site haritası adresi)
sitemap.xml                 Site haritası (ana sayfa, şirket kurulumu, vize & oturum)
assets/css/style.css        Tasarım (renk/tipografi token'ları en üstte)
assets/js/i18n.js           6 dilin tüm metinleri (çeviri sözlükleri)
assets/js/main.js           Dil değiştirme, menü, form, animasyonlar
assets/favicon.svg          Sekme simgesi (mühür)
assets/apple-touch-icon.png iOS ana ekran simgesi (180×180)
assets/og.png               Sosyal medya paylaşım görseli (1200×630)
```

> **Alan adı aldığınızda:** `index.html`, `sirket-kurulumu/index.html` ve
> `vize-oturum/index.html` içindeki `muhurtercume.com` adreslerini (canonical,
> `og:url`, `og:image`, JSON-LD) ve `sitemap.xml` / `robots.txt`'yi kendi alan
> adınızla değiştirin — WhatsApp/Facebook paylaşım önizlemeleri ancak doğru
> tam adresle çalışır.

## Yayına almadan önce doldurulacak yer tutucular

| Yer tutucu | Nerede | Ne yazılmalı |
|---|---|---|
| `+90 532 000 00 00` / `wa.me/905320000000` | tüm sayfalar ve JSON-LD (`grep -rn -e 905320000000 -e "532 000 00 00" .`) | Gerçek WhatsApp numaranız |
| `info@muhurtercume.com` | tüm sayfalar ve `assets/js/main.js` | Gerçek e-posta adresiniz |
| `[Mühür Tercüme — ticari unvan]`, `[Adres satırı…]`, `[Vergi dairesi / numarası]` | `gizlilik/index.html` | Künye: unvan, adres, vergi bilgisi (yasal zorunluluk) |
| `[MERSİS ve ticaret sicil numarası]`, `[KEP adresi]`, `[Yemin zaptının bulunduğu noterlik]` | `gizlilik/index.html` | Künye: MERSİS/ticaret sicil no (TTK 1524), KEP adresi, tercümanların yemin zaptının bulunduğu noterlik |
| `muhurtercume.com` (canonical, og:url, og:image, sitemap.xml, robots.txt, JSON-LD) | `index.html`, `sirket-kurulumu/index.html`, `vize-oturum/index.html`, `sitemap.xml`, `robots.txt` | Kendi alan adınız |
| `t1–t4` örnek senaryolar | `index.html` NO. 08, `assets/js/i18n.js` | Yalnızca gerçek ve yazılı onaylı müşteri yorumlarıyla değiştirin; o zaman `testi.eyebrow`'u 'Referanslar' yapıp `testi.note`'u kaldırın |
| `MÜHÜR` marka adı | `index.html`, `assets/js/i18n.js` | Kendi marka adınız (isterseniz) |
| `EST. 2026` | `index.html` (mühür görseli) | Kuruluş yılınız |

Metinleri değiştirmek için `assets/js/i18n.js` içindeki ilgili dilin
anahtarını düzenlemeniz yeterli — HTML'e dokunmanız gerekmez.

## Formdaki belge yükleme nasıl "gerçek" olur?

Teklif formunda sürükle-bırak belge yükleme alanı var (tüm formatlar).
Site statik olduğu için dosyaların size ulaşması iki şekilde çalışır:

1. **Şu anki durum (servis bağlı değil):** Form ziyaretçinin e-posta
   uygulamasını açar; seçtiği dosyaların adları mesaja yazılır ve
   ziyaretçiye dosyaları e-postaya eklemesi hatırlatılır.
2. **Önerilen kurulum (5 dakika):** [formspree.io](https://formspree.io)
   (veya Web3Forms/Getform) üzerinden ücretsiz bir form oluşturun ve size
   verilen adresi `index.html` içindeki forma ekleyin:

   ```html
   <form class="quote-form" id="quoteForm" data-endpoint="https://formspree.io/f/XXXXXXXX">
   ```

   Bu tek satırla form, dosyalarla birlikte doğrudan e-posta kutunuza
   düşer; sayfa içinde "Talebiniz alındı" onayı gösterilir (6 dilde hazır).

   > `data-endpoint` ile bir form servisi (ör. Formspree) bağlarsanız,
   > sağlayıcının adını `assets/js/i18n.js` içindeki `privacy.p7`'ye
   > (gizlilik sayfası, "Üçüncü taraf hizmetler") 6 dilde ekleyin.

## Bu site bağımsızdır

Bu klasör kendi başına çalışan, tamamen statik bir sitedir. Aynı depodaki
diğer projelerle (VESTRA / chat-help) **hiçbir ortak dosyası, kodu veya
dağıtımı yoktur** ve olmamalıdır.

> ⚠️ **Yayına alırken dikkat:** Bu siteyi kendi alan adının kendi kök
> dizinine yükleyin. Başka bir projenin (ör. VESTRA / vestrasales.com)
> `public_html` dizinine **yüklemeyin** — o sitenin ana sayfasının üzerine
> yazarsınız. En temizi: bu site için ayrı bir hosting hesabı ya da
> cPanel'de ayrı bir **Addon Domain** açmak; o zaman kök dizin
> `public_html/alanadiniz.com` gibi kendine ait bir klasör olur.

## cPanel'e yükleme (hosting)

Site tamamen statiktir; PHP, veritabanı veya özel ayar gerektirmez.
Aşağıda "kök dizin" derken **bu sitenin kendi alan adının** kök dizini
kastediliyor (ayrı hesapta `public_html`, addon domain'de
`public_html/alanadiniz.com`).

### Yöntem 1 — ZIP ile (en kolay, 5 dakika)

1. Bu klasörün içeriğini ZIP'leyin — gizli `.htaccess` dosyası da pakette olmalı.
2. cPanel → **File Manager** → sitenin kök dizinine girin.
3. **Upload** ile ZIP'i yükleyin, sonra dosyaya sağ tıklayıp **Extract** deyin.
   Gizli `.htaccess` dosyasının da çıktığını kontrol edin (File Manager →
   Settings → **Show Hidden Files**); 404 sayfası onunla çalışır.
4. ZIP'i silin. Site `https://alanadiniz.com` adresinde yayında.

### Yöntem 2 — FTP ile

1. cPanel → **FTP Accounts** → hesap oluşturun; dizin olarak **bu sitenin**
   kök dizinini seçin.
2. FileZilla'ya sunucu adresi (genelde `ftp.alanadiniz.com`), kullanıcı adı
   ve şifreyle bağlanın.
3. Bu klasörün içeriğini o dizine sürükleyin — gizli `.htaccess` dosyası
   dahil (FileZilla: Sunucu → **Gizli dosyaları göstermeye zorla**).

### Otomatik dağıtım (Git) hakkında

Bilerek kurulmadı. cPanel'in Git dağıtımı depo genelinde çalışır ve bu depo
başka projeleri de barındırdığı için yanlış bir kök dizine yazma riski taşır.
Otomatik dağıtım isterseniz doğru yol, bu klasörü **kendi deposuna** taşıyıp
o depoyu sitenin kendi hosting hesabına bağlamaktır.

Yayın sonrası kontrol listesi:
- cPanel → **SSL/TLS Status** ile ücretsiz SSL'i (AutoSSL) çalıştırın.
- `index.html`, `sirket-kurulumu/index.html` ve `vize-oturum/index.html`
  içindeki `muhurtercume.com` adreslerini (canonical, `og:url`, `og:image`,
  JSON-LD) kendi alan adınızla değiştirin; `sitemap.xml` ve `robots.txt`
  için de aynısını yapın.
- Var olmayan bir adres açın (`/deneme/`) ve 404 sayfasının stiliyle
  geldiğini doğrulayın (`.htaccess`).

## Yerelde çalıştırma

Herhangi bir statik sunucu yeterli:

```bash
python3 -m http.server 8080
# http://localhost:8080
```
