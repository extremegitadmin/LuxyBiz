<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Bookings\BookingEngine;
use App\Services\Bookings\CustomerDirectory;
use App\Services\Payments\GatewayException;
use App\Services\Payments\PaymentRequestService;
use App\Services\Payments\PaymentSessionService;
use App\Support\SafeAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Assisted (staff-made) bookings. Day 26: form and customer search. Day 27: uses the shared booking engine.
 * Day 28: take payment on the hosted page. Day 29: send the customer a payment link. Day 30: the list.
 */
class AssistedBookingController extends Controller
{
    // ------------------------------------------------------------------ Day 30: the list (I3)

    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', Rule::in(Booking::PAYMENT_STATUSES)],
            'status' => ['nullable', Rule::in(Booking::STATUSES)],
            'staff' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        if ($request->boolean('mine')) {
            $f['staff'] = $request->user()->id;
        }

        $base = Booking::query()->where('source', 'csr')
            ->when($f['q'] ?? null, function (Builder $query, string $term) {
                $like = '%' . str_replace(['%', '_'], ' ', trim($term)) . '%';
                $query->where(function (Builder $w) use ($like) {
                    $w->where('reference', 'like', $like)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                        ->orWhereHas('travelers', fn ($t) => $t->where('full_name', 'like', $like));
                });
            })
            ->when($f['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($f['staff'] ?? null, fn (Builder $q, $v) => $q->where('created_by', (int) $v))
            ->when($f['from'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '<=', $v));

        // Tabs and money follow the search, staff and dates, but not the payment tab itself.
        $counts = (clone $base)->selectRaw('payment_status, count(*) as n')->groupBy('payment_status')->pluck('n', 'payment_status');

        $owing = (clone $base)->whereNotIn('status', ['cancelled'])->whereIn('payment_status', ['unpaid', 'partial'])
            ->withSum(['payments as charged_total' => fn ($q) => $q->where('type', 'charge')->where('status', 'succeeded')], 'amount')
            ->withSum(['payments as refunded_total' => fn ($q) => $q->where('type', 'refund')->where('status', 'succeeded')], 'amount')
            ->get(['id', 'total_amount', 'currency'])
            ->groupBy('currency')
            ->map(fn ($rows) => round($rows->sum(fn ($b) => max(0, (float) $b->total_amount - ((float) $b->charged_total - (float) $b->refunded_total))), 2));

        $bookings = (clone $base)
            ->when($f['payment_status'] ?? null, fn (Builder $q, string $v) => $q->where('payment_status', $v))
            ->with(['user', 'leadTraveler', 'tour', 'departure', 'creator'])
            ->withSum(['payments as charged_total' => fn ($q) => $q->where('type', 'charge')->where('status', 'succeeded')], 'amount')
            ->withSum(['payments as refunded_total' => fn ($q) => $q->where('type', 'refund')->where('status', 'succeeded')], 'amount')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Open customer payment links per booking (a separate query: Booking has no relation to payment requests).
        $openLinks = PaymentRequest::query()->whereIn('booking_id', $bookings->pluck('id'))
            ->whereNull('cancelled_at')->whereNull('paid_at')->where('expires_at', '>', now())
            ->selectRaw('booking_id, count(*) as n')->groupBy('booking_id')->pluck('n', 'booking_id');

        $staff = User::query()->whereIn('id', Booking::query()->where('source', 'csr')->whereNotNull('created_by')->select('created_by'))->orderBy('name')->get(['id', 'name']);

        return view('admin.assisted.index', [
            'bookings' => $bookings, 'openLinks' => $openLinks, 'filters' => $f, 'counts' => $counts, 'owing' => $owing, 'staff' => $staff,
            'payable' => PaymentSessionService::PAYABLE_STATUSES,
        ]);
    }

    // ------------------------------------------------------------------ Days 26 and 27: the form

    public function create(Request $request, BookingEngine $engine)
    {
        $override = $request->user()->can('admin.assisted.override_cutoff');
        $tours = Tour::query()->published()->orderBy('title')->get()->map(function (Tour $t) use ($engine, $override) {
            $departures = $engine->bookableDepartures($t, $override);

            return [
                'id' => $t->id, 'title' => $t->title ?: ('Tour #' . $t->id), 'currency' => $t->currency, 'cutoff' => $engine->cutoffDays($t),
                'departures' => $departures->map(fn ($row) => [
                    'id' => $row['departure']->id, 'date' => $row['departure']->departs_on->format('d M Y'),
                    'seats' => $row['seats_left'], 'price' => $row['unit_price'], 'late' => $row['inside_cutoff'],
                ])->all(),
            ];
        })->filter(fn ($t) => $t['departures'] !== [])->values();

        return view('admin.assisted.create', [
            'tours' => $tours,
            'maxTravelers' => (int) config('booking.max_travelers', 10),
            'minSearch' => CustomerDirectory::MIN_SEARCH,
        ]);
    }

    /** Customer search for the form. Returns a few matches with most contact details hidden. */
    public function customers(Request $request, CustomerDirectory $customers)
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return response()->json(['results' => $customers->search($q)])->header('Cache-Control', 'no-store');
    }

