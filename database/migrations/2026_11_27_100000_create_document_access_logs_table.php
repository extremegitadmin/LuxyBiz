<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 23. Every time a customer document is opened or downloaded, one row is written here BEFORE the file is sent.
 * Rows are only ever added. There are no foreign keys on purpose: when a document is deleted or a user is removed,
 * the history of who opened what stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_access_logs')) {
            return;
        }

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 140)->nullable();      // copy of the name at that time
            $table->string('action', 20)->index();             // view, download, missing, integrity_failed
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
    }
};
