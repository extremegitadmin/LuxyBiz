
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_id')
                ->constrained('cms_media')
                ->cascadeOnDelete();

            $table->morphs('attachable',191);

            $table->string('collection_name',191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_attachments');
    }
};
