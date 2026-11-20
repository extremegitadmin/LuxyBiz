<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The travel credit table from the Day 1 design (schema.sql "credit shell"). Issued here when an approved cancellation
 * ends as credit instead of a refund (Day 20). Sini builds the credit screens (F3 to F5) on this table.
 * It does nothing if the table already exists, so it is safe if Sini created it first. Check with Sini before running it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_credits')) {
            return;
        }
        Schema::create('travel_credits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('cancellation_request_id')->nullable()->constrained('cancellation_requests')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->char('currency', 3);
            $table->date('expires_at')->nullable();
            $table->string('status', 10)->default('active');          // active | redeemed | expired | void
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        // Left alone on purpose: it may hold real customer credit, and may belong to Sini's screens.
    }
};
