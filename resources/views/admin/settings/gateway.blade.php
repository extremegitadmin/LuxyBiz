@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Settings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment gateways</li>
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

    <div class="alert alert-info border-0">
        Card details are entered on the gateway's own page. This system stores only references.
        Secret keys are saved <strong>encrypted</strong> and are <strong>never shown again</strong>, so keep your own copy.
    </div>

    {{-- Routing: which provider takes which currency --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-1 fw-semibold">Which gateway takes which currency</h5>
            <p class="small text-secondary">A booking's currency decides the gateway. A gateway only takes payments once it is switched on below and its keys are saved.</p>
            <form method="POST" action="{{ route('admin.settings.gateway.routing') }}" class="row g-3 align-items-end">
                @csrf @method('PUT')
                <div class="col-md-4">
                    <label class="form-label" for="route_inr">Bookings in INR</label>
                    <select id="route_inr" name="route_inr" class="form-select">
                        @foreach ($adapters as $p)
                            <option value="{{ $p }}" @selected(old('route_inr', $routeInr) === $p)>{{ $labels[$p] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="route_other">Bookings in USD and other currencies</label>
                    <select id="route_other" name="route_other" class="form-select">
                        @foreach ($adapters as $p)
                            <option value="{{ $p }}" @selected(old('route_other', $routeOther) === $p)>{{ $labels[$p] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button type="submit" class="btn btn-primary px-4">Save routing</button></div>
            </form>
            @foreach (['INR' => $routeInr, 'USD and others' => $routeOther] as $what => $p)
                @if (! $cards[$p]['ready'])
                    <div class="small text-warning mt-2">{{ $what }}: {{ $labels[$p] }} is not ready yet. Staff cannot create online payment links for these bookings until it is switched on with a saved secret key.</div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="row g-3">
        @foreach ($cards as $p => $c)
            @php
                $bag = $errors->getBag($p);
                $mine = old('_provider') === $p;
                $mode = $mine ? old('mode', $c['mode']) : $c['mode'];
                $on = $mine ? (bool) old('enabled') : $c['enabled'];
                $info = $c['info'];
            @endphp
            <div class="col-12 col-xl-6">
                <div class="card rounded-4 h-100" id="gw-{{ $p }}">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-semibold">{{ $c['label'] }}</h5>
                            <span class="badge bg-{{ $c['ready'] ? 'success' : 'secondary' }}">{{ $c['ready'] ? 'Ready' : ($c['enabled'] ? 'Switched on, keys missing' : 'Off') }}</span>
                        </div>

                        @if ($bag->any())
                            <div class="alert alert-danger border-0 py-2"><ul class="mb-0 ps-3">@foreach ($bag->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
                        @endif

                        <form method="POST" action="{{ route('admin.settings.gateway.update', $p) }}" class="row g-3" autocomplete="off">
                            @csrf @method('PUT')
                            <input type="hidden" name="_provider" value="{{ $p }}">

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enabled-{{ $p }}" name="enabled" value="1" @checked($on)>
                                    <label class="form-check-label" for="enabled-{{ $p }}">Switched on</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mode-{{ $p }}">Mode</label>
                                <select id="mode-{{ $p }}" name="mode" class="form-select" data-saved="{{ $c['mode'] }}" data-provider="{{ $p }}">
                                    <option value="sandbox" @selected($mode === 'sandbox')>Sandbox (testing, no real money)</option>
                                    <option value="live" @selected($mode === 'live')>Live (real money)</option>
                                </select>
                                <div class="form-text">Sandbox and live use different keys.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="public_key-{{ $p }}">{{ $info['public_label'] }}</label>
                                <input id="public_key-{{ $p }}" name="public_key" value="{{ $mine ? old('public_key') : $c['publicKey'] }}" maxlength="255"
                                    class="form-control" autocomplete="off" spellcheck="false">
                                <div class="form-text">{{ $info['public_hint'] }} Safe to show; it identifies your account.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="secret_key-{{ $p }}">{{ $info['secret_label'] }}</label>
                                <input id="secret_key-{{ $p }}" type="password" name="secret_key" maxlength="255" class="form-control"
                                    autocomplete="new-password" spellcheck="false"
                                    placeholder="{{ $c['hasSecret'] ? 'Saved (blank keeps it)' : 'Paste it here' }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="webhook_secret-{{ $p }}">Webhook secret</label>
                                <input id="webhook_secret-{{ $p }}" type="password" name="webhook_secret" maxlength="255" class="form-control"
                                    autocomplete="new-password" spellcheck="false"
                                    placeholder="{{ $c['hasWebhook'] ? 'Saved (blank keeps it)' : 'Paste it here' }}">
                            </div>

                            <div class="col-12" id="live-confirm-{{ $p }}" @style(['display: none' => $mode !== 'live' || $c['mode'] === 'live'])>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="confirm_live-{{ $p }}" name="confirm_live">
                                    <label class="form-check-label" for="confirm_live-{{ $p }}">I have completed a successful sandbox booking test and I want to switch to Live.</label>
                                </div>
                            </div>

                            <div class="col-12 d-flex gap-2 align-items-center flex-wrap">
                                <button type="submit" class="btn btn-primary px-4">Save {{ $c['label'] }}</button>
                                <span class="small text-secondary">Changing the mode asks for the secrets again.</span>
                            </div>
                        </form>

                        @if ($c['ready'])
                            <form method="POST" action="{{ route('admin.settings.gateway.test', $p) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">Test saved keys</button>
                                <span class="form-text ms-2">Asks {{ $c['label'] }} if the saved keys work. Nothing is charged.</span>
                            </form>
                        @endif

                        <hr>
                        <div class="small fw-semibold mb-1">Webhook address</div>
                        <div class="input-group input-group-sm">
                            <input id="webhook-url-{{ $p }}" class="form-control" value="{{ $c['webhookUrl'] }}" readonly>
                            <button type="button" class="btn btn-outline-secondary copy-webhook" data-target="webhook-url-{{ $p }}">Copy</button>
                        </div>
                        @if (! str_starts_with($c['webhookUrl'], 'https://'))
                            <div class="form-text text-warning mt-1">This address is not https and may not be reachable from the internet. Gateways need a public https address.</div>
                        @endif
                        <div class="small mt-2">Events to tick in the {{ $c['label'] }} dashboard (nothing else is needed):</div>
                        <ul class="small text-secondary mb-1">
                            @foreach ($c['events'] as $evt)
                                <li><code>{{ $evt }}</code></li>
                            @endforeach
                        </ul>
                        <div class="form-text">What arrives is listed in <a href="{{ route('admin.payments.events') }}">Webhook events</a>.</div>
                        @if ($c['updatedAt'])
                            <div class="form-text">Last changed {{ \Illuminate\Support\Carbon::parse($c['updatedAt'])->format('d M Y, H:i') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12 col-xl-6">
            <div class="card rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold">PayPal</h5>
                        <span class="badge bg-secondary">Not built yet</span>
                    </div>
                    <p class="text-secondary mb-0">PayPal is added once the client decides between PayPal and Stripe for US dollars.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            document.querySelectorAll('select[data-provider]').forEach(function (sel) {
                var box = document.getElementById('live-confirm-' + sel.dataset.provider);
                sel.addEventListener('change', function () {
                    box.style.display = (sel.value === 'live' && sel.dataset.saved !== 'live') ? '' : 'none';
                });
            });
            document.querySelectorAll('.copy-webhook').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.dataset.target);
                    input.select();
                    try { navigator.clipboard.writeText(input.value); } catch (e) { document.execCommand('copy'); }
                    btn.textContent = 'Copied';
                });
            });
        })();
    </script>
@endsection
