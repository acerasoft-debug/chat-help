# Yerel görsel inceleme (mobil + masaüstü ekran görüntüleri)

Siteyi yerelde çalıştırıp her sayfayı iPhone (390×844) ve masaüstü (1366×768)
genişliğinde çeker; yatay taşma, konsol hataları, başarısız istekler ve 11px
altı yazıları raporlar. `auth.js` ayrıca yerel bir alıcı + satıcı hesabı açıp
panel sayfalarını ve kapalı fiyat kapısı hâllerini çeker.

```sh
# 1) yerel sunucu (rewrite kuralları _router_local.php'de, .htaccess'in aynası)
cd vestra && php -S 127.0.0.1:8085 -d display_errors=0 -d log_errors=1 \
  -d error_log=/tmp/vestra_php_errors.log -t . _router_local.php &

# 2) playwright (tarayıcı bu ortamda hazır: /opt/pw-browsers)
cd tests/render && npm init -y >/dev/null && npm i playwright@1.47.0 --no-audit --no-fund

# 3) çek
CHROME=/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell node shoot.js         # tüm sayfalar
CHROME=... node shoot.js shop product                                                                    # yalnızca bazıları
CHROME=... node auth.js                                                                                  # giriş yapılmış hâller
```

Çıktı `tests/render/out/` (gitignore'da): `<sayfa>-<mobile|desktop>.png` tam sayfa,
`-top.png` yalnızca ilk ekran (okunabilir olan bu), `report.json` / `auth-report.json`.

## Arapça (RTL) kontrolü — `rtl-check.js`

Site 8 dilde ve **Arapça sağdan sola** basılıyor (`vlang_dir()` → `<html dir="rtl">`).
Bu betik her sayfayı **önce İngilizce, sonra Arapça** açar ve yalnızca **farkı**
raporlar: iki dilde de taşan bir öğe RTL sorunu değildir.

```sh
CHROME=/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell node rtl-check.js
```

İki tuzak bilerek ele alınıyor:
- **Kasıtlı yatay kaydırıcılar** (marka rayı, beden tablosu) çocuklarını viewport
  dışına taşırır; `overflow-x: auto/scroll` bir üst öğede varsa taşma sayılmaz.
  İlk yazımda bu ayrım yoktu ve 24 görünümün 24'ü "sorunlu" çıktı.
- **Engellenen dış istekler** (Google Fonts) her sayfada konsol hatası üretir;
  bunlar hata değil, ölçüm kurulumunun kendisidir.

4 Eylül 2026 ölçümü: 12 sayfa × 2 genişlik, **RTL kaynaklı gerileme 0**.

Notlar:
- Dış istekler (Google Fonts, CDN) bilerek engellenir; yoksa proxy'de asılı kalır.
  Rapordaki `fonts.googleapis.com ... ERR_FAILED` bu yüzden, hata değil.
- Yeni Chromium'da `--headless=old` yok; `chromium_headless_shell` ikilisi kullanılır.
- Yerel `data/` boş olduğu için katalog demo + seed ürünlerle çizilir; `showroom`
  gerçek satıcı id'si olmadan 404 döner, `catalog` bir .xlsx indirmesidir (ERR_ABORTED normal).
- OVERFLOW bayrağı yatay kaydırıcıların (marka rayı, "yeni eklenenler" rayı, varyant
  tablosu) çocuklarını da işaretler; HSCROLL yoksa sayfa yana kaymıyordur.
