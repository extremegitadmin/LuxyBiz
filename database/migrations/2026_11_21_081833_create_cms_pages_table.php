<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title',191);
            $table->string('slug',191)->unique();

            $table->longText('body')->nullable();
            $table->json('content')->nullable();

            $table->string('status',191)->default('draft');
            $table->boolean('is_homepage')->default(false);

            $table->string('meta_title',191)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords',191)->nullable();
            $table->string('canonical_url',191)->nullable();

            $table->foreignId('featured_media_id')
                ->nullable()
                ->constrained('cms_media')
                ->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};