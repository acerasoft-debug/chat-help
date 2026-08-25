import { NextRequest } from "next/server";
import { z } from "zod";
import { searchProfessionals } from "@/lib/search";

const querySchema = z.object({
  city: z.string().trim().min(2).max(100),
  postalCode: z.string().trim().max(12).optional(),
  category: z.string().trim().max(64).optional(),
});

export async function GET(req: NextRequest) {
  const params = Object.fromEntries(req.nextUrl.searchParams.entries());
  const parsed = querySchema.safeParse(params);
  if (!parsed.success) {
    return Response.json({ error: "Invalid query" }, { status: 400 });
  }
  const results = await searchProfessionals(parsed.data);
  return Response.json({ results });
}
