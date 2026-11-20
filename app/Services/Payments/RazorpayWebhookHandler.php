<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * Razorpay webhooks. Events to tick in the Razorpay dashboard (Settings, Webhooks), nothing else is needed:
 *   payment_link.paid, payment_link.expired, payment_link.cancelled,
 *   refund.created, refund.processed, refund.failed
 *
 * Signature: header X-Razorpay-Signature = HMAC-SHA256 (hex) of the RAW body with the webhook secret you typed
 * in the Razorpay dashboard (NOT the Key Secret). Event id: header X-Razorpay-Event-Id.
 * Razorpay gives up waiting after 5 seconds, so this handler does only quick database work.
 *
 * "payment.failed" is deliberately not used: a customer whose card or OTP fails can try again on the same link,
 * so one failed attempt does not mean the payment failed. The link ends as paid, expired or cancelled.
 */
class RazorpayWebhookHandler extends WebhookHandler
{
    public const EVENTS = [
        'payment_link.paid',
        'payment_link.expired',
        'payment_link.cancelled',
        'refund.created',
        'refund.processed',
        'refund.failed',
    ];

    public function gateway(): string
    {
        return 'razorpay';
    }

    public function events(): array
    {
        return self::EVENTS;
    }

    public function signatureValid(string $rawBody, Request $request, string $secret): bool
    {
        $header = (string) $request->header('X-Razorpay-Signature', '');
        if ($header === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $header);
    }

    public function eventName(array $event): string
    {
        return (string) ($event['event'] ?? '');
    }

    public function eventId(array $event, Request $request): ?string
    {
        $id = trim((string) $request->header('X-Razorpay-Event-Id', ''));
        if ($id !== '') {
            return mb_substr($id, 0, 120);
        }

        // Razorpay always sends the header. If it is missing, the same body still gets the same id, so retries stay harmless.
        return 'rzp-' . substr(hash('sha256', $request->getContent()), 0, 40);
    }

    public function summary(array $event): array
    {
        $keep = ['id', 'entity', 'status', 'amount', 'amount_paid', 'currency', 'reference_id', 'payment_id', 'method', 'notes', 'error_code'];
        $pick = fn ($name) => array_intersect_key((array) data_get($event, "payload.$name.entity", []), array_flip($keep));

        return [
            'event' => $event['event'] ?? null,
            'created_at' => $event['created_at'] ?? null,
            'payload' => array_filter([
                'payment_link' => $pick('payment_link'),
                'payment' => $pick('payment'),
                'refund' => $pick('refund'),
            ]),
        ];
    }

    protected function dispatch(array $event, string $mode): array
    {
        // Razorpay messages carry no live/sandbox flag. The webhook secret already separates them:
        // a sandbox account signs with its own secret, so a message can only pass if it came from the mode we saved.
        $name = $this->eventName($event);

        return match ($name) {
            'payment_link.paid', 'payment_link.expired', 'payment_link.cancelled' => $this->link($name, $event),
            'refund.created', 'refund.processed', 'refund.failed' => $this->refund($name, $event),
            default => ['ignored', 'Event type not used by this system.', null, null],
        };
    }

    private function link(string $name, array $event): array
    {
        $link = (array) data_get($event, 'payload.payment_link.entity', []);
        $pay = (array) data_get($event, 'payload.payment.entity', []);
        $linkId = (string) ($link['id'] ?? '');
        $reference = (string) ($pay['id'] ?? $linkId);

        [$payment, $result] = $this->matchCharge($linkId, data_get($link, 'notes.payment_id'), data_get($link, 'notes.booking_id'), $reference);
        if ($result) {
            return $result;
        }

        return match ($name) {
            'payment_link.paid' => $this->paid($payment, $link, $pay, $reference),
            'payment_link.expired' => $this->markFailed($payment, 'session_expired', 'The payment link expired without payment.', $reference),
            'payment_link.cancelled' => $this->markFailed($payment, 'link_cancelled', 'The payment link was cancelled.', $reference),
        };
    }

    private function paid($payment, array $link, array $pay, string $reference): array
    {
        if (isset($link['status']) && $link['status'] !== 'paid') {
            return ['ignored', 'Link status is "' . $link['status'] . '", not paid. Payment stays as it is.', $payment, $reference];
        }

        $cents = isset($pay['amount']) ? (int) $pay['amount'] : (isset($link['amount_paid']) ? (int) $link['amount_paid'] : null);
        $currency = (string) ($pay['currency'] ?? $link['currency'] ?? '');

        return $this->markSucceeded($payment, $cents, $currency, $reference);
    }

    private function refund(string $name, array $event): array
    {
        $refund = (array) data_get($event, 'payload.refund.entity', []);
        $refundId = (string) ($refund['id'] ?? '');
        if ($refundId === '') {
            return ['failed', 'Refund event has no refund id.', null, null];
        }

        $mapped = $name === 'refund.failed' ? 'failed' : match ((string) ($refund['status'] ?? '')) {
            'processed' => 'succeeded',
            'created', 'pending' => 'pending',
            'failed' => 'failed',
            default => null,
        };
        if ($mapped === null) {
            return ['ignored', 'Refund status "' . ($refund['status'] ?? '') . '" is not used by this system.', null, $refundId];
        }

        $paymentId = isset($refund['payment_id']) && is_string($refund['payment_id']) ? $refund['payment_id'] : null;

        return $this->applyRefund($refundId, $paymentId, (int) ($refund['amount'] ?? 0), (string) ($refund['currency'] ?? ''), $mapped, isset($refund['error_code']) ? (string) $refund['error_code'] : null);
    }
}
