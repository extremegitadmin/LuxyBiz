<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->longText('overview')->nullable();
            $table->boolean('senior_friendly')->default(false);
            $table->text('good_to_know')->nullable();
            $table->json('gallery')->nullable();
            $table->decimal('price_inr', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'overview',
                'senior_friendly',
                'good_to_know',
                'gallery',
                'price_inr',
            ]);
        });
    }
};
