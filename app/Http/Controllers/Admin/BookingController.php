<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\BookingChange;
use App\Services\BookingChangeTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Owner: Asha. Day 4-5: edit() and update(). Day 6: index(), show() and export().
 */
class BookingController extends Controller
{
    // ------------------------------------------------------------------ list

    /** Bookings list with search and filters. */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $bookings = $this->listQuery($filters)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'filters'));
    }

    /** CSV download of the same filtered list. Every export is logged. */
    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $this->audit('booking.exported', null, ['filters' => $filters]);

        $query = $this->listQuery($filters);
        $filename = 'bookings-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows names correctly
            fputcsv($out, [
                'Reference', 'Created', 'Customer', 'Customer email', 'Lead traveler', 'Travelers',
                'Currency', 'Total', 'Paid (net)', 'Booking status', 'Payment status', 'Source',
            ]);

            foreach ($query->lazyByIdDesc(500) as $b) {
                fputcsv($out, [
                    $b->reference,
                    $b->created_at?->format('Y-m-d H:i'),
                    $this->csv($b->user?->name),
                    $this->csv($b->user?->email),
                    $this->csv($b->leadTraveler?->full_name),
                    $b->travelers_count,
                    $b->currency,
                    number_format((float) $b->total_amount, 2, '.', ''),
                    number_format($this->net($b), 2, '.', ''),
                    $b->status,
                    $b->payment_status,
                    $b->source,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------- detail

    /** Booking detail. Health notes are NOT shown here (restricted view comes on Days 24-25). */
    public function show(Booking $booking)
    {
        $booking->load([
            'user',
            'travelers' => fn ($q) => $q->orderByDesc('is_lead')->orderBy('id'),
            'payments' => fn ($q) => $q->latest('id'),
        ]);
        $changes = $booking->changes()->with('user')->limit(10)->get();
        $paid = $booking->paidAmount();

        return view('admin.bookings.show', compact('booking', 'changes', 'paid'));
    }

    // ------------------------------------------------------------------ edit

    /** Edit booking (E3): show the form and the change history. */
    public function edit(Booking $booking)
    {
        $booking->load([
            'user',
            'travelers' => fn ($q) => $q->orderByDesc('is_lead')->orderBy('id'),
        ]);
        $changes = $booking->changes()->with('user')->limit(20)->get();

        return view('admin.bookings.edit', compact('booking', 'changes'));
    }

    /** Edit booking (E3): validate, save, record what changed. */
    public function update(UpdateBookingRequest $request, Booking $booking, BookingChangeTracker $tracker)
    {
        $data = $request->validated();

        $diff = DB::transaction(function () use ($data, $booking, $request, $tracker) {
            $before = $tracker->snapshot($booking);

            $booking->update([
                'insurance_selected' => $request->boolean('insurance_selected'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['travelers'] as $row) {
                $traveler = $booking->travelers()->findOrFail($row['id']);

                $fields = [
                    'is_lead' => (int) $row['id'] === (int) $data['lead_traveler_id'],
                    'full_name' => $row['full_name'],
                    'date_of_birth' => $row['date_of_birth'] ?? null,
                    'nationality' => $row['nationality'] ?? null,
                    'passport_expiry' => $row['passport_expiry'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'arrival_date' => $row['arrival_date'] ?? null,
                    'arrival_flight' => $row['arrival_flight'] ?? null,
                    'arrival_time' => $row['arrival_time'] ?? null,
                    'arrival_details' => $row['arrival_details'] ?? null,
                    'flag_note' => $row['flag_note'] ?? null,
                ];

                // Health notes are NOT part of this form: they are changed on the restricted health page (Day 24), so they are kept as they are here.

                // A blank passport field keeps the stored (encrypted) number.
                if (! empty($row['passport_number'])) {
                    $fields['passport_number'] = $row['passport_number'];
                }

                $traveler->update($fields);
            }

            $diff = $tracker->diff($before, $tracker->snapshot($booking));

            if (! $tracker->isEmpty($diff)) {
                BookingChange::create([
                    'booking_id' => $booking->id,
                    'changed_by' => $request->user()?->id,
                    'old_values' => $diff['old'],
                    'new_values' => $diff['new'],
                    'reason' => $data['reason'] ?? null,
                ]);
            }

            return $diff;
        });

        if ($tracker->isEmpty($diff)) {
            return redirect()->route('admin.bookings.edit', $booking)->with('success', 'No changes were made.');
        }

        $this->audit('booking.edited', $booking, [
            'reference' => $booking->reference,
            'changed' => $this->changedFieldNames($diff),
            'reason' => $data['reason'] ?? null,
        ]);
        if ($diff['sensitive']) {
            // Which sensitive fields changed, never their values.
            $this->audit('booking.sensitive_edited', $booking, [
                'reference' => $booking->reference,
                'fields' => $diff['sensitive'],
            ]);
        }

        return redirect()->route('admin.bookings.edit', $booking)->with('success', 'Booking ' . $booking->reference . ' updated.');
    }

    // --------------------------------------------------------------- helpers

    /** Validated search and filter values from the query string. */
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Booking::STATUSES)],
            'payment_status' => ['nullable', Rule::in(Booking::PAYMENT_STATUSES)],
            'source' => ['nullable', Rule::in(Booking::SOURCES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /** Shared by the list and the CSV export, so both always show the same bookings. */
    private function listQuery(array $f): Builder
    {
        return Booking::query()
            ->with(['user', 'leadTraveler'])
            ->withSum(['payments as charged_total' => fn ($q) => $q->where('type', 'charge')->where('status', 'succeeded')], 'amount')
            ->withSum(['payments as refunded_total' => fn ($q) => $q->where('type', 'refund')->where('status', 'succeeded')], 'amount')
            ->when($f['q'] ?? null, function (Builder $query, string $term) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)) . '%';
                $query->where(function (Builder $w) use ($like) {
                    $w->where('reference', 'like', $like)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                        ->orWhereHas('travelers', fn ($t) => $t->where('full_name', 'like', $like));
                });
            })
            ->when($f['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($f['payment_status'] ?? null, fn (Builder $q, string $v) => $q->where('payment_status', $v))
            ->when($f['source'] ?? null, fn (Builder $q, string $v) => $q->where('source', $v))
            ->when($f['from'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '<=', $v));
    }

    /** Net paid amount from the withSum columns of listQuery(). */
    private function net(Booking $b): float
    {
        return round((float) $b->charged_total - (float) $b->refunded_total, 2);
    }

    /** Stops spreadsheet formulas: a text cell starting with = + - @ is prefixed so Excel shows it as text. */
    private function csv(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /** Field names that changed, for the audit entry (no values). */
    private function changedFieldNames(array $diff): array
    {
        $names = array_keys($diff['new']['booking']);
        foreach ($diff['new']['travelers'] as $id => $fields) {
            foreach (array_keys($fields) as $field) {
                if ($field !== '_name') {
                    $names[] = "traveler.$id.$field";
                }
            }
        }

        return $names;
    }

    /**
     * Writes to the team's audit log (App\Support\Audit, signature: action, description, properties).
     * SafeAudit never lets a problem with the audit log break the booking screen.
     */
    private function audit(string $action, ?Model $subject, array $meta): void
    {
        $label = $subject instanceof Booking ? 'Booking ' . $subject->reference : ($subject ? class_basename($subject) . ' #' . $subject->getKey() : $action);
        \App\Support\SafeAudit::log($action, $label, $meta + ($subject ? ['subject_id' => $subject->getKey(), 'subject_type' => class_basename($subject)] : []));
    }
}
