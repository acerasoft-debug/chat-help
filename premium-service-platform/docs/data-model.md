# Veri Modeli

Kaynak: [`prisma/schema.prisma`](../prisma/schema.prisma)

## Ana varlıklar

- **User** — tek kimlik; rol (`CUSTOMER` / `PROFESSIONAL` / `ADMIN`) ve
  yereller. Müşteri ve profesyonel ayrıntıları ayrı profillerde.
- **ProfessionalProfile** — vitrin: şehir, hizmet yarıçapı, denormalize
  puan ortalaması. `isListed` yalnızca tüm doğrulamalar onaylanınca `true`
  yapılır; aramada sadece listelenmiş profiller görünür.
- **Verification** — güvenin kaydı. Üç tip: `IDENTITY` (KYC),
  `CERTIFICATION` (meslek belgesi), `BACKGROUND_CHECK` (adli sicil). Ham
  belge saklanmaz; yalnızca sağlayıcı referansı (`providerRef`) tutulur —
  KVKK/GDPR yüzeyini küçültür.
- **ServiceCategory / Service** — katalog. Fiyat + süre profesyonel
  tanımlıdır; `Booking` fiyatın anlık kopyasını (`priceAmount`) saklar ki
  sonradan fiyat değişse bile geçmiş bozulmaz.
- **Booking** — durum makinesi:
  `PENDING_CONFIRMATION → CONFIRMED → IN_PROGRESS → COMPLETED`
  (+ iptal ve `DISPUTED` dalları). `checkInAt`/`checkOutAt` kapıda
  başlangıç-bitiş kanıtıdır.
- **Payment** — escrow yaşam döngüsü:
  `AUTHORIZED → ESCROW_HELD → RELEASED` (veya `REFUNDED`). Booking ile
  bire bir.
- **Review** — çift taraflı, randevu-doğrulamalı:
  `@@unique([bookingId, authorId])` her tarafın randevu başına tek yorum
  yazmasını garanti eder. Profildeki puan, `ProfessionalProfile` üzerinde
  denormalize tutulur (arama performansı).
- **SosEvent** — güvenlik olay kaydı; randevuya ve tetikleyen kullanıcıya
  bağlı, operasyon çözene kadar açık kalır.

## Bilinçli tasarım kararları

1. **Fiyat kopyası Booking'de** — katalog değişse de finansal kayıt sabit.
2. **Denormalize rating** — arama sorgusu Review tablosuna inmez; yazma
   anında güncellenir.
3. **Kimlik verisi dışarıda** — KYC belgeleri sağlayıcıda kalır; veri
   ihlali yüzeyi minimal.
4. **Availability basit başlar** — haftalık tekrar eden slotlar; istisna
   günleri ve takvim senkronu sonraki faz.
