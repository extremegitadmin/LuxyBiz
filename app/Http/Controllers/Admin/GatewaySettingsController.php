<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\Payments\Gateway;
use App\Services\Payments\GatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Gateway settings (M3). Every provider has its own card: switched on or off, mode, keys, webhook secret.
 * Routing decides which provider takes INR and which takes other currencies. Secrets are write-only.
 */
class GatewaySettingsController extends Controller
{
    /** What each provider calls its keys, and how a valid one starts (used to catch a wrong paste early). */
    public const KEY_INFO = [
        'razorpay' => [
            'public_label' => 'Key Id',
            'secret_label' => 'Key Secret',
            'public_pattern' => '/^rzp_(test|live)_[A-Za-z0-9]+$/',
            'public_hint' => 'Starts with rzp_test_ (sandbox) or rzp_live_ (live).',
            'secret_pattern' => null,
        ],
        'stripe' => [
            'public_label' => 'Publishable key',
            'secret_label' => 'Secret key',
            'public_pattern' => '/^pk_(test|live)_[A-Za-z0-9_]+$/',
            'public_hint' => 'Starts with pk_test_ (sandbox) or pk_live_ (live).',
            'secret_pattern' => '/^(sk|rk)_(test|live)_[A-Za-z0-9_]+$/',
        ],
    ];

    public function edit()
    {
        $cards = [];
        foreach (Gateway::providers() as $p) {
            $cards[$p] = [
                'label' => PaymentSetting::PROVIDERS[$p],
                'info' => self::KEY_INFO[$p],
                'enabled' => PaymentSetting::isEnabled($p),
                'mode' => (string) PaymentSetting::field($p, 'mode', 'sandbox'),
                'publicKey' => PaymentSetting::field($p, 'public_key'),
                'hasSecret' => PaymentSetting::hasField($p, 'secret_key'),
                'hasWebhook' => PaymentSetting::hasField($p, 'webhook_secret'),
                'ready' => Gateway::isReady($p),
                'webhookUrl' => PaymentSetting::webhookUrl($p),
                'events' => Gateway::webhookHandler($p)->events(),
                'updatedAt' => PaymentSetting::query()->where('key', 'like', 'gw.' . $p . '.%')->max('updated_at'),
            ];
        }

        return view('admin.settings.gateway', [
            'cards' => $cards,
            'routeInr' => PaymentSetting::routedProvider('INR'),
            'routeOther' => PaymentSetting::routedProvider('USD'),
            'labels' => PaymentSetting::PROVIDERS,
            'adapters' => Gateway::providers(),
        ]);
    }

    public function routing(Request $request)
    {
        $data = $request->validate([
            'route_inr' => ['required', Rule::in(Gateway::providers())],
            'route_other' => ['required', Rule::in(Gateway::providers())],
        ], [], ['route_inr' => 'INR provider', 'route_other' => 'provider for other currencies']);

        PaymentSetting::put('route.inr', $data['route_inr']);
        PaymentSetting::put('route.other', $data['route_other']);

        $this->audit('settings.gateway_routing_updated', ['inr' => $data['route_inr'], 'other' => $data['route_other']]);

        return redirect()->route('admin.settings.gateway')->with('success', 'Routing saved.');
    }

