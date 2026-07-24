<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_analysis_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('video_id')->constrained('form_correction_videos')->cascadeOnDelete();
            $table->text('amplitude_assessment')->nullable();
            $table->text('posture_assessment')->nullable();
            $table->text('tempo_assessment')->nullable();
            $table->text('compensations')->nullable();
            $table->text('safety_notes')->nullable();
            $table->json('three_key_feedback')->default(new \Illuminate\Database\Query\Expression("'[]'"));
            $table->string('analysis_status')->default('completed');
            $table->timestamp('created_at')->useCurrent();

            $table->index('video_id', 'idx_form_analysis_video');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_analysis_results');
    }
};
