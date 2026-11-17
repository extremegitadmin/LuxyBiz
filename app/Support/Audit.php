<?php
namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(
        string $action,
        string $description,
        array $properties = []
    ): void {
        try {
            if (!DB::getSchemaBuilder()->hasTable('activity_logs')) {
                return;
            }

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'action' => $action,
                'description' => $description,
                'properties' => json_encode($properties),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
