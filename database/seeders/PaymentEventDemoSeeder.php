<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\Seeder;

/**
 * Asha - demo webhook events so the log has something to show before a real gateway is connected.
 * Run:  php artisan db:seed --class=PaymentEventDemoSeeder   (safe to run twice)
 */
class PaymentEventDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (PaymentEvent::where('gateway', 'demo')->exists()) {
            $this->command?->line('Demo events already exist. Nothing changed.');

            return;
        }

        foreach (Payment::charges()->with('booking')->latest()->take(8)->get() as $i => $payment) {
            $ref = $payment->gateway_reference ?: 'REF-DEMO-' . $payment->id;
            $failed = $payment->status === 'failed';

            PaymentEvent::record([
                'payment_id' => $payment->id, 'gateway' => 'demo', 'external_id' => "evt_demo_{$payment->id}_1",
                'gateway_reference' => $ref, 'event' => $failed ? 'payment.failed' : 'payment.succeeded',
                'outcome' => 'processed', 'created_at' => now()->subHours($i * 5 + 1),
            ], [
                'id' => "evt_demo_{$payment->id}_1", 'type' => $failed ? 'payment.failed' : 'payment.succeeded',
                'data' => ['reference' => $ref, 'amount' => (float) $payment->amount, 'currency' => $payment->currency, 'card' => ['brand' => 'visa', 'last4' => '4242', 'number' => '4242424242424242']],
                'signature' => 'should-never-be-stored',
            ]);
        }

        PaymentEvent::record([
            'gateway' => 'demo', 'external_id' => 'evt_demo_orphan', 'gateway_reference' => 'REF-UNKNOWN-1',
            'event' => 'payment.succeeded', 'outcome' => 'ignored', 'note' => 'No payment found for this reference.',
        ], ['id' => 'evt_demo_orphan', 'data' => ['reference' => 'REF-UNKNOWN-1']]);

        PaymentEvent::record([
            'gateway' => 'demo', 'external_id' => 'evt_demo_bad', 'event' => 'charge.refund.updated',
            'outcome' => 'failed', 'note' => 'Could not process: amount missing.',
        ], ['id' => 'evt_demo_bad', 'data' => []]);

        $this->command?->info('Demo webhook events created: ' . PaymentEvent::count());
    }
}
