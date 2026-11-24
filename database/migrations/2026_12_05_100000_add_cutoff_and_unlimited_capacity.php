<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client rules (23 Nov): booking closes N days before departure (default 5, set per tour),
 * and a departure has no seat limit unless a capacity is entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tours', 'booking_cutoff_days')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->unsignedSmallInteger('booking_cutoff_days')->nullable();   // null = use config('booking.cutoff_days')
            });
        }

        Schema::table('tour_departures', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->default(null)->change();   // null = unlimited
        });
    }

    public function down(): void
    {
        \DB::table('tour_departures')->whereNull('capacity')->update(['capacity' => 12]);
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->default(12)->nullable(false)->change();
        });
        if (Schema::hasColumn('tours', 'booking_cutoff_days')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->dropColumn('booking_cutoff_days');
            });
        }
    }
};
