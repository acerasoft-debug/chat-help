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
| `balenciaga-burberry-01..03` | 72 | **yes — covers every live row, all correct** |
| `balenciaga-burberry-04..17` | 342 | **not needed for live rows** (see coverage note) |
| `shorts-sweats-01..10` | 236 | not yet |

## batch2 / batch3 cannot be imported as they stand

All 158 rows carry one of just two categories — `Hoodies & Sweatshirts` (109)
and `Shorts` (49) — because that is what the two source folders were called.
Every row maps to a contact sheet, so each one can be checked; they sit in
twelve sheets:

`balenciaga-burberry-02` (1), `-03` (5), `-04` (21), `-05` (22), `-06` (21),
`-07` (23), `-08` (15), `dg-swim-01` (11), `mixed-root-c-01` (13), `-02` (5),
`shorts-sweats-03` (11), `shorts-sweats-04` (10).

### The generator emitted some products twice, under both categories

`dgn-m4e48tfusfw` and `dgn-f9o00tgdcnu` each appear twice with the **same id and
the same image**, once named "Sweatshirt" in `Hoodies & Sweatshirts` and once
"Shorts" in `Shorts`. Whatever built these walked the folder once per category
and took the name and category from the pass rather than from the product. Two
rows sharing an id do not just look untidy: on import one silently overwrites
the other, so which of the two survives is arbitrary.

### `dg-swim-01` — reviewed, 12 rows corrected

Every one of the 24 tiles is men's swim shorts: drawstring waist, mesh lining,
several photographed with the drawstring pouch D&G ships them in, models on the
beach. The rows were stored as `Shorts` (and one as `Hoodies & Sweatshirts`).

All 12 are now `Swim Shorts`, with names to match, and the duplicate
`dgn-m4e48tfusfw` "Sweatshirt" row is gone — tile 12 is plainly a pair of black
swim shorts, so that row was wrong in both name and category.

`dgn-f9o00tgdcnu` is left alone for now: its photo sits on
`balenciaga-burberry-04` and `shorts-sweats-03`, neither reviewed yet, and
guessing which of its two rows is right would repeat the original mistake.

## Coverage: only three of the seventeen sheets matter

The folder has **44 live product rows**, and every one of them appears on sheets
01, 02 or 03. Sheets 04–17 tile images that no live row references — photos that
were uploaded but never turned into products. Reviewing them cannot correct a
category, because there is nothing to correct; they are only useful when those
images are eventually made into rows.

This was computed rather than assumed: the live rows come from
`inspect-products.yml` (`img_contains: balenciaga-burberry/`, `compact: true`,
44 lines) and were joined by image filename against all 17 `.txt` tile maps.
So "17 sheets to review" was really three, and all three are now done.

## `balenciaga-burberry-01..03` — reviewed, no corrections needed

All 44 live rows checked against their photo. Every one is in the right
category: Balenciaga briefs under Underwear, its printed tees under T-Shirts,
the allover-logo shorts under Shorts, hoodies and crewnecks under Hoodies &
Sweatshirts, the purple women's tee and the Burberry women's tops under Women's
T-Shirts, the women's denim under Women's Jeans, Burberry's collared piqués
under Polos, and `8049455` (black denim shorts) under Jeans Shorts.

Nothing was moved, because nothing was wrong.

### Two things the sheets raise that photos cannot settle

- **Denim sets.** `662853TJW90` and `TWJ90` are a denim jacket *and* jeans
  photographed together, filed under Jeans / Women's Jeans.
- **Hoodie-and-trouser sets.** `8045004+8045013`, `8045006` and
  `8045015+8045005` are a hooded sweat with matching joggers, filed under
  Hoodies & Sweatshirts. A `Tracksuit Sets` category exists.

Both are merchandising calls — is the product the top, or the set? — so they are
recorded, not changed.

## Read the LIVE rows, not `product-batches/batch1.json`

`batch1.json` still shows every Balenciaga row as `Hoodies & Sweatshirts` — the
folder-derived category this whole exercise exists to undo. That file is the
*input* that was imported once; the corrections since then were written to
`listings.json` on the server and were never back-ported to it. Comparing sheets
against `batch1.json` therefore reports faults that were fixed weeks ago, and
"correcting" them would move products that are already right.

Use `inspect-products.yml` (brand filter) for the current state. It prints
`id | cat | first image`, and the image filename is what the sheet's `.txt`
tile map keys on, so the two line up directly.

## `balenciaga-burberry-01` — reviewed, no corrections needed

All 24 tiles read from the photo and checked against the live rows. Every
Balenciaga row this sheet covers is already in the right category:

| Model code | Photo shows | Live category |
|---|---|---|
| `4A8B8` | briefs, black + white | Underwear |
| `612966TLVF1` | printed tee | T-Shirts |
| `612966TMVG7` | beige tee | T-Shirts |
| `641675TLVF9` | washed blue tee | T-Shirts |
| `672410TLLJ4` | allover-logo shorts | Shorts |
| `674986TLVL8` | cream crewneck sweat | Hoodies & Sweatshirts |
| `676589` | black Simpsons tee | T-Shirts |
| `676589TLVG70901` | white Simpsons tee | T-Shirts |
| `681314TLVH4` | purple women's tee | Women's T-Shirts |
| `681734TJW60` | women's jeans | Women's Jeans |
| `TMVF5` | brown hoodie | Hoodies & Sweatshirts |

The sheet also tiles BALMAIN, DSQUARED2 and GIVENCHY images (a denim jacket, two
sleeveless BALMAIN tops, two cropped DSQ2 tops) that have no row in `batch1.json`
at all — they belong to the folders covered by the other sheets.

One open question this sheet raises but cannot answer: `662853TJW90` and `TWJ90`
are **denim sets** (jacket + jeans) filed under `Jeans` / `Women's Jeans`. There
is no "sets" category, so this is a product decision, not a mis-file.

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
