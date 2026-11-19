<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentSetting;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Day 13, part A. Plays the provider: sends a correctly SIGNED webhook message to our own public webhook address,
 * through the real route, middleware and handler, then prints what happened. No keys, no internet, no money.
 *
 *   php artisan payments:simulate paid
 *   php artisan payments:simulate expired --booking=5
 *   php artisan payments:simulate refund --provider=stripe
 *
 * It creates a pending charge marked "SIMULATED" first (a real payment link needs real keys). Local testing only.
 */
class PaymentsSimulate extends Command
{
    public const SCENARIOS = ['paid', 'expired', 'cancelled', 'wrong-amount', 'duplicate', 'bad-signature', 'refund', 'refund-failed'];

    protected $signature = 'payments:simulate
        {scenario : paid | expired | cancelled | wrong-amount | duplicate | bad-signature | refund | refund-failed}
        {--booking= : Booking id (default: the newest booking)}
        {--provider= : razorpay or stripe (default: the one chosen for the booking currency)}
        {--amount= : Amount to charge (default: the booking total)}';

    protected $description = 'Send a signed fake provider webhook to this app (local testing only)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Not allowed in production.');

            return self::FAILURE;
        }
        $scenario = (string) $this->argument('scenario');
        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->error('Unknown scenario. Use one of: ' . implode(', ', self::SCENARIOS));

            return self::FAILURE;
        }

        $booking = $this->option('booking') ? Booking::find($this->option('booking')) : Booking::latest('id')->first();
        if (! $booking) {
            $this->error('No booking found. Create one in the admin panel first, or pass --booking=ID.');

            return self::FAILURE;
        }

        $provider = (string) ($this->option('provider') ?: PaymentSetting::routedProvider($booking->currency));
        if (! in_array($provider, ['razorpay', 'stripe'], true)) {
            $this->error('Provider must be razorpay or stripe.');

            return self::FAILURE;
        }

        $config = PaymentSetting::config($provider);
        if (! $config['enabled'] || blank($config['webhook_secret'])) {
            $this->error("$provider is not set up. Run: php artisan payments:sandbox-setup");

            return self::FAILURE;
        }
        if ($config['mode'] === 'live') {
            $this->error("$provider is in LIVE mode. Simulation is refused: it must never touch real records.");

            return self::FAILURE;
        }

        $amount = (float) ($this->option('amount') ?: $booking->total_amount);
        if ($amount <= 0) {
            $this->error('Amount must be more than 0.');

            return self::FAILURE;
        }

        $this->line("Booking #{$booking->id}  {$booking->currency}  provider: $provider  scenario: $scenario");

        $charge = $this->newCharge($booking, $provider, $amount);
        $this->line("Created pending test payment #{$charge->id} ({$charge->gateway_session_id}), amount " . number_format($amount, 2) . " {$charge->currency}");

        if ($scenario === 'bad-signature') {
            $this->send($provider, $this->payload($provider, 'paid', $charge, $amount), $config['webhook_secret'] . 'x');
        } elseif ($scenario === 'wrong-amount') {
            $this->send($provider, $this->payload($provider, 'paid', $charge, $amount - 1), $config['webhook_secret']);
        } elseif ($scenario === 'duplicate') {
            $p = $this->payload($provider, 'paid', $charge, $amount);
            $this->send($provider, $p, $config['webhook_secret']);
            $this->send($provider, $p, $config['webhook_secret'], 'again, same event id');
        } elseif (in_array($scenario, ['refund', 'refund-failed'], true)) {
            $this->send($provider, $this->payload($provider, 'paid', $charge, $amount), $config['webhook_secret'], 'payment');
            $charge->refresh();
            $kind = $scenario === 'refund' ? 'refund' : 'refund-failed';
            $this->send($provider, $this->payload($provider, $kind, $charge, round($amount / 2, 2)), $config['webhook_secret'], 'refund of half');
        } else {
            $this->send($provider, $this->payload($provider, $scenario, $charge, $amount), $config['webhook_secret']);
        }

        $this->report($booking, $charge);

        return self::SUCCESS;
    }

    private function newCharge(Booking $booking, string $provider, float $amount): Payment
    {
        $session = $provider === 'razorpay' ? 'plink_SIM' . Str::upper(Str::random(10)) : 'cs_test_SIM' . Str::random(16);

        return Payment::create([
            'booking_id' => $booking->id,
            'type' => 'charge',
            'channel' => 'csr_link',
            'gateway' => $provider,
            'gateway_session_id' => $session,
            'amount' => $amount,
            'currency' => strtoupper($booking->currency),
            'method' => 'card',
            'status' => 'pending',
            'note' => 'SIMULATED (Day 13 test). Not real money.',
        ]);
    }

    /** The message the provider would send, with only the fields our handler reads. */
    private function payload(string $provider, string $kind, Payment $p, float $amount): array
    {
        $minor = (int) round($amount * 100);
        $cur = $provider === 'razorpay' ? strtoupper($p->currency) : strtolower($p->currency);
        $meta = ['payment_id' => (string) $p->id, 'booking_id' => (string) $p->booking_id];

        if ($provider === 'razorpay') {
            $payId = 'pay_SIM' . substr(md5((string) $p->id), 0, 10);
            $link = ['id' => $p->gateway_session_id, 'entity' => 'payment_link', 'status' => 'paid', 'amount' => (int) round($p->amount * 100), 'amount_paid' => $minor, 'currency' => $cur, 'notes' => $meta];

            return match ($kind) {
                'paid' => ['event' => 'payment_link.paid', 'created_at' => time(), 'payload' => ['payment_link' => ['entity' => $link], 'payment' => ['entity' => ['id' => $payId, 'entity' => 'payment', 'amount' => $minor, 'currency' => $cur, 'status' => 'captured', 'method' => 'card']]]],
                'expired' => ['event' => 'payment_link.expired', 'created_at' => time(), 'payload' => ['payment_link' => ['entity' => ['status' => 'expired'] + $link]]],
                'cancelled' => ['event' => 'payment_link.cancelled', 'created_at' => time(), 'payload' => ['payment_link' => ['entity' => ['status' => 'cancelled'] + $link]]],
                'refund' => ['event' => 'refund.processed', 'created_at' => time(), 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_SIM' . $p->id, 'entity' => 'refund', 'payment_id' => $p->gateway_reference, 'amount' => $minor, 'currency' => $cur, 'status' => 'processed']]]],
                'refund-failed' => ['event' => 'refund.failed', 'created_at' => time(), 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_SIM' . $p->id, 'entity' => 'refund', 'payment_id' => $p->gateway_reference, 'amount' => $minor, 'currency' => $cur, 'status' => 'failed']]]],
            };
        }

        $intent = 'pi_SIM' . substr(md5((string) $p->id), 0, 12);
        $session = ['id' => $p->gateway_session_id, 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => $minor, 'currency' => $cur, 'payment_intent' => $intent, 'metadata' => $meta];
        $wrap = fn (string $type, array $obj) => ['id' => 'evt_SIM' . Str::random(14), 'object' => 'event', 'type' => $type, 'livemode' => false, 'created' => time(), 'data' => ['object' => $obj]];

        return match ($kind) {
            'paid' => $wrap('checkout.session.completed', $session),
            'expired' => $wrap('checkout.session.expired', ['payment_status' => 'unpaid'] + $session),
            'cancelled' => $wrap('checkout.session.expired', ['payment_status' => 'unpaid'] + $session),
            'refund' => $wrap('refund.updated', ['id' => 're_SIM' . $p->id, 'object' => 'refund', 'payment_intent' => $p->gateway_reference, 'amount' => $minor, 'currency' => $cur, 'status' => 'succeeded']),
            'refund-failed' => $wrap('refund.failed', ['id' => 're_SIM' . $p->id, 'object' => 'refund', 'payment_intent' => $p->gateway_reference, 'amount' => $minor, 'currency' => $cur, 'status' => 'failed', 'failure_reason' => 'expired_or_canceled_card']),
        };
    }

    /** Sign the exact body, post it to our own webhook route and print the answer. */
    private function send(string $provider, array $event, string $secret, string $label = ''): void
    {
        $body = json_encode($event, JSON_UNESCAPED_SLASHES);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($provider === 'razorpay') {
            $headers['HTTP_X_RAZORPAY_SIGNATURE'] = hash_hmac('sha256', $body, $secret);
            $headers['HTTP_X_RAZORPAY_EVENT_ID'] = 'evt_sim_' . substr(hash('sha256', $body), 0, 16);
            $name = $event['event'];
        } else {
            $t = time();
            $headers['HTTP_STRIPE_SIGNATURE'] = 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
            $name = $event['type'];
        }

        $request = Request::create('/webhooks/payments/' . $provider, 'POST', [], [], [], $headers, $body);
        $response = app(Kernel::class)->handle($request);

        $this->line(sprintf('  -> sent %-28s %s  HTTP %d %s', $name, $label, $response->getStatusCode(), $response->getContent()));
    }

    private function report(Booking $booking, Payment $charge): void
    {
        $booking->refresh();
        $charge->refresh();
        $this->newLine();
        $this->info('Result');
        $this->table(['What', 'Value'], [
            ['Payment status', $charge->status . ($charge->failure_code ? " ({$charge->failure_code})" : '')],
            ['Booking payment status', (string) $booking->payment_status],
            ['Refund rows on booking', (string) Payment::where('booking_id', $booking->id)->where('type', 'refund')->count()],
        ]);

        $events = PaymentEvent::where('gateway', $charge->gateway)->latest('id')->take(3)->get(['event', 'outcome', 'note']);
        $this->table(['Webhook event log', 'Outcome', 'Note'], $events->map(fn ($e) => [$e->event, $e->outcome, $e->note])->all());
        $this->line('See it in the admin panel: Payments > open payment #' . $charge->id . ' and the Webhook events page.');
    }
}
