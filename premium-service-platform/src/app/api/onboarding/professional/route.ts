import { z } from "zod";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

const bodySchema = z.object({
  displayName: z.string().trim().min(2).max(100),
  baseCity: z.string().trim().min(2).max(100),
  countryCode: z
    .string()
    .trim()
    .toUpperCase()
    .regex(/^[A-Z]{2}$/, "ISO 3166-1 alpha-2 expected"),
  basePostalCode: z.string().trim().min(2).max(12).optional(),
  bio: z.string().trim().max(1000).optional(),
  yearsExperience: z.number().int().min(0).max(60).optional(),
});

export async function POST(req: Request) {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }
  if (session.role !== "PROFESSIONAL") {
    return Response.json({ error: "Wrong role" }, { status: 403 });
  }

  const parsed = bodySchema.safeParse(await req.json().catch(() => null));
  if (!parsed.success) {
    return Response.json({ error: "Invalid request" }, { status: 400 });
  }

  // isListed stays false until every required verification is APPROVED;
  // listing is flipped by the verification pipeline, never here.
  const profile = await prisma.professionalProfile.upsert({
    where: { userId: session.userId },
    create: { userId: session.userId, ...parsed.data },
    update: parsed.data,
  });
  return Response.json({ ok: true, profileId: profile.id });
}
