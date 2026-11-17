<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tours', 'tour_category_id')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->foreignId('tour_category_id')
                    ->nullable()
                    ->after('objective_id')
                    ->constrained('tour_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tours', 'tour_category_id')) {
            Schema::table('tours', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tour_category_id');
            });
        }
    }
};
