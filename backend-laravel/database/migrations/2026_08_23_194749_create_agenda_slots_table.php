<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horário fixo semanal do personal — pensado pra organização da própria
     * rotina dele, não um registro de frequência do aluno (isso já vive em
     * training_sessions/checkins). student_id é opcional de propósito: muito
     * personal só dá consultoria (nunca treina presencialmente com o aluno)
     * ou quer bloquear um horário só pra si, sem vincular ninguém — por isso
     * o `titulo` livre existe, pra rotular o horário quando não há aluno.
     */
    public function up(): void
    {
        Schema::create('agenda_slots', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('titulo')->nullable();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo...6=sábado, igual Date.getDay() do JS
            $table->time('hora');
            $table->unsignedInteger('duracao_minutos')->default(60);
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('professional_id', 'idx_agenda_slots_professional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_slots');
    }
};
