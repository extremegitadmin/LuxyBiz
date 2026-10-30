<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 2. bookings table (Member 2: Bookings, Payments & Cancellations).
 *
 * tour_id and tour_departure_id are plain indexed columns for now: the tours and tour_departures
 * tables are created by Sini and may not be in your branch yet. Once they are merged to develop,
 * add the foreign keys in a NEW migration (see day2_guide_v3.md, "Later").
 *
 * Never edit this file after it is merged. Fix with a new migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();                      // e.g. LB-261103-AB12
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // customer
            $table->unsignedBigInteger('tour_id')->index();                 // FK added later (Sini's table)
            $table->unsignedBigInteger('tour_departure_id')->nullable()->index(); // FK added later
            $table->unsignedSmallInteger('travelers_count')->default(1);

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('insurance_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');                  // OPEN QUESTION G4: USD / INR / both

            // pending | confirmed | cancellation_requested | cancelled | completed
            $table->string('status', 24)->default('pending')->index();
            // unpaid | partial | paid | refunded
            $table->string('payment_status', 12)->default('unpaid')->index();
            $table->string('source', 10)->default('web')->index();          // web | csr
            $table->boolean('insurance_selected')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // CSR staff
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
