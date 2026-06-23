# VESTRA — Payments, Escrow, Refunds & Disputes

> **DRAFT — not legal advice.** Mirror this to your chosen provider's (Tazapay/Escrow.com) actual
> flow and timelines before publishing.

## 1. How payment works
- Buyers pay via the **licensed escrow provider** (SEPA bank transfer for EU B2B; cards available).
- Funds are **held in escrow by the provider** — **VESTRA never holds the money**.

## 2. Escrow release
Funds are released to the Seller when the agreed condition is met:
- **Buyer confirmation** of receipt, **or**
- **verified delivery** (e.g., carrier tracking), **or**
- expiry of an agreed auto-release window if no dispute is raised.
VESTRA's platform triggers release via the provider's API; the provider disburses
**Seller payout + VESTRA commission**.

## 3. Fees
- **VESTRA fee/commission:** [x]% per order and/or [membership fee].
- **Provider fee:** as charged by the escrow provider.
Fees are shown before checkout.

## 4. Refunds
If a dispute is resolved in the Buyer's favour (e.g., non-delivery, materially not-as-described,
proven counterfeit), escrowed funds are **refunded to the Buyer** before release. After release,
refunds are a matter between Buyer and Seller, with VESTRA assistance where appropriate.

## 5. Disputes
- A Buyer may open a dispute within the order window. Funds **remain in escrow** during a dispute.
- Parties submit evidence; VESTRA facilitates a **fair resolution** and instructs the provider to
  release or refund accordingly.
- Suspected counterfeit triggers the IP & Anti-Counterfeit process.

## 6. Chargebacks
Bank-transfer (SEPA) payments are not subject to card chargebacks. Card payments, where used,
follow the provider's chargeback process.

*Version 0.1 (draft), [date].*
