<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypts health notes that were saved before Day 24, when they were plain text.
 * Safe to run again: notes that are already encrypted are skipped. Run it once on every database (yours, Anakha's, live).
 */
class HealthEncryptExisting extends Command
{
    protected $signature = 'health:encrypt-existing {--dry-run : Only count, change nothing}';

    protected $description = 'Encrypt traveler health notes that are still stored as plain text';

    public function handle(): int
    {
        $plain = 0;
        $done = 0;
        $already = 0;

        DB::table('booking_travelers')->whereNotNull('health_notes')->where('health_notes', '!=', '')->orderBy('id')
            ->select(['id', 'health_notes'])->chunkById(200, function ($rows) use (&$plain, &$done, &$already) {
                foreach ($rows as $row) {
                    try {
                        Crypt::decryptString($row->health_notes);
                        $already++;

                        continue;
                    } catch (DecryptException) {
                        $plain++;
                    }

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    $text = trim($row->health_notes);
                    DB::table('booking_travelers')->where('id', $row->id)
                        ->update(['health_notes' => $text === '' ? null : Crypt::encryptString($text)]);
                    $done++;
                }
            });

        $this->info(($this->option('dry-run') ? 'Dry run. ' : '') . "Plain text: {$plain}. Encrypted now: {$done}. Already encrypted: {$already}.");

        return self::SUCCESS;
    }
}
