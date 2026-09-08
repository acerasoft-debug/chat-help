# LUMÉA — Private Spa. At Your Door.

Premium mobile massage & skincare marketplace for **Germany, Austria, Switzerland and Spain**.
Guests book vetted therapists and aestheticians to their home, hotel or office; therapists apply,
pass identity + document verification, and accept nearby requests. **Five languages (DE · EN · ES · FR · IT)**,
twenty cities, twenty-three treatments — from anti-cellulite and lymphatic drainage to HIFU lifting and signature facials.

Zero npm dependencies. Node ≥ 22.5 only (built-in `node:sqlite`, `node:crypto`, `node:http`).

```
npm start          # builds dist/ and serves site + API on http://localhost:4477
npm run build      # static site only (GitHub Pages / Netlify / Cloudflare Pages)
npm run dev        # build + auto-restart API on change
```

## What is inside

| Layer | Path | Notes |
| --- | --- | --- |
| Content | `data/` | services (DE/EN/ES copy), cities, therapists (deterministic roster), journal, Privé tiers, UI strings |
| Static generator | `scripts/build.mjs`, `src/lib/` | 1 965 pages, sitemap, robots, manifest, JSON-LD, hreflang |
| Front-end runtime | `src/assets/app.js`, `styles.css` | IP/GPS location, matching, wizards, dashboards; works without the API |
| API server | `server/` | auth, sessions with IP log, geo-IP, matching, bookings, verification, admin |
| Ops | `Dockerfile`, `.env.example`, `.github/workflows/lumea-pages.yml` | container + Pages deploy |

### Pages generated (per locale × 5)

- Home, treatments index, skincare index, cities index, how-it-works, Privé membership, journal + 4 articles
- 23 treatment pages · 20 city pages · **200 city × treatment landing pages** (10 "money" treatments × 20 cities)
- **128 therapist profile pages** with bio, certificates, verification seals, coverage, availability, price list, reviews
- Sign-in / register / account / booking wizard (`noindex`), contact, gift vouchers, corporate, imprint, privacy, terms

Every page carries `canonical`, `hreflang` (de/en/es/fr/it/x-default), Open Graph/Twitter cards and typed JSON-LD
(`Organization`, `HealthAndBeautyBusiness` per city, `Service`, `Person` per therapist, `FAQPage`, `Article`,
`JobPosting`, `BreadcrumbList`, `ItemList`, `Offer`). Sitemap includes `xhtml:link` alternates.

## The platform rules baked in

- **Nobody works without verification.** A therapist profile is `pending` until the review team approves
  **ID, qualification certificate and liability insurance** (criminal record + business registration optional
  but shown). Approval of the three required documents auto-activates the profile; a rejection de-activates it.
  Pending therapists can sign in and upload documents but cannot see guest addresses or accept requests.
- **Prepaid escrow.** Guests pay at booking (`payment_status = authorised`); the amount is released to the
  therapist only when they mark the appointment `done` (`released`), or refunded on cancellation (`refunded`).
  Plug your PSP (Stripe/Adyen/Mollie) webhook into `POST /api/bookings` → `authorised` and
  `POST /api/therapist/complete` → payout.
- **Strictly therapeutic.** Copy, FAQ and terms state it in all three languages; the API has no other scope.
- **Location first.** `/api/geo` resolves city from edge headers (Cloudflare / Vercel), an optional geo-IP provider
  (`LUMEA_GEOIP_URL`), or `Accept-Language`; the browser can upgrade to GPS on request. Full IPs are never
  stored — sign-in logs keep a truncated `/24` (`/48` for IPv6).

## API

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/geo` | anyone | IP → nearest launch city (+ therapist count) |
| GET | `/api/therapists?city&service&lat&lng&when&lang` | anyone | ranked matches within each therapist's radius |
| GET | `/api/therapists/profile?id&locale` | anyone | full public profile incl. verification seals |
| POST | `/api/auth/register` · `/login` · `/logout` | — | scrypt hashes, HttpOnly cookie, throttling (8/email, 25/IP per 15 min) |
| GET | `/api/auth/me` · `/api/auth/sessions` | signed in | current user; sign-in history with IP, device, city |
| POST | `/api/bookings` | anyone | create request (prepaid), returns 3 suggested therapists |
| GET | `/api/bookings` · `/api/bookings/ics?id` | client | my appointments; iCalendar export |
| POST | `/api/bookings/cancel` | client | cancel → refund state |
| POST | `/api/therapists/apply` | anyone | onboarding (creates a pending therapist account) |
| GET/POST | `/api/therapist/documents` | therapist | checklist; upload (base64 JSON, PDF/JPEG/PNG/WEBP/HEIC ≤ 8 MB) |
| GET | `/api/therapist/requests` | therapist | open requests in city for offered treatments (address hidden until accepted) |
| POST | `/api/therapist/accept` · `/complete` | therapist | claim a request; finish and release payout |
| GET/POST | `/api/therapist/me` · `/api/therapist/profile` | therapist | read / edit own public profile (name & city locked to documents) |
| GET | `/api/admin/overview` | admin | pending documents, pending profiles, counters |
| POST | `/api/admin/documents/review` | admin | approve / reject with note → auto-activation |
| GET | `/api/admin/documents/file?id` | admin | stream the stored document |
| POST | `/api/admin/therapists/status` | admin | manual status; refuses `active` without the 3 required approvals |
| POST | `/api/contact` | anyone | contact / B2B enquiries |

Admin accounts: set `LUMEA_ADMIN_EMAIL=a@x.com,b@y.com`; those e-mails become `admin` on register/login.

## Deploy

**Static only (GitHub Pages):** the workflow builds `lumea/dist` and deploys on `main`. For a project page set the
repository variables `LUMEA_ORIGIN=https://<user>.github.io` and `LUMEA_BASE=/<repo>`; for a custom domain add a
`CNAME` file next to `package.json`. Without the API the site still works: location falls back to time-zone,
matching uses the bundled directory, forms are kept locally.

**Full platform:** `docker build -t lumea . && docker run -p 4477:4477 -v lumea-data:/data -e LUMEA_ADMIN_EMAIL=you@x.com lumea`
or `npm start` on any Node 22 host (Fly, Railway, Render, Hetzner). Put it behind Cloudflare to get free edge
geo headers. SQLite lives in `LUMEA_DATA_DIR` (default `.data/`), uploads under `.data/uploads/`.

## Adding a market or language

- **City:** one object in `data/cities.mjs` (coords, districts, hotels). The roster, pages, sitemap and matching
  update on the next build.
- **Treatment:** one line in `data/services.mjs` plus copy in `data/content/services.{de,en,es}.mjs`;
  set `popular: true` to get city landing pages.
- **Language:** add the code to `site.locales`, a `t.<code>` block in `data/i18n.mjs`, a
  `services.<code>.mjs`, URL segments in `src/lib/html.mjs`, and journal/Privé/legal/profile copy (see how `fr`
  and `it` were added for the exact touch points).

## Conversion layer

Live "just booked" ticker and today's-slots nudge are derived deterministically from the bundled directory
(seeded by city + hour, not random per visit), a trust strip under the hero, a satisfaction-guarantee line,
book buttons on every treatment card, and a sticky mobile bar (WhatsApp + Book) that becomes a floating pill
on desktop once the hero scrolls away. Trust figures live in `data/site.mjs → trust`.

## Before launch

Replace the placeholder address/register data in the legal pages, drop real verification tokens and analytics
into `.env`, connect a PSP for the prepaid flow, and swap the generated roster for real verified therapists.
