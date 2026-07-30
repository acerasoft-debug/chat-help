# Wholesale price sheet — operator instructions (2026-07-30)

Recorded verbatim from the operator so the numbers are not lost between sessions.
This file is documentation only; nothing reads it at runtime.

## Agreed prices

| Group | Wholesale price | MOQ | Pack / series | Status |
|---|---|---|---|---|
| BALMAIN — heavily embroidered / printed | **59.90** | **20** | — | blocked, no model codes |
| D&G — T-shirts | **49.90** | **20** | 10/pack | ~60 items, ready to price |
| D&G — Polos | **60.00** | **20** | 10/pack | 4 items, ready to price |
| D&G — Hoodies & Sweatshirts | **90.00** | **20** | 10/pack | 7 items, ready to price |
| D&G — Body / bodysuits | **39.90** | not given | not given | no such products yet |
| Jeans | **110.00** | **20** | 10/series | no such products yet |
| Jeans shorts | **90.00** | **20** | 10/series | no such products yet |

T-shirt MOQ/pack was not in the original instruction; the operator confirmed it
should match polos and sweatshirts (10/pack, MOQ 20 = 998 EUR minimum order).

## Pack run for tops (S–XXL) — 10 per pack

    S×1 · M×2 · L×3 · XL×3 · XXL×1 · 10/paket

MOQ 20 = 2 packs. The S–XXL split was not specified, so it mirrors the logic used
on the jeans run: tails light, middle weighted. Matches the house format already in
use on the live Lacoste listing (`S×1 · M×2 · L×2 · XL×2 · XXL×1 · 8/pack`).

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

Also noted: `dg-101209` ("Oversized Vertical Logo T-Shirt") appears TWICE in the
catalogue with the same image. Worth de-duplicating.

## Blocker — BALMAIN

The operator supplied a Dropbox folder link for the BALMAIN products. This
environment's egress policy refuses it:

    kind:   connect_rejected
    detail: gateway answered 403 to CONNECT (policy denial)
    host:   www.dropbox.com:443

Both the folder page and the `?dl=1` archive are blocked, so the real model codes
could not be read. They were deliberately NOT invented: a fabricated SKU in a
catalogue that sells authenticated goods would be ordered against by a real boutique.

To unblock, any one of: attach the files in chat (how the Lacoste/Amiri/D&G products
were added), paste the folder's file listing as text, or allow `dropbox.com` in the
environment's network policy.
