<?php

namespace App\Services\Cancellations;

use App\Models\CancellationRequest;
use App\Models\User;
use App\Notifications\CancellationDecisionNotification;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Approve or reject a cancellation request (Day 19), and tell the customer.
 * Approving does NOT move money: it locks the amount. Paying it out (or issuing credit) is Day 20.
 * Rule failures throw a DomainException whose message is safe to show to staff.
 */
class CancellationDecisionService
{
    public function approve(CancellationRequest $request, User $by, ?string $note = null, bool $acknowledged = false): CancellationRequest
    {
        $row = DB::transaction(function () use ($request, $by, $note, $acknowledged) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBePending($row);

            $warnings = (array) data_get($row->calculation, 'warnings', []);
            if ($warnings !== [] && ! $acknowledged) {
                throw new \DomainException('This request has warnings. Read them, then tick "I have checked the warnings" to approve.');
            }

            $amount = round($row->refundAmount(), 2);
            $paid = max(0, round((float) $row->booking->paidAmount(), 2));
            if ($amount > $paid) {
                throw new \DomainException('The customer has paid ' . $row->currency . ' ' . number_format($paid, 2) . ', which is less than the refund of '
                    . $row->currency . ' ' . number_format($amount, 2) . '. The payments changed after the request was opened: check them, or change the refund amount.');
            }

            $row->update([
                'status' => 'approved',
                'final_refund' => $amount,                       // the amount is now fixed
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $this->clean($note),
            ]);

            SafeAudit::log('cancellation.approved', 'Cancellation ' . $row->reference . ' approved', [
                ...$row->auditContext(),
                'refund' => $amount, 'overridden' => $row->isOverridden(), 'warnings_acknowledged' => $warnings !== [] && $acknowledged,
                'note' => $row->decision_note,
            ]);

            return $row;
        });

        $this->notifyAndRecord($row, 'approved');

        return $row->fresh();
    }

    public function reject(CancellationRequest $request, User $by, string $reason): CancellationRequest
    {
        $reason = $this->clean($reason);
        if ($reason === null || mb_strlen($reason) < 10) {
            throw new \DomainException('Write the reason for rejecting (at least 10 characters). The customer will see it.');
        }

        $row = DB::transaction(function () use ($request, $by, $reason) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBePending($row);

            $row->update([
                'status' => 'rejected',
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $reason,
            ]);

            // The booking goes back to where it was: still on, and unpaid bookings go back to "pending".
            $booking = $row->booking;
            if ($booking->status === 'cancellation_requested') {
                $booking->update(['status' => $booking->payment_status === 'unpaid' ? 'pending' : 'confirmed']);
            }

            SafeAudit::log('cancellation.rejected', 'Cancellation ' . $row->reference . ' rejected', [
                ...$row->auditContext(),
                'reason' => $reason,
            ]);

            return $row;
        });

        $this->notifyAndRecord($row, 'rejected');

        return $row->fresh();
    }

    /** First email after a decision. A failure is written to the audit log, because nobody is watching when it happens. */
    private function notifyAndRecord(CancellationRequest $row, string $decision): void
    {
        if (! $this->notify($row)) {
            SafeAudit::log('cancellation.email_failed', 'Decision email of ' . $row->reference . ' was not sent', [
                ...$row->auditContext(),
                'decision' => $decision, 'error' => $row->fresh()->notification_error,
            ]);
        }
    }

    /**
     * Email the customer about the decision. Never throws: a mail problem must not undo a decision.
     * The result is saved on the request so staff can see it and send again.
     */
    public function notify(CancellationRequest $request): bool
    {
        $booking = $request->booking()->with(['user', 'leadTraveler'])->first();
        $name = $booking?->user?->name ?? $booking?->leadTraveler?->full_name;
        $email = collect([$booking?->user?->email, $booking?->leadTraveler?->email])
            ->first(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL));

        if (! $email) {
            $request->update(['customer_notified_at' => null, 'notification_error' => 'The booking has no valid email address, so the customer was not told.']);

            return false;
        }

        try {
            Notification::route('mail', [$email => $name ?? $email])->notifyNow(new CancellationDecisionNotification($request->fresh()));
        } catch (\Throwable $e) {
            report($e);
            $request->update(['customer_notified_at' => null, 'notification_error' => 'The email could not be sent. Check the mail settings, then send it again.']);

            return false;
        }

        $request->update(['customer_notified_at' => now(), 'notification_error' => null]);

        return true;
    }

    /** Send the decision email again (decided requests only). */
    public function resend(CancellationRequest $request, User $by): bool
    {
        if ($request->status === 'pending') {
            throw new \DomainException('There is no decision to send yet.');
        }
        $ok = $this->notify($request);
        SafeAudit::log('cancellation.email_resent', 'Decision email of ' . $request->reference . ' sent again', [
            ...$request->auditContext(), 'ok' => $ok, 'resent_by' => $by->id,
        ]);

        return $ok;
    }

    private function mustBePending(CancellationRequest $row): void
    {
        if ($row->status !== 'pending') {
            throw new \DomainException('This request is already ' . $row->status . ' and cannot be decided again.');
        }
    }

    private function clean(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return $text === '' ? null : mb_substr($text, 0, 1000);
    }
}
