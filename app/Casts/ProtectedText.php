<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Text that is stored encrypted (health notes, Day 24), so a database dump or backup does not show it.
 *  - blank text is stored as NULL, so "has a note" can be answered without decrypting;
 *  - rows written before this cast existed are still plain text: they are read as they are and become encrypted
 *    the next time they are saved, or all at once with  php artisan health:encrypt-existing .
 */
class ProtectedText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return (string) $value;   // an older plain-text row
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : Crypt::encryptString($text);
    }
}
