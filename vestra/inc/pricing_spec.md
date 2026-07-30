# Wholesale price sheet — operator instructions (2026-07-30)

Recorded verbatim from the operator so the numbers are not lost between sessions.
This file is documentation only; nothing reads it at runtime.

## Agreed prices

| Group | Wholesale price | MOQ | Pack / series | Status |
|---|---|---|---|---|
| BALMAIN — heavily embroidered / printed | **59.90** | **20** | — | blocked, no model codes |
| Balenciaga — Sweatshirts | **140.00** | not given | not given | blocked, no model codes |
| Balenciaga — Boxers | **24.90** | **40** | not given | blocked, no model codes |
| Burberry — Hoodies | **120.00** | not given | not given | blocked, no model codes |
| Burberry — Polos | **60.00** | not given | not given | blocked, no model codes |
| 3rd folder — Shorts | **49.90** | **20** | 10/pack | blocked, no model codes |
| 3rd folder — Sweat / Hoodies | **120.00** | not given | not given | blocked, no model codes |
| Balenciaga — Sweatshirts | **140.00** | not given | not given | blocked, no model codes |
| Balenciaga — Boxers | **24.90** | **40** | not given | blocked, no model codes |
| Burberry — Hoodies | **120.00** | not given | not given | blocked, no model codes |
| Burberry — Polos | **60.00** | not given | not given | blocked, no model codes |
| D&G — T-shirts | **49.90** | **20** | 10/pack | APPLIED 2026-07-30, 58 items |
| D&G — Polos | **60.00** | **20** | 10/pack | APPLIED 2026-07-30, 4 items |
| D&G — Hoodies & Sweatshirts | **90.00** | **20** | 10/pack | APPLIED 2026-07-30, 7 items |
| D&G — Body / bodysuits | **39.90** | not given | not given | no such products yet |
| Jeans | **110.00** | **20** | 10/series | no such products yet |
| Jeans shorts | **90.00** | **20** | 10/series | no such products yet |

T-shirt MOQ/pack was not in the original instruction; the operator confirmed it
should match polos and sweatshirts (10/pack, MOQ 20 = 998 EUR minimum order).

## Pack run for tops (S–XXL) — 10 per pack

    S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/paket

MOQ 20 = 2 packs. CORRECTED by operator 2026-07-30: M×3, XL×2 (was M×2, XL×3).
Still totals 10. Applied live across all 94 listings via set-prices brand=*, cat=*.

Minimum order value: polos 1,200 EUR, hoodies/sweatshirts 1,800 EUR.

## D&G jeans sizing — VERIFIED

Italian men's sizing, confirmed against published D&G size charts:

| IT size | Waist (in) |
|---|---|
| 44 | 28–29 |
| 46 | 30–31 |
| 48 | 32–33 |
| 50 | 34–35 |
| 52 | 36–37 |
| 54 | 38–39 |

Source: LookSize / SizeChartly / e-Outlet D&G jeans size guides.

## Size series — CONFIRMED by operator

48 and 50 at 2 each, 54 at 1, ten per series, minimum order 20.

| 44 | 46 | 48 | 50 | 52 | 54 | Total |
|---|---|---|---|---|---|---|
| 1 | 2 | 2 | 2 | 2 | 1 | **10** |

The operator pinned 48, 50 and 54 and gave the total; 44/46/52 were left open, so
the tails (44, 54) take 1 and the middle four take 2, which is the only symmetric
run that also sums to 10. Correct here if a different split was intended.

`sizes` field text:

    44×1 · 46×2 · 48×2 · 50×2 · 52×2 · 54×1 · 10/seri

`moq` = 20 = 2 series. At 110 EUR that is 2,200 EUR minimum for jeans; at 90 EUR,
1,800 EUR for jeans shorts.

## What the D&G catalogue actually contains

Verified against the live listings (read-only inspect, 2026-07-30):

| Category | In catalogue? | Action |
|---|---|---|
| T-Shirts | yes, ~60 items `dg-1012xx` | can be priced at 49.90 now |
| Polos | yes, 4 items | 60.00 + 10/pack + MOQ 20 |
| Hoodies & Sweatshirts | yes, 7 items | 90.00 + 10/pack + MOQ 20 |
| Body / bodysuits | **no** | needs product data before 39.90 can apply |
| Jeans | **no** | needs product data before 110 + series can apply |
| Jeans shorts | **no** | needs product data before 90 + series can apply |

`dg-101209` duplicate — RESOLVED 2026-07-30. Both rows shared brand/name/cat/sku/
list/moq/sizes AND the same single image file; only desc/accent/colors differed.
The second row's desc had a corrupted model code (embedded newline + stray "G"
after "G8PN9TG7M1D") and claimed colour "White" against the same photo the first
row used for "Black" -- no White photo exists for this SKU, so the second row was
judged a copy-paste accident, not a real second colourway, and removed. Backup:
listings.json.bak-20260730-164731. If a genuine White variant exists, it needs a
real photo and should be re-added as a proper second image/variant, not a second
top-level row.

## What the D&G change actually did

The dry-run exposed the previous state, which was a much larger change than price
alone. T-shirts were 69.90 in cartons of 200 (S×20 · M×60 · L×60 · XL×40 · XXL×20).
They are now 49.90 in packs of 10 with MOQ 20. That is a 29% price cut but a 93%
cut in minimum order value: 13,980 EUR down to 998 EUR. Flagged to the operator as
a repositioning toward small boutiques rather than a pricing tweak.

## Blocker — BALMAIN, Balenciaga, Burberry

The operator supplied Dropbox folder links for the BALMAIN and the
Balenciaga/Burberry products. This environment's egress policy refuses both, the
second one exactly as it refused the first:

    kind:   connect_rejected
    detail: gateway answered 403 to CONNECT (policy denial)
    host:   www.dropbox.com:443

Both the folder page and the `?dl=1` archive are blocked, so the real model codes
could not be read. They were deliberately NOT invented: a fabricated SKU in a
catalogue that sells authenticated goods would be ordered against by a real boutique.

To unblock, any one of: attach the files in chat (how the Lacoste/Amiri/D&G products
were added), paste the folder's file listing as text, or allow `dropbox.com` in the
environment's network policy.

## Why the catalogue looked empty — caching, not data

The operator reported the products were not live. They were: a read-only render on
the server produced 96 product cards and a server-side count of 96, with all 94
listings approved and no price-helper failures. The site was showing 8, a state the
catalogue left long ago.

No page sent any cache header. /shop is dynamic -- it reflects listings.json, and
prices, MOQs and size runs change with no file deployed and therefore no URL change
for a cache to notice -- so an edge or browser cache could hold a rendered copy
indefinitely. The repo's cloudflare-cache-fix.sh only ever covered chat-help.com
and /chat/, never vestrasales.com.

inc/head.php now sends no-store (plus CDN-Cache-Control and the Cloudflare-specific
variant, honoured even under a "cache everything" rule) and a Vary on Cookie and
Accept-Language so member and guest, and each language, stay distinct. Verified
over real HTTP: all five headers present.

An edge cache may still hold the old copy; that needs purging once, after which
the headers keep it from recurring.
