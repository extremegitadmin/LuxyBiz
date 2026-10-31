<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 2. booking_travelers table.
 * passport_number is TEXT because the model will store it with the 'encrypted' cast (Day 3).
 * health_notes is restricted data: only admin / fulfillment may read it, every access is logged (Days 24-25).
 * OPEN QUESTION R2: who supplies the arrival details, and when? Columns stay nullable until answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);

            $table->string('full_name', 140);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 80)->nullable();
            $table->text('passport_number')->nullable();                    // encrypted
            $table->date('passport_expiry')->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('arrival_details', 255)->nullable();             // free text
            $table->date('arrival_date')->nullable();
            $table->string('arrival_flight', 40)->nullable();
            $table->time('arrival_time')->nullable();

            $table->text('health_notes')->nullable();                       // restricted
            $table->string('flag_note', 255)->nullable();                   // e.g. needs wheelchair assistance (manifest)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_travelers');
    }
};
