# Wholesale price sheet — operator instructions (2026-07-30)

Recorded verbatim from the operator so the numbers are not lost between sessions.
This file is documentation only; nothing reads it at runtime.

## Agreed prices

| Group | Wholesale price | MOQ | Notes |
|---|---|---|---|
| BALMAIN — heavily embroidered / printed | **59.90 EUR** | **20** | Awaiting real model codes (see blocker below) |
| Dolce & Gabbana — T-shirts | **49.90 EUR** | — | `dg-1012xx`, already in catalogue |
| Dolce & Gabbana — Body / bodysuits | **39.90 EUR** | — | IDs not yet confirmed in catalogue |
| Jeans | **110.00 EUR** | size series | Sized on the D&G jeans run, see below |
| Jeans shorts | **90.00 EUR** | size series | Same size run as jeans |

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

## Size series (pack) — NOT VERIFIED, needs operator confirmation

The operator asked for the standard series to be looked up ("13-15-18 serisi kaçsa").
**No brand publishes this** — a "serie" is the supplier's own packing decision, not a
D&G specification, so there is nothing authoritative to cite. Rather than guess a
number and present it as researched, here are three bell-curve runs over the verified
44–54 range, weighted to the middle sizes as denim normally is:

| Series | 44 | 46 | 48 | 50 | 52 | 54 | Total |
|---|---|---|---|---|---|---|---|
| A | 1 | 2 | 3 | 3 | 2 | 2 | **13** |
| B | 1 | 2 | 4 | 4 | 2 | 2 | **15** |
| C | 2 | 3 | 4 | 4 | 3 | 2 | **18** |

Once one is chosen it goes into the product's `sizes` field in the existing format,
e.g. `44×1 · 46×2 · 48×3 · 50×3 · 52×2 · 54×2 · 13/serie`, and `moq` is set to the
series total (or a multiple of it).

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
