# Premium Service Platform

Global, premium **evde hizmet pazaryeri**: doğrulanmış profesyoneller (masaj,
güzellik, kişisel antrenörlük) müşterinin adresine gelir. Güven ürünün
kendisidir — kimlik doğrulama, sertifika rozetleri, escrow ödeme, çift taraflı
doğrulanmış yorumlar ve randevu boyunca güvenlik katmanı (canlı konum, SOS).

> "Maison" şu an çalışma adıdır; marka kararı verilince değişecek.

## Açılış kategorileri (Faz 1)

| Kategori | Neden ilk faz? |
|---|---|
| Masaj & Wellness | Evde deneyim salondan iyi; yüksek sepet, yüksek tekrar |
| Güzellik (tırnak, cilt, makyaj) | En yüksek frekans; etkinlik talebi (düğün vb.) |
| Kişisel Antrenörlük | Abonelik doğası; en yüksek LTV |
| Erkek Bakım / Berber | İkinci dalga (faz 1.5) |

Sağlık kategorileri (doktor, hemşire, fizyoterapi) bilinçli olarak **Faz 3**'e
ertelendi — ayrı regülasyon/uyumluluk katmanı gerektiriyor. Ayrıntı:
[`docs/concept.md`](docs/concept.md).

## Teknoloji

- **Next.js 15** (App Router) + **TypeScript** — web uygulaması ve API
- **Tailwind CSS v4** — arayüz
- **Prisma + PostgreSQL** — veri modeli ([`prisma/schema.prisma`](prisma/schema.prisma))
- **Stripe** (planlanan) — escrow tarzı ödeme (manual capture) + Connect ile profesyonel ödemeleri
- **Stripe Identity / Onfido / Sumsub** (planlanan) — KYC sağlayıcısı

## Geliştirme

```bash
npm install
cp .env.example .env   # değerleri doldur
npm run db:generate    # Prisma client üret
npm run dev            # http://localhost:3000
```

Veritabanı şemasını lokal PostgreSQL'e uygulamak için: `npm run db:push`

## Dokümantasyon

- [`docs/concept.md`](docs/concept.md) — konsept, konumlandırma, gelir modeli, riskler
- [`docs/mvp-scope.md`](docs/mvp-scope.md) — MVP kapsamı: ekranlar ve akışlar
- [`docs/data-model.md`](docs/data-model.md) — veri modelinin açıklaması
- [`docs/roadmap.md`](docs/roadmap.md) — fazlar ve yol haritası
