<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_testimonials', function (Blueprint $table) {
            $table->id();

            $table->string('customer_name',191);
            $table->string('designation',191)->nullable();
            $table->string('location',191)->nullable();

            $table->text('testimonial');
            $table->unsignedTinyInteger('rating')->default(5);

            $table->foreignId('media_id')
                ->nullable()
                ->constrained('cms_media')
                ->nullOnDelete();

            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_testimonials');
    }
};