# DSQUARED2 — verified from photos (2026-07-31)

Read off `contact-sheets/dsq-jeans-01..02`, `dsq-sweat-01`, `dsq-tshirt-01`,
joined to filenames through the matching `.txt` manifests. 51 images in, 46
product rows out, 5 deliberately held back (bottom of this file).

## The code scheme, now established from photos

Earlier I recorded that DSQUARED2's style-code scheme is not publicly documented
and refused to assign categories from code prefixes. That still holds as a
*source* — but having now looked at all 51 photos, the pattern is visible and
consistent, and it is worth writing down as a cross-check on future folders:

| Segment | Garment | Seen in |
|---|---|---|
| `…LB…` / `…LA…` | jeans | 27 items |
| `…MU…` | shorts — denim in the jeans folder, jersey in the t-shirt folder | 5 items |
| `…KB…` | chino trousers | 2 items |
| `…GU…` | sweatshirt / hoodie | 6 items |
| `…GD…` | t-shirt | 2 items |
| `…GL…` | polo | 6 items |

This is a pattern observed after the fact, not a rule used to classify. Every row
below was still assigned from its own photo.

It does resolve the two shorts prices cleanly. `MU` appears in both folders: the
jeans folder holds **denim** shorts, the t-shirt folder holds **jersey** shorts.
That is exactly the split the operator's two numbers imply — 125–130 for denim,
49.90 for jersey — so the two instructions were never in conflict.

## Sizing

Jeans and denim shorts use DSQUARED2's own Italian run, which is offset from
D&G's and must not share a series with it:

    IT  42  44  46  48  50  52  54
    US  32  34  36  38  40  42  44

Series applied: `44×1 · 46×2 · 48×2 · 50×2 · 52×2 · 54×1 · 10/seri`, MOQ 20.
IT 42 is left out to keep the ten-per-series run the operator confirmed; say so
if 42 should be carried and 54 dropped instead.

Tops use the confirmed `S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/paket`, MOQ 20.

## Price split within the ranges — ASSUMPTION, not instruction

The operator gave `150-190` for jeans and `125-130` for denim shorts, "modele
göre". No rule was given for which model sits where, and a filename cannot
supply one. Applied rule, readable from the photo and reversible in one run:

    heavy distress, paint splatter, rips, patches  -> 190 jeans / 130 shorts
    clean or uniform wash, no rips                 -> 150 jeans / 125 shorts

Result: 19 jeans at 190, 8 at 150; 2 denim shorts at 130, 1 at 125.

The eight jeans put at **150** are `S74LB0786` (grey, clean), `S74LB0869` (dark,
side stripe), `S74LB0907`, `S74LB0988` (grey, light), `S74LB1026`, `S74LB1079`
(dark, clean), `S74LB707`, `S74LB0658`. Everything else is at 190. Moving any of
them is one `set-product.yml` run.

## What went in

| Category | Count | Price |
|---|---|---|
| Jeans | 27 | 150 / 190 |
| Jeans Shorts | 3 | 125 / 130 |
| Hoodies & Sweatshirts | 6 | 90.00 |
| Polos | 6 | 64.00 |
| T-Shirts | 2 | 49.90 |
| Shorts (jersey) | 2 | 49.90 |

Five of the six sweat items are crewnecks and one (`S74GU0546`) is a hoodie; both
sit in `Hoodies & Sweatshirts` at the 90.00 the operator gave for sweatshirts.

## Held back — 5 items with no price

Not invented, not guessed, not added:

| Code | What the photo shows | Missing |
|---|---|---|
| `S74KB0383` (navy) | chino trousers, not jeans | no trouser price |
| `S74KB0383` (beige) | chino trousers | no trouser price |
| `S74MU0593` | beige **cargo** shorts, cotton not denim | neither shorts price clearly applies |
| `S79GU0051` / `S79KA0022` | hooded **tracksuit set** (top + joggers) | Fendi set is 245, Givenchy 190, DSQ set not given |
| `S79GC0003` | one frame shows an ICON tee **and** ICON shorts | cannot tell which garment the code belongs to |

The tracksuit set is the one worth a decision soonest — it is a complete outfit
sitting unpriced while its components' prices are known.
