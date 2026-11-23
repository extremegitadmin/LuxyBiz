<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 29. A "payment request" is the customer payment link staff send for an assisted booking.
 * The link holds a random token. Only a fingerprint (SHA-256) of the token is stored, so a copy of the database
 * does not contain working links. The customer opens the link, and only then is the provider's hosted payment page created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_requests')) {
            Schema::create('payment_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3);
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sent_to', 190)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('send_error', 255)->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->unsignedInteger('open_count')->default(0);
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['booking_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('payments', 'payment_request_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_request_id')->nullable()->index()->after('booking_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'payment_request_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_request_id');
            });
        }
        Schema::dropIfExists('payment_requests');
    }
};
