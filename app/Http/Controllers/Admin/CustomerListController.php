<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Services\Bookings\CustomerDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customers menu: every customer account, with their bookings and documents in one place.
 * A customer is a user without a staff role (see CustomerDirectory::isCustomer).
 */
class CustomerListController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'with_documents' => ['nullable', 'boolean'],
        ]);

        $canDocs = $request->user()->can('admin.documents.view');

        $customers = $this->customers()
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $like = '%' . str_replace(['%', '_'], ' ', trim($term)) . '%';
                $q->where(fn (Builder $w) => $w->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like)->orWhere('users.phone', 'like', $like));
            })
            ->when($canDocs && $request->boolean('with_documents'), fn (Builder $q) => $q->whereIn('users.id', $this->userIdsWithDocuments()))
            ->orderBy('users.name')
            ->paginate(25)
            ->withQueryString();

        $ids = $customers->pluck('id');
        $bookings = Booking::query()->whereIn('user_id', $ids)->selectRaw('user_id, count(*) as n, max(created_at) as last_at')->groupBy('user_id')->get()->keyBy('user_id');
        $documents = $canDocs
            ? CustomerDocument::query()->join('bookings', 'bookings.id', '=', 'customer_documents.booking_id')
                ->whereIn('bookings.user_id', $ids)->selectRaw('bookings.user_id as user_id, count(*) as n')->groupBy('bookings.user_id')->pluck('n', 'user_id')
            : collect();

        return view('admin.customers.index', [
            'customers' => $customers, 'filters' => $f, 'bookings' => $bookings, 'documents' => $documents, 'canDocs' => $canDocs,
        ]);
    }

    public function show(Request $request, User $customer)
    {
        abort_unless(CustomerDirectory::isCustomer($customer), 404);

        $bookings = Booking::query()->where('user_id', $customer->id)->with(['tour', 'departure'])->latest('id')->get();
        $canDocs = $request->user()->can('admin.documents.view');

        $documents = $canDocs
            ? CustomerDocument::query()->whereIn('booking_id', $bookings->pluck('id'))->with(['traveler:id,full_name', 'uploader:id,name'])->orderByDesc('id')->get()->groupBy('booking_id')
            : collect();

        return view('admin.customers.show', [
            'customer' => $customer, 'bookings' => $bookings, 'documents' => $documents, 'canDocs' => $canDocs,
            'documentTotal' => $documents->flatten(1)->count(),
        ]);
    }

    private function customers(): Builder
    {
        return User::query()->where('is_super', false)->whereNull('role_id');
    }

    private function userIdsWithDocuments()
    {
        return Booking::query()->whereIn('id', CustomerDocument::query()->select('booking_id'))->select('user_id');
    }
}
