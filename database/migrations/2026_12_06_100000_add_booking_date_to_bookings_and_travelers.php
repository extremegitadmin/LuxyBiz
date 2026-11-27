<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('booking_date')->nullable()->index()->after('currency');
        });
        Schema::table('booking_travelers', function (Blueprint $table) {
            $table->date('booking_date')->nullable()->index();
        });

        // Fill existing rows from created_at (date part only)
        DB::table('bookings')->whereNull('booking_date')
            ->update(['booking_date' => DB::raw('DATE(created_at)')]);
        DB::table('booking_travelers')->whereNull('booking_date')
            ->update(['booking_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('booking_date'));
        Schema::table('booking_travelers', fn (Blueprint $t) => $t->dropColumn('booking_date'));
    }
};