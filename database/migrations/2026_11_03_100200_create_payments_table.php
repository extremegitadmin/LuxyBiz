<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 2. payments table. One row per charge or refund.
 * NEVER store card number, CVV or expiry. Only gateway references.
 * card_brand / card_last4 are display values returned by the gateway (optional).
 * OPEN QUESTION G1: which gateway. 'gateway' is a plain string so any provider fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('charge');                  // charge | refund
            $table->string('channel', 14)->default('offline');              // offline | online | csr_link | csr_hosted
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_reference', 120)->nullable()->index();  // gateway token / charge id only
            $table->string('gateway_session_id', 120)->nullable()->index();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method', 30)->nullable();                       // card, bank transfer, cash ...
            $table->string('status', 12)->default('pending');               // pending | succeeded | failed | refunded
            $table->string('failure_code', 80)->nullable();
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); // staff who recorded it
            $table->timestamps();

            $table->index(['booking_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
