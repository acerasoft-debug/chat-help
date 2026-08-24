import { z } from "zod";
import { prisma } from "@/lib/db";
import { generateToken, MAGIC_LINK_TTL_MS } from "@/lib/auth/tokens";

const bodySchema = z.object({
  email: z.email().transform((e) => e.toLowerCase()),
  role: z.enum(["CUSTOMER", "PROFESSIONAL"]),
});

export async function POST(req: Request) {
  const parsed = bodySchema.safeParse(await req.json().catch(() => null));
  if (!parsed.success) {
    return Response.json({ error: "Invalid request" }, { status: 400 });
  }
  const { email, role } = parsed.data;

  const { raw, hash } = generateToken();
  await prisma.authToken.create({
    data: {
      email,
      role,
      tokenHash: hash,
      expiresAt: new Date(Date.now() + MAGIC_LINK_TTL_MS),
    },
  });

  const appUrl = process.env.APP_URL ?? "http://localhost:3000";
  const link = `${appUrl}/api/auth/verify?token=${raw}`;

  // TODO: deliver via transactional email provider. Until then the link is
  // only ever exposed outside production.
  if (process.env.NODE_ENV !== "production") {
    return Response.json({ ok: true, devLink: link });
  }
  return Response.json({ ok: true });
}
