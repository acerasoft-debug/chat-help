# Konsept

## Tek cümlede

Kimliği, yeterliliği ve geçmişi doğrulanmış profesyonellerin müşterinin
adresinde premium hizmet verdiği; ödemenin escrow'da tutulduğu, her randevunun
sigortalı ve izlenebilir olduğu global bir pazaryeri.

## Konumlandırma

Mevcut platformların (Urban Company, Soothe, Treatwell, Armut vb.) en zayıf
noktası güven: "eve gelen kişi gerçekten kim?" Bu platformun farkı ucuzluk
değil, **doğrulanmış güven**. Premium fiyat, premium deneyim, sıfır anonimlik.

## Güven mimarisi (ürünün çekirdeği)

1. **Kimlik (KYC)** — iki taraf da resmi kimlik + canlılık (liveness)
   doğrulamasından geçer. Hazır sağlayıcı kullanılır (Stripe Identity /
   Onfido / Sumsub); ham kimlik belgeleri bizde tutulmaz, yalnızca sağlayıcı
   referansı saklanır.
2. **Yeterlilik** — kategori bazlı sertifika/lisans doğrulaması; profilde
   rozet olarak görünür.
3. **Geçmiş** — ülke mevzuatının izin verdiği yerde adli sicil kontrolü.
4. **Randevu güvenliği** — canlı konum paylaşımı, kapıda check-in/check-out,
   uygulama içi SOS, her randevuda sigorta.
5. **Şeffaflık** — yalnızca gerçekleşmiş randevular puanlanabilir; yorumlar
   çift taraflıdır (profesyonel de müşteriyi puanlar).

## Gelir modeli

- Randevu komisyonu (%15–25 bandı; premium segmentte alt banttan başla)
- Müşteri premium üyeliği (öncelikli randevu, esnek iptal)
- Profesyonel araç/görünürlük aboneliği
- Sigorta-garanti paketi marjı

Escrow + sigorta + garanti, platformu baypas etmenin (disintermediation)
panzehiridir: değer platformda kalır.

## Ana riskler ve karşılıkları

| Risk | Karşılık |
|---|---|
| Tavuk-yumurta (arz/talep) | Tek şehir, 2-3 kategori; ilk 50-100 profesyoneli elle onboard et |
| Platform baypası | Escrow, sigorta yalnız platformda geçerli, düşük komisyon + üyelik |
| Regülasyon (sağlık) | Doktor/hemşire/fizyo Faz 3'e ertelendi, ayrı uyumluluk katmanı |
| Fiziksel güvenlik | Çift taraflı KYC, canlı konum, SOS, sigorta |
| Sahte yorum | Yalnızca randevu-doğrulamalı yorum |

## Başlangıç stratejisi

Mimaride global (çoklu dil/para birimi/saat dilimi baştan), operasyonda tek
şehir. Açılış öncesi arz testi: seçilen şehirde her kategori için 4-6 haftada
30 doğrulanabilir profesyonel toplanabiliyor mu?
