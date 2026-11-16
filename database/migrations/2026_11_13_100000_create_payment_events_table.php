<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30)->nullable()->index();
            $table->string('external_id', 120)->nullable()->unique();   // the gateway's own event id, makes retries harmless
            $table->string('gateway_reference', 120)->nullable()->index();
            $table->string('event', 80)->index();                        // e.g. payment.succeeded
            $table->string('outcome', 20)->default('received')->index(); // received | processed | ignored | failed
            $table->string('note', 255)->nullable();                     // why it was ignored or failed
            $table->json('payload')->nullable();                         // webhook body with secrets and card data removed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
