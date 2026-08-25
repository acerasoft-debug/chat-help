import { notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

export const dynamic = "force-dynamic";

const badgeLabels: Record<string, string> = {
  IDENTITY: "Identity verified",
  CERTIFICATION: "Certified",
  BACKGROUND_CHECK: "Background checked",
};

export default async function ProfessionalPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  // Public page, but only listed (fully verified) professionals exist here.
  const pro = await prisma.professionalProfile.findFirst({
    where: { id, isListed: true },
    include: {
      services: {
        where: { isActive: true },
        include: { category: true },
      },
      user: {
        select: {
          verifications: {
            where: { status: "APPROVED" },
            select: { type: true },
          },
        },
      },
    },
  });
  if (!pro) notFound();

  const session = await getSession();

  return (
    <main className="mx-auto max-w-2xl px-6 py-14">
      <div className="flex items-center justify-between">
        <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
          MAISON
        </a>
        <a
          href="/explore"
          className="text-xs uppercase tracking-widest text-gold-deep underline"
        >
          Back to search
        </a>
      </div>

      <h1 className="mt-10 font-serif text-4xl text-ink">{pro.displayName}</h1>
      <p className="mt-2 text-sm text-ink-soft">
        {pro.baseCity}
        {pro.basePostalCode ? ` · ${pro.basePostalCode}` : ""} · serves within{" "}
        {pro.serviceRadiusKm} km
        {pro.yearsExperience ? ` · ${pro.yearsExperience} yrs experience` : ""}
      </p>

      <div className="mt-4 flex flex-wrap gap-2">
        {pro.user.verifications.map((v) => (
          <span
            key={v.type}
            className="border border-gold/50 px-3 py-1 text-xs text-gold-deep"
          >
            ✓ {badgeLabels[v.type] ?? v.type}
          </span>
        ))}
      </div>

      {pro.bio && (
        <p className="mt-6 leading-relaxed text-ink-soft">{pro.bio}</p>
      )}

      <section className="mt-10">
        <h2 className="text-xs uppercase tracking-widest text-gold-deep">
          Services & fixed prices
        </h2>
        <ul className="mt-4 divide-y divide-sand border-y border-sand">
          {pro.services.map((s) => (
            <li key={s.id} className="py-4">
              <div className="flex items-center justify-between">
                <span className="text-ink">{s.title}</span>
                <span className="text-ink">
                  {s.price.toString()} {s.currency}
                </span>
              </div>
              <p className="mt-1 text-sm text-ink-soft">
                {s.category.nameEn} · {s.durationMin} min at your address
              </p>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-10">
        {session ? (
          <p className="border-l-2 border-gold pl-6 text-ink-soft">
            Booking opens with our first city launch — you&apos;ll be able to
            invite {pro.displayName} to your address with escrow-protected
            payment.
          </p>
        ) : (
          <a
            href="/signup"
            className="inline-block bg-ink px-8 py-4 text-sm uppercase tracking-widest text-cream"
          >
            Sign up to invite to your home
          </a>
        )}
      </section>
    </main>
  );
}
