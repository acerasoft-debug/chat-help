import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { hashToken } from "@/lib/auth/tokens";
import { createSession } from "@/lib/auth/session";

export async function GET(req: NextRequest) {
  const raw = req.nextUrl.searchParams.get("token");
  if (!raw) {
    return NextResponse.redirect(new URL("/signup?error=invalid", req.url));
  }

  const token = await prisma.authToken.findUnique({
    where: { tokenHash: hashToken(raw) },
  });
  if (!token || token.usedAt || token.expiresAt < new Date()) {
    return NextResponse.redirect(new URL("/signup?error=expired", req.url));
  }

  const user = await prisma.$transaction(async (tx) => {
    await tx.authToken.update({
      where: { id: token.id },
      data: { usedAt: new Date() },
    });
    const existing = await tx.user.findUnique({
      where: { email: token.email },
    });
    if (existing) return existing;
    return tx.user.create({
      data: { email: token.email, role: token.role ?? "CUSTOMER" },
    });
  });

  await createSession({ userId: user.id, role: user.role });
  return NextResponse.redirect(new URL("/onboarding", req.url));
}
