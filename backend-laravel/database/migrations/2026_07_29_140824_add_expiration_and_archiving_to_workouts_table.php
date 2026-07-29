<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * duration_weeks/expires_at: prazo de validade opcional definido pelo personal
     * ao enviar o treino — expires_at é calculado uma vez (sent_at + N semanas) e
     * guardado, em vez de recalculado toda hora, pra não depender de duration_weeks
     * mudar de sentido se o treino for reenviado depois.
     *
     * archived_at: arquivamento manual — só o personal decide, nunca automático
     * por expirar. Um treino "sent" e não arquivado é o que aparece pro aluno
     * escolher no portal; arquivado continua existindo (histórico), só some da
     * lista de escolha.
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedInteger('duration_weeks')->nullable()->after('sent_at');
            $table->date('expires_at')->nullable()->after('duration_weeks');
            $table->timestamp('archived_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn(['duration_weeks', 'expires_at', 'archived_at']);
        });
    }
};
