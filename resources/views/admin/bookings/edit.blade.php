@extends('admin.layouts.master')
@section('content')
    @php
        $statusColors = ['pending' => 'warning', 'confirmed' => 'success', 'cancellation_requested' => 'danger', 'cancelled' => 'secondary', 'completed' => 'info'];
        $payColors = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', 'refunded' => 'secondary'];
        $leadId = (int) old('lead_traveler_id', optional($booking->travelers->firstWhere('is_lead', true))->id ?? optional($booking->travelers->first())->id);
    @endphp

    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit {{ $booking->reference }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <!--end breadcrumb-->

    <div class="row">
        <div class="col-12 col-xl-10">

        @if (session('success'))
            <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0" role="alert">
                <div class="fw-semibold mb-1">Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('problem', $errors->count()) }} and save again:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


            {{-- Booking summary (read-only) --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <h4 class="mb-1 fw-semibold">{{ $booking->reference }}</h4>
                            <p class="mb-0 text-secondary">
                                Customer: {{ $booking->user->name ?? 'Guest' }}
                                @if ($booking->user?->email) &middot; {{ $booking->user->email }} @endif
                            </p>
                        </div>
                        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-{{ $statusColors[$booking->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                            <span class="badge bg-{{ $payColors[$booking->payment_status] ?? 'secondary' }}">{{ ucfirst($booking->payment_status) }}</span>
                            <span class="badge bg-light text-dark border">{{ strtoupper($booking->source) }}</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3 text-center text-sm-start">
                        <div class="col-6 col-md-3"><div class="text-secondary small">Travelers</div><div class="fw-semibold">{{ $booking->travelers_count }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Subtotal</div><div class="fw-semibold">{{ $booking->currency }} {{ number_format($booking->subtotal, 2) }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Insurance</div><div class="fw-semibold">{{ $booking->currency }} {{ number_format($booking->insurance_amount, 2) }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Total</div><div class="fw-semibold">{{ $booking->currency }} {{ number_format($booking->total_amount, 2) }}</div></div>
                    </div>
                    <div class="form-text mt-3">Prices, status and payments are not changed here. Use the payment and cancellation screens for those.</div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" novalidate>
                @csrf
                @method('PUT')

                {{-- Options --}}
                <div class="card rounded-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3 fw-semibold">Options</h5>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="insurance_selected" name="insurance_selected" value="1"
                                @checked(old('insurance_selected', $booking->insurance_selected))>
                            <label class="form-check-label" for="insurance_selected">Customer chose travel insurance</label>
                        </div>
                        <label class="form-label" for="notes">Internal notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Only staff can see this">{{ old('notes', $booking->notes) }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Travelers --}}
                @foreach ($booking->travelers as $i => $t)
                    @php
                        $p = 'travelers.' . $i . '.';
                        $passportHint = $t->passport_number ? 'On file: ending ' . substr($t->passport_number, -3) : 'Not on file';
                    @endphp
                    <div class="card rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <h5 class="mb-0 fw-semibold">Traveler {{ $i + 1 }}</h5>
                                <div class="form-check ms-auto mb-0">
                                    <input class="form-check-input" type="radio" name="lead_traveler_id" id="lead_{{ $t->id }}" value="{{ $t->id }}" @checked($leadId === $t->id)>
                                    <label class="form-check-label" for="lead_{{ $t->id }}">Lead traveler</label>
                                </div>
                            </div>
                            <input type="hidden" name="travelers[{{ $i }}][id]" value="{{ $t->id }}">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="t{{ $i }}_name">Full name <span class="text-danger">*</span></label>
                                    <input id="t{{ $i }}_name" name="travelers[{{ $i }}][full_name]" maxlength="140"
                                        class="form-control @error($p.'full_name') is-invalid @enderror"
                                        value="{{ old($p.'full_name', $t->full_name) }}">
                                    @error($p.'full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="t{{ $i }}_dob">Date of birth</label>
                                    <input type="date" id="t{{ $i }}_dob" name="travelers[{{ $i }}][date_of_birth]"
                                        class="form-control @error($p.'date_of_birth') is-invalid @enderror"
                                        value="{{ old($p.'date_of_birth', $t->date_of_birth?->format('Y-m-d')) }}">
                                    @error($p.'date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="t{{ $i }}_nat">Nationality</label>
                                    <input id="t{{ $i }}_nat" name="travelers[{{ $i }}][nationality]" maxlength="80"
                                        class="form-control @error($p.'nationality') is-invalid @enderror"
                                        value="{{ old($p.'nationality', $t->nationality) }}">
                                    @error($p.'nationality') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="t{{ $i }}_email">Email</label>
                                    <input type="email" id="t{{ $i }}_email" name="travelers[{{ $i }}][email]" maxlength="160"
                                        class="form-control @error($p.'email') is-invalid @enderror"
                                        value="{{ old($p.'email', $t->email) }}">
                                    @error($p.'email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="t{{ $i }}_phone">Phone</label>
                                    <input id="t{{ $i }}_phone" name="travelers[{{ $i }}][phone]" maxlength="30"
                                        class="form-control @error($p.'phone') is-invalid @enderror"
                                        value="{{ old($p.'phone', $t->phone) }}">
                                    @error($p.'phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="t{{ $i }}_passport">Passport number</label>
                                    <input id="t{{ $i }}_passport" name="travelers[{{ $i }}][passport_number]" maxlength="30" autocomplete="off"
                                        class="form-control @error($p.'passport_number') is-invalid @enderror"
                                        value="{{ old($p.'passport_number') }}" placeholder="Leave blank to keep the current number">
                                    <div class="form-text">{{ $passportHint }}</div>
                                    @error($p.'passport_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="t{{ $i }}_pexp">Passport expiry</label>
                                    <input type="date" id="t{{ $i }}_pexp" name="travelers[{{ $i }}][passport_expiry]"
                                        class="form-control @error($p.'passport_expiry') is-invalid @enderror"
                                        value="{{ old($p.'passport_expiry', $t->passport_expiry?->format('Y-m-d')) }}">
                                    @error($p.'passport_expiry') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12"><hr class="my-1"><div class="fw-semibold small text-secondary text-uppercase">Arrival (shown on the manifest)</div></div>
                                <div class="col-md-4">
                                    <label class="form-label" for="t{{ $i }}_adate">Arrival date</label>
                                    <input type="date" id="t{{ $i }}_adate" name="travelers[{{ $i }}][arrival_date]"
                                        class="form-control @error($p.'arrival_date') is-invalid @enderror"
                                        value="{{ old($p.'arrival_date', $t->arrival_date?->format('Y-m-d')) }}">
                                    @error($p.'arrival_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="t{{ $i }}_aflight">Flight</label>
                                    <input id="t{{ $i }}_aflight" name="travelers[{{ $i }}][arrival_flight]" maxlength="40"
                                        class="form-control @error($p.'arrival_flight') is-invalid @enderror"
                                        value="{{ old($p.'arrival_flight', $t->arrival_flight) }}" placeholder="e.g. EK 530">
                                    @error($p.'arrival_flight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="t{{ $i }}_atime">Arrival time</label>
                                    <input type="time" id="t{{ $i }}_atime" name="travelers[{{ $i }}][arrival_time]"
                                        class="form-control @error($p.'arrival_time') is-invalid @enderror"
                                        value="{{ old($p.'arrival_time', $t->arrival_time ? substr($t->arrival_time, 0, 5) : '') }}">
                                    @error($p.'arrival_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="t{{ $i }}_adetails">Arrival details</label>
                                    <input id="t{{ $i }}_adetails" name="travelers[{{ $i }}][arrival_details]" maxlength="255"
                                        class="form-control @error($p.'arrival_details') is-invalid @enderror"
                                        value="{{ old($p.'arrival_details', $t->arrival_details) }}" placeholder="Airport, pick-up point, anything the ground team should know">
                                    @error($p.'arrival_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12"><hr class="my-1"><div class="fw-semibold small text-secondary text-uppercase">Flags and health</div></div>
                                <div class="col-12">
                                    <label class="form-label" for="t{{ $i }}_flag">Traveler flag</label>
                                    <input id="t{{ $i }}_flag" name="travelers[{{ $i }}][flag_note]" maxlength="255"
                                        class="form-control @error($p.'flag_note') is-invalid @enderror"
                                        value="{{ old($p.'flag_note', $t->flag_note) }}" placeholder="e.g. Needs wheelchair assistance on arrival">
                                    @error($p.'flag_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="t{{ $i }}_health">Health notes <span class="text-secondary small">(restricted data)</span></label>
                                    <textarea id="t{{ $i }}_health" name="travelers[{{ $i }}][health_notes]" rows="2" maxlength="3000"
                                        class="form-control @error($p.'health_notes') is-invalid @enderror">{{ old($p.'health_notes', $t->health_notes) }}</textarea>
                                    @error($p.'health_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($booking->travelers->isEmpty())
                    <div class="alert alert-warning border-0">This booking has no travelers yet, so there is nothing to edit here.</div>
                @endif

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="submit" class="btn btn-primary px-4" @disabled($booking->travelers->isEmpty())>Save changes</button>
                    <a href="{{ Route::has('admin.bookings.index') ? route('admin.bookings.index') : url('/admin/dashboard') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
