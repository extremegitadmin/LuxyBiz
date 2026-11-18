@extends('admin.layouts.master')
@section('content')
    @php $isRefund = $payment->type === 'refund'; @endphp

    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Payments</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.payments.index') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $isRefund ? 'Refund' : 'Payment' }} #{{ $payment->id }}</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary px-4">Back to payments</a>
            @if ($booking)
                <a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-primary px-4">View booking</a>
            @endif
        </div>
    </div>
    <!--end breadcrumb-->

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            {{-- Details --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <div>
                            <h4 class="mb-1 fw-semibold {{ $isRefund ? 'text-danger' : '' }}">{{ $isRefund ? '-' : '' }}{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</h4>
                            <div class="text-secondary">{{ $isRefund ? 'Refund' : 'Payment' }} #{{ $payment->id }}</div>
                        </div>
                        <div class="ms-auto">@include('admin.payments._badge', ['value' => $payment->status])</div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-secondary small">Booking</div>
                            @if ($booking)
                                <a class="fw-semibold" href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->reference }}</a>
                            @else
                                <div class="fw-semibold">-</div>
                            @endif
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Customer</div>
                            <div class="fw-semibold">{{ $booking?->user?->name ?? 'Guest' }}</div>
                            @if ($booking?->user?->email)<div class="small text-secondary">{{ $booking->user->email }}</div>@endif
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Method</div>
                            <div class="fw-semibold">{{ $payment->method ? ucfirst(str_replace('_', ' ', $payment->method)) : '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Channel</div>
                            <div class="fw-semibold">{{ $payment->channel ? ucfirst(str_replace('_', ' ', $payment->channel)) : '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Gateway</div>
                            <div class="fw-semibold">{{ $payment->gateway ? ucfirst($payment->gateway) : '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Gateway reference</div>
                            <div class="fw-semibold text-break">{{ $payment->gateway_reference ?: '-' }}</div>
                        </div>
                        @if ($payment->gateway_session_id)
                            <div class="col-12">
                                <div class="text-secondary small">Gateway session</div>
                                <div class="fw-semibold text-break">{{ $payment->gateway_session_id }}</div>
                            </div>
                        @endif
                        <div class="col-6">
                            <div class="text-secondary small">Recorded</div>
                            <div class="fw-semibold">{{ $payment->created_at?->format('d M Y, H:i') }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Paid at</div>
                            <div class="fw-semibold">{{ $payment->paid_at?->format('d M Y, H:i') ?? '-' }}</div>
                        </div>
                    </div>
                    @if ($payment->note)
                        <hr>
                        <div class="text-secondary small mb-1">Note</div>
                        <div style="white-space: pre-line">{{ $payment->note }}</div>
                    @endif
                    <div class="form-text mt-3">Only gateway references are stored. Full card numbers and CVV never reach this system.</div>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Status timeline</h5>
                    <ul class="list-unstyled mb-0">
                        @foreach ($timeline as $step)
                            <li class="d-flex gap-3 {{ $loop->last ? '' : 'mb-3' }}">
                                <span class="badge rounded-pill bg-{{ $step['tone'] }} align-self-start mt-1">&nbsp;</span>
                                <div>
                                    <div class="fw-semibold">{{ $step['label'] }}</div>
                                    <div class="small text-secondary">
                                        {{ $step['at']?->format('d M Y, H:i') ?? 'Not yet' }}@if ($step['detail']) &middot; {{ $step['detail'] }}@endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Gateway events for this payment --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold">Gateway events</h5>
                        @can('admin.payments.events.view')
                            <a href="{{ route('admin.payments.events') }}" class="ms-auto small">All events</a>
                        @endcan
                    </div>
                    @forelse ($payment->events as $e)
                        <div class="py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <code>{{ $e->event }}</code>
                                @include('admin.payments.events._badge', ['value' => $e->outcome])
                                <span class="small text-secondary ms-auto">{{ $e->created_at?->format('d M Y, H:i') }}</span>
                            </div>
                            @if ($e->note)<div class="small text-secondary mt-1">{{ $e->note }}</div>@endif
                        </div>
                    @empty
                        <div class="text-secondary">No gateway events yet. Offline payments never have any.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            {{-- Booking money summary --}}
            @if ($booking)
                <div class="card rounded-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3 fw-semibold">Booking totals</h5>
                        <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Booking total</span><strong>{{ $booking->currency }} {{ number_format($booking->total_amount, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Net paid</span><strong>{{ $booking->currency }} {{ number_format($net, 2) }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-secondary">Payment status</span>@include('admin.bookings._badge', ['kind' => 'payment', 'value' => $booking->payment_status])</div>
                    </div>
                </div>
            @endif

            {{-- Refund history --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold">Refund history</h5>
                        @if ($refunds->isNotEmpty())
                            <span class="ms-auto small text-secondary">Refunded in total: <strong class="text-danger">{{ $booking->currency }} {{ number_format($refundedTotal, 2) }}</strong></span>
                        @endif
                    </div>
                    @forelse ($refunds as $r)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div>
                                @if ($r->id === $payment->id)
                                    <span class="fw-semibold">Refund #{{ $r->id }} (this page)</span>
                                @else
                                    <a href="{{ route('admin.payments.show', $r) }}">Refund #{{ $r->id }}</a>
                                @endif
                                <div class="small text-secondary">{{ $r->created_at?->format('d M Y, H:i') }} @include('admin.payments._badge', ['value' => $r->status])</div>
                                @if ($r->note)<div class="small text-secondary">{{ $r->note }}</div>@endif
                            </div>
                            <strong class="text-danger">-{{ $r->currency }} {{ number_format($r->amount, 2) }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary">No refunds on this booking.</div>
                    @endforelse
                </div>
            </div>

            {{-- Other payments on this booking --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Other payments on this booking</h5>
                    @forelse ($siblings as $s)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div>
                                <a href="{{ route('admin.payments.show', $s) }}">{{ $s->type === 'refund' ? 'Refund' : 'Payment' }} #{{ $s->id }}</a>
                                <div class="small text-secondary">{{ $s->created_at?->format('d M Y') }} @include('admin.payments._badge', ['value' => $s->status])</div>
                            </div>
                            <strong class="{{ $s->type === 'refund' ? 'text-danger' : '' }}">{{ $s->type === 'refund' ? '-' : '' }}{{ $s->currency }} {{ number_format($s->amount, 2) }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary">This is the only payment on the booking.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
