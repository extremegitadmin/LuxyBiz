<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->nullable();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160)->nullable();
            $table->string('slug', 180)->unique();
            $table->foreignId('objective_id')->nullable()->constrained('objectives')->nullOnDelete();
            $table->string('destination', 120)->nullable();
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->string('short_description', 400)->nullable();
            $table->longText('description')->nullable();
            $table->text('highlights')->nullable();      // one item per line
            $table->text('inclusions')->nullable();      // one item per line
            $table->text('exclusions')->nullable();      // one item per line
            $table->string('expert_name', 120)->nullable();
            $table->string('expert_role', 120)->nullable();
            $table->text('expert_bio')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('hero_image')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('status', 12)->default('draft')->index(); // draft | published | archived
            $table->timestamps();
        });

        Schema::create('tour_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->text('activities')->nullable();
            $table->string('meals', 120)->nullable();
            $table->string('stay', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('departs_on')->index();
            $table->unsignedSmallInteger('capacity')->default(12);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_departures');
        Schema::dropIfExists('tour_days');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('objectives');
    }
};
