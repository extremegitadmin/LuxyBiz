<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\GatewayException;
use App\Services\Payments\PaymentSessionService;
use Illuminate\Http\Request;

/** "Request online payment" on the booking page: makes a hosted payment link staff can open or send. */
class BookingPaymentLinkController extends Controller
{
    public function store(Request $request, Booking $booking, PaymentSessionService $sessions)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.5', 'max:1000000'],
        ]);

        try {
            $result = $sessions->start($booking, (float) $data['amount'], $request->user());
        } catch (GatewayException $e) {
            return redirect()->route('admin.bookings.show', $booking)->with('error', $e->getMessage())->withInput();
        }

        // The audit entry never contains the link itself.
        \App\Support\SafeAudit::log('payment.session_created', 'Payment link for booking ' . $booking->reference, [
            'booking_id' => $booking->id,
            'payment_id' => $result['payment']->id,
            'amount' => (float) $result['payment']->amount,
            'currency' => $booking->currency,
            'gateway' => $result['payment']->gateway,
        ]);

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Payment link created. The payment stays pending until the provider confirms it.')
            ->with('payment_link', $result['url'])
            ->with('payment_link_hours', \App\Services\Payments\PaymentSessionService::linkHours((string) $result['payment']->gateway));
    }
}
