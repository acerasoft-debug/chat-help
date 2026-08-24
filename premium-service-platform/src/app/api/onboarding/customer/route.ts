import { z } from "zod";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

const bodySchema = z.object({
  firstName: z.string().trim().min(1).max(100),
  lastName: z.string().trim().min(1).max(100),
});

export async function POST(req: Request) {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }
  if (session.role !== "CUSTOMER") {
    return Response.json({ error: "Wrong role" }, { status: 403 });
  }

  const parsed = bodySchema.safeParse(await req.json().catch(() => null));
  if (!parsed.success) {
    return Response.json({ error: "Invalid request" }, { status: 400 });
  }

  const profile = await prisma.customerProfile.upsert({
    where: { userId: session.userId },
    create: { userId: session.userId, ...parsed.data },
    update: parsed.data,
  });
  return Response.json({ ok: true, profileId: profile.id });
}
