<?php

namespace App\Http\Controllers;

/**
 * Where the customer lands after the hosted payment page. These pages are public, show no booking data
 * and change nothing: a payment is confirmed only by the provider's webhook, never by someone opening a URL.
 */
class PaymentReturnController extends Controller
{
    public function success()
    {
        return response()->view('payments.return', [
            'title' => 'Thank you',
            'message' => 'Your payment is being confirmed. You will receive a confirmation shortly. You can close this page.',
        ])->header('Cache-Control', 'no-store');
    }

    public function cancelled()
    {
        return response()->view('payments.return', [
            'title' => 'Payment not completed',
            'message' => 'No payment was taken. If you still want to pay, please use the payment link again or contact us.',
        ])->header('Cache-Control', 'no-store');
    }
}
