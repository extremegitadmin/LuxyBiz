<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\DB;

/**
 * The one place that prices, checks seats and creates a booking (Day 27).
 * The staff (assisted) booking form uses it with source "csr". The public website checkout must use the same
 * methods with source "web", so a customer and a CSR always get the same price, the same seat check and the same record.
 * Rule failures throw a DomainException whose message is safe to show to the person booking.
 */
class BookingEngine
{
    public const SOURCES = ['web', 'csr'];

    /** Validation rules for the request. The public checkout can reuse them as they are. */
    public static function rules(): array
    {
        $max = (int) config('booking.max_travelers', 10);

        return [
            'tour_id' => ['required', 'integer'],
            'tour_departure_id' => ['required', 'integer'],
            'insurance_selected' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'travelers' => ['required', 'array', 'min:1', 'max:' . $max],
            'travelers.*.full_name' => ['required', 'string', 'max:140'],
            'travelers.*.nationality' => ['nullable', 'string', 'max:80'],
            'travelers.*.date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'travelers.*.email' => ['nullable', 'email:rfc', 'max:160'],
            'travelers.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** Seats still free on a departure: capacity minus travelers on bookings that are not cancelled. */
    public function seatsLeft(TourDeparture $departure): int
    {
        $booked = (int) Booking::query()->where('tour_departure_id', $departure->id)->where('status', '!=', 'cancelled')->sum('travelers_count');

        return max(0, (int) $departure->capacity - $booked);
    }

    /** Departures a customer can book today: published tour, open, in the future, with seats. */
    public function bookableDepartures(Tour $tour): \Illuminate\Support\Collection
    {
        if ($tour->status !== 'published') {
            return collect();
        }

        return $tour->departures()->where('status', 'open')->whereDate('departs_on', '>', now()->toDateString())->orderBy('departs_on')->get()
            ->map(fn (TourDeparture $d) => ['departure' => $d, 'seats_left' => $this->seatsLeft($d), 'unit_price' => $this->unitPrice($tour, $d)])
            ->filter(fn ($row) => $row['seats_left'] > 0)
            ->values();
    }

    public function unitPrice(Tour $tour, TourDeparture $departure): float
    {
        return round((float) ($departure->price_override ?? $tour->base_price), 2);
    }

    public function insuranceFor(string $currency, int $travelers): float
    {
        $per = (float) (config('booking.insurance_per_traveler')[$currency] ?? 0);

        return round($per * $travelers, 2);
    }

    /** @throws \DomainException */
    public function quote(Tour $tour, TourDeparture $departure, int $travelers, bool $insurance): BookingQuote
    {
        $max = (int) config('booking.max_travelers', 10);

        if ($tour->status !== 'published') {
            throw new \DomainException('This tour is not open for booking.');
        }
        if ((int) $departure->tour_id !== (int) $tour->id) {
            throw new \DomainException('That departure does not belong to this tour.');
        }
        if ($departure->status !== 'open') {
            throw new \DomainException('Sales for this departure are closed.');
        }
        if (! $departure->departs_on || $departure->departs_on->lte(now()->startOfDay())) {
            throw new \DomainException('This departure date has passed or is today. Choose a later departure.');
        }
        if ($travelers < 1 || $travelers > $max) {
            throw new \DomainException('A booking can have 1 to ' . $max . ' travelers.');
        }

        $left = $this->seatsLeft($departure);
        if ($travelers > $left) {
            throw new \DomainException($left === 0 ? 'This departure is full.' : 'Only ' . $left . ' ' . ($left === 1 ? 'seat is' : 'seats are') . ' left on this departure.');
        }

        $unit = $this->unitPrice($tour, $departure);
        if ($unit <= 0) {
            throw new \DomainException('This tour has no price set yet.');
        }
        $currency = (string) $tour->currency;
        $subtotal = round($unit * $travelers, 2);
        $insuranceAmount = $insurance ? $this->insuranceFor($currency, $travelers) : 0.0;

        return new BookingQuote($currency, $unit, $travelers, $subtotal, $insuranceAmount, round($subtotal + $insuranceAmount, 2), $left);
    }

    /**
     * Create the booking and its travelers. The first traveler is the lead.
     * The departure row is locked while seats are checked, so two people booking the last seat cannot both get it.
     *
     * @param  array<string, mixed>  $data  validated with self::rules()
     *
     * @throws \DomainException
     */
    public function create(array $data, string $source, User $customer, ?User $by = null): Booking
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException('Unknown booking source.');
        }

        $booking = DB::transaction(function () use ($data, $source, $customer, $by) {
            $tour = Tour::query()->find($data['tour_id']) ?? throw new \DomainException('Choose a tour.');
            $departure = TourDeparture::query()->lockForUpdate()->find($data['tour_departure_id']) ?? throw new \DomainException('Choose a departure.');

            $travelers = array_values($data['travelers']);
            $quote = $this->quote($tour, $departure, count($travelers), (bool) ($data['insurance_selected'] ?? false));

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'user_id' => $customer->id,
                'tour_id' => $tour->id,
                'tour_departure_id' => $departure->id,
                'travelers_count' => $quote->travelers,
                'subtotal' => $quote->subtotal,
                'insurance_amount' => $quote->insuranceAmount,
                'total_amount' => $quote->total,
                'currency' => $quote->currency,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'source' => $source,
                'insurance_selected' => $quote->insuranceAmount > 0,
                'created_by' => $source === 'csr' ? $by?->id : null,
                'notes' => $this->clean($data['notes'] ?? null, 2000),
            ]);

            foreach ($travelers as $i => $row) {
                BookingTraveler::create([
                    'booking_id' => $booking->id,
                    'is_lead' => $i === 0,
                    'full_name' => $this->clean($row['full_name'], 140),
                    'nationality' => $this->clean($row['nationality'] ?? null, 80),
                    'date_of_birth' => $row['date_of_birth'] ?? null,
                    // The lead traveler's contact defaults to the customer's.
                    'email' => $this->clean($row['email'] ?? null, 160) ?? ($i === 0 ? $customer->email : null),
                    'phone' => $this->clean($row['phone'] ?? null, 30) ?? ($i === 0 ? $customer->phone : null),
                ]);
            }

            return $booking;
        });

        SafeAudit::log('booking.created', 'Booking ' . $booking->reference . ' created (' . $source . ')', [
            'subject_id' => $booking->id, 'subject_type' => 'Booking', 'booking_reference' => $booking->reference,
            'source' => $source, 'created_by' => $by?->id, 'customer_id' => $customer->id,
            'tour_id' => $booking->tour_id, 'departure_id' => $booking->tour_departure_id,
            'travelers' => $booking->travelers_count, 'total' => (float) $booking->total_amount, 'currency' => $booking->currency,
        ]);

        return $booking;
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
