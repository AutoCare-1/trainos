<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complementa a anamnese inicial (par_q_answers/health_notes já existentes) com as
     * perguntas do formulário em papel que ainda não tinham campo no app: data de
     * nascimento e o restante das seções (histórico de atividade física, objetivos,
     * condições de saúde detalhadas, estilo de vida, motivação/preferências,
     * disponibilidade, histórico familiar) — tudo isso guardado num único JSON, mesmo
     * padrão já usado por par_q_answers, pra não precisar de uma coluna por pergunta.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('objective');
            $table->json('anamnese')->nullable()->after('health_notes');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'anamnese']);
        });
    }
};
