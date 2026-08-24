import { createHash, randomBytes } from "crypto";

export const MAGIC_LINK_TTL_MS = 15 * 60 * 1000;

// The raw token goes into the magic link; only its hash is persisted,
// so a database leak cannot be replayed as a login.
export function generateToken(): { raw: string; hash: string } {
  const raw = randomBytes(32).toString("base64url");
  return { raw, hash: hashToken(raw) };
}

export function hashToken(raw: string): string {
  return createHash("sha256").update(raw).digest("hex");
}
