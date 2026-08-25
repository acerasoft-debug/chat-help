import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

export const dynamic = "force-dynamic";

const badgeLabels: Record<string, string> = {
  IDENTITY: "Identity verified",
  CERTIFICATION: "Certified",
  BACKGROUND_CHECK: "Background checked",
};

export default async function DashboardPage() {
  const session = await getSession();
  if (!session) redirect("/signup");

  const user = await prisma.user.findUnique({
    where: { id: session.userId },
    include: {
      customerProfile: true,
      professionalProfile: true,
      verifications: { where: { status: "APPROVED" } },
    },
  });
  if (!user) redirect("/signup");

  const name =
    user.customerProfile?.firstName ??
    user.professionalProfile?.displayName ??
    user.email;

  return (
    <main className="mx-auto max-w-2xl px-6 py-20">
      <div className="flex items-center justify-between">
        <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
          MAISON
        </a>
        <div className="flex items-center gap-5">
          <a
            href="/bookings"
            className="text-xs uppercase tracking-widest text-gold-deep underline"
          >
            Bookings
          </a>
          <form action="/api/auth/logout" method="post">
            <button className="text-xs uppercase tracking-widest text-ink-soft underline">
              Sign out
            </button>
          </form>
        </div>
      </div>

      <h1 className="mt-10 font-serif text-3xl text-ink">Welcome, {name}</h1>
      <p className="mt-3 leading-relaxed text-ink-soft">
        {user.role === "PROFESSIONAL"
          ? user.professionalProfile?.isListed
            ? "Your profile is live and bookable."
            : "Your profile is not listed yet — certification review is the next step."
          : "You're all set. Booking opens with our first city launch."}
      </p>

      <section className="mt-10">
        <h2 className="text-xs uppercase tracking-widest text-gold-deep">
          Trust badges
        </h2>
        <div className="mt-4 flex flex-wrap gap-3">
          {user.verifications.length === 0 && (
            <p className="text-sm text-ink-soft">No approved checks yet.</p>
          )}
          {user.verifications.map((v) => (
            <span
              key={v.id}
              className="border border-gold/50 px-4 py-2 text-sm text-gold-deep"
            >
              ✓ {badgeLabels[v.type] ?? v.type}
            </span>
          ))}
        </div>
      </section>
    </main>
  );
}
