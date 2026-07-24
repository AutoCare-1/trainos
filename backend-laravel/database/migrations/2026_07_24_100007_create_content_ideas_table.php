<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_ideas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->uuid('batch_id');
            $table->string('format');
            $table->string('title');
            $table->text('description');
            $table->text('caption_suggestion');
            $table->boolean('saved')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['professional_id', 'created_at'], 'idx_content_ideas_professional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ideas');
    }
};
