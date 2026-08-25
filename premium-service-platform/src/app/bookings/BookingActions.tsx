"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

const buttonCls =
  "bg-ink px-5 py-2.5 text-xs uppercase tracking-widest text-cream disabled:opacity-50";
const outlineCls =
  "border border-sand px-5 py-2.5 text-xs uppercase tracking-widest text-ink-soft disabled:opacity-50";

export function BookingActions({
  bookingId,
  status,
  isCustomer,
  alreadyReviewed,
}: {
  bookingId: string;
  status: string;
  isCustomer: boolean;
  alreadyReviewed: boolean;
}) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reviewing, setReviewing] = useState(false);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");

  async function call(path: string, body?: unknown) {
    setBusy(true);
    setError(null);
    try {
      const res = await fetch(path, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: body ? JSON.stringify(body) : undefined,
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "Something went wrong");
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong");
    } finally {
      setBusy(false);
      setReviewing(false);
    }
  }

  const act = (action: string) => call(`/api/bookings/${bookingId}/${action}`);

  return (
    <div className="mt-4">
      <div className="flex flex-wrap gap-3">
        {!isCustomer && status === "PENDING_CONFIRMATION" && (
          <>
            <button disabled={busy} onClick={() => act("confirm")} className={buttonCls}>
              Confirm
            </button>
            <button disabled={busy} onClick={() => act("decline")} className={outlineCls}>
              Decline
            </button>
          </>
        )}
        {isCustomer && ["PENDING_CONFIRMATION", "CONFIRMED"].includes(status) && (
          <button disabled={busy} onClick={() => act("cancel")} className={outlineCls}>
            Cancel
          </button>
        )}
        {isCustomer && status === "CONFIRMED" && (
          <button disabled={busy} onClick={() => act("complete")} className={buttonCls}>
            Confirm completion
          </button>
        )}
        {status === "COMPLETED" && !alreadyReviewed && !reviewing && (
          <button disabled={busy} onClick={() => setReviewing(true)} className={buttonCls}>
            Leave review
          </button>
        )}
      </div>

      {reviewing && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            call(`/api/bookings/${bookingId}/review`, {
              rating,
              comment: comment || undefined,
            });
          }}
          className="mt-4 space-y-3"
        >
          <div className="flex items-center gap-3">
            <label htmlFor={`rating-${bookingId}`} className="text-xs uppercase tracking-widest text-gold-deep">
              Rating
            </label>
            <select
              id={`rating-${bookingId}`}
              value={rating}
              onChange={(e) => setRating(Number(e.target.value))}
              className="border border-sand bg-white px-3 py-2 text-ink"
            >
              {[5, 4, 3, 2, 1].map((r) => (
                <option key={r} value={r}>
                  {"★".repeat(r)}
                </option>
              ))}
            </select>
          </div>
          <textarea
            rows={2}
            placeholder="How was it?"
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            className="w-full border border-sand bg-white px-4 py-3 text-ink outline-none focus:border-gold"
          />
          <button type="submit" disabled={busy} className={buttonCls}>
            Submit review
          </button>
        </form>
      )}

      {error && <p className="mt-3 text-sm text-red-700">{error}</p>}
    </div>
  );
}
