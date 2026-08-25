import { z } from "zod";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

const bodySchema = z.object({
  rating: z.number().int().min(1).max(5),
  comment: z.string().trim().max(2000).optional(),
});

// Two-way, booking-verified reviews: only participants of a COMPLETED
// booking, one review per side (enforced by @@unique([bookingId, authorId])).
export async function POST(
  req: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

  const parsed = bodySchema.safeParse(await req.json().catch(() => null));
  if (!parsed.success) {
    return Response.json({ error: "Invalid request" }, { status: 400 });
  }
  const { rating, comment } = parsed.data;

  const booking = await prisma.booking.findUnique({
    where: { id },
    include: {
      customer: { select: { userId: true } },
      professional: { select: { id: true, userId: true } },
    },
  });
  if (!booking) {
    return Response.json({ error: "Not found" }, { status: 404 });
  }
  if (booking.status !== "COMPLETED") {
    return Response.json(
      { error: "Only completed bookings can be reviewed" },
      { status: 409 },
    );
  }

  const participants = [booking.customer.userId, booking.professional.userId];
  if (!participants.includes(session.userId)) {
    return Response.json({ error: "Forbidden" }, { status: 403 });
  }
  const subjectId = participants.find((u) => u !== session.userId)!;

  const existing = await prisma.review.findUnique({
    where: {
      bookingId_authorId: { bookingId: booking.id, authorId: session.userId },
    },
  });
  if (existing) {
    return Response.json({ error: "Already reviewed" }, { status: 409 });
  }

  await prisma.$transaction(async (tx) => {
    await tx.review.create({
      data: {
        bookingId: booking.id,
        authorId: session.userId,
        subjectId,
        rating,
        comment,
      },
    });
    // Keep the professional's denormalized rating in sync when the review
    // is about them.
    if (subjectId === booking.professional.userId) {
      const profile = await tx.professionalProfile.findUniqueOrThrow({
        where: { id: booking.professional.id },
      });
      const newCount = profile.ratingCount + 1;
      const newAvg =
        (profile.ratingAvg * profile.ratingCount + rating) / newCount;
      await tx.professionalProfile.update({
        where: { id: profile.id },
        data: { ratingAvg: newAvg, ratingCount: newCount },
      });
    }
  });

  return Response.json({ ok: true }, { status: 201 });
}
