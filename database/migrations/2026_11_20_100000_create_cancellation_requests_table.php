<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per cancellation request on a booking (Day 16). Days 17 to 21 add the detail screen, override, approve/reject
 * and "mark refunded", so their columns are created now. The refund calculation is saved as a snapshot (JSON) so later
 * changes to the cancellation rules never change what was shown for an old request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();                       // CR-261120-AB12
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 10)->default('staff');                  // customer | staff
            $table->text('reason')->nullable();
            $table->date('requested_on');                                    // the day the customer asked: the refund is worked out from this day

            // pending | approved | rejected | refunded | credited
            $table->string('status', 12)->default('pending');

            $table->string('currency', 3)->default('USD');
            $table->decimal('calculated_refund', 10, 2)->default(0);         // what the rules said when the request was opened
            $table->json('calculation')->nullable();                         // the full snapshot (RefundCalculation::toArray())
            $table->decimal('final_refund', 10, 2)->nullable();              // set by an override (Day 18) or on approval
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('completed_at')->nullable();                   // refund paid or credit issued

            $table->timestamps();

            $table->index(['status', 'requested_on']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
    }
};
