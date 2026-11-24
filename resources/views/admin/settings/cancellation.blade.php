@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Settings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Cancellation rules</li>
                </ol>
            </nav>
        </div>
    </div>
    <!--end breadcrumb-->

    @if (session('success'))
        <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $policy->confirmed)
        <div class="alert alert-warning border-0">
            <strong>Placeholder rules.</strong> These values are assumptions and are not yet confirmed by LuxyBiz.
            Refunds are calculated from them, so confirm them with the client before real cancellations are handled.
        </div>
    @else
        <div class="alert alert-success border-0">Rules confirmed by LuxyBiz{{ $policy->confirmedAt ? ' on ' . \Illuminate\Support\Carbon::parse($policy->confirmedAt)->format('d M Y') : '' }}.</div>
    @endif

    @can('admin.settings.cancellation.edit')
    <form method="POST" action="{{ route('admin.settings.cancellation.update') }}">
        @csrf @method('PUT')
    @endcan
        <fieldset @cannot('admin.settings.cancellation.edit') disabled @endcannot>

        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="mb-1 fw-semibold">Refund by days before departure</h5>
                <p class="small text-secondary">A customer who cancels <em>at least</em> this many days before departure gets this percent back. After the departure date the refund is always 0%. The last row must be 0 days.</p>

                <table class="table align-middle" style="max-width: 560px;" id="tiers">
                    <thead><tr><th>At least (days before departure)</th><th>Refund %</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($tiers as $i => $t)
                        <tr>
                            <td><input type="number" min="0" max="730" class="form-control" name="tiers[{{ $i }}][min_days]" value="{{ $t['min_days'] }}"></td>
                            <td><input type="number" min="0" max="100" class="form-control" name="tiers[{{ $i }}][percent]" value="{{ $t['percent'] }}"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-tier" title="Remove row">&times;</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @can('admin.settings.cancellation.edit')
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="add-tier">+ Add tier</button>
                @endcan

                <div class="mt-3 small">
                    <span class="text-secondary">Reads as:</span> {{ implode(' · ', $policy->describeTiers()) }}
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="mb-3 fw-semibold">What the refund is calculated from</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="base">Refund base</label>
                        <select id="base" name="base" class="form-select">
                            @foreach ($bases as $key => $label)
                                <option value="{{ $key }}" @selected(old('base', $policy->base) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fixed_fee">Fixed cancellation fee, taken before the percent (in the booking's currency, 0 for none)</label>
                        <input type="number" step="0.01" min="0" id="fixed_fee" name="fixed_fee" class="form-control" value="{{ old('fixed_fee', number_format($policy->fixedFee, 2, '.', '')) }}">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="insurance_refundable" name="insurance_refundable" value="1" @checked(old('insurance_refundable', $policy->insuranceRefundable))>
                            <label class="form-check-label" for="insurance_refundable">Travel insurance is refundable (leave off if insurance is non-refundable once issued)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (config('booking.credits_enabled'))
        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="mb-3 fw-semibold">Travel credit instead of a refund</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="credit_percent">Credit as % of the refund amount</label>
                        <input type="number" min="0" max="200" id="credit_percent" name="credit_percent" class="form-control" value="{{ old('credit_percent', $policy->creditPercent) }}">
                        <div class="form-text">100 = same value as the refund. Above 100 = a bonus.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="credit_months">Credit valid for (months)</label>
                        <input type="number" min="1" max="60" id="credit_months" name="credit_months" class="form-control" value="{{ old('credit_months', $policy->creditMonths) }}">
                    </div>
                </div>
            </div>
        </div>
        @else
            {{-- Travel credits are switched off (refund only). The values are kept so nothing is lost. --}}
            <input type="hidden" name="credit_percent" value="{{ old('credit_percent', $policy->creditPercent) }}">
            <input type="hidden" name="credit_months" value="{{ old('credit_months', $policy->creditMonths) }}">
        @endif

        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="mb-3 fw-semibold">Refund timing shown to the customer</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="refund_days_min">Shortest (working days)</label>
                        <input type="number" min="0" max="90" id="refund_days_min" name="refund_days_min" class="form-control" value="{{ old('refund_days_min', $policy->refundDaysMin) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="refund_days_max">Longest (working days)</label>
                        <input type="number" min="0" max="90" id="refund_days_max" name="refund_days_max" class="form-control" value="{{ old('refund_days_max', $policy->refundDaysMax) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card rounded-4">
            <div class="card-body p-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmed" name="confirmed" value="1" @checked(old('confirmed', $policy->confirmed))>
                    <label class="form-check-label" for="confirmed"><strong>These rules are confirmed by LuxyBiz</strong> (turns off the placeholder warning)</label>
                </div>
            </div>
        </div>
        </fieldset>

    @can('admin.settings.cancellation.edit')
        <button type="submit" class="btn btn-primary px-4 mb-4">Save cancellation rules</button>
    </form>
    @endcan

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">Try it</h5>
            <form method="GET" action="{{ route('admin.settings.cancellation') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label" for="try_days">Cancelled this many days before departure</label>
                    <input type="number" min="0" max="3650" id="try_days" name="try_days" class="form-control" value="{{ request('try_days') }}">
                </div>
                <div class="col-auto"><button class="btn btn-outline-primary">Check</button></div>
            </form>
            @if ($tryResult)
                <p class="mt-3 mb-0">At <strong>{{ $tryResult['days'] }}</strong> days before departure the refund is <strong>{{ $tryResult['percent'] }}%</strong> (saved rules).</p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.querySelector('#tiers tbody');
    var add = document.getElementById('add-tier');
    if (!body) return;
    function renumber() {
        Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr, i) {
            var inputs = tr.querySelectorAll('input');
            inputs[0].name = 'tiers[' + i + '][min_days]';
            inputs[1].name = 'tiers[' + i + '][percent]';
        });
    }
    if (add) add.addEventListener('click', function () {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="number" min="0" max="730" class="form-control"></td>' +
            '<td><input type="number" min="0" max="100" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger remove-tier" title="Remove row">&times;</button></td>';
        body.appendChild(tr);
        renumber();
    });
    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-tier')) { e.target.closest('tr').remove(); renumber(); }
    });
})();
</script>
@endpush