    /** Live price and seat check for the form. The same quote the booking will be created with. */
    public function quote(Request $request, BookingEngine $engine)
    {
        $data = $request->validate([
            'tour_id' => ['required', 'integer'], 'tour_departure_id' => ['required', 'integer'],
            'travelers' => ['required', 'integer', 'min:1', 'max:100'], 'insurance' => ['nullable', 'boolean'],
        ]);

        $tour = Tour::find($data['tour_id']);
        $departure = TourDeparture::find($data['tour_departure_id']);
        if (! $tour || ! $departure) {
            return response()->json(['ok' => false, 'error' => 'Choose a tour and a departure.']);
        }

        try {
            return response()->json(['ok' => true, 'quote' => $engine->quote($tour, $departure, (int) $data['travelers'], $request->boolean('insurance'), $request->user()->can('admin.assisted.override_cutoff'))->toArray()]);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function store(Request $request, BookingEngine $engine, CustomerDirectory $customers)
    {
        $data = $request->validate(BookingEngine::rules() + [
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['required_if:customer_mode,existing', 'nullable', 'integer'],
            'customer_name' => ['required_if:customer_mode,new', 'nullable', 'string', 'max:140'],
            'customer_email' => ['required_if:customer_mode,new', 'nullable', 'email:rfc', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ], [
            'customer_id.required_if' => 'Search for the customer and choose one, or switch to "New customer".',
            'customer_name.required_if' => 'Enter the customer\'s name.',
            'customer_email.required_if' => 'Enter the customer\'s email address.',
            'travelers.required' => 'Add at least one traveler.',
            'travelers.*.full_name.required' => 'Every traveler needs a name.',
        ]);

        try {
            $booking = DB::transaction(function () use ($data, $request, $engine, $customers) {
                if ($data['customer_mode'] === 'existing') {
                    $customer = User::query()->find($data['customer_id']);
                    if (! $customer || ! CustomerDirectory::isCustomer($customer)) {
                        throw new \DomainException('Choose a customer from the search results.');
                    }
                } else {
                    [$customer] = $customers->findOrCreate($data['customer_name'], $data['customer_email'], $data['customer_phone'] ?? null, $request->user());
                }

                return $engine->create($data, 'csr', $customer, $request->user(), $request->user()->can('admin.assisted.override_cutoff'));
            });
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        if ($request->user()->can('admin.payments.link.create')) {
            return redirect()->route('admin.assisted.payment', $booking)->with('success', 'Booking ' . $booking->reference . ' created. Now take payment or send the customer a payment link.');
        }

        return redirect()->route('admin.assisted.index')->with('success', 'Booking ' . $booking->reference . ' created. It is waiting for payment.');
    }

    // ------------------------------------------------------------------ Days 28 and 29: take payment

    public function payment(Booking $booking)
    {
        $booking->load(['user', 'tour', 'departure']);

        return view('admin.assisted.payment', [
            'booking' => $booking,
            'paid' => $booking->paidAmount(),
            'balance' => PaymentSessionService::balanceDue($booking),
            'payable' => in_array($booking->status, PaymentSessionService::PAYABLE_STATUSES, true),
            'requests' => PaymentRequest::query()->where('booking_id', $booking->id)->latest('id')->limit(10)->get(),
            'payments' => Payment::query()->where('booking_id', $booking->id)->where('type', 'charge')->latest('id')->limit(10)->get(),
            'openProviderLinks' => PaymentSessionService::openLinks($booking),
        ]);
    }

    /** Day 28: the customer is with the agent. Create the provider's hosted payment page and open it. */
    public function hosted(Request $request, Booking $booking, PaymentSessionService $sessions)
    {
        $amount = $this->amount($request, $booking);

        try {
            $result = $sessions->start($booking, $amount, $request->user(), 'csr_hosted');
        } catch (GatewayException $e) {
            return redirect()->route('admin.assisted.payment', $booking)->with('error', $e->getMessage())->withInput();
        }

        // The audit entry never contains the link itself.
        SafeAudit::log('payment.session_created', 'Hosted payment page for booking ' . $booking->reference, [
            'booking_id' => $booking->id, 'payment_id' => $result['payment']->id, 'amount' => (float) $result['payment']->amount,
            'currency' => $booking->currency, 'gateway' => $result['payment']->gateway, 'channel' => 'csr_hosted',
        ]);

        return redirect()->away($result['url']);
    }

    /** Day 29: make a customer payment link and email it. The link is shown once so staff can also copy it. */
    public function sendLink(Request $request, Booking $booking, PaymentRequestService $links)
    {
        $amount = $this->amount($request, $booking);

        try {
            ['request' => $link, 'url' => $url] = $links->create($booking, $amount, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('admin.assisted.payment', $booking)->with('error', $e->getMessage())->withInput();
        }

        $sent = $links->send($link, $url);
        $flash = $sent ? 'The payment link was emailed to the customer.' : $link->fresh()->send_error;

        return redirect()->route('admin.assisted.payment', $booking)
            ->with($sent ? 'success' : 'warning', $flash)
            ->with('customer_link', $url)
            ->with('customer_link_hours', (int) config('booking.payment_link_hours', 72));
    }

    public function resendLink(Request $request, Booking $booking, PaymentRequest $paymentRequest, PaymentRequestService $links)
    {
        abort_unless($paymentRequest->booking_id === $booking->id, 404);

        try {
            $url = $links->resend($paymentRequest, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('admin.assisted.payment', $booking)->with('error', $e->getMessage());
        }

        $fresh = $paymentRequest->fresh();

        return redirect()->route('admin.assisted.payment', $booking)
            ->with($fresh->send_error ? 'warning' : 'success', $fresh->send_error ?: 'A new link was emailed. The old link no longer works.')
            ->with('customer_link', $url)
            ->with('customer_link_hours', max(1, (int) now()->diffInHours($fresh->expires_at, false)));
    }

    public function cancelLink(Request $request, Booking $booking, PaymentRequest $paymentRequest, PaymentRequestService $links)
    {
        abort_unless($paymentRequest->booking_id === $booking->id, 404);

        try {
            $links->cancel($paymentRequest, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('admin.assisted.payment', $booking)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.assisted.payment', $booking)->with('success', 'The payment link was cancelled and no longer works.');
    }

    /** The amount chosen on the payment page: the full balance, or a custom amount. */
    private function amount(Request $request, Booking $booking): float
    {
        $data = $request->validate([
            'choice' => ['required', Rule::in(['balance', 'custom'])],
            'amount' => ['required_if:choice,custom', 'nullable', 'numeric', 'decimal:0,2', 'min:0.5', 'max:1000000'],
        ], ['amount.required_if' => 'Enter the amount to pay.']);

        return $data['choice'] === 'balance' ? PaymentSessionService::balanceDue($booking) : (float) $data['amount'];
    }
}
