<?php

namespace App\Console\Commands;

use App\Models\PaymentSetting;
use Illuminate\Console\Command;

/**
 * Day 13, part A. Saves harmless DUMMY keys so the whole payment screen and the webhook can be tested
 * before the client gives real sandbox keys. Dummy keys can NOT create a real payment page at Razorpay or Stripe:
 * use payments:simulate to test what happens after a payment instead.
 */
class PaymentsSandboxSetup extends Command
{
    protected $signature = 'payments:sandbox-setup {--force : Overwrite keys that look real}';

    protected $description = 'Save dummy sandbox keys for Razorpay and Stripe (local testing only)';

    public const WEBHOOK_SECRET = 'whsec_DUMMY_local_testing_only';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Not allowed in production.');

            return self::FAILURE;
        }

        $dummy = [
            'razorpay' => ['public_key' => 'rzp_test_DUMMYKEY000001', 'secret_key' => 'DUMMY_razorpay_secret_000001'],
            'stripe' => ['public_key' => 'pk_test_DUMMYKEY000001', 'secret_key' => 'sk_test_DUMMYKEY000000000000001'],
        ];

        foreach ($dummy as $provider => $keys) {
            $existing = (string) (PaymentSetting::config($provider)['secret_key'] ?? '');
            if ($existing !== '' && ! str_contains($existing, 'DUMMY') && ! $this->option('force')) {
                $this->warn("$provider already has keys that look real. Left alone (use --force to replace them).");

                continue;
            }
            PaymentSetting::putField($provider, 'enabled', '1');
            PaymentSetting::putField($provider, 'mode', 'sandbox');
            PaymentSetting::putField($provider, 'public_key', $keys['public_key']);
            PaymentSetting::putField($provider, 'secret_key', $keys['secret_key']);
            PaymentSetting::putField($provider, 'webhook_secret', self::WEBHOOK_SECRET);
            $this->info("$provider: dummy sandbox keys saved.");
        }

        $this->line('Next: php artisan payments:simulate paid');

        return self::SUCCESS;
    }
}
