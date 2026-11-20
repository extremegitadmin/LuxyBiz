<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * Stripe webhooks. Events to tick in the Stripe dashboard (nothing else is needed):
 *   checkout.session.completed, checkout.session.async_payment_succeeded,
 *   checkout.session.async_payment_failed, checkout.session.expired,
 *   refund.created, refund.updated, refund.failed
 */
class StripeWebhookHandler extends WebhookHandler
{
    /** Stripe's own advice: reject signatures older than 5 minutes (stops replays of a stolen request). */
    public const TOLERANCE_SECONDS = 300;

    public const EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'refund.created',
        'refund.updated',
        'refund.failed',
    ];

    public function gateway(): string
    {
        return 'stripe';
    }

    public function events(): array
    {
        return self::EVENTS;
    }

    public function signatureValid(string $rawBody, Request $request, string $secret): bool
    {
        return self::signatureIsValid($rawBody, $request->header('Stripe-Signature'), $secret);
    }

    public function eventName(array $event): string
    {
        return (string) ($event['type'] ?? '');
    }

    public function eventId(array $event, Request $request): ?string
    {
        return isset($event['id']) && is_string($event['id']) ? $event['id'] : null;
    }

    /**
     * Check the Stripe-Signature header against the RAW request body. Header looks like: t=1700000000,v1=abc...,v1=def...
     * The signed text is "{t}.{body}", signed with HMAC-SHA256 using the webhook signing secret.
     */
    public static function signatureIsValid(string $rawBody, ?string $header, string $secret, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    public function summary(array $event): array
    {
        $o = (array) data_get($event, 'data.object', []);
        $keep = ['id', 'object', 'payment_intent', 'payment_status', 'status', 'amount', 'amount_total', 'currency',
            'client_reference_id', 'failure_reason', 'charge', 'metadata'];

        return [
            'id' => $event['id'] ?? null,
            'type' => $event['type'] ?? null,
            'created' => $event['created'] ?? null,
            'livemode' => $event['livemode'] ?? null,
            'data' => ['object' => array_intersect_key($o, array_flip($keep))],
        ];
    }

    protected function dispatch(array $event, string $mode): array
    {
        $type = $this->eventName($event);
        $obj = (array) data_get($event, 'data.object', []);

        if (! in_array($type, self::EVENTS, true)) {
            return ['ignored', 'Event type not used by this system.', null, null];
        }

        // A sandbox event must never touch real money records, and the other way round.
        $live = (bool) ($event['livemode'] ?? false);
        if ($live !== ($mode === 'live')) {
            return ['ignored', $live ? 'Live event received while the system is in sandbox mode.' : 'Sandbox event received while the system is in live mode.', null, null];
        }

        return str_starts_with($type, 'refund.') ? $this->refund($type, $obj) : $this->checkout($type, $obj);
    }

    private function checkout(string $type, array $session): array
    {
        $sessionId = (string) ($session['id'] ?? '');
        $intent = isset($session['payment_intent']) && is_string($session['payment_intent']) ? $session['payment_intent'] : null;
        $reference = $intent ?? $sessionId;

        [$payment, $result] = $this->matchCharge($sessionId, data_get($session, 'metadata.payment_id'), data_get($session, 'metadata.booking_id'), $reference);
        if ($result) {
            return $result;
        }

        return match ($type) {
            'checkout.session.completed' => ($session['payment_status'] ?? '') === 'paid'
                ? $this->succeeded($payment, $session, $reference)
                : ['processed', 'Session finished but the money has not arrived yet (waiting for the bank). Payment stays pending.', $payment, $reference],
            'checkout.session.async_payment_succeeded' => $this->succeeded($payment, $session, $reference),
            'checkout.session.async_payment_failed' => $this->markFailed($payment, 'async_payment_failed', 'The bank payment failed.', $reference),
            'checkout.session.expired' => $this->markFailed($payment, 'session_expired', 'The payment link expired without payment.', $reference),
        };
    }

    private function succeeded($payment, array $session, string $reference): array
    {
        $paid = isset($session['amount_total']) ? (int) $session['amount_total'] : null;

        return $this->markSucceeded($payment, $paid, (string) ($session['currency'] ?? ''), $reference);
    }

    private function refund(string $type, array $refund): array
    {
        $refundId = (string) ($refund['id'] ?? '');
        $intent = isset($refund['payment_intent']) && is_string($refund['payment_intent']) ? $refund['payment_intent'] : null;
        $status = $type === 'refund.failed' ? 'failed' : (string) ($refund['status'] ?? '');

        if ($refundId === '') {
            return ['failed', 'Refund event has no refund id.', null, null];
        }

        $mapped = match ($status) {
            'succeeded' => 'succeeded',
            'pending', 'requires_action' => 'pending',
            'failed', 'canceled' => 'failed',
            default => null,
        };
        if ($mapped === null) {
            return ['ignored', 'Refund status "' . $status . '" is not used by this system.', null, $refundId];
        }

        return $this->applyRefund($refundId, $intent, (int) ($refund['amount'] ?? 0), (string) ($refund['currency'] ?? ''), $mapped, isset($refund['failure_reason']) ? (string) $refund['failure_reason'] : null);
    }
}
