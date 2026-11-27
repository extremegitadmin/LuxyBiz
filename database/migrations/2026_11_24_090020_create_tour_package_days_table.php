<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_package_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_package_id')
                ->constrained('tour_packages')
                ->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->string('title', 160);
            $table->longText('description')->nullable();
            $table->longText('activities')->nullable();
            $table->string('meals', 120)->nullable();
            $table->string('stay', 160)->nullable();
            $table->timestamps();
            $table->unique(['tour_package_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_days');
    }
};
