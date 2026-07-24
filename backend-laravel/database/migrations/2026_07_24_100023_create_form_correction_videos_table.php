<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_correction_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('workout_id')->nullable()->constrained('workouts')->nullOnDelete();
            $table->foreignUuid('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->text('video_file_path');
            $table->decimal('video_duration_seconds', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at'], 'idx_form_videos_student');
            $table->index('exercise_id', 'idx_form_videos_exercise');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_correction_videos');
    }
};
