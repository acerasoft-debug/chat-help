import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth/session";
import {
  CustomerForm,
  ProfessionalForm,
  StartKycButton,
} from "./OnboardingForms";

export const dynamic = "force-dynamic";

export default async function OnboardingPage() {
  const session = await getSession();
  if (!session) redirect("/signup");

  const user = await prisma.user.findUnique({
    where: { id: session.userId },
    include: {
      customerProfile: true,
      professionalProfile: true,
      verifications: { where: { type: "IDENTITY" } },
    },
  });
  if (!user) redirect("/signup");

  const hasProfile =
    user.role === "CUSTOMER"
      ? Boolean(user.customerProfile)
      : Boolean(user.professionalProfile);
  const identity = user.verifications[0];
  const identityApproved = identity?.status === "APPROVED";

  if (hasProfile && identityApproved) redirect("/dashboard");

  return (
    <main className="mx-auto max-w-md px-6 py-20">
      <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
        MAISON
      </a>

      {!hasProfile ? (
        <>
          <h1 className="mt-10 font-serif text-3xl text-ink">
            Tell us about yourself
          </h1>
          <p className="mt-3 leading-relaxed text-ink-soft">
            {user.role === "CUSTOMER"
              ? "Your name, as it will appear to professionals you book."
              : "Your public profile basics — you can refine everything later."}
          </p>
          <div className="mt-8">
            {user.role === "CUSTOMER" ? <CustomerForm /> : <ProfessionalForm />}
          </div>
        </>
      ) : (
        <>
          <h1 className="mt-10 font-serif text-3xl text-ink">
            Verify your identity
          </h1>
          <p className="mt-3 leading-relaxed text-ink-soft">
            Everyone on Maison — customers and professionals — verifies a
            government ID before their first booking. It takes about two
            minutes.
          </p>
          <div className="mt-8">
            {identity ? (
              <p className="border-l-2 border-gold pl-6 text-ink">
                Verification status:{" "}
                <span className="uppercase tracking-widest">
                  {identity.status}
                </span>
              </p>
            ) : (
              <StartKycButton />
            )}
          </div>
        </>
      )}
    </main>
  );
}
