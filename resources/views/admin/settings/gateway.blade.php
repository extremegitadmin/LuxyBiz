@extends('admin.layouts.master')
@section('content')
    @php
        $selProvider = old('provider', $provider);
        $selMode = old('mode', $mode);
        $ready = $provider !== 'manual' && $hasSecret && $hasWebhook;
    @endphp

    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Settings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment gateway</li>
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

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="alert alert-info border-0">
                        Card details are entered on the gateway's own page. This system stores only references.
                        Secret keys are saved <strong>encrypted</strong> and are <strong>never shown again</strong>, so keep your own copy.
                    </div>

                    <form method="POST" action="{{ route('admin.settings.gateway.update') }}" class="row g-3" autocomplete="off">
                        @csrf @method('PUT')

                        <div class="col-md-6">
                            <label class="form-label" for="provider">Provider</label>
                            <select id="provider" name="provider" class="form-select @error('provider') is-invalid @enderror">
                                @foreach ($providers as $key => $label)
                                    <option value="{{ $key }}" @selected($selProvider === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Until the connection for the chosen provider is built, online payments stay off and staff record payments manually.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="mode">Mode</label>
                            <select id="mode" name="mode" class="form-select @error('mode') is-invalid @enderror">
                                <option value="sandbox" @selected($selMode === 'sandbox')>Sandbox (testing, no real money)</option>
                                <option value="live" @selected($selMode === 'live')>Live (real money)</option>
                            </select>
                            @error('mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Sandbox and live use different keys.</div>
                        </div>

                        <div id="keys" class="row g-3 m-0 p-0">
                            <div class="col-12">
                                <label class="form-label" for="public_key">Public (publishable) key</label>
                                <input id="public_key" name="public_key" value="{{ old('public_key', $publicKey) }}" maxlength="255"
                                    class="form-control @error('public_key') is-invalid @enderror" autocomplete="off" spellcheck="false">
                                @error('public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Safe to show in the website. It identifies your account.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="secret_key">Secret key</label>
                                <input id="secret_key" type="password" name="secret_key" maxlength="255"
                                    class="form-control @error('secret_key') is-invalid @enderror" autocomplete="new-password" spellcheck="false"
                                    placeholder="{{ $hasSecret ? 'Saved. Leave blank to keep it' : 'Paste the secret key' }}">
                                @error('secret_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="webhook_secret">Webhook signing secret</label>
                                <input id="webhook_secret" type="password" name="webhook_secret" maxlength="255"
                                    class="form-control @error('webhook_secret') is-invalid @enderror" autocomplete="new-password" spellcheck="false"
                                    placeholder="{{ $hasWebhook ? 'Saved. Leave blank to keep it' : 'Paste the webhook secret' }}">
                                @error('webhook_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-12" id="live-confirm" @style(['display: none' => $selMode !== 'live' || $mode === 'live'])>
                            <div class="form-check">
                                <input class="form-check-input @error('confirm_live') is-invalid @enderror" type="checkbox" value="1" id="confirm_live" name="confirm_live">
                                <label class="form-check-label" for="confirm_live">I have completed a successful sandbox booking test and I want to switch to Live.</label>
                                @error('confirm_live')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-primary px-4">Save gateway settings</button>
                            <span class="small text-secondary">Changing the provider or mode asks for the secret keys again.</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            {{-- Status --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Status</h5>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Provider</span><strong>{{ $providers[$provider] ?? ucfirst($provider) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Mode</span>
                        <span class="badge bg-{{ $mode === 'live' ? 'danger' : 'info' }}">{{ ucfirst($mode) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Secret key</span>
                        <span class="badge bg-{{ $hasSecret ? 'success' : 'secondary' }}">{{ $hasSecret ? 'Saved' : 'Not saved' }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Webhook secret</span>
                        <span class="badge bg-{{ $hasWebhook ? 'success' : 'secondary' }}">{{ $hasWebhook ? 'Saved' : 'Not saved' }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Connection built</span>
                        <span class="badge bg-{{ $hasAdapter ? 'success' : 'warning' }}">{{ $hasAdapter ? 'Yes' : 'Not yet' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Keys ready</span>
                        <span class="badge bg-{{ $ready ? 'success' : 'warning' }}">{{ $ready ? 'Yes' : 'No' }}</span></div>
                    @if ($canTest)
                        <form method="POST" action="{{ route('admin.settings.gateway.test') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">Test saved keys</button>
                            <div class="form-text">Asks the provider if the saved secret key works. Nothing is charged.</div>
                        </form>
                    @endif
                    @if ($updatedAt)
                        <div class="form-text mt-3">Last changed {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('d M Y, H:i') }}</div>
                    @endif
                </div>
            </div>

            {{-- Webhook URL --}}
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold">Webhook URL</h5>
                    <p class="small text-secondary">Paste this address into your gateway's dashboard so it can tell us about payments and refunds.</p>
                    <div class="input-group">
                        <input id="webhook-url" class="form-control" value="{{ $webhookUrl }}" readonly>
                        <button type="button" class="btn btn-outline-secondary" id="copy-webhook">Copy</button>
                    </div>
                    @if (! str_starts_with($webhookUrl, 'https://'))
                        <div class="form-text text-warning mt-2">This address is not https and may not be reachable from the internet. Gateways need a public https address.</div>
                    @endif
                    <div class="form-text mt-2">The receiving end is added in a later step. Events will then appear in <a href="{{ route('admin.payments.events') }}">Webhook events</a>.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var mode = document.getElementById('mode');
            var confirmBox = document.getElementById('live-confirm');
            var saved = @json($mode);
            mode.addEventListener('change', function () {
                confirmBox.style.display = (mode.value === 'live' && saved !== 'live') ? '' : 'none';
            });
            var provider = document.getElementById('provider');
            var keys = document.getElementById('keys');
            function toggleKeys() { keys.style.display = provider.value === 'manual' ? 'none' : ''; }
            provider.addEventListener('change', toggleKeys); toggleKeys();
            document.getElementById('copy-webhook').addEventListener('click', function () {
                var input = document.getElementById('webhook-url');
                input.select();
                try { navigator.clipboard.writeText(input.value); } catch (e) { document.execCommand('copy'); }
                this.textContent = 'Copied';
            });
        })();
    </script>
@endsection
