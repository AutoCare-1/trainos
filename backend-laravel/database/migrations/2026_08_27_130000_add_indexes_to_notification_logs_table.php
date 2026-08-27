<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para a contagem diária por destinatário do CoordenadorNotificacoes
 * (LIMITE_DIARIO_POR_DESTINATARIO): filtra por student_id/professional_id +
 * enviado_em do dia + tipo_chave, e roda a cada ciclo de 15 min do Scheduler,
 * pra cada candidato avaliado.
 *
 * A FK já dá índice em student_id/professional_id sozinha, mas ela para no
 * primeiro campo — a varredura de enviado_em continuava sendo feita linha a
 * linha dentro do aluno. Numa tabela que só cresce (nunca há expurgo), isso
 * piora todo dia.
 *
 * O outro acesso à tabela (dedup por dedup_key, em ProcessNotifications) já é
 * servido pelo unique que a coluna tem desde a criação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index(['student_id', 'enviado_em'], 'idx_notification_logs_student_enviado');
            $table->index(['professional_id', 'enviado_em'], 'idx_notification_logs_professional_enviado');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('idx_notification_logs_student_enviado');
            $table->dropIndex('idx_notification_logs_professional_enviado');
        });
    }
};
