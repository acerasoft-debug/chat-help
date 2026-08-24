import { launchCategories } from "@/lib/categories";

const trustPillars = [
  {
    title: "Identity-verified, always",
    body: "Every professional and every customer passes government-ID and liveness verification before their first booking. No anonymous strangers, on either side of the door.",
  },
  {
    title: "Credentials you can see",
    body: "Certificates, licenses and background checks are verified by us and shown as badges on every profile — transparency is the product.",
  },
  {
    title: "Payment held in escrow",
    body: "Your payment is held safely and released to the professional only after the service is completed. Cancellations and disputes are covered by clear guarantees.",
  },
  {
    title: "Safe at every step",
    body: "Live location sharing during the visit, check-in/check-out at the door, an in-app SOS button and insurance on every booking — for customers and professionals alike.",
  },
];

const steps = [
  {
    n: "01",
    title: "Choose & book",
    body: "Pick a verified professional, a time and your address. Transparent, fixed pricing — no surprises.",
  },
  {
    n: "02",
    title: "We come to you",
    body: "Your professional arrives with everything needed. Track their arrival live and confirm the start at your door.",
  },
  {
    n: "03",
    title: "Confirm & review",
    body: "Payment is released only when you confirm completion. Both sides leave a booking-verified review.",
  },
];

export default function Home() {
  return (
    <main>
      {/* Header */}
      <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <span className="font-serif text-2xl tracking-[0.2em] text-ink">
          MAISON
        </span>
        <a
          href="/signup"
          className="rounded-full border border-gold/40 px-4 py-1.5 text-xs uppercase tracking-widest text-gold-deep hover:border-gold"
        >
          Join early
        </a>
      </header>

      {/* Hero */}
      <section className="mx-auto max-w-6xl px-6 pb-20 pt-16 text-center">
        <p className="mb-6 text-xs uppercase tracking-[0.35em] text-gold-deep">
          Premium services, at your address
        </p>
        <h1 className="mx-auto max-w-3xl font-serif text-5xl leading-tight text-ink md:text-6xl">
          The people you invite into your home, fully verified.
        </h1>
        <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-ink-soft">
          Massage therapists, beauty specialists and personal trainers —
          identity-checked, certified and insured — delivered to your door with
          escrow-protected payments and transparent, two-way reviews.
        </p>
      </section>

      {/* Categories */}
      <section className="border-y border-sand bg-white/60">
        <div className="mx-auto grid max-w-6xl gap-px bg-sand px-0 md:grid-cols-4">
          {launchCategories.map((c) => (
            <div key={c.slug} className="bg-cream p-8">
              <h3 className="font-serif text-xl text-ink">{c.nameEn}</h3>
              <p className="mt-3 text-sm leading-relaxed text-ink-soft">
                {c.tagline}
              </p>
              {c.comingSoon && (
                <span className="mt-4 inline-block text-xs uppercase tracking-widest text-gold-deep">
                  Coming soon
                </span>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* Trust pillars */}
      <section className="mx-auto max-w-6xl px-6 py-20">
        <h2 className="text-center font-serif text-3xl text-ink">
          Trust is the product
        </h2>
        <div className="mt-12 grid gap-10 md:grid-cols-2">
          {trustPillars.map((p) => (
            <div key={p.title} className="border-l-2 border-gold pl-6">
              <h3 className="font-serif text-xl text-ink">{p.title}</h3>
              <p className="mt-2 leading-relaxed text-ink-soft">{p.body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* How it works */}
      <section className="border-t border-sand bg-white/60 py-20">
        <div className="mx-auto max-w-6xl px-6">
          <h2 className="text-center font-serif text-3xl text-ink">
            How it works
          </h2>
          <div className="mt-12 grid gap-10 md:grid-cols-3">
            {steps.map((s) => (
              <div key={s.n}>
                <span className="font-serif text-4xl text-gold">{s.n}</span>
                <h3 className="mt-3 font-serif text-xl text-ink">{s.title}</h3>
                <p className="mt-2 leading-relaxed text-ink-soft">{s.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="mx-auto max-w-6xl px-6 py-10 text-center text-sm text-ink-soft">
        <p>
          Maison — working title. Launching city by city, starting with one.
        </p>
      </footer>
    </main>
  );
}
