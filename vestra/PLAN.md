# VESTRA — Yapım Planı (Orijinal Model)

> Çalışma adı **VESTRA** (placeholder — değiştirilebilir).
> Konum: B2B moda toptan pazaryeri · **güven/orijinallik-önce** · doğrulanmış ağ.

## 1. Konumlandırma (farkımız)
Pazar Merkandi/Ankorstore klonlarıyla dolu ve hepsi orijinallik/güven sorununu
görmezden geliyor. VESTRA bunu **ürünün çekirdeği** yapar:

| Herkes | VESTRA |
|---|---|
| "Biz aracıyız, sahtesi bizi bağlamaz" | "Orijinalliği **biz garanti ediyoruz**" |
| Açık kayıt, anonim satıcı | **Sadece KYC'li** satıcı + alıcı |
| Ödeme tarafların derdi | **Escrow** (sağlayıcı tutar) — onaydan sonra serbest |
| İzlenebilirlik yok | **Dijital Ürün Pasaportu** (DPP) — AB yasasından önde |

## 2. Ürün sütunları
1. **Doğrulanmış ağ** — işletme KYC (vergi no, kayıt, kimlik).
2. **Escrow + orijinallik kontrolü** — para Stripe/Mangopay'de durur; mal gelip
   onaylanınca satıcıya geçer. (Parayı biz tutmayız → risk sağlayıcıda.)
3. **DPP / köken kaydı** — her ürüne QR + kaynak zinciri. AB tekstil DPP'si
   ~2027 delege akt, ~2028 uygulama → bugün kurarsak öndeyiz.
4. **AI katmanı** — sahte/grey-market ön-tarama, tedarik eşleştirme, ilan üretimi.
5. **Katmanlı katalog** — markalı (dar+seçkin, sıkı doğrulanmış) + generic tekstil (hacim).

## 3. Gelir modeli
- **Üyelik/abonelik** (satıcı ve/veya alıcı) — ana gelir, düşük risk.
- (İleride) escrow üstünden küçük işlem ücreti.
- Komisyonlu otomatik ödeme **opsiyonel**, tutunca eklenir.

## 4. Uyum / risk (baştan gömülü)
- **KYC** her satıcı + alıcı (DSA "Know Your Business Customer").
- **İhbar-kaldır** (notice-and-takedown) + "bilinen ihlal" stay-down filtresi.
- **Strike/askı** politikası (ayarlanabilir eşik).
- **Satıcı sözleşmesi**: orijinallik + AB satış hakkı taahhüdü + tazmin (indemnify).
- **Şeffaf Impressum** (ABD şirketi, gerçek bilgi).
- İspat yükü satıcıda; şüphede ilan kapalı.

## 5. Mimari
**Faz 0 (ŞİMDİ — bu klasör):** Landing + bekleme listesi · sade PHP · Hosteurope.
**Faz 1+ (özel sistem):**
- Frontend: **Next.js** (veya Laravel Blade)
- Backend: **Laravel** (PHP — bildiğin ekosistem) veya Node
- DB: **PostgreSQL**
- AI: **Claude API** (eşleştirme, ön-tarama, ilan üretimi)
- Escrow/ödeme: **Mangopay** / **Stripe Connect**
- KYC: Stripe Identity / Sumsub / Veriff
- Hosting: küçük **VPS** (ör. Hetzner, AB/GDPR) — paylaşımlı hosting yetmez
- DPP: ürün-köken veri modeli + QR; sonra DPP standardına bağlanır

## 6. Yol haritası
- **Faz 0 — Landing + waitlist** (gün) → talebi/likiditeyi topla. ✅ kuruldu
- **Faz 1 — MVP** (hafta): KYC kayıt, ürün listeleme, üyeye özel fiyat,
  marka sayfaları, teklif/iletişim, ihbar-kaldır, T&C. VPS'te Laravel.
- **Faz 2 — Lansman**: ilk doğrulanmış satıcılar (Faz 0 listesinden) + alıcılar.
- **Faz 3 — Büyüme**: escrow, DPP, AI, daha çok kategori, (opsiyonel) komisyon.

## 7. Açık kararlar
- [ ] Gerçek marka adı + domain
- [ ] ABD şirketi yasal bilgileri (Impressum/footer)
- [ ] Hedef alıcı tipi (butik / online reseller / zincir)
- [ ] Başlangıç koridoru (ör. TR↔AB)
- [ ] Faz 1 stack onayı (Laravel + VPS öneri)
