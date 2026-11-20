<?php

namespace App\Services\Cancellations;

use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ends an APPROVED cancellation request (Day 20): either "refund paid" or "travel credit instead".
 *
 * Mark refunded does not call a gateway. It RECORDS a refund that a person made (in the provider's dashboard, by bank
 * transfer, cash...), and cancels the booking. A refund that the gateway webhook already recorded after the approval is
 * counted, so the same money is never recorded twice. Rule failures throw a DomainException safe to show to staff.
 */
class CancellationCompletionService
{
    public const METHODS = [
        'gateway_dashboard' => 'Refunded in the payment provider\'s dashboard',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash or cheque',
        'other' => 'Other',
    ];

    /**
     * What is left to record for this request: the approved amount, minus refunds that the gateway webhook already
     * recorded on the booking since the approval (and that no other request owns).
     *
     * @return array{amount: float, already: float, to_record: float, already_rows: int}
     */
    public function outstanding(CancellationRequest $request): array
    {
        $amount = round($request->refundAmount(), 2);
        $rows = $this->unclaimedRefunds($request)->get();
        $already = round((float) $rows->sum('amount'), 2);

        return ['amount' => $amount, 'already' => min($already, $amount), 'to_record' => max(0, round($amount - $already, 2)), 'already_rows' => $rows->count()];
    }

    public function markRefunded(CancellationRequest $request, User $by, array $in): CancellationRequest
    {
        $method = $in['method'] ?? '';
        if (! array_key_exists($method, self::METHODS)) {
            throw new \DomainException('Choose how the refund was paid.');
        }
        $reference = $this->clean($in['reference'] ?? null, 120);
        if ($method === 'gateway_dashboard' && $reference === null) {
            throw new \DomainException('Enter the refund id from the provider\'s dashboard (for example rfnd_... or re_...). It lets the payment webhook match this refund instead of recording it twice.');
        }

        return DB::transaction(function () use ($request, $by, $method, $reference, $in) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBeApproved($row);
            $booking = $row->booking()->lockForUpdate()->first();

            $refundedOn = ! empty($in['refunded_on']) ? \Carbon\CarbonImmutable::parse($in['refunded_on']) : now();
            $out = $this->outstanding($row);

            if ($out['to_record'] > 0) {
                $paid = max(0, round((float) $booking->paidAmount(), 2));
                if ($out['to_record'] > $paid) {
                    throw new \DomainException('Only ' . $row->currency . ' ' . number_format($paid, 2) . ' is left to refund on this booking, which is less than '
                        . $row->currency . ' ' . number_format($out['to_record'], 2) . '. Check the payments first.');
                }

                $provider = $method === 'gateway_dashboard'
                    ? ($booking->payments()->where('type', 'charge')->where('status', 'succeeded')->whereNotNull('gateway')->latest('id')->value('gateway'))
                    : null;

                Payment::create([
                    'booking_id' => $booking->id,
                    'cancellation_request_id' => $row->id,
                    'type' => 'refund',
                    'channel' => 'offline',
                    'gateway' => $provider ?: 'manual',
                    'gateway_reference' => $reference,
                    'amount' => $out['to_record'],
                    'currency' => $row->currency,
                    'method' => $method,
                    'status' => 'succeeded',
                    'paid_at' => $refundedOn,
                    'note' => 'Refund for cancellation ' . $row->reference,
                    'recorded_by' => $by->id,
                ]);
            }

            // Refunds the gateway had already recorded now belong to this request.
            $this->unclaimedRefunds($row)->update(['cancellation_request_id' => $row->id]);

            $row->update([
                'status' => 'refunded',
                'resolution' => 'refund',
                'completed_at' => now(),
                'completion_note' => $this->clean($in['note'] ?? null, 1000),
            ]);

            $booking->update(['status' => 'cancelled']);
            $booking->syncPaymentStatus();

            SafeAudit::log('cancellation.refunded', 'Cancellation ' . $row->reference . ' marked refunded', [
                'subject_id' => $row->id, 'subject_type' => 'CancellationRequest', 'amount' => $out['amount'], 'recorded_now' => $out['to_record'],
                'already_recorded' => $out['already'], 'method' => $method, 'currency' => $row->currency,
            ]);

            return $row;
        });
    }

    /** Give the customer travel credit instead of money. Returns the credit code. */
    public function issueCredit(CancellationRequest $request, User $by, ?string $note = null): string
    {
        $code = null;
        DB::transaction(function () use ($request, $by, $note, &$code) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBeApproved($row);
            $booking = $row->booking()->lockForUpdate()->first();

            if (! Schema::hasTable('travel_credits')) {
                throw new \DomainException('The travel credit table does not exist yet. Run php artisan migrate.');
            }
            if (! $booking->user_id) {
                throw new \DomainException('This booking has no customer account, and travel credit belongs to an account. Refund the money instead, or link a customer to the booking first.');
            }
            if (DB::table('travel_credits')->where('cancellation_request_id', $row->id)->exists()) {
                throw new \DomainException('Credit was already issued for this request.');
            }
            if (round($row->refundAmount(), 2) <= 0) {
                throw new \DomainException('There is no refund amount, so there is nothing to turn into credit.');
            }
            // Money already paid out for this request cannot also become credit.
            if ($this->outstanding($row)['already'] > 0) {
                throw new \DomainException('A refund was already recorded on this booking after the approval. Mark it refunded instead of issuing credit.');
            }

            $policy = CancellationPolicy::current();
            $amount = round($row->refundAmount() * $policy->creditPercent / 100, 2);
            $code = $this->newCode();

            DB::table('travel_credits')->insert([
                'code' => $code,
                'customer_id' => $booking->user_id,
                'cancellation_request_id' => $row->id,
                'amount' => $amount,
                'balance' => $amount,
                'currency' => strtoupper($row->currency),
                'expires_at' => now()->addMonthsNoOverflow($policy->creditMonths)->toDateString(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row->update([
                'status' => 'credited',
                'resolution' => 'credit',
                'completed_at' => now(),
                'completion_note' => $this->clean($note, 1000),
            ]);

            // The money stays with LuxyBiz as credit: the payments are not touched, the booking is cancelled.
            $booking->update(['status' => 'cancelled']);

            SafeAudit::log('cancellation.credited', 'Cancellation ' . $row->reference . ' ended as travel credit ' . $code, [
                'subject_id' => $row->id, 'subject_type' => 'CancellationRequest', 'credit' => $amount, 'code' => $code, 'currency' => $row->currency,
            ]);
        });

        return $code;
    }

    /** Refunds on the booking that succeeded at or after the approval and belong to no cancellation request yet. */
    private function unclaimedRefunds(CancellationRequest $request)
    {
        return Payment::query()
            ->where('booking_id', $request->booking_id)
            ->where('type', 'refund')
            ->where('status', 'succeeded')
            ->whereNull('cancellation_request_id')
            ->when($request->decided_at, fn ($q, $at) => $q->where('created_at', '>=', $at));
    }

    private function mustBeApproved(CancellationRequest $row): void
    {
        if ($row->status !== 'approved') {
            throw new \DomainException($row->status === 'pending'
                ? 'Approve the request first.'
                : 'This request is already ' . $row->status . '.');
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'TC-' . Str::upper(Str::random(8));
        } while (DB::table('travel_credits')->where('code', $code)->exists());

        return $code;
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
