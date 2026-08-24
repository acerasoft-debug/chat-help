import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";

export async function GET() {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

  const user = await prisma.user.findUnique({
    where: { id: session.userId },
    select: {
      id: true,
      email: true,
      role: true,
      customerProfile: {
        select: { firstName: true, lastName: true },
      },
      professionalProfile: {
        select: { displayName: true, baseCity: true, isListed: true },
      },
      verifications: {
        select: { type: true, status: true, createdAt: true },
        orderBy: { createdAt: "desc" },
      },
    },
  });
  if (!user) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }
  return Response.json({ user });
}
