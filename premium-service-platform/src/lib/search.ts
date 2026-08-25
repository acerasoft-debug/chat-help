import { prisma } from "@/lib/db";

// Public discovery: only fully verified (isListed) professionals are ever
// returned. Proximity is postal-code prefix based until a real geocoder
// provider is wired in.

export type ProfessionalSearchResult = {
  id: string;
  displayName: string;
  bio: string | null;
  baseCity: string;
  area: string | null;
  serviceRadiusKm: number;
  yearsExperience: number;
  ratingAvg: number;
  ratingCount: number;
  badges: string[];
  services: {
    id: string;
    title: string;
    durationMin: number;
    price: string;
    currency: string;
    category: { slug: string; nameEn: string; nameTr: string };
  }[];
  proximity: number;
};

function prefixScore(a: string | null | undefined, b: string | undefined) {
  if (!a || !b) return 0;
  const x = a.toUpperCase();
  const y = b.toUpperCase();
  let i = 0;
  while (i < x.length && i < y.length && x[i] === y[i]) i++;
  return i;
}

export async function searchProfessionals(query: {
  city: string;
  postalCode?: string;
  category?: string;
}): Promise<ProfessionalSearchResult[]> {
  const { city, postalCode, category } = query;

  const professionals = await prisma.professionalProfile.findMany({
    where: {
      isListed: true,
      baseCity: { equals: city, mode: "insensitive" },
      ...(category
        ? { services: { some: { isActive: true, category: { slug: category } } } }
        : {}),
    },
    select: {
      id: true,
      displayName: true,
      bio: true,
      baseCity: true,
      basePostalCode: true,
      serviceRadiusKm: true,
      yearsExperience: true,
      ratingAvg: true,
      ratingCount: true,
      services: {
        where: { isActive: true },
        select: {
          id: true,
          title: true,
          durationMin: true,
          price: true,
          currency: true,
          category: { select: { slug: true, nameEn: true, nameTr: true } },
        },
      },
      user: {
        select: {
          verifications: {
            where: { status: "APPROVED" },
            select: { type: true },
          },
        },
      },
    },
    take: 50,
  });

  return professionals
    .map((p) => ({
      id: p.id,
      displayName: p.displayName,
      bio: p.bio,
      baseCity: p.baseCity,
      area: p.basePostalCode,
      serviceRadiusKm: p.serviceRadiusKm,
      yearsExperience: p.yearsExperience,
      ratingAvg: p.ratingAvg,
      ratingCount: p.ratingCount,
      badges: p.user.verifications.map((v) => v.type),
      services: p.services.map((s) => ({
        id: s.id,
        title: s.title,
        durationMin: s.durationMin,
        price: s.price.toString(),
        currency: s.currency,
        category: s.category,
      })),
      proximity: prefixScore(p.basePostalCode, postalCode),
    }))
    .sort(
      (a, b) =>
        b.proximity - a.proximity ||
        b.ratingAvg - a.ratingAvg ||
        b.ratingCount - a.ratingCount,
    );
}
