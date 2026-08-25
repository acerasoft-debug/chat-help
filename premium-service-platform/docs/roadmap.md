# Yol Haritası

## Faz 0 — Hazırlık (şimdi)

- [x] Konsept ve kategori seçimi
- [x] Veri modeli (Prisma şeması)
- [x] Proje iskeleti (Next.js 15 + Tailwind v4)
- [ ] Marka adı ve açılış şehri kararı
- [ ] Arz testi: hedef şehirde kategori başına 30 profesyonel görüşmesi

## Faz 1 — MVP (tek şehir, 3 kategori)

- [x] Kayıt akışı: magic-link giriş, rol bazlı onboarding, oturum (JWT cookie)
- [x] KYC altyapısı: sağlayıcı arayüzü + mock sağlayıcı; Verification kayıtları
- [x] Konum bazlı keşif: posta kodu (PLZ) + şehir ile çevredeki doğrulanmış
      uzman araması (/explore), herkese açık profil sayfaları, rozet ve
      şeffaf fiyat gösterimi (posta kodu öneki ile yakınlık; geocoder sonra)
- [ ] Gerçek KYC entegrasyonu (Stripe Identity veya Sumsub) + webhook
- [ ] Profesyonel onboarding + sertifika inceleme paneli
- [ ] Arama, profil, rezervasyon akışı
- [x] Rezervasyon akışı: KYC kapılı rezervasyon, profesyonel onay/ret,
      müşteri iptal/tamamlama, escrow ödeme yaşam döngüsü (mock sağlayıcı:
      AUTHORIZED → ESCROW_HELD → RELEASED/REFUNDED), adres gizliliği
      (onaya kadar yalnızca şehir görünür)
- [x] Çift taraflı randevu-doğrulamalı yorum + denormalize puan güncelleme
- [ ] Stripe ödeme (manual capture escrow) + Connect payout
- [ ] İptal penceresi ücret kuralları, check-in/check-out, anlaşmazlık akışı
- [ ] Çift taraflı doğrulanmış yorum
- [ ] Canlı konum + check-in/out + SOS
- [ ] Operasyon paneli (doğrulama kuyruğu, anlaşmazlık, SOS izleme)

## Faz 1.5

- [ ] Erkek bakım / berber kategorisi
- [ ] Müşteri premium üyeliği
- [ ] Sigorta ortaklığı

## Faz 2

- [ ] Mobil uygulama (React Native / Expo)
- [ ] İkinci şehir + çoklu para birimi operasyonu
- [ ] Profesyonel takvim senkronizasyonu, paket/abonelik satışı

## Faz 3

- [ ] Sağlık kategorileri (fizyoterapi → hemşirelik → doktor) — ülke bazlı
      lisans doğrulama, sağlık verisi uyumluluğu (KVKK özel nitelikli veri,
      GDPR, gerekirse HIPAA) ve malpraktis sigortası katmanıyla
