<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Day 20: how an approved request ended (refund paid, or travel credit instead), and a note about it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->string('resolution', 10)->nullable()->after('status');     // refund | credit
            $table->text('completion_note')->nullable()->after('decision_note');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'completion_note']);
        });
    }
};
