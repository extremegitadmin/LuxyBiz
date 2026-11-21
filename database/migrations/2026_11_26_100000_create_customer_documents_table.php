<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asha - Day 22 (G3). Customer documents: passport scans, visas, tickets, insurance.
 * The database only holds the facts about a file. The file itself lives on the private "documents" disk
 * under a random name, outside the public folder, so it can never be opened by a web address.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_documents')) {
            return;
        }

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_traveler_id')->nullable()->constrained('booking_travelers')->nullOnDelete();   // empty = belongs to the whole booking
            $table->string('type', 30);                      // passport, visa, id_card, insurance, flight_ticket, other
            $table->string('label', 120)->nullable();        // optional short note, e.g. "Page with photo"
            $table->string('original_name', 190);            // the name the file had when uploaded (shown to staff only)
            $table->string('stored_path', 255);              // random name on the private disk
            $table->string('disk', 40)->default('documents');
            $table->string('mime', 100);                     // detected from the file content, not from the name
            $table->unsignedInteger('size');                 // bytes
            $table->char('sha256', 64);                      // fingerprint: shows the file was not changed later
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
