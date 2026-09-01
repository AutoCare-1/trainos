<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copos de água por dia. Uma linha por aluno por dia (unique), incrementada
 * conforme ele registra — não um evento por copo, porque o que interessa é o
 * total do dia e assim o streak/gamificação lê uma linha só.
 *
 * Entra junto com o diário alimentar por ser o item de maior adesão com menor
 * esforço: um toque, sem digitar nada. E hidratação é orientação geral, longe
 * da fronteira da prescrição dietética.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hydration_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('data');
            $table->unsignedSmallInteger('copos')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['student_id', 'data'], 'uq_hydration_logs_student_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydration_logs');
    }
};
