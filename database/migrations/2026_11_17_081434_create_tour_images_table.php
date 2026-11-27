<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('image_path',191);
            $table->string('alt_text',191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_images');
    }
};
