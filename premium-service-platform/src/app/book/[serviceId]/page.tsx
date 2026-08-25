import { notFound, redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import { BookingForm } from "./BookingForm";

export const dynamic = "force-dynamic";

export default async function BookServicePage({
  params,
}: {
  params: Promise<{ serviceId: string }>;
}) {
  const { serviceId } = await params;

  const service = await prisma.service.findFirst({
    where: { id: serviceId, isActive: true, professional: { isListed: true } },
    include: {
      professional: { select: { id: true, displayName: true, baseCity: true } },
      category: { select: { nameEn: true } },
    },
  });
  if (!service) notFound();

  const session = await getSession();
  if (!session) redirect(`/signup`);

  const identityApproved = session.role === "CUSTOMER"
    ? Boolean(
        await prisma.verification.findFirst({
          where: {
            userId: session.userId,
            type: "IDENTITY",
            status: "APPROVED",
          },
        }),
      )
    : false;

  return (
    <main className="mx-auto max-w-md px-6 py-14">
      <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
        MAISON
      </a>

      <h1 className="mt-10 font-serif text-3xl text-ink">
        Invite to your home
      </h1>
      <div className="mt-4 border-l-2 border-gold pl-6">
        <p className="text-ink">{service.title}</p>
        <p className="mt-1 text-sm text-ink-soft">
          {service.professional.displayName} · {service.category.nameEn} ·{" "}
          {service.durationMin} min
        </p>
        <p className="mt-1 text-ink">
          {service.price.toString()} {service.currency}
          <span className="text-sm text-ink-soft">
            {" "}
            — held in escrow until you confirm completion
          </span>
        </p>
      </div>

      {session.role !== "CUSTOMER" ? (
        <p className="mt-8 text-ink-soft">
          Only customer accounts can book. You are signed in as a professional.
        </p>
      ) : !identityApproved ? (
        <div className="mt-8">
          <p className="text-ink-soft">
            One step left: verify your identity to book. Everyone on Maison is
            verified — professionals and customers alike.
          </p>
          <a
            href="/onboarding"
            className="mt-4 inline-block bg-ink px-6 py-3 text-sm uppercase tracking-widest text-cream"
          >
            Verify identity
          </a>
        </div>
      ) : (
        <BookingForm serviceId={service.id} />
      )}
    </main>
  );
}
