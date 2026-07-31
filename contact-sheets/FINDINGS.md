# Category verification from photos — findings so far

The 252 product rows built from the Dropbox uploads took their category from the
*source folder*, never from the product. That produced exactly the errors the
operator reported: t-shirts filed as sweatshirts, boxers as sweatshirts, swimwear
in the wrong place.

These contact sheets exist to fix that from the actual photos. Each page tiles 24
images with the filename (= model code) printed under each one.

## Status

| Sheets | Images | Reviewed |
|---|---|---|
| `balmain-01..02` | 29 | **yes** |
| `balenciaga-burberry-01..17` | 390 | not yet |
| `shorts-sweats-01..10` | 236 | not yet |

## Confirmed corrections — BALMAIN

All of these are currently stored as `T-Shirts`. Verified against the photos:

| Model code | Stored | Actually | Evidence in photo |
|---|---|---|---|
| `XH16B005 BB04` | T-Shirts | **Polos** | white collared polo |
| `XH1GB005 BB04` | T-Shirts | **Polos** | pink open-collar polo |
| `XH1GB005` | T-Shirts | **Polos** | navy polo |
| `WH1JQ040B139MAI` | T-Shirts | **Hoodies & Sweatshirts** | red sweat worn with matching track shorts |
| `WH1JQ055275JGFQ` | T-Shirts | **Hoodies & Sweatshirts** | white crewneck sweat, yellow sleeve stripes |

The remaining ~24 BALMAIN images are genuinely t-shirts, so those rows are correct.

## Why the model code cannot substitute for looking

Searched for the brands' style-code schemes: they are **not publicly documented**.
The single verifiable data point found was `M4A53T` = D&G swim shorts, i.e. `M4…`
sits in men's beachwear/underwear — but no published letter-by-letter mapping
exists. Assigning categories from code prefixes would repeat the original mistake
with extra confidence, so it is not done.

## Current catalogue state — safe

93 live products, all originals and correctly categorised. The 94 added products
are `status: pending`: invisible in the catalogue, every field preserved in
listings.json, reversible in one step via `hold-products.yml` with
`status: approved` once categories are corrected.

A further 158 rows (D&G 135, Burberry 23) were never added at all — they sit in
`product-batches/batch2.json` and `batch3.json`.

## Next pass

1. Review the remaining 27 sheets, recording the real category per model code.
2. Apply the category corrections to listings.json.
3. Flip the corrected rows back to `approved`.
4. Add batch2/batch3, verifying each run's output rather than assuming it landed.

---

# shorts-sweats-01 — reviewed (24 images)

This single sheet already disproves the batch2/batch3 categories, which only ever
used `Hoodies & Sweatshirts` and `Shorts`. The folder actually holds four
different product types, including women's swimwear:

| # | Model code | Actually is | Category |
|---|---|---|---|
| 1 | (DSQ2 blue) | men's swim shorts | **Swim Shorts** |
| 2 | BALMAIN black | men's swim shorts | **Swim Shorts** |
| 3 | BKBGA0700116 | **women's one-piece swimsuit**, white/gold allover | **Women's Swimwear** |
| 4 | BKBU30810 | **women's one-piece**, black | **Women's Swimwear** |
| 5 | BKBU9650 | **women's one-piece**, black high-cut | **Women's Swimwear** |
| 6 | BKBU90650 | **women's one-piece**, pink | **Women's Swimwear** |
| 7 | BALMAIN allover | boxer briefs | **Underwear** |
| 8 | (blue print) | briefs | **Underwear** |
| 9 | BWB210400 | boxer briefs, black | **Underwear** |
| 10 | BWB210400 | briefs, red/white stripe | **Underwear** |
| 11 | BWB410360 | boxer trunks, black | **Underwear** |
| 12 | BWB410400 | boxer trunks, black allover | **Underwear** |
| 13 | BWB410400 | boxers, red/white stripe | **Underwear** |
| 14 | BWB410400 | boxers, navy/white stripe | **Underwear** |
| 15 | BWB550350 | boxer trunks, black allover | **Underwear** |
| 16 | BWB550350 | boxer trunks, white allover | **Underwear** |
| 17 | BWB640410 62815 | swim shorts, red stripe, drawstring | **Swim Shorts** |
| 18 | BWB640410 13813 | swim shorts, black/white stripe | **Swim Shorts** |
| 19 | BWB640410 01016 | swim shorts, black/white | **Swim Shorts** |
| 20 | BURBERRY 4567629 | printed t-shirt | **T-Shirts** |
| 21 | BURBERRY 8014004 | polo, navy | **Polos** |
| 22 | BURBERRY 8045515 | swim shorts, light blue | **Swim Shorts** |
| 23 | BURBERRY 80170071 | polo, navy | **Polos** |
| 24 | BURBERRY 8017294 | swim shorts, check | **Swim Shorts** |

Tally for this sheet alone: Underwear 10, Swim Shorts 8, Women's Swimwear 4,
Polos 2, T-Shirts 1. batch2/batch3 would have filed every one of these as either
Shorts or Hoodies & Sweatshirts.

Boxers and swim shorts are visually distinct and reliably separable: swim shorts
have a drawstring waist and a longer, looser leg; boxer trunks have a wide
branded elastic waistband and no tie. Women's one-pieces are unmistakable.

## Why batch2/batch3 must NOT be run as they stand

They would add 158 products carrying the same folder-derived guess, and would
also create a `Shorts` category that does not exist in the catalogue — a second
stray section of exactly the kind just cleaned up. They need regenerating from
the sheets first.

## Remaining to review

shorts-sweats 02..10 (~212 images) and balenciaga-burberry 01..17 (390), plus
givenchy 01..02. Method is proven on this sheet and on dg-jeans: read the sheet,
write the real category per model code, regenerate the batch, add, verify.
