<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Services\Payments\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Where a payment provider calls us: POST /webhooks/payments/{provider}. No login: the signature is the proof.
 * Answers: 400 = signature not valid (do not retry), 503 = not set up yet or a fault (provider retries), 200 = received.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $handler = Gateway::webhookHandler($provider);
        if (! $handler) {
            return response()->json(['received' => false, 'error' => 'unknown_provider'], 404);
        }

        $config = PaymentSetting::config($provider);
        $secret = (string) ($config['webhook_secret'] ?? '');

        if (! $config['enabled'] || $secret === '') {
            return response()->json(['received' => false, 'error' => 'not_configured'], 503);
        }

        // The raw body, exactly as sent: the signature covers the original bytes, not the parsed JSON.
        $raw = $request->getContent();

        if (! $handler->signatureValid($raw, $request, $secret)) {
            // Never log the body or the headers: they hold signed customer data.
            Log::warning('Payment webhook rejected: signature not valid (' . $provider . ').');

            return response()->json(['received' => false, 'error' => 'invalid_signature'], 400);
        }

        $event = json_decode($raw, true);
        $id = is_array($event) ? $handler->eventId($event, $request) : null;
        if (! is_array($event) || blank($id) || $handler->eventName($event) === '') {
            return response()->json(['received' => false, 'error' => 'invalid_payload'], 400);
        }

        try {
            $handler->handle($event, $config['mode'], $id);
        } catch (\Throwable $e) {
            // Let the provider retry. Only the class and message go to the log, never the event body.
            Log::error('Payment webhook failed (' . $provider . '): ' . $e::class . ': ' . $e->getMessage());

            return response()->json(['received' => false, 'error' => 'server_error'], 500);
        }

        return response()->json(['received' => true]);
    }
}
