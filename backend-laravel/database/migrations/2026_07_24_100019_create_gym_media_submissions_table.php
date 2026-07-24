<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_media_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('submission_type');
            $table->integer('days_per_week')->nullable();
            $table->string('status')->default('analyzing');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at'], 'idx_gym_submissions_student');
            $table->index(['professional_id', 'status'], 'idx_gym_submissions_professional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_media_submissions');
    }
};
