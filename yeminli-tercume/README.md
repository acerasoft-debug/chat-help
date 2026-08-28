# Mühür — Noter Onaylı Yeminli Tercüme Web Sitesi

Türkiye'de ve yurt dışında — Almanya, Belçika, Fransa, İspanya, İtalya,
Birleşik Krallık, ABD, Kanada, Japonya, Çin ve Avustralya'da — yaşayanlara
noter onaylı yeminli tercüme hizmeti sunan premium tanıtım sitesi.

## Özellikler

- **6 dilde arayüz** — Türkçe (varsayılan), İngilizce, Almanca, Fransızca,
  İspanyolca, İtalyanca. Sağ üstteki TR/EN/DE/FR/ES/IT düğmeleriyle geçiş;
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
404.html                    Bulunamadı sayfası (GitHub Pages otomatik kullanır)
robots.txt                  Arama motoru izinleri
assets/css/style.css        Tasarım (renk/tipografi token'ları en üstte)
assets/js/i18n.js           6 dilin tüm metinleri (çeviri sözlükleri)
assets/js/main.js           Dil değiştirme, menü, form, animasyonlar
assets/favicon.svg          Sekme simgesi (mühür)
assets/og.png               Sosyal medya paylaşım görseli (1200×630)
```

> **Alan adı aldığınızda:** `index.html` ve `sirket-kurulumu/index.html`
> içindeki `og:image` adreslerini tam URL yapın
> (`https://alanadiniz.com/assets/og.png`) — WhatsApp/Facebook paylaşım
> önizlemeleri ancak tam adresle çalışır.

## Yayına almadan önce doldurulacak yer tutucular

| Yer tutucu | Nerede | Ne yazılmalı |
|---|---|---|
| `+90 532 000 00 00` / `wa.me/905320000000` | `index.html` (2 yerde) | Gerçek WhatsApp numaranız |
| `info@muhurtercume.com` | `index.html` ve `assets/js/main.js` | Gerçek e-posta adresiniz |
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

## Yerelde çalıştırma

Herhangi bir statik sunucu yeterli:

```bash
python3 -m http.server 8080
# http://localhost:8080
```
