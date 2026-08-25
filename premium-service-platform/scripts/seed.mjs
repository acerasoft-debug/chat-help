// Dev seed: launch categories + a few fully verified demo professionals in
// Istanbul so /explore has something to show.
// Run with: node --env-file=.env scripts/seed.mjs
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

const categories = [
  { slug: "massage-wellness", nameEn: "Massage & Wellness", nameTr: "Masaj & Wellness", sortOrder: 1 },
  { slug: "beauty", nameEn: "Beauty", nameTr: "Güzellik", sortOrder: 2 },
  { slug: "personal-training", nameEn: "Personal Training", nameTr: "Kişisel Antrenörlük", sortOrder: 3 },
];

const demoPros = [
  {
    email: "demo.aylin@example.com",
    displayName: "Aylin K. — Wellness Therapist",
    bio: "Swedish and deep-tissue massage, 10 years of spa experience. Brings a full setup: table, warm towels, oils.",
    baseCity: "Istanbul",
    countryCode: "TR",
    basePostalCode: "34365",
    yearsExperience: 10,
    ratingAvg: 4.9,
    ratingCount: 87,
    services: [
      { category: "massage-wellness", title: "Swedish Massage (home visit)", durationMin: 60, price: "1800", currency: "TRY" },
      { category: "massage-wellness", title: "Deep Tissue Massage", durationMin: 90, price: "2600", currency: "TRY" },
    ],
  },
  {
    email: "demo.melis@example.com",
    displayName: "Melis Beauty Studio",
    bio: "Manicure, gel polish and event make-up at your home. Hygiene-first: single-use kit for every visit.",
    baseCity: "Istanbul",
    countryCode: "TR",
    basePostalCode: "34349",
    yearsExperience: 6,
    ratingAvg: 4.8,
    ratingCount: 54,
    services: [
      { category: "beauty", title: "Manicure + Gel Polish", durationMin: 75, price: "1200", currency: "TRY" },
      { category: "beauty", title: "Event Make-up", durationMin: 60, price: "2500", currency: "TRY" },
    ],
  },
  {
    email: "demo.emre@example.com",
    displayName: "Emre T. — Personal Trainer",
    bio: "Functional training and mobility coaching in your living room. Equipment provided.",
    baseCity: "Istanbul",
    countryCode: "TR",
    basePostalCode: "34710",
    yearsExperience: 8,
    ratingAvg: 5.0,
    ratingCount: 32,
    services: [
      { category: "personal-training", title: "1:1 Functional Training", durationMin: 60, price: "1500", currency: "TRY" },
      { category: "personal-training", title: "4-Session Starter Pack", durationMin: 60, price: "5400", currency: "TRY" },
    ],
  },
];

for (const c of categories) {
  await prisma.serviceCategory.upsert({
    where: { slug: c.slug },
    create: c,
    update: c,
  });
}

for (const p of demoPros) {
  const user = await prisma.user.upsert({
    where: { email: p.email },
    create: { email: p.email, role: "PROFESSIONAL" },
    update: {},
  });

  for (const type of ["IDENTITY", "CERTIFICATION"]) {
    const existing = await prisma.verification.findFirst({
      where: { userId: user.id, type, status: "APPROVED" },
    });
    if (!existing) {
      await prisma.verification.create({
        data: {
          userId: user.id,
          type,
          status: "APPROVED",
          provider: "seed",
          reviewedAt: new Date(),
        },
      });
    }
  }

  const profile = await prisma.professionalProfile.upsert({
    where: { userId: user.id },
    create: {
      userId: user.id,
      displayName: p.displayName,
      bio: p.bio,
      baseCity: p.baseCity,
      countryCode: p.countryCode,
      basePostalCode: p.basePostalCode,
      yearsExperience: p.yearsExperience,
      ratingAvg: p.ratingAvg,
      ratingCount: p.ratingCount,
      isListed: true,
    },
    update: {
      displayName: p.displayName,
      bio: p.bio,
      basePostalCode: p.basePostalCode,
      ratingAvg: p.ratingAvg,
      ratingCount: p.ratingCount,
      isListed: true,
    },
  });

  for (const s of p.services) {
    const category = await prisma.serviceCategory.findUniqueOrThrow({
      where: { slug: s.category },
    });
    const existing = await prisma.service.findFirst({
      where: { professionalId: profile.id, title: s.title },
    });
    if (!existing) {
      await prisma.service.create({
        data: {
          professionalId: profile.id,
          categoryId: category.id,
          title: s.title,
          durationMin: s.durationMin,
          price: s.price,
          currency: s.currency,
        },
      });
    }
  }
}

console.log("Seed complete.");
await prisma.$disconnect();
