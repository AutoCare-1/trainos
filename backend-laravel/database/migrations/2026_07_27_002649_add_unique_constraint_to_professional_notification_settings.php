<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Item 11 de uma revisão externa: MySQL trata NULL como distinto em índice
    // único, então (professional_id, tipo_chave, NULL) não impedia linhas globais
    // duplicadas no banco — só o updateOrCreate() da aplicação evitava isso, "só
    // disciplina de código". Coluna gerada (VIRTUAL — STORED esbarra num limite
    // real do InnoDB, "Cannot add foreign key constraint" ao tentar
    // ADD COLUMN ... STORED numa tabela com FK, confirmado testando local)
    // substitui NULL por um sentinela fixo só pra fins de unicidade, sem abrir
    // mão do nullable/FK real em student_id — preferência por-aluno de verdade
    // continua íntegra.
    private const SENTINELA_GLOBAL = '00000000-0000-0000-0000-000000000000';

    public function up(): void
    {
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->uuid('student_id_chave')
                ->virtualAs("coalesce(student_id, '".self::SENTINELA_GLOBAL."')")
                ->after('student_id');
        });

        // Ordem importa: o índice velho sustenta a FK de professional_id até o novo
        // existir — trocar na ordem errada trava com "needed in a foreign key
        // constraint" (confirmado testando local).
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->unique(['professional_id', 'tipo_chave', 'student_id_chave'], 'idx_notif_settings_unique');
        });
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->dropIndex('idx_notif_settings_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->index(['professional_id', 'tipo_chave', 'student_id'], 'idx_notif_settings_lookup');
        });
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->dropUnique('idx_notif_settings_unique');
        });
        Schema::table('professional_notification_settings', function (Blueprint $table) {
            $table->dropColumn('student_id_chave');
        });
    }
};
