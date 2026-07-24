<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();

            $table->index('workout_id', 'idx_training_sessions_workout');
            // Índice composto — pensado pra achar rápido a última sessão concluída de
            // cada aluno (dashboard "Meu Negócio"), espelha a migration 020 do Node.
            $table->index(['student_id', 'status', 'finished_at'], 'idx_training_sessions_student_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
