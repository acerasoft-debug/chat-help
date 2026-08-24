# MVP Kapsamı

Tek şehir, üç kategori (masaj-wellness, güzellik, kişisel antrenörlük).
Web-öncelikli (responsive); mobil uygulama Faz 2.

## Akışlar

### 1. Kayıt & doğrulama (iki taraf)
- E-posta/telefon ile kayıt, rol seçimi (müşteri / profesyonel)
- KYC oturumu (sağlayıcı SDK'sı) → `Verification` kaydı
- Profesyonel ek adımları: sertifika yükleme → manuel inceleme → rozet;
  profil ancak tüm doğrulamalar `APPROVED` olunca listelenir (`isListed`)

### 2. Arama & profil
- Kategori + konum + tarih filtresi
- Profil: rozetler (kimlik ✓, sertifika ✓, sicil ✓), hizmet listesi,
  fiyatlar, doğrulanmış yorumlar, hizmet yarıçapı

### 3. Rezervasyon & ödeme
- Hizmet + saat + adres seçimi → fiyat sabit ve şeffaf
- Kart yetkilendirme (Stripe, manual capture) → profesyonel onayı →
  `CONFIRMED`; tutar escrow'da (`ESCROW_HELD`)
- İptal politikası: son 24 saat kuralları net ve otomatik

### 4. Randevu günü (güvenlik katmanı)
- Profesyonelin canlı konumu (randevu penceresinde)
- Kapıda check-in, bitişte check-out
- İki taraf için SOS butonu → operasyon ekibine gerçek zamanlı uyarı

### 5. Tamamlama & feedback
- Müşteri onayı → ödeme serbest bırakılır (`RELEASED`)
- 48 saat içinde çift taraflı puan + yorum (yalnızca bu randevuya bağlı)
- Anlaşmazlık → `DISPUTED`, ödeme dondurulur, operasyon devreye girer

## MVP'de OLMAYANLAR (bilinçli)

- Mobil uygulama (web responsive yeter)
- Sağlık kategorileri
- Anlık (on-demand) eşleştirme — yalnızca planlı randevu
- Çoklu şehir/ülke operasyonu (mimari hazır, operasyon tek şehir)
- Profesyonel takvim senkronizasyonu (Google Calendar vb.)

## Operasyon paneli (minimum)

- Doğrulama kuyruğu (sertifika inceleme, onay/ret)
- Randevu izleme + SOS uyarıları
- Anlaşmazlık yönetimi ve iade/serbest bırakma
