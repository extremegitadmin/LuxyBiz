<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_id')
                ->constrained('cms_menus')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('cms_menu_items')
                ->cascadeOnDelete();

            $table->string('label',191);
            $table->string('url',191)->nullable();

            $table->string('target',191)->default('_self');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['menu_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
    }
};