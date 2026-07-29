<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anamnese de revisão — respondida pelo aluno quando um treino com prazo de
     * validade vence (ver workouts.expires_at). Um registro por treino vencido
     * (unique em workout_id evita pedir revisão de novo do mesmo treino). As
     * respostas ficam num único JSON, mesmo padrão de students.anamnese, em vez
     * de uma coluna por pergunta.
     */
    public function up(): void
    {
        Schema::create('workout_reviews', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('workout_id')->unique()->constrained('workouts')->cascadeOnDelete();
            $table->unsignedInteger('tempo_acompanhamento_semanas')->nullable();
            $table->json('respostas');
            $table->timestamp('created_at')->useCurrent();

            $table->index('student_id', 'idx_workout_reviews_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_reviews');
    }
};
