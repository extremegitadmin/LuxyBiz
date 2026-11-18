<?php

namespace App\Support;

/**
 * Writes to the team's audit log (App\Support\Audit) without ever breaking the screen that called it.
 * If the audit class is missing, its table is missing, or its signature is different, the entry is skipped
 * and the problem is reported to the Laravel log. A booking edit or a payment link must never fail because of an audit entry.
 */
class SafeAudit
{
    /** @param array<string, mixed> $properties  What changed. Never put secrets, links or card data in here. */
    public static function log(string $action, string $description, array $properties = []): void
    {
        if (! class_exists(Audit::class)) {
            return;
        }

        try {
            Audit::log($action, $description, $properties);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
