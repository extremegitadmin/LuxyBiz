<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('public');

            $table->string('mime_type')->nullable();
            $table->string('file_type')->default('image');
            $table->unsignedBigInteger('file_size')->default(0);

            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('file_type');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};