    public function update(Request $request, string $provider)
    {
        $info = self::KEY_INFO[$provider];
        $savedMode = (string) PaymentSetting::field($provider, 'mode', 'sandbox');
        $wasEnabled = PaymentSetting::isEnabled($provider);
        $hasSecret = PaymentSetting::hasField($provider, 'secret_key');
        $hasWebhook = PaymentSetting::hasField($provider, 'webhook_secret');
        $enabled = $request->boolean('enabled');

        // Keys are printable characters with no spaces. This catches a paste with a stray space or line break.
        $keyRule = ['nullable', 'string', 'max:255', 'regex:/^[\x21-\x7E]+$/'];

        $validator = Validator::make($request->all(), [
            'mode' => ['required', Rule::in(['sandbox', 'live'])],
            'public_key' => $keyRule,
            'secret_key' => $keyRule,
            'webhook_secret' => $keyRule,
            'confirm_live' => ['nullable', 'boolean'],
        ], [
            '*.regex' => 'The :attribute must not contain spaces or line breaks.',
        ], [
            'public_key' => strtolower($info['public_label']), 'secret_key' => strtolower($info['secret_label']), 'webhook_secret' => 'webhook secret',
        ]);

        $validator->after(function ($v) use ($request, $info, $savedMode, $hasSecret, $hasWebhook, $enabled) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $mode = $request->input('mode');
            $changed = $mode !== $savedMode;

            // Even when switched off, a key that is typed must look right and match the mode.
            $this->checkKeyShape($v, $request, 'public_key', $info['public_pattern'], $info['public_label'], $mode);
            if ($info['secret_pattern']) {
                $this->checkKeyShape($v, $request, 'secret_key', $info['secret_pattern'], $info['secret_label'], $mode);
            }
            if ($v->errors()->isNotEmpty() || ! $enabled) {
                return;
            }

            if (blank($request->input('public_key'))) {
                $v->errors()->add('public_key', 'Enter the ' . $info['public_label'] . '.');
            }
            // Keys belong to one mode. After a change, the old keys must not be reused.
            if (blank($request->input('secret_key')) && (! $hasSecret || $changed)) {
                $v->errors()->add('secret_key', $changed
                    ? 'You changed the mode, so enter the ' . $info['secret_label'] . ' again.'
                    : 'Enter the ' . $info['secret_label'] . '.');
            }
            if (blank($request->input('webhook_secret')) && (! $hasWebhook || $changed)) {
                $v->errors()->add('webhook_secret', $changed
                    ? 'You changed the mode, so enter the webhook secret again.'
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
            return back()->withErrors($validator, $provider)->withInput($request->except(['secret_key', 'webhook_secret']) + ['_provider' => $provider]);
        }

        $data = $validator->validated();
        $modeChanged = $data['mode'] !== $savedMode;

        PaymentSetting::putField($provider, 'enabled', $enabled ? '1' : '0');
        PaymentSetting::putField($provider, 'mode', $data['mode']);
        if (filled($data['public_key'] ?? null)) {
            PaymentSetting::putField($provider, 'public_key', $data['public_key']);
        }
        // Blank means "keep the saved secret". After a mode change the old secrets are dropped, they belong to the other mode.
        if ($modeChanged) {
            PaymentSetting::putField($provider, 'secret_key', null);
            PaymentSetting::putField($provider, 'webhook_secret', null);
        }
        foreach (['secret_key', 'webhook_secret'] as $field) {
            if (filled($data[$field] ?? null)) {
                PaymentSetting::putField($provider, $field, $data[$field]);
            }
        }

        // The audit entry says WHAT changed, never the values.
        $this->audit('settings.gateway_updated', [
            'provider' => $provider, 'enabled' => $enabled, 'mode' => $data['mode'],
            'was_enabled' => $wasEnabled, 'previous_mode' => $savedMode,
            'secret_key_changed' => filled($data['secret_key'] ?? null),
            'webhook_secret_changed' => filled($data['webhook_secret'] ?? null),
        ]);

        return redirect()->route('admin.settings.gateway')->with('success', PaymentSetting::PROVIDERS[$provider] . ' settings saved.' . ($enabled ? '' : ' It is switched off.'));
    }

    /** Checks the SAVED keys against the provider. Shows a message only, never a key. */
    public function test(string $provider)
    {
        try {
            $message = Gateway::forProvider($provider)->checkConnection();
        } catch (GatewayException $e) {
            return redirect()->route('admin.settings.gateway')->withErrors(['test_' . $provider => PaymentSetting::PROVIDERS[$provider] . ': ' . $e->getMessage()]);
        }

        return redirect()->route('admin.settings.gateway')->with('success', $message);
    }

    private function checkKeyShape($v, Request $request, string $field, string $pattern, string $label, string $mode): void
    {
        $value = (string) $request->input($field, '');
        if ($value === '') {
            return;
        }
        if (! preg_match($pattern, $value)) {
            $v->errors()->add($field, 'That does not look like a ' . $label . '. Check what was pasted.');

            return;
        }
        if (str_contains($value, '_live_') !== ($mode === 'live')) {
            $v->errors()->add($field, 'That ' . $label . ' is a ' . (str_contains($value, '_live_') ? 'live' : 'test') . ' key but the mode is ' . $mode . '.');
        }
    }

    /** The audit entry says WHAT changed, never a key. SafeAudit never lets the audit log break this screen. */
    private function audit(string $action, array $meta): void
    {
        \App\Support\SafeAudit::log($action, 'Payment gateway settings', $meta);
    }
}
