# VESTRA — Faz 0 (Landing + Bekleme Listesi)

Sade PHP. Hosteurope dâhil her paylaşımlı hostingte çalışır. Ekstra kurulum yok.

## Yükleme (chat-help gibi)
1. Hosteurope panelinde yeni domaini bir **klasöre ata** (ör. `vestra` ya da domain kökü).
2. Şu dosyaları o klasöre yükle (FTP / dosya yöneticisi):
   - `index.php`
   - `join.php`
   - `data/.htaccess`  (klasörüyle birlikte)
3. **SSL**'i aç (Hosteurope Let's Encrypt — ücretsiz).
4. Bitti. Sayfayı aç, formu test et.

## Markayı/ayarları değiştir
- `index.php` en üstteki satırlar: `$BRAND`, `$CONTACT`, `$COMPANY`, `$ACCENT`.
- `$COMPANY` alanına **gerçek şirket/iletişim bilgini** yaz (şeffaflık = güven + yasal zorunluluk).
- E-posta bildirimi istersen: `join.php` içinde `$NOTIFY = true;` yap ve `$CONTACT`'ı ayarla.

## Diller (EN / FR / IT / ES / DE)
- Sağ üstteki dil değiştiriciden (ve mobil menüden) geçilir. URL: `?lang=fr`, `?lang=de` …
- Varsayılan: İngilizce. Form gönderilince seçili dil korunur.
- Tüm metinler `index.php` içindeki **`$T`** dizisinde — yeni dil eklemek/metni düzenlemek kolaydır.

## Kayıtlar nerede?
- `data/signups.csv` — satıcı/alıcı kayıtları (web'den indirilemez; sadece FTP/panelden).
- Sütunlar: `timestamp, type, name, company, email, country, message, ip`.

## Sırada ne var? → `PLAN.md`
Faz 1 (özel pazaryeri: KYC + escrow + DPP + AI) mimarisi ve yol haritası orada.
