"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

const inputCls =
  "w-full border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold";
const labelCls =
  "mb-2 block text-xs uppercase tracking-widest text-gold-deep";
const buttonCls =
  "w-full bg-ink px-4 py-3 text-sm uppercase tracking-widest text-cream disabled:opacity-50";

function useSubmit(url: string) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function post(body: unknown) {
    setBusy(true);
    setError(null);
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "Something went wrong");
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong");
    } finally {
      setBusy(false);
    }
  }
  return { post, busy, error };
}

export function CustomerForm() {
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const { post, busy, error } = useSubmit("/api/onboarding/customer");

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        post({ firstName, lastName });
      }}
      className="space-y-6"
    >
      <div>
        <label htmlFor="firstName" className={labelCls}>
          First name
        </label>
        <input
          id="firstName"
          required
          value={firstName}
          onChange={(e) => setFirstName(e.target.value)}
          className={inputCls}
        />
      </div>
      <div>
        <label htmlFor="lastName" className={labelCls}>
          Last name
        </label>
        <input
          id="lastName"
          required
          value={lastName}
          onChange={(e) => setLastName(e.target.value)}
          className={inputCls}
        />
      </div>
      {error && <p className="text-sm text-red-700">{error}</p>}
      <button type="submit" disabled={busy} className={buttonCls}>
        {busy ? "Saving…" : "Continue"}
      </button>
    </form>
  );
}

export function ProfessionalForm() {
  const [displayName, setDisplayName] = useState("");
  const [baseCity, setBaseCity] = useState("");
  const [countryCode, setCountryCode] = useState("");
  const [basePostalCode, setBasePostalCode] = useState("");
  const [bio, setBio] = useState("");
  const { post, busy, error } = useSubmit("/api/onboarding/professional");

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        post({
          displayName,
          baseCity,
          countryCode,
          basePostalCode: basePostalCode || undefined,
          bio: bio || undefined,
        });
      }}
      className="space-y-6"
    >
      <div>
        <label htmlFor="displayName" className={labelCls}>
          Display name
        </label>
        <input
          id="displayName"
          required
          value={displayName}
          onChange={(e) => setDisplayName(e.target.value)}
          className={inputCls}
        />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <div className="col-span-2">
          <label htmlFor="baseCity" className={labelCls}>
            City
          </label>
          <input
            id="baseCity"
            required
            value={baseCity}
            onChange={(e) => setBaseCity(e.target.value)}
            className={inputCls}
          />
        </div>
        <div>
          <label htmlFor="countryCode" className={labelCls}>
            Country
          </label>
          <input
            id="countryCode"
            required
            maxLength={2}
            placeholder="TR"
            value={countryCode}
            onChange={(e) => setCountryCode(e.target.value.toUpperCase())}
            className={inputCls}
          />
        </div>
      </div>
      <div>
        <label htmlFor="basePostalCode" className={labelCls}>
          Postal code (your base area)
        </label>
        <input
          id="basePostalCode"
          maxLength={12}
          placeholder="34365"
          value={basePostalCode}
          onChange={(e) => setBasePostalCode(e.target.value)}
          className={inputCls}
        />
      </div>
      <div>
        <label htmlFor="bio" className={labelCls}>
          Short bio (optional)
        </label>
        <textarea
          id="bio"
          rows={3}
          value={bio}
          onChange={(e) => setBio(e.target.value)}
          className={inputCls}
        />
      </div>
      {error && <p className="text-sm text-red-700">{error}</p>}
      <button type="submit" disabled={busy} className={buttonCls}>
        {busy ? "Saving…" : "Continue"}
      </button>
    </form>
  );
}

export function StartKycButton() {
  const { post, busy, error } = useSubmit("/api/kyc/start");
  return (
    <div>
      <button onClick={() => post({})} disabled={busy} className={buttonCls}>
        {busy ? "Starting…" : "Start identity verification"}
      </button>
      {error && <p className="mt-3 text-sm text-red-700">{error}</p>}
    </div>
  );
}
