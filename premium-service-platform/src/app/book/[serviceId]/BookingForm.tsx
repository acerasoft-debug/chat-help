"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

const inputCls =
  "w-full border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold";
const labelCls = "mb-2 block text-xs uppercase tracking-widest text-gold-deep";

export function BookingForm({ serviceId }: { serviceId: string }) {
  const router = useRouter();
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [line1, setLine1] = useState("");
  const [city, setCity] = useState("");
  const [postalCode, setPostalCode] = useState("");
  const [country, setCountry] = useState("TR");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await fetch("/api/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          serviceId,
          scheduledAt: new Date(`${date}T${time}`).toISOString(),
          note: note || undefined,
          address: {
            line1,
            city,
            postalCode: postalCode || undefined,
            country,
          },
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "Something went wrong");
      router.push("/bookings");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong");
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="mt-8 space-y-6">
      <div className="grid grid-cols-2 gap-3">
        <div>
          <label htmlFor="date" className={labelCls}>
            Date
          </label>
          <input
            id="date"
            type="date"
            required
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className={inputCls}
          />
        </div>
        <div>
          <label htmlFor="time" className={labelCls}>
            Time
          </label>
          <input
            id="time"
            type="time"
            required
            value={time}
            onChange={(e) => setTime(e.target.value)}
            className={inputCls}
          />
        </div>
      </div>

      <div>
        <label htmlFor="line1" className={labelCls}>
          Address
        </label>
        <input
          id="line1"
          required
          placeholder="Street, building, apartment"
          value={line1}
          onChange={(e) => setLine1(e.target.value)}
          className={inputCls}
        />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <div>
          <label htmlFor="bCity" className={labelCls}>
            City
          </label>
          <input
            id="bCity"
            required
            value={city}
            onChange={(e) => setCity(e.target.value)}
            className={inputCls}
          />
        </div>
        <div>
          <label htmlFor="bPostal" className={labelCls}>
            Postal code
          </label>
          <input
            id="bPostal"
            value={postalCode}
            onChange={(e) => setPostalCode(e.target.value)}
            className={inputCls}
          />
        </div>
        <div>
          <label htmlFor="bCountry" className={labelCls}>
            Country
          </label>
          <input
            id="bCountry"
            required
            maxLength={2}
            value={country}
            onChange={(e) => setCountry(e.target.value.toUpperCase())}
            className={inputCls}
          />
        </div>
      </div>

      <div>
        <label htmlFor="note" className={labelCls}>
          Note for the professional (optional)
        </label>
        <textarea
          id="note"
          rows={2}
          placeholder="Parking, pets, preferences…"
          value={note}
          onChange={(e) => setNote(e.target.value)}
          className={inputCls}
        />
      </div>

      {error && <p className="text-sm text-red-700">{error}</p>}

      <button
        type="submit"
        disabled={busy}
        className="w-full bg-ink px-4 py-3 text-sm uppercase tracking-widest text-cream disabled:opacity-50"
      >
        {busy ? "Booking…" : "Confirm booking"}
      </button>
      <p className="text-xs leading-relaxed text-ink-soft">
        Your card is authorized now and captured into escrow only when the
        professional confirms. Funds are released after you confirm the
        service is complete.
      </p>
    </form>
  );
}
