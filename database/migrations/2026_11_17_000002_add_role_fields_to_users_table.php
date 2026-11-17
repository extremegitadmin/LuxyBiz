<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('roles')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('users', 'is_super')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_super')
                    ->default(false)
                    ->after('role_id');
            });
        }

        // is_active already exists in your database.
        // phone and role are also existing columns.
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        if (Schema::hasColumn('users', 'is_super')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_super');
            });
        }
    }
};
