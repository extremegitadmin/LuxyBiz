<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_trust_items', function (Blueprint $table) {
            $table->id();

            $table->string('title',191);
            $table->text('description')->nullable();
            $table->string('icon',191)->nullable();

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
        Schema::dropIfExists('cms_trust_items');
    }
};