<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the test data made by payments:simulate (Day 13): the SIMULATED payments and refunds, their webhook events,
 * and (only if you ask) the dummy gateway keys. Real and demo-seeder rows are never touched: a row is removed only if
 * it carries a simulation marker (gateway id containing "SIM", event id starting evt_SIM / evt_sim_).
 *
 *   php artisan payments:clean-simulated            shows what would be removed, removes nothing
 *   php artisan payments:clean-simulated --force    removes it
 *   php artisan payments:clean-simulated --force --dummy-keys   also removes the dummy keys (only if they contain DUMMY)
 */
class PaymentsCleanSimulated extends Command
{
    protected $signature = 'payments:clean-simulated {--force : Really delete (without it this is a dry run)} {--dummy-keys : Also remove dummy gateway keys}';

    protected $description = 'Remove payments, refunds and webhook events made by payments:simulate';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Not allowed in production.');

            return self::FAILURE;
        }

        $payments = Payment::query()->where(function ($q) {
            $q->where('gateway_session_id', 'like', '%SIM%')
                ->orWhere('gateway_reference', 'like', 'pi_SIM%')
                ->orWhere('gateway_reference', 'like', 're_SIM%')
                ->orWhere('gateway_reference', 'like', 'rfnd_SIM%')
                ->orWhere('gateway_reference', 'like', 'pay_SIM%');
        });
        $events = PaymentEvent::query()->where(function ($q) {
            $q->where('external_id', 'like', 'evt_SIM%')->orWhere('external_id', 'like', 'evt_sim_%');
        });

        $paymentIds = (clone $payments)->pluck('id');
        $bookingIds = (clone $payments)->pluck('booking_id')->unique()->values();
        $eventCount = (clone $events)->count();
        $dummy = $this->dummyKeyRows();

        $this->table(['What', 'Count'], [
            ['Simulated payments and refunds', $paymentIds->count()],
            ['Simulated webhook events', $eventCount],
            ['Bookings whose payment status will be recalculated', $bookingIds->count()],
            ['Dummy gateway key rows' . ($this->option('dummy-keys') ? '' : ' (kept, use --dummy-keys)'), $dummy->count()],
        ]);

        if (! $this->option('force')) {
            $this->warn('Dry run: nothing was deleted. Add --force to delete.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($events, $payments, $bookingIds, $dummy) {
            $events->delete();
            $payments->delete();
            Booking::query()->whereIn('id', $bookingIds)->get()->each->syncPaymentStatus();
            if ($this->option('dummy-keys')) {
                PaymentSetting::query()->whereIn('id', $dummy->pluck('id'))->delete();
            }
        });

        $this->info('Done. Booking payment statuses were recalculated.');

        return self::SUCCESS;
    }

    /** Gateway key rows, but only for a provider whose saved key is a DUMMY one (never real test keys). */
    private function dummyKeyRows()
    {
        $rows = collect();
        foreach (['razorpay', 'stripe'] as $p) {
            if (str_contains((string) PaymentSetting::field($p, 'public_key'), 'DUMMY')) {
                $rows = $rows->merge(PaymentSetting::query()->where('key', 'like', 'gw.' . $p . '.%')->get(['id']));
            }
        }

        return $rows;
    }
}
