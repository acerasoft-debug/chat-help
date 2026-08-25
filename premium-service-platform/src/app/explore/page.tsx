import { launchCategories } from "@/lib/categories";
import { searchProfessionals } from "@/lib/search";

export const dynamic = "force-dynamic";

const badgeLabels: Record<string, string> = {
  IDENTITY: "Identity verified",
  CERTIFICATION: "Certified",
  BACKGROUND_CHECK: "Background checked",
};

export default async function ExplorePage({
  searchParams,
}: {
  searchParams: Promise<{ city?: string; postalCode?: string; category?: string }>;
}) {
  const params = await searchParams;
  const city = params.city?.trim() ?? "";
  const postalCode = params.postalCode?.trim() || undefined;
  const category = params.category?.trim() || undefined;

  const results =
    city.length >= 2
      ? await searchProfessionals({ city, postalCode, category })
      : null;

  return (
    <main className="mx-auto max-w-4xl px-6 py-14">
      <div className="flex items-center justify-between">
        <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
          MAISON
        </a>
        <a
          href="/signup"
          className="text-xs uppercase tracking-widest text-gold-deep underline"
        >
          Join
        </a>
      </div>

      <h1 className="mt-10 font-serif text-3xl text-ink">
        Verified professionals near you
      </h1>
      <p className="mt-3 leading-relaxed text-ink-soft">
        Enter your city and postal code — every profile you see has passed
        identity verification. Prices are fixed and transparent.
      </p>

      <form method="get" className="mt-8 grid gap-3 md:grid-cols-[2fr_1fr_2fr_auto]">
        <input
          name="city"
          required
          defaultValue={city}
          placeholder="City (e.g. Istanbul)"
          className="border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold"
        />
        <input
          name="postalCode"
          defaultValue={postalCode ?? ""}
          placeholder="Postal code"
          className="border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold"
        />
        <select
          name="category"
          defaultValue={category ?? ""}
          className="border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold"
        >
          <option value="">All categories</option>
          {launchCategories
            .filter((c) => !c.comingSoon)
            .map((c) => (
              <option key={c.slug} value={c.slug}>
                {c.nameEn}
              </option>
            ))}
        </select>
        <button
          type="submit"
          className="bg-ink px-6 py-3 text-sm uppercase tracking-widest text-cream"
        >
          Search
        </button>
      </form>

      {results !== null && (
        <section className="mt-10 space-y-6">
          {results.length === 0 && (
            <p className="border-l-2 border-gold pl-6 text-ink-soft">
              No verified professionals in this area yet — we&apos;re launching
              city by city.
            </p>
          )}
          {results.map((p) => (
            <article key={p.id} className="border border-sand bg-white/60 p-6">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h2 className="font-serif text-2xl text-ink">
                    <a href={`/pro/${p.id}`} className="hover:underline">
                      {p.displayName}
                    </a>
                  </h2>
                  <p className="mt-1 text-sm text-ink-soft">
                    {p.baseCity}
                    {p.area ? ` · ${p.area}` : ""} · serves within{" "}
                    {p.serviceRadiusKm} km
                    {p.yearsExperience
                      ? ` · ${p.yearsExperience} yrs experience`
                      : ""}
                  </p>
                </div>
                <div className="text-right text-sm text-ink-soft">
                  {p.ratingCount > 0 ? (
                    <span>
                      ★ {p.ratingAvg.toFixed(1)}{" "}
                      <span className="text-xs">({p.ratingCount})</span>
                    </span>
                  ) : (
                    <span className="text-xs uppercase tracking-widest">
                      New
                    </span>
                  )}
                </div>
              </div>

              <div className="mt-3 flex flex-wrap gap-2">
                {p.badges.map((b) => (
                  <span
                    key={b}
                    className="border border-gold/50 px-3 py-1 text-xs text-gold-deep"
                  >
                    ✓ {badgeLabels[b] ?? b}
                  </span>
                ))}
              </div>

              {p.bio && (
                <p className="mt-4 leading-relaxed text-ink-soft">{p.bio}</p>
              )}

              <ul className="mt-4 divide-y divide-sand border-t border-sand">
                {p.services.map((s) => (
                  <li
                    key={s.id}
                    className="flex items-center justify-between py-3"
                  >
                    <span className="text-ink">
                      {s.title}{" "}
                      <span className="text-sm text-ink-soft">
                        · {s.durationMin} min
                      </span>
                    </span>
                    <span className="text-ink">
                      {s.price} {s.currency}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="mt-5">
                <a
                  href={`/pro/${p.id}`}
                  className="inline-block bg-ink px-6 py-3 text-sm uppercase tracking-widest text-cream"
                >
                  Invite to your home
                </a>
              </div>
            </article>
          ))}
        </section>
      )}
    </main>
  );
}
