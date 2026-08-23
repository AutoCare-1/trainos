<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exceção pontual de um slot numa data específica — só existe uma linha
     * aqui quando o personal mexeu naquele dia (trocou o aluno, marcou vago,
     * ou registrou presença/falta). Sem linha, a ocorrência usa o padrão do
     * slot direto (ver AgendaController::semana). unique(slot_id, data)
     * porque só pode haver UMA exceção por slot por dia.
     */
    public function up(): void
    {
        Schema::create('agenda_ocorrencias', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('slot_id')->constrained('agenda_slots')->cascadeOnDelete();
            $table->date('data');
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('titulo')->nullable();
            $table->string('presenca')->nullable(); // presente|falta
            $table->string('observacao')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['slot_id', 'data'], 'uq_agenda_ocorrencias_slot_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_ocorrencias');
    }
};
