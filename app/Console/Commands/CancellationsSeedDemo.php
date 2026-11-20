<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\TourDeparture;
use App\Services\Cancellations\CancellationRequestService;
use App\Services\Cancellations\RefundCalculator;
use Illuminate\Console\Command;

/**
 * Demo data for the cancellations screens (local only). Uses the demo bookings the booking seeder made:
 *  - a booking in "cancellation_requested"  -> a pending request (and a departure 40 days ahead if it has none)
 *  - bookings already "cancelled" + "refunded" -> past requests with status refunded
 * Safe to run twice: it skips a booking that already has a request. Remove again with --remove.
 */
class CancellationsSeedDemo extends Command
{
    protected $signature = 'cancellations:seed-demo {--remove : Delete the demo requests}';

    protected $description = 'Create demo cancellation requests from the demo bookings (local testing only)';

    public const MARK = '[demo] ';

    public function handle(CancellationRequestService $service, RefundCalculator $calc): int
    {
        if (app()->environment('production')) {
            $this->error('Not allowed in production.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $n = CancellationRequest::query()->where('reason', 'like', self::MARK . '%')->delete();
            $this->info("Removed $n demo request(s).");

            return self::SUCCESS;
        }

        $made = 0;

        // Waiting requests
        foreach (Booking::query()->where('status', 'cancellation_requested')->get() as $b) {
            if (CancellationRequest::where('booking_id', $b->id)->exists()) {
                continue;
            }
            if (! $b->tour_departure_id && ! $b->departure) {
                $dep = TourDeparture::create(['tour_id' => $b->tour_id, 'departs_on' => now()->addDays(40)->toDateString(), 'capacity' => 12, 'status' => 'open']);
                $b->update(['tour_departure_id' => $dep->id]);
                $b->unsetRelation('departure');
                $this->line("Booking {$b->reference}: added a demo departure on " . $dep->departs_on->format('d M Y') . '.');
            }
            // The booking is already in "cancellation_requested", so create the row directly (the service would refuse nothing here, but would re-set the status).
            $c = $calc->calculate($b->fresh(), now());
            CancellationRequest::create([
                'reference' => CancellationRequest::generateReference(), 'booking_id' => $b->id, 'source' => 'customer',
                'reason' => self::MARK . 'Change of plans', 'requested_on' => now()->subDays(2)->toDateString(), 'status' => 'pending',
                'currency' => $c->currency, 'calculated_refund' => $c->refund, 'calculation' => $c->toArray(),
            ]);
            $made++;
        }

        // Past requests, finished and refunded
        foreach (Booking::query()->where('status', 'cancelled')->where('payment_status', 'refunded')->get() as $b) {
            if (CancellationRequest::where('booking_id', $b->id)->exists()) {
                continue;
            }
            $refunded = (float) $b->payments()->where('type', 'refund')->where('status', 'succeeded')->sum('amount');
            CancellationRequest::create([
                'reference' => CancellationRequest::generateReference(), 'booking_id' => $b->id, 'source' => 'staff',
                'reason' => self::MARK . 'Customer cancelled', 'requested_on' => now()->subDays(10)->toDateString(), 'status' => 'refunded',
                'currency' => $b->currency, 'calculated_refund' => $refunded, 'final_refund' => $refunded,
                'decided_at' => now()->subDays(9), 'completed_at' => now()->subDays(8), 'decision_note' => self::MARK . 'Approved in full',
            ]);
            $made++;
        }

        $this->info("Created $made demo request(s). Open Cancellations in the sidebar.");

        return self::SUCCESS;
    }
}
