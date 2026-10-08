# ChatHelp — Otonom Agent'lar (GitHub Actions)

Her gün kendi kendine çalışan zamanlanmış agent'lar. Hepsi ücretsiz, GitHub'ın
sunucularında çalışır, gizli anahtarlar **GitHub Secrets**'ta şifreli durur.

## Agent'lar

| Workflow | Ne yapar | Zaman (UTC) |
|---|---|---|
| `daily-monitor.yml` | Site/API ayakta mı kontrol eder, rapor + (opsiyonel) e-posta, sorun varsa issue açar | her gün 06:00 |
| `daily-shopify.yml` | Mondimart stok senkronu (`shopify-stock-all.mjs`) | her gün 05:00 |
| `daily-content.yml` | Claude ile günlük "Rechtstipp" üretir, `content/generated/`'a commit'ler | her gün 04:00 |
| `weekly-maintenance.yml` | `npm audit` + kodda secret sızıntısı taraması, bulgu varsa issue | Pazartesi 03:00 |
| `find-customers.yml` | **Müşteri bulma (v2):** marka çiftleriyle web araması → çok markalı butik siteleri → sitede **yayınlanmış gerçek e-posta** (tahmin yok) → sunucudaki `leads.json`'a ekler; ayakkabı / iç çamaşırı / toptancı / zincir elenir. `send=true` ya da repo değişkeni `PROSPECT_AUTO_SEND=true` ile gönderir. Eski OSM hattı (`daily-customers`, `discover-city`) kaldırıldı. | her gün 05:20 |

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
| `SHOPIFY_TOKEN` | daily-shopify | Stok senkronu için evet |
| `SMTP_HOST` | daily-monitor (mail) | Opsiyonel — `smtp.hosteurope.de` |
| `SMTP_PORT` | daily-monitor (mail) | Opsiyonel — `587` |
| `SMTP_USER` | daily-monitor (mail) | Opsiyonel — `support@chat-help.de` |
| `SMTP_PASS` | daily-monitor (mail) | Opsiyonel |
| `REPORT_TO` | daily-monitor (mail) | Opsiyonel — raporu alacak e-posta |
| `REPORT_FROM` | daily-monitor (mail) | Opsiyonel — gönderen (varsayılan SMTP_USER) |
| `DEPLOY_HOST` / `DEPLOY_USER` / `DEPLOY_SSH_KEY` / `DEPLOY_PORT` | find-customers (+ diğer lead işleri) | Sunucuya SSH — zaten tanımlı |
| `BRAVE_API_KEY` | find-customers | Opsiyonel ama önerilir — Brave Search API (brave.com/search/api, ücretsiz 2000 sorgu/ay); Bing/DDG engellerse tek güvenilir motor |
| `HUNTER_API_KEY` | find-customers | Opsiyonel — sitesinde adres yayınlamayan uygun butikler için Hunter.io'nun **kaynaklı** (yayınlanmış) adresleri (hunter.io, ücretsiz 25 arama/ay) |

> Mail secret'larını eklemezsen monitor yine çalışır; rapor sadece Actions
> "Summary" sekmesinde görünür ve sorun olursa GitHub seni otomatik uyarır + issue açılır.

> **Güvenlik:** Bu secret'lar koda yazılmaz, şifreli saklanır. Daha önce kodda/sohbette
> görünen anahtarları (SMTP, Shopify, API) **mutlaka yenile** ve sadece Secrets'a koy.

## Zaman değiştirme

`cron: '0 6 * * *'` → `dakika saat * * *` (UTC). Örn. Türkiye 09:00 = UTC 06:00.
