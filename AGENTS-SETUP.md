# ChatHelp — Otonom Agent'lar (GitHub Actions)

Her gün kendi kendine çalışan zamanlanmış agent'lar. Hepsi ücretsiz, GitHub'ın
sunucularında çalışır, gizli anahtarlar **GitHub Secrets**'ta şifreli durur.

## Agent'lar

| Workflow | Ne yapar | Zaman (UTC) |
|---|---|---|
| `daily-monitor.yml` | Site/API ayakta mı kontrol eder, rapor + (opsiyonel) e-posta, sorun varsa issue açar | her gün 06:00 |
| `daily-shopify.yml` | Mondimart stok senkronu (`shopify-stock-all.mjs`) | her gün 05:00 |
| `shopify-markets.yml` | Pazar açılışı + soğuk zincir kargo (`shopify-markets.mjs`) | **elle** (workflow_dispatch) |
| `daily-content.yml` | Claude ile günlük "Rechtstipp" üretir, `content/generated/`'a commit'ler | her gün 04:00 |
| `weekly-maintenance.yml` | `npm audit` + kodda secret sızıntısı taraması, bulgu varsa issue | Pazartesi 03:00 |

Hepsini **Actions** sekmesinden elle de tetikleyebilirsin (**Run workflow**).

## ⚠️ ÖNEMLİ: Zamanlama sadece DEFAULT branch'ten çalışır

GitHub, `schedule` (cron) workflow'larını **yalnızca default branch'te** (genelde
`main`/`master`) çalıştırır. Bu dosyalar şu an `claude/charming-franklin-1ynmuj`
branch'inde. Günlük çalışması için:

1. Bu branch'i default branch'e **merge et** (veya `.github/workflows/` + `scripts/`
   klasörlerini default branch'e taşı).
2. Sonra Actions sekmesinde workflow'lar görünür ve zamanında çalışır.

## Gerekli GitHub Secrets

Repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Hangi agent | Zorunlu? |
|---|---|---|
| `ANTHROPIC_API_KEY` | daily-content | İçerik üretimi için evet |
| `SHOPIFY_TOKEN` | daily-shopify, shopify-markets | Stok + pazar/kargo için evet |
| `SHOPIFY_STORE` | shopify-markets | Evet — örn. `mondimart.myshopify.com` |
| `SMTP_HOST` | daily-monitor (mail) | Opsiyonel — `smtp.hosteurope.de` |
| `SMTP_PORT` | daily-monitor (mail) | Opsiyonel — `587` |
| `SMTP_USER` | daily-monitor (mail) | Opsiyonel — `support@chat-help.de` |
| `SMTP_PASS` | daily-monitor (mail) | Opsiyonel |
| `REPORT_TO` | daily-monitor (mail) | Opsiyonel — raporu alacak e-posta |
| `REPORT_FROM` | daily-monitor (mail) | Opsiyonel — gönderen (varsayılan SMTP_USER) |

> Mail secret'larını eklemezsen monitor yine çalışır; rapor sadece Actions
> "Summary" sekmesinde görünür ve sorun olursa GitHub seni otomatik uyarır + issue açılır.

> **Güvenlik:** Bu secret'lar koda yazılmaz, şifreli saklanır. Daha önce kodda/sohbette
> görünen anahtarları (SMTP, Shopify, API) **mutlaka yenile** ve sadece Secrets'a koy.

## Pazar açılışı + Soğuk Zincir (`shopify-markets.mjs`)

Et ürünlerini **FR/BE/ES/LU**'ya açar + **"Livraison Chaîne du Froid ❄️"** kargo
profili kurar, kuru gıdayı **tüm AB-27**'ye açar, tüm ürünleri bu 4 markette
satışa açar. **Etiket (tag) bazlı** çalışır, **Shopify Plus** içindir.

**Ürün ayrımı (etiketler):** Script şu etiketlere bakar (env ile değiştirilebilir):
- ET / soğuk zincir → `MEAT_TAGS` (varsayılan: `viande, et, frais, froid, soğuk, soğuk-zincir, chaîne-du-froid …`)
- KURU GIDA → `DRY_TAGS` (varsayılan: `sec, épicerie, kuru, kuru-gıda, dry …`)
> Mağazanızdaki gerçek etiketler farklıysa, ürünleri o etiketlerle işaretleyin
> **veya** workflow/secret ile `MEAT_TAGS` / `DRY_TAGS` değerlerini geçin.

**Çalıştırma (önce DRY-RUN!):**
1. Repo → **Actions → "Shopify Markets + Soğuk Zincir" → Run workflow**.
2. `apply` = **false** (önizleme) ile çalıştır → **Summary** sekmesinde planı oku.
3. Plan doğruysa tekrar çalıştır, bu kez `apply` = **true**.

**Lokal çalıştırma:**
```bash
SHOPIFY_STORE=mondimart.myshopify.com SHOPIFY_TOKEN=shpat_xxx \
  node shopify-markets.mjs                 # dry-run (önizleme)
SHOPIFY_STORE=... SHOPIFY_TOKEN=... \
  node shopify-markets.mjs --apply         # uygula
# Tek adım: --only=delivery | --only=catalogs | --only=markets
```

> Güvenlik notu: Varsayılan **dry-run**'dır; hiçbir şey değiştirmez. Soğuk zincir
> kargo ücretini `COLD_RATE` (varsayılan 9.90), kuru gıdayı `DRY_RATE` (5.90),
> para birimini `RATE_CURRENCY` (EUR) ile ayarlayın — uygulamadan önce planda görünür.

## Zaman değiştirme

`cron: '0 6 * * *'` → `dakika saat * * *` (UTC). Örn. Türkiye 09:00 = UTC 06:00.
