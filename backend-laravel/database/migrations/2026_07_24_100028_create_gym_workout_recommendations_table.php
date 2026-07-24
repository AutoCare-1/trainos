<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_workout_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('gym_media_submissions')->cascadeOnDelete();
            $table->foreignUuid('analysis_result_id')->constrained('gym_analysis_results')->cascadeOnDelete();
            $table->string('name');
            $table->string('split_type')->nullable();
            $table->text('reasoning')->nullable();
            $table->json('recommended_items');
            $table->string('approval_status')->default('pending');
            $table->foreignUuid('approved_workout_id')->nullable()->constrained('workouts')->nullOnDelete();
            $table->text('professional_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('submission_id', 'idx_gym_recommendations_submission');
            $table->index('approval_status', 'idx_gym_recommendations_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_workout_recommendations');
    }
};
