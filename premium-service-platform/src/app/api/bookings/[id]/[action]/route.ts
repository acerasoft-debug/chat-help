import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import { getPaymentProvider } from "@/lib/payments/provider";

// Booking state machine transitions. Who may do what, and what happens to
// the escrow:
//   confirm  (professional): PENDING_CONFIRMATION -> CONFIRMED, capture into escrow
//   decline  (professional): PENDING_CONFIRMATION -> CANCELLED_BY_PROFESSIONAL, refund
//   cancel   (customer):     PENDING_CONFIRMATION | CONFIRMED -> CANCELLED_BY_CUSTOMER, refund
//   complete (customer):     CONFIRMED -> COMPLETED, release to professional
// TODO: cancellation-window fees, check-in/check-out, disputes.

const transitions = {
  confirm: {
    actor: "professional",
    from: ["PENDING_CONFIRMATION"],
    to: "CONFIRMED",
    payment: { from: "AUTHORIZED", to: "ESCROW_HELD", call: "capture" },
  },
  decline: {
    actor: "professional",
    from: ["PENDING_CONFIRMATION"],
    to: "CANCELLED_BY_PROFESSIONAL",
    payment: { from: "AUTHORIZED", to: "REFUNDED", call: "refund" },
  },
  cancel: {
    actor: "customer",
    from: ["PENDING_CONFIRMATION", "CONFIRMED"],
    to: "CANCELLED_BY_CUSTOMER",
    payment: { from: null, to: "REFUNDED", call: "refund" },
  },
  complete: {
    actor: "customer",
    from: ["CONFIRMED"],
    to: "COMPLETED",
    payment: { from: "ESCROW_HELD", to: "RELEASED", call: "release" },
  },
} as const;

type Action = keyof typeof transitions;

export async function POST(
  _req: Request,
  { params }: { params: Promise<{ id: string; action: string }> },
) {
  const { id, action } = await params;
  if (!(action in transitions)) {
    return Response.json({ error: "Unknown action" }, { status: 404 });
  }
  const t = transitions[action as Action];

  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

  const booking = await prisma.booking.findUnique({
    where: { id },
    include: {
      customer: { select: { userId: true } },
      professional: { select: { userId: true } },
      payment: true,
    },
  });
  if (!booking) {
    return Response.json({ error: "Not found" }, { status: 404 });
  }

  const actorUserId =
    t.actor === "customer"
      ? booking.customer.userId
      : booking.professional.userId;
  if (actorUserId !== session.userId) {
    return Response.json({ error: "Forbidden" }, { status: 403 });
  }

  if (!(t.from as readonly string[]).includes(booking.status)) {
    return Response.json(
      { error: `Cannot ${action} a ${booking.status} booking` },
      { status: 409 },
    );
  }

  const payment = booking.payment;
  if (payment?.providerRef) {
    if (t.payment.from && payment.status !== t.payment.from) {
      return Response.json(
        { error: `Payment is ${payment.status}` },
        { status: 409 },
      );
    }
    const provider = getPaymentProvider();
    await provider[t.payment.call](payment.providerRef);
  }

  const now = new Date();
  const updated = await prisma.$transaction(async (tx) => {
    if (payment) {
      await tx.payment.update({
        where: { id: payment.id },
        data: {
          status: t.payment.to,
          ...(t.payment.to === "ESCROW_HELD" ? { capturedAt: now } : {}),
          ...(t.payment.to === "RELEASED" ? { releasedAt: now } : {}),
          ...(t.payment.to === "REFUNDED" ? { refundedAt: now } : {}),
        },
      });
    }
    return tx.booking.update({
      where: { id: booking.id },
      data: { status: t.to },
    });
  });

  return Response.json({ ok: true, status: updated.status });
}
