<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Confirm the callback genuinely came from the gateway.
     *
     * Implementations MUST fail closed: if a secret or credential is missing,
     * return false. Never fall back to a default secret and never treat an
     * unverifiable payload as valid.
     */
    public function verify(Request $request): bool;

    /**
     * The gateway's own reference for this payment. Used with the gateway name
     * as the idempotency key.
     */
    public function transactionId(Request $request): ?string;

    /**
     * Our booking UUID, as echoed back by the gateway.
     */
    public function bookingReference(Request $request): ?string;

    public function amount(Request $request): ?string;

    /**
     * Whether the gateway is reporting a successful, completed payment.
     */
    public function isSuccessful(Request $request): bool;
}
