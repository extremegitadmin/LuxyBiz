<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentRequestService;
use App\Services\Payments\PaymentSessionService;
use Illuminate\Http\Request;

/**
 * The page a customer reaches from their payment link (Day 29). Public: the secret token in the address is the only key.
 * It shows the least possible (reference, tour, date, amount), never names, emails or payments, and every answer
 * for an unknown or unusable token looks the same, so tokens cannot be guessed or probed.
 */
class PayLinkController extends Controller
{
    public function show(string $token, PaymentRequestService $links)
    {
        $request = $links->findByToken($token);
        if (! $request) {
            return $this->page('unavailable');
        }

        $links->recordOpen($request);
        $state = $links->state($request);
        $booking = $request->booking()->with(['tour', 'departure'])->first();

        return $this->page($state, [
            'token' => $token,
            'reference' => $booking?->reference,
            'tour' => $booking?->tour?->title,
            'departsOn' => $booking?->departure?->departs_on?->format('d M Y'),
            'currency' => $request->currency,
            'amount' => $state === 'open' ? $links->amountDue($request) : null,
        ]);
    }

    public function start(Request $http, string $token, PaymentRequestService $links, PaymentSessionService $sessions)
    {
        $request = $links->findByToken($token);
        if (! $request) {
            return $this->page('unavailable');
        }

        try {
            $url = $links->startPayment($request, $sessions);
        } catch (\DomainException $e) {
            return redirect()->route('pay.show', $token)->with('pay_error', $e->getMessage());
        }

        return redirect()->away($url)->withHeaders($this->headers());
    }

    private function page(string $state, array $data = [])
    {
        return response()->view('payments.pay', ['state' => $state] + $data)->withHeaders($this->headers());
    }

    /** The address holds a secret, so the page must not be stored, indexed, or passed on as a referrer to the payment provider. */
    private function headers(): array
    {
        return ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer'];
    }
}
