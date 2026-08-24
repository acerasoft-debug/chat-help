// Launch categories (phase 1). Health-regulated categories (doctors,
// nurses, physiotherapy) are intentionally deferred to a later phase
// with a dedicated compliance layer.

export type LaunchCategory = {
  slug: string;
  nameEn: string;
  nameTr: string;
  tagline: string;
  comingSoon?: boolean;
};

export const launchCategories: LaunchCategory[] = [
  {
    slug: "massage-wellness",
    nameEn: "Massage & Wellness",
    nameTr: "Masaj & Wellness",
    tagline: "Deep relaxation without leaving home — certified therapists at your door.",
  },
  {
    slug: "beauty",
    nameEn: "Beauty",
    nameTr: "Güzellik",
    tagline: "Manicure, skincare, lashes and event make-up, wherever you are.",
  },
  {
    slug: "personal-training",
    nameEn: "Personal Training",
    nameTr: "Kişisel Antrenörlük",
    tagline: "One-on-one training, yoga and pilates in your living room.",
  },
  {
    slug: "mens-grooming",
    nameEn: "Men's Grooming",
    nameTr: "Erkek Bakım",
    tagline: "Barber-grade cuts and grooming at home.",
    comingSoon: true,
  },
];
