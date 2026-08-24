import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Maison — Premium services at your home",
  description:
    "Verified massage therapists, beauty specialists and personal trainers, at your address. Identity-verified professionals, escrow payments, insured visits.",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
