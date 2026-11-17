<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Gateway settings (M3): provider, mode, keys and the webhook URL. Secrets are write-only. */
class GatewaySettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.gateway', [
            'providers' => PaymentSetting::PROVIDERS,
            'provider' => (string) PaymentSetting::get('gateway_provider', 'manual'),
            'mode' => (string) PaymentSetting::get('gateway_mode', 'sandbox'),
            'publicKey' => PaymentSetting::get('gateway_public_key'),
            'hasSecret' => PaymentSetting::has('gateway_secret_key'),
            'hasWebhook' => PaymentSetting::has('gateway_webhook_secret'),
            'webhookUrl' => PaymentSetting::webhookUrl(),
            'updatedAt' => PaymentSetting::query()->where('key', 'like', 'gateway_%')->max('updated_at'),
        ]);
    }

    public function update(Request $request)
    {
        $savedProvider = (string) PaymentSetting::get('gateway_provider', 'manual');
        $savedMode = (string) PaymentSetting::get('gateway_mode', 'sandbox');
        $hasSecret = PaymentSetting::has('gateway_secret_key');
        $hasWebhook = PaymentSetting::has('gateway_webhook_secret');

        // Keys are printable characters with no spaces. This catches a paste with a stray space or line break.
        $keyRule = ['nullable', 'string', 'max:255', 'regex:/^[\x21-\x7E]+$/'];

        $validator = Validator::make($request->all(), [
            'provider' => ['required', Rule::in(array_keys(PaymentSetting::PROVIDERS))],
            'mode' => ['required', Rule::in(['sandbox', 'live'])],
            'public_key' => $keyRule,
            'secret_key' => $keyRule,
            'webhook_secret' => $keyRule,
            'confirm_live' => ['nullable', 'boolean'],
        ], [
            '*.regex' => 'The :attribute must not contain spaces or line breaks.',
        ], [
            'public_key' => 'public key', 'secret_key' => 'secret key', 'webhook_secret' => 'webhook secret',
        ]);

        $validator->after(function ($v) use ($request, $savedProvider, $savedMode, $hasSecret, $hasWebhook) {
            if ($v->errors()->isNotEmpty() || $request->input('provider') === 'manual') {
                return;
            }

            $provider = $request->input('provider');
            $mode = $request->input('mode');
            $changed = $provider !== $savedProvider || $mode !== $savedMode;

            if (blank($request->input('public_key'))) {
                $v->errors()->add('public_key', 'Enter the public key for this provider.');
            }
            // Keys belong to one provider and one mode. After a change, the old keys must not be reused.
            if (blank($request->input('secret_key')) && (! $hasSecret || $changed)) {
                $v->errors()->add('secret_key', $changed
                    ? 'You changed the provider or mode, so enter its secret key again.'
                    : 'Enter the secret key.');
            }
            if (blank($request->input('webhook_secret')) && (! $hasWebhook || $changed)) {
                $v->errors()->add('webhook_secret', $changed
                    ? 'You changed the provider or mode, so enter its webhook secret again.'
                    : 'Enter the webhook secret.');
            }
            if ($mode === 'live') {
                if (! $request->isSecure()) {
                    $v->errors()->add('mode', 'Live keys can only be saved over a secure (https) connection.');
                }
                if ($savedMode !== 'live' && ! $request->boolean('confirm_live')) {
                    $v->errors()->add('confirm_live', 'Tick the box to confirm a sandbox booking has been tested successfully.');
                }
            }
        });

        if ($validator->fails()) {
            // Never send secrets back to the form or into the session.
            return back()->withErrors($validator)->withInput($request->except(['secret_key', 'webhook_secret']));
        }

        $data = $validator->validated();
        $isManual = $data['provider'] === 'manual';

        PaymentSetting::put('gateway_provider', $data['provider']);
        PaymentSetting::put('gateway_mode', $data['mode']);

        if ($isManual) {
            // Manual needs no keys, so none are kept.
            PaymentSetting::forget('gateway_public_key');
            foreach (PaymentSetting::SECRET_KEYS as $key) {
                PaymentSetting::forget($key);
            }
        } else {
            PaymentSetting::put('gateway_public_key', $data['public_key']);
            // Blank means "keep the saved secret".
            if (filled($data['secret_key'] ?? null)) {
                PaymentSetting::putSecret('gateway_secret_key', $data['secret_key']);
            }
            if (filled($data['webhook_secret'] ?? null)) {
                PaymentSetting::putSecret('gateway_webhook_secret', $data['webhook_secret']);
            }
        }

        // The audit entry says WHAT changed, never the values.
        $this->audit('settings.gateway_updated', [
            'provider' => $data['provider'], 'mode' => $data['mode'],
            'secret_key_changed' => filled($data['secret_key'] ?? null),
            'webhook_secret_changed' => filled($data['webhook_secret'] ?? null),
            'previous_provider' => $savedProvider, 'previous_mode' => $savedMode,
        ]);

        return redirect()->route('admin.settings.gateway')->with('success', $isManual && ($hasSecret || $hasWebhook)
            ? 'Gateway settings saved. Saved keys were removed because Manual needs none.'
            : 'Gateway settings saved.');
    }

    /** Anakha's audit helper may not be merged yet, so call it only when it exists. */
    private function audit(string $action, array $meta): void
    {
        if (class_exists(\App\Support\Audit::class)) {
            \App\Support\Audit::log($action, null, $meta);
        }
    }
}
