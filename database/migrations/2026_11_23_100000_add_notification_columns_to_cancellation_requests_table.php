<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Day 19: remember whether the customer was told about the decision, and why not if the email failed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->timestamp('customer_notified_at')->nullable()->after('completed_at');
            $table->string('notification_error', 255)->nullable()->after('customer_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->dropColumn(['customer_notified_at', 'notification_error']);
        });
    }
};
