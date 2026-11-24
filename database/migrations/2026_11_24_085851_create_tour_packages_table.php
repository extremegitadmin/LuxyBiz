<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();

            $table->string('name', 50);
            $table->decimal('price', 10, 2)->default(0);

            $table->text('description')->nullable();
            $table->longText('inclusions')->nullable();
            $table->longText('exclusions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['tour_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_packages');
    }
};
