# ChatHelp — Instagram Tam Otomatik Paylaşım (Kurulum)

Bir kez kurulur, sonra **her gün kendi çalışır**: cron her dil için konu bankasından
sıradaki reel'i Instagram'a atar. Kod hazır — sana düşen tek seferlik kurulum aşağıda.

> Instagram'a otomatik post **yalnızca resmi Graph API** ile verilir. Kendi hesabın
> (@chathelpp) olduğu için Meta App'i **Development modunda** kullanabilirsin — tam
> "App Review" gerekmez (hesabın admin/test kullanıcısı olman yeterli).

---

## 0) Ön koşul (5 dk)
1. Instagram'da **@chathelpp → Business/Creator hesabı** yap.
2. Bir **Facebook Sayfası** oluştur ve Instagram'ı ona bağla (Meta Business Suite → Ayarlar → Bağlı hesaplar).

## 1) Meta App oluştur
1. <https://developers.facebook.com/apps> → **Create App** → tür: **Business**.
2. Uygulamaya **Instagram Graph API** ürününü ekle.
3. **App ID** ve **App Secret**'ı not al (App → Settings → Basic).

## 2) IG User ID + uzun ömürlü token al
**Graph API Explorer** ile (<https://developers.facebook.com/tools/explorer>):
1. Sağ üstten uygulamanı seç → **Generate Access Token**. İzinler:
   `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`, `business_management`.
2. `GET /me/accounts` → Facebook Sayfanı ve **page id**'yi gör.
3. `GET /{page-id}?fields=instagram_business_account` → dönen **id = IG User ID** (bunu `config.php` `ig_user_id`'e yaz).
4. **Uzun ömürlü token** (60 gün) al — tarayıcıda şu URL'yi aç:
   ```
   https://graph.facebook.com/v21.0/oauth/access_token?grant_type=fb_exchange_token&client_id=APP_ID&client_secret=APP_SECRET&fb_exchange_token=KISA_TOKEN
   ```
   Dönen `access_token` değerini **`token.txt`** içine tek satır yapıştır.

## 3) Dosyaları sunucuya koy
1. `marketing/auto-post/` klasörünü sunucuna, **web kökünün DIŞINA** yükle
   (ör. `/home/KULLANICI/auto-post/`) — böylece `token.txt` tarayıcıdan erişilemez.
2. Sırları oluştur (repoda yok):
   ```
   echo 'UZUN_OMURLU_TOKEN' > token.txt      && chmod 600 token.txt
   echo 'APP_SECRET'         > app_secret.txt && chmod 600 app_secret.txt
   ```
3. `config.php` içinde `ig_user_id` ve `app_id`'yi doldur.

## 4) Reel'leri public URL'ye yükle
1. Web kökünde `reels/` klasörü aç: `public_html/reels/`
2. Sana gönderdiğim 6 MP4'ü oraya yükle:
   `reel2-de.mp4, reel2-en.mp4, reel2-fr.mp4, reel2-tr.mp4, reel2-es.mp4, reel2-ru.mp4`
3. Kontrol: tarayıcıda `https://chat-help.com/reels/reel2-de.mp4` açılmalı (200).
   `content.json` içindeki URL'ler buna göre ayarlı.

## 5) Test et (elle)
```
php /home/KULLANICI/auto-post/schedule.php de
```
→ Birkaç saniye sonra @chathelpp'te DE reel'i yayınlanmalı. `post.log`'a bak.

## 6) Cron kur
`cron.txt` içindeki satırları cPanel → **Cron Jobs**'a ekle (yolu/saatleri düzelt).
Her dil günde 1 → tek hesapta günde 6, her biri bölgesinin prime-time'ında.
Ayrıca ayda bir `refresh-token.php` (token'ı taze tutar).

---

## Sonrası: "her gün farklı konu"
`content.json`'a her yeni konsept için 6 satır (6 dil) eklenir; cron otomatik sıradakine
geçer, banka bitince başa döner. Yeni konseptlerin reel'lerini ben üretip sana veririm
(planlanan konular `content.json` → `planned_topics`).

## Güvenlik
- `token.txt` / `app_secret.txt` **repoya girmez** (.gitignore'da) ve **web kökü dışında** durur.
- Token sızarsa: Meta App → Settings → **Reset App Secret** ve yeni token üret.
- Rate limit: Graph API 24 saatte 25 post — günde 6 rahat sığar.
