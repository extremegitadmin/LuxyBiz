<?php

namespace App\Services\Cancellations;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\User;
use App\Support\SafeAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Opens a cancellation request: saves the refund calculation as a snapshot and puts the booking into
 * "cancellation_requested". The customer portal and the staff "request cancellation" action will both call this.
 */
class CancellationRequestService
{
    public function __construct(private readonly RefundCalculator $calculator)
    {
    }

    /** @throws \DomainException  with a message that is safe to show to staff */
    public function open(Booking $booking, ?User $by = null, ?string $reason = null, string $source = 'staff', ?CarbonInterface $on = null): CancellationRequest
    {
        return DB::transaction(function () use ($booking, $by, $reason, $source, $on) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (in_array($booking->status, ['cancelled', 'completed'], true)) {
                throw new \DomainException('A ' . $booking->status . ' booking cannot be cancelled.');
            }
            if (CancellationRequest::query()->where('booking_id', $booking->id)->open()->exists()) {
                throw new \DomainException('This booking already has an open cancellation request.');
            }

            $on = $on ?? now();
            $calc = $this->calculator->calculate($booking, $on);

            $request = CancellationRequest::create([
                'reference' => CancellationRequest::generateReference(),
                'booking_id' => $booking->id,
                'requested_by' => $by?->id,
                'source' => $source,
                'reason' => $reason !== null ? mb_substr(trim($reason), 0, 2000) : null,
                'requested_on' => $on->toDateString(),
                'status' => 'pending',
                'currency' => $calc->currency,
                'calculated_refund' => $calc->refund,
                'calculation' => $calc->toArray(),
            ]);

            $booking->update(['status' => 'cancellation_requested']);

            SafeAudit::log('cancellation.requested', 'Cancellation request ' . $request->reference . ' for booking ' . $booking->reference, [
                ...$request->setRelation('booking', $booking)->auditContext(),
                'calculated_refund' => $calc->refund, 'source' => $source, 'requested_by' => $by?->id,
            ]);

            return $request;
        });
    }
}
