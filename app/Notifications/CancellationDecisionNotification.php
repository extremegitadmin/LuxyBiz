<?php

namespace App\Notifications;

use App\Models\CancellationRequest;
use App\Services\Cancellations\CancellationPolicy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer that a cancellation request was approved or rejected.
 * Sent immediately (not queued) so a staff member sees at once whether it worked.
 *
 * PLACEHOLDER WORDING. The look and text live in resources/views/emails/cancellation-decision.blade.php.
 * When Anakha's final email templates are ready, replace that one view (keep the variables) and nothing else changes.
 */
class CancellationDecisionNotification extends Notification
{
    public function __construct(private readonly CancellationRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request->loadMissing(['booking.user', 'booking.leadTraveler', 'booking.tour']);
        $approved = in_array($r->status, ['approved', 'refunded', 'credited'], true);
        $policy = CancellationPolicy::current();
        $booking = $r->booking;

        $subject = ($approved ? 'Your cancellation request is approved' : 'Your cancellation request was not approved')
            . ' · ' . $booking->reference;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.cancellation-decision', [
                'name' => $booking->user?->name ?? $booking->leadTraveler?->full_name ?? 'there',
                'approved' => $approved,
                'bookingReference' => $booking->reference,
                'requestReference' => $r->reference,
                'tour' => $booking->tour?->title,
                'currency' => $r->currency,
                'refund' => $approved ? $r->refundAmount() : 0.0,
                'creditPercent' => $policy->creditPercent,
                'creditMonths' => $policy->creditMonths,
                'daysMin' => $policy->refundDaysMin,
                'daysMax' => $policy->refundDaysMax,
                'note' => $r->decision_note,
            ]);
    }
}
