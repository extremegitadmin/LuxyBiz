<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_homepage_settings', function (Blueprint $table) {
            $table->id();

            $table->string('hero_title',191)->nullable();
            $table->text('hero_subtitle',191)->nullable();

            $table->foreignId('hero_media_id')
                ->nullable()
                ->constrained('cms_media')
                ->nullOnDelete();

            $table->string('hero_button_text',191)->nullable();
            $table->string('hero_button_url',191)->nullable();

            // IDs of existing tours selected for the homepage.
            $table->json('featured_tour_ids')->nullable();

            $table->string('featured_section_title',191)->nullable();
            $table->text('featured_section_description')->nullable();

            $table->string('trust_section_title',191)->nullable();
            $table->text('trust_section_description')->nullable();

            $table->string('primary_cta_title',191)->nullable();
            $table->string('primary_cta_button_text',191)->nullable();
            $table->string('primary_cta_button_url',191)->nullable();

            $table->string('secondary_cta_title',191)->nullable();
            $table->string('secondary_cta_button_text',191)->nullable();
            $table->string('secondary_cta_button_url',191)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_homepage_settings');
    }
};