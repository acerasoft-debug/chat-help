# ChatHelp — Instagram Görselleri (Reel + Karusel)

ChatHelp'in çalışma sistemini anlatan sosyal medya varlıkları. Anlatım **Türkçe**
(alt yazılar gömülü), ürünün ürettiği **belge Almanca** gösteriliyor — yani
"sen kendi dilinde anlat, o kusursuz Almanca resmî belgeyi yazsın" mesajı.

## İçindekiler

| Dosya | Ne | Ölçü |
|---|---|---|
| `chathelp-reel.mp4` | **Açıklayıcı Reel/video** — 35 sn, doğrudan Instagram'a yüklenir | 1080×1920 (9:16), H.264, 30 fps |
| `carousel/01-kapak.png` | Karusel 1 — Kanca: "Almanca resmî yazı mı geldi?" | 1080×1350 (4:5) |
| `carousel/02-sorun.png` | Karusel 2 — Sorun: anlaşılmayan Bescheid + itiraz süresi | 1080×1350 |
| `carousel/03-anlat.png` | Karusel 3 — 1. adım: kendi dilinde anlat, belgeni yükle | 1080×1350 |
| `carousel/04-analiz.png` | Karusel 4 — 2. adım: **güçlü yapay zekâlar birlikte çalışır** | 1080×1350 |
| `carousel/05-sonuc.png` | Karusel 5 — 3. adım: gönderime hazır Almanca belge | 1080×1350 |
| `carousel/06-cta.png` | Karusel 6 — CTA: chat-help.com | 1080×1350 |
| `CAPTIONS.md` | Hazır açıklama metinleri (TR + DE) + hashtag'ler | — |
| `scene.html` | Reel animasyonunun **kaynağı** (düzenlenebilir) | — |
| `carousel.html` | Karusel slaytlarının **kaynağı** (düzenlenebilir) | — |
| `render.mjs` / `carousel.mjs` | Kaynaktan yeniden üretim script'leri | — |

## Anlatılan sistem (koddaki gerçek akışa dayanır — `chat/fall-api.php`)

1. **Anlat (`fall_chat`)** — kullanıcı derdini anlatır, belge/fotoğraf yükler; YZ belgeyi
   okuyup (tarih, süre, dosya no) tam anlayana kadar soru sorar.
2. **Analiz (`fall_solve`)** — birden çok uzman YZ birlikte çalışır:
   Hukuk Analizi → Strateji → Belge Tipi & Argümanlar → **Belge Yazımı**.
3. **Sonuç** — gönderime hazır, doğru mektup formunda resmî Almanca belge (PDF / e-posta / kopyala).
   Kişisel veriler yalnızca sunucuda birleştirilir, 3. taraflara gönderilmez.

> Marka kararına uygun olarak sağlayıcı adları (ör. arka plandaki modeller) **gösterilmez**;
> hepsi "ChatHelp KI" çatısı altında, görev bazlı sunulur.

## Yeniden üretmek / düzenlemek

Metni/renkleri değiştirmek için `scene.html` (Reel) veya `carousel.html`'i düzenleyin,
sonra yeniden render alın. Gerekli araçlar: Node.js + `playwright-core` + `ffmpeg-static`
(Chromium sistemde kurulu).

```bash
npm install playwright-core ffmpeg-static

# Reel (kare kare yakalar + H.264 MP4'e kodlar)
node render.mjs full chathelp-reel.mp4

# Anahtar kareleri hızlı önizleme (test/ klasörüne)
node render.mjs test

# Karusel PNG'leri (carousel/ klasörüne)
node carousel.mjs
```

- `scene.html` deterministik bir zaman ekseniyle çalışır: `window.renderFrame(ms)` her kareyi
  saf olarak çizer, bu yüzden kare-kare yakalama birebir tekrarlanabilir.
- Tarayıcıda `scene.html`'i doğrudan açarsanız animasyon otomatik oynar ve döngüye girer
  (telefonda tam ekran açıp **ekran kaydı** ile de video alabilirsiniz — MP4'e alternatif).

## Marka

Altın gradyan `#F6E2A6 → #D4A84A → #B0801F`, antrasit zemin `#07070e/#12121d`,
amblem = sohbet balonu içinde adalet terazisi (bkz. `brand/`). Serif başlık, sans gövde.
