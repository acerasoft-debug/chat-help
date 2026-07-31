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

## Later pricing (2026-07-30, evening)

| Group | Price | Note |
|---|---|---|
| Jeans | **120.00** | REVISED from 110.00 |
| Jeans shorts | **90.00** | unchanged |
| Badeshorts (swim shorts) | **39.90** | new |
| Fendi — T-shirts | **80.00** | new brand |
| Fendi — Polos | **110.00** | new brand |
| Fendi — Sweatshirts | **125.00** | new brand |

Fendi has no products and no photo folder yet.

## Categories — actual state (read-only check, 2026-07-30 22:33)

    'Hoodies & Sweatshirts'   67  (live 8 / pending 59)  D&G 7, Lacoste 1, Balenciaga 20, Burberry 39
    'Polos'                    6  (live 6 / pending 0)   D&G 5, Ralph Lauren 1
    'Sweatshirts'              7  (live 7 / pending 0)   DSQUARED2 7   <-- the stray one
    'T-Shirts'               107  (live 72 / pending 35) D&G 57, Lacoste 3, RL 1, DSQUARED2 11, BALMAIN 35

'Sweatshirts' was the duplicate section: 7 DSQUARED2 products sitting apart from
the main 'Hoodies & Sweatshirts'. Merged into it.

## Categories still needed

Swim shorts, boxers and women's swimwear have nowhere correct to sit. Proposed,
matching the existing English naming and separating men's from women's:

    'Swim Shorts'          men's Badeshorts
    'Underwear'            boxers / briefs
    'Women's Swimwear'     Badeanzug für Frauen, bikinis
    'Jeans'                D&G jeans (folder now fetched)
    'Jeans Shorts'

These cannot be populated until the contact sheets identify which product is
which -- the whole point of the current correction pass. Assigning them from
folder names would repeat the original error.

## Later brands (2026-07-31)

| Brand | Group | Price | Status |
|---|---|---|---|
| Givenchy | T-shirts | 80.00 | 24 ADDED (sheet 1 verified) |
| Givenchy | Hoodie BMJ0HC3Y7M | 125.00 | 1 ADDED (hooded in photo) |
| Givenchy | remaining 8 files | — | sheet 2 unreviewed, NOT added |
| Marcelo Burlon | Sweatshirts | 90.00 | folder fetched, nothing added |
| Marcelo Burlon | T-shirts | 59.90 | folder fetched, nothing added |
| GCDS | Sweatshirts | 90.00 | folder fetched, nothing added |
| GCDS | T-shirts | 59.90 | folder fetched, nothing added |
| Fendi | Tracksuit set (SWEAT folder) | 245.00 | folder fetched, nothing added |
| Fendi | Patterned / monogram sweat | 290.00 | folder fetched, nothing added |

Givenchy follows the Fendi tier the operator set (t-shirt 80 / polo 110 /
sweatshirt 125). Marcelo Burlon and GCDS share their own tier: sweatshirt 90,
t-shirt 59.90.

Marcelo Burlon and GCDS images are on the server but NO products exist for them:
their contact sheets have not been generated or reviewed, and assigning a category
from the folder name is the exact mistake being corrected. Regenerate the sheets
(contact-sheets.yml FOLDERS), read them, then build the batches.

Fendi SWEAT folder carries two price points that CANNOT be told apart by filename:
the plain tracksuit set at 245.00 and the patterned/monogram piece at 290.00.
Which is which is only visible in the photo, so this folder in particular must be
reviewed on its contact sheet before any product is built — a wrong guess here is
a 45 EUR error per unit, not just a wrong label.

## Pricing added 2026-07-31 (later)

| Brand | Group | Price | Status |
|---|---|---|---|
| Fendi | T-shirts | **79.90** | folder fetched, nothing added |
| Givenchy | T-shirts | **79.90** | CORRECTED from 80.00, applied to the 24 live rows |
| Valentino | (all) | **59.90** | folder fetched, nothing added |
| Gucci | T-shirts | **99.90** | folder fetched, nothing added |
| Gucci | Polos | **120.00** | folder fetched, nothing added |

Givenchy t-shirts went in at 80.00 before the operator gave 79.90; corrected in
place via set-prices rather than left to drift.

Folders now on the server with NO products yet: marcelo-burlon, gcds, fendi-sweat,
fendi-tshirt, valentino, gucci. All six are in contact-sheets.yml FOLDERS so one
run produces every sheet.

Valentino was given a single price with no category split, so its sheet also has
to say which items are t-shirts and which are something else before rows exist.
