import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import { getKycProvider } from "@/lib/kyc/provider";

export async function POST() {
  const session = await getSession();
  if (!session) {
    return Response.json({ error: "Unauthorized" }, { status: 401 });
  }

  const existing = await prisma.verification.findFirst({
    where: {
      userId: session.userId,
      type: "IDENTITY",
      status: { in: ["APPROVED", "PENDING", "IN_REVIEW"] },
    },
  });
  if (existing) {
    return Response.json({ status: existing.status });
  }

  const provider = getKycProvider();
  const result = await provider.start(session.userId);

  const verification = await prisma.verification.create({
    data: {
      userId: session.userId,
      type: "IDENTITY",
      status: result.instantStatus ?? "PENDING",
      provider: provider.name,
      providerRef: result.providerRef,
      reviewedAt: result.instantStatus ? new Date() : null,
    },
  });

  return Response.json({
    status: verification.status,
    redirectUrl: result.redirectUrl,
  });
}
