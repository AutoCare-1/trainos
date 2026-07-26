<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Preferência de notificação por personal e tipo. student_id nullable de
    // propósito: hoje toda linha fica com student_id vazio (preferência global do
    // personal pra aquele tipo); no futuro dá pra criar uma linha específica pra um
    // aluno sem precisar de migration nova — o código já sabe checar "existe
    // configuração específica desse aluno? senão usa a global".
    //
    // Sem unique constraint aqui de propósito: MySQL trata NULL como distinto em
    // índice único, então (professional_id, tipo_chave, NULL) não impediria linhas
    // globais duplicadas. A escrita sempre passa por updateOrCreate() no controller,
    // que garante uma linha só por combinação — ver NotificacaoController.
    public function up(): void
    {
        Schema::create('professional_notification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('tipo_chave');
            $table->foreign('tipo_chave')->references('chave')->on('tipos_notificacao')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->boolean('enabled');
            $table->timestamps();

            $table->index(['professional_id', 'tipo_chave', 'student_id'], 'idx_notif_settings_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_notification_settings');
    }
};
