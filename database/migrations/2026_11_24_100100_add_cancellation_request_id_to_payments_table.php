<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Day 20: which cancellation request a refund row belongs to (so one refund is never counted for two requests). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'cancellation_request_id')) {
            return;
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cancellation_request_id')->nullable()->after('booking_id')->constrained('cancellation_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'cancellation_request_id')) {
            return;
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_request_id');
        });
    }
};
