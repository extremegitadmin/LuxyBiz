<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('tour_date')->nullable()->index();
        });
        Schema::table('booking_travelers', function (Blueprint $table) {
            $table->date('tour_date')->nullable()->index();
        });

        // Fill existing rows (works on MySQL and SQLite)
        DB::statement('UPDATE bookings SET tour_date = (SELECT departs_on FROM tour_departures WHERE tour_departures.id = bookings.tour_departure_id)');
        DB::statement('UPDATE booking_travelers SET tour_date = (SELECT tour_date FROM bookings WHERE bookings.id = booking_travelers.booking_id)');
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('tour_date'));
        Schema::table('booking_travelers', fn (Blueprint $t) => $t->dropColumn('tour_date'));
    }
};