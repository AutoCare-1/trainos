<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_feedback_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->integer('feedback_count')->default(0);
            $table->timestamp('last_feedback_at')->nullable();

            $table->unique(['student_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_feedback_history');
    }
};
