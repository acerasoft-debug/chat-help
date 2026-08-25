import { z } from "zod";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import { getPaymentProvider } from "@/lib/payments/provider";

const createSchema = z.object({
  serviceId: z.string().min(1),
  scheduledAt: z.coerce.date(),
  note: z.string().trim().max(1000).optional(),
  address: z.object({
    line1: z.string().trim().min(3).max(200),
    line2: z.string().trim().max(200).optional(),
    city: z.string().trim().min(2).max(100),
    postalCode: z.string().trim().max(12).optional(),
    country: z
      .string()
      .trim()
      .toUpperCase()
      .regex(/^[A-Z]{2}$/),
  }),
});

// The KYC gate lives here: browsing is public, but completing a booking
// requires an identity-verified customer.
export async function POST(req: Request) {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }
  if (session.role !== "CUSTOMER") {
    return Response.json({ error: "Only customers can book" }, { status: 403 });
  }

  const parsed = createSchema.safeParse(await req.json().catch(() => null));
  if (!parsed.success) {
    return Response.json({ error: "Invalid request" }, { status: 400 });
  }
  const { serviceId, scheduledAt, note, address } = parsed.data;

  if (scheduledAt.getTime() < Date.now() + 60 * 60 * 1000) {
    return Response.json(
      { error: "Bookings must be at least 1 hour in advance" },
      { status: 400 },
    );
  }

  const [customer, identity] = await Promise.all([
    prisma.customerProfile.findUnique({ where: { userId: session.userId } }),
    prisma.verification.findFirst({
      where: { userId: session.userId, type: "IDENTITY", status: "APPROVED" },
    }),
  ]);
  if (!customer) {
    return Response.json(
      { error: "Complete your profile first", code: "NO_PROFILE" },
      { status: 403 },
    );
  }
  if (!identity) {
    return Response.json(
      { error: "Identity verification required to book", code: "KYC_REQUIRED" },
      { status: 403 },
    );
  }

  const service = await prisma.service.findFirst({
    where: { id: serviceId, isActive: true, professional: { isListed: true } },
    include: { professional: true },
  });
  if (!service) {
    return Response.json({ error: "Service not found" }, { status: 404 });
  }

  const booking = await prisma.$transaction(async (tx) => {
    const addr = await tx.address.create({
      data: { userId: session.userId, ...address },
    });
    return tx.booking.create({
      data: {
        customerId: customer.id,
        professionalId: service.professionalId,
        serviceId: service.id,
        addressId: addr.id,
        scheduledAt,
        durationMin: service.durationMin,
        priceAmount: service.price,
        currency: service.currency,
        customerNote: note,
      },
    });
  });

  // Authorize the card now; funds are only captured into escrow when the
  // professional confirms.
  const provider = getPaymentProvider();
  const { providerRef } = await provider.authorize({
    bookingId: booking.id,
    amount: service.price.toString(),
    currency: service.currency,
  });
  await prisma.payment.create({
    data: {
      bookingId: booking.id,
      amount: service.price,
      currency: service.currency,
      status: "AUTHORIZED",
      provider: provider.name,
      providerRef,
    },
  });

  return Response.json({ ok: true, bookingId: booking.id }, { status: 201 });
}

export async function GET() {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

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
      customer: {
        select: { firstName: true, lastName: true, userId: true },
      },
      payment: { select: { status: true, amount: true, currency: true } },
      reviews: { select: { authorId: true, rating: true } },
    },
  });
  return Response.json({ bookings });
}
