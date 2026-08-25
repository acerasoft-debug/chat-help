import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import { BookingActions } from "./BookingActions";

export const dynamic = "force-dynamic";

const statusLabels: Record<string, string> = {
  PENDING_CONFIRMATION: "Awaiting confirmation",
  CONFIRMED: "Confirmed",
  IN_PROGRESS: "In progress",
  COMPLETED: "Completed",
  CANCELLED_BY_CUSTOMER: "Cancelled by customer",
  CANCELLED_BY_PROFESSIONAL: "Declined by professional",
  DISPUTED: "In dispute",
};

const paymentLabels: Record<string, string> = {
  REQUIRES_PAYMENT: "Payment required",
  AUTHORIZED: "Card authorized",
  ESCROW_HELD: "Held in escrow",
  RELEASED: "Paid out",
  REFUNDED: "Refunded",
  FAILED: "Payment failed",
};

export default async function BookingsPage() {
  const session = await getSession();
  if (!session) redirect("/signup");

  const bookings = await prisma.booking.findMany({
    where: {
      OR: [
        { customer: { userId: session.userId } },
        { professional: { userId: session.userId } },
      ],
    },
    orderBy: { scheduledAt: "desc" },
    include: {
      service: { select: { title: true } },
      professional: { select: { displayName: true, userId: true } },
      customer: { select: { firstName: true, lastName: true, userId: true } },
      address: { select: { line1: true, city: true, postalCode: true } },
      payment: { select: { status: true, amount: true, currency: true } },
      reviews: { select: { authorId: true } },
    },
  });

  return (
    <main className="mx-auto max-w-2xl px-6 py-14">
      <div className="flex items-center justify-between">
        <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
          MAISON
        </a>
        <a
          href="/dashboard"
          className="text-xs uppercase tracking-widest text-gold-deep underline"
        >
          Dashboard
        </a>
      </div>

      <h1 className="mt-10 font-serif text-3xl text-ink">Bookings</h1>

      {bookings.length === 0 && (
        <p className="mt-6 text-ink-soft">
          No bookings yet.{" "}
          <a href="/explore" className="text-gold-deep underline">
            Find a professional near you.
          </a>
        </p>
      )}

      <div className="mt-8 space-y-6">
        {bookings.map((b) => {
          const isCustomer = b.customer.userId === session.userId;
          const otherName = isCustomer
            ? b.professional.displayName
            : `${b.customer.firstName} ${b.customer.lastName}`;
          const alreadyReviewed = b.reviews.some(
            (r) => r.authorId === session.userId,
          );
          // The exact address is only revealed to the professional once the
          // booking is confirmed.
          const showAddress =
            isCustomer || ["CONFIRMED", "IN_PROGRESS", "COMPLETED"].includes(b.status);

          return (
            <article key={b.id} className="border border-sand bg-white/60 p-6">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="font-serif text-xl text-ink">
                    {b.service.title}
                  </h2>
                  <p className="mt-1 text-sm text-ink-soft">
                    {isCustomer ? "with" : "for"} {otherName} ·{" "}
                    {b.scheduledAt.toISOString().slice(0, 16).replace("T", " ")}
                  </p>
                  <p className="mt-1 text-sm text-ink-soft">
                    {showAddress
                      ? `${b.address.line1}, ${b.address.city}${b.address.postalCode ? ` ${b.address.postalCode}` : ""}`
                      : `${b.address.city} — full address revealed on confirmation`}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-ink">
                    {b.priceAmount.toString()} {b.currency}
                  </p>
                  <p className="mt-1 text-xs uppercase tracking-widest text-gold-deep">
                    {statusLabels[b.status] ?? b.status}
                  </p>
                  {b.payment && (
                    <p className="mt-1 text-xs text-ink-soft">
                      {paymentLabels[b.payment.status] ?? b.payment.status}
                    </p>
                  )}
                </div>
              </div>

              {b.customerNote && isCustomer === false && (
                <p className="mt-3 border-l-2 border-sand pl-4 text-sm text-ink-soft">
                  “{b.customerNote}”
                </p>
              )}

              <BookingActions
                bookingId={b.id}
                status={b.status}
                isCustomer={isCustomer}
                alreadyReviewed={alreadyReviewed}
              />
            </article>
          );
        })}
      </div>
    </main>
  );
}
