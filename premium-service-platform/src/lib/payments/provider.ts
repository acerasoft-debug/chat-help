// Payment provider abstraction for the escrow lifecycle:
//   authorize (at booking) -> capture/hold (at confirmation)
//   -> release (at completion) | refund (on cancellation/decline).
// The real implementation will be Stripe (manual capture + Connect payout);
// the mock succeeds unconditionally for local development.

export interface PaymentProvider {
  name: string;
  authorize(input: {
    bookingId: string;
    amount: string;
    currency: string;
  }): Promise<{ providerRef: string }>;
  capture(providerRef: string): Promise<void>;
  release(providerRef: string): Promise<void>;
  refund(providerRef: string): Promise<void>;
}

class MockPaymentProvider implements PaymentProvider {
  name = "mock";

  async authorize({ bookingId }: { bookingId: string }) {
    return { providerRef: `mockpay_${bookingId}` };
  }
  async capture() {}
  async release() {}
  async refund() {}
}

export function getPaymentProvider(): PaymentProvider {
  const configured = process.env.PAYMENT_PROVIDER ?? "mock";
  switch (configured) {
    case "mock":
      return new MockPaymentProvider();
    default:
      throw new Error(`Unknown PAYMENT_PROVIDER: ${configured}`);
  }
}
