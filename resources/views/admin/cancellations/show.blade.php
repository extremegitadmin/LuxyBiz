@extends('admin.layouts.master')
@section('content')
    @php
        $money = fn ($v, $cur = null) => ($cur ?? $r->currency) . ' ' . number_format((float) $v, 2);
        $customer = $booking?->user?->name ?? $booking?->leadTraveler?->full_name ?? 'Guest';
        $departs = $booking?->departure?->departs_on;
        $tiers = $calc['tiers'] ?? [];
    @endphp

    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Cancellations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.cancellations.index') }}">Cancellation requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $r->reference }}</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <a href="{{ route('admin.cancellations.index') }}" class="btn btn-outline-secondary px-4">Back to list</a>
        </div>
    </div>
    <!--end breadcrumb-->

    @if (session('success'))
        <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex align-items-center gap-3 mb-3">
        <h4 class="mb-0 fw-semibold">{{ $r->reference }}</h4>
        @include('admin.cancellations._badge', ['value' => $r->status])
        @if (($w = $r->waitingDays()) !== null && $w > 0)
            <span class="small {{ $w >= 3 ? 'text-danger' : 'text-secondary' }}">waiting {{ $w }} {{ \Illuminate\Support\Str::plural('day', $w) }}</span>
        @endif
    </div>

    {{-- Rules or payments changed since the request was opened --}}
    @if ($changed)
        <div class="alert alert-warning border-0">
            <strong>The amount would be different today.</strong>
            When this request was opened the refund was {{ $money($r->calculated_refund) }}. With today's rules and payments it would be
            <strong>{{ $money($current->refund) }}</strong> ({{ $current->percent }}%). The saved amount below is the one that stays on this request until it is overridden or decided.
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-xl-8">

            {{-- The refund --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Refund</h5>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-secondary small">Calculated by the rules</div>
                                <div class="fs-4 fw-semibold">{{ $money($r->calculated_refund) }}</div>
                                @if ($calc)<div class="small text-secondary">{{ $calc['percent'] }}% of the refundable amount</div>@endif
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-3 h-100 {{ $r->isOverridden() ? 'border-warning' : '' }}">
                                <div class="text-secondary small">Amount to refund</div>
                                <div class="fs-4 fw-semibold">{{ $money($r->refundAmount()) }}</div>
                                @if ($r->isOverridden())
                                    <div class="small text-warning">Overridden by staff</div>
                                @else
                                    <div class="small text-secondary">Same as calculated</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($r->override_reason)
                        <div class="mb-3"><span class="text-secondary small">Reason for the override</span><div>{{ $r->override_reason }}</div></div>
                    @endif

                    @if ($calc)
                        @if (! empty($calc['warnings']))
                            <div class="alert alert-warning border-0">
                                <strong>Check before deciding</strong>
                                <ul class="mb-0 ps-3">
                                    @foreach ($calc['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-3">
                                <tbody>
                                    <tr><td class="text-secondary">Days before departure</td><td class="text-end">{{ $calc['days_before'] === null ? 'unknown (no departure date)' : $calc['days_before'] }}</td></tr>
                                    <tr><td class="text-secondary">Refund percent for those days</td><td class="text-end">{{ $calc['percent'] }}%</td></tr>
                                    <tr><td class="text-secondary">Booking total</td><td class="text-end">{{ $money($calc['total']) }}</td></tr>
                                    <tr><td class="text-secondary">Refundable amount (rules)</td><td class="text-end">{{ $money($calc['refundable_base']) }}</td></tr>
                                    @if ($calc['fixed_fee'] > 0)
                                        <tr><td class="text-secondary">Fixed cancellation fee, taken first</td><td class="text-end">- {{ $money($calc['fixed_fee']) }}</td></tr>
                                    @endif
                                    <tr><td class="text-secondary">Customer is entitled to</td><td class="text-end">{{ $money($calc['entitlement']) }}</td></tr>
                                    <tr><td class="text-secondary">Kept by LuxyBiz (total minus entitlement)</td><td class="text-end">{{ $money($calc['retained']) }}</td></tr>
                                    <tr><td class="text-secondary">Paid by the customer (not yet refunded)</td><td class="text-end">{{ $money($calc['paid']) }}</td></tr>
                                    <tr class="fw-semibold"><td>Refund (paid minus kept, never below 0)</td><td class="text-end">{{ $money($calc['refund']) }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        @if (! empty($calc['notes']))
                            <ul class="small text-secondary ps-3 mb-3">
                                @foreach ($calc['notes'] as $note)<li>{{ $note }}</li>@endforeach
                            </ul>
                        @endif

                        <div class="border rounded-3 p-3 mb-3">
                            <div class="fw-semibold mb-1">Travel credit instead of a refund</div>
                            @if (($calc['credit'] ?? 0) > 0)
                                {{ $money($calc['credit']) }}
                                @if (! empty($calc['credit_valid_until']))<span class="text-secondary">, valid until {{ \Illuminate\Support\Carbon::parse($calc['credit_valid_until'])->format('d M Y') }}</span>@endif
                            @else
                                <span class="text-secondary">No credit, because there is no refund.</span>
                            @endif
                        </div>

                        @if ($tiers)
                            <div class="small text-secondary">
                                Rules used: {{ implode(' · ', $tiers) }}.
                                @if (empty($calc['policy_confirmed']))<span class="text-warning">These rules were still placeholders.</span>@endif
                                Worked out for a cancellation on {{ $r->requested_on?->format('d M Y') }}.
                            </div>
                        @endif
                    @else
                        <div class="text-secondary">No calculation was saved for this request (an older or imported request).</div>
                    @endif
                </div>
            </div>

            {{-- Money on the booking --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Payments on the booking</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Method</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($payments as $p)
                                    <tr>
                                        <td class="text-nowrap">
                                            @can('admin.payments.view')
                                                <a href="{{ route('admin.payments.show', $p) }}">{{ $p->created_at?->format('d M Y, H:i') }}</a>
                                            @else
                                                {{ $p->created_at?->format('d M Y, H:i') }}
                                            @endcan
                                        </td>
                                        <td>{{ $p->type === 'refund' ? 'Refund' : 'Payment' }}</td>
                                        <td>{{ $p->method ? ucfirst(str_replace('_', ' ', $p->method)) : '-' }}</td>
                                        <td class="text-end text-nowrap {{ $p->type === 'refund' ? 'text-danger' : '' }}">{{ $p->type === 'refund' ? '-' : '' }}{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                                        <td>@include('admin.payments._badge', ['value' => $p->status])</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">No payments on this booking.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($booking)
                        <div class="mt-3 small text-secondary">Net paid so far: <strong class="text-body">{{ $money($booking->paidAmount(), $booking->currency) }}</strong> of {{ $money($booking->total_amount, $booking->currency) }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">

            {{-- The request --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Request</h5>
                    <dl class="mb-0">
                        <dt class="text-secondary small fw-normal">Requested on</dt><dd>{{ $r->requested_on?->format('d M Y') }}</dd>
                        <dt class="text-secondary small fw-normal">Requested by</dt>
                        <dd>{{ $r->source === 'customer' ? 'Customer' : 'Staff' }}@if ($r->requester) ({{ $r->requester->name }})@endif</dd>
                        <dt class="text-secondary small fw-normal">Reason given</dt><dd>{{ $r->reason ?: '-' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- The booking --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Booking</h5>
                    @if ($booking)
                        <dl class="mb-0">
                            <dt class="text-secondary small fw-normal">Booking</dt>
                            <dd>
                                @can('admin.bookings.view')
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}">{{ $booking->reference }}</a>
                                @else
                                    {{ $booking->reference }}
                                @endcan
                                <span class="text-secondary">({{ str_replace('_', ' ', $booking->status) }})</span>
                            </dd>
                            <dt class="text-secondary small fw-normal">Customer</dt>
                            <dd>{{ $customer }}@if ($booking->user?->email)<div class="small text-secondary">{{ $booking->user->email }}</div>@endif</dd>
                            <dt class="text-secondary small fw-normal">Tour</dt><dd>{{ $booking->tour?->title ?: '-' }}</dd>
                            <dt class="text-secondary small fw-normal">Departure</dt>
                            <dd>{!! $departs ? e($departs->format('d M Y')) : '<span class="text-secondary">not set</span>' !!}</dd>
                            <dt class="text-secondary small fw-normal">Travelers</dt><dd>{{ $booking->travelers_count }}</dd>
                            <dt class="text-secondary small fw-normal">Tour price / insurance / total</dt>
                            <dd>{{ number_format($booking->subtotal, 2) }} / {{ number_format($booking->insurance_amount, 2) }} / <strong>{{ number_format($booking->total_amount, 2) }}</strong> {{ $booking->currency }}</dd>
                        </dl>
                    @else
                        <div class="text-secondary">The booking no longer exists.</div>
                    @endif
                </div>
            </div>

            {{-- Decision (read only until Day 19) --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Decision</h5>
                    @if ($r->decided_at)
                        <dl class="mb-0">
                            <dt class="text-secondary small fw-normal">Decided</dt>
                            <dd>{{ $r->decided_at->format('d M Y, H:i') }}@if ($r->decider) by {{ $r->decider->name }}@endif</dd>
                            @if ($r->decision_note)<dt class="text-secondary small fw-normal">Note</dt><dd>{{ $r->decision_note }}</dd>@endif
                            @if ($r->completed_at)<dt class="text-secondary small fw-normal">{{ $r->status === 'credited' ? 'Credit issued' : 'Refund paid' }}</dt><dd>{{ $r->completed_at->format('d M Y, H:i') }}</dd>@endif
                        </dl>
                    @else
                        <div class="text-secondary">Not decided yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
