<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 25. Every time health information is shown or changed, rows are written here BEFORE the page is shown.
 * One row per traveler. Rows are only ever added. No foreign keys on purpose: the history stays when a traveler,
 * a booking or a user is removed. It never holds the health text itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('health_access_logs')) {
            return;
        }

        Schema::create('health_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('booking_traveler_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 140)->nullable();      // copy of the name at that time
            $table->string('action', 20)->index();             // view, edit
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_access_logs');
    }
};
