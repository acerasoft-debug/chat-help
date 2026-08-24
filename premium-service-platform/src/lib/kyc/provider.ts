// Identity-verification provider abstraction. Real providers (Stripe
// Identity, Onfido, Sumsub) host the document/liveness flow themselves and
// report back via webhook; we only store their session reference.

export type KycStartResult = {
  providerRef: string;
  // Hosted-flow URL to redirect the user to (absent for the mock).
  redirectUrl?: string;
  // Set when the provider resolves synchronously (mock only) — real
  // providers resolve via webhook instead.
  instantStatus?: "APPROVED";
};

export interface KycProvider {
  name: string;
  start(userId: string): Promise<KycStartResult>;
}

// Auto-approving stub for local development: behaves as if the hosted flow
// completed and the provider webhook fired immediately.
class MockKycProvider implements KycProvider {
  name = "mock";

  async start(userId: string): Promise<KycStartResult> {
    return {
      providerRef: `mock_${userId}_${Date.now()}`,
      instantStatus: "APPROVED",
    };
  }
}

export function getKycProvider(): KycProvider {
  const configured = process.env.KYC_PROVIDER ?? "mock";
  switch (configured) {
    case "mock":
      return new MockKycProvider();
    default:
      throw new Error(`Unknown KYC_PROVIDER: ${configured}`);
  }
}
