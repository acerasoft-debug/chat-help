"use client";

import { useState } from "react";

type Role = "CUSTOMER" | "PROFESSIONAL";

export default function SignupPage() {
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<Role>("CUSTOMER");
  const [sent, setSent] = useState(false);
  const [devLink, setDevLink] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await fetch("/api/auth/request-link", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, role }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "Something went wrong");
      setSent(true);
      setDevLink(data.devLink ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="mx-auto max-w-md px-6 py-20">
      <a href="/" className="font-serif text-xl tracking-[0.2em] text-ink">
        MAISON
      </a>
      <h1 className="mt-10 font-serif text-3xl text-ink">Join Maison</h1>
      <p className="mt-3 leading-relaxed text-ink-soft">
        We&apos;ll email you a secure sign-in link. Identity verification comes
        right after — for everyone, on both sides of the door.
      </p>

      {sent ? (
        <div className="mt-8 border-l-2 border-gold pl-6">
          <p className="text-ink">
            Check your inbox — your sign-in link is on its way.
          </p>
          {devLink && (
            <p className="mt-4 break-all text-sm text-ink-soft">
              Dev only:{" "}
              <a href={devLink} className="text-gold-deep underline">
                {devLink}
              </a>
            </p>
          )}
        </div>
      ) : (
        <form onSubmit={submit} className="mt-8 space-y-6">
          <fieldset className="grid grid-cols-2 gap-3">
            <legend className="mb-2 text-xs uppercase tracking-widest text-gold-deep">
              I am a
            </legend>
            {(
              [
                ["CUSTOMER", "Customer"],
                ["PROFESSIONAL", "Professional"],
              ] as const
            ).map(([value, label]) => (
              <label
                key={value}
                className={`cursor-pointer border px-4 py-3 text-center text-sm ${
                  role === value
                    ? "border-gold bg-white text-ink"
                    : "border-sand text-ink-soft"
                }`}
              >
                <input
                  type="radio"
                  name="role"
                  value={value}
                  checked={role === value}
                  onChange={() => setRole(value)}
                  className="sr-only"
                />
                {label}
              </label>
            ))}
          </fieldset>

          <div>
            <label
              htmlFor="email"
              className="mb-2 block text-xs uppercase tracking-widest text-gold-deep"
            >
              Email
            </label>
            <input
              id="email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              className="w-full border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold"
            />
          </div>

          {error && <p className="text-sm text-red-700">{error}</p>}

          <button
            type="submit"
            disabled={busy}
            className="w-full bg-ink px-4 py-3 text-sm uppercase tracking-widest text-cream disabled:opacity-50"
          >
            {busy ? "Sending…" : "Send sign-in link"}
          </button>
        </form>
      )}
    </main>
  );
}
