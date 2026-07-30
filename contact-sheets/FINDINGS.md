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
