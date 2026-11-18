<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Checkout (hosted payment page). The customer enters the card on Stripe's own page,
 * so card numbers never reach this system. Talks to Stripe's REST API directly, no extra package.
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    /** @param array{provider?: string, mode?: string, public_key?: ?string, secret_key?: ?string, webhook_secret?: ?string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function supportsPaymentSessions(): bool
    {
        return true;
    }

    /** Stripe Checkout pages last at most 24 hours; we use 23. */
    public function linkHours(): int
    {
        return 23;
    }

    public function assertReady(): void
    {
        $this->secretKey();
    }

    public function createPaymentSession(PaymentSessionRequest $r): PaymentSession
    {
        $params = [
            'mode' => 'payment',
            'client_reference_id' => $r->bookingReference,
            'success_url' => $r->successUrl,
            'cancel_url' => $r->cancelUrl,
            'billing_address_collection' => 'required',   // customer name and address are needed for export payments
            'expires_at' => now()->addHours($this->linkHours())->timestamp,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($r->currency),
                    'unit_amount' => (int) round($r->amount * 100),
                    'product_data' => ['name' => $r->description],
                ],
            ]],
            'payment_intent_data' => [
                'description' => $r->description,
                'metadata' => ['booking_id' => $r->bookingId, 'booking_reference' => $r->bookingReference, 'payment_id' => $r->paymentId],
            ],
            'metadata' => ['booking_id' => $r->bookingId, 'booking_reference' => $r->bookingReference, 'payment_id' => $r->paymentId],
        ];
        if ($r->customerEmail) {
            $params['customer_email'] = $r->customerEmail;
        }

        $response = $this->call(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $r->idempotencyKey])
            ->post(self::API . '/checkout/sessions', $params));

        $id = $response->json('id');
        $url = $response->json('url');
        // The link is shown to staff and sent to a customer, so it must be a plain https address.
        if (! is_string($id) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new GatewayException('The payment provider did not return a payment page. Please try again.');
        }

        return new PaymentSession($id, $url);
    }

    public function checkConnection(): string
    {
        $this->call(fn (PendingRequest $http) => $http->get(self::API . '/balance'));

        return 'Connected to Stripe (' . $this->mode() . ' mode). The secret key works.';
    }

    // ------------------------------------------------------------------ internals

    private function mode(): string
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    /** Sends one request. Turns every failure into a GatewayException whose message is safe to show. */
    private function call(callable $send): Response
    {
        $secret = $this->secretKey();

        try {
            $response = $send(Http::withBasicAuth($secret, '')->asForm()->acceptJson()->timeout(15));
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Stripe. Check the internet connection and try again.');
        }

        if ($response->successful()) {
            return $response;
        }

        $type = (string) $response->json('error.type', 'error');
        $code = (string) $response->json('error.code', '');
        // Log what happened, never the key or the request body.
        Log::warning('Stripe request failed', ['status' => $response->status(), 'type' => $type, 'code' => $code]);

        if ($response->status() === 401) {
            throw new GatewayException('Stripe rejected the secret key. Check that it was copied correctly and matches the ' . $this->mode() . ' mode.');
        }

        $detail = (string) $response->json('error.message', '');
        $detail = str_replace($secret, '[key]', $detail);

        throw new GatewayException('Stripe could not complete the request (' . ($code ?: $type) . ').' . ($detail !== '' ? ' ' . mb_substr($detail, 0, 200) : ''));
    }

    /** The saved key must exist and must belong to the saved mode, so a live key is never used by accident in sandbox. */
    private function secretKey(): string
    {
        $secret = (string) ($this->config['secret_key'] ?? '');
        if ($secret === '') {
            throw new GatewayException('Stripe is selected but no secret key is saved. Add it under Settings, Payment gateways.');
        }
        if (! preg_match('/^(sk|rk)_(test|live)_/', $secret, $m)) {
            throw new GatewayException('The saved secret key does not look like a Stripe secret key (it should start with sk_test_ or sk_live_).');
        }
        $isLiveKey = $m[2] === 'live';
        if ($isLiveKey !== ($this->mode() === 'live')) {
            throw new GatewayException('The saved secret key is a ' . ($isLiveKey ? 'live' : 'test') . ' key but the mode is ' . $this->mode() . '. Save the matching key.');
        }

        return $secret;
    }
}
