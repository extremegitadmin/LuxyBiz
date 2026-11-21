<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends the customer their payment link (Day 29). Sent immediately (not queued) so staff see at once whether it worked.
 * The link is passed in because only its fingerprint is stored.
 *
 * PLACEHOLDER WORDING. Look and text live in resources/views/emails/payment-link.blade.php.
 * When Anakha's final email templates are ready, replace that one view (keep the variables) and nothing else changes.
 */
class PaymentLinkNotification extends Notification
{
    public function __construct(private readonly PaymentRequest $request, private readonly string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->request->booking()->with(['user', 'leadTraveler', 'tour', 'departure'])->first();

        return (new MailMessage)
            ->subject('Your payment link · ' . $booking->reference)
            ->view('emails.payment-link', [
                'name' => $booking->user?->name ?? $booking->leadTraveler?->full_name ?? 'there',
                'bookingReference' => $booking->reference,
                'tour' => $booking->tour?->title,
                'departsOn' => $booking->departure?->departs_on?->format('d M Y'),
                'currency' => $this->request->currency,
                'amount' => (float) $this->request->amount,
                'url' => $this->url,
                'expiresAt' => $this->request->expires_at->format('d M Y, H:i'),
            ]);
    }
}
