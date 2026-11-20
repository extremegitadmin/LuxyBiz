<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay Payment Links (hosted payment page). The customer pays on Razorpay's own page, so card numbers
 * and OTPs never reach this system. Talks to Razorpay's REST API directly, no extra package.
 *
 * Settings: "public key" = the Key Id (rzp_test_... / rzp_live_...), "secret key" = the Key Secret.
 * Amounts go to Razorpay in the smallest unit (paise for INR, cents for USD).
 * For payments in other currencies than INR, Razorpay support must switch on International Payments for the account.
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    /** Razorpay allows up to 6 months. 48 hours matches our payment-link rule. */
    private const LINK_HOURS = 48;

    /** @param array{mode?: string, public_key?: ?string, secret_key?: ?string, webhook_secret?: ?string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return 'razorpay';
    }

    public function supportsPaymentSessions(): bool
    {
        return true;
    }

    public function linkHours(): int
    {
        return self::LINK_HOURS;
    }

    public function assertReady(): void
    {
        $this->keyId();
        $this->keySecret();
    }

    public function createPaymentSession(PaymentSessionRequest $r): PaymentSession
    {
        $params = [
            'amount' => (int) round($r->amount * 100),
            'currency' => strtoupper($r->currency),
            'accept_partial' => false,
            // Unique per payment (Razorpay refuses a reused reference), max 40 characters.
            'reference_id' => 'pay-' . $r->paymentId,
            'description' => mb_substr($r->description, 0, 2048),
            'expire_by' => now()->addHours(self::LINK_HOURS)->timestamp,
            // We send the link ourselves. Razorpay must not message the customer on its own.
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            'callback_url' => $r->successUrl,
            'callback_method' => 'get',
            'notes' => [
                'booking_id' => (string) $r->bookingId,
                'booking_reference' => $r->bookingReference,
                'payment_id' => (string) $r->paymentId,
            ],
        ];

        $customer = array_filter(['name' => $r->customerName ? mb_substr($r->customerName, 0, 100) : null, 'email' => $r->customerEmail]);
        if ($customer !== []) {
            $params['customer'] = $customer;
        }

        $response = $this->call(fn (PendingRequest $http) => $http->post(self::API . '/payment_links/', $params));

        $id = $response->json('id');
        $url = $response->json('short_url');
        // The link is shown to staff and sent to a customer, so it must be a plain https address.
        if (! is_string($id) || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new GatewayException('The payment provider did not return a payment page. Please try again.');
        }

        return new PaymentSession($id, $url);
    }

    public function checkConnection(): string
    {
        $this->call(fn (PendingRequest $http) => $http->get(self::API . '/payment_links', ['count' => 1]));

        return 'Connected to Razorpay (' . $this->mode() . ' mode). The keys work.';
    }

    // ------------------------------------------------------------------ internals

    private function mode(): string
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    /** Sends one request. Turns every failure into a GatewayException whose message is safe to show. */
    private function call(callable $send): Response
    {
        $id = $this->keyId();
        $secret = $this->keySecret();

        try {
            $response = $send(Http::withBasicAuth($id, $secret)->asJson()->acceptJson()->timeout(15));
        } catch (ConnectionException) {
            throw new GatewayException('Could not reach Razorpay. Check the internet connection and try again.');
        }

        if ($response->successful()) {
            return $response;
        }

        $code = (string) $response->json('error.code', 'error');
        $field = (string) $response->json('error.field', '');
        // Log what happened, never the keys or the request body.
        Log::warning('Razorpay request failed', ['status' => $response->status(), 'code' => $code, 'field' => $field]);

        if ($response->status() === 401) {
            throw new GatewayException('Razorpay rejected the keys. Check that the Key Id and Key Secret were copied correctly and match the ' . $this->mode() . ' mode.');
        }

        $detail = (string) $response->json('error.description', '');
        $detail = str_replace([$secret, $id], '[key]', $detail);

        throw new GatewayException('Razorpay could not complete the request (' . $code . ').' . ($detail !== '' ? ' ' . mb_substr($detail, 0, 200) : ''));
    }

    /** The Key Id must exist and belong to the saved mode, so a live key is never used by accident in sandbox. */
    private function keyId(): string
    {
        $id = (string) ($this->config['public_key'] ?? '');
        if ($id === '') {
            throw new GatewayException('Razorpay is switched on but no Key Id is saved. Add it under Settings, Payment gateways.');
        }
        if (! preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/', $id, $m)) {
            throw new GatewayException('The saved Key Id does not look like a Razorpay key (it should start with rzp_test_ or rzp_live_).');
        }
        if (($m[1] === 'live') !== ($this->mode() === 'live')) {
            throw new GatewayException('The saved Key Id is a ' . $m[1] . ' key but the mode is ' . $this->mode() . '. Save the matching key.');
        }

        return $id;
    }

    private function keySecret(): string
    {
        $secret = (string) ($this->config['secret_key'] ?? '');
        if ($secret === '') {
            throw new GatewayException('Razorpay is switched on but no Key Secret is saved. Add it under Settings, Payment gateways.');
        }

        return $secret;
    }
}
