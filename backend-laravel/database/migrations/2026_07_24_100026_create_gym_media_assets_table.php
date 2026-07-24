<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('gym_media_submissions')->cascadeOnDelete();
            $table->string('asset_type');
            $table->text('file_path');
            $table->integer('frame_index')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('submission_id', 'idx_gym_assets_submission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_media_assets');
    }
};